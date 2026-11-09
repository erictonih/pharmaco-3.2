<?php
declare(strict_types=1);
namespace Pharmaco\Billing;

use Pharmaco\Users\{UserService, UserStore};

/**
 * Module payant VIP : abonnement à durée (VIP_DAYS jours) payé via une passerelle.
 * Passerelle actuelle : SIMULATION (PAYMENT_MODE=simulation, défaut) — aucun débit réel. La confirmation passe par
 * confirm(), le point d'entrée à appeler depuis le webhook d'un vrai prestataire (Stripe, Mobile Money…).
 * Les transactions et abonnements sont persistés dans Turso (tables app_payments / app_subscriptions).
 */
final class PaymentService
{
    public const PENDING_TTL = 1800;                          // une transaction non confirmée expire après 30 min

    public static function mode(): string { return strtolower((string) env_get('PAYMENT_MODE', 'simulation')); }
    public static function simulated(): bool { return self::mode() === 'simulation'; }
    public static function price(): int { return max(1, (int) env_get('VIP_PRICE', '50000')); }
    public static function currency(): string { return preg_replace('/[^A-Z]/', '', strtoupper((string) env_get('VIP_CURRENCY', 'MGA'))) ?: 'MGA'; }
    public static function days(): int { return min(366, max(1, (int) env_get('VIP_DAYS', '30'))); }

    private static function store(): ?UserStore { return UserService::store(); }
    private static function cacheFile(string $u): string { return cache_dir() . '/sub_' . md5(mb_strtolower($u, 'UTF-8')) . '.json'; }

    /** Abonnement actif ? Contrôle mis en cache 30 s ; en cas d'indisponibilité de la base : refus (jamais d'accès gratuit). */
    public static function isActive(string $username): bool {
        $f = self::cacheFile($username);
        $c = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
        if (is_array($c) && (time() - (int) ($c['t'] ?? 0)) < 30) { return (bool) $c['ok']; }
        $st = self::store(); if (!$st) { return false; }
        try { $until = $st->subscriptionUntil($username); }
        catch (\Throwable $e) { error_log('[pharmaco][billing] lecture abonnement : ' . $e->getMessage()); return false; }
        $ok = $until !== null && $until > gmdate('Y-m-d H:i:s');
        @file_put_contents($f, json_encode(['t' => time(), 'ok' => $ok]), LOCK_EX);
        return $ok;
    }

    /** @return array{active:bool,until:?string,error:?string} */
    public static function status(string $username): array {
        $st = self::store(); if (!$st) { return ['active' => false, 'until' => null, 'error' => 'Base de données non configurée.']; }
        try { $u = $st->subscriptionUntil($username); }
        catch (\Throwable $e) { error_log('[pharmaco][billing] statut : ' . $e->getMessage()); return ['active' => false, 'until' => null, 'error' => 'Vérification de l’abonnement impossible pour le moment.']; }
        return ['active' => $u !== null && $u > gmdate('Y-m-d H:i:s'), 'until' => $u, 'error' => null];
    }

    /** Crée une transaction en attente. @return array{id:?string,error:?string} */
    public static function start(string $username): array {
        if (!self::simulated()) { return ['id' => null, 'error' => 'Passerelle de paiement non configurée (PAYMENT_MODE).']; }
        $st = self::store(); if (!$st) { return ['id' => null, 'error' => 'Base de données non configurée.']; }
        $id = bin2hex(random_bytes(16));
        try { $st->createPayment($id, $username, self::price(), self::currency(), self::days()); }
        catch (\Throwable $e) { error_log('[pharmaco][billing] création : ' . $e->getMessage()); return ['id' => null, 'error' => 'Création de la transaction impossible (base indisponible).']; }
        error_log("[pharmaco][billing] transaction $id créée pour « $username »");
        return ['id' => $id, 'error' => null];
    }

    /** Transaction en attente appartenant à cet utilisateur et non expirée. */
    public static function pending(string $username, string $id): ?array {
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) { return null; }
        $st = self::store(); if (!$st) { return null; }
        try { $p = $st->payment($id); } catch (\Throwable $e) { return null; }
        if (!$p || $p['status'] !== 'pending' || strcasecmp($p['username'], $username) !== 0) { return null; }
        if (strtotime($p['created_at'] . ' UTC') < time() - self::PENDING_TTL) { return null; }
        return $p;
    }

    /** Confirme la transaction (idempotent). @return array{until:?string,error:?string} */
    public static function confirm(string $username, string $id): array {
        $p = self::pending($username, $id);
        if (!$p) { return ['until' => null, 'error' => 'Transaction introuvable, déjà traitée ou expirée.']; }
        $st = self::store();
        try { $until = $st->confirmPayment($id); }
        catch (\Throwable $e) { error_log('[pharmaco][billing] confirmation : ' . $e->getMessage()); return ['until' => null, 'error' => 'Confirmation impossible (base indisponible). Rien n’a été débité.']; }
        if ($until === null) { return ['until' => null, 'error' => 'Transaction déjà traitée.']; }
        @unlink(self::cacheFile($username));
        error_log("[pharmaco][billing] transaction $id confirmée pour « $username », abonnement jusqu'au $until UTC");
        return ['until' => $until, 'error' => null];
    }

    public static function cancel(string $username, string $id): void {
        if (self::pending($username, $id)) { try { self::store()->cancelPayment($id); } catch (\Throwable $e) { /* sans effet */ } }
    }
}
