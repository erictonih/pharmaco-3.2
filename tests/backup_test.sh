#!/usr/bin/env bash
# Sauvegarde / restauration : export, base vidée, restauration par fusion, validations, cron protégé, accès Super Admin.
set -u; cd "$(dirname "$0")/.."
pkill -f "php -S 127.0.0.1:809[12]" 2>/dev/null; sleep 0.3
rm -f /tmp/fake_turso.db /tmp/fake_turso_down; rm -rf "${TMPDIR:-/tmp}/pharmaco_cache"
export APP_SECRET=$(openssl rand -hex 32) APP_ENV=local TURSO_DATABASE_URL=http://127.0.0.1:8091 TURSO_AUTH_TOKEN=test-token VIP_DAYS=30 MVOLA_NUMBER="034 00 000 00" CRON_SECRET=0123456789abcdef0123456789abcdef
export SHEETS_FIXTURE=$PWD/tests/fixtures/mpox_synthetic.json
export APP_USERS_JSON=$(php -r 'echo json_encode([["username"=>"root","hash"=>password_hash("RootPassword123",PASSWORD_DEFAULT),"role"=>"Super Admin"]]);')
(setsid php -S 127.0.0.1:8091 tests/mock/fake_turso.php >/dev/null 2>&1 &)
(setsid php -S 127.0.0.1:8092 -t public tools/dev-router.php >/dev/null 2>&1 &); sleep 1
B=http://127.0.0.1:8092; fail=0; PW=MotDePasse-1234; D=/tmp/bk_test; rm -rf $D; mkdir $D
chk(){ if [ "$2" = "$3" ]; then echo "  ok   $1"; else echo "  FAIL $1: $2 (attendu $3)"; fail=1; fi; }
code(){ curl -s -o /dev/null -w '%{http_code}' "$@"; }
login(){ J=$1; rm -f $J; T=$(curl -s -c $J -b $J $B/Login.php | grep -o 'name="csrf" value="[^"]*"' | sed 's/.*value="//;s/"//'); curl -s -o /dev/null -w "%{http_code}" -c $J -b $J -d "csrf=$T&username=$2&password=$3" $B/Login.php; }
csrf(){ curl -s -b $1 $B/$2 | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
sql(){ php -r '$d=new PDO("sqlite:/tmp/fake_turso.db");'"$1"; }
login $D/a root RootPassword123 >/dev/null; TA=$(csrf $D/a utilisateurs.php)
mk(){ curl -s -o /dev/null -b $D/a -d "csrf=$TA&action=create&username=$1&role=$2&password=$PW&confirm=$PW&type=${3:-permanent}&expires=${4:-}" $B/utilisateurs.php; }
mk vip1 VIP; mk ana1 Analyste; mk temp1 Visualiseur limite "2030-01-01T10:00"
# un abonnement + une transaction validée
login $D/v vip1 $PW >/dev/null; TV=$(csrf $D/v paiement.php)
curl -s -o /dev/null -b $D/v -d "csrf=$TV&reference=MP260109.1234.A1" $B/paiement.php
ID=$(sql 'echo $d->query("SELECT id FROM app_payments")->fetchColumn();')
curl -s -o /dev/null -b $D/a -d "csrf=$TA&action=accept&id=$ID" $B/paiements.php
UNTIL=$(sql 'echo $d->query("SELECT paid_until FROM app_subscriptions")->fetchColumn();')
chk "état initial : 3 comptes, 1 abonnement" "$(sql 'echo $d->query("SELECT COUNT(*) FROM app_users")->fetchColumn();')/$(sql 'echo $d->query("SELECT COUNT(*) FROM app_subscriptions")->fetchColumn();')" "3/1"
# export
chk "export sans CSRF refusé" "$(code -b $D/a -d 'action=export' $B/utilisateurs.php)" 403
curl -s -b $D/a -d "csrf=$TA&action=export" $B/utilisateurs.php -o $D/backup.json
chk "export : JSON valide, 3 comptes" "$(python3 -c "import json;d=json.load(open('$D/backup.json'));print(d['format'],len(d['users']),len(d['payments']),len(d['subscriptions']))")" "pharmaco-backup 3 1 1"
login $D/n ana1 $PW >/dev/null
chk "export interdit à un non Super Admin" "$(code -b $D/n -d 'action=export' $B/utilisateurs.php)$(curl -s -b $D/n -d 'action=export' $B/utilisateurs.php | grep -c pharmaco-backup)" "3020"
# sinistre : la base est vidée
sql '$d->exec("DELETE FROM app_users; DELETE FROM app_subscriptions; DELETE FROM app_payments");'
chk "base vidée" "$(sql 'echo $d->query("SELECT COUNT(*) FROM app_users")->fetchColumn();')" 0
rm -rf "${TMPDIR:-/tmp}/pharmaco_cache"
imp(){ curl -s -o /dev/null -w '%{redirect_url}' -b $D/a -F "csrf=$TA" -F action=import -F "backup=@$1" $B/utilisateurs.php | sed "s#$B##"; }
chk "import sans CSRF refusé" "$(code -b $D/a -F action=import -F "backup=@$D/backup.json" $B/utilisateurs.php)" 403
echo '{"format":"autre"}' > $D/bad.json
chk "fichier invalide refusé" "$(curl -s -b $D/a -F "csrf=$TA" -F action=import -F "backup=@$D/bad.json" $B/utilisateurs.php | grep -c 'Fichier de sauvegarde invalide')" 1
python3 -c "
import json
d=json.load(open('$D/backup.json'));d['users'][0]['hash']='texte-clair';json.dump(d,open('$D/bad2.json','w'))
d=json.load(open('$D/backup.json'));d['users'][0]['role']='Root';json.dump(d,open('$D/bad3.json','w'))"
chk "mot de passe en clair refusé" "$(curl -s -b $D/a -F "csrf=$TA" -F action=import -F "backup=@$D/bad2.json" $B/utilisateurs.php | grep -c 'compte mal formé')" 1
chk "rôle inconnu refusé" "$(curl -s -b $D/a -F "csrf=$TA" -F action=import -F "backup=@$D/bad3.json" $B/utilisateurs.php | grep -c 'compte mal formé')" 1
chk "rien n a été écrit par les fichiers refusés" "$(sql 'echo $d->query("SELECT COUNT(*) FROM app_users")->fetchColumn();')" 0
R=$(imp $D/backup.json); chk "restauration" "${R%%&k=*}" "/utilisateurs.php?ok=restored&u=3&s=1&p=1"
chk "comptes restaurés" "$(sql 'echo $d->query("SELECT COUNT(*) FROM app_users")->fetchColumn();')" 3
chk "abonnement restauré à l identique" "$(sql 'echo $d->query("SELECT paid_until FROM app_subscriptions")->fetchColumn();')" "$UNTIL"
chk "transaction restaurée (confirmée)" "$(sql 'echo $d->query("SELECT status FROM app_payments")->fetchColumn();')" confirmed
chk "compte limité : expiration conservée" "$(sql 'echo $d->query("SELECT expires_at FROM app_users WHERE username=\"temp1\"")->fetchColumn();')" "$(python3 -c "import json;print([u for u in json.load(open('$D/backup.json'))['users'] if u['username']=='temp1'][0]['expires_at'])")"
chk "le VIP retrouve ses accès après restauration" "$(login $D/v2 vip1 $PW >/dev/null; code -b $D/v2 $B/sitrep.php)" 200
sql '$d->exec("UPDATE app_subscriptions SET paid_until=\"2099-01-01 00:00:00\"");'
R=$(imp $D/backup.json); chk "second import : rien d ajouté" "${R%%&k=*}" "/utilisateurs.php?ok=restored&u=0&s=1&p=0"
chk "un abonnement n est jamais raccourci" "$(sql 'echo $d->query("SELECT paid_until FROM app_subscriptions")->fetchColumn();')" "2099-01-01 00:00:00"
chk "aucun doublon" "$(sql 'echo $d->query("SELECT COUNT(*) FROM app_users")->fetchColumn();')" 3
chk "sauvegarde de sécurité avant chaque restauration" "$(sql 'echo $d->query("SELECT COUNT(*) FROM app_backups WHERE created_by LIKE \"%avant restauration%\"")->fetchColumn();')" 2
chk "cron sans jeton : refusé" "$(code $B/sauvegarde.php)" 401
chk "cron mauvais jeton : refusé" "$(code -H 'Authorization: Bearer faux' $B/sauvegarde.php)" 401
chk "cron avec jeton : sauvegarde" "$(curl -s -H "Authorization: Bearer $CRON_SECRET" $B/sauvegarde.php)" '{"ok":true,"users":3,"payments":1,"subscriptions":1}'
chk "cron : instantané enregistré" "$(sql 'echo $d->query("SELECT COUNT(*) FROM app_backups WHERE created_by=\"automatique\"")->fetchColumn();')" 1
chk "page : instantanés listés" "$([ $(curl -s -b $D/a $B/utilisateurs.php | grep -c 'automatique') -ge 1 ] && echo ok)" ok
SID=$(sql 'echo $d->query("SELECT id FROM app_backups WHERE created_by=\"automatique\"")->fetchColumn();')
chk "téléchargement d un instantané" "$(curl -s -b $D/a -d "csrf=$TA&action=snapshot_dl&id=$SID" $B/utilisateurs.php | python3 -c 'import json,sys;print(json.load(sys.stdin)["format"])')" pharmaco-backup
for i in $(seq 1 16); do curl -s -o /dev/null -H "Authorization: Bearer $CRON_SECRET" $B/sauvegarde.php; done
chk "14 sauvegardes conservées au maximum" "$(sql 'echo $d->query("SELECT COUNT(*) FROM app_backups")->fetchColumn();')" 14
pkill -f 'php -S 127.0.0.1:8092'; sleep 0.5; (setsid php -S 127.0.0.1:8092 -t public tools/dev-router.php >/dev/null 2>&1 &); sleep 1
chk "les données survivent à un redémarrage du site" "$(login $D/v3 vip1 $PW)" 302
pkill -f "php -S 127.0.0.1:809[12]"; rm -rf $D
[ $fail -eq 0 ] && echo "TOUT OK" || echo "ÉCHEC"; exit $fail
