<?php
declare(strict_types=1);
namespace Pharmaco\Users;

/** Stockage fichier JSON — développement local et tests uniquement (APP_ENV=local + USERS_FILE). Éphémère sur Vercel. */
final class FileUserStore implements UserStore
{
    public function __construct(private string $file) {}

    private function read(): array { $d = is_file($this->file) ? json_decode((string) file_get_contents($this->file), true) : []; return is_array($d) ? $d : []; }
    private function write(array $d): void { file_put_contents($this->file, json_encode(array_values($d), JSON_UNESCAPED_UNICODE), LOCK_EX); }

    public function all(): array { $d = $this->read(); usort($d, fn($a, $b) => strcasecmp($a['username'], $b['username'])); return $d; }

    public function create(string $username, string $hash, string $role, string $createdBy): void {
        $d = $this->read();
        foreach ($d as $u) { if (strcasecmp($u['username'], $username) === 0) { throw new \RuntimeException('UNIQUE constraint failed'); } }
        $d[] = ['username' => $username, 'hash' => $hash, 'role' => $role, 'created_at' => gmdate('Y-m-d H:i:s'), 'created_by' => $createdBy];
        $this->write($d);
    }

    public function delete(string $username): bool {
        $d = $this->read(); $n = count($d);
        $d = array_filter($d, fn($u) => strcasecmp($u['username'], $username) !== 0);
        $this->write($d);
        return count($d) < $n;
    }
}
