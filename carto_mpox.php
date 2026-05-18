<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: Login.php");
    exit();
}

$PAGE_ROLES = [
    'carto_mpox.php' => ['Super Admin', 'Visiteur', 'Visualiseur', 'Analyste'],
];
$userRole = $_SESSION["role"] ?? '';
if (!in_array($userRole, $PAGE_ROLES['carto_mpox.php'])) {
    header("Location: index.php");
    exit();
}

$page_title  = 'Cartographie Mpox';
$active_page = 'analyse_mpox';
$breadcrumb  = ['Accueil' => 'index.php', 'Analyse Mpox' => 'analyse_mpox.php', 'Cartographie' => ''];
include 'sidebar.php';

// ════════════════════════════════════════════════════════════════════════════
//  CONFIG & FONCTIONS (même config que analyse_mpox.php)
// ════════════════════════════════════════════════════════════════════════════
define('SHEET_ID',    '1VdqjKcr00GmRI-FuOopXhRwYolvkUJmtp3DSV9ow4CE');
define('SHEET_RANGE', 'BDD 28042026!A1:BF5000');
define('SA_KEY_FILE', __DIR__ . '/config/google_service_account.json');
define('CACHE_FILE',  __DIR__ . '/cache/mpox_data.json');
define('CACHE_TTL',   300);

