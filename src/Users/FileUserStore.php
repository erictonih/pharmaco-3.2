<?php
declare(strict_types=1);
namespace Pharmaco\Users;

/** Stockage fichier JSON — développement local et tests uniquement (APP_ENV=local + USERS_FILE). Éphémère sur Vercel. */
final class FileUserStore implements UserStore
{
    public function __construct(private string $file) {}

    private function read(): array { $d = is_file($this->file) ? json_decode((string) file_get_contents($this->file), true) : []; return is_array($d) ? $d : []; }
    private function write(array $d): void { file_put_contents($this->file, json_encode(array_values($d), JSON_UNESCAPED_UNICODE), LOCK_EX); }

    public function all(): array {
        $now = gmdate('Y-m-d H:i:s'); $d = array_values(array_filter($this->read(), fn($u) => empty($u['expires_at']) || $u['expires_at'] > $now));
        if (count($d) !== count($this->read())) { $this->write($d); }
        usort($d, fn($a, $b) => strcasecmp($a['username'], $b['username'])); return array_map(fn($u) => $u + ['expires_at' => null], $d); }

    public function create(string $username, string $hash, string $role, string $createdBy, ?string $expiresAt = null): void {
        $d = $this->read();
        foreach ($d as $u) { if (strcasecmp($u['username'], $username) === 0) { throw new \RuntimeException('UNIQUE constraint failed'); } }
        $d[] = ['username' => $username, 'hash' => $hash, 'role' => $role, 'created_at' => gmdate('Y-m-d H:i:s'), 'created_by' => $createdBy, 'expires_at' => $expiresAt];
        $this->write($d);
    }

    public function delete(string $username): bool {
        $d = $this->read(); $n = count($d);
        $d = array_filter($d, fn($u) => strcasecmp($u['username'], $username) !== 0);
        $this->write($d);
        return count($d) < $n;
    }

    public function health(): array {
        $t = microtime(true); $probe = $this->file . '.probe'; $v = bin2hex(random_bytes(4));
        $ok = @file_put_contents($probe, $v) !== false && @file_get_contents($probe) === $v; @unlink($probe);
        return ['backend' => 'Fichier local (développement)', 'host' => basename($this->file), 'users' => count($this->read()), 'roundtrip' => $ok, 'ms' => (int) round((microtime(true) - $t) * 1000)];
    }
}
