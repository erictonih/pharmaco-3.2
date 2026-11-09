<?php
declare(strict_types=1);
namespace Pharmaco\Users;

use RuntimeException;

/**
 * Base de données des utilisateurs : Turso (libSQL) via son API HTTP — aucun pilote PHP requis, adapté à Vercel.
 * Variables : TURSO_DATABASE_URL (libsql://… ou https://…) et TURSO_AUTH_TOKEN (fournies par l'intégration Vercel).
 */
final class TursoUserStore implements UserStore
{
    private const SCHEMA = 'CREATE TABLE IF NOT EXISTS app_users (
        username TEXT PRIMARY KEY, username_lc TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL,
        role TEXT NOT NULL, created_at TEXT NOT NULL, created_by TEXT NOT NULL DEFAULT \'\', expires_at TEXT)';
    private const SCHEMA_SUBS = 'CREATE TABLE IF NOT EXISTS app_subscriptions (username_lc TEXT PRIMARY KEY, paid_until TEXT NOT NULL, updated_at TEXT NOT NULL)';
    private const SCHEMA_PAY = 'CREATE TABLE IF NOT EXISTS app_payments (
        id TEXT PRIMARY KEY, username_lc TEXT NOT NULL, amount INTEGER NOT NULL, currency TEXT NOT NULL, days INTEGER NOT NULL,
        status TEXT NOT NULL, created_at TEXT NOT NULL, confirmed_at TEXT, reference TEXT, sender TEXT, decided_by TEXT, decided_at TEXT, note TEXT)';
    /** Colonnes ajoutées aux tables créées par une version précédente (erreur « duplicate column » ignorée). */
    private const ALTERS = [
        'ALTER TABLE app_users ADD COLUMN expires_at TEXT',
        'ALTER TABLE app_payments ADD COLUMN reference TEXT', 'ALTER TABLE app_payments ADD COLUMN sender TEXT',
        'ALTER TABLE app_payments ADD COLUMN decided_by TEXT', 'ALTER TABLE app_payments ADD COLUMN decided_at TEXT', 'ALTER TABLE app_payments ADD COLUMN note TEXT',
    ];
    private const INDEXES = ['CREATE UNIQUE INDEX IF NOT EXISTS ix_pay_reference ON app_payments(reference)'];
    private static bool $migrated = false;

    public function __construct(private string $baseUrl, private string $token) {
        $this->baseUrl = rtrim((string) preg_replace('#^libsql://#', 'https://', $baseUrl), '/');
    }

    public static function fromEnv(): ?self {
        $u = env_get('TURSO_DATABASE_URL'); $t = env_get('TURSO_AUTH_TOKEN');
        if (!$u || !$t) { return null; }
        // Hors développement local, l'URL doit être en HTTPS (le jeton transite dans l'en-tête).
        if (env_get('APP_ENV') !== 'local' && !preg_match('#^(libsql|https)://#', $u)) { return null; }
        return new self($u, $t);
    }

    public function all(): array {
        // Les comptes expirés sont supprimés (définitivement) avant chaque lecture, dans le même aller-retour.
        $r = $this->run([
            ['DELETE FROM app_users WHERE expires_at IS NOT NULL AND expires_at <= ?', [gmdate('Y-m-d H:i:s')]],
            ['SELECT username, password_hash, role, created_at, created_by, expires_at FROM app_users ORDER BY username_lc', []],
        ]);
        if ($r[0]['affected'] > 0) { error_log('[pharmaco][users] ' . $r[0]['affected'] . ' compte(s) limité(s) expiré(s) supprimé(s)'); }
        return array_map(static fn(array $x) => ['username' => $x[0], 'hash' => $x[1], 'role' => $x[2], 'created_at' => $x[3], 'created_by' => $x[4], 'expires_at' => ($x[5] ?? '') === '' ? null : $x[5]], $r[1]['rows']);
    }

    public function create(string $username, string $hash, string $role, string $createdBy, ?string $expiresAt = null): void {
        $this->run([[
            'INSERT INTO app_users (username, username_lc, password_hash, role, created_at, created_by, expires_at) VALUES (?,?,?,?,?,?,?)',
            [$username, mb_strtolower($username, 'UTF-8'), $hash, $role, gmdate('Y-m-d H:i:s'), $createdBy, $expiresAt],
        ]]);
    }

    public function delete(string $username): bool {
        $r = $this->run([['DELETE FROM app_users WHERE username_lc = ?', [mb_strtolower($username, 'UTF-8')]]])[0];
        return $r['affected'] > 0;
    }

    public function subscriptionUntil(string $username): ?string {
        $r = $this->run([['SELECT paid_until FROM app_subscriptions WHERE username_lc = ?', [mb_strtolower($username, 'UTF-8')]]])[0];
        return $r['rows'][0][0] ?? null;
    }

    public function subscriptions(): array {
        $out = []; foreach ($this->run([['SELECT username_lc, paid_until FROM app_subscriptions', []]])[0]['rows'] as $x) { $out[$x[0]] = $x[1]; }
        return $out;
    }

    private const PAY_COLS = 'id, username_lc, amount, currency, days, status, reference, sender, created_at, decided_by, decided_at, note';
    private static function payRow(array $x): array {
        return ['id' => $x[0], 'username' => $x[1], 'amount' => (int) $x[2], 'currency' => $x[3], 'days' => (int) $x[4], 'status' => $x[5],
                'reference' => (string) $x[6], 'sender' => (string) $x[7], 'created_at' => $x[8], 'decided_by' => (string) $x[9], 'decided_at' => (string) $x[10], 'note' => (string) $x[11]];
    }

    public function submitPayment(string $id, string $username, int $amount, string $currency, int $days, string $reference, string $sender): void {
        $this->run([['INSERT INTO app_payments (id, username_lc, amount, currency, days, status, created_at, reference, sender) VALUES (?,?,?,?,?,?,?,?,?)',
            [$id, mb_strtolower($username, 'UTF-8'), $amount, $currency, $days, 'submitted', gmdate('Y-m-d H:i:s'), $reference, $sender]]]);
    }

    public function payment(string $id): ?array {
        $x = $this->run([['SELECT ' . self::PAY_COLS . ' FROM app_payments WHERE id = ?', [$id]]])[0]['rows'][0] ?? null;
        return $x ? self::payRow($x) : null;
    }

    public function payments(?string $status = null, ?string $username = null, int $limit = 50): array {
        $w = []; $a = [];
        if ($status !== null) { $w[] = 'status = ?'; $a[] = $status; }
        if ($username !== null) { $w[] = 'username_lc = ?'; $a[] = mb_strtolower($username, 'UTF-8'); }
        $r = $this->run([['SELECT ' . self::PAY_COLS . ' FROM app_payments' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY created_at DESC LIMIT ' . max(1, min(200, $limit)), $a]])[0]['rows'];
        return array_map([self::class, 'payRow'], $r);
    }

    public function decidePayment(string $id, bool $accept, string $by, string $note): ?string {
        $now = gmdate('Y-m-d H:i:s');
        // Transition atomique submitted -> confirmed|rejected : une transaction n'est traitée qu'une seule fois.
        $r = $this->run([['UPDATE app_payments SET status = ?, decided_by = ?, decided_at = ?, confirmed_at = ?, note = ? WHERE id = ? AND status = ?',
            [$accept ? 'confirmed' : 'rejected', $by, $now, $accept ? $now : null, $note, $id, 'submitted']]])[0];
        if ($r['affected'] < 1) { return null; }
        if (!$accept) { return ''; }
        $p = $this->payment($id); if (!$p) { return null; }
        $cur = $this->subscriptionUntil($p['username']);
        $base = ($cur !== null && $cur > $now) ? $cur : $now;
        $until = (new \DateTimeImmutable($base, new \DateTimeZone('UTC')))->modify('+' . $p['days'] . ' days')->format('Y-m-d H:i:s');
        $this->run([['INSERT OR REPLACE INTO app_subscriptions (username_lc, paid_until, updated_at) VALUES (?,?,?)', [$p['username'], $until, $now]]]);
        return $until;
    }

    public function health(): array {
        $t = microtime(true); $v = bin2hex(random_bytes(6));
        $r = $this->run([
            ['CREATE TABLE IF NOT EXISTS app_meta (k TEXT PRIMARY KEY, v TEXT NOT NULL)', []],
            ['INSERT OR REPLACE INTO app_meta (k, v) VALUES (?, ?)', ['heartbeat', $v]],
            ['SELECT v FROM app_meta WHERE k = ?', ['heartbeat']],
            ['SELECT COUNT(*) FROM app_users', []],
        ]);
        return ['backend' => 'Turso (libSQL, base distante persistante)', 'host' => (string) parse_url($this->baseUrl, PHP_URL_HOST),
                'users' => (int) ($r[3]['rows'][0][0] ?? 0), 'roundtrip' => ($r[2]['rows'][0][0] ?? null) === $v, 'ms' => (int) round((microtime(true) - $t) * 1000)];
    }

    /**
     * Exécute des requêtes dans un seul aller-retour (le schéma est créé au besoin dans la même requête).
     * @param list<array{0:string,1:list<string>}> $stmts
     * @return list<array{rows:list<list<?string>>,affected:int}>
     */
    private function run(array $stmts): array {
        $reqs = [['type' => 'execute', 'stmt' => ['sql' => self::SCHEMA]]];
        $pre = 1; $tolerant = [];
        if (!self::$migrated) {                                  // schéma complet + migrations : une seule fois par instance
            foreach ([self::SCHEMA_SUBS, self::SCHEMA_PAY] as $q) { $reqs[] = ['type' => 'execute', 'stmt' => ['sql' => $q]]; }
            foreach (self::ALTERS as $q) { $tolerant[count($reqs)] = true; $reqs[] = ['type' => 'execute', 'stmt' => ['sql' => $q]]; }
            foreach (self::INDEXES as $q) { $reqs[] = ['type' => 'execute', 'stmt' => ['sql' => $q]]; }
            $pre = count($reqs);
        }
        foreach ($stmts as [$sql, $args]) {
            $reqs[] = ['type' => 'execute', 'stmt' => ['sql' => $sql, 'args' => array_map(static fn($a) => $a === null ? ['type' => 'null'] : ['type' => 'text', 'value' => (string) $a], $args)]];
        }
        $reqs[] = ['type' => 'close'];
        $ch = curl_init($this->baseUrl . '/v2/pipeline');
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->token, 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['requests' => $reqs], JSON_UNESCAPED_UNICODE),
        ]);
        $out = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch);
        if ($out === false) { throw new RuntimeException("Turso injoignable : $err"); }
        $j = json_decode((string) $out, true);
        if ($code >= 400 || !is_array($j) || !isset($j['results'])) {
            throw new RuntimeException("Turso HTTP $code : " . (is_array($j) ? ($j['error'] ?? json_encode($j)) : substr((string) $out, 0, 200)));
        }
        self::$migrated = true;
        $res = [];
        foreach ($j['results'] as $i => $r) {
            if (($r['type'] ?? '') === 'error') {
                $m = (string) ($r['error']['message'] ?? 'erreur');
                if (isset($tolerant[$i]) && stripos($m, 'duplicate column') !== false) { continue; }       // colonne déjà migrée
                throw new RuntimeException('Turso SQL : ' . $m);
            }
            if ($i < $pre || ($r['response']['type'] ?? '') !== 'execute') { continue; }       // 0 (et 1) = schéma / migration
            $x = $r['response']['result'] ?? [];
            $res[] = [
                'rows' => array_map(static fn(array $row) => array_map(static fn($c) => ($c['type'] ?? '') === 'null' ? null : (string) ($c['value'] ?? ''), $row), $x['rows'] ?? []),
                'affected' => (int) ($x['affected_row_count'] ?? 0),
            ];
        }
        return $res;
    }
}
