<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: Login.php");
    exit();
}

// ── Vérification des droits ────────────────────────────────────────────────
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

// ════════════════════════════════════════════════════════════════════════════
//  CONFIGURATION GOOGLE SHEETS API v4 — SERVICE ACCOUNT
// ════════════════════════════════════════════════════════════════════════════
define('SHEET_ID',     '1VdqjKcr00GmRI-FuOopXhRwYolvkUJmtp3DSV9ow4CE');
define('SHEET_GID',    '1801487203');
define('SHEET_RANGE',  'BDD 28042026!A1:BF5000'); // ← nom exact de l'onglet
define('SA_KEY_FILE',  __DIR__ . '/config/google_service_account.json');
define('CACHE_FILE',   __DIR__ . '/cache/mpox_data.json');
define('CACHE_TTL',    300); // 5 minutes

// ── Helper cURL — compatible InfinityFree (file_get_contents HTTP bloqué) ─
function curlGet(string $url, array $headers = []): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,   // InfinityFree : pas de bundle CA
        CURLOPT_SSL_VERIFYHOST => false,
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
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err) throw new Exception("cURL POST : $err");
    return $resp ?: '';
}

// ── Fonction : obtenir un token OAuth2 via Service Account (JWT RS256) ────
function getGoogleToken(): string {
    if (!file_exists(SA_KEY_FILE)) {
        throw new Exception("Fichier Service Account introuvable : " . SA_KEY_FILE);
    }
    $key = json_decode(file_get_contents(SA_KEY_FILE), true);
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

    // Construire le JWT RS256
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

// ── Fonction : récupérer les données depuis Google Sheets via cURL ─────────
function fetchSheetData(): array {
    // Servir depuis le cache si encore valide
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

        // Créer le dossier cache si nécessaire
        if (!is_dir(dirname(CACHE_FILE))) {
            mkdir(dirname(CACHE_FILE), 0755, true);
        }
        file_put_contents(CACHE_FILE, json_encode($rows));
        return $rows;

    } catch (Exception $e) {
        // Fallback sur le cache expiré plutôt qu'une page blanche
        if (file_exists(CACHE_FILE)) {
            return json_decode(file_get_contents(CACHE_FILE), true) ?? [];
        }
        // Stocker l'erreur pour l'afficher dans la vue
        file_put_contents(
            dirname(CACHE_FILE) . '/mpox_last_error.txt',
            date('Y-m-d H:i:s') . ' — ' . $e->getMessage()
        );
        return [];
    }
}

// ── Chargement et traitement des données ─────────────────────────────────
$rawRows  = fetchSheetData();
$lastSync = file_exists(CACHE_FILE) ? date('d/m/Y H:i:s', filemtime(CACHE_FILE)) : 'Jamais';
$headers  = [];
$records  = [];

if (!empty($rawRows)) {
    $headers = array_shift($rawRows); // ligne 1 = en-têtes
    foreach ($rawRows as $row) {
        if (empty(array_filter($row))) continue;
        // Aligner chaque ligne sur les en-têtes
        while (count($row) < count($headers)) $row[] = '';
        $records[] = array_combine($headers, array_slice($row, 0, count($headers)));
    }
}

// ── Index des colonnes importantes ────────────────────────────────────────
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

// ── Calculs statistiques ───────────────────────────────────────────────────
$total       = count($records);
$confirmes   = 0; $suspects = 0; $probables = 0; $nonCas = 0;
$deces       = 0; $gueris = 0; $actifs = 0;
$hommes      = 0; $femmes = 0;
$hospitalises= 0;
$preleves    = 0;

$bySE        = [];   // semaine épidémique
$byRegion    = [];
$byDistrict  = [];
$byTranche   = [];
$bySexeEvo   = ['Masculin'=>['Guéri'=>0,'Décédé'=>0,'Actif'=>0],
                 'Féminin' =>['Guéri'=>0,'Décédé'=>0,'Actif'=>0]];
