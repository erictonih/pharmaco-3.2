<?php
require_once __DIR__ . '/db.php';
if (!isset($_SESSION["user_id"])) {
    header("Location: Login.php");
    exit();
}

$PAGE_ROLES = [
    'analyse_mpox.php' => ['Super Admin', 'Visualiseur', 'Analyste'],
];
$userRole = $_SESSION["role"] ?? '';
if (!in_array($userRole, $PAGE_ROLES['analyse_mpox.php'])) {
    header("Location: index.php");
    exit();
}

$page_title  = 'Analyse Mpox';
$active_page = 'analyse_mpox';
$breadcrumb  = ['Accueil' => 'index.php', 'Analyse Mpox' => ''];
include 'sidebar.php';

define('SHEET_ID',     '1VdqjKcr00GmRI-FuOopXhRwYolvkUJmtp3DSV9ow4CE');
define('SHEET_GID',    '1801487203');
define('SHEET_RANGE',  'BDD 28042026!A1:BF5000');
define('SA_KEY_FILE', 'env:GOOGLE_SA_JSON_B64');
define('CACHE_FILE', cache_dir() . '/mpox_data.json');
define('CACHE_TTL',    300);

function curlGet(string $url, array $headers = []): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err) throw new Exception("cURL GET : $err");
    return $resp ?: '';
}

function curlPost(string $url, string $body, array $headers = []): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err) throw new Exception("cURL POST : $err");
    return $resp ?: '';
}

function getGoogleToken(): string {
    $key = google_service_account();
    if (!$key) throw new Exception('Compte de service Google non configuré (GOOGLE_SA_JSON_B64).');
    if (($key['type'] ?? '') !== 'service_account') {
        throw new Exception("Fichier JSON invalide : type ≠ service_account");
    }
    $now   = time();
    $claim = [
        'iss'   => $key['client_email'],
        'scope' => 'https://www.googleapis.com/auth/spreadsheets.readonly',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
    ];
    $b64 = fn($d) => rtrim(strtr(base64_encode(is_array($d) ? json_encode($d) : $d), '+/', '-_'), '=');
    $h   = $b64(['alg' => 'RS256', 'typ' => 'JWT']);
    $p   = $b64($claim);
    openssl_sign("$h.$p", $sig, $key['private_key'], 'SHA256');
    $jwt = "$h.$p." . $b64($sig);
    $resp  = curlPost(
        'https://oauth2.googleapis.com/token',
        http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
        ['Content-Type: application/x-www-form-urlencoded']
    );
    $token = json_decode($resp, true);
    if (isset($token['error'])) {
        throw new Exception("OAuth2 : " . $token['error'] . ' — ' . ($token['error_description'] ?? ''));
    }
    return $token['access_token'] ?? '';
}

function fetchSheetData(): array {
    if (file_exists(CACHE_FILE) && (time() - filemtime(CACHE_FILE)) < CACHE_TTL) {
        $cached = json_decode(file_get_contents(CACHE_FILE), true);
        if (!empty($cached)) return $cached;
    }
    try {
        $token = getGoogleToken();
        $url   = 'https://sheets.googleapis.com/v4/spreadsheets/'
               . SHEET_ID . '/values/' . rawurlencode(SHEET_RANGE)
               . '?majorDimension=ROWS&valueRenderOption=UNFORMATTED_VALUE';
        $resp = curlGet($url, ["Authorization: Bearer $token"]);
        $data = json_decode($resp, true);
        if (isset($data['error'])) {
            throw new Exception("Sheets API : " . $data['error']['message']);
        }
        $rows = $data['values'] ?? [];
        if (!is_dir(dirname(CACHE_FILE))) {
            mkdir(dirname(CACHE_FILE), 0755, true);
        }
        file_put_contents(CACHE_FILE, json_encode($rows));
        return $rows;
    } catch (Exception $e) {
        if (file_exists(CACHE_FILE)) {
            return json_decode(file_get_contents(CACHE_FILE), true) ?? [];
        }
        file_put_contents(
            cache_dir() . '/mpox_last_error.txt',
            date('Y-m-d H:i:s') . ' — ' . $e->getMessage()
        );
        return [];
    }
}

$rawRows  = fetchSheetData();
$lastSync = file_exists(CACHE_FILE) ? date('d/m/Y H:i:s', filemtime(CACHE_FILE)) : 'Jamais';
$headers  = [];
$records  = [];

if (!empty($rawRows)) {
    $headers = array_shift($rawRows);
    foreach ($rawRows as $row) {
        if (empty(array_filter($row))) continue;
        while (count($row) < count($headers)) $row[] = '';
        $records[] = array_combine($headers, array_slice($row, 0, count($headers)));
    }
}

$COL = [
    'cas'           => 'Cas',
    'date_cousp'    => 'Date cousp',
    'date_notif'    => 'Date de notification',
    'date_signes'   => 'Date de début des signes',
    'region'        => 'Region',
    'district'      => 'District',
    'classification'=> 'Classification',
    'conclusion'    => 'Conclusion 1',
    'age_an'        => 'Age (année)',
    'tranche_age'   => 'tranche_d_age',
    'sexe'          => 'Sexe',
    'evolution'     => 'Evolution',
    'hospitalise'   => 'Hospitalisation',
    'lien_epide'    => 'Lien épidémiologique (Oui, Non)',
    'prelevement'   => 'Prélèvement (Oui ou Non)',
    'labo'          => 'Laboratoire (IPM ou LA2M)',
    'vaccin'        => 'Vaccin MPOX',
    'vih'           => 'Statut VIH( confirmé, Negatif, NON RENSEIGNE)',
    'clade'         => 'Charactères génomiques',
    'se_notif'      => 'SE Notification',
    'commune'       => 'Nom Commune',
];

$total       = count($records);
$confirmes   = 0; $suspects = 0; $probables = 0; $nonCas = 0;
$deces       = 0; $gueris = 0; $actifs = 0;
$hommes      = 0; $femmes = 0;
$hospitalises= 0;
$preleves    = 0;

$bySE        = [];
$byRegion    = [];
$byDistrict  = [];
$byTranche   = [];
$bySexeEvo   = ['Masculin'=>['Guéri'=>0,'Décédé'=>0,'Actif'=>0],
                 'Féminin' =>['Guéri'=>0,'Décédé'=>0,'Actif'=>0]];
$byMonth     = [];
$byClassSexe = [];
$byVIH       = [];
$ages        = [];

