<?php
declare(strict_types=1);
namespace Pharmaco\Auth;

/**
 * Authentification SANS base de données.
 *  - Comptes : variable d'environnement APP_USERS_JSON  [{"username","hash","role"}]  (hash = password_hash)
 *  - Session : cookie signé HMAC-SHA256 (APP_SECRET), HttpOnly/Secure/SameSite=Lax, expiration 8 h
 *  - CSRF    : jeton dérivé de la session (HMAC), vérifié sur tout POST
 */
final class Auth
{
    public const ROLES = ['Super Admin', 'Analyste', 'Visualiseur', 'VIP'];
    private const COOKIE = 'PH_SESS';
    private const TTL = 28800;
    private static ?array $session = null;

    private static function secret(): string {
        $s = env_get('APP_SECRET');
        if (!$s || strlen($s) < 32) {
            error_log('[pharmaco] APP_SECRET absent ou trop court (32 caractères minimum)');
            http_response_code(500);
            exit('Service indisponible (configuration serveur).');
        }
        return $s;
    }
    private static function b64e(string $d): string { return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); }
    private static function b64d(string $d): string { return (string) base64_decode(strtr($d, '-_', '+/')); }
    private static function sign(string $data): string { return self::b64e(hash_hmac('sha256', $data, self::secret(), true)); }

    private static function setCookie(string $name, string $value, int $expires): void {
        setcookie($name, $value, [
            'expires' => $expires, 'path' => '/', 'secure' => is_https(),
            'httponly' => true, 'samesite' => 'Lax',
        ]);
    }

    /** Décode APP_USERS_JSON de façon tolérante (guillemets autour, BOM, base64, objet seul). */
    private static function parseUsersEnv(): array {
        $raw = trim((string) env_get('APP_USERS_JSON', ''));
        $raw = ltrim($raw, "\xEF\xBB\xBF");
        if ($raw === '') { error_log('[pharmaco][auth] APP_USERS_JSON absent ou vide'); return []; }
        if (strlen($raw) > 1 && $raw[0] === $raw[-1] && ($raw[0] === '"' || $raw[0] === "'")) { $raw = trim(substr($raw, 1, -1)); }
        if ($raw !== '' && $raw[0] !== '[' && $raw[0] !== '{') {
            $dec = base64_decode($raw, true);
            if ($dec !== false) { $raw = trim($dec); }
        }
        $list = json_decode($raw, true);
        if ($list === null && str_contains($raw, '\\"')) { $list = json_decode(stripcslashes($raw), true); }
        if (!is_array($list)) { error_log('[pharmaco][auth] APP_USERS_JSON illisible : ' . json_last_error_msg()); return []; }
        if (isset($list['username']) || isset($list['user'])) { $list = [$list]; }
        return $list;
    }

    /** Comptes de secours définis dans APP_USERS_JSON. @return array<string,array{hash:string,role:string,name:string}> */
    public static function envUsers(): array {
        $out = [];
        foreach (self::parseUsersEnv() as $i => $u) {
            if (!is_array($u)) { error_log("[pharmaco][auth] entrée #$i ignorée : pas un objet"); continue; }
            $name = trim((string) ($u['username'] ?? $u['user'] ?? ''));
            $hash = trim((string) ($u['hash'] ?? $u['password_hash'] ?? ''));
            $role = null;
            foreach (self::ROLES as $r) { if (strcasecmp(trim((string) ($u['role'] ?? '')), $r) === 0) { $role = $r; } }
            if ($name === '' || $role === null || !preg_match('/^\$(2y|2a|2b|argon2i|argon2id)\$/', $hash)) {
                error_log("[pharmaco][auth] entrée #$i ignorée : "
                    . ($name === '' ? 'username manquant ' : '')
                    . ($role === null ? 'rôle invalide (attendu : ' . implode('|', self::ROLES) . ') ' : '')
                    . (!preg_match('/^\$(2y|2a|2b|argon2i|argon2id)\$/', $hash) ? 'hash invalide (doit être un password_hash, pas un mot de passe en clair) ' : ''));
                continue;
            }
            $out[strtolower($name)] = ['hash' => $hash, 'role' => $role, 'name' => $name];
        }
        return $out;
    }

    /** Comptes de connexion : base de données (Turso) + APP_USERS_JSON. */
    private static function users(): array { return \Pharmaco\Users\UserService::credentials(); }

    // ── Session ───────────────────────────────────────────────────────────────
    public static function session(): ?array {
        if (self::$session !== null) { return self::$session ?: null; }
        self::$session = [];
        $c = $_COOKIE[self::COOKIE] ?? '';
        if (!is_string($c) || substr_count($c, '.') !== 1) { return null; }
        [$p, $sig] = explode('.', $c);
        if (!hash_equals(self::sign($p), $sig)) { return null; }
        $d = json_decode(self::b64d($p), true);
        if (!is_array($d) || ($d['exp'] ?? 0) < time()) { return null; }
        self::$session = $d;
        return $d;
    }
    public static function user(): ?string { return self::session()['u'] ?? null; }
    public static function role(): string  { return self::session()['r'] ?? ''; }
    public static function can(string ...$roles): bool { return in_array(self::role(), $roles, true); }

    /**
     * Zones : 'std' (défaut : pages d'analyse) et 'home' (accueil) : tous les rôles ; 'premium' (module payant) : Super Admin et VIP ;
     * 'billing' (page de paiement) : VIP et Super Admin. Un VIP n'accède à rien d'autre que 'billing' sans abonnement actif.
     */
    public static function allows(string $zone): bool {
        $r = self::role();
        return match ($zone) {
            'billing' => $r === 'VIP' || $r === 'Super Admin',
            'premium' => $r === 'VIP' || $r === 'Super Admin',
            default => true,            // accueil et pages d'analyse : tous les rôles (un VIP doit en plus avoir un abonnement actif)
        };
    }

    public static function requireLogin(string $zone = 'std'): void {
        if (!self::session() || !\Pharmaco\Users\UserService::stillValid((string) self::user())) {
            if (self::session()) { self::logout(); }          // compte supprimé : session révoquée
            header('Location: /Login.php'); exit();
        }
        if (!self::allows($zone)) { http_response_code(403); header('Location: /access_denied.php'); exit(); }
        // Compte VIP sans abonnement actif : toujours redirigé vers la page de paiement.
        if (self::role() === 'VIP' && $zone !== 'billing' && !\Pharmaco\Billing\PaymentService::isActive((string) self::user())) {
            header('Location: /paiement.php'); exit();
        }
    }
    public static function requireRole(string ...$roles): void {
        self::requireLogin();
        if (!self::can(...$roles)) { http_response_code(403); header('Location: /access_denied.php'); exit(); }
    }

    // ── Connexion ─────────────────────────────────────────────────────────────
    /** @return string|null message d'erreur, null si succès */
    public static function attempt(string $username, string $password): ?string {
        // Verrou souple (cookie signé) + délai constant : limite le brute-force sans base de données.
        // Un blocage serveur (WAF Vercel) est recommandé en complément — voir README.
        $lf = self::readLock();
        if ($lf['n'] >= 5 && $lf['t'] > time() - 900) { return 'Trop de tentatives. Réessayez dans 15 minutes.'; }

        usleep(random_int(300000, 600000));
        $users = self::users();
        $u = $users[strtolower($username)] ?? null;
        $ok = password_verify($password, $u['hash'] ?? '$2y$10$usesomesillystringforsaltuQ3m3o3ZyqkVJ7k0mR8mV1l6w5p9x2a') && $u !== null;
        if (!$ok) {
            error_log('[pharmaco][auth] échec : ' . ($u === null ? 'utilisateur inconnu (' . count($users) . ' compte(s) chargé(s))' : 'mot de passe différent du hash'));
            $n = ($lf['t'] > time() - 900 ? $lf['n'] : 0) + 1;
            $payload = self::b64e(json_encode(['n' => $n, 't' => time()]));
            self::setCookie('PH_LF', $payload . '.' . self::sign('lf' . $payload), time() + 900);
            return 'Identifiant ou mot de passe incorrect.';
        }
        self::setCookie('PH_LF', '', time() - 3600);
        $p = self::b64e(json_encode(['u' => $u['name'], 'r' => $u['role'], 'sid' => bin2hex(random_bytes(16)), 'exp' => time() + self::TTL]));
        self::setCookie(self::COOKIE, $p . '.' . self::sign($p), time() + self::TTL);
        self::$session = null;
        return null;
    }
    private static function readLock(): array {
        $c = $_COOKIE['PH_LF'] ?? '';
        if (is_string($c) && substr_count($c, '.') === 1) {
            [$p, $sig] = explode('.', $c);
            if (hash_equals(self::sign('lf' . $p), $sig)) {
                $d = json_decode(self::b64d($p), true);
                if (is_array($d)) { return ['n' => (int) ($d['n'] ?? 0), 't' => (int) ($d['t'] ?? 0)]; }
            }
        }
        return ['n' => 0, 't' => 0];
    }
    public static function logout(): void {
        self::setCookie(self::COOKIE, '', time() - 3600);
        self::$session = null;
    }

    // ── CSRF ──────────────────────────────────────────────────────────────────
    /** Jeton lié à la session ; avant connexion : cookie « double submit » aléatoire. */
    public static function csrfToken(): string {
        $s = self::session();
        if ($s) { return self::b64e(hash_hmac('sha256', 'csrf|' . $s['sid'], self::secret(), true)); }
        $t = $_COOKIE['PH_CSRF'] ?? '';
        if (!is_string($t) || !preg_match('/^[A-Za-z0-9_-]{43}$/', $t)) {
            $t = self::b64e(random_bytes(32));
            self::setCookie('PH_CSRF', $t, 0);
            $_COOKIE['PH_CSRF'] = $t;
        }
        return $t;
    }
    public static function csrfField(): string {
        return '<input type="hidden" name="csrf" value="' . h(self::csrfToken()) . '">';
    }
    public static function csrfCheck(): void {
        $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!is_string($sent) || !hash_equals(self::csrfToken(), $sent)) {
            http_response_code(403);
            exit('Requête refusée (jeton CSRF invalide). Rechargez la page et réessayez.');
        }
    }
}
