<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: Login.php");
    exit();
}

$PAGE_ROLES = ['carto_mpox.php' => ['Super Admin', 'Visualiseur', 'Analyste']];
$userRole   = $_SESSION["role"] ?? '';
if (!in_array($userRole, $PAGE_ROLES['carto_mpox.php'])) {
    header("Location: access_denied.php");
    exit();
}

$page_title  = 'Cartographie Mpox';
$active_page = 'analyse_mpox';
$breadcrumb  = ['Accueil' => 'index.php', 'Analyse Mpox' => 'analyse_mpox.php', 'Cartographie' => ''];
include 'sidebar.php';

// ═══════════════════════════════════════════════════════════════════
//  CONFIG
// ═══════════════════════════════════════════════════════════════════
define('SHEET_ID',    '1VdqjKcr00GmRI-FuOopXhRwYolvkUJmtp3DSV9ow4CE');
define('SHEET_RANGE', 'BDD 28042026!A1:BF5000');
define('SA_KEY_FILE', __DIR__ . '/config/google_service_account.json');
define('CACHE_FILE',  __DIR__ . '/cache/mpox_data.json');
define('CACHE_TTL',   300);

// ── Helpers HTTP ────────────────────────────────────────────────────
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

// ── Google OAuth2 JWT ────────────────────────────────────────────────
function getGoogleToken(): string {
    if (!file_exists(SA_KEY_FILE)) throw new Exception("SA Key introuvable");
    $key = json_decode(file_get_contents(SA_KEY_FILE), true);
    $now = time();
    $claim = [
        'iss' => $key['client_email'],
        'scope' => 'https://www.googleapis.com/auth/spreadsheets.readonly',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now, 'exp' => $now + 3600,
    ];
    $b64 = fn($d) => rtrim(strtr(base64_encode(is_array($d) ? json_encode($d) : $d), '+/', '-_'), '=');
    $h = $b64(['alg' => 'RS256', 'typ' => 'JWT']); $p = $b64($claim);
    openssl_sign("$h.$p", $sig, $key['private_key'], 'SHA256');
    $jwt  = "$h.$p." . $b64($sig);
    $resp = curlPost('https://oauth2.googleapis.com/token',
        http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt]),
        ['Content-Type: application/x-www-form-urlencoded']);
    $token = json_decode($resp, true);
    if (isset($token['error'])) throw new Exception("OAuth2 : " . $token['error']);
    return $token['access_token'] ?? '';
}

// ── Récupération données Sheets ──────────────────────────────────────
function fetchSheetData(): array {
    if (file_exists(CACHE_FILE) && (time() - filemtime(CACHE_FILE)) < CACHE_TTL) {
        $cached = json_decode(file_get_contents(CACHE_FILE), true);
        if (!empty($cached)) return $cached;
    }
    try {
        $token = getGoogleToken();
        $url   = 'https://sheets.googleapis.com/v4/spreadsheets/' . SHEET_ID . '/values/'
               . rawurlencode(SHEET_RANGE) . '?majorDimension=ROWS&valueRenderOption=UNFORMATTED_VALUE';
        $resp  = curlGet($url, ["Authorization: Bearer $token"]);
        $data  = json_decode($resp, true);
        if (isset($data['error'])) throw new Exception("Sheets API : " . $data['error']['message']);
        $rows  = $data['values'] ?? [];
        if (!is_dir(dirname(CACHE_FILE))) mkdir(dirname(CACHE_FILE), 0755, true);
        file_put_contents(CACHE_FILE, json_encode($rows));
        return $rows;
    } catch (Exception $e) {
        if (file_exists(CACHE_FILE)) return json_decode(file_get_contents(CACHE_FILE), true) ?? [];
        return [];
    }
}

// ── Parse données brutes ─────────────────────────────────────────────
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

// ═══════════════════════════════════════════════════════════════════
//  COLONNES
//  date_notif  : "Date de notification" → cumul toute période
//  date_cousp  : "Date cousp"           → aujourd'hui + 4 semaines
//  district    : "District"
//  conclusion  : "Conclusion 1"
// ═══════════════════════════════════════════════════════════════════
$COL = [
    'date_notif' => 'Date de notification',
    'date_cousp' => 'Date cousp',
    'district'   => 'District',
    'conclusion' => 'Conclusion 1',
];