foreach ($records as $r) {
    $classif = mb_strtolower(trim($r[$COL['classification']] ?? ''), 'UTF-8');
    $evol    = trim($r[$COL['evolution']]  ?? '');
    $cas     = mb_strtolower(trim($r[$COL['conclusion']]  ?? ''), 'UTF-8');
    $sexe    = trim($r[$COL['sexe']]       ?? '');
    $region  = trim($r[$COL['region']]     ?? 'Inconnu');
    $district= trim($r[$COL['district']]   ?? 'Inconnu');
    $tranche = trim($r[$COL['tranche_age']]?? 'Inconnu');
    $se      = trim($r[$COL['se_notif']]   ?? '');
    $ageRaw  = trim($r[$COL['age_an']]     ?? '');
    $vih     = trim($r[$COL['vih']]        ?? 'NON RENSEIGNE');

    // ── Classification ──────────────────────────────────────────────────────
    $isConfirme = str_contains($classif, 'pos');
    if ($isConfirme)                            { $confirmes++; }
    elseif (str_contains($classif,'suspect'))   { $suspects++;  }
    elseif (str_contains($classif,'pro'))       { $probables++; }
    elseif (str_contains($classif,'neg'))       { $probables++; }

    // ── Évolution ────────────────────────────────────────────────────────────
    $evolLow = strtolower($evol);
    if (str_contains($evolLow, 'déc') || str_contains($evolLow, 'dec'))  $deces++;
    elseif (str_contains($evolLow, 'gué') || str_contains($evolLow, 'gue')) $gueris++;
    else $actifs++;

    // ── Sexe ─────────────────────────────────────────────────────────────────
    if (str_contains(strtolower($sexe), 'masc') || $sexe==='M') $hommes++;
    elseif (str_contains(strtolower($sexe), 'fém') || str_contains(strtolower($sexe),'fem') || $sexe==='F') $femmes++;

    if (strtolower($r[$COL['hospitalise']] ?? '') === 'oui') $hospitalises++;
    if (strtolower($r[$COL['prelevement']] ?? '') === 'oui') $preleves++;

    // ── SE Notification (avec comptage confirmes) ─────────────────────────────
    if ($se !== '') {
        if (!isset($bySE[$se])) $bySE[$se] = ['cas' => 0, 'confirmes' => 0];
        $bySE[$se]['cas']++;
        if ($isConfirme) $bySE[$se]['confirmes']++;
    }

    // ── Mois (avec comptage confirmes) ────────────────────────────────────────
    $dateStr = trim($r[$COL['date_notif']] ?? '');
    if ($dateStr) {
        $ts = strtotime($dateStr);
        if ($ts) {
            $mois = date('Y-m', $ts);
            if (!isset($byMonth[$mois])) $byMonth[$mois] = ['cas'=>0,'confirmes'=>0,'deces'=>0,'gueris'=>0];
            $byMonth[$mois]['cas']++;
            if ($isConfirme) $byMonth[$mois]['confirmes']++;
            if (str_contains($evolLow,'déc')||str_contains($evolLow,'dec')) $byMonth[$mois]['deces']++;
            if (str_contains($evolLow,'gué')||str_contains($evolLow,'gue')) $byMonth[$mois]['gueris']++;
        }
    }

    // ── Région ───────────────────────────────────────────────────────────────
    if ($region) {
        if (!isset($byRegion[$region])) $byRegion[$region]=['total'=>0,'confirmes'=>0,'deces'=>0,'gueris'=>0,'H'=>0,'F'=>0];
        $byRegion[$region]['total']++;
        if (str_contains($cas,'confirm')) $byRegion[$region]['confirmes']++;
        if (str_contains($evolLow,'déc')||str_contains($evolLow,'dec'))    $byRegion[$region]['deces']++;
        if (str_contains($evolLow,'gué')||str_contains($evolLow,'gue'))    $byRegion[$region]['gueris']++;
        if (str_contains(strtolower($sexe),'masc')||$sexe==='M') $byRegion[$region]['H']++;
        else $byRegion[$region]['F']++;
    }

    // ── District ─────────────────────────────────────────────────────────────
    if ($district) {
        if (!isset($byDistrict[$district])) $byDistrict[$district]=['total'=>0,'confirmes'=>0,'deces'=>0,'H'=>0,'F'=>0];
        $byDistrict[$district]['total']++;
        if (str_contains($cas,'confirm')) $byDistrict[$district]['confirmes']++;
        if (str_contains($evolLow,'déc')||str_contains($evolLow,'dec'))    $byDistrict[$district]['deces']++;
        if (str_contains(strtolower($sexe),'masc')||$sexe==='M') $byDistrict[$district]['H']++;
        else $byDistrict[$district]['F']++;
    }

    if ($tranche) $byTranche[$tranche] = ($byTranche[$tranche] ?? 0) + 1;

    $sexeKey = (str_contains(strtolower($sexe),'masc')||$sexe==='M') ? 'Masculin' : 'Féminin';
    if (str_contains($evolLow,'déc')||str_contains($evolLow,'dec'))
        $bySexeEvo[$sexeKey]['Décédé']++;
    elseif (str_contains($evolLow,'gué')||str_contains($evolLow,'gue'))
        $bySexeEvo[$sexeKey]['Guéri']++;
    else
        $bySexeEvo[$sexeKey]['Actif']++;

    $vihKey = $vih ?: 'NON RENSEIGNE';
    $byVIH[$vihKey] = ($byVIH[$vihKey] ?? 0) + 1;

    if (is_numeric($ageRaw)) $ages[] = (int)$ageRaw;
}

ksort($bySE);
ksort($byMonth);
arsort($byDistrict);

// ── Calcul des 4 dernières semaines épidémiologiques ─────────────────────────
$currentSE   = (int)date('W');
$currentYear = (int)date('Y');
$seRange     = [];
for ($i = 0; $i <= 4; $i++) {
    $seNum = $currentSE - $i;
    if ($seNum <= 0) $seNum += 52;
    $seRange[] = $seNum;
}

$byDistrict4W = [];
foreach ($records as $r) {
    $se4      = trim($r[$COL['se_notif']]      ?? '');
    $dist4    = trim($r[$COL['district']]       ?? 'Inconnu');
    $sexe4    = trim($r[$COL['sexe']]           ?? '');
    $classif4 = mb_strtolower(trim($r[$COL['classification']] ?? ''), 'UTF-8');
    $evol4    = strtolower(trim($r[$COL['evolution']] ?? ''));
    $cas4     = mb_strtolower(trim($r[$COL['conclusion']]  ?? ''), 'UTF-8');

    $seNum4 = (int)$se4;
    if (!in_array($seNum4, $seRange)) continue;

    if (!isset($byDistrict4W[$dist4]))
        $byDistrict4W[$dist4] = ['total'=>0,'confirmes'=>0,'deces'=>0,'H'=>0,'F'=>0];
    $byDistrict4W[$dist4]['total']++;
    if (str_contains($classif4,'pos'))                                      $byDistrict4W[$dist4]['confirmes']++;
    if (str_contains($evol4,'déc')||str_contains($evol4,'dec'))            $byDistrict4W[$dist4]['deces']++;
    if (str_contains(strtolower($sexe4),'masc')||$sexe4==='M')             $byDistrict4W[$dist4]['H']++;
    else                                                                     $byDistrict4W[$dist4]['F']++;
}
arsort($byDistrict4W);

// ── KPI ───────────────────────────────────────────────────────────────────────
$ageMoyen  = $ages ? round(array_sum($ages)/count($ages), 1) : 0;
$ageMedian = 0;
if ($ages) { sort($ages); $mid = count($ages)/2; $ageMedian = count($ages)%2 ? $ages[$mid] : ($ages[$mid-1]+$ages[$mid])/2; }
$cfr       = $total > 0 ? round($deces/$total*100,1) : 0;
$tauxHospi = $total > 0 ? round($hospitalises/$total*100,1) : 0;
$tauxGueri = $total > 0 ? round($gueris/$total*100,1) : 0;

