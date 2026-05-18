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

// ═══════════════════════════════════════════════════════════════════
//  CONFIG & DONNÉES
// ═══════════════════════════════════════════════════════════════════
define('SHEET_ID',    '1VdqjKcr00GmRI-FuOopXhRwYolvkUJmtp3DSV9ow4CE');
define('SHEET_RANGE', 'BDD 28042026!A1:BF5000');
define('SA_KEY_FILE', __DIR__ . '/config/google_service_account.json');
define('CACHE_FILE',  __DIR__ . '/cache/mpox_data.json');
define('CACHE_TTL',   300);

function curlGet(string $url, array $headers = []): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,
        CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>false,
        CURLOPT_HTTPHEADER=>$headers,CURLOPT_FOLLOWLOCATION=>true]);
    $resp = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    if ($err) throw new Exception("cURL GET : $err");
    return $resp ?: '';
}
function curlPost(string $url, string $body, array $headers = []): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,
        CURLOPT_TIMEOUT=>20,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>false,
        CURLOPT_HTTPHEADER=>$headers]);
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
        $url = 'https://sheets.googleapis.com/v4/spreadsheets/'.SHEET_ID.'/values/'.rawurlencode(SHEET_RANGE)
             . '?majorDimension=ROWS&valueRenderOption=UNFORMATTED_VALUE';
        $resp = curlGet($url, ["Authorization: Bearer $token"]);
        $data = json_decode($resp, true);
        if (isset($data['error'])) throw new Exception("Sheets API : ".$data['error']['message']);
        $rows = $data['values'] ?? [];
        if (!is_dir(dirname(CACHE_FILE))) mkdir(dirname(CACHE_FILE), 0755, true);
        file_put_contents(CACHE_FILE, json_encode($rows));
        return $rows;
    } catch (Exception $e) {
        if (file_exists(CACHE_FILE)) return json_decode(file_get_contents(CACHE_FILE), true) ?? [];
        return [];
    }
}

$rawRows  = fetchSheetData();
$lastSync = file_exists(CACHE_FILE) ? date('d/m/Y H:i:s', filemtime(CACHE_FILE)) : 'Jamais';
$headers  = []; $records = [];
if (!empty($rawRows)) {
    $headers = array_shift($rawRows);
    foreach ($rawRows as $row) {
        if (empty(array_filter($row))) continue;
        while (count($row) < count($headers)) $row[] = '';
        $records[] = array_combine($headers, array_slice($row, 0, count($headers)));
    }
}

$COL = [
    'date_notif' => 'Date de notification',
    'district'   => 'District',
    'conclusion' => 'Conclusion 1',
];

$today      = date('Y-m-d');
$todayTs    = strtotime($today);
$week4Start = strtotime('-28 days', $todayTs);
$districtData = [];

foreach ($records as $r) {
    $district = trim($r[$COL['district']] ?? 'Inconnu');
    if (!$district || $district === 'Inconnu') continue;
    $dateStr   = trim($r[$COL['date_notif']] ?? '');
    $cas       = mb_strtolower(trim($r[$COL['conclusion']] ?? ''), 'UTF-8');
    $isConfirm = str_contains($cas, 'confirm');
    $ts        = $dateStr ? strtotime($dateStr) : null;
    $dateFmt   = $ts ? date('Y-m-d', $ts) : null;
    if (!isset($districtData[$district]))
        $districtData[$district] = ['notif_today'=>0,'confirm_today'=>0,'notif_cumul'=>0,'confirm_4w'=>0];
    if ($dateFmt === $today) {
        $districtData[$district]['notif_today']++;
        if ($isConfirm) $districtData[$district]['confirm_today']++;
    }
    $districtData[$district]['notif_cumul']++;
    if ($ts && $ts >= $week4Start && $isConfirm) $districtData[$district]['confirm_4w']++;
}

$totalNotifToday   = array_sum(array_column($districtData, 'notif_today'));
$totalConfirmToday = array_sum(array_column($districtData, 'confirm_today'));
$totalNotifCumul   = array_sum(array_column($districtData, 'notif_cumul'));
$totalConfirm4w    = array_sum(array_column($districtData, 'confirm_4w'));

// Top 5 cumulés
$top5cumul = $districtData;
arsort($top5cumul);
$top5cumul = array_slice($top5cumul, 0, 5, true);