$byMonth     = [];   // AAAA-MM
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
	 
   
    // Classification
    if (str_contains($classif,'pos')) {$confirmes++;}
        elseif (str_contains($classif,'suspect')) {$suspects++;}
    	elseif (str_contains($classif,'pro')) {$probables++;}
    	elseif (str_contains($classif,'neg')) {$probables++;}
    // Évolution
    $evolLow = strtolower($evol);
    if (str_contains($evolLow, 'déc') || str_contains($evolLow, 'dec'))  $deces++;
    elseif (str_contains($evolLow, 'gué') || str_contains($evolLow, 'gue')) $gueris++;
    else $actifs++;

    // Sexe
    if (str_contains(strtolower($sexe), 'masc') || $sexe==='M') $hommes++;
    elseif (str_contains(strtolower($sexe), 'fém') || str_contains(strtolower($sexe),'fem') || $sexe==='F') $femmes++;

    // Hospitalisation
    if (strtolower($r[$COL['hospitalise']] ?? '') === 'oui') $hospitalises++;

    // Prélèvements
    if (strtolower($r[$COL['prelevement']] ?? '') === 'oui') $preleves++;

    // Semaine épidémique
    if ($se !== '') {
        $bySE[$se] = ($bySE[$se] ?? 0) + 1;
    }

    // Par mois (date notification)
    $dateStr = trim($r[$COL['date_notif']] ?? '');
    if ($dateStr) {
        $ts = strtotime($dateStr);
        if ($ts) {
            $mois = date('Y-m', $ts);
            if (!isset($byMonth[$mois])) $byMonth[$mois] = ['cas'=>0,'deces'=>0,'gueris'=>0];
            $byMonth[$mois]['cas']++;
            if (str_contains($evolLow,'déc')||str_contains($evolLow,'dec')) $byMonth[$mois]['deces']++;
            if (str_contains($evolLow,'gué')||str_contains($evolLow,'gue')) $byMonth[$mois]['gueris']++;
        }
    }

    // Par région
    if ($region) {
        if (!isset($byRegion[$region])) $byRegion[$region]=['total'=>0,'confirmes'=>0,'deces'=>0,'gueris'=>0,'H'=>0,'F'=>0];
        $byRegion[$region]['total']++;
        if (str_contains($cas,'confirm')) $byRegion[$region]['confirmes']++;
        if (str_contains($evolLow,'déc')||str_contains($evolLow,'dec'))    $byRegion[$region]['deces']++;
        if (str_contains($evolLow,'gué')||str_contains($evolLow,'gue'))    $byRegion[$region]['gueris']++;
        if (str_contains(strtolower($sexe),'masc')||$sexe==='M') $byRegion[$region]['H']++;
        else $byRegion[$region]['F']++;
    }

    // Par district
    if ($district) {
        if (!isset($byDistrict[$district])) $byDistrict[$district]=['total'=>0,'confirmes'=>0,'deces'=>0,'H'=>0,'F'=>0];
        $byDistrict[$district]['total']++;
        if (str_contains($cas,'confirm')) $byDistrict[$district]['confirmes']++;
        if (str_contains($evolLow,'déc')||str_contains($evolLow,'dec'))    $byDistrict[$district]['deces']++;
        if (str_contains(strtolower($sexe),'masc')||$sexe==='M') $byDistrict[$district]['H']++;
        else $byDistrict[$district]['F']++;
    }

    // Tranche d'âge
    if ($tranche) $byTranche[$tranche] = ($byTranche[$tranche] ?? 0) + 1;

    // Sexe × Évolution
    $sexeKey = (str_contains(strtolower($sexe),'masc')||$sexe==='M') ? 'Masculin' : 'Féminin';
    if (str_contains($evolLow,'déc')||str_contains($evolLow,'dec'))
        $bySexeEvo[$sexeKey]['Décédé']++;
    elseif (str_contains($evolLow,'gué')||str_contains($evolLow,'gue'))
        $bySexeEvo[$sexeKey]['Guéri']++;
    else
        $bySexeEvo[$sexeKey]['Actif']++;

    // VIH
    $vihKey = $vih ?: 'NON RENSEIGNE';
    $byVIH[$vihKey] = ($byVIH[$vihKey] ?? 0) + 1;

    // Âges
    if (is_numeric($ageRaw)) $ages[] = (int)$ageRaw;
}

// Trier semaines épidémiques numériquement
ksort($bySE);
ksort($byMonth);
arsort($byDistrict);

$ageMoyen  = $ages ? round(array_sum($ages)/count($ages), 1) : 0;
$ageMedian = 0;
if ($ages) { sort($ages); $mid = count($ages)/2; $ageMedian = count($ages)%2 ? $ages[$mid] : ($ages[$mid-1]+$ages[$mid])/2; }
$cfr       = $total > 0 ? round($deces/$total*100,1) : 0;
$tauxHospi = $total > 0 ? round($hospitalises/$total*100,1) : 0;
$tauxGueri = $total > 0 ? round($gueris/$total*100,1) : 0;

// Top 10 districts
arsort($byDistrict);
$top10Districts = array_slice($byDistrict, 0, 10, true);

