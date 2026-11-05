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
        role TEXT NOT NULL, created_at TEXT NOT NULL, created_by TEXT NOT NULL DEFAULT \'\')';

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
        $rows = $this->run([['SELECT username, password_hash, role, created_at, created_by FROM app_users ORDER BY username_lc', []]])[0];
        return array_map(static fn(array $r) => ['username' => $r[0], 'hash' => $r[1], 'role' => $r[2], 'created_at' => $r[3], 'created_by' => $r[4]], $rows['rows']);
    }

    public function create(string $username, string $hash, string $role, string $createdBy): void {
        $this->run([[
            'INSERT INTO app_users (username, username_lc, password_hash, role, created_at, created_by) VALUES (?,?,?,?,?,?)',
            [$username, mb_strtolower($username, 'UTF-8'), $hash, $role, gmdate('Y-m-d H:i:s'), $createdBy],
        ]]);
    }

    public function delete(string $username): bool {
        $r = $this->run([['DELETE FROM app_users WHERE username_lc = ?', [mb_strtolower($username, 'UTF-8')]]])[0];
        return $r['affected'] > 0;
    }

    /**
     * Exécute des requêtes dans un seul aller-retour (le schéma est créé au besoin dans la même requête).
     * @param list<array{0:string,1:list<string>}> $stmts
     * @return list<array{rows:list<list<?string>>,affected:int}>
     */
    private function run(array $stmts): array {
        $reqs = [['type' => 'execute', 'stmt' => ['sql' => self::SCHEMA]]];
        foreach ($stmts as [$sql, $args]) {
            $reqs[] = ['type' => 'execute', 'stmt' => ['sql' => $sql, 'args' => array_map(static fn($a) => ['type' => 'text', 'value' => (string) $a], $args)]];
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
        $res = [];
        foreach ($j['results'] as $i => $r) {
            if (($r['type'] ?? '') === 'error') { throw new RuntimeException('Turso SQL : ' . ($r['error']['message'] ?? 'erreur')); }
            if ($i === 0 || ($r['response']['type'] ?? '') !== 'execute') { continue; }       // 0 = création du schéma
            $x = $r['response']['result'] ?? [];
            $res[] = [
                'rows' => array_map(static fn(array $row) => array_map(static fn($c) => ($c['type'] ?? '') === 'null' ? null : (string) ($c['value'] ?? ''), $row), $x['rows'] ?? []),
                'affected' => (int) ($x['affected_row_count'] ?? 0),
            ];
        }
        return $res;
    }
}