arsort($byDistrict);
$top10Districts = array_slice($byDistrict, 0, 10, true);

// ── Variables JSON pour les graphiques ───────────────────────────────────────
$jsonSE         = json_encode(array_values(array_map(fn($v)=>$v['cas'],       $bySE)));
$jsonSEConfirm  = json_encode(array_values(array_map(fn($v)=>$v['confirmes'], $bySE)));
$jsonSELabels   = json_encode(array_keys($bySE));
$jsonMonth      = json_encode(array_values(array_map(fn($v)=>$v['cas'],       $byMonth)));
$jsonMonthC     = json_encode(array_values(array_map(fn($v)=>$v['confirmes'], $byMonth)));
$jsonMonthD     = json_encode(array_values(array_map(fn($v)=>$v['deces'],     $byMonth)));
$jsonMonthG     = json_encode(array_values(array_map(fn($v)=>$v['gueris'],    $byMonth)));
$jsonMonthL     = json_encode(array_keys($byMonth));
$jsonDistL      = json_encode(array_keys($top10Districts));
$jsonDistV      = json_encode(array_values(array_map(fn($v)=>$v['total'],    $top10Districts)));
$jsonDistH      = json_encode(array_values(array_map(fn($v)=>$v['H'],        $top10Districts)));
$jsonDistF      = json_encode(array_values(array_map(fn($v)=>$v['F'],        $top10Districts)));
$jsonTrL        = json_encode(array_keys($byTranche));
$jsonTrH        = json_encode(array_map(fn($k)=>$byTranche[$k]??0, array_keys($byTranche)));
$jsonTrF        = json_encode(array_fill(0, count($byTranche), 0));
$jsonRegL       = json_encode(array_keys($byRegion));
$jsonRegV       = json_encode(array_values(array_map(fn($v)=>$v['total'],    $byRegion)));
$jsonRegC       = json_encode(array_values(array_map(fn($v)=>$v['confirmes'],$byRegion)));
$jsonRegD       = json_encode(array_values(array_map(fn($v)=>$v['deces'],    $byRegion)));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
/* ══════════════════════════════════════════════════════════
   MPOX — Variables locales (couleurs sémantiques épidémio)
══════════════════════════════════════════════════════════ */
:root {
  --mpox-red:    #e05252;
  --mpox-orange: #f39c12;
  --mpox-blue:   #3d9de0;
  --mpox-green:  #2ecc71;
  --mpox-red-a:    rgba(224, 82,  82,  .20);
  --mpox-orange-a: rgba(243, 156, 18,  .20);
  --mpox-blue-a:   rgba(61,  157, 224, .20);
  --mpox-green-a:  rgba(46,  204, 113, .20);
  --mpox-gray-a:   rgba(148, 163, 184, .15);
}

.mpox-wrap { padding: 0; color: var(--text-primary); }

.sync-bar {
  display: flex; align-items: center; justify-content: space-between;
  background: var(--bg-card); border: 1px solid var(--border);
  border-radius: 10px; padding: 10px 18px; margin-bottom: 20px;
  font-size: .82rem; color: var(--text-muted);
  transition: background .3s, border-color .3s;
}
.sync-bar .sync-status { display: flex; align-items: center; gap: 8px; }
.sync-bar strong { color: var(--text-primary); }
.sync-dot {
  width: 8px; height: 8px; border-radius: 50%;
  background: var(--mpox-green); animation: mpox-pulse 2s infinite;
}
@keyframes mpox-pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px,1fr));
  gap: 12px; margin-bottom: 22px;
}
.kpi {
  background: var(--bg-card); border: 1px solid var(--border);
  border-radius: 12px; padding: 16px;
  position: relative; overflow: hidden;
  box-shadow: var(--shadow);
  transition: background .3s, border-color .3s, box-shadow .2s;
}
.kpi:hover { box-shadow: var(--shadow-hover); }
.kpi::before {
  content: ''; position: absolute;
  top: 0; left: 0; right: 0; height: 3px;
}
.kpi-red::before    { background: var(--mpox-red); }
.kpi-orange::before { background: var(--mpox-orange); }
.kpi-blue::before   { background: var(--mpox-blue); }
.kpi-green::before  { background: var(--mpox-green); }
.kpi-dark::before   { background: var(--accent); }
.kpi-label {
  font-size: .72rem; font-weight: 700; color: var(--text-muted);
  text-transform: uppercase; letter-spacing: .06em; margin-bottom: 6px;
}
.kpi-value { font-size: 2rem; font-weight: 800; color: var(--text-primary); line-height: 1; }
.kpi-sub  { font-size: .75rem; color: var(--text-muted); margin-top: 5px; }
.kpi-icon { position: absolute; right: 12px; top: 12px; font-size: 1.6rem; opacity: .12; }

.sec-grid { display: grid; gap: 16px; margin-bottom: 20px; }
.g2  { grid-template-columns: 1fr 1fr; }
.g3  { grid-template-columns: 1fr 1fr 1fr; }
.g12 { grid-template-columns: 2fr 1fr; }
.g21 { grid-template-columns: 1fr 2fr; }

.mcard {
  background: var(--bg-card); border: 1px solid var(--border);
  border-radius: 12px; padding: 18px; box-shadow: var(--shadow);
  transition: background .3s, border-color .3s, box-shadow .2s;
}
.mcard:hover { box-shadow: var(--shadow-hover); }
.mcard-title {
  font-size: .85rem; font-weight: 700; color: var(--text-primary);
  margin-bottom: 14px; display: flex; align-items: center; gap: 8px;
  border-bottom: 1px solid var(--border); padding-bottom: 10px;
  transition: border-color .3s;
}
.mcard-title i { color: var(--accent); }

.mtbl { width: 100%; border-collapse: collapse; font-size: .82rem; }
.mtbl th {
  background: var(--accent-soft); color: var(--accent);
  font-weight: 700; padding: 8px 10px; text-align: left;
  border-bottom: 2px solid var(--border);
  font-size: .75rem; text-transform: uppercase; letter-spacing: .04em;
  transition: background .3s;
}
.mtbl td {
  padding: 7px 10px; border-bottom: 1px solid var(--border);
  color: var(--text-secondary); vertical-align: middle;
  transition: background .15s;
}
.mtbl tbody tr:hover td { background: var(--accent-soft); }
.mtbl tbody tr:last-child td { border-bottom: none; }
.mtbl tfoot tr { background: var(--accent-soft); font-weight: 700; color: var(--text-primary); }
.mtbl tfoot td { color: var(--text-primary); border-top: 2px solid var(--border); }
.mtbl-scroll { scrollbar-color: var(--border) var(--bg-card); scrollbar-width: thin; }

.mb { display: inline-flex; padding: 2px 8px; border-radius: 12px; font-size: .72rem; font-weight: 700; }
.mb-red    { background: var(--mpox-red-a);    color: var(--mpox-red); }
.mb-orange { background: var(--mpox-orange-a); color: var(--mpox-orange); }
.mb-blue   { background: var(--mpox-blue-a);   color: var(--mpox-blue); }
.mb-green  { background: var(--mpox-green-a);  color: var(--mpox-green); }
.mb-gray   { background: var(--mpox-gray-a);   color: var(--text-muted); }

