<?php
/**
 * setup_google_sheets.php
 * ─────────────────────────────────────────────────────────────
 * Script de configuration et diagnostic Google Sheets API
 * À SUPPRIMER après configuration réussie !
 * Accès : http://votre-site/setup_google_sheets.php
 * ─────────────────────────────────────────────────────────────
 */
session_start();
if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? '') !== 'Super Admin') {
    die('<h2 style="color:red;font-family:sans-serif;">Accès refusé — Super Admin uniquement</h2>');
}

define('SA_KEY_FILE', __DIR__ . '/config/google_service_account.json');
define('CACHE_DIR',   __DIR__ . '/cache/');

$msg     = '';
$msgType = '';

// ── Action : upload du fichier JSON ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['sa_json'])) {
    $file = $_FILES['sa_json'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = '❌ Erreur lors de l\'upload : code ' . $file['error'];
        $msgType = 'error';
    } else {
        $content = file_get_contents($file['tmp_name']);
        $json    = json_decode($content, true);

        if (!$json) {
            $msg = '❌ Fichier JSON invalide ou corrompu.';
            $msgType = 'error';
        } elseif (($json['type'] ?? '') !== 'service_account') {
            $msg = '❌ Ce fichier n\'est PAS un Service Account. '
                 . 'Type détecté : <code>' . htmlspecialchars($json['type'] ?? 'inconnu') . '</code>. '
                 . 'Il faut un fichier avec <code>"type": "service_account"</code>.';
            $msgType = 'error';
        } else {
            // Créer le dossier config si besoin
            if (!is_dir(dirname(SA_KEY_FILE))) {
                mkdir(dirname(SA_KEY_FILE), 0750, true);
            }
            if (file_put_contents(SA_KEY_FILE, $content) !== false) {
                chmod(SA_KEY_FILE, 0600);
                $msg = '✅ Service Account enregistré avec succès ! '
                     . 'Email : <strong>' . htmlspecialchars($json['client_email']) . '</strong>';
                $msgType = 'success';
            } else {
                $msg = '❌ Impossible d\'écrire le fichier. Vérifiez les permissions du dossier <code>config/</code>.';
                $msgType = 'error';
            }
        }
    }
}

// ── Action : créer le dossier cache ───────────────────────────────────────
if (isset($_POST['create_cache'])) {
    if (!is_dir(CACHE_DIR)) {
        mkdir(CACHE_DIR, 0755, true);
        $msg = '✅ Dossier cache/ créé.';
        $msgType = 'success';
    }
}

// ── Diagnostics ───────────────────────────────────────────────────────────
$diag = [];

// 1. Fichier SA existe ?
$saExists   = file_exists(SA_KEY_FILE);
$saValid    = false;
$saEmail    = '';
$saProject  = '';
if ($saExists) {
    $saJson    = json_decode(file_get_contents(SA_KEY_FILE), true);
    $saValid   = ($saJson['type'] ?? '') === 'service_account';
    $saEmail   = $saJson['client_email'] ?? '';
    $saProject = $saJson['project_id']   ?? '';
}
$diag[] = ['label' => 'Fichier Service Account',
           'ok'    => $saExists && $saValid,
           'detail'=> $saExists
               ? ($saValid ? "✅ Valide · $saEmail" : '❌ Fichier présent mais type incorrect')
               : '❌ Absent — uploadez-le ci-dessous'];

// 2. Dossier cache/ accessible en écriture ?
$cacheOk = is_dir(CACHE_DIR) && is_writable(CACHE_DIR);
$diag[]  = ['label' => 'Dossier cache/ (écriture)',
            'ok'    => $cacheOk,
            'detail'=> $cacheOk ? '✅ Accessible' : '❌ Manquant ou non accessible en écriture'];

// 3. Extension OpenSSL disponible ?
$sslOk  = extension_loaded('openssl');
$diag[] = ['label' => 'Extension OpenSSL (JWT)',
           'ok'    => $sslOk,
           'detail'=> $sslOk ? '✅ Disponible' : '❌ Manquante — impossible de signer le JWT'];

// 4. cURL disponible ?
$curlOk = function_exists('curl_init');
$diag[] = ['label' => 'Extension cURL',
           'ok'    => $curlOk,
           'detail'=> $curlOk ? '✅ Disponible' : '❌ Manquante — requise pour les appels API'];

