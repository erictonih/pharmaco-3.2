#!/usr/bin/env bash
# Teste GoogleSheetsSource (JWT, en-têtes, lettres de colonnes, batchGet, cache, repli sur cache périmé) contre un faux serveur Google.
set -u; cd "$(dirname "$0")/.."
rm -f /tmp/fake_google_down; rm -rf "${TMPDIR:-/tmp}/pharmaco_cache"
KEY=$(openssl genrsa 2048 2>/dev/null | openssl pkcs8 -topk8 -nocrypt 2>/dev/null)
export GOOGLE_SA_JSON_B64=$(KEY="$KEY" php -r 'echo base64_encode(json_encode(["type"=>"service_account","client_email"=>"t@x.iam","private_key"=>getenv("KEY")]));')
export APP_ENV=local SHEETS_API_BASE=http://127.0.0.1:8095 OAUTH_TOKEN_URL=http://127.0.0.1:8095/token SHEETS_CACHE_TTL=1
(setsid php -S 127.0.0.1:8095 tests/mock/fake_google.php >/dev/null 2>&1 &); sleep 1
php -r '
require "src/bootstrap.php";
use Pharmaco\Sheets\GoogleSheetsSource; use Pharmaco\Services\{MpoxDataset,EpiAnalyticsService};
$exp = json_decode(file_get_contents("tests/fixtures/expected.json"), true); $f=0;
function t($n,$g,$w){ global $f; if($g===$w){echo "  ok   $n\n";}else{$f++;echo "  FAIL $n: ".json_encode($g)." / ".json_encode($w)."\n";} }
$src = GoogleSheetsSource::fromEnv();
$ds = MpoxDataset::load($src);
t("lecture via API (JWT + batchGet)", $ds->meta["source"], "google_sheets");
t("même nombre de lignes que la fixture", count($ds->cases), $exp["parsed_total"] + 1);
t("aucune colonne manquante", $ds->meta["missing_columns"], []);
t("indicateurs identiques (confirmés)", (new EpiAnalyticsService($ds))->overview()["status"]["confirmed"], $exp["status"]["confirmed"]);
t("colonne nominative jamais téléchargée", str_contains(json_encode($ds->cases), "FICTIF"), false);
$bad = new GoogleSheetsSource(getenv("MPOX_SHEET_ID") ?: "x", "BDD", 0, 30000, "http://127.0.0.1:8095", "http://127.0.0.1:8095/token");
t("onglet inexistant -> repli sur Base", count(MpoxDataset::load($bad)->cases), $exp["parsed_total"] + 1);
t("cache utilisé (pas stale)", MpoxDataset::load($src)->meta["stale"], false);
touch("/tmp/fake_google_down"); sleep(2);
$d2 = MpoxDataset::load($src);
t("Google en panne -> repli sur cache périmé", [$d2->meta["stale"], count($d2->cases)], [true, $exp["parsed_total"] + 1]);
echo $f ? "$f échec(s)\n" : "OK\n"; exit($f ? 1 : 0);
'
RC=$?; rm -f /tmp/fake_google_down; exit $RC