.prog {
  height: 6px; background: var(--accent-soft);
  border-radius: 3px; overflow: hidden; margin-top: 4px;
  transition: background .3s;
}
.prog-fill { height: 6px; border-radius: 3px; }

.srow {
  display: flex; justify-content: space-between; align-items: center;
  padding: 7px 0; border-bottom: 1px solid var(--border);
  font-size: .83rem; color: var(--text-primary);
  transition: border-color .3s;
}
.srow:last-child { border: none; }

.mpox-alert {
  background: rgba(224,82,82,.10); border: 1px solid rgba(224,82,82,.30);
  border-radius: 10px; padding: 12px 18px;
  display: flex; align-items: center; gap: 12px;
  margin-bottom: 18px; font-size: .85rem; color: var(--mpox-red);
}

#district-search {
  background: var(--bg-card) !important; color: var(--text-primary) !important;
  border: 1px solid var(--border) !important;
  border-radius: 6px; padding: 4px 10px;
  font-size: .8rem; outline: none;
  transition: background .3s, border-color .2s, color .3s;
}
#district-search:focus {
  border-color: var(--accent) !important;
  box-shadow: 0 0 0 3px var(--accent-soft);
}
#district-search::placeholder { color: var(--text-muted); }

code {
  background: var(--accent-soft); color: var(--accent);
  padding: 2px 6px; border-radius: 4px; font-size: .8em;
  transition: background .3s, color .3s;
}