// Top 5 confirmés 4 semaines
$top5conf4w = $districtData;
uasort($top5conf4w, fn($a,$b) => $b['confirm_4w'] <=> $a['confirm_4w']);
$top5conf4w = array_slice($top5conf4w, 0, 5, true);
?>

<!-- ══ Styles spécifiques cartographie ════════════════════════════ -->
<style>
/* ── SVG map ───────────────────────────────────────── */
#map-svg-wrap {
    position: relative;
    overflow: hidden;
    background: var(--bg-base, #f8faff);
    border-radius: 0 0 0 0;
}
#svg-map {
    width: 100%;
    height: 540px;
    min-height: 300px;
    max-height: 90vh;
    resize: vertical;
    overflow: auto;
    display: block;
    transition: height 0.25s ease;
}
.district {
    cursor: pointer;
    transition: filter .12s;
}
.district:hover { filter: brightness(0.82); }

/* ── Onglets couches ───────────────────────────────── */
.map-nav { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px; }
.map-tab {
    display:flex; align-items:center; gap:8px; padding:9px 16px;
    border-radius:10px; border:1.5px solid var(--border);
    background:var(--bg-card); cursor:pointer; font-size:.81rem; font-weight:700;
    color:var(--text-secondary); transition:all .18s; line-height:1.3;
    font-family: 'Syne', sans-serif;
}
.map-tab:hover { border-color:var(--accent); color:var(--accent); background:var(--accent-soft); }
.map-tab .tab-dot { width:9px; height:9px; border-radius:50%; flex-shrink:0; }
.map-tab .tab-sub { font-size:.67rem; font-weight:400; opacity:.75; display:block; }
.map-tab.t-blue   { background:#2980b9; color:#fff; border-color:#2980b9; }
.map-tab.t-red    { background:#c0392b; color:#fff; border-color:#c0392b; }
.map-tab.t-orange { background:#e67e22; color:#fff; border-color:#e67e22; }
.map-tab.t-green  { background:#27ae60; color:#fff; border-color:#27ae60; }

/* ── Légende carte ─────────────────────────────────── */
.map-legend {
    background:var(--bg-card); border:1px solid var(--border);
    border-radius:10px; padding:12px 14px;
    font-size:.78rem; color:var(--text-secondary); line-height:2;
}
.legend-dot {
    display:inline-block; width:14px; height:14px;
    border-radius:3px; margin-right:6px; vertical-align:middle;
}

/* ── Tooltip ───────────────────────────────────────── */
.carto-tooltip {
    position:fixed; z-index:9999; pointer-events:none;
    background:var(--bg-card); border:1px solid var(--border);
    border-radius:12px; padding:13px 16px; min-width:210px;
    box-shadow:0 6px 24px rgba(0,0,0,.14); font-size:.82rem;
    color:var(--text-primary); display:none; line-height:1.5;
}
.carto-tooltip .tt-name {
    font-family:'Syne',sans-serif; font-weight:800; font-size:.93rem;
    margin-bottom:8px; color:var(--text-primary);
    border-bottom:1px solid var(--border); padding-bottom:6px;
}
.tt-row { display:flex; justify-content:space-between; align-items:center;
    padding:3px 0; font-size:.8rem; }
.tt-val { font-weight:800; }

/* ── Overlay loading ───────────────────────────────── */
#map-loading {
    position:absolute; top:0; left:0; right:0; bottom:0;
    display:flex; align-items:center; justify-content:center;
    flex-direction:column; gap:12px;
    background:var(--bg-base, #f8faff); z-index:20;
}
.spin-ring {
    width:34px; height:34px; border:3px solid var(--border);
    border-top-color:var(--accent,#0a6cbc); border-radius:50%;
    animation:spin .7s linear infinite;
}
@keyframes spin { to { transform:rotate(360deg); } }

/* ── KPI stats grid ────────────────────────────────── */
.kpi-4 { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:16px; }
@media(max-width:720px) { .kpi-4 { grid-template-columns:1fr 1fr; } }

/* ── Top districts ─────────────────────────────────── */
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
.bar-bg { flex:1; background:var(--border); border-radius:3px; height:5px; }
.bar-fill { height:5px; border-radius:3px; }

/* ── Sync dot ──────────────────────────────────────── */
.pulse-dot {
    display:inline-block; width:8px; height:8px;
    border-radius:50%; background:#27ae60;
    animation:pdot 2s infinite; margin-right:6px;
}
@keyframes pdot { 0%,100%{opacity:1} 50%{opacity:.3} }

/* ── Contrôles carte ───────────────────────────────── */
.map-size-controls { display:flex; gap:6px; flex-wrap:wrap; }
</style>

<div class="dv-content dv-animate">

    <!-- ══ En-tête ══════════════════════════════════════════ -->
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

    <!-- ══ Barre sync ════════════════════════════════════════ -->
    <div class="dv-card dv-animate dv-animate-d1"
         style="padding:10px 18px;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
        <div style="font-size:.82rem;color:var(--text-secondary);display:flex;align-items:center;">
            <span class="pulse-dot"></span>
            Mise à jour : <strong style="color:var(--text-primary);margin:0 4px;"><?= htmlspecialchars($lastSync) ?></strong>
            · <?= number_format(count($records)) ?> enregistrements
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
            <div class="dv-stat-sub"><?= date('d/m/Y') ?></div>
        </div>
        <div class="dv-stat" style="border-left:3px solid #c0392b;">
            <div class="dv-stat-label">Confirmés aujourd'hui</div>
            <div class="dv-stat-value" style="color:#c0392b;"><?= $totalConfirmToday ?></div>
            <div class="dv-stat-sub"><?= date('d/m/Y') ?></div>
        </div>
        <div class="dv-stat" style="border-left:3px solid #e67e22;">
            <div class="dv-stat-label">Notifiés cumulés</div>
            <div class="dv-stat-value" style="color:#e67e22;"><?= $totalNotifCumul ?></div>
            <div class="dv-stat-sub">Toute la période</div>
        </div>
        <div class="dv-stat" style="border-left:3px solid #27ae60;">
            <div class="dv-stat-label">Confirmés 4 semaines</div>
            <div class="dv-stat-value" style="color:#27ae60;"><?= $totalConfirm4w ?></div>
            <div class="dv-stat-sub"><?= date('d/m/Y', $week4Start) ?> → <?= date('d/m/Y') ?></div>
        </div>
    </div>

    <!-- ══ Carte choroplèthe ════════════════════════════════ -->
    <div class="dv-card dv-animate dv-animate-d2">

        <!-- Header card -->
        <div class="dv-card-header">
            <i class="bi bi-map"></i>
            <span id="map-card-title">Cas notifiés aujourd'hui — <?= date('d/m/Y') ?></span>
            <span class="dv-badge" id="map-total-badge"
                  style="margin-left:8px;background:#e8f4fd;color:#2980b9;">
                Total : <?= $totalNotifToday ?>
            </span>
            <div style="margin-left:auto;" class="map-size-controls">
                <button class="dv-btn dv-btn-outline" style="padding:4px 10px;font-size:.75rem;"
                        onclick="setMapHeight(380)">Petite</button>
                <button class="dv-btn dv-btn-outline" style="padding:4px 10px;font-size:.75rem;"
                        onclick="setMapHeight(540)">Moyenne</button>
                <button class="dv-btn dv-btn-outline" style="padding:4px 10px;font-size:.75rem;"
                        onclick="setMapHeight(720)">Grande</button>
                <button class="dv-btn dv-btn-outline" style="padding:4px 10px;font-size:.75rem;"
                        onclick="toggleFullscreen()">
                    <i class="bi bi-arrows-fullscreen"></i> Plein écran
                </button>
            </div>
        </div>

        <!-- Onglets sélection couche -->
        <div style="padding:14px 18px 0;">
            <div class="map-nav">
                <button class="map-tab t-blue" id="btn-notif-today"
                        onclick="switchMap('notif_today')">
                    <span class="tab-dot" style="background:#fff;opacity:.8;"></span>
                    <span>Notifiés aujourd'hui
                        <span class="tab-sub"><?= date('d/m/Y') ?></span>
                    </span>
                </button>
                <button class="map-tab" id="btn-confirm-today"
                        onclick="switchMap('confirm_today')">
                    <span class="tab-dot" style="background:#c0392b;"></span>
                    <span>Confirmés aujourd'hui
                        <span class="tab-sub"><?= date('d/m/Y') ?></span>
                    </span>
                </button>
                <button class="map-tab" id="btn-notif-cumul"
                        onclick="switchMap('notif_cumul')">
                    <span class="tab-dot" style="background:#e67e22;"></span>
                    <span>Notifiés cumulés
                        <span class="tab-sub">Toute la période</span>
                    </span>
                </button>
                <button class="map-tab" id="btn-confirm-4w"
                        onclick="switchMap('confirm_4w')">
                    <span class="tab-dot" style="background:#27ae60;"></span>
                    <span>Confirmés 4 semaines
                        <span class="tab-sub"><?= date('d/m/Y', $week4Start) ?> → <?= date('d/m/Y') ?></span>
                    </span>
                </button>
            </div>
        </div>

        <!-- SVG de la carte -->
        <div id="map-svg-wrap" style="margin:0 18px;">
            <div id="map-loading">
                <div class="spin-ring"></div>
                <span style="font-size:.82rem;color:var(--text-secondary);">Chargement de la carte…</span>
            </div>
            <svg id="svg-map"
                 viewBox="0 0 800 620"
                 preserveAspectRatio="xMidYMid meet"
                 xmlns="http://www.w3.org/2000/svg"></svg>
        </div>

        <!-- Légende -->
        <div style="display:flex;flex-wrap:wrap;gap:14px;padding:14px 18px 18px;">
            <div class="map-legend" id="dyn-legend" style="min-width:160px;">
                <!-- généré JS -->
            </div>
            <div class="map-legend" style="flex:1;min-width:200px;">
                <div style="font-weight:700;margin-bottom:4px;color:var(--text-primary);">
                    <i class="bi bi-info-circle"></i> Informations
                </div>
                <div style="font-size:.75rem;line-height:1.8;color:var(--text-muted);">
                    <strong>Source :</strong> Google Sheets DVSSER<br>
                    <strong>Fond de carte :</strong> district_cache.geojson (DISTRICT.shp)<br>
                    <strong>Confirmés :</strong> conclusion contenant « confirmé »<br>
                    <strong>4 semaines :</strong> <?= date('d/m/Y', $week4Start) ?> → <?= date('d/m/Y') ?>
                </div>
            </div>
        </div>

    </div><!-- /dv-card carte -->

    <!-- ══ Top districts ════════════════════════════════════ -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;"
         class="dv-animate dv-animate-d2">

        <!-- Top 5 cumulés -->
        <div class="dv-card">
            <div class="dv-card-header">
                <i class="bi bi-trophy"></i> Top 5 — Cas notifiés cumulés
                <span class="dv-badge" style="margin-left:auto;"><?= $totalNotifCumul ?> total</span>
            </div>
            <?php
            $colors5 = ['#e53e3e','#f59e0b','#2980b9','#27ae60','#7c3aed'];
            $maxC = max(array_column($top5cumul,'notif_cumul') ?: [1]);
            $i = 1;
            foreach ($top5cumul as $dist => $d):
                $pct = $maxC > 0 ? round($d['notif_cumul']/$maxC*100) : 0;
                $col = $colors5[$i-1] ?? '#aaa';
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
            $maxC4 = max(array_column($top5conf4w,'confirm_4w') ?: [1]);
            $i2 = 1;
            foreach ($top5conf4w as $dist => $d):
                $pct = $maxC4 > 0 ? round($d['confirm_4w']/$maxC4*100) : 0;
                $col = $colors5[$i2-1] ?? '#aaa';
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

    </div>

</div><!-- /.dv-content -->

<!-- ══ Scripts ══════════════════════════════════════════════════════ -->
<script>
// ═══════════════════════════════════════════════════
// DONNÉES PHP → JS
// ═══════════════════════════════════════════════════
const DISTRICT_DATA = <?= json_encode($districtData, JSON_UNESCAPED_UNICODE) ?>;
const TOTALS = {
    notif_today:   <?= $totalNotifToday ?>,
    confirm_today: <?= $totalConfirmToday ?>,
    notif_cumul:   <?= $totalNotifCumul ?>,
    confirm_4w:    <?= $totalConfirm4w ?>,
};

// ═══════════════════════════════════════════════════
// CONFIG 4 COUCHES
// ═══════════════════════════════════════════════════
const MAP_CFG = {
    notif_today: {
        title:       'Cas notifiés aujourd\'hui — <?= date('d/m/Y') ?>',
        label:       'Notifiés aujourd\'hui',
        activeClass: 't-blue',
        badgeStyle:  'background:#e8f4fd;color:#2980b9;',
        colors:      ['#EBF5FB','#AED6F1','#5DADE2','#2980B9','#1A5276'],
    },
    confirm_today: {
        title:       'Cas confirmés aujourd\'hui — <?= date('d/m/Y') ?>',
        label:       'Confirmés aujourd\'hui',
        activeClass: 't-red',
        badgeStyle:  'background:#fdecea;color:#c0392b;',
        colors:      ['#FDEDEC','#F5B7B1','#E74C3C','#C0392B','#7B241C'],
    },
    notif_cumul: {
        title:       'Cas notifiés cumulés — toute la période',
        label:       'Notifiés cumulés',
        activeClass: 't-orange',
        badgeStyle:  'background:#fef3e2;color:#e67e22;',
        colors:      ['#FEF9E7','#FAD7A0','#F5B041','#E67E22','#784212'],
    },
    confirm_4w: {
        title:       'Cas confirmés — 4 dernières semaines',
        label:       'Confirmés 4 sem.',
        activeClass: 't-green',
        badgeStyle:  'background:#eafaf1;color:#27ae60;',
        colors:      ['#EAFAF1','#A9DFBF','#52BE80','#27AE60','#145A32'],
    },
};

const BTN_IDS = {
    notif_today:   'btn-notif-today',
    confirm_today: 'btn-confirm-today',
    notif_cumul:   'btn-notif-cumul',
    confirm_4w:    'btn-confirm-4w',
};

// ═══════════════════════════════════════════════════
// SVG + PROJECTION MADAGASCAR
// ═══════════════════════════════════════════════════
const svg    = document.getElementById('svg-map');
const SVG_W  = 800;
const SVG_H  = 620;
const PAD    = 18; // marges internes

// Bounding box serrée de Madagascar (WGS84)
// On découpe précisément pour ne montrer QUE l'île
const MDG = { minLon:43.15, maxLon:50.55, minLat:-25.65, maxLat:-11.85 };

function project(lon, lat) {
    // Garde uniquement les coordonnées dans la bbox Madagascar
    const uw = SVG_W - PAD * 2;
    const uh = SVG_H - PAD * 2;
    const x = PAD + ((lon - MDG.minLon) / (MDG.maxLon - MDG.minLon)) * uw;
    const y = PAD + ((MDG.maxLat - lat) / (MDG.maxLat - MDG.minLat)) * uh;
    return [x, y];
}

// Filtre : ignorer les polygones entièrement hors de Madagascar
function isInMdg(coords) {
    for (const [lon, lat] of coords) {
        if (lon >= MDG.minLon && lon <= MDG.maxLon && lat >= MDG.minLat && lat <= MDG.maxLat)
            return true;
    }
    return false;
}

function flatCoords(geom) {
    const pts = [];
    if (geom.type === 'Polygon')
        geom.coordinates.forEach(r => r.forEach(c => pts.push(c)));
    if (geom.type === 'MultiPolygon')
        geom.coordinates.forEach(p => p.forEach(r => r.forEach(c => pts.push(c))));
    return pts;
}

// ═══════════════════════════════════════════════════
// CORRESPONDANCE NOMS DISTRICTS
// ═══════════════════════════════════════════════════
function normStr(s) {
    return (s||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim();
}
function getDistrictName(feature) {
    const p = feature.properties;
    return p.NOM_DISTRI || p.NOM_DIST || p.NOMDIST || p.District || p.district
        || p.NAME_2 || p.NAME_3 || p.NAME || p.NOM || p.nom || p.name || '';
}
function findDistrictKey(geoName) {
    if (!geoName) return null;
    const ng = normStr(geoName);
    for (const k of Object.keys(DISTRICT_DATA))
        if (normStr(k) === ng) return k;
    for (const k of Object.keys(DISTRICT_DATA))
        if (normStr(k).includes(ng) || ng.includes(normStr(k))) return k;
    return null;
}

// ═══════════════════════════════════════════════════
// SEUILS + COULEURS
// ═══════════════════════════════════════════════════
function computeBreaks(field, n) {
    const vals = Object.values(DISTRICT_DATA).map(d=>d[field]||0).filter(v=>v>0).sort((a,b)=>a-b);
    if (!vals.length) return [0,1,2,3,4];
    const max = vals[vals.length-1];
    return Array.from({length:n}, (_,i) => Math.round((max/n)*i));
}
function getColor(value, breaks, colors) {
    if (!value || value === 0) return null;
    for (let i = breaks.length-1; i >= 0; i--)
        if (value >= breaks[i]) return colors[Math.min(i, colors.length-1)];
    return colors[0];
}
function noDataFill() {
    return document.documentElement.getAttribute('data-theme') === 'dark' ? '#2d3748' : '#e9ecef';
}
function strokeCol() {
    return document.documentElement.getAttribute('data-theme') === 'dark' ? '#4a5568' : '#cbd5e0';
}

// ═══════════════════════════════════════════════════
// RENDU SVG
// ═══════════════════════════════════════════════════
let curField  = 'notif_today';
let GEOJSON   = null;
let pendingField = null;

function buildPath(geom) {
    let d = '';
    function drawRing(ring) {
        ring.forEach((c, i) => {
            const [x, y] = project(c[0], c[1]);
            d += (i === 0 ? `M${x.toFixed(1)} ${y.toFixed(1)}` : ` L${x.toFixed(1)} ${y.toFixed(1)}`);
        });
        d += ' Z ';
    }
    if (geom.type === 'Polygon') geom.coordinates.forEach(drawRing);
    if (geom.type === 'MultiPolygon') geom.coordinates.forEach(poly => poly.forEach(drawRing));
    return d;
}

function renderMap(field) {
    if (!GEOJSON) return;
    curField = field;
    svg.innerHTML = '';

    const cfg    = MAP_CFG[field];
    const colors = cfg.colors;
    const breaks = computeBreaks(field, 5);
    const noData = noDataFill();
    const stroke = strokeCol();

    GEOJSON.features.forEach(feature => {
        // Filtrer les géométries hors Madagascar
        const pts = flatCoords(feature.geometry);
        if (!isInMdg(pts)) return;

        const name  = getDistrictName(feature);
        const key   = findDistrictKey(name);
        const value = key ? (DISTRICT_DATA[key][field] || 0) : 0;
        const fill  = getColor(value, breaks, colors) || noData;

        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', buildPath(feature.geometry));
        path.setAttribute('fill', fill);
        path.setAttribute('stroke', stroke);
        path.setAttribute('stroke-width', '0.55');
        path.classList.add('district');

        // Interactions
        path.addEventListener('mouseenter', () => {
            path.setAttribute('stroke', 'var(--accent,#0a6cbc)');
            path.setAttribute('stroke-width', '1.6');
        });
        path.addEventListener('mouseleave', () => {
            path.setAttribute('stroke', stroke);
            path.setAttribute('stroke-width', '0.55');
        });
        path.addEventListener('click', e => {
            showTooltip(e.clientX, e.clientY, name || '—', key ? DISTRICT_DATA[key] : null);
        });

        svg.appendChild(path);
    });

    updateUI(field, breaks, colors);
}

// ═══════════════════════════════════════════════════
// MISE À JOUR UI
// ═══════════════════════════════════════════════════
function updateUI(field, breaks, colors) {
    const cfg = MAP_CFG[field];

    // Titre + badge header
    document.getElementById('map-card-title').textContent = cfg.title;
    const badge = document.getElementById('map-total-badge');
    badge.textContent  = 'Total : ' + (TOTALS[field] || 0);
    badge.style.cssText = cfg.badgeStyle
        + 'margin-left:8px;border-radius:20px;padding:2px 12px;font-size:.78rem;font-weight:700;';

    // Onglets
    Object.keys(BTN_IDS).forEach(f => {
        const btn = document.getElementById(BTN_IDS[f]);
        if (!btn) return;
        // Retirer toutes les classes actives
        btn.className = 'map-tab';
        if (f === field) btn.classList.add(MAP_CFG[f].activeClass);
    });

    // Légende dynamique
    const noData = noDataFill();
    let html = `<div style="font-weight:700;margin-bottom:4px;color:var(--text-primary);">${cfg.label}</div>`;
    html += `<div><span class="legend-dot" style="background:${noData};border:1px solid var(--border);"></span> Aucun cas</div>`;
    const labels = [
        '1',
        (breaks[1]||1) + (breaks[2] ? ' – '+(breaks[2]-1) : '+'),
        breaks[2] + (breaks[3] ? ' – '+(breaks[3]-1) : '+'),
        breaks[3] + (breaks[4] ? ' – '+(breaks[4]-1) : '+'),
        '≥ ' + (breaks[4]||5),
    ];
    colors.forEach((col, i) => {
        html += `<div><span class="legend-dot" style="background:${col};"></span> ${labels[i]}</div>`;
    });
    document.getElementById('dyn-legend').innerHTML = html;
}

// ═══════════════════════════════════════════════════
// TOOLTIP
// ═══════════════════════════════════════════════════
let ttEl = null;

function showTooltip(cx, cy, name, d) {
    if (!ttEl) {
        ttEl = document.createElement('div');
        ttEl.className = 'carto-tooltip';
        document.body.appendChild(ttEl);
    }
    ttEl.innerHTML = `
        <div class="tt-name"><i class="bi bi-geo-alt"></i> ${name}</div>
        <div class="tt-row">
            <span><span style="color:#2980b9;">▌</span> Notifiés aujourd'hui</span>
            <span class="tt-val" style="color:#2980b9;">${d ? d.notif_today : 0}</span>
        </div>
        <div class="tt-row">
            <span><span style="color:#c0392b;">▌</span> Confirmés aujourd'hui</span>
            <span class="tt-val" style="color:#c0392b;">${d ? d.confirm_today : 0}</span>
        </div>
        <div class="tt-row">
            <span><span style="color:#e67e22;">▌</span> Notifiés cumulés</span>
            <span class="tt-val" style="color:#e67e22;">${d ? d.notif_cumul : 0}</span>
        </div>
        <div class="tt-row">
            <span><span style="color:#27ae60;">▌</span> Confirmés 4 semaines</span>
            <span class="tt-val" style="color:#27ae60;">${d ? d.confirm_4w : 0}</span>
        </div>`;
    ttEl.style.display = 'block';
    const tw = ttEl.offsetWidth || 220, th = ttEl.offsetHeight || 160;
    const vw = window.innerWidth,    vh = window.innerHeight;
    ttEl.style.left = (cx + 16 + tw > vw ? cx - tw - 16 : cx + 16) + 'px';
    ttEl.style.top  = (cy + 16 + th > vh ? cy - th - 16 : cy + 16) + 'px';
}
document.addEventListener('click', e => {
    if (!e.target.classList.contains('district') && ttEl) ttEl.style.display = 'none';
});

// ═══════════════════════════════════════════════════
// CONTRÔLES TAILLE
// ═══════════════════════════════════════════════════
function setMapHeight(h) { svg.style.height = h + 'px'; }
function toggleFullscreen() {
    if (svg.style.height !== '90vh') { svg._oh = svg.style.height||'540px'; svg.style.height='90vh'; }
    else svg.style.height = svg._oh || '540px';
}

// ═══════════════════════════════════════════════════
// SWITCH CARTE (sans alert)
// ═══════════════════════════════════════════════════
function switchMap(field) {
    if (!GEOJSON) { pendingField = field; return; }
    renderMap(field);
}

// ═══════════════════════════════════════════════════
// CHARGEMENT GEOJSON
// ═══════════════════════════════════════════════════
const loadingEl = document.getElementById('map-loading');

fetch('district_cache.geojson')
    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(data => {
        GEOJSON = data;
        loadingEl.remove();
        renderMap(pendingField || 'notif_today');
    })
    .catch(err => {
        loadingEl.innerHTML = `
            <i class="bi bi-exclamation-triangle" style="font-size:2.2rem;color:#e67e22;"></i>
            <div style="font-size:.85rem;color:var(--text-secondary);text-align:center;max-width:360px;">
                <strong style="color:var(--text-primary);">Fichier GeoJSON introuvable</strong><br>
                Exécutez <a href="shp2geojson.php" style="color:var(--accent);">shp2geojson.php</a>
                pour générer <code>district_cache.geojson</code> à partir de DISTRICT.shp<br>
                <small style="color:var(--text-muted);">${err.message}</small>
            </div>
            <a href="shp2geojson.php" class="dv-btn dv-btn-primary" style="font-size:.82rem;margin-top:6px;">
                <i class="bi bi-tools"></i> Convertir DISTRICT.shp
            </a>`;
    });

// Sync dark mode → re-render couleurs
const dvThemeBtn = document.getElementById('dvThemeBtn');
if (dvThemeBtn) dvThemeBtn.addEventListener('click', () => {
    setTimeout(() => { if (GEOJSON) renderMap(curField); }, 160);
});
</script>

<?php include 'footer_shared.php'; ?>