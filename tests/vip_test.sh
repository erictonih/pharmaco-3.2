#!/usr/bin/env bash
# Module payant VIP (virement MVola déclaré + validation admin) : parcours HTTP complet contre un faux Turso.
set -u; cd "$(dirname "$0")/.."
pkill -f "php -S 127.0.0.1:809[12]" 2>/dev/null; sleep 0.3
rm -f /tmp/fake_turso.db /tmp/fake_turso_down; rm -rf "${TMPDIR:-/tmp}/pharmaco_cache"
export APP_SECRET=$(openssl rand -hex 32) APP_ENV=local TURSO_DATABASE_URL=http://127.0.0.1:8091 TURSO_AUTH_TOKEN=test-token VIP_PRICE=50000 VIP_DAYS=30 MVOLA_NUMBER="034 00 000 00" MVOLA_NAME="DVSSER"
export SHEETS_FIXTURE=$PWD/tests/fixtures/mpox_synthetic.json
export APP_USERS_JSON=$(php -r 'echo json_encode([["username"=>"root","hash"=>password_hash("RootPassword123",PASSWORD_DEFAULT),"role"=>"Super Admin"]]);')
(setsid php -S 127.0.0.1:8091 tests/mock/fake_turso.php >/dev/null 2>&1 &)
(setsid php -S 127.0.0.1:8092 -t public tools/dev-router.php >/dev/null 2>&1 &); sleep 1
B=http://127.0.0.1:8092; fail=0; PW=MotDePasse-1234; C="${TMPDIR:-/tmp}/pharmaco_cache"
chk(){ if [ "$2" = "$3" ]; then echo "  ok   $1"; else echo "  FAIL $1: $2 (attendu $3)"; fail=1; fi; }
loc(){ curl -s -o /dev/null -w '%{http_code} %{redirect_url}' "$@" | sed "s#http://127.0.0.1:8092##"; }
code(){ curl -s -o /dev/null -w '%{http_code}' "$@"; }
login(){ J=$1; rm -f $J; T=$(curl -s -c $J -b $J $B/Login.php | grep -o 'name="csrf" value="[^"]*"' | sed 's/.*value="//;s/"//'); curl -s -o /dev/null -w "%{http_code}" -c $J -b $J -d "csrf=$T&username=$2&password=$3" $B/Login.php; }
csrf(){ curl -s -b $1 $B/$2 | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
sql(){ php -r '$d=new PDO("sqlite:/tmp/fake_turso.db");'"$1"; }
login /tmp/ja.txt root RootPassword123 >/dev/null; TA=$(csrf /tmp/ja.txt utilisateurs.php)
mk(){ curl -s -o /dev/null -b /tmp/ja.txt -d "csrf=$TA&action=create&username=$1&role=$2&password=$PW&confirm=$PW" $B/utilisateurs.php; }
mk vip1 VIP; mk vip2 VIP; mk ana1 Analyste; mk vis1 Visualiseur
chk "création d un compte VIP" "$(curl -s -b /tmp/ja.txt $B/utilisateurs.php | grep -c '<strong>vip1</strong>')" 1
chk "connexion VIP" "$(login /tmp/jv.txt vip1 $PW)" 302
for pg in index sitrep rapport situation carte alertes; do chk "VIP sans abonnement : $pg -> paiement" "$(loc -b /tmp/jv.txt $B/$pg.php)" "302 /paiement.php"; done
chk "VIP : page de paiement" "$(code -b /tmp/jv.txt $B/paiement.php)" 200
chk "page de paiement : numéro MVola affiché" "$([ $(curl -s -b /tmp/jv.txt $B/paiement.php | grep -c '034 00 000 00') -ge 1 ] && echo ok)" ok
chk "VIP sans abonnement : gestion des utilisateurs -> paiement" "$(loc -b /tmp/jv.txt $B/utilisateurs.php)" "302 /paiement.php"
chk "VIP sans abonnement : validation des paiements -> paiement" "$(loc -b /tmp/jv.txt $B/paiements.php)" "302 /paiement.php"
chk "menu VIP sans abonnement : pas de modules" "$(curl -s -b /tmp/jv.txt $B/paiement.php | grep -c 'sitrep.php')" 0
TV=$(csrf /tmp/jv.txt paiement.php)
chk "déclaration sans CSRF refusée" "$(code -b /tmp/jv.txt -d 'reference=MP260109.1234.A1' $B/paiement.php)" 403
chk "référence invalide refusée" "$(curl -s -b /tmp/jv.txt -d "csrf=$TV&reference=ab" $B/paiement.php | grep -c 'Référence invalide')" 1
chk "déclaration de la référence" "$(loc -b /tmp/jv.txt -d "csrf=$TV&reference=mp260109.1234.a1&sender=034 11 111 11" $B/paiement.php)" "302 /paiement.php?ok=sent"
chk "référence normalisée en majuscules" "$(sql 'echo $d->query("SELECT reference FROM app_payments")->fetchColumn();')" "MP260109.1234.A1"
chk "page : en attente de validation" "$(curl -s -b /tmp/jv.txt $B/paiement.php | grep -c 'en attente de validation')" 1
chk "accès toujours refusé avant validation" "$(loc -b /tmp/jv.txt $B/sitrep.php)" "302 /paiement.php"
chk "seconde déclaration refusée (une en attente)" "$(curl -s -b /tmp/jv.txt -d "csrf=$TV&reference=ZZ9999999" $B/paiement.php | grep -c 'déjà en attente')" 1
login /tmp/jv2.txt vip2 $PW >/dev/null; TV2=$(csrf /tmp/jv2.txt paiement.php)
chk "référence déjà utilisée par un autre compte refusée" "$(curl -s -b /tmp/jv2.txt -d "csrf=$TV2&reference=MP260109.1234.A1" $B/paiement.php | grep -c 'déjà été déclarée')" 1
chk "sondage : inactif" "$(curl -s -b /tmp/jv.txt "$B/paiement.php?poll=1")" '{"active":false}'
chk "Analyste : validation interdite" "$(login /tmp/jn.txt ana1 $PW >/dev/null; loc -b /tmp/jn.txt $B/paiements.php)" "302 /access_denied.php"
chk "admin : transaction listée" "$([ $(curl -s -b /tmp/ja.txt $B/paiements.php | grep -c 'MP260109.1234.A1') -ge 1 ] && echo ok)" ok
chk "admin : menu avec compteur" "$(curl -s -b /tmp/ja.txt $B/paiements.php | grep -c 'Paiements MVola (1)')" 1
ID=$(sql 'echo $d->query("SELECT id FROM app_payments")->fetchColumn();')
chk "validation sans CSRF refusée" "$(code -b /tmp/ja.txt -d "action=accept&id=$ID" $B/paiements.php)" 403
chk "validation par le Super Admin" "$(loc -b /tmp/ja.txt -d "csrf=$TA&action=accept&id=$ID" $B/paiements.php)" "302 /paiements.php?ok=accepted"
for pg in index sitrep rapport situation carte alertes paiement; do chk "VIP validé : $pg ouvert immédiatement" "$(code -b /tmp/jv.txt $B/$pg.php)" 200; done
chk "sondage : actif" "$(curl -s -b /tmp/jv.txt "$B/paiement.php?poll=1")" '{"active":true}'
chk "VIP validé : gestion utilisateurs toujours interdite" "$(loc -b /tmp/jv.txt $B/utilisateurs.php)" "302 /access_denied.php"
chk "VIP validé : menu avec les modules" "$(curl -s -b /tmp/jv.txt $B/index.php | grep -c 'sitrep.php')" 1
chk "rejeu de la validation refusé" "$(curl -s -b /tmp/ja.txt -d "csrf=$TA&action=accept&id=$ID" $B/paiements.php | grep -c 'déjà traitée')" 1
chk "abonnement ≈ +30 jours" "$(sql 'echo abs(strtotime($d->query("SELECT paid_until FROM app_subscriptions")->fetchColumn()." UTC")-time()-30*86400)<120?"ok":"ko";')" ok
# prolongation : un second paiement s'ajoute à l'abonnement en cours
chk "renouvellement : déclaration" "$(loc -b /tmp/jv.txt -d "csrf=$TV&reference=MP260110.5555.B2" $B/paiement.php)" "302 /paiement.php?ok=sent"
ID2=$(sql 'echo $d->query("SELECT id FROM app_payments WHERE reference=\"MP260110.5555.B2\"")->fetchColumn();')
curl -s -o /dev/null -b /tmp/ja.txt -d "csrf=$TA&action=accept&id=$ID2" $B/paiements.php
chk "renouvellement : ≈ +60 jours cumulés" "$(sql 'echo abs(strtotime($d->query("SELECT paid_until FROM app_subscriptions")->fetchColumn()." UTC")-time()-60*86400)<120?"ok":"ko";')" ok
# rejet
curl -s -o /dev/null -b /tmp/jv2.txt -d "csrf=$TV2&reference=MP260111.0000.C3" $B/paiement.php
ID3=$(sql 'echo $d->query("SELECT id FROM app_payments WHERE reference=\"MP260111.0000.C3\"")->fetchColumn();')
chk "rejet" "$(loc -b /tmp/ja.txt -d "csrf=$TA&action=reject&id=$ID3&note=virement introuvable" $B/paiements.php)" "302 /paiements.php?ok=rejected"
chk "rejet : motif visible par l utilisateur" "$(curl -s -b /tmp/jv2.txt $B/paiement.php | grep -c 'virement introuvable')" 1
chk "rejet : accès toujours refusé" "$(loc -b /tmp/jv2.txt $B/sitrep.php)" "302 /paiement.php"
chk "rejet : l utilisateur peut redéclarer" "$(loc -b /tmp/jv2.txt -d "csrf=$TV2&reference=MP260111.0001.C4" $B/paiement.php)" "302 /paiement.php?ok=sent"
chk "aucune prolongation par un rejet" "$(sql 'echo $d->query("SELECT COUNT(*) FROM app_subscriptions")->fetchColumn();')" 1
sql '$d->exec("UPDATE app_subscriptions SET paid_until=\"2020-01-01 00:00:00\"");'; rm -rf "$C"/sub_*
chk "abonnement expiré : retour au paiement" "$(loc -b /tmp/jv.txt $B/sitrep.php)" "302 /paiement.php"
# autres rôles
login /tmp/jw.txt vis1 $PW >/dev/null
chk "Analyste : sitrep réservé" "$(loc -b /tmp/jn.txt $B/sitrep.php)" "302 /access_denied.php"
chk "Visualiseur : point de situation réservé" "$(loc -b /tmp/jw.txt $B/rapport.php)" "302 /access_denied.php"
chk "Analyste : paiement interdit" "$(loc -b /tmp/jn.txt $B/paiement.php)" "302 /access_denied.php"
chk "Analyste : accueil et analyses ouverts" "$(code -b /tmp/jn.txt $B/index.php)$(code -b /tmp/jn.txt $B/situation.php)" 200200
chk "Super Admin : sitrep ouvert sans abonnement" "$(code -b /tmp/ja.txt $B/sitrep.php)" 200
chk "liste admin : abonnement VIP affiché" "$(curl -s -b /tmp/ja.txt $B/utilisateurs.php | grep -c 'Aucun abonnement actif')" 2
pkill -f "php -S 127.0.0.1:809[12]"; rm -f /tmp/ja.txt /tmp/jv*.txt /tmp/jn.txt /tmp/jw.txt
[ $fail -eq 0 ] && echo "TOUT OK" || echo "ÉCHEC"; exit $fail