// JSON pour JS
$jsonSE       = json_encode(array_values($bySE));
$jsonSELabels = json_encode(array_keys($bySE));
$jsonMonth    = json_encode(array_values(array_map(fn($v)=>$v['cas'],    $byMonth)));
$jsonMonthD   = json_encode(array_values(array_map(fn($v)=>$v['deces'],  $byMonth)));
$jsonMonthG   = json_encode(array_values(array_map(fn($v)=>$v['gueris'], $byMonth)));
$jsonMonthL   = json_encode(array_keys($byMonth));
$jsonDistL    = json_encode(array_keys($top10Districts));
$jsonDistV    = json_encode(array_values(array_map(fn($v)=>$v['total'],    $top10Districts)));
$jsonDistH    = json_encode(array_values(array_map(fn($v)=>$v['H'],        $top10Districts)));
$jsonDistF    = json_encode(array_values(array_map(fn($v)=>$v['F'],        $top10Districts)));
$jsonTrL      = json_encode(array_keys($byTranche));
$jsonTrH      = json_encode(array_map(fn($k)=>$byTranche[$k]??0, array_keys($byTranche)));
$jsonTrF      = json_encode(array_fill(0, count($byTranche), 0)); // placeholder si non dispo par sexe
$jsonRegL     = json_encode(array_keys($byRegion));
$jsonRegV     = json_encode(array_values(array_map(fn($v)=>$v['total'],    $byRegion)));
$jsonRegC     = json_encode(array_values(array_map(fn($v)=>$v['confirmes'],$byRegion)));
$jsonRegD     = json_encode(array_values(array_map(fn($v)=>$v['deces'],    $byRegion)));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
/* ── Variables ───────────────────────────────────────────── */
:root{
  --mpox-red:#c0392b;--mpox-orange:#e67e22;--mpox-blue:#2980b9;
  --mpox-green:#27ae60;--mpox-dark:#1a2332;--mpox-gray:#6b7a8d;
  --card-bg:#fff;--border:#e2e8f0;--hover:#f7faff;
}
/* ── Layout ──────────────────────────────────────────────── */
.mpox-wrap{padding:0;}
.sync-bar{display:flex;align-items:center;justify-content:space-between;background:#fff;border:1px solid var(--border);border-radius:10px;padding:10px 18px;margin-bottom:20px;font-size:.82rem;color:var(--mpox-gray);}
.sync-bar .sync-status{display:flex;align-items:center;gap:8px;}
.sync-dot{width:8px;height:8px;border-radius:50%;background:var(--mpox-green);animation:pulse 2s infinite;}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
/* ── KPI Cards ───────────────────────────────────────────── */
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:22px;}
.kpi{background:var(--card-bg);border:1px solid var(--border);border-radius:12px;padding:16px;position:relative;overflow:hidden;}
.kpi::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;}
.kpi-red::before{background:var(--mpox-red);}
.kpi-orange::before{background:var(--mpox-orange);}
.kpi-blue::before{background:var(--mpox-blue);}
.kpi-green::before{background:var(--mpox-green);}
.kpi-dark::before{background:var(--mpox-dark);}
.kpi-label{font-size:.72rem;font-weight:700;color:var(--mpox-gray);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;}
.kpi-value{font-size:2rem;font-weight:800;color:var(--mpox-dark);line-height:1;}
.kpi-sub{font-size:.75rem;color:var(--mpox-gray);margin-top:5px;}
.kpi-icon{position:absolute;right:12px;top:12px;font-size:1.6rem;opacity:.15;}
/* ── Section grid ────────────────────────────────────────── */
.sec-grid{display:grid;gap:16px;margin-bottom:20px;}
.g2{grid-template-columns:1fr 1fr;}
.g3{grid-template-columns:1fr 1fr 1fr;}
.g12{grid-template-columns:2fr 1fr;}
.g21{grid-template-columns:1fr 2fr;}
/* ── Cards ───────────────────────────────────────────────── */
.mcard{background:var(--card-bg);border:1px solid var(--border);border-radius:12px;padding:18px;}
.mcard-title{font-size:.85rem;font-weight:700;color:var(--mpox-dark);margin-bottom:14px;display:flex;align-items:center;gap:8px;border-bottom:1px solid var(--border);padding-bottom:10px;}
.mcard-title i{color:var(--mpox-blue);}
/* ── Tables ──────────────────────────────────────────────── */
.mtbl{width:100%;border-collapse:collapse;font-size:.82rem;}
.mtbl th{background:#f1f5ff;color:var(--mpox-dark);font-weight:700;padding:8px 10px;text-align:left;border-bottom:2px solid var(--border);font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;}
.mtbl td{padding:7px 10px;border-bottom:1px solid var(--border);color:#334155;vertical-align:middle;}
.mtbl tbody tr:hover td{background:var(--hover);}
.mtbl tbody tr:last-child td{border-bottom:none;}
/* ── Badges ──────────────────────────────────────────────── */
.mb{display:inline-flex;padding:2px 8px;border-radius:12px;font-size:.72rem;font-weight:700;}
.mb-red{background:#fdecea;color:var(--mpox-red);}
.mb-orange{background:#fef3e2;color:var(--mpox-orange);}
.mb-blue{background:#e8f4fd;color:var(--mpox-blue);}
.mb-green{background:#eafaf1;color:var(--mpox-green);}
.mb-gray{background:#f1f5f9;color:var(--mpox-gray);}
/* ── Progress bar ────────────────────────────────────────── */
.prog{height:6px;background:#e2e8f0;border-radius:3px;overflow:hidden;margin-top:4px;}
.prog-fill{height:6px;border-radius:3px;}
/* ── Stat row ────────────────────────────────────────────── */
.srow{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--border);font-size:.83rem;}
.srow:last-child{border:none;}
/* ── Alerte ──────────────────────────────────────────────── */
.mpox-alert{background:#fdecea;border:1px solid #f5c6c6;border-radius:10px;padding:12px 18px;display:flex;align-items:center;gap:12px;margin-bottom:18px;font-size:.85rem;color:#7f1d1d;}
/* ── Responsive ──────────────────────────────────────────── */
@media(max-width:900px){.g2,.g3,.g12,.g21{grid-template-columns:1fr;}}
/* ── Spinner ─────────────────────────────────────────────── */
.spinner{display:inline-block;width:14px;height:14px;border:2px solid #ccc;border-top-color:var(--mpox-blue);border-radius:50%;animation:spin .7s linear infinite;}
@keyframes spin{to{transform:rotate(360deg)}}
/* ── Chart wrap ──────────────────────────────────────────── */
.chart-wrap{position:relative;width:100%;}
</style>
</head>

<div class="dv-content dv-animate mpox-wrap">

    <!-- En-tête ──────────────────────────────────────────────────────────── -->
    <div class="dv-page-header">
        <h1><i class="bi bi-virus" style="color:#c0392b;margin-right:8px;"></i>Tableau de bord épidémique — Mpox Madagascar</h1>
        <div class="dv-breadcrumb">
            <a href="index.php">Accueil</a>
            <i class="bi bi-chevron-right" style="font-size:.65rem;"></i>
            <span>Analyse Mpox</span>
        </div>
    </div>

    <!-- Boutons navigation vers la cartographie ─────────────────────────── -->
    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
        <a href="carto_mpox.php" class="dv-btn dv-btn-primary" style="display:flex;align-items:center;gap:8px;padding:10px 20px;border-radius:10px;font-weight:700;text-decoration:none;background:#2980b9;color:#fff;border:none;">
            <i class="bi bi-map"></i>
            Cartographie par District
        </a>
        <a href="carto_mpox.php?map=notif_today" class="dv-btn" style="display:flex;align-items:center;gap:8px;padding:10px 18px;border-radius:10px;font-weight:600;text-decoration:none;background:#e8f4fd;color:#2980b9;border:1px solid #aed6f1;">
            <i class="bi bi-calendar-day"></i>
            Notifiés aujourd'hui
        </a>
        <a href="carto_mpox.php?map=confirm_today" class="dv-btn" style="display:flex;align-items:center;gap:8px;padding:10px 18px;border-radius:10px;font-weight:600;text-decoration:none;background:#fdecea;color:#c0392b;border:1px solid #f5c6c6;">
            <i class="bi bi-virus"></i>
            Confirmés aujourd'hui
        </a>
        <a href="carto_mpox.php?map=notif_cumul" class="dv-btn" style="display:flex;align-items:center;gap:8px;padding:10px 18px;border-radius:10px;font-weight:600;text-decoration:none;background:#fef3e2;color:#e67e22;border:1px solid #fadaa7;">
            <i class="bi bi-layers"></i>
            Notifiés cumulés
        </a>
        <a href="carto_mpox.php?map=confirm_4w" class="dv-btn" style="display:flex;align-items:center;gap:8px;padding:10px 18px;border-radius:10px;font-weight:600;text-decoration:none;background:#eafaf1;color:#27ae60;border:1px solid #a9dfbf;">
            <i class="bi bi-calendar-range"></i>
            Confirmés 4 semaines
        </a>
    </div>

    <!-- Barre de synchronisation ─────────────────────────────────────────── -->
    <div class="sync-bar">
        <div class="sync-status">
            <div class="sync-dot" id="sync-dot"></div>
            <span>Synchronisé avec Google Sheets ·
                <strong>Dernière mise à jour :</strong> <?= htmlspecialchars($lastSync) ?>
            </span>
            <span id="next-sync" style="color:var(--mpox-blue);font-weight:600;"></span>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:.8rem;color:var(--mpox-gray);">
                <i class="bi bi-clock"></i> Actualisation auto. toutes les 5 min
            </span>
            <button onclick="forceSync()" class="dv-btn dv-btn-outline" style="padding:5px 14px;font-size:.8rem;">
                <i class="bi bi-arrow-clockwise" id="sync-icon"></i> Actualiser
            </button>
        </div>
    </div>

    <?php if ($total === 0):
        $lastErr = "";
        $errFile = __DIR__ . "/cache/mpox_last_error.txt";
        if (file_exists($errFile)) $lastErr = file_get_contents($errFile);
        $saOk = file_exists(SA_KEY_FILE);
    ?>
    <div class="mpox-alert" style="flex-direction:column;gap:10px;">
        <div style="display:flex;align-items:center;gap:12px;">
            <i class="bi bi-exclamation-triangle-fill" style="font-size:1.4rem;flex-shrink:0;"></i>
            <div><strong>Données non disponibles.</strong> <?php echo $saOk ? "Erreur de connexion à Google Sheets." : "Fichier Service Account manquant."; ?></div>
            <a href="setup_google_sheets.php" style="margin-left:auto;background:#c0392b;color:#fff;padding:7px 16px;border-radius:8px;font-size:.82rem;font-weight:700;text-decoration:none;white-space:nowrap;">⚙️ Configurer</a>
        </div>
        <div style="background:rgba(0,0,0,.08);border-radius:6px;padding:10px 14px;font-size:.8rem;font-family:monospace;">
            📁 Fichier SA : <strong><?php echo $saOk ? "✅ Présent" : "❌ Absent"; ?></strong> — <?php echo htmlspecialchars(SA_KEY_FILE); ?><br>
            📋 Plage : <strong><?php echo htmlspecialchars(SHEET_RANGE); ?></strong><br>
            <?php if ($lastErr): ?> ❌ Erreur : <span style="color:#ff9999;"><?php echo htmlspecialchars($lastErr); ?></span><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ════════════════════════════════════════════════════════════════════
         KPI CARDS — Indicateurs clés
    ════════════════════════════════════════════════════════════════════ -->
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

    <!-- ════════════════════════════════════════════════════════════════════
         COURBE ÉPIDÉMIQUE — Cas par semaine & par mois
    ════════════════════════════════════════════════════════════════════ -->
    <div class="mcard" style="margin-bottom:16px;">
        <div class="mcard-title">
            <i class="bi bi-graph-up-arrow"></i>
            Courbe épidémique — Évolution des cas dans le temps
            <span style="margin-left:auto;font-size:.75rem;color:var(--mpox-gray);font-weight:400;">
                <button onclick="toggleEpiView('se')"   id="btn-se"    class="dv-btn" style="padding:3px 10px;font-size:.75rem;margin-right:4px;">Par SE</button>
                <button onclick="toggleEpiView('mois')" id="btn-mois"  class="dv-btn dv-btn-primary" style="padding:3px 10px;font-size:.75rem;">Par mois</button>
            </span>
        </div>
        <div class="chart-wrap" style="height:240px;">
            <canvas id="chartEpi"></canvas>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════════════
         RÉPARTITION GÉOGRAPHIQUE
    ════════════════════════════════════════════════════════════════════ -->
    <div class="sec-grid g12" style="margin-bottom:16px;">

        <!-- Tableau par région ────────────────────────────── -->
        <div class="mcard">
            <div class="mcard-title"><i class="bi bi-map"></i> Répartition par Région</div>
            <div style="overflow-x:auto;max-height:300px;overflow-y:auto;">
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
                        // Trier par total décroissant
                        uasort($byRegion, fn($a,$b) => $b['total'] <=> $a['total']);
                        foreach ($byRegion as $reg => $d):
                            $cfr_r = $d['total'] ? round($d['deces']/$d['total']*100,1) : 0;
                            $pct   = $total      ? round($d['total']/$total*100,1) : 0;
                        ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;"><?= htmlspecialchars($reg) ?></div>
                                <div class="prog"><div class="prog-fill" style="width:<?= $pct ?>%;background:var(--mpox-blue);"></div></div>
                            </td>
                            <td style="text-align:center;font-weight:700;"><?= $d['total'] ?></td>
                            <td style="text-align:center;"><span class="mb mb-red"><?= $d['confirmes'] ?></span></td>
                            <td style="text-align:center;"><span class="mb mb-gray"><?= $d['deces'] ?></span></td>
                            <td style="text-align:center;color:<?= $cfr_r>5?'var(--mpox-red)':'inherit' ?>;font-weight:<?= $cfr_r>5?700:400 ?>;"><?= $cfr_r ?>%</td>
                            <td style="text-align:center;color:var(--mpox-blue);"><?= $d['H'] ?></td>
                            <td style="text-align:center;color:var(--mpox-orange);"><?= $d['F'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background:#f1f5ff;font-weight:700;">
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

        <!-- Graphique Top 10 districts ───────────────────── -->
        <div class="mcard">
            <div class="mcard-title"><i class="bi bi-bar-chart-line"></i> Top 10 Districts — Cas H/F</div>
            <div class="chart-wrap" style="height:280px;">
                <canvas id="chartDistrict"></canvas>
            </div>
        </div>
    </div>

    <!-- Graphique régions ────────────────────────────────────────────────── -->
    <div class="mcard" style="margin-bottom:16px;">
        <div class="mcard-title"><i class="bi bi-bar-chart"></i> Cas confirmés & décès par Région</div>
        <div class="chart-wrap" style="height:220px;">
            <canvas id="chartRegion"></canvas>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════════════
         DÉMOGRAPHIE — Sexe & Tranches d'âge
    ════════════════════════════════════════════════════════════════════ -->
    <div class="sec-grid g3" style="margin-bottom:16px;">

        <!-- Répartition sexe ─────────────────────────────── -->
        <div class="mcard">
            <div class="mcard-title"><i class="bi bi-gender-ambiguous"></i> Répartition par sexe</div>
            <div class="chart-wrap" style="height:180px;">
                <canvas id="chartSexe"></canvas>
            </div>
            <div style="margin-top:12px;">
                <div class="srow">
                    <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#2980b9;margin-right:6px;"></span>Masculin</span>
                    <span><strong><?= $hommes ?></strong> (<?= $total?round($hommes/$total*100,1):0 ?>%)</span>
                </div>
                <div class="srow">
                    <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#e67e22;margin-right:6px;"></span>Féminin</span>
                    <span><strong><?= $femmes ?></strong> (<?= $total?round($femmes/$total*100,1):0 ?>%)</span>
                </div>
                <div class="srow" style="color:var(--mpox-gray);font-size:.78rem;">
                    <span>Sex ratio H/F</span>
                    <span><?= $femmes?round($hommes/$femmes,2):'N/A' ?></span>
                </div>
            </div>
        </div>

        <!-- Tranches d'âge ───────────────────────────────── -->
        <div class="mcard">
            <div class="mcard-title"><i class="bi bi-people"></i> Tranches d'âge</div>
            <div class="chart-wrap" style="height:180px;">
                <canvas id="chartTranche"></canvas>
            </div>
            <div style="margin-top:8px;font-size:.78rem;color:var(--mpox-gray);text-align:center;">
                Âge moyen : <strong><?= $ageMoyen ?> ans</strong> · Médiane : <strong><?= $ageMedian ?> ans</strong>
            </div>
        </div>

        <!-- Évolution × Sexe ─────────────────────────────── -->
        <div class="mcard">
            <div class="mcard-title"><i class="bi bi-activity"></i> Évolution par sexe</div>
            <div class="chart-wrap" style="height:180px;">
                <canvas id="chartEvoSexe"></canvas>
            </div>
            <div style="margin-top:8px;">
                <div class="srow">
                    <span>CFR Masculin</span>
                    <span style="color:var(--mpox-red);font-weight:700;">
                        <?= $bySexeEvo['Masculin']['Décédé'] ?> décès
                    </span>
                </div>
                <div class="srow">
                    <span>CFR Féminin</span>
                    <span style="color:var(--mpox-orange);font-weight:700;">
                        <?= $bySexeEvo['Féminin']['Décédé'] ?> décès
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════════════
         STATUTS VIH & CLASSIF
    ════════════════════════════════════════════════════════════════════ -->
    <div class="sec-grid g2" style="margin-bottom:16px;">

        <!-- Statut VIH ───────────────────────────────────── -->
        <div class="mcard">
            <div class="mcard-title"><i class="bi bi-shield-exclamation"></i> Statut VIH des patients</div>
            <?php foreach ($byVIH as $vihStat => $cnt):
                $pct = $total ? round($cnt/$total*100,1) : 0;
                $col = str_contains(strtolower($vihStat),'confirm') ? 'var(--mpox-red)'
                     : (str_contains(strtolower($vihStat),'neg') ? 'var(--mpox-green)' : 'var(--mpox-gray)');
            ?>
            <div class="srow">
                <span style="font-weight:600;"><?= htmlspecialchars($vihStat) ?></span>
                <span>
                    <strong><?= $cnt ?></strong>
                    <span style="color:var(--mpox-gray);margin-left:6px;"><?= $pct ?>%</span>
                </span>
            </div>
            <div class="prog"><div class="prog-fill" style="width:<?= $pct ?>%;background:<?= $col ?>;"></div></div>
            <?php endforeach; ?>
        </div>

        <!-- Classification ───────────────────────────────── -->
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

    <!-- ════════════════════════════════════════════════════════════════════
         TABLE DISTRICTS complète
    ════════════════════════════════════════════════════════════════════ -->
    <div class="mcard" style="margin-bottom:16px;">
        <div class="mcard-title">
            <i class="bi bi-pin-map"></i> Tableau détaillé par District
            <span style="margin-left:auto">
                <input type="text" id="district-search" placeholder="🔍 Filtrer..." onkeyup="filterDistrictTable()"
                    style="padding:4px 10px;border:1px solid var(--border);border-radius:6px;font-size:.8rem;outline:none;">
            </span>
        </div>
        <div style="overflow-x:auto;max-height:350px;overflow-y:auto;">
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
                        <td style="color:var(--mpox-gray);font-weight:700;"><?= $rank++ ?></td>
                        <td style="font-weight:600;"><?= htmlspecialchars($dist) ?></td>
                        <td style="text-align:center;font-weight:700;"><?= $d['total'] ?></td>
                        <td style="text-align:center;"><span class="mb mb-red"><?= $d['confirmes'] ?></span></td>
                        <td style="text-align:center;"><?= $d['deces'] ?></td>
                        <td style="text-align:center;color:<?= $cfr_d>5?'var(--mpox-red)':'inherit' ?>;font-weight:<?= $cfr_d>5?700:400 ?>;"><?= $cfr_d ?>%</td>
                        <td style="text-align:center;color:var(--mpox-blue);"><?= $d['H'] ?></td>
                        <td style="text-align:center;color:var(--mpox-orange);"><?= $d['F'] ?></td>
                        <td style="text-align:center;">
                            <div style="display:flex;align-items:center;gap:6px;">
                                <div class="prog" style="flex:1"><div class="prog-fill" style="width:<?= min(100,$pct_d*3) ?>%;background:var(--mpox-blue);"></div></div>
                                <span style="font-size:.75rem;color:var(--mpox-gray);min-width:32px;"><?= $pct_d ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Footer sync ─────────────────────────────────────────────────────── -->
    <div style="text-align:center;font-size:.78rem;color:var(--mpox-gray);padding:10px 0 20px;">
        <i class="bi bi-google"></i> Données synchronisées depuis Google Sheets ·
        ID feuille : <code><?= SHEET_ID ?></code> ·
        Prochaine mise à jour automatique dans <span id="countdown-footer">5:00</span>
    </div>

</div><!-- /dv-content -->

<!-- ════════════════════════════════════════════════════════════════════════
     SCRIPTS — Chart.js & Sync
════════════════════════════════════════════════════════════════════════ -->
<script>
Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
Chart.defaults.font.size   = 11;
Chart.defaults.color       = '#6b7a8d';

const C = {
  red:    '#c0392b', redA:    'rgba(192,57,43,.15)',
  orange: '#e67e22', orangeA: 'rgba(230,126,34,.15)',
  blue:   '#2980b9', blueA:   'rgba(41,128,185,.18)',
  green:  '#27ae60', greenA:  'rgba(39,174,96,.15)',
  dark:   '#1a2332',
};

// ── Données PHP → JS ─────────────────────────────────────────────────────
const DATA = {
  se:       { labels: <?= $jsonSELabels ?>, values: <?= $jsonSE ?> },
  month:    { labels: <?= $jsonMonthL  ?>, cas: <?= $jsonMonth ?>, deces: <?= $jsonMonthD ?>, gueris: <?= $jsonMonthG ?> },
  district: { labels: <?= $jsonDistL   ?>, total: <?= $jsonDistV ?>, H: <?= $jsonDistH ?>, F: <?= $jsonDistF ?> },
  region:   { labels: <?= $jsonRegL    ?>, total: <?= $jsonRegV ?>, confirmes: <?= $jsonRegC ?>, deces: <?= $jsonRegD ?> },
  tranche:  { labels: <?= $jsonTrL     ?>, H: <?= $jsonTrH ?>, F: <?= $jsonTrF ?> },
  classif:  { labels: ['Confirmés','Suspects','Probables','Non cas'], values: [<?= $confirmes ?>,<?= $suspects ?>,<?= $probables ?>,<?= $nonCas ?>] },
  sexe:     { values: [<?= $hommes ?>, <?= $femmes ?>] },
  evoSexe:  {
    M: [<?= $bySexeEvo['Masculin']['Guéri'] ?>, <?= $bySexeEvo['Masculin']['Décédé'] ?>, <?= $bySexeEvo['Masculin']['Actif'] ?>],
    F: [<?= $bySexeEvo['Féminin']['Guéri']  ?>, <?= $bySexeEvo['Féminin']['Décédé']  ?>, <?= $bySexeEvo['Féminin']['Actif']  ?>],
  },
};

// ── Courbe épidémique ─────────────────────────────────────────────────────
let epiChart, epiMode = 'mois';

function buildEpiChart(mode) {
  const ctx = document.getElementById('chartEpi');
  if (epiChart) epiChart.destroy();
  const d = mode === 'se' ? {
    labels:   DATA.se.labels,
    datasets: [{label:'Cas notifiés',data:DATA.se.values,borderColor:C.blue,backgroundColor:C.blueA,fill:true,tension:.4,pointRadius:3}]
  } : {
    labels:   DATA.month.labels,
    datasets: [
      {label:'Cas',    data:DATA.month.cas,    borderColor:C.blue,   backgroundColor:C.blueA,   fill:true,tension:.4,pointRadius:3},
      {label:'Décès',  data:DATA.month.deces,  borderColor:C.red,    backgroundColor:C.redA,    fill:false,tension:.4,borderDash:[4,3],pointRadius:3},
      {label:'Guéris', data:DATA.month.gueris, borderColor:C.green,  backgroundColor:C.greenA,  fill:false,tension:.4,borderDash:[4,3],pointRadius:3},
    ]
  };
  epiChart = new Chart(ctx, {
    type:'line', data: d,
    options:{
      responsive:true, maintainAspectRatio:false,
      plugins:{legend:{position:'top'},tooltip:{mode:'index',intersect:false}},
      scales:{x:{grid:{color:'#f0f0f0'}},y:{beginAtZero:true,grid:{color:'#f0f0f0'}}},
    }
  });
}

function toggleEpiView(mode) {
  epiMode = mode;
  document.getElementById('btn-se').className   = mode==='se'   ? 'dv-btn dv-btn-primary' : 'dv-btn';
  document.getElementById('btn-mois').className  = mode==='mois' ? 'dv-btn dv-btn-primary' : 'dv-btn';
  buildEpiChart(mode);
}
buildEpiChart('mois');

// ── Top 10 Districts (H/F) ───────────────────────────────────────────────
new Chart(document.getElementById('chartDistrict'), {
  type:'bar',
  data:{
    labels: DATA.district.labels,
    datasets:[
      {label:'Masculin', data:DATA.district.H, backgroundColor:C.blue,   barPercentage:.7},
      {label:'Féminin',  data:DATA.district.F, backgroundColor:C.orange, barPercentage:.7},
    ]
  },
  options:{
    responsive:true,maintainAspectRatio:false,
    plugins:{legend:{position:'top'}},
    scales:{x:{stacked:false,ticks:{maxRotation:40,font:{size:9}}},y:{beginAtZero:true}},
  }
});

// ── Cas par région ───────────────────────────────────────────────────────
new Chart(document.getElementById('chartRegion'), {
  type:'bar',
  data:{
    labels: DATA.region.labels,
    datasets:[
      {label:'Total cas',   data:DATA.region.total,    backgroundColor:C.blueA,  borderColor:C.blue,  borderWidth:1.5},
      {label:'Confirmés',   data:DATA.region.confirmes,backgroundColor:C.redA,   borderColor:C.red,   borderWidth:1.5},
      {label:'Décès',       data:DATA.region.deces,    backgroundColor:C.orangeA,borderColor:C.orange,borderWidth:1.5},
    ]
  },
  options:{
    responsive:true,maintainAspectRatio:false,
    plugins:{legend:{position:'top'}},
    scales:{x:{ticks:{maxRotation:40,font:{size:9}}},y:{beginAtZero:true}},
  }
});

// ── Sexe (donut) ─────────────────────────────────────────────────────────
new Chart(document.getElementById('chartSexe'), {
  type:'doughnut',
  data:{
    labels:['Masculin','Féminin'],
    datasets:[{data:DATA.sexe.values,backgroundColor:[C.blue,C.orange],borderWidth:0,hoverOffset:6}]
  },
  options:{responsive:true,maintainAspectRatio:false,cutout:'62%',plugins:{legend:{display:false}}}
});

// ── Tranches d'âge ───────────────────────────────────────────────────────
new Chart(document.getElementById('chartTranche'), {
  type:'bar',
  data:{
    labels: DATA.tranche.labels,
    datasets:[{label:'Cas',data:DATA.tranche.H,backgroundColor:C.blueA,borderColor:C.blue,borderWidth:1.5}]
  },
  options:{
    responsive:true,maintainAspectRatio:false,
    plugins:{legend:{display:false}},
    scales:{x:{ticks:{font:{size:9}}},y:{beginAtZero:true}},
  }
});

// ── Évolution × Sexe ─────────────────────────────────────────────────────
new Chart(document.getElementById('chartEvoSexe'), {
  type:'bar',
  data:{
    labels:['Guéri','Décédé','Actif/Inconnu'],
    datasets:[
      {label:'Masculin',data:DATA.evoSexe.M,backgroundColor:C.blue},
      {label:'Féminin', data:DATA.evoSexe.F,backgroundColor:C.orange},
    ]
  },
  options:{
    responsive:true,maintainAspectRatio:false,
    plugins:{legend:{position:'top'}},
    scales:{x:{},y:{beginAtZero:true}},
  }
});

// ── Classification (donut) ───────────────────────────────────────────────
new Chart(document.getElementById('chartClassif'), {
  type:'doughnut',
  data:{
    labels: DATA.classif.labels,
    datasets:[{
      data:DATA.classif.values,
      backgroundColor:[C.red,C.orange,C.blue,'#95a5a6'],
      borderWidth:0,hoverOffset:6
    }]
  },
  options:{responsive:true,maintainAspectRatio:false,cutout:'55%',plugins:{legend:{position:'bottom',labels:{font:{size:10}}}}}
});

// ── Filtrer le tableau districts ─────────────────────────────────────────
function filterDistrictTable() {
  const q    = document.getElementById('district-search').value.toLowerCase();
  const rows = document.querySelectorAll('#district-table tbody tr');
  rows.forEach(r => {
    r.style.display = r.cells[1].textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

// ── Synchronisation automatique (toutes les 5 min) ───────────────────────
let countdown = 300; // 5 minutes en secondes

function updateCountdown() {
  const m = Math.floor(countdown / 60);
  const s = String(countdown % 60).padStart(2,'0');
  const txt = `${m}:${s}`;
  const el1 = document.getElementById('next-sync');
  const el2 = document.getElementById('countdown-footer');
  if (el1) el1.textContent = `· Prochaine sync dans ${txt}`;
  if (el2) el2.textContent = txt;
  countdown--;
  if (countdown < 0) {
    countdown = 300;
    autoSync();
  }
}

function autoSync() {
  document.getElementById('sync-dot').style.background = '#f39c12';
  fetch(window.location.href + '?sync=1&ts=' + Date.now(), {cache:'no-store'})
    .then(() => { location.reload(); })
    .catch(() => { document.getElementById('sync-dot').style.background = '#c0392b'; });
}

function forceSync() {
  const icon = document.getElementById('sync-icon');
  icon.classList.add('bi-arrow-clockwise');
  icon.style.animation = 'spin .7s linear infinite';
  setTimeout(() => location.reload(), 600);
}

setInterval(updateCountdown, 1000);
updateCountdown();
</script>

<?php include 'footer_shared.php'; ?>