// 5. allow_url_fopen ?
$furlOk = (bool) ini_get('allow_url_fopen');
$diag[] = ['label' => 'allow_url_fopen',
           'ok'    => $curlOk || $furlOk, // cURL suffit
           'detail'=> $furlOk ? '✅ Activé' : '⚠️ Désactivé (cURL sera utilisé à la place)'];

// 6. Test connexion Google OAuth ─────────────────────────────────────────
$googleReach = false;
if ($curlOk) {
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>5,
                            CURLOPT_NOBODY=>true, CURLOPT_SSL_VERIFYPEER=>false]);
    curl_exec($ch);
    $googleReach = (curl_getinfo($ch, CURLINFO_HTTP_CODE) > 0);
    curl_close($ch);
}
$diag[] = ['label' => 'Accès réseau → Google APIs',
           'ok'    => $googleReach,
           'detail'=> $googleReach ? '✅ Accessible' : '❌ Bloqué — vérifiez le pare-feu hébergeur'];

// 7. Test token Google (si tout est OK) ─────────────────────────────────
$tokenOk    = false;
$tokenError = '';
if ($saExists && $saValid && $sslOk && $curlOk && $googleReach) {
    try {
        $tok = getGoogleTokenCurl($saJson);
        $tokenOk = !empty($tok);
        if (!$tokenOk) $tokenError = 'Token vide — vérifiez que le Sheet est partagé avec ' . $saEmail;
    } catch (Exception $e) {
        $tokenError = $e->getMessage();
    }
}
$diag[] = ['label' => 'Génération token OAuth2',
           'ok'    => $tokenOk,
           'detail'=> $tokenOk ? '✅ Token obtenu avec succès' : '❌ ' . htmlspecialchars($tokenError)];

// ── Fonction : obtenir token via cURL ─────────────────────────────────────
function getGoogleTokenCurl(array $key): string {
    $now   = time();
    $claim = [
        'iss'   => $key['client_email'],
        'scope' => 'https://www.googleapis.com/auth/spreadsheets.readonly',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
    ];
    $h = rtrim(strtr(base64_encode(json_encode(['alg'=>'RS256','typ'=>'JWT'])), '+/', '-_'), '=');
    $p = rtrim(strtr(base64_encode(json_encode($claim)), '+/', '-_'), '=');
    openssl_sign("$h.$p", $sig, $key['private_key'], 'SHA256');
    $s   = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
    $jwt = "$h.$p.$s";

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $resp = curl_exec($ch);
    if (curl_errno($ch)) throw new Exception('cURL : ' . curl_error($ch));
    curl_close($ch);
    $data = json_decode($resp, true);
    if (isset($data['error'])) throw new Exception($data['error'] . ': ' . ($data['error_description'] ?? ''));
    return $data['access_token'] ?? '';
}