function curlGet(string $url, array $headers = []): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => $headers, CURLOPT_FOLLOWLOCATION => true,
    ]);
    $resp = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    if ($err) throw new Exception("cURL GET : $err");
    return $resp ?: '';
}
function curlPost(string $url, string $body, array $headers = []): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 20, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $resp = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    if ($err) throw new Exception("cURL POST : $err");
    return $resp ?: '';
}
function getGoogleToken(): string {
    if (!file_exists(SA_KEY_FILE)) throw new Exception("SA Key introuvable");
    $key = json_decode(file_get_contents(SA_KEY_FILE), true);
    $now = time();
    $claim = ['iss'=>$key['client_email'],'scope'=>'https://www.googleapis.com/auth/spreadsheets.readonly',
              'aud'=>'https://oauth2.googleapis.com/token','iat'=>$now,'exp'=>$now+3600];
    $b64 = fn($d) => rtrim(strtr(base64_encode(is_array($d)?json_encode($d):$d),'+/','-_'),'=');
    $h = $b64(['alg'=>'RS256','typ'=>'JWT']); $p = $b64($claim);
    openssl_sign("$h.$p", $sig, $key['private_key'], 'SHA256');
    $jwt = "$h.$p.".$b64($sig);
    $resp = curlPost('https://oauth2.googleapis.com/token',
        http_build_query(['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$jwt]),
        ['Content-Type: application/x-www-form-urlencoded']);
    $token = json_decode($resp, true);
    if (isset($token['error'])) throw new Exception("OAuth2 : ".$token['error']);
    return $token['access_token'] ?? '';
}
function fetchSheetData(): array {
    if (file_exists(CACHE_FILE) && (time()-filemtime(CACHE_FILE)) < CACHE_TTL) {
        $cached = json_decode(file_get_contents(CACHE_FILE), true);
        if (!empty($cached)) return $cached;
    }
    try {
        $token = getGoogleToken();
        $url   = 'https://sheets.googleapis.com/v4/spreadsheets/'.SHEET_ID.'/values/'.rawurlencode(SHEET_RANGE)
               . '?majorDimension=ROWS&valueRenderOption=UNFORMATTED_VALUE';
        $resp  = curlGet($url, ["Authorization: Bearer $token"]);
        $data  = json_decode($resp, true);
        if (isset($data['error'])) throw new Exception("Sheets API : ".$data['error']['message']);
        $rows  = $data['values'] ?? [];
        if (!is_dir(dirname(CACHE_FILE))) mkdir(dirname(CACHE_FILE), 0755, true);
        file_put_contents(CACHE_FILE, json_encode($rows));
        return $rows;
    } catch (Exception $e) {
        if (file_exists(CACHE_FILE)) return json_decode(file_get_contents(CACHE_FILE), true) ?? [];
        return [];
    }
}

// ── Chargement données ────────────────────────────────────────────────────
$rawRows = fetchSheetData();
$lastSync = file_exists(CACHE_FILE) ? date('d/m/Y H:i:s', filemtime(CACHE_FILE)) : 'Jamais';
$headers = []; $records = [];
if (!empty($rawRows)) {
    $headers = array_shift($rawRows);
    foreach ($rawRows as $row) {
        if (empty(array_filter($row))) continue;
        while (count($row) < count($headers)) $row[] = '';
        $records[] = array_combine($headers, array_slice($row, 0, count($headers)));
    }
}

$COL = [
    'date_notif'    => 'Date de notification',
    'district'      => 'District',
    'classification'=> 'Classification de cas( cas suspect, confirmé, probable, non cas)',
    'conclusion'    => 'Conclusion 1',
];

// ── Date du jour & calcul des 4 dernières semaines ───────────────────────
$today     = date('Y-m-d');
$todayTs   = strtotime($today);
$week4Start= strtotime('-28 days', $todayTs); // 4 semaines = 28 jours

// ── Agrégation par district ───────────────────────────────────────────────
$districtData = []; // [district => [notif_today, confirm_today, notif_cumul, confirm_4w]]

foreach ($records as $r) {
    $district = trim($r[$COL['district']] ?? 'Inconnu');
    if (!$district || $district === 'Inconnu') continue;

    $dateStr  = trim($r[$COL['date_notif']] ?? '');
    $cas      = mb_strtolower(trim($r[$COL['conclusion']] ?? ''), 'UTF-8');
    $isConfirm= str_contains($cas, 'confirm');

    $ts = $dateStr ? strtotime($dateStr) : null;
    $dateFormatted = $ts ? date('Y-m-d', $ts) : null;

    if (!isset($districtData[$district])) {
        $districtData[$district] = [
            'notif_today'   => 0,
            'confirm_today' => 0,
            'notif_cumul'   => 0,
            'confirm_4w'    => 0,
        ];
    }

    // Notifiés aujourd'hui
    if ($dateFormatted === $today) {
        $districtData[$district]['notif_today']++;
        if ($isConfirm) $districtData[$district]['confirm_today']++;
    }

    // Notifiés cumulés (tous)
    $districtData[$district]['notif_cumul']++;

    // Confirmés 4 dernières semaines
    if ($ts && $ts >= $week4Start && $isConfirm) {
        $districtData[$district]['confirm_4w']++;
    }
}

// Totaux pour info
$totalNotifToday   = array_sum(array_column($districtData, 'notif_today'));
$totalConfirmToday = array_sum(array_column($districtData, 'confirm_today'));
$totalNotifCumul   = array_sum(array_column($districtData, 'notif_cumul'));
$totalConfirm4w    = array_sum(array_column($districtData, 'confirm_4w'));

$jsonDistrictData = json_encode($districtData);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
:root {
  --mpox-red:#c0392b; --mpox-orange:#e67e22; --mpox-blue:#2980b9;
  --mpox-green:#27ae60; --mpox-dark:#1a2332; --mpox-gray:#6b7a8d;
  --border:#e2e8f0;
}
.carto-wrap { padding: 0; }

/* ── Navigation cartes ── */
.map-nav {
  display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap;
}
.map-btn {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 18px; border-radius: 10px; border: 2px solid var(--border);
  background: #fff; cursor: pointer; font-size: .84rem; font-weight: 700;
  color: var(--mpox-dark); transition: all .2s; text-decoration: none;
}
.map-btn:hover { border-color: var(--mpox-blue); color: var(--mpox-blue); background: #f0f8ff; }
.map-btn.active { background: var(--mpox-blue); color: #fff; border-color: var(--mpox-blue); }
.map-btn.active-red  { background: var(--mpox-red);    color: #fff; border-color: var(--mpox-red); }
.map-btn.active-orange { background: var(--mpox-orange); color: #fff; border-color: var(--mpox-orange); }
.map-btn.active-green { background: var(--mpox-green);  color: #fff; border-color: var(--mpox-green); }
.map-btn .dot {
  width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
}

/* ── Layout ── */
.carto-layout { display: grid; grid-template-columns: 1fr 320px; gap: 16px; }
@media(max-width:900px) { .carto-layout { grid-template-columns: 1fr; } }

/* ── Map container ── */
.map-card {
  background: #fff; border: 1px solid var(--border); border-radius: 14px;
  overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.06);
}
.map-header {
  padding: 14px 18px; border-bottom: 1px solid var(--border);
  display: flex; align-items: center; gap: 10px;
}
.map-header-title { font-size: .95rem; font-weight: 800; color: var(--mpox-dark); }
.map-header-sub   { font-size: .78rem; color: var(--mpox-gray); margin-top: 2px; }
.map-header-badge {
  margin-left: auto; padding: 4px 14px; border-radius: 20px;
  font-size: .8rem; font-weight: 700;
}
#map-container { height: 560px; width: 100%; }

/* ── Légende ── */
.legend-card {
  background: #fff; border: 1px solid var(--border); border-radius: 14px;
  padding: 18px; box-shadow: 0 2px 12px rgba(0,0,0,.06);
}
.legend-title {
  font-size: .85rem; font-weight: 800; color: var(--mpox-dark);
  border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 14px;
  display: flex; align-items: center; gap: 8px;
}
.legend-item {
  display: flex; align-items: center; justify-content: space-between;
  padding: 7px 0; border-bottom: 1px solid var(--border); font-size: .82rem;
}
.legend-item:last-child { border: none; }
.legend-color {
  width: 22px; height: 14px; border-radius: 3px; flex-shrink: 0; margin-right: 8px;
}
.legend-left { display: flex; align-items: center; }
.legend-val  { font-weight: 700; color: var(--mpox-dark); }

/* ── Top districts ── */
.top-item {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 0; border-bottom: 1px solid var(--border); font-size: .82rem;
}
.top-item:last-child { border: none; }
.top-rank {
  width: 24px; height: 24px; border-radius: 50%; display: flex;
  align-items: center; justify-content: center; font-size: .72rem;
  font-weight: 800; flex-shrink: 0; color: #fff;
}
.top-bar-wrap { flex: 1; }
.top-bar { height: 5px; border-radius: 3px; margin-top: 3px; }

/* ── KPI mini ── */
.kpi-mini-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 14px; }
.kpi-mini {
  background: #f8faff; border: 1px solid var(--border); border-radius: 8px;
  padding: 10px 12px; text-align: center;
}
.kpi-mini-val  { font-size: 1.4rem; font-weight: 800; line-height: 1; }
.kpi-mini-lbl  { font-size: .68rem; color: var(--mpox-gray); font-weight: 600;
                  text-transform: uppercase; margin-top: 3px; }

/* ── Sync bar ── */
.sync-bar { display:flex;align-items:center;justify-content:space-between;background:#fff;
  border:1px solid var(--border);border-radius:10px;padding:10px 18px;
  margin-bottom:16px;font-size:.82rem;color:var(--mpox-gray); }
.sync-dot { width:8px;height:8px;border-radius:50%;background:var(--mpox-green);
  animation:pulse 2s infinite; }
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}

/* ── Tooltip ── */
.leaflet-popup-content-wrapper { border-radius: 10px !important; }

/* ── No data notice ── */
.no-data-notice {
  background: #fef9e7; border: 1px solid #f9ca24; border-radius: 10px;
  padding: 12px 16px; font-size: .82rem; color: #8b6914; margin-bottom: 14px;
  display: flex; align-items: center; gap: 8px;
#svg-map {
    background: #f8fafc;
    width: 100%;
    height: 560px;
}

.district {
    stroke: #ffffff;
    stroke-width: 1;
    cursor: pointer;
    transition: 0.2s;
}

.district:hover {
    stroke: #222;
    stroke-width: 2;
    opacity: 0.9;
}

.map-tooltip {
    position: absolute;
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 10px;
    pointer-events: none;
    font-size: 12px;
    box-shadow: 0 4px 10px rgba(0,0,0,.15);
    z-index: 9999;
}
}
</style>
</head>

<div class="dv-content dv-animate carto-wrap">

  <!-- En-tête ───────────────────────────────────────────────────────────── -->
  <div class="dv-page-header">
    <h1>
      <i class="bi bi-map" style="color:#2980b9;margin-right:8px;"></i>
      Cartographie épidémique — Mpox Madagascar par District
    </h1>
    <div class="dv-breadcrumb">
      <a href="index.php">Accueil</a>
      <i class="bi bi-chevron-right" style="font-size:.65rem;"></i>
      <a href="analyse_mpox.php">Analyse Mpox</a>
      <i class="bi bi-chevron-right" style="font-size:.65rem;"></i>
      <span>Cartographie</span>
    </div>
  </div>

  <!-- Barre de sync ─────────────────────────────────────────────────────── -->
  <div class="sync-bar">
    <div style="display:flex;align-items:center;gap:8px;">
      <div class="sync-dot"></div>
      <span>Données · <strong>Mise à jour :</strong> <?= htmlspecialchars($lastSync) ?></span>
    </div>
    <div style="display:flex;gap:8px;align-items:center;font-size:.8rem;">
      <span><i class="bi bi-geo-alt"></i> Fond de carte : <strong>DISTRICT.shp</strong></span>
      <a href="analyse_mpox.php" class="dv-btn dv-btn-outline" style="padding:5px 14px;font-size:.8rem;">
        <i class="bi bi-bar-chart-line"></i> Tableau de bord
      </a>
    </div>
  </div>

  <?php if (empty($districtData)): ?>
  <div class="no-data-notice">
    <i class="bi bi-exclamation-triangle-fill"></i>
    Aucune donnée disponible. Vérifiez la connexion Google Sheets depuis
    <a href="analyse_mpox.php">le tableau de bord</a>.
  </div>
  <?php endif; ?>

  <!-- Navigation entre les 4 cartes ───────────────────────────────────── -->
  <div class="map-nav">
    <button class="map-btn active" id="btn-notif-today" onclick="switchMap('notif_today')">
      <span class="dot" style="background:#2980b9;"></span>
      <span>
        <div>Cas notifiés aujourd'hui</div>
        <div style="font-size:.72rem;font-weight:400;opacity:.8;"><?= date('d/m/Y') ?></div>
      </span>
    </button>
    <button class="map-btn" id="btn-confirm-today" onclick="switchMap('confirm_today')">
      <span class="dot" style="background:#c0392b;"></span>
      <span>
        <div>Cas confirmés aujourd'hui</div>
        <div style="font-size:.72rem;font-weight:400;opacity:.8;"><?= date('d/m/Y') ?></div>
      </span>
    </button>
    <button class="map-btn" id="btn-notif-cumul" onclick="switchMap('notif_cumul')">
      <span class="dot" style="background:#e67e22;"></span>
      <span>
        <div>Cas notifiés cumulés</div>
        <div style="font-size:.72rem;font-weight:400;opacity:.8;">Toute la période</div>
      </span>
    </button>
    <button class="map-btn" id="btn-confirm-4w" onclick="switchMap('confirm_4w')">
      <span class="dot" style="background:#27ae60;"></span>
      <span>
        <div>Cas confirmés — 4 dernières semaines</div>
        <div style="font-size:.72rem;font-weight:400;opacity:.8;"><?= date('d/m/Y', $week4Start) ?> → <?= date('d/m/Y') ?></div>
      </span>
    </button>
  </div>

  <!-- Layout carte + légende ───────────────────────────────────────────── -->
  <div class="carto-layout">

    <!-- Carte Leaflet ───────────────────────────────────────────────────── -->
    <div class="map-card">
      <div class="map-header" id="map-header">
        <i class="bi bi-map" style="font-size:1.2rem;color:#2980b9;" id="map-icon"></i>
        <div>
          <div class="map-header-title" id="map-title">Cas notifiés aujourd'hui</div>
          <div class="map-header-sub" id="map-sub"><?= date('d/m/Y') ?> · Cliquez sur un district pour les détails</div>
        </div>
        <div class="map-header-badge" id="map-badge" style="background:#e8f4fd;color:#2980b9;">
          Total : <?= $totalNotifToday ?>
        </div>
      </div>
      <div id="map-container">
        <svg id="svg-map" width="100%" height="560"></svg>
      </div>
    </div>

    <!-- Panneau latéral ─────────────────────────────────────────────────── -->
    <div>
      <!-- KPI mini ── -->
      <div class="legend-card" style="margin-bottom:12px;">
        <div class="legend-title"><i class="bi bi-clipboard-data"></i> Résumé</div>
        <div class="kpi-mini-grid">
          <div class="kpi-mini">
            <div class="kpi-mini-val" style="color:#2980b9;"><?= $totalNotifToday ?></div>
            <div class="kpi-mini-lbl">Notifiés<br>aujourd'hui</div>
          </div>
          <div class="kpi-mini">
            <div class="kpi-mini-val" style="color:#c0392b;"><?= $totalConfirmToday ?></div>
            <div class="kpi-mini-lbl">Confirmés<br>aujourd'hui</div>
          </div>
          <div class="kpi-mini">
            <div class="kpi-mini-val" style="color:#e67e22;"><?= $totalNotifCumul ?></div>
            <div class="kpi-mini-lbl">Notifiés<br>cumulés</div>
          </div>
          <div class="kpi-mini">
            <div class="kpi-mini-val" style="color:#27ae60;"><?= $totalConfirm4w ?></div>
            <div class="kpi-mini-lbl">Confirmés<br>4 semaines</div>
          </div>
        </div>
      </div>

      <!-- Légende ── -->
      <div class="legend-card" style="margin-bottom:12px;">
        <div class="legend-title"><i class="bi bi-palette"></i> Légende — <span id="legend-label">Cas notifiés aujourd'hui</span></div>
        <div id="legend-items">
          <!-- généré dynamiquement par JS -->
        </div>
        <div style="font-size:.75rem;color:var(--mpox-gray);margin-top:10px;padding-top:8px;border-top:1px solid var(--border);">
          <i class="bi bi-info-circle"></i> Districts sans cas apparaissent en gris clair
        </div>
      </div>

      <!-- Top 5 districts ── -->
      <div class="legend-card">
        <div class="legend-title"><i class="bi bi-trophy"></i> Top 5 Districts</div>
        <div id="top5-list">
          <!-- généré dynamiquement par JS -->
        </div>
      </div>
    </div>
  </div>

  <!-- Note bas de page ─────────────────────────────────────────────────── -->
  <div style="text-align:center;font-size:.78rem;color:var(--mpox-gray);padding:14px 0 20px;">
    <i class="bi bi-geo"></i> Fond de carte : <strong>DISTRICT.shp</strong> · Madagascar ·
    Données : Google Sheets · Période affichée : <strong id="period-label">Aujourd'hui (<?= date('d/m/Y') ?>)</strong>
  </div>

</div><!-- /dv-content -->

<!-- ═══════════════════════════════════════════════════════════════════════
     SCRIPTS
════════════════════════════════════════════════════════════════════════ -->
<script>
// ── Données PHP → JS ─────────────────────────────────────────────────────
const DISTRICT_DATA = <?= $jsonDistrictData ?>;
const TODAY_STR     = '<?= date('d/m/Y') ?>';
const WEEK4_START   = '<?= date('d/m/Y', $week4Start) ?>';

// ── Config des 4 couches ─────────────────────────────────────────────────
const MAP_CONFIGS = {
  notif_today: {
    field: 'notif_today',
    title: 'Cas notifiés aujourd\'hui',
    sub:   TODAY_STR + ' · Cliquez sur un district',
    icon:  'bi-calendar-day',
    iconColor: '#2980b9',
    badge: 'background:#e8f4fd;color:#2980b9;',
    colors: ['#EBF5FB','#AED6F1','#5DADE2','#2980B9','#1A5276'],
    breaks: null, // calculé dynamiquement
    legendLabel: 'Cas notifiés aujourd\'hui',
    period: 'Aujourd\'hui (' + TODAY_STR + ')',
    btnId: 'btn-notif-today',
    btnClass: 'active',
  },
  confirm_today: {
    field: 'confirm_today',
    title: 'Cas confirmés aujourd\'hui',
    sub:   TODAY_STR + ' · Cliquez sur un district',
    icon:  'bi-virus',
    iconColor: '#c0392b',
    badge: 'background:#fdecea;color:#c0392b;',
    colors: ['#FDEDEC','#F1948A','#E74C3C','#C0392B','#7B241C'],
    breaks: null,
    legendLabel: 'Cas confirmés aujourd\'hui',
    period: 'Aujourd\'hui (' + TODAY_STR + ')',
    btnId: 'btn-confirm-today',
    btnClass: 'active-red',
  },
  notif_cumul: {
    field: 'notif_cumul',
    title: 'Cas notifiés cumulés',
    sub:   'Toute la période · Cliquez sur un district',
    icon:  'bi-layers',
    iconColor: '#e67e22',
    badge: 'background:#fef3e2;color:#e67e22;',
    colors: ['#FEF9E7','#FAD7A0','#F5B041','#E67E22','#784212'],
    breaks: null,
    legendLabel: 'Cas notifiés cumulés',
    period: 'Toute la période',
    btnId: 'btn-notif-cumul',
    btnClass: 'active-orange',
  },
  confirm_4w: {
    field: 'confirm_4w',
    title: 'Cas confirmés — 4 dernières semaines',
    sub:   WEEK4_START + ' → ' + TODAY_STR + ' · Cliquez sur un district',
    icon:  'bi-calendar-range',
    iconColor: '#27ae60',
    badge: 'background:#eafaf1;color:#27ae60;',
    colors: ['#EAFAF1','#A9DFBF','#52BE80','#27AE60','#145A32'],
    breaks: null,
    legendLabel: '4 dernières semaines',
    period: WEEK4_START + ' → ' + TODAY_STR,
    btnId: 'btn-confirm-4w',
    btnClass: 'active-green',
  },
};

// ── Init Leaflet ──────────────────────────────────────────────────────────
const svg = document.getElementById("svg-map");

let GEOJSON_DATA = null;
let currentField = 'notif_today';

const width = 1000;
const height = 560;

svg.setAttribute("viewBox", `0 0 ${width} ${height}`);

fetch("dist2.geojson")
.then(r => r.json())
.then(data => {

    GEOJSON_DATA = data;

    renderMap(currentField);

})
.catch(err => {
    console.error(err);

    document.getElementById("map-container").innerHTML = `
        <div style="padding:30px;text-align:center;color:red;">
            Erreur chargement dist2.geojson
        </div>
    `;
});

function renderMap(field)
{
    currentField = field;

    svg.innerHTML = "";

    const cfg = MAP_CONFIGS[field];

    const colors = cfg.colors;

    const breaks = computeBreaks(field, 5);

    let allCoords = [];

    GEOJSON_DATA.features.forEach(feature => {

        const geom = feature.geometry;

        if (geom.type === "Polygon") {

            geom.coordinates.forEach(ring => {
                ring.forEach(c => allCoords.push(c));
            });

        }

        if (geom.type === "MultiPolygon") {

            geom.coordinates.forEach(poly => {
                poly.forEach(ring => {
                    ring.forEach(c => allCoords.push(c));
                });
            });

        }

    });

    const lons = allCoords.map(c => c[0]);
    const lats = allCoords.map(c => c[1]);

    const minLon = Math.min(...lons);
    const maxLon = Math.max(...lons);

    const minLat = Math.min(...lats);
    const maxLat = Math.max(...lats);

    function project(lon, lat)
    {
        const x = ((lon - minLon) / (maxLon - minLon)) * width;

        const y =
            height -
            ((lat - minLat) / (maxLat - minLat)) * height;

        return [x, y];
    }

    GEOJSON_DATA.features.forEach(feature => {

        const districtName = getDistrictName(feature);

        const matchedKey = findDistrictKey(districtName);

        const value =
            matchedKey
                ? (DISTRICT_DATA[matchedKey][field] || 0)
                : 0;

        const color = getColor(value, breaks, colors);

        const path = document.createElementNS(
            "http://www.w3.org/2000/svg",
            "path"
        );

        let d = "";

        function drawPolygon(coords)
        {
            coords.forEach(ring => {

                ring.forEach((coord, i) => {

                    const [x, y] = project(coord[0], coord[1]);

                    if (i === 0)
                    {
                        d += `M ${x} ${y} `;
                    }
                    else
                    {
                        d += `L ${x} ${y} `;
                    }

                });

                d += "Z ";
            });
        }

        const geom = feature.geometry;

        if (geom.type === "Polygon")
        {
            drawPolygon(geom.coordinates);
        }

        if (geom.type === "MultiPolygon")
        {
            geom.coordinates.forEach(poly => {
                drawPolygon(poly);
            });
        }

        path.setAttribute("d", d);

        path.setAttribute("fill", color);

        path.setAttribute("class", "district");

        path.addEventListener("click", e => {

            const d =
                matchedKey
                    ? DISTRICT_DATA[matchedKey]
                    : null;

            showTooltip(
                e.pageX,
                e.pageY,
                districtName,
                d
            );

        });

        svg.appendChild(path);

    });

    updateUI(field, breaks, colors);
}

function showTooltip(x, y, district, d)
{
    let tooltip = document.getElementById("tooltip");

    if (!tooltip)
    {
        tooltip = document.createElement("div");

        tooltip.id = "tooltip";

        tooltip.className = "map-tooltip";

        document.body.appendChild(tooltip);
    }

    tooltip.style.left = x + 10 + "px";

    tooltip.style.top = y + 10 + "px";

    tooltip.innerHTML = `
        <strong>${district}</strong><br><br>

        📅 Notifiés aujourd'hui :
        <strong style="color:#2980b9;">
            ${d ? d.notif_today : 0}
        </strong><br>

        🔬 Confirmés aujourd'hui :
        <strong style="color:#c0392b;">
            ${d ? d.confirm_today : 0}
        </strong><br>

        📊 Cumulés :
        <strong style="color:#e67e22;">
            ${d ? d.notif_cumul : 0}
        </strong><br>

        ✅ Confirmés 4 semaines :
        <strong style="color:#27ae60;">
            ${d ? d.confirm_4w : 0}
        </strong>
    `;
}

document.addEventListener("click", e => {

    if (!e.target.classList.contains("district"))
    {
        const t = document.getElementById("tooltip");

        if (t) t.remove();
    }

});
</script>

<?php include 'footer_shared.php'; ?>