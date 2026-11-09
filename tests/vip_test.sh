#!/usr/bin/env bash
# Module payant VIP : rôle VIP, redirection vers le paiement, confirmation, accès au module, expiration, rôles non VIP.
set -u; cd "$(dirname "$0")/.."
rm -f /tmp/fake_turso.db /tmp/fake_turso_down /tmp/vip_cache; rm -rf "${TMPDIR:-/tmp}/pharmaco_cache"
export APP_SECRET=$(openssl rand -hex 32) APP_ENV=local TURSO_DATABASE_URL=http://127.0.0.1:8091 TURSO_AUTH_TOKEN=test-token PAYMENT_MODE=simulation VIP_PRICE=50000 VIP_DAYS=30
export SHEETS_FIXTURE=$PWD/tests/fixtures/mpox_synthetic.json
export APP_USERS_JSON=$(php -r 'echo json_encode([["username"=>"root","hash"=>password_hash("RootPassword123",PASSWORD_DEFAULT),"role"=>"Super Admin"]]);')
(setsid php -S 127.0.0.1:8091 tests/mock/fake_turso.php >/dev/null 2>&1 &)
(setsid php -S 127.0.0.1:8092 -t public tools/dev-router.php >/dev/null 2>&1 &); sleep 1
B=http://127.0.0.1:8092; fail=0; PW=MotDePasse-1234
chk(){ if [ "$2" = "$3" ]; then echo "  ok   $1"; else echo "  FAIL $1: $2 (attendu $3)"; fail=1; fi; }
loc(){ curl -s -o /dev/null -w '%{http_code} %{redirect_url}' "$@" | sed "s#http://127.0.0.1:8092##"; }
login(){ J=$1; rm -f $J; T=$(curl -s -c $J -b $J $B/Login.php | grep -o 'name="csrf" value="[^"]*"' | sed 's/.*value="//;s/"//'); curl -s -o /dev/null -w "%{http_code}" -c $J -b $J -d "csrf=$T&username=$2&password=$3" $B/Login.php; }
csrf(){ curl -s -b $1 $B/$2 | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
login /tmp/ja.txt root RootPassword123 >/dev/null; TA=$(csrf /tmp/ja.txt utilisateurs.php)
mk(){ curl -s -o /dev/null -b /tmp/ja.txt -d "csrf=$TA&action=create&username=$1&role=$2&password=$PW&confirm=$PW" $B/utilisateurs.php; }
mk vip1 VIP; mk ana1 Analyste; mk vis1 Visualiseur
chk "création d un compte VIP" "$(curl -s -b /tmp/ja.txt $B/utilisateurs.php | grep -c '<strong>vip1</strong>')" 1
chk "connexion VIP" "$(login /tmp/jv.txt vip1 $PW)" 302
chk "VIP sans abonnement : accueil -> paiement" "$(loc -b /tmp/jv.txt $B/index.php)" "302 /paiement.php"
chk "VIP sans abonnement : sitrep -> paiement" "$(loc -b /tmp/jv.txt $B/sitrep.php)" "302 /paiement.php"
chk "VIP sans abonnement : point de situation -> paiement" "$(loc -b /tmp/jv.txt $B/rapport.php)" "302 /paiement.php"
chk "VIP : page de paiement accessible" "$(loc -b /tmp/jv.txt $B/paiement.php | cut -c1-3)" 200
chk "VIP : pages d analyse interdites" "$(loc -b /tmp/jv.txt $B/situation.php)" "302 /access_denied.php"
chk "VIP : gestion des utilisateurs interdite" "$(loc -b /tmp/jv.txt $B/utilisateurs.php | cut -c1-3)" 302
TV=$(csrf /tmp/jv.txt paiement.php)
chk "paiement : démarrage sans CSRF refusé" "$(curl -s -o /dev/null -w '%{http_code}' -b /tmp/jv.txt -d 'action=start' $B/paiement.php)" 403
TX=$(curl -s -b /tmp/jv.txt -D - -o /dev/null -d "csrf=$TV&action=start" $B/paiement.php | grep -i '^location' | grep -o '[a-f0-9]\{32\}')
chk "paiement : transaction créée" "${#TX}" 32
chk "paiement : étape de confirmation affichée" "$(curl -s -b /tmp/jv.txt "$B/paiement.php?tx=$TX" | grep -c 'Confirmer la transaction')" 1
chk "accès toujours refusé avant confirmation" "$(loc -b /tmp/jv.txt $B/sitrep.php)" "302 /paiement.php"
login /tmp/jv2.txt vip1 $PW >/dev/null; chk "transaction d un autre compte refusée" "$(curl -s -b /tmp/ja.txt -d "csrf=$TA&action=confirm&tx=$TX" $B/paiement.php | grep -c 'introuvable')" 1
chk "confirmation -> accueil" "$(loc -b /tmp/jv.txt -d "csrf=$TV&action=confirm&tx=$TX" $B/paiement.php)" "302 /index.php"
rm -rf "${TMPDIR:-/tmp}/pharmaco_cache/sub_"*
chk "VIP abonné : accueil ouvert" "$(loc -b /tmp/jv.txt $B/index.php | cut -c1-3)" 200
chk "VIP abonné : sitrep ouvert" "$(loc -b /tmp/jv.txt $B/sitrep.php | cut -c1-3)" 200
chk "VIP abonné : point de situation ouvert" "$(loc -b /tmp/jv.txt $B/rapport.php | cut -c1-3)" 200
chk "VIP abonné : pages d analyse toujours interdites" "$(loc -b /tmp/jv.txt $B/carte.php)" "302 /access_denied.php"
chk "rejeu de la transaction refusé" "$(curl -s -b /tmp/jv.txt -d "csrf=$TV&action=confirm&tx=$TX" $B/paiement.php | grep -c 'introuvable')" 1
DB=/tmp/fake_turso.db
chk "abonnement ≈ +30 jours" "$(php -r '$d=new PDO("sqlite:/tmp/fake_turso.db");$u=$d->query("SELECT paid_until FROM app_subscriptions")->fetchColumn();echo abs(strtotime($u." UTC")-time()-30*86400)<120?"ok":$u;')" ok
chk "une seule transaction confirmée" "$(php -r '$d=new PDO("sqlite:/tmp/fake_turso.db");echo $d->query("SELECT COUNT(*) FROM app_payments WHERE status=\"confirmed\"")->fetchColumn();')" 1
TX2=$(curl -s -b /tmp/jv.txt -D - -o /dev/null -d "csrf=$TV&action=start" $B/paiement.php | grep -i '^location' | grep -o '[a-f0-9]\{32\}')
chk "annulation" "$(loc -b /tmp/jv.txt -d "csrf=$TV&action=cancel&tx=$TX2" $B/paiement.php)" "302 /paiement.php?ok=cancelled"
chk "transaction annulée non confirmable" "$(curl -s -b /tmp/jv.txt -d "csrf=$TV&action=confirm&tx=$TX2" $B/paiement.php | grep -c 'introuvable')" 1
php -r '$d=new PDO("sqlite:/tmp/fake_turso.db");$d->exec("UPDATE app_subscriptions SET paid_until=\"2020-01-01 00:00:00\"");'; rm -rf "${TMPDIR:-/tmp}/pharmaco_cache/sub_"*
chk "abonnement expiré : retour au paiement" "$(loc -b /tmp/jv.txt $B/sitrep.php)" "302 /paiement.php"
TX3=$(curl -s -b /tmp/jv.txt -D - -o /dev/null -d "csrf=$TV&action=start" $B/paiement.php | grep -i '^location' | grep -o '[a-f0-9]\{32\}'); curl -s -o /dev/null -b /tmp/jv.txt -d "csrf=$TV&action=confirm&tx=$TX3" $B/paiement.php; rm -rf "${TMPDIR:-/tmp}/pharmaco_cache/sub_"*
chk "renouvellement : accès rétabli" "$(loc -b /tmp/jv.txt $B/sitrep.php | cut -c1-3)" 200
# autres rôles : le module payant est réservé
login /tmp/jn.txt ana1 $PW >/dev/null; login /tmp/jw.txt vis1 $PW >/dev/null
chk "Analyste : sitrep réservé" "$(loc -b /tmp/jn.txt $B/sitrep.php)" "302 /access_denied.php"
chk "Visualiseur : point de situation réservé" "$(loc -b /tmp/jw.txt $B/rapport.php)" "302 /access_denied.php"
chk "Analyste : paiement interdit" "$(loc -b /tmp/jn.txt $B/paiement.php)" "302 /access_denied.php"
chk "Analyste : accueil ouvert" "$(loc -b /tmp/jn.txt $B/index.php | cut -c1-3)" 200
chk "Super Admin : sitrep ouvert sans abonnement" "$(loc -b /tmp/ja.txt $B/sitrep.php | cut -c1-3)" 200
chk "menu VIP : modules payants + abonnement" "$(curl -s -b /tmp/jv.txt $B/sitrep.php | grep -c 'paiement.php')" 1
chk "menu Analyste : pas de module payant" "$(curl -s -b /tmp/jn.txt $B/index.php | grep -c 'sitrep.php')" 0
chk "liste admin : abonnement affiché" "$(curl -s -b /tmp/ja.txt $B/utilisateurs.php | grep -c 'Abonnement jusqu')" 1
pkill -f "php -S 127.0.0.1:809[12]"; rm -f /tmp/ja.txt /tmp/jv*.txt /tmp/jn.txt /tmp/jw.txt
[ $fail -eq 0 ] && echo "TOUT OK" || echo "ÉCHEC"; exit $fail