// ── Parser de date multi-format ──────────────────────────────────────
function parseDate($raw): ?int {
    if (empty($raw)) return null;
    $raw = trim((string)$raw);
    // Numérique Excel (jours depuis 1899-12-30)
    if (is_numeric($raw) && (int)$raw > 20000 && (int)$raw < 60000) {
        return strtotime('1899-12-30 +' . (int)$raw . ' days');
    }
    foreach (['Y-m-d','d/m/Y','d-m-Y','m/d/Y','Y/m/d','d.m.Y','d/m/Y H:i:s','Y-m-d H:i:s'] as $fmt) {
        $dt = DateTimeImmutable::createFromFormat($fmt, $raw);
        if ($dt !== false) return $dt->getTimestamp();
    }
    $ts = strtotime($raw);
    return ($ts !== false && $ts > 0) ? $ts : null;
}

// ═══════════════════════════════════════════════════════════════════
//  CALCUL PAR DISTRICT
// ═══════════════════════════════════════════════════════════════════
$today      = date('Y-m-d');
$todayTs    = strtotime($today);
$week4Start = strtotime('-28 days', $todayTs);
$districtData = [];

foreach ($records as $r) {
    $district = trim($r[$COL['district']] ?? '');
    if (!$district || strtolower($district) === 'inconnu') continue;

    $cas       = mb_strtolower(trim($r[$COL['conclusion']] ?? ''), 'UTF-8');
    $isConfirm = str_contains($cas, 'confirm');

    // Date de notification → cumul total uniquement
    $tsNotif   = parseDate($r[$COL['date_notif']] ?? '');

    // Date cousp → aujourd'hui + 4 semaines
    $tsCousp   = parseDate($r[$COL['date_cousp']] ?? '');
    $dateCousp = $tsCousp ? date('Y-m-d', $tsCousp) : null;

    if (!isset($districtData[$district])) {
        $districtData[$district] = [
            'notif_today'   => 0,  // date_cousp == today
            'confirm_today' => 0,  // date_cousp == today + confirmé
            'notif_cumul'   => 0,  // date_notif existante (toute période)
            'notif_4w'      => 0,  // date_cousp dans fenêtre 4 sem
            'confirm_4w'    => 0,  // date_cousp dans fenêtre 4 sem + confirmé
        ];
    }

    // Notifiés & confirmés aujourd'hui (basé sur Date cousp)
    if ($dateCousp === $today) {
        $districtData[$district]['notif_today']++;
        if ($isConfirm) $districtData[$district]['confirm_today']++;
    }

    // Notifiés cumulés (basé sur Date de notification)
    if ($tsNotif !== null) {
        $districtData[$district]['notif_cumul']++;
    }

    // 4 semaines (basé sur Date cousp)
    if ($tsCousp !== null && $tsCousp >= $week4Start && $tsCousp <= ($todayTs + 86399)) {
        $districtData[$district]['notif_4w']++;
        if ($isConfirm) $districtData[$district]['confirm_4w']++;
    }
}

// ── Totaux globaux ───────────────────────────────────────────────────
$totalNotifToday   = array_sum(array_column($districtData, 'notif_today'));
$totalConfirmToday = array_sum(array_column($districtData, 'confirm_today'));
$totalNotifCumul   = array_sum(array_column($districtData, 'notif_cumul'));
$totalNotif4w      = array_sum(array_column($districtData, 'notif_4w'));
$totalConfirm4w    = array_sum(array_column($districtData, 'confirm_4w'));

// ── Top 5 ────────────────────────────────────────────────────────────
$top5cumul = $districtData;
uasort($top5cumul, fn($a, $b) => $b['notif_cumul'] <=> $a['notif_cumul']);
$top5cumul = array_slice($top5cumul, 0, 5, true);

$top5conf4w = $districtData;
uasort($top5conf4w, fn($a, $b) => $b['confirm_4w'] <=> $a['confirm_4w']);
$top5conf4w = array_slice($top5conf4w, 0, 5, true);
?>

<!-- ══ Leaflet ══════════════════════════════════════════════════════ -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- ══ Styles ══════════════════════════════════════════════════════ -->
<style>
/* ── Carte (identique à analyse.php) ───────────────── */
#map {
    height: 520px;
    min-height: 300px;
    max-height: 90vh;
    resize: vertical;
    overflow: auto;
    border-radius: 14px;
    border: 1px solid var(--border);
    position: relative;
    z-index: 1;
    transition: height 0.25s ease;
}

