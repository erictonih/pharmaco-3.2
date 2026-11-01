<?php
/**
 * db.php — Configuration, connexion MySQL, sessions et sécurité (point d'entrée commun)
 *
 * Toute la configuration vient des variables d'environnement (Vercel > Settings > Environment Variables).
 * AUCUN secret ne doit être écrit dans le code.
 *
 *   DATABASE_URL        → fournie automatiquement par l'intégration Neon de Vercel
 *   (ou DB_HOST, DB_PORT, DB_USER, DB_PASSWORD, DB_NAME, DB_SSLMODE)
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

// ── Connexion PostgreSQL (Neon) ───────────────────────────────────────────────
// Neon via Vercel injecte DATABASE_URL / POSTGRES_URL (…?sslmode=require). Sinon : DB_HOST, DB_NAME…
// Couche de compatibilité : expose l'API mysqli utilisée par les pages (query/prepare/bind_param/
// execute/get_result/fetch_assoc/num_rows/bind_result/fetch) au-dessus de PDO pgsql.

// Colonnes à casse mixte héritées de MySQL : PostgreSQL les renvoie en minuscules.
const PG_COLUMN_CASE = ['Id','Nom','Prenom','Age','Genre','Type','Residence','District','Nombres','Date'];

function pg_case_map(string $sql): array {
    $map = [];
    foreach (PG_COLUMN_CASE as $c) { $map[strtolower($c)] = $c; }
    if (preg_match_all('/\bAS\s+([A-Za-z_][A-Za-z0-9_]*)/i', $sql, $m)) {
        foreach ($m[1] as $a) { $map[strtolower($a)] = $a; }   // alias : casse exacte de la requête
    }
    return $map;
}

class PgResult {
    public int $num_rows = 0;
    private array $rows;
    private array $map;
    private int $i = 0;
    public function __construct(array $rows, array $map) { $this->rows = $rows; $this->map = $map; $this->num_rows = count($rows); }
    private function fix(array $r): array {
        $o = [];
        foreach ($r as $k => $v) { $o[$this->map[$k] ?? $k] = $v; }
        return $o;
    }
    public function fetch_assoc(): ?array { return isset($this->rows[$this->i]) ? $this->fix($this->rows[$this->i++]) : null; }
    public function fetch_all(): array { $o = []; while (($r = $this->fetch_assoc()) !== null) { $o[] = $r; } return $o; }
    public function close(): void {}
}

class PgStmt {
    public string $error = '';
    public int $num_rows = 0;
    private PDOStatement $st;
    private array $map;
    private array $types = [];
    private array $refs = [];
    private array $rows = [];
    private int $ri = 0;
    private array $out = [];
    public function __construct(PDOStatement $st, string $sql) { $this->st = $st; $this->map = pg_case_map($sql); }
    public function bind_param(string $types, &...$vars): bool { $this->types = str_split($types); $this->refs = []; foreach ($vars as $k => &$v) { $this->refs[$k] = &$v; } return true; }
    public function execute(): bool {
        try {
            foreach ($this->refs as $k => $v) {
                $t = $this->types[$k] ?? 's';
                $this->st->bindValue($k + 1, $v, $t === 'i' && $v !== null ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $this->st->execute();
            $this->rows = $this->st->columnCount() > 0 ? $this->st->fetchAll(PDO::FETCH_ASSOC) : [];
            $this->num_rows = count($this->rows);
            $this->ri = 0;
            return true;
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            error_log('[pharmaco][sql] ' . $e->getMessage());
            return false;
        }
    }
    public function get_result(): PgResult { return new PgResult($this->rows, $this->map); }
    public function store_result(): bool { return true; }
    public function bind_result(&...$vars): bool { $this->out = []; foreach ($vars as $k => &$v) { $this->out[$k] = &$v; } return true; }
    public function fetch(): ?bool {
        if (!isset($this->rows[$this->ri])) { return null; }
        $vals = array_values($this->rows[$this->ri++]);
        foreach ($this->out as $k => &$ref) { $ref = $vals[$k] ?? null; }
        return true;
    }
    public function close(): bool { return true; }
}

class PgConn {
    public ?string $connect_error = null;
    public string $error = '';
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }
    public function query(string $sql) {
        try {
            $st = $this->pdo->query($sql);
            if ($st->columnCount() === 0) { return true; }
            return new PgResult($st->fetchAll(PDO::FETCH_ASSOC), pg_case_map($sql));
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            error_log('[pharmaco][sql] ' . $e->getMessage());
            return false;
        }
    }
    public function prepare(string $sql) {
        try { return new PgStmt($this->pdo->prepare($sql), $sql); }
        catch (PDOException $e) { $this->error = $e->getMessage(); error_log('[pharmaco][sql] ' . $e->getMessage()); return false; }
    }
    public function set_charset(string $c): bool { return true; }
    public function close(): bool { return true; }
}

function db(): PgConn {
    static $conn = null;
    if ($conn instanceof PgConn) { return $conn; }

    $url = env_get('DATABASE_URL') ?: env_get('POSTGRES_URL') ?: env_get('POSTGRES_URL_NON_POOLING');
    $host = $port = $user = $pass = $name = null; $ssl = 'require';
    if ($url) {
        $u = parse_url($url);
        $host = $u['host'] ?? null; $port = $u['port'] ?? 5432;
        $user = isset($u['user']) ? rawurldecode($u['user']) : null;
        $pass = isset($u['pass']) ? rawurldecode($u['pass']) : null;
        $name = isset($u['path']) ? ltrim($u['path'], '/') : null;
        parse_str($u['query'] ?? '', $q);
        $ssl = $q['sslmode'] ?? 'require';
    } else {
        $host = env_get('DB_HOST'); $port = (int) env_get('DB_PORT', 5432);
        $user = env_get('DB_USER'); $pass = env_get('DB_PASSWORD'); $name = env_get('DB_NAME');
        $ssl  = env_get('DB_SSLMODE', 'require');
    }
    if (!$host || !$name) {
        error_log('[pharmaco] base de données non configurée (DATABASE_URL)');
        http_response_code(500);
        exit('Service indisponible (configuration serveur).');
    }
    try {
        $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$name;sslmode=$ssl;connect_timeout=8", $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES   => true,    // compatible pooler Neon (pgbouncer, mode transaction)
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
        $pdo->exec("SET TIME ZONE 'Indian/Antananarivo'");
    } catch (PDOException $e) {
        error_log('[pharmaco] connexion DB échouée : ' . $e->getMessage());
        http_response_code(503);
        exit('Service indisponible (base de données).');
    }
    return $conn = new PgConn($pdo);
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
        $out = $st->fetch() ? (string) base64_decode((string) $data) : '';
        return $out;
    }
    public function write(string $id, string $data): bool {
        $exp = time() + 8 * 3600;
        $enc = base64_encode($data);
        $st = db()->prepare('INSERT INTO app_sessions (id, data, expires_at) VALUES (?, ?, ?)
                             ON CONFLICT (id) DO UPDATE SET data = EXCLUDED.data, expires_at = EXCLUDED.expires_at');
        $st->bind_param('ssi', $id, $enc, $exp);
        return $st->execute();
    }
    public function destroy(string $id): bool {
        $st = db()->prepare('DELETE FROM app_sessions WHERE id = ?');
        $st->bind_param('s', $id);
        return $st->execute();
    }
    public function gc(int $max): int|false {
        db()->query('DELETE FROM app_sessions WHERE expires_at < ' . time());
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