$allOk = array_reduce($diag, fn($c, $d) => $c && $d['ok'], true);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Configuration Google Sheets — PharmaCRM</title>
<style>
body{font-family:'Inter',system-ui,sans-serif;background:#f0f4f8;margin:0;padding:24px;color:#1a2332;}
.wrap{max-width:780px;margin:0 auto;}
h1{font-size:1.4rem;margin-bottom:4px;display:flex;align-items:center;gap:10px;}
.sub{color:#6b7a8d;font-size:.88rem;margin-bottom:24px;}
.card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:22px;margin-bottom:18px;}
.card h2{font-size:1rem;margin:0 0 16px;color:#1a2332;display:flex;align-items:center;gap:8px;}
.diag-row{display:flex;align-items:flex-start;gap:12px;padding:10px 0;border-bottom:1px solid #f0f4f8;font-size:.88rem;}
.diag-row:last-child{border:none;}
.diag-label{font-weight:600;min-width:220px;color:#334155;}
.diag-detail{color:#475569;flex:1;}
.ok-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;margin-top:3px;}
.dot-ok{background:#22c55e;} .dot-err{background:#ef4444;} .dot-warn{background:#f59e0b;}
.alert{padding:12px 16px;border-radius:8px;font-size:.88rem;margin-bottom:16px;border:1px solid;}
.alert-success{background:#f0fdf4;border-color:#86efac;color:#166534;}
.alert-error  {background:#fef2f2;border-color:#fca5a5;color:#991b1b;}
.alert-info   {background:#eff6ff;border-color:#93c5fd;color:#1e40af;}
label{display:block;font-size:.85rem;font-weight:600;color:#374151;margin-bottom:6px;}
input[type=file]{width:100%;padding:10px;border:2px dashed #cbd5e1;border-radius:8px;
                  font-size:.88rem;background:#f8fafc;cursor:pointer;box-sizing:border-box;}
input[type=file]:hover{border-color:#3b82f6;background:#eff6ff;}
.btn{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:8px;
     font-size:.88rem;font-weight:600;cursor:pointer;border:none;transition:.15s;}
.btn-primary{background:#3b82f6;color:#fff;} .btn-primary:hover{background:#2563eb;}
.btn-danger {background:#ef4444;color:#fff;} .btn-danger:hover{background:#dc2626;}
.steps{list-style:none;padding:0;margin:0;counter-reset:step;}
.steps li{counter-increment:step;display:flex;gap:14px;padding:10px 0;
           border-bottom:1px solid #f0f4f8;font-size:.88rem;color:#374151;align-items:flex-start;}
.steps li:last-child{border:none;}
.steps li::before{content:counter(step);min-width:24px;height:24px;border-radius:50%;
                   background:#3b82f6;color:#fff;font-weight:700;font-size:.8rem;
                   display:flex;align-items:center;justify-content:center;flex-shrink:0;}
code{background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:.85rem;color:#e11d48;}
.success-banner{background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;border-radius:12px;
                 padding:20px 24px;text-align:center;margin-bottom:18px;}
.warning-banner{background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;border-radius:12px;
                 padding:16px 24px;margin-bottom:18px;font-size:.88rem;}
</style>
</head>
<body>
<div class="wrap">

<h1>⚙️ Configuration Google Sheets API</h1>
<div class="sub">Assistant de configuration — PharmaCRM · Module Analyse Mpox</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType === 'success' ? 'success' : 'error' ?>">
    <?= $msg ?>
</div>
<?php endif; ?>

<?php if ($allOk): ?>
<div class="success-banner">
    <div style="font-size:1.5rem;margin-bottom:6px;">✅ Tout est configuré correctement !</div>
    <div style="opacity:.9;">La synchronisation avec Google Sheets est opérationnelle.</div>
    <div style="margin-top:12px;">
        <a href="analyse_mpox.php" style="background:#fff;color:#16a34a;padding:8px 20px;border-radius:8px;font-weight:700;text-decoration:none;">
            → Ouvrir l'analyse Mpox
        </a>
    </div>
</div>
<?php endif; ?>

<!-- ── Diagnostics ─────────────────────────────────────────────────── -->
<div class="card">
    <h2>🔍 Diagnostic du système</h2>
    <?php foreach ($diag as $d):
        $dot = $d['ok'] ? 'dot-ok' : (str_contains($d['detail'],'⚠️') ? 'dot-warn' : 'dot-err');
    ?>
    <div class="diag-row">
        <div class="ok-dot <?= $dot ?>" style="margin-top:4px;"></div>
        <div class="diag-label"><?= htmlspecialchars($d['label']) ?></div>
        <div class="diag-detail"><?= $d['detail'] ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── Upload Service Account JSON ───────────────────────────────── -->
<?php if (!$saExists || !$saValid): ?>
<div class="card">
    <h2>📁 Uploader le fichier Service Account</h2>

    <div class="alert alert-info" style="margin-bottom:16px;">
        Le fichier doit être de type <strong>Service Account</strong> (pas OAuth2 Web Client).<br>
        Il doit contenir <code>"type": "service_account"</code>.
    </div>

    <form method="POST" enctype="multipart/form-data">
        <div style="margin-bottom:16px;">
            <label for="sa_json">Sélectionner le fichier JSON du Service Account</label>
            <input type="file" name="sa_json" id="sa_json" accept=".json" required>
            <div style="font-size:.78rem;color:#6b7a8d;margin-top:6px;">
                Sera enregistré dans : <code><?= htmlspecialchars(SA_KEY_FILE) ?></code>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">⬆️ Uploader et configurer</button>
    </form>
</div>
<?php endif; ?>

<!-- ── Infos Service Account si valide ───────────────────────────── -->
<?php if ($saExists && $saValid): ?>
<div class="card">
    <h2>✅ Service Account configuré</h2>
    <div class="diag-row">
        <div class="diag-label">Email du compte</div>
        <div class="diag-detail"><code><?= htmlspecialchars($saEmail) ?></code></div>
    </div>
    <div class="diag-row">
        <div class="diag-label">Projet Google Cloud</div>
        <div class="diag-detail"><code><?= htmlspecialchars($saProject) ?></code></div>
    </div>
    <div class="diag-row" style="border:none;">
        <div class="diag-label">Fichier</div>
        <div class="diag-detail">
            <code><?= htmlspecialchars(SA_KEY_FILE) ?></code>
            · <?= number_format(filesize(SA_KEY_FILE)/1024, 1) ?> Ko
        </div>
    </div>

    <?php if (!$tokenOk): ?>
    <div class="alert alert-error" style="margin-top:12px;">
        ⚠️ Le token ne peut pas être généré. Vérifiez que la feuille Google Sheet est bien partagée avec :<br>
        <strong><?= htmlspecialchars($saEmail) ?></strong>
    </div>
    <?php endif; ?>

    <div style="margin-top:14px;">
        <form method="POST" enctype="multipart/form-data">
            <label>Remplacer le fichier Service Account</label>
            <input type="file" name="sa_json" accept=".json" style="margin-bottom:10px;">
            <button type="submit" class="btn btn-primary">🔄 Remplacer</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ── Créer cache si manquant ────────────────────────────────────── -->
<?php if (!$cacheOk): ?>
<div class="card">
    <h2>📂 Créer le dossier cache/</h2>
    <p style="font-size:.88rem;color:#475569;">
        Le dossier <code><?= htmlspecialchars(CACHE_DIR) ?></code> est requis pour le cache des données.
    </p>
    <form method="POST">
        <button type="submit" name="create_cache" class="btn btn-primary">📁 Créer le dossier cache/</button>
    </form>
</div>
<?php endif; ?>

<!-- ── Instructions ───────────────────────────────────────────────── -->
<div class="card">
    <h2>📋 Étapes de configuration (si pas encore fait)</h2>
    <ol class="steps">
        <li>
            <div>
                Aller sur <a href="https://console.cloud.google.com/apis/credentials" target="_blank">console.cloud.google.com/apis/credentials</a>
                → Projet : <code>wired-ripsaw-471110-p6</code>
            </div>
        </li>
        <li>
            <div>
                <strong>IAM & Admin → Comptes de service → Créer</strong><br>
                Nom : <code>pharmaco-mpox-reader</code> → Terminer<br>
                Puis : onglet <strong>Clés → Ajouter une clé → JSON</strong> → Télécharger
            </div>
        </li>
        <li>
            <div>
                Uploader le fichier JSON téléchargé via le formulaire ci-dessus
            </div>
        </li>
        <li>
            <div>
                <strong>Partager la feuille Google Sheet</strong> avec l'email du Service Account affiché ci-dessus<br>
                <em>Google Sheets → Partager → coller l'email → Rôle : Lecteur</em>
            </div>
        </li>
        <li>
            <div>
                Vérifier que tous les diagnostics sont verts ✅ puis supprimer ce fichier
            </div>
        </li>
    </ol>
</div>

<!-- ── Danger zone ────────────────────────────────────────────────── -->
<div class="card" style="border-color:#fca5a5;">
    <h2 style="color:#991b1b;">🗑️ Supprimer ce fichier de configuration</h2>
    <p style="font-size:.88rem;color:#475569;">
        Une fois la configuration terminée, supprimez ce fichier pour des raisons de sécurité.
    </p>
    <?php if (isset($_POST['delete_setup']) && $_POST['confirm_delete'] === 'OUI'): ?>
        <?php unlink(__FILE__); ?>
        <div class="alert alert-success">✅ Fichier supprimé. Redirection...</div>
        <script>setTimeout(()=>location.href='analyse_mpox.php', 2000);</script>
    <?php else: ?>
    <form method="POST" style="display:flex;gap:10px;align-items:center;">
        <input type="text" name="confirm_delete" placeholder='Tapez "OUI" pour confirmer'
               style="padding:8px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:.88rem;outline:none;">
        <button type="submit" name="delete_setup" class="btn btn-danger">🗑️ Supprimer ce fichier</button>
    </form>
    <?php endif; ?>
</div>

</div><!-- /wrap -->
</body>
</html>