/* Fix Leaflet z-index dans sidebar layout */
.leaflet-pane         { z-index: 4 !important; }
.leaflet-top,
.leaflet-bottom       { z-index: 5 !important; }
.leaflet-control      { z-index: 6 !important; }

/* ── Onglets couches ───────────────────────────────── */
.map-nav { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px; }
.map-tab {
    display:flex; align-items:center; gap:8px; padding:9px 16px;
    border-radius:10px; border:1.5px solid var(--border);
    background:var(--bg-card); cursor:pointer; font-size:.81rem; font-weight:700;
    color:var(--text-secondary); transition:all .18s; line-height:1.3;
    font-family:'Syne', sans-serif;
}
.map-tab:hover { border-color:var(--accent); color:var(--accent); background:var(--accent-soft); }
.map-tab .tab-dot { width:9px; height:9px; border-radius:50%; flex-shrink:0; }
.map-tab .tab-sub { font-size:.67rem; font-weight:400; opacity:.75; display:block; }
.map-tab.t-blue   { background:#2980b9; color:#fff; border-color:#2980b9; }
.map-tab.t-red    { background:#c0392b; color:#fff; border-color:#c0392b; }
.map-tab.t-orange { background:#e67e22; color:#fff; border-color:#e67e22; }
.map-tab.t-green  { background:#27ae60; color:#fff; border-color:#27ae60; }

/* ── Légende ───────────────────────────────────────── */
.map-legend {
    background: var(--bg-card); border: 1px solid var(--border);
    border-radius: 10px; padding: 12px 14px;
    font-size: .78rem; color: var(--text-secondary); line-height: 2;
}
.legend-dot {
    display:inline-block; width:16px; height:16px;
    border-radius:4px; margin-right:6px; vertical-align:middle;
}

/* ── Popup Leaflet (même style qu'analyse.php) ─────── */
.leaflet-popup-content-wrapper {
    background: var(--bg-card, #fff) !important;
    border: 1px solid var(--border, #e2e8f0) !important;
    border-radius: 12px !important;
    box-shadow: 0 6px 24px rgba(0,0,0,.14) !important;
    padding: 0 !important;
}
.leaflet-popup-content { margin: 0 !important; min-width: 200px; }
.leaflet-popup-tip     { background: var(--bg-card, #fff) !important; }
.leaflet-popup-close-button {
    color: var(--text-secondary, #666) !important;
    font-size: 1.1rem !important; top: 8px !important; right: 10px !important;
}
.popup-inner {
    padding: 13px 15px; font-size: .82rem; color: var(--text-primary, #1a202c);
}
.popup-title {
    font-weight: bold; font-size: 13px; margin-bottom: 4px;
    color: var(--text-primary, #1a202c);
}
.popup-sub {
    font-size: 11px; color: #666; margin-bottom: 8px;
}
.popup-table {
    width: 100%; border-collapse: collapse; font-size: 11px; margin-top: 4px;
}
.popup-table th {
    background: var(--bg-base, #f5f5f5); padding: 3px 7px;
    text-align: left; font-weight: 600; color: var(--text-secondary, #555);
}
.popup-table td {
    padding: 3px 7px; border-top: 1px solid var(--border, #eee);
}
.popup-table td:last-child { text-align: right; font-weight: 700; }

/* ── Contrôles carte ───────────────────────────────── */
.map-controls {
    display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
    margin-bottom: 12px;
}

/* ── KPI grid ──────────────────────────────────────── */
.kpi-4 { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:16px; }
@media(max-width:720px) { .kpi-4 { grid-template-columns:1fr 1fr; } }

/* ── Top bars ──────────────────────────────────────── */
.top-bar-item {
    display:flex; align-items:center; gap:10px;
    padding:7px 0; border-bottom:1px solid var(--border); font-size:.82rem;
}
.top-bar-item:last-child { border:none; }
.top-num {
    width:22px; height:22px; border-radius:50%; display:flex;
    align-items:center; justify-content:center;
    font-size:.7rem; font-weight:800; color:#fff; flex-shrink:0;
}
.bar-bg  { flex:1; background:var(--border); border-radius:3px; height:5px; }
.bar-fill { height:5px; border-radius:3px; }

/* ── Pulse dot ─────────────────────────────────────── */
.pulse-dot {
    display:inline-block; width:8px; height:8px;
    border-radius:50%; background:#27ae60;
    animation:pdot 2s infinite; margin-right:6px;
}
@keyframes pdot { 0%,100%{opacity:1} 50%{opacity:.3} }

/* ── Contrôles taille ──────────────────────────────── */
.map-size-controls { display:flex; gap:8px; flex-wrap:wrap; }
</style>

<div class="dv-content dv-animate">

    <!-- ══ En-tête ═════════════════════════════════════════ -->
    <div class="dv-page-header">
        <h1>
            <i class="bi bi-map" style="color:#2980b9;margin-right:8px;"></i>
            Cartographie épidémique Mpox — Districts de Madagascar
        </h1>
        <div class="dv-breadcrumb">
            <a href="index.php">Accueil</a>
            <i class="bi bi-chevron-right" style="font-size:.65rem;"></i>
            <a href="analyse_mpox.php">Analyse Mpox</a>
            <i class="bi bi-chevron-right" style="font-size:.65rem;"></i>
            <span>Cartographie</span>
        </div>
    </div>

    <!-- ══ Barre sync ══════════════════════════════════════ -->
    <div class="dv-card dv-animate dv-animate-d1"
         style="padding:10px 18px;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
        <div style="font-size:.82rem;color:var(--text-secondary);display:flex;align-items:center;">
            <span class="pulse-dot"></span>
            Mise à jour :
            <strong style="color:var(--text-primary);margin:0 4px;"><?= htmlspecialchars($lastSync) ?></strong>
            · <?= number_format(count($records)) ?> enregistrements
            <span style="margin-left:12px;padding:2px 10px;border-radius:20px;
                  background:#e8f8f0;color:#27ae60;font-size:.74rem;font-weight:700;">
                <i class="bi bi-calendar-check"></i>
                Date cousp pour J0 &amp; 4 semaines
            </span>
        </div>
        <a href="analyse_mpox.php" class="dv-btn dv-btn-outline" style="font-size:.8rem;padding:5px 14px;">
            <i class="bi bi-bar-chart-line"></i> Tableau de bord
        </a>
    </div>

    <!-- ══ KPI rapides ══════════════════════════════════════ -->
    <div class="kpi-4 dv-animate dv-animate-d1">
        <div class="dv-stat" style="border-left:3px solid #2980b9;">
            <div class="dv-stat-label">Notifiés aujourd'hui</div>
            <div class="dv-stat-value" style="color:#2980b9;"><?= $totalNotifToday ?></div>
            <div class="dv-stat-sub"><?= date('d/m/Y') ?> · Date cousp</div>
        </div>
        <div class="dv-stat" style="border-left:3px solid #c0392b;">
            <div class="dv-stat-label">Confirmés aujourd'hui</div>
            <div class="dv-stat-value" style="color:#c0392b;"><?= $totalConfirmToday ?></div>
            <div class="dv-stat-sub"><?= date('d/m/Y') ?> · Date cousp</div>
        </div>
        <div class="dv-stat" style="border-left:3px solid #e67e22;">
            <div class="dv-stat-label">Notifiés cumulés</div>
            <div class="dv-stat-value" style="color:#e67e22;"><?= $totalNotifCumul ?></div>
            <div class="dv-stat-sub">Toute la période · Date notif</div>
        </div>
        <div class="dv-stat" style="border-left:3px solid #27ae60;">
            <div class="dv-stat-label">Confirmés 4 semaines</div>
            <div class="dv-stat-value" style="color:#27ae60;"><?= $totalConfirm4w ?></div>
            <div class="dv-stat-sub"><?= date('d/m/Y', $week4Start) ?> → <?= date('d/m/Y') ?> · Date cousp</div>
        </div>
    </div>

    <!-- ══ Carte choroplèthe ════════════════════════════════ -->
    <div class="dv-card dv-animate dv-animate-d2">

        <!-- Header -->
        <div class="dv-card-header">
            <i class="bi bi-map"></i>
            <span id="map-card-title">Cas notifiés aujourd'hui — <?= date('d/m/Y') ?></span>
            <span class="dv-badge" id="map-total-badge"
                  style="margin-left:8px;background:#e8f4fd;color:#2980b9;">
                Total : <?= $totalNotifToday ?>
            </span>
            <div style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap;">
                <button class="dv-btn dv-btn-outline" style="padding:4px 10px;font-size:0.75rem;"
                        onclick="setMapHeight(400)">Petite</button>
                <button class="dv-btn dv-btn-outline" style="padding:4px 10px;font-size:0.75rem;"
                        onclick="setMapHeight(520)">Moyenne</button>
                <button class="dv-btn dv-btn-outline" style="padding:4px 10px;font-size:0.75rem;"
                        onclick="setMapHeight(700)">Grande</button>
                <button class="dv-btn dv-btn-outline" style="padding:4px 10px;font-size:0.75rem;"
                        onclick="toggleFullscreenMap()">
                    <i class="bi bi-arrows-fullscreen"></i> Plein écran
                </button>
                <button class="dv-btn dv-btn-outline" style="padding:4px 10px;font-size:0.75rem;"
                        onclick="resetMap()">Recentrer</button>
            </div>
        </div>

        <!-- Onglets couches -->
        <div style="padding:14px 18px 0;">
            <div class="map-nav">
                <button class="map-tab t-blue" id="btn-notif-today" onclick="switchLayer('notif_today')">
                    <span class="tab-dot" style="background:#fff;opacity:.8;"></span>
                    <span>Notifiés aujourd'hui
                        <span class="tab-sub"><?= date('d/m/Y') ?> · Date cousp</span>
                    </span>
                </button>
                <button class="map-tab" id="btn-confirm-today" onclick="switchLayer('confirm_today')">
                    <span class="tab-dot" style="background:#c0392b;"></span>
                    <span>Confirmés aujourd'hui
                        <span class="tab-sub"><?= date('d/m/Y') ?> · Date cousp</span>
                    </span>
                </button>
                <button class="map-tab" id="btn-notif-cumul" onclick="switchLayer('notif_cumul')">
                    <span class="tab-dot" style="background:#e67e22;"></span>
                    <span>Notifiés cumulés
                        <span class="tab-sub">Toute la période · Date notif</span>
                    </span>
                </button>
                <button class="map-tab" id="btn-confirm-4w" onclick="switchLayer('confirm_4w')">
                    <span class="tab-dot" style="background:#27ae60;"></span>
                    <span>Confirmés 4 semaines
                        <span class="tab-sub"><?= date('d/m/Y', $week4Start) ?> → <?= date('d/m/Y') ?> · Date cousp</span>
                    </span>
                </button>
            </div>
        </div>

        <!-- Carte Leaflet -->
        <div id="map"></div>

        <!-- Légende + infos -->
        <div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:14px;align-items:flex-start;">
            <div class="map-legend" id="dyn-legend" style="min-width:180px;"></div>
            <div class="map-legend" style="flex:1;min-width:220px;">
                <div style="font-weight:700;margin-bottom:6px;color:var(--text-primary);">
                    <i class="bi bi-info-circle"></i> Sources des données
                </div>
                <div style="font-size:.75rem;line-height:1.8;color:var(--text-muted);">
                    <strong>Source :</strong> Google Sheets DVSSER<br>
                    <strong>Fond de carte :</strong> Districts3.geojson · OSM / CARTO<br>
                    <strong>Notifiés J0 &amp; 4 sem. :</strong> colonne <em>Date cousp</em><br>
                    <strong>Notifiés cumulés :</strong> colonne <em>Date de notification</em><br>
                    <strong>Confirmés :</strong> Conclusion 1 contient « confirmé »<br>
                    <strong>Fenêtre 4 sem. :</strong> <?= date('d/m/Y', $week4Start) ?> → <?= date('d/m/Y') ?>
                </div>
            </div>
        </div>

    </div><!-- /carte -->

    <!-- ══ Top 5 ════════════════════════════════════════════ -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;" class="dv-animate dv-animate-d2">

        <!-- Top 5 cumulés -->
        <div class="dv-card">
            <div class="dv-card-header">
                <i class="bi bi-trophy"></i> Top 5 — Cas notifiés cumulés
                <span class="dv-badge" style="margin-left:auto;"><?= $totalNotifCumul ?> total</span>
            </div>
            <?php
            $colors5 = ['#e53e3e','#f59e0b','#2980b9','#27ae60','#7c3aed'];
            $maxC = max(array_column($top5cumul, 'notif_cumul') ?: [1]);
            $i = 1;
            foreach ($top5cumul as $dist => $d):
                $pct = $maxC > 0 ? round($d['notif_cumul'] / $maxC * 100) : 0;
                $col = $colors5[$i - 1] ?? '#aaa';
            ?>
            <div class="top-bar-item">
                <div class="top-num" style="background:<?= $col ?>;"><?= $i ?></div>
                <div style="flex:1;">
                    <div style="font-weight:700;font-size:.82rem;color:var(--text-primary);">
                        <?= htmlspecialchars($dist) ?>
                    </div>
                    <div class="bar-bg" style="margin-top:4px;">
                        <div class="bar-fill" style="width:<?= $pct ?>%;background:<?= $col ?>;"></div>
                    </div>
                </div>
                <div style="font-weight:800;font-size:.9rem;color:<?= $col ?>;min-width:32px;text-align:right;">
                    <?= $d['notif_cumul'] ?>
                </div>
            </div>
            <?php $i++; endforeach; ?>
        </div>

        <!-- Top 5 confirmés 4 semaines -->
        <div class="dv-card">
            <div class="dv-card-header">
                <i class="bi bi-calendar-range"></i> Top 5 — Confirmés 4 dernières semaines
                <span class="dv-badge" style="margin-left:auto;background:#eafaf1;color:#27ae60;">
                    <?= $totalConfirm4w ?> total
                </span>
            </div>
            <?php
            $maxC4 = max(array_column($top5conf4w, 'confirm_4w') ?: [1]);
            $i2 = 1;
            foreach ($top5conf4w as $dist => $d):
                $pct = $maxC4 > 0 ? round($d['confirm_4w'] / $maxC4 * 100) : 0;
                $col = $colors5[$i2 - 1] ?? '#aaa';
            ?>
            <div class="top-bar-item">
                <div class="top-num" style="background:<?= $col ?>;"><?= $i2 ?></div>
                <div style="flex:1;">
                    <div style="font-weight:700;font-size:.82rem;color:var(--text-primary);">
                        <?= htmlspecialchars($dist) ?>
                    </div>
                    <div class="bar-bg" style="margin-top:4px;">
                        <div class="bar-fill" style="width:<?= $pct ?>%;background:<?= $col ?>;"></div>
                    </div>
                </div>
                <div style="font-weight:800;font-size:.9rem;color:<?= $col ?>;min-width:32px;text-align:right;">
                    <?= $d['confirm_4w'] ?>
                </div>
            </div>
            <?php $i2++; endforeach; ?>
        </div>

    </div><!-- /top 5 -->

</div><!-- /.dv-content -->

<!-- ══ Script Leaflet (style analyse.php) ══════════════════════════ -->
<script>
// ── Données PHP → JS ─────────────────────────────────────────────────
const DISTRICT_DATA = <?= json_encode($districtData, JSON_UNESCAPED_UNICODE) ?>;
const TOTALS = {
    notif_today:   <?= $totalNotifToday ?>,
    confirm_today: <?= $totalConfirmToday ?>,
    notif_cumul:   <?= $totalNotifCumul ?>,
    confirm_4w:    <?= $totalConfirm4w ?>,
};

// ── Palettes choroplèthes par couche ──────────────────────────────────
const LAYERS_CFG = {
    notif_today: {
        title:      'Cas notifiés aujourd\'hui — <?= date('d/m/Y') ?>',
        label:      'Notifiés aujourd\'hui',
        btnId:      'btn-notif-today',
        activeClass:'t-blue',
        badgeStyle: 'background:#e8f4fd;color:#2980b9;',
        colors:     { 0:'#d4d4d4', 1:'#AED6F1', 2:'#5DADE2', 3:'#2E86C1', 4:'#1A5276', 5:'#0d2e47' },
    },
    confirm_today: {
        title:      'Cas confirmés aujourd\'hui — <?= date('d/m/Y') ?>',
        label:      'Confirmés aujourd\'hui',
        btnId:      'btn-confirm-today',
        activeClass:'t-red',
        badgeStyle: 'background:#fdecea;color:#c0392b;',
        colors:     { 0:'#d4d4d4', 1:'#F5B7B1', 2:'#EC7063', 3:'#E74C3C', 4:'#C0392B', 5:'#7B241C' },
    },
    notif_cumul: {
        title:      'Cas notifiés cumulés — toute la période',
        label:      'Notifiés cumulés',
        btnId:      'btn-notif-cumul',
        activeClass:'t-orange',
        badgeStyle: 'background:#fef3e2;color:#e67e22;',
        colors:     { 0:'#d4d4d4', 1:'#FAD7A0', 2:'#F5B041', 3:'#E67E22', 4:'#CA6F1E', 5:'#784212' },
    },
    confirm_4w: {
        title:      'Cas confirmés — 4 dernières semaines',
        label:      'Confirmés 4 sem.',
        btnId:      'btn-confirm-4w',
        activeClass:'t-green',
        badgeStyle: 'background:#eafaf1;color:#27ae60;',
        colors:     { 0:'#d4d4d4', 1:'#A9DFBF', 2:'#52BE80', 3:'#27AE60', 4:'#1E8449', 5:'#145A32' },
    },
};

// ── Init Leaflet (même pattern qu'analyse.php) ────────────────────────
let map = L.map('map').setView([-18.879, 47.508], 6);
let currentLayer = null;
let geojsonData  = null;
let currentField = 'notif_today';

// Tuiles adaptatives dark/light comme analyse.php
function getTileUrl() {
    const dark = document.documentElement.getAttribute('data-theme') === 'dark';
    return dark
        ? 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png'
        : 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png';
}

let tileLayer = L.tileLayer(getTileUrl(), {
    attribution: '&copy; <a href="https://openstreetmap.org">OSM</a> &copy; <a href="https://carto.com">CARTO</a>',
    maxZoom: 10,
}).addTo(map);

// ── Normalisation noms ────────────────────────────────────────────────
function normStr(s) {
    return (s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
}
function getDistrictName(props) {
    return props.NOM_DISTRI || props.NOM_DIST || props.NOMDIST
        || props.District   || props.district
        || props.NAME_3     || props.NAME      || props.NOM
        || props.nom        || props.name      || '';
}
function matchDistrictKey(geoName) {
    if (!geoName) return null;
    const ng = normStr(geoName);
    for (const k of Object.keys(DISTRICT_DATA))
        if (normStr(k) === ng) return k;
    for (const k of Object.keys(DISTRICT_DATA))
        if (normStr(k).includes(ng) || ng.includes(normStr(k))) return k;
    return null;
}

// ── Calcul niveau (0–5) pour un district et une couche ───────────────
function computeBreaks(field) {
    const vals = Object.values(DISTRICT_DATA)
        .map(d => d[field] || 0).filter(v => v > 0).sort((a, b) => a - b);
    if (!vals.length) return [0, 1, 2, 4, 8];
    const max = vals[vals.length - 1];
    return [0,
        Math.round(max * 0.10),
        Math.round(max * 0.30),
        Math.round(max * 0.60),
        Math.round(max * 0.85),
    ];
}
function getNiveau(value, breaks) {
    if (!value || value === 0) return 0;
    for (let i = breaks.length - 1; i >= 0; i--)
        if (value > breaks[i]) return Math.min(i + 1, 5);
    return 1;
}

// ── Style feature (même logique que analyse.php) ─────────────────────
let _breaks = [0,1,2,4,8];
function styleFeature(feature) {
    const name   = getDistrictName(feature.properties);
    const key    = matchDistrictKey(name);
    const value  = key ? (DISTRICT_DATA[key][currentField] || 0) : 0;
    const niveau = getNiveau(value, _breaks);
    const colors = LAYERS_CFG[currentField].colors;
    return {
        color:       '#555',
        weight:      0.7,
        fillColor:   colors[niveau] || '#d4d4d4',
        fillOpacity: niveau > 0 ? 0.75 : 0.35,
    };
}

// ── Popup (même structure que analyse.php) ────────────────────────────
function buildPopup(feature) {
    const name  = getDistrictName(feature.properties);
    const key   = matchDistrictKey(name);
    const d     = key ? DISTRICT_DATA[key] : null;
    const v     = f => (d ? (d[f] || 0) : 0);

    const rows = [
        { label: 'Notifiés aujourd\'hui',  color: '#2980b9', field: 'notif_today'   },
        { label: 'Confirmés aujourd\'hui', color: '#c0392b', field: 'confirm_today' },
        { label: 'Notifiés cumulés',       color: '#e67e22', field: 'notif_cumul'   },
        { label: 'Confirmés 4 sem.',       color: '#27ae60', field: 'confirm_4w'    },
    ];

    let tableRows = rows.map(r =>
        `<tr>
            <td style="color:${r.color};font-weight:600;">${r.label}</td>
            <td style="font-weight:bold;color:${r.color};">${v(r.field)}</td>
        </tr>`
    ).join('');

    return `<div class="popup-inner">
        <div class="popup-title">${name || '—'}</div>
        ${!key ? '<div class="popup-sub" style="color:#e67e22;"><i class="bi bi-info-circle"></i> Aucune donnée disponible</div>' : ''}
        <table class="popup-table">
            <thead><tr><th>Indicateur</th><th>Valeur</th></tr></thead>
            <tbody>${tableRows}</tbody>
        </table>
    </div>`;
}

// ── Rendu couche GeoJSON ──────────────────────────────────────────────
function renderMap() {
    if (!geojsonData) return;
    _breaks = computeBreaks(currentField);

    if (currentLayer) map.removeLayer(currentLayer);

    currentLayer = L.geoJSON(geojsonData, {
        style: styleFeature,
        onEachFeature: (feature, layer) => {
            layer.bindPopup(buildPopup(feature));
            layer.on('mouseover', function() {
                this.setStyle({ weight: 2.5, color: '#0a6cbc', fillOpacity: 0.9 });
                this.bringToFront();
            });
            layer.on('mouseout', function() {
                currentLayer.resetStyle(this);
            });
        },
    }).addTo(map);

    updateUI();
}

// ── Mise à jour header + onglets + légende ────────────────────────────
function updateUI() {
    const cfg = LAYERS_CFG[currentField];

    document.getElementById('map-card-title').textContent = cfg.title;
    const badge = document.getElementById('map-total-badge');
    badge.textContent  = 'Total : ' + (TOTALS[currentField] || 0);
    badge.style.cssText = cfg.badgeStyle
        + 'margin-left:8px;border-radius:20px;padding:2px 12px;font-size:.78rem;font-weight:700;';

    // Onglets
    Object.values(LAYERS_CFG).forEach(c => {
        const btn = document.getElementById(c.btnId);
        if (btn) btn.className = 'map-tab';
    });
    const activeBtn = document.getElementById(cfg.btnId);
    if (activeBtn) activeBtn.classList.add(cfg.activeClass);

    // Légende dynamique
    const colors = cfg.colors;
    const niveauLabels = ['Aucun cas', 'Très faible', 'Faible', 'Modéré', 'Élevé', 'Très élevé'];
    let html = `<div style="font-weight:700;margin-bottom:6px;color:var(--text-primary);">${cfg.label}</div>`;
    [0,1,2,3,4,5].forEach(n => {
        html += `<div><span class="legend-dot" style="background:${colors[n]};border:${n===0?'1px solid var(--border)':'none'};"></span> ${niveauLabels[n]}</div>`;
    });
    document.getElementById('dyn-legend').innerHTML = html;
}

// ── Changer de couche ─────────────────────────────────────────────────
function switchLayer(field) {
    if (!geojsonData) return;
    currentField = field;
    renderMap();
}

// ── Contrôles hauteur (identiques à analyse.php) ─────────────────────
function setMapHeight(height) {
    document.getElementById('map').style.height = height + 'px';
    setTimeout(() => map.invalidateSize(), 200);
}
function toggleFullscreenMap() {
    const mapDiv = document.getElementById('map');
    if (mapDiv.style.height !== '90vh') {
        mapDiv.dataset.oldHeight = mapDiv.style.height || '520px';
        mapDiv.style.height = '90vh';
    } else {
        mapDiv.style.height = mapDiv.dataset.oldHeight || '520px';
    }
    setTimeout(() => map.invalidateSize(), 200);
}
function resetMap() {
    map.setView([-18.879, 47.508], 6);
}

// ── Chargement Districts3.geojson ─────────────────────────────────────
fetch('Districts3.geojson')
    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(data => {
        geojsonData = data;
        renderMap();
        // fitBounds automatique sur Madagascar
        try { map.fitBounds(currentLayer.getBounds(), { padding: [20, 20] }); } catch(e) {}
    })
    .catch(() => {
        document.getElementById('map').innerHTML =
            '<div style="display:flex;align-items:center;justify-content:center;height:100%;'
            + 'flex-direction:column;gap:12px;color:var(--text-muted);">'
            + '<i class="bi bi-map" style="font-size:3rem;opacity:0.3;"></i>'
            + '<p style="font-size:0.85rem;text-align:center;">'
            + 'Fichier <code>Districts3.geojson</code> introuvable.<br>'
            + 'Placez-le à la racine du projet.</p></div>';
    });

// ── Sync tuiles avec changement de thème (identique à analyse.php) ────
const themeBtn = document.getElementById('dvThemeBtn');
if (themeBtn) {
    themeBtn.addEventListener('click', () => {
        setTimeout(() => {
            map.removeLayer(tileLayer);
            tileLayer = L.tileLayer(getTileUrl(), {
                attribution: '&copy; OSM &copy; CARTO', maxZoom: 10
            }).addTo(map);
            renderMap();
        }, 150);
    });
}
</script>

<?php include 'footer_shared.php'; ?>
