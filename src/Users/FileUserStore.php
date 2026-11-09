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

    // ── Abonnement VIP : fichiers JSON annexes (développement/tests uniquement) ──
    private function aux(string $n): array { $f = $this->file . '.' . $n; $d = is_file($f) ? json_decode((string) file_get_contents($f), true) : []; return is_array($d) ? $d : []; }
    private function putAux(string $n, array $d): void { file_put_contents($this->file . '.' . $n, json_encode($d), LOCK_EX); }
    public function subscriptionUntil(string $username): ?string { return $this->aux('subs')[mb_strtolower($username, 'UTF-8')] ?? null; }
    public function subscriptions(): array { return $this->aux('subs'); }
    public function createPayment(string $id, string $username, int $amount, string $currency, int $days): void {
        $p = $this->aux('pay'); $p[$id] = ['id' => $id, 'username' => mb_strtolower($username, 'UTF-8'), 'amount' => $amount, 'currency' => $currency, 'days' => $days, 'status' => 'pending', 'created_at' => gmdate('Y-m-d H:i:s')]; $this->putAux('pay', $p);
    }
    public function payment(string $id): ?array { return $this->aux('pay')[$id] ?? null; }
    public function confirmPayment(string $id): ?string {
        $p = $this->aux('pay'); if (($p[$id]['status'] ?? '') !== 'pending') { return null; }
        $p[$id]['status'] = 'confirmed'; $this->putAux('pay', $p);
        $now = gmdate('Y-m-d H:i:s'); $s = $this->aux('subs'); $cur = $s[$p[$id]['username']] ?? null; $base = ($cur && $cur > $now) ? $cur : $now;
        $s[$p[$id]['username']] = (new \DateTimeImmutable($base, new \DateTimeZone('UTC')))->modify('+' . $p[$id]['days'] . ' days')->format('Y-m-d H:i:s'); $this->putAux('subs', $s);
        return $s[$p[$id]['username']];
    }
    public function cancelPayment(string $id): void { $p = $this->aux('pay'); if (($p[$id]['status'] ?? '') === 'pending') { $p[$id]['status'] = 'cancelled'; $this->putAux('pay', $p); } }

    public function health(): array {
        $t = microtime(true); $probe = $this->file . '.probe'; $v = bin2hex(random_bytes(4));
        $ok = @file_put_contents($probe, $v) !== false && @file_get_contents($probe) === $v; @unlink($probe);
        return ['backend' => 'Fichier local (développement)', 'host' => basename($this->file), 'users' => count($this->read()), 'roundtrip' => $ok, 'ms' => (int) round((microtime(true) - $t) * 1000)];
    }
}