.mpox-nav-btn {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 18px; border-radius: 10px;
  font-weight: 600; text-decoration: none; font-size: .875rem;
  border: 1px solid var(--border); background: var(--bg-card);
  color: var(--text-secondary);
  transition: background .2s, color .2s, border-color .2s;
}
.mpox-nav-btn:hover { background: var(--accent-soft); color: var(--accent); border-color: var(--accent); }
.mpox-nav-btn.primary { background: var(--accent); color: #fff; border-color: var(--accent); box-shadow: 0 2px 10px rgba(10,108,188,.25); }
.mpox-nav-btn.primary:hover { background: var(--accent-hover); }
.mpox-nav-btn.danger  { color: var(--mpox-red);    border-color: var(--mpox-red-a);    }
.mpox-nav-btn.danger:hover  { background: var(--mpox-red-a);    }
.mpox-nav-btn.warning { color: var(--mpox-orange); border-color: var(--mpox-orange-a); }
.mpox-nav-btn.warning:hover { background: var(--mpox-orange-a); }
.mpox-nav-btn.success { color: var(--mpox-green);  border-color: var(--mpox-green-a);  }
.mpox-nav-btn.success:hover { background: var(--mpox-green-a);  }

.footer-sync { color: var(--text-muted) !important; }
.chart-wrap  { position: relative; width: 100%; }

.spinner {
  display: inline-block; width: 14px; height: 14px;
  border: 2px solid var(--border); border-top-color: var(--accent);
  border-radius: 50%; animation: mpox-spin .7s linear infinite;
}
@keyframes mpox-spin { to { transform: rotate(360deg); } }

@media(max-width:900px){
  .g2,.g3,.g12,.g21 { grid-template-columns: 1fr; }
}
</style>
</head>

<div class="dv-content dv-animate mpox-wrap">

    <!-- En-tête -->
    <div class="dv-page-header">
        <h1><i class="bi bi-virus" style="color:var(--mpox-red);margin-right:8px;"></i>Tableau de bord épidémique — Mpox Madagascar</h1>
        <div class="dv-breadcrumb">
            <a href="index.php">Accueil</a>
            <i class="bi bi-chevron-right" style="font-size:.65rem;"></i>
            <span>Analyse Mpox</span>
        </div>
    </div>

    <!-- Boutons navigation cartographie -->
    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
        <a href="carto_mpox.php" class="mpox-nav-btn primary">
            <i class="bi bi-map"></i> Cartographie par District
        </a>
        <a href="carto_mpox.php?map=notif_today" class="mpox-nav-btn">
            <i class="bi bi-calendar-day"></i> Notifiés aujourd'hui
        </a>
        <a href="carto_mpox.php?map=confirm_today" class="mpox-nav-btn danger">
            <i class="bi bi-virus"></i> Confirmés aujourd'hui
        </a>
        <a href="carto_mpox.php?map=notif_cumul" class="mpox-nav-btn warning">
            <i class="bi bi-layers"></i> Notifiés cumulés
        </a>
        <a href="carto_mpox.php?map=confirm_4w" class="mpox-nav-btn success">
            <i class="bi bi-calendar-range"></i> Confirmés 4 semaines
        </a>
    </div>

    <!-- Barre de synchronisation -->
    <div class="sync-bar">
        <div class="sync-status">
            <div class="sync-dot" id="sync-dot"></div>
            <span>Synchronisé avec Google Sheets ·
                <strong>Dernière mise à jour :</strong> <?= htmlspecialchars($lastSync) ?>
            </span>
            <span id="next-sync" style="color:var(--accent);font-weight:600;"></span>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:.8rem;color:var(--text-muted);">
                <i class="bi bi-clock"></i> Actualisation auto. toutes les 5 min
            </span>
            <button onclick="forceSync()" class="dv-btn dv-btn-outline" style="padding:5px 14px;font-size:.8rem;">
                <i class="bi bi-arrow-clockwise" id="sync-icon"></i> Actualiser
            </button>
        </div>
    </div>

    <?php if ($total === 0):
        $lastErr = "";
        $errFile = cache_dir() . '/mpox_last_error.txt';
        if (file_exists($errFile)) $lastErr = file_get_contents($errFile);
        $saOk = (bool) google_service_account();
    ?>
    <div class="mpox-alert" style="flex-direction:column;gap:10px;">
        <div style="display:flex;align-items:center;gap:12px;">
            <i class="bi bi-exclamation-triangle-fill" style="font-size:1.4rem;flex-shrink:0;"></i>
            <div><strong>Données non disponibles.</strong> <?php echo $saOk ? "Erreur de connexion à Google Sheets." : "Fichier Service Account manquant."; ?></div>
        </div>
        <div style="background:var(--accent-soft);border-radius:6px;padding:10px 14px;font-size:.8rem;font-family:monospace;color:var(--mpox-red);">
            📁 Fichier SA : <strong><?php echo $saOk ? "✅ Présent" : "❌ Absent"; ?></strong> — variable GOOGLE_SA_JSON_B64<br>
            📋 Plage : <strong><?php echo htmlspecialchars(SHEET_RANGE); ?></strong><br>
            <?php if ($lastErr): ?> ❌ Erreur : <span><?php echo htmlspecialchars($lastErr); ?></span><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- KPI CARDS -->
    <div class="kpi-grid">
        <div class="kpi kpi-dark">
            <div class="kpi-icon">📊</div>
            <div class="kpi-label">Total cas notifiés</div>
            <div class="kpi-value"><?= number_format($total) ?></div>
            <div class="kpi-sub">Toutes classifications</div>
        </div>
        <div class="kpi kpi-red">
            <div class="kpi-icon">🔬</div>
            <div class="kpi-label">Cas confirmés</div>
            <div class="kpi-value" style="color:var(--mpox-red)"><?= number_format($confirmes) ?></div>
            <div class="kpi-sub"><?= $total?round($confirmes/$total*100,1):0 ?>% du total</div>
        </div>
        <div class="kpi kpi-orange">
            <div class="kpi-icon">🔍</div>
            <div class="kpi-label">Cas suspects</div>
            <div class="kpi-value" style="color:var(--mpox-orange)"><?= number_format($suspects) ?></div>
            <div class="kpi-sub">En attente de résultat</div>
        </div>
        <div class="kpi kpi-blue">
            <div class="kpi-icon">⚠️</div>
            <div class="kpi-label">Cas probables</div>
            <div class="kpi-value" style="color:var(--mpox-blue)"><?= number_format($probables) ?></div>
            <div class="kpi-sub">&nbsp;</div>
        </div>
        <div class="kpi kpi-red">
            <div class="kpi-icon">💀</div>
            <div class="kpi-label">Décès</div>
            <div class="kpi-value" style="color:var(--mpox-red)"><?= number_format($deces) ?></div>
            <div class="kpi-sub">CFR : <strong><?= $cfr ?>%</strong></div>
        </div>
        <div class="kpi kpi-green">
            <div class="kpi-icon">✅</div>
            <div class="kpi-label">Guéris</div>
            <div class="kpi-value" style="color:var(--mpox-green)"><?= number_format($gueris) ?></div>
            <div class="kpi-sub">Taux : <?= $tauxGueri ?>%</div>
        </div>
        <div class="kpi kpi-orange">
            <div class="kpi-icon">🏥</div>
            <div class="kpi-label">Hospitalisés</div>
            <div class="kpi-value" style="color:var(--mpox-orange)"><?= number_format($hospitalises) ?></div>
            <div class="kpi-sub"><?= $tauxHospi ?>% des cas</div>
        </div>
        <div class="kpi kpi-blue">
            <div class="kpi-icon">👤</div>
            <div class="kpi-label">Âge moyen</div>
            <div class="kpi-value" style="color:var(--mpox-blue)"><?= $ageMoyen ?></div>
            <div class="kpi-sub">Médiane : <?= $ageMedian ?> ans</div>
        </div>
    </div>

    <!-- COURBE ÉPIDÉMIQUE -->
    <div class="mcard" style="margin-bottom:16px;">
        <div class="mcard-title">
            <i class="bi bi-graph-up-arrow"></i>
            Courbe épidémique — Évolution des cas dans le temps
            <span style="margin-left:auto;font-size:.75rem;color:var(--text-muted);font-weight:400;">
                <button onclick="toggleEpiView('se')"   id="btn-se"   class="dv-btn" style="padding:3px 10px;font-size:.75rem;margin-right:4px;">Par SE</button>
                <button onclick="toggleEpiView('mois')" id="btn-mois" class="dv-btn dv-btn-primary" style="padding:3px 10px;font-size:.75rem;">Par mois</button>
            </span>
        </div>
        <div class="chart-wrap" style="height:240px;">
            <canvas id="chartEpi"></canvas>
        </div>
    </div>

    <!-- RÉPARTITION GÉOGRAPHIQUE -->
    <div class="sec-grid g12" style="margin-bottom:16px;">
        <div class="mcard">
            <div class="mcard-title"><i class="bi bi-map"></i> Répartition par Région</div>
            <div class="mtbl-scroll" style="overflow-x:auto;max-height:300px;overflow-y:auto;">
                <table class="mtbl">
                    <thead>
                        <tr>
                            <th>Région</th>
                            <th style="text-align:center;">Total</th>
                            <th style="text-align:center;">Confirmés</th>
                            <th style="text-align:center;">Décès</th>
                            <th style="text-align:center;">CFR%</th>
                            <th style="text-align:center;">H</th>
                            <th style="text-align:center;">F</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        uasort($byRegion, fn($a,$b) => $b['total'] <=> $a['total']);
                        foreach ($byRegion as $reg => $d):
                            $cfr_r = $d['total'] ? round($d['deces']/$d['total']*100,1) : 0;
                            $pct   = $total      ? round($d['total']/$total*100,1) : 0;
                        ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($reg) ?></div>
                                <div class="prog"><div class="prog-fill" style="width:<?= $pct ?>%;background:var(--accent);"></div></div>
                            </td>
                            <td style="text-align:center;font-weight:700;color:var(--text-primary);"><?= $d['total'] ?></td>
                            <td style="text-align:center;"><span class="mb mb-red"><?= $d['confirmes'] ?></span></td>
                            <td style="text-align:center;"><span class="mb mb-gray"><?= $d['deces'] ?></span></td>
                            <td style="text-align:center;color:<?= $cfr_r>5?'var(--mpox-red)':'var(--text-muted)' ?>;font-weight:<?= $cfr_r>5?700:400 ?>;"><?= $cfr_r ?>%</td>
                            <td style="text-align:center;color:var(--mpox-blue);"><?= $d['H'] ?></td>
                            <td style="text-align:center;color:var(--mpox-orange);"><?= $d['F'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>TOTAL</td>
                            <td style="text-align:center;"><?= $total ?></td>
                            <td style="text-align:center;"><?= $confirmes ?></td>
                            <td style="text-align:center;"><?= $deces ?></td>
                            <td style="text-align:center;"><?= $cfr ?>%</td>
                            <td style="text-align:center;"><?= $hommes ?></td>
                            <td style="text-align:center;"><?= $femmes ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="mcard">
            <div class="mcard-title"><i class="bi bi-bar-chart-line"></i> Top 10 Districts — Cas H/F</div>
            <div class="chart-wrap" style="height:280px;">
                <canvas id="chartDistrict"></canvas>
            </div>
        </div>
    </div>

    <!-- Graphique régions -->
    <div class="mcard" style="margin-bottom:16px;">
        <div class="mcard-title"><i class="bi bi-bar-chart"></i> Cas confirmés &amp; décès par Région</div>
        <div class="chart-wrap" style="height:220px;">
            <canvas id="chartRegion"></canvas>
        </div>
    </div>

    <!-- DÉMOGRAPHIE -->
    <div class="sec-grid g3" style="margin-bottom:16px;">
        <div class="mcard">
            <div class="mcard-title"><i class="bi bi-gender-ambiguous"></i> Répartition par sexe</div>
            <div class="chart-wrap" style="height:180px;">
                <canvas id="chartSexe"></canvas>
            </div>
            <div style="margin-top:12px;">
                <div class="srow">
                    <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:var(--mpox-blue);margin-right:6px;"></span>Masculin</span>
                    <span><strong><?= $hommes ?></strong> (<?= $total?round($hommes/$total*100,1):0 ?>%)</span>
                </div>
                <div class="srow">
                    <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:var(--mpox-orange);margin-right:6px;"></span>Féminin</span>
                    <span><strong><?= $femmes ?></strong> (<?= $total?round($femmes/$total*100,1):0 ?>%)</span>
                </div>
                <div class="srow" style="color:var(--text-muted);font-size:.78rem;">
                    <span>Sex ratio H/F</span>
                    <span><?= $femmes?round($hommes/$femmes,2):'N/A' ?></span>
                </div>
            </div>
        </div>

        <div class="mcard">
            <div class="mcard-title"><i class="bi bi-people"></i> Tranches d'âge</div>
            <div class="chart-wrap" style="height:180px;">
                <canvas id="chartTranche"></canvas>
            </div>
            <div style="margin-top:8px;font-size:.78rem;color:var(--text-muted);text-align:center;">
                Âge moyen : <strong style="color:var(--text-primary);"><?= $ageMoyen ?> ans</strong> · Médiane : <strong style="color:var(--text-primary);"><?= $ageMedian ?> ans</strong>
            </div>
        </div>

        <div class="mcard">
            <div class="mcard-title"><i class="bi bi-activity"></i> Évolution par sexe</div>
            <div class="chart-wrap" style="height:180px;">
                <canvas id="chartEvoSexe"></canvas>
            </div>
            <div style="margin-top:8px;">
                <div class="srow">
                    <span>CFR Masculin</span>
                    <span style="color:var(--mpox-red);font-weight:700;"><?= $bySexeEvo['Masculin']['Décédé'] ?> décès</span>
                </div>
                <div class="srow">
                    <span>CFR Féminin</span>
                    <span style="color:var(--mpox-orange);font-weight:700;"><?= $bySexeEvo['Féminin']['Décédé'] ?> décès</span>
                </div>
            </div>
        </div>
    </div>

    <!-- STATUTS VIH & CLASSIF -->
    <div class="sec-grid g2" style="margin-bottom:16px;">
        <div class="mcard">
            <div class="mcard-title"><i class="bi bi-shield-exclamation"></i> Statut VIH des patients</div>
            <?php foreach ($byVIH as $vihStat => $cnt):
                $pct = $total ? round($cnt/$total*100,1) : 0;
                $col = str_contains(strtolower($vihStat),'confirm') ? 'var(--mpox-red)'
                     : (str_contains(strtolower($vihStat),'neg') ? 'var(--mpox-green)' : 'var(--text-muted)');
            ?>
            <div class="srow">
                <span style="font-weight:600;"><?= htmlspecialchars($vihStat) ?></span>
                <span>
                    <strong><?= $cnt ?></strong>
                    <span style="color:var(--text-muted);margin-left:6px;"><?= $pct ?>%</span>
                </span>
            </div>
            <div class="prog"><div class="prog-fill" style="width:<?= $pct ?>%;background:<?= $col ?>;"></div></div>
            <?php endforeach; ?>
        </div>

        <div class="mcard">
            <div class="mcard-title"><i class="bi bi-clipboard-data"></i> Classification des cas</div>
            <div class="chart-wrap" style="height:180px;">
                <canvas id="chartClassif"></canvas>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:12px;font-size:.8rem;">
                <div class="srow"><span>Confirmés</span><span class="mb mb-red"><?= $confirmes ?></span></div>
                <div class="srow"><span>Suspects</span><span class="mb mb-orange"><?= $suspects ?></span></div>
                <div class="srow"><span>Probables</span><span class="mb mb-blue"><?= $probables ?></span></div>
                <div class="srow"><span>Non cas</span><span class="mb mb-gray"><?= $nonCas ?></span></div>
            </div>
        </div>
    </div>

    <!-- TABLE DISTRICTS -->
    <div class="mcard" style="margin-bottom:16px;">
        <div class="mcard-title">
            <i class="bi bi-pin-map"></i> Tableau détaillé par District
            <span style="margin-left:auto;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <button onclick="toggleDistrictView('all')" id="btn-dist-all" class="dv-btn dv-btn-primary" style="padding:3px 12px;font-size:.78rem;">
                    <i class="bi bi-list-ul"></i> Cumulé
                </button>
                <button onclick="toggleDistrictView('4w')" id="btn-dist-4w" class="dv-btn" style="padding:3px 12px;font-size:.78rem;">
                    <i class="bi bi-calendar-range"></i> 4 dernières SE
                </button>
                <input type="text" id="district-search" placeholder="🔍 Filtrer..." onkeyup="filterDistrictTable()">
            </span>
        </div>

        <!-- Badge informatif SE (caché par défaut, visible en mode 4W) -->
        <div id="dist-info-bar" style="display:none;font-size:.78rem;color:var(--text-muted);margin-bottom:10px;background:var(--accent-soft);border-radius:6px;padding:7px 12px;">
            <i class="bi bi-info-circle" style="color:var(--accent);"></i>
            Semaines épidémiologiques affichées :
            <strong style="color:var(--accent);">
                SE <?= implode(', SE ', array_reverse($seRange)) ?>
            </strong>
            — SE courante : <strong style="color:var(--accent);">S<?= $currentSE ?></strong>
            (S<?= $currentSE ?>, S<?= $seRange[1] ?>, S<?= $seRange[2] ?>, S<?= $seRange[3] ?>, S<?= $seRange[4] ?>)
        </div>

        <!-- Tableau cumulé (visible par défaut) -->
        <div id="district-all-wrap">
            <div class="mtbl-scroll" style="overflow-x:auto;max-height:350px;overflow-y:auto;">
                <table class="mtbl" id="district-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>District</th>
                            <th style="text-align:center;">Total</th>
                            <th style="text-align:center;">Confirmés</th>
                            <th style="text-align:center;">Décès</th>
                            <th style="text-align:center;">CFR%</th>
                            <th style="text-align:center;">Masculin</th>
                            <th style="text-align:center;">Féminin</th>
                            <th style="text-align:center;">% du total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        arsort($byDistrict);
                        $rank = 1;
                        foreach ($byDistrict as $dist => $d):
                            $cfr_d = $d['total'] ? round($d['deces']/$d['total']*100,1) : 0;
                            $pct_d = $total      ? round($d['total']/$total*100,1)       : 0;
                        ?>
                        <tr>
                            <td style="color:var(--text-muted);font-weight:700;"><?= $rank++ ?></td>
                            <td style="font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($dist) ?></td>
                            <td style="text-align:center;font-weight:700;color:var(--text-primary);"><?= $d['total'] ?></td>
                            <td style="text-align:center;"><span class="mb mb-red"><?= $d['confirmes'] ?></span></td>
                            <td style="text-align:center;"><?= $d['deces'] ?></td>
                            <td style="text-align:center;color:<?= $cfr_d>5?'var(--mpox-red)':'var(--text-muted)' ?>;font-weight:<?= $cfr_d>5?700:400 ?>;"><?= $cfr_d ?>%</td>
                            <td style="text-align:center;color:var(--mpox-blue);"><?= $d['H'] ?></td>
                            <td style="text-align:center;color:var(--mpox-orange);"><?= $d['F'] ?></td>
                            <td style="text-align:center;">
                                <div style="display:flex;align-items:center;gap:6px;">
                                    <div class="prog" style="flex:1"><div class="prog-fill" style="width:<?= min(100,$pct_d*3) ?>%;background:var(--accent);"></div></div>
                                    <span style="font-size:.75rem;color:var(--text-muted);min-width:32px;"><?= $pct_d ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tableau 4 dernières SE (caché par défaut) -->
        <div id="district-4w-wrap" style="display:none;">
            <div class="mtbl-scroll" style="overflow-x:auto;max-height:350px;overflow-y:auto;">
                <table class="mtbl" id="district-4w-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>District</th>
                            <th style="text-align:center;">Total (4 SE)</th>
                            <th style="text-align:center;">Confirmés</th>
                            <th style="text-align:center;">Décès</th>
                            <th style="text-align:center;">CFR%</th>
                            <th style="text-align:center;">Masculin</th>
                            <th style="text-align:center;">Féminin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($byDistrict4W)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center;color:var(--text-muted);padding:24px;">
                                <i class="bi bi-calendar-x" style="font-size:1.4rem;display:block;margin-bottom:6px;opacity:.4;"></i>
                                Aucun cas enregistré dans les 4 dernières semaines épidémiologiques
                            </td>
                        </tr>
                        <?php else:
                        $rank4w = 1;
                        foreach ($byDistrict4W as $dist => $d):
                            $cfr_d4 = $d['total'] ? round($d['deces']/$d['total']*100,1) : 0;
                        ?>
                        <tr>
                            <td style="color:var(--text-muted);font-weight:700;"><?= $rank4w++ ?></td>
                            <td style="font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($dist) ?></td>
                            <td style="text-align:center;font-weight:700;color:var(--text-primary);"><?= $d['total'] ?></td>
                            <td style="text-align:center;"><span class="mb mb-red"><?= $d['confirmes'] ?></span></td>
                            <td style="text-align:center;"><?= $d['deces'] ?></td>
                            <td style="text-align:center;color:<?= $cfr_d4>5?'var(--mpox-red)':'var(--text-muted)' ?>;font-weight:<?= $cfr_d4>5?700:400 ?>;"><?= $cfr_d4 ?>%</td>
                            <td style="text-align:center;color:var(--mpox-blue);"><?= $d['H'] ?></td>
                            <td style="text-align:center;color:var(--mpox-orange);"><?= $d['F'] ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Footer sync -->
    <div class="footer-sync" style="text-align:center;font-size:.78rem;padding:10px 0 20px;">
        <i class="bi bi-google"></i> Données synchronisées depuis Google Sheets ·
        ID feuille : <code><?= SHEET_ID ?></code> ·
        Prochaine mise à jour automatique dans <span id="countdown-footer">5:00</span>
    </div>

</div><!-- /dv-content -->

<!-- SCRIPTS -->
<script>
/* ── Variables CSS du thème actif ───────────────────────────────────────── */
function cssVar(name) {
  return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}

/* ── Couleurs sémantiques épidémio ──────────────────────────────────────── */
const C = {
  red:    '#e05252', redA:    'rgba(224,82,82,.25)',
  orange: '#f39c12', orangeA: 'rgba(243,156,18,.25)',
  blue:   '#3d9de0', blueA:   'rgba(61,157,224,.25)',
  green:  '#2ecc71', greenA:  'rgba(46,204,113,.25)',
  gray:   '#95a5a6',
};

function themeColors() {
  return {
    grid:   cssVar('--border')      || '#2a3d52',
    label:  cssVar('--text-muted')  || '#94a3b8',
    accent: cssVar('--accent')      || '#3d9de0',
    text:   cssVar('--text-primary')|| '#e2e8f0',
    cardBg: cssVar('--bg-card')     || '#1a2535',
  };
}

Chart.defaults.font.family = "'DM Sans', system-ui, sans-serif";
Chart.defaults.font.size   = 11;

function buildDarkAxes() {
  const t = themeColors();
  return {
    x: { grid: { color: t.grid + '99' }, ticks: { color: t.label } },
    y: { beginAtZero: true, grid: { color: t.grid + '99' }, ticks: { color: t.label } },
  };
}
function buildLegend() {
  return { labels: { color: themeColors().text } };
}

/* ── Données PHP → JS ───────────────────────────────────────────────────── */
const DATA = {
  se:    {
    labels:    <?= $jsonSELabels ?>,
    values:    <?= $jsonSE ?>,
    confirmes: <?= $jsonSEConfirm ?>,
  },
  month: {
    labels:    <?= $jsonMonthL ?>,
    cas:       <?= $jsonMonth ?>,
    confirmes: <?= $jsonMonthC ?>,
    deces:     <?= $jsonMonthD ?>,
    gueris:    <?= $jsonMonthG ?>,
  },
  district: { labels: <?= $jsonDistL ?>, total: <?= $jsonDistV ?>, H: <?= $jsonDistH ?>, F: <?= $jsonDistF ?> },
  region:   { labels: <?= $jsonRegL  ?>, total: <?= $jsonRegV  ?>, confirmes: <?= $jsonRegC ?>, deces: <?= $jsonRegD ?> },
  tranche:  { labels: <?= $jsonTrL   ?>, H: <?= $jsonTrH ?>, F: <?= $jsonTrF ?> },
  classif:  { labels: ['Confirmés','Suspects','Probables','Non cas'], values: [<?= $confirmes ?>,<?= $suspects ?>,<?= $probables ?>,<?= $nonCas ?>] },
  sexe:     { values: [<?= $hommes ?>, <?= $femmes ?>] },
  evoSexe:  {
    M: [<?= $bySexeEvo['Masculin']['Guéri'] ?>, <?= $bySexeEvo['Masculin']['Décédé'] ?>, <?= $bySexeEvo['Masculin']['Actif'] ?>],
    F: [<?= $bySexeEvo['Féminin']['Guéri']  ?>, <?= $bySexeEvo['Féminin']['Décédé']  ?>, <?= $bySexeEvo['Féminin']['Actif']  ?>],
  },
};

/* ── Courbe épidémique (avec cas confirmés) ─────────────────────────────── */
let epiChart, epiMode = 'mois';

function buildEpiChart(mode) {
  const ctx = document.getElementById('chartEpi');
  if (epiChart) epiChart.destroy();
  const darkAxes = buildDarkAxes();

  const d = mode === 'se' ? {
    labels: DATA.se.labels,
    datasets: [
      {
        label: 'Cas notifiés',
        data: DATA.se.values,
        borderColor: C.blue, backgroundColor: C.blueA,
        fill: true, tension: .4, pointRadius: 3,
      },
      {
        label: 'Cas confirmés',
        data: DATA.se.confirmes,
        borderColor: C.red, backgroundColor: 'transparent',
        fill: false, tension: .4, pointRadius: 3, borderDash: [5, 3],
      },
    ]
  } : {
    labels: DATA.month.labels,
    datasets: [
      {
        label: 'Cas notifiés',
        data: DATA.month.cas,
        borderColor: C.blue, backgroundColor: C.blueA,
        fill: true, tension: .4, pointRadius: 3,
      },
      {
        label: 'Cas confirmés',
        data: DATA.month.confirmes,
        borderColor: C.red, backgroundColor: 'transparent',
        fill: false, tension: .4, pointRadius: 3, borderDash: [5, 3],
      },
      {
        label: 'Décès',
        data: DATA.month.deces,
        borderColor: C.orange, backgroundColor: 'transparent',
        fill: false, tension: .4, borderDash: [4, 3], pointRadius: 3,
      },
      {
        label: 'Guéris',
        data: DATA.month.gueris,
        borderColor: C.green, backgroundColor: 'transparent',
        fill: false, tension: .4, borderDash: [4, 3], pointRadius: 3,
      },
    ]
  };

  epiChart = new Chart(ctx, {
    type: 'line', data: d,
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { position: 'top', labels: buildLegend().labels },
        tooltip: { mode: 'index', intersect: false },
      },
      scales: darkAxes,
    }
  });
}

