<?php
/**
 * db.php — Configuration, connexion MySQL, sessions et sécurité (point d'entrée commun)
 *
 * Toute la configuration vient des variables d'environnement (Vercel > Settings > Environment Variables).
 * AUCUN secret ne doit être écrit dans le code.
 *
 *   DB_HOST, DB_PORT (défaut 3306), DB_USER, DB_PASSWORD, DB_NAME
 *   DB_SSL=1            → connexion TLS (obligatoire chez la plupart des MySQL cloud)
 *   DB_SSL_CA_PEM       → (optionnel) certificat CA du fournisseur, contenu PEM
 *   GOOGLE_SA_JSON_B64  → compte de service Google (JSON encodé en base64)
 *   DHIS2_USER / DHIS2_PASSWORD (si utilisés)
 */

if (defined('PHARMACO_BOOTSTRAPPED')) { return; }
define('PHARMACO_BOOTSTRAPPED', true);

// ── Erreurs : jamais affichées au navigateur, seulement dans les logs ─────────
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

date_default_timezone_set('Indian/Antananarivo');

function env_get(string $key, $default = null) {
    $v = getenv($key);
    if ($v === false || $v === '') {
        $v = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    }
    return ($v === null || $v === '') ? $default : $v;
}

function is_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

// ── En-têtes de sécurité ──────────────────────────────────────────────────────
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// ── Connexion MySQL (mysqli, exceptions désactivées → on gère nous-mêmes) ─────
function db(): mysqli {
    static $conn = null;
    if ($conn instanceof mysqli) { return $conn; }

    $host = env_get('DB_HOST');
    $name = env_get('DB_NAME');
    if (!$host || !$name) {
        error_log('[pharmaco] DB_HOST / DB_NAME non définis');
        http_response_code(500);
        exit('Service indisponible (configuration serveur).');
    }

    mysqli_report(MYSQLI_REPORT_OFF);
    $c = mysqli_init();
    $c->options(MYSQLI_OPT_CONNECT_TIMEOUT, 8);
    $flags = 0;

    if (env_get('DB_SSL', '1') !== '0') {
        $ca = null;
        if ($pem = env_get('DB_SSL_CA_PEM')) {
            $ca = sys_get_temp_dir() . '/db_ca_' . md5($pem) . '.pem';
            if (!is_file($ca)) { file_put_contents($ca, str_replace('\n', "\n", $pem)); }
        }
        $c->ssl_set(null, null, $ca, null, null);
        $flags |= MYSQLI_CLIENT_SSL;
        if ($ca === null) { $flags |= MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT; }
    }

    $ok = @$c->real_connect(
        $host,
        env_get('DB_USER', ''),
        env_get('DB_PASSWORD', ''),
        $name,
        (int) env_get('DB_PORT', 3306),
        null,
        $flags
    );
    if (!$ok) {
        error_log('[pharmaco] connexion DB échouée : ' . $c->connect_error);
        http_response_code(503);
        exit('Service indisponible (base de données).');
    }
    $c->set_charset('utf8mb4');
    $c->query("SET time_zone = '+03:00'");
    return $conn = $c;
}

// ── Sessions stockées en base (le disque Vercel est éphémère et non partagé) ──
class DbSessionHandler implements SessionHandlerInterface {
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }
    public function read(string $id): string|false {
        $st = db()->prepare('SELECT data FROM app_sessions WHERE id = ? AND expires_at > ?');
        $now = time();
        $st->bind_param('si', $id, $now);
        $st->execute();
        $st->bind_result($data);
        $out = $st->fetch() ? (string) $data : '';
        $st->close();
        return $out;
    }
    public function write(string $id, string $data): bool {
        $exp = time() + 8 * 3600;
        $st = db()->prepare('REPLACE INTO app_sessions (id, data, expires_at) VALUES (?, ?, ?)');
        $st->bind_param('ssi', $id, $data, $exp);
        $ok = $st->execute();
        $st->close();
        return $ok;
    }
    public function destroy(string $id): bool {
        $st = db()->prepare('DELETE FROM app_sessions WHERE id = ?');
        $st->bind_param('s', $id);
        $ok = $st->execute();
        $st->close();
        return $ok;
    }
    public function gc(int $max): int|false {
        $now = time();
        db()->query('DELETE FROM app_sessions WHERE expires_at < ' . $now);
        return 0;
    }
}

function app_session_start(): void {
    if (PHP_SAPI === 'cli' || session_status() !== PHP_SESSION_NONE) { return; }
    session_set_save_handler(new DbSessionHandler(), true);
    session_name('PHARMACO_SID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}
app_session_start();

// ── CSRF ──────────────────────────────────────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}
function csrf_check(): void {
    $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(403);
        exit('Requête refusée (jeton CSRF invalide). Rechargez la page et réessayez.');
    }
}

function h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: /Login.php');
        exit();
    }
}
function require_role(array $roles): void {
    require_login();
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        http_response_code(403);
        exit('Accès refusé.');
    }
}

// ── Compte de service Google (depuis variable d'environnement) ────────────────
function google_service_account(): ?array {
    $b64 = env_get('GOOGLE_SA_JSON_B64');
    if (!$b64) { return null; }
    $j = json_decode(base64_decode($b64), true);
    return is_array($j) ? $j : null;
}

function cache_dir(): string {
    $d = sys_get_temp_dir() . '/pharmaco_cache';
    if (!is_dir($d)) { @mkdir($d, 0700, true); }
    return $d;
}
