#!/usr/bin/env bash
# Gestion des utilisateurs : TursoUserStore contre un faux Turso (SQLite), règles métier, et parcours HTTP complet.
set -u; cd "$(dirname "$0")/.."
rm -f /tmp/fake_turso.db /tmp/fake_turso_down /tmp/users_e2e.json; rm -rf "${TMPDIR:-/tmp}/pharmaco_cache"
export APP_SECRET=$(openssl rand -hex 32) APP_ENV=local TURSO_DATABASE_URL=http://127.0.0.1:8091 TURSO_AUTH_TOKEN=test-token
export APP_USERS_JSON=$(php -r 'echo json_encode([["username"=>"root","hash"=>password_hash("RootPassword123",PASSWORD_DEFAULT),"role"=>"Super Admin"]]);')
(setsid php -S 127.0.0.1:8091 tests/mock/fake_turso.php >/dev/null 2>&1 &); sleep 1
php -r '
require "src/bootstrap.php";
use Pharmaco\Users\UserService; use Pharmaco\Auth\Auth; $f = 0;
function t($n,$g,$w){ global $f; if($g===$w){echo "  ok   $n\n";}else{$f++;echo "  FAIL $n: ".json_encode($g)." / ".json_encode($w)."\n";} }
$PW = "MotDePasse-1234"; function rm_cache(){ foreach (glob(cache_dir() . "/uv_*") ?: [] as $x) { @unlink($x); } }
t("liste initiale = compte de secours", array_column(UserService::listAll()["users"], "source", "username"), ["root" => "env"]);
t("création valide", UserService::create("root", "alice", "Analyste", $PW, $PW), null);
t("liste fusionnée", array_column(UserService::listAll()["users"], "username"), ["alice", "root"]);
t("doublon (casse ignorée)", UserService::create("root", "ALICE", "Visualiseur", $PW, $PW), "Cet identifiant existe déjà.");
t("doublon avec compte de secours", UserService::create("root", "Root", "Visualiseur", $PW, $PW), "Cet identifiant existe déjà.");
t("mot de passe court", str_contains((string) UserService::create("root", "bob", "Visualiseur", "court", "court"), "trop court"), true);
t("confirmation différente", str_contains((string) UserService::create("root", "bob", "Visualiseur", $PW, $PW."x"), "confirmation"), true);
t("rôle invalide", UserService::create("root", "bob", "Dieu", $PW, $PW), "Rôle invalide.");
t("identifiant invalide", str_contains((string) UserService::create("root", "a b<script>", "Analyste", $PW, $PW), "Identifiant invalide"), true);
t("hash stocké (pas de clair)", (function(){ foreach (UserService::listAll()["users"] as $u) { if ($u["username"]==="alice") return password_verify("MotDePasse-1234",$u["hash"]) && $u["hash"] !== "MotDePasse-1234"; } return false; })(), true);
$_COOKIE = [];
t("connexion avec le nouveau compte", Auth::attempt("alice", $PW), null);
t("compte de secours toujours valide", Auth::attempt("root", "RootPassword123"), null);
t("suppression de soi-même refusée", str_contains((string) UserService::delete("alice", "alice"), "propre compte"), true);
t("suppression compte de secours refusée", str_contains((string) UserService::delete("autre", "root"), "APP_USERS_JSON"), true);
t("compte valide avant suppression", UserService::stillValid("alice"), true);
t("suppression", UserService::delete("root", "alice"), null);
t("compte supprimé = accès révoqué", UserService::stillValid("alice"), false);
t("suppression inconnue", UserService::delete("root", "alice"), "Utilisateur introuvable.");
UserService::create("root", "adm2", "Super Admin", $PW, $PW);
t("on peut supprimer un Super Admin s en reste un autre", UserService::delete("root", "adm2"), null);
UserService::create("root", "adm3", "Super Admin", $PW, $PW);
touch("/tmp/fake_turso_down");
$r = UserService::listAll();
t("base en panne : repli sur comptes de secours", [array_column($r["users"], "username"), $r["error"] !== null], [["root"], true]);
t("base en panne : création refusée", UserService::create("root", "zed", "Analyste", $PW, $PW) !== null, true);
t("base en panne : sessions non coupées", UserService::stillValid("adm3"), true);
t("base en panne : connexion de secours", Auth::attempt("root", "RootPassword123"), null);
unlink("/tmp/fake_turso_down");
// ── Comptes limités / permanents ──
$fut = (new DateTimeImmutable("+2 days", new DateTimeZone("Indian/Antananarivo")))->format("Y-m-d\\TH:i");
t("type invalide", UserService::create("root", "tmp0", "Analyste", $PW, $PW, "x", ""), "Type de compte invalide.");
t("limité sans date", UserService::create("root", "tmp0", "Analyste", $PW, $PW, "limite", ""), "Date d’expiration invalide.");
t("limité date passée", UserService::create("root", "tmp0", "Analyste", $PW, $PW, "limite", "2020-01-01T10:00"), "La date d’expiration doit être dans le futur.");
t("limité trop lointain", str_contains((string) UserService::create("root", "tmp0", "Analyste", $PW, $PW, "limite", "2099-01-01T10:00"), "trop lointaine"), true);
t("création compte limité", UserService::create("root", "tmp1", "Visualiseur", $PW, $PW, "limite", $fut), null);
$u = array_values(array_filter(UserService::listAll()["users"], fn($x) => $x["username"] === "tmp1"))[0] ?? null;
t("expires_at stocké en UTC (+2 j, ±1 min)", $u && abs(strtotime($u["expires_at"] . " UTC") - (time() + 2 * 86400)) < 120, true);
t("affichage en heure de Madagascar", $u ? UserService::localTime($u["expires_at"]) === (new DateTimeImmutable($u["expires_at"], new DateTimeZone("UTC")))->setTimezone(new DateTimeZone("Indian/Antananarivo"))->format("d/m/Y H:i") : false, true);
t("compte permanent sans expiration", array_values(array_filter(UserService::listAll()["users"], fn($x) => $x["username"] === "adm3"))[0]["expires_at"], null);
t("connexion compte limité avant expiration", Auth::attempt("tmp1", $PW), null);
// expiration : on recule la date directement dans la base (faux Turso = SQLite)
$db = new PDO("sqlite:/tmp/fake_turso.db"); $db->exec("UPDATE app_users SET expires_at = \"2020-01-01 00:00:00\" WHERE username_lc = \"tmp1\"");
rm_cache();
t("compte expiré : connexion refusée", Auth::attempt("tmp1", $PW) !== null, true);
t("compte expiré : accès révoqué", UserService::stillValid("tmp1"), false);
t("compte expiré : supprimé de la base", (int) $db->query("SELECT COUNT(*) FROM app_users WHERE username_lc = \"tmp1\"")->fetchColumn(), 0);
t("compte expiré : absent de la liste", in_array("tmp1", array_column(UserService::listAll()["users"], "username"), true), false);
// migration : ancienne table sans expires_at
$db->exec("DROP TABLE app_users"); $db->exec("CREATE TABLE app_users (username TEXT PRIMARY KEY, username_lc TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, role TEXT NOT NULL, created_at TEXT NOT NULL, created_by TEXT NOT NULL DEFAULT \"\")");
$db->exec("INSERT INTO app_users VALUES (\"old\",\"old\",\"h\",\"Analyste\",\"2026-01-01 00:00:00\",\"root\")");
$r = new ReflectionProperty(Pharmaco\Users\TursoUserStore::class, "migrated"); $r->setValue(null, false);
$l = UserService::listAll();
t("ancienne base migrée : compte conservé et permanent", [array_key_exists("old", array_column($l["users"], "expires_at", "username")) ? array_column($l["users"], "expires_at", "username")["old"] : "absent", $l["error"]], [null, null]);
t("ancienne base migrée : création limitée possible", UserService::create("root", "new1", "Analyste", $PW, $PW, "limite", $fut), null);
// persistance : nouveau « processus » (état statique réinitialisé) relit les mêmes données
$r->setValue(null, false);
t("persistance après redémarrage", in_array("new1", array_column(UserService::listAll()["users"], "username"), true), true);
$h = UserService::store()->health();
t("test de persistance (écriture + relecture)", [$h["roundtrip"], $h["users"] >= 2], [true, true]);
echo $f ? "$f échec(s)\n" : "OK\n"; exit($f ? 1 : 0);
' 2>&1 | grep -v "^\[pharmaco\]\|^\[.*\] \[pharmaco"
RC=${PIPESTATUS[0]}
# ── Parcours HTTP (stockage Turso simulé) ──
(setsid php -S 127.0.0.1:8090 -t public tools/dev-router.php >/dev/null 2>&1 &); sleep 1
B=http://127.0.0.1:8090; fail=0
chk(){ if [ "$2" = "$3" ]; then echo "  ok   $1"; else echo "  FAIL $1: $2 (attendu $3)"; fail=1; fi; }
login(){ J=$1; rm -f $J; T=$(curl -s -c $J -b $J $B/Login.php | grep -o 'name="csrf" value="[^"]*"' | sed 's/.*value="//;s/"//'); curl -s -o /dev/null -w "%{http_code}" -c $J -b $J -d "csrf=$T&username=$2&password=$3" $B/Login.php; }
tok(){ curl -s -b $1 $B/utilisateurs.php | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
chk "page sans session -> redirection" "$(curl -s -o /dev/null -w '%{http_code}' $B/utilisateurs.php)" 302
chk "connexion Super Admin" "$(login /tmp/ja.txt root RootPassword123)" 302
chk "page utilisateurs (Super Admin)" "$(curl -s -o /dev/null -w '%{http_code}' -b /tmp/ja.txt $B/utilisateurs.php)" 200
T=$(tok /tmp/ja.txt)
chk "création refusée sans CSRF" "$(curl -s -o /dev/null -w '%{http_code}' -b /tmp/ja.txt -d 'action=create&username=eve&role=Visualiseur&password=MotDePasse-1234&confirm=MotDePasse-1234' $B/utilisateurs.php)" 403
chk "création via la page" "$(curl -s -o /dev/null -w '%{http_code}' -b /tmp/ja.txt -d "csrf=$T&action=create&username=eve&role=Visualiseur&password=MotDePasse-1234&confirm=MotDePasse-1234" $B/utilisateurs.php)" 302
chk "création compte limité via la page" "$(curl -s -o /dev/null -w '%{http_code}' -b /tmp/ja.txt -d "csrf=$T&action=create&username=lim1&role=Visualiseur&type=limite&expires=2099-01-01T10:00&password=MotDePasse-1234&confirm=MotDePasse-1234" $B/utilisateurs.php)" 200
EXP=$(date -d "+3 days" +%Y-%m-%dT%H:%M)
chk "création compte limité valide via la page" "$(curl -s -o /dev/null -w '%{http_code}' -b /tmp/ja.txt -d "csrf=$T&action=create&username=lim2&role=Visualiseur&type=limite&expires=$EXP&password=MotDePasse-1234&confirm=MotDePasse-1234" $B/utilisateurs.php)" 302
chk "liste affiche Limité + expiration" "$(curl -s -b /tmp/ja.txt $B/utilisateurs.php | grep -A4 '<strong>lim2</strong>' | grep -c 'expire le')" 1
chk "test de persistance via la page" "$(curl -s -b /tmp/ja.txt -d "csrf=$T&action=diag" $B/utilisateurs.php | grep -c 'Écriture et relecture réussies')" 1
chk "liste contient eve" "$(curl -s -b /tmp/ja.txt $B/utilisateurs.php | grep -c '<strong>eve</strong>')" 1
chk "connexion du nouvel utilisateur" "$(login /tmp/je.txt eve MotDePasse-1234)" 302
chk "Visualiseur : page utilisateurs interdite" "$(curl -s -o /dev/null -w '%{http_code}%{redirect_url}' -b /tmp/je.txt $B/utilisateurs.php | sed 's#http://127.0.0.1:8090##')" "302/access_denied.php"
TE=$(curl -s -b /tmp/je.txt $B/index.php | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
chk "Visualiseur : POST suppression interdit" "$(curl -s -o /dev/null -w '%{http_code}' -b /tmp/je.txt -d "csrf=$TE&action=delete&username=root" $B/utilisateurs.php)" 302
chk "root toujours présent" "$(curl -s -b /tmp/ja.txt $B/utilisateurs.php | grep -c '<strong>root</strong>')" 1
chk "menu Utilisateurs visible (Super Admin)" "$(curl -s -b /tmp/ja.txt $B/situation.php | grep -c 'utilisateurs.php')" 1
chk "menu Utilisateurs caché (Visualiseur)" "$(curl -s -b /tmp/je.txt $B/situation.php | grep -c 'utilisateurs.php')" 0
chk "suppression via la page" "$(curl -s -o /dev/null -w '%{http_code}' -b /tmp/ja.txt -d "csrf=$T&action=delete&username=eve" $B/utilisateurs.php)" 302
sleep 0.2; rm -rf "${TMPDIR:-/tmp}/pharmaco_cache/uv_"*
chk "session de l utilisateur supprimé révoquée" "$(curl -s -o /dev/null -w '%{http_code}' -b /tmp/je.txt $B/situation.php)" 302
chk "reconnexion impossible" "$(login /tmp/je2.txt eve MotDePasse-1234)" 200
chk "auto-suppression refusée" "$(curl -s -b /tmp/ja.txt -d "csrf=$T&action=delete&username=root" $B/utilisateurs.php | grep -c 'propre compte')" 1
fl=$((RC | fail)); [ $fl -eq 0 ] && echo "TOUT OK" || echo "ÉCHEC"; exit $fl