function toggleEpiView(mode) {
  epiMode = mode;
  document.getElementById('btn-se').className   = mode === 'se'   ? 'dv-btn dv-btn-primary' : 'dv-btn';
  document.getElementById('btn-mois').className  = mode === 'mois' ? 'dv-btn dv-btn-primary' : 'dv-btn';
  buildEpiChart(mode);
}
buildEpiChart('mois');

/* ── Top 10 Districts ───────────────────────────────────────────────────── */
new Chart(document.getElementById('chartDistrict'), {
  type: 'bar',
  data: {
    labels: DATA.district.labels,
    datasets: [
      { label: 'Masculin', data: DATA.district.H, backgroundColor: C.blue,   barPercentage: .7 },
      { label: 'Féminin',  data: DATA.district.F, backgroundColor: C.orange, barPercentage: .7 },
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'top', labels: buildLegend().labels } },
    scales: {
      x: { ...buildDarkAxes().x, stacked: false, ticks: { ...buildDarkAxes().x.ticks, maxRotation: 40, font: { size: 9 } } },
      y: buildDarkAxes().y,
    },
  }
});

/* ── Cas par région ─────────────────────────────────────────────────────── */
new Chart(document.getElementById('chartRegion'), {
  type: 'bar',
  data: {
    labels: DATA.region.labels,
    datasets: [
      { label: 'Total cas',  data: DATA.region.total,    backgroundColor: C.blueA,   borderColor: C.blue,   borderWidth: 1.5 },
      { label: 'Confirmés',  data: DATA.region.confirmes,backgroundColor: C.redA,    borderColor: C.red,    borderWidth: 1.5 },
      { label: 'Décès',      data: DATA.region.deces,    backgroundColor: C.orangeA, borderColor: C.orange, borderWidth: 1.5 },
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'top', labels: buildLegend().labels } },
    scales: {
      x: { ...buildDarkAxes().x, ticks: { ...buildDarkAxes().x.ticks, maxRotation: 40, font: { size: 9 } } },
      y: buildDarkAxes().y,
    },
  }
});

/* ── Sexe (donut) ───────────────────────────────────────────────────────── */
new Chart(document.getElementById('chartSexe'), {
  type: 'doughnut',
  data: {
    labels: ['Masculin', 'Féminin'],
    datasets: [{ data: DATA.sexe.values, backgroundColor: [C.blue, C.orange], borderWidth: 0, hoverOffset: 6 }]
  },
  options: {
    responsive: true, maintainAspectRatio: false, cutout: '62%',
    plugins: { legend: { display: false } }
  }
});

/* ── Tranches d'âge ─────────────────────────────────────────────────────── */
new Chart(document.getElementById('chartTranche'), {
  type: 'bar',
  data: {
    labels: DATA.tranche.labels,
    datasets: [{ label: 'Cas', data: DATA.tranche.H, backgroundColor: C.blueA, borderColor: C.blue, borderWidth: 1.5 }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { ...buildDarkAxes().x, ticks: { ...buildDarkAxes().x.ticks, font: { size: 9 } } },
      y: buildDarkAxes().y,
    },
  }
});

/* ── Évolution × Sexe ───────────────────────────────────────────────────── */
new Chart(document.getElementById('chartEvoSexe'), {
  type: 'bar',
  data: {
    labels: ['Guéri', 'Décédé', 'Actif/Inconnu'],
    datasets: [
      { label: 'Masculin', data: DATA.evoSexe.M, backgroundColor: C.blue   },
      { label: 'Féminin',  data: DATA.evoSexe.F, backgroundColor: C.orange },
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'top', labels: buildLegend().labels } },
    scales: buildDarkAxes(),
  }
});

/* ── Classification (donut) ─────────────────────────────────────────────── */
new Chart(document.getElementById('chartClassif'), {
  type: 'doughnut',
  data: {
    labels: DATA.classif.labels,
    datasets: [{
      data: DATA.classif.values,
      backgroundColor: [C.red, C.orange, C.blue, C.gray],
      borderWidth: 0, hoverOffset: 6
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false, cutout: '55%',
    plugins: { legend: { position: 'bottom', labels: { font: { size: 10 }, color: themeColors().text } } }
  }
});

/* ── Filtre texte tableau districts ─────────────────────────────────────── */
function filterDistrictTable() {
  const q       = document.getElementById('district-search').value.toLowerCase();
  const isAll   = document.getElementById('district-all-wrap').style.display !== 'none';
  const tableId = isAll ? 'district-table' : 'district-4w-table';
  const rows    = document.querySelectorAll('#' + tableId + ' tbody tr');
  rows.forEach(r => {
    if (!r.cells[1]) return;
    r.style.display = r.cells[1].textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

/* ── Basculer vue districts (Cumulé ↔ 4 dernières SE) ───────────────────── */
function toggleDistrictView(mode) {
  const isAll = mode === 'all';

  document.getElementById('btn-dist-all').className = isAll ? 'dv-btn dv-btn-primary' : 'dv-btn';
  document.getElementById('btn-dist-4w').className  = isAll ? 'dv-btn' : 'dv-btn dv-btn-primary';

  document.getElementById('district-all-wrap').style.display  = isAll ? '' : 'none';
  document.getElementById('district-4w-wrap').style.display   = isAll ? 'none' : '';
  document.getElementById('dist-info-bar').style.display      = isAll ? 'none' : '';

  // Réinitialiser le filtre texte
  document.getElementById('district-search').value = '';
  filterDistrictTable();
}

/* ── Synchronisation automatique ───────────────────────────────────────── */
let countdown = 300;

function updateCountdown() {
  const m   = Math.floor(countdown / 60);
  const s   = String(countdown % 60).padStart(2, '0');
  const txt = `${m}:${s}`;
  const el1 = document.getElementById('next-sync');
  const el2 = document.getElementById('countdown-footer');
  if (el1) el1.textContent = `· Prochaine sync dans ${txt}`;
  if (el2) el2.textContent = txt;
  countdown--;
  if (countdown < 0) { countdown = 300; autoSync(); }
}

function autoSync() {
  document.getElementById('sync-dot').style.background = '#f39c12';
  fetch(window.location.href + '?sync=1&ts=' + Date.now(), { cache: 'no-store' })
    .then(() => { location.reload(); })
    .catch(() => { document.getElementById('sync-dot').style.background = '#e05252'; });
}

function forceSync() {
  const icon = document.getElementById('sync-icon');
  icon.style.animation = 'mpox-spin .7s linear infinite';
  setTimeout(() => location.reload(), 600);
}

setInterval(updateCountdown, 1000);
updateCountdown();
</script>

<?php include 'footer_shared.php'; ?>
