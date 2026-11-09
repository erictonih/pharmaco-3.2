<?php
declare(strict_types=1);
namespace Pharmaco\Billing;

use Pharmaco\Users\{UserService, UserStore};

/**
 * Module payant VIP : abonnement à durée (VIP_DAYS jours) payé par virement MVola, SANS branchement au prestataire.
 * Circuit : l'utilisateur effectue le virement vers MVOLA_NUMBER puis déclare la référence de la transaction MVola
 * (statut « submitted ») ; un Super Admin vérifie le virement (page paiements.php) et valide ou rejette. À la validation,
 * l'abonnement est prolongé et l'accès à tous les modules est ouvert immédiatement. Tout est persisté dans Turso.
 */
final class PaymentService
{
    public static function number(): string { return trim((string) env_get('MVOLA_NUMBER', '')); }
    public static function holder(): string { return trim((string) env_get('MVOLA_NAME', '')); }
    public static function price(): int { return max(1, (int) env_get('VIP_PRICE', '50000')); }
    public static function currency(): string { return preg_replace('/[^A-Z]/', '', strtoupper((string) env_get('VIP_CURRENCY', 'MGA'))) ?: 'MGA'; }
    public static function days(): int { return min(366, max(1, (int) env_get('VIP_DAYS', '30'))); }

    private static function store(): ?UserStore { return UserService::store(); }
    private static function cacheFile(string $u): string { return cache_dir() . '/sub_' . md5(mb_strtolower($u, 'UTF-8')) . '.json'; }

    /** Abonnement actif ? Mis en cache (30 s si actif, 5 s sinon : l'accès s'ouvre vite après validation). Base indisponible : refus. */
    public static function isActive(string $username): bool {
        $f = self::cacheFile($username);
        $c = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
        if (is_array($c) && (time() - (int) ($c['t'] ?? 0)) < (!empty($c['ok']) ? 30 : 5)) { return (bool) $c['ok']; }
        $st = self::store(); if (!$st) { return false; }
        try { $until = $st->subscriptionUntil($username); }
        catch (\Throwable $e) { error_log('[pharmaco][billing] lecture abonnement : ' . $e->getMessage()); return false; }
        $ok = $until !== null && $until > gmdate('Y-m-d H:i:s');
        @file_put_contents($f, json_encode(['t' => time(), 'ok' => $ok]), LOCK_EX);
        return $ok;
    }

    /** @return array{active:bool,until:?string,waiting:?array,history:list<array>,error:?string} */
    public static function status(string $username): array {
        $o = ['active' => false, 'until' => null, 'waiting' => null, 'history' => [], 'error' => null];
        $st = self::store(); if (!$st) { $o['error'] = 'Base de données non configurée.'; return $o; }
        try {
            $u = $st->subscriptionUntil($username);
            $o['active'] = $u !== null && $u > gmdate('Y-m-d H:i:s'); $o['until'] = $u;
            $o['history'] = $st->payments(null, $username, 5);
            foreach ($o['history'] as $p) { if ($p['status'] === 'submitted') { $o['waiting'] = $p; break; } }
        } catch (\Throwable $e) { error_log('[pharmaco][billing] statut : ' . $e->getMessage()); $o['error'] = 'Vérification de l’abonnement impossible pour le moment.'; }
        return $o;
    }

    /** Normalise la référence MVola saisie (majuscules). @return array{0:?string,1:?string} [référence, erreur] */
    public static function cleanReference(string $ref): array {
        $ref = strtoupper(trim($ref));
        if (!preg_match('/^[A-Z0-9][A-Z0-9._-]{5,39}$/', $ref)) { return [null, 'Référence invalide : 6 à 40 caractères (lettres, chiffres, . _ -).']; }
        return [$ref, null];
    }

    /** Déclare un virement MVola. @return string|null message d'erreur */
    public static function submit(string $username, string $reference, string $sender): ?string {
        if (self::number() === '') { return 'Le numéro MVola de réception n’est pas configuré : contactez l’administrateur.'; }
        [$ref, $e] = self::cleanReference($reference); if ($e) { return $e; }
        $sender = trim($sender);
        if ($sender !== '' && !preg_match('/^\+?[0-9 ]{6,20}$/', $sender)) { return 'Numéro MVola de l’expéditeur invalide.'; }
        $st = self::store(); if (!$st) { return 'Base de données non configurée.'; }
        try {
            foreach ($st->payments('submitted', $username, 5) as $x) { return 'Une transaction est déjà en attente de validation.'; }
            $st->submitPayment(bin2hex(random_bytes(16)), $username, self::price(), self::currency(), self::days(), $ref, $sender);
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'UNIQUE') !== false) { return 'Cette référence de transaction a déjà été déclarée.'; }
            error_log('[pharmaco][billing] déclaration : ' . $e->getMessage()); return 'Enregistrement impossible (base indisponible). Réessayez.';
        }
        @unlink(cache_dir() . '/pay_pending.json');
        error_log("[pharmaco][billing] « $username » a déclaré la référence $ref");
        return null;
    }

    /** Validation / rejet par un Super Admin. @return array{until:?string,error:?string} */
    public static function decide(string $admin, string $id, bool $accept, string $note = ''): array {
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) { return ['until' => null, 'error' => 'Transaction invalide.']; }
        $st = self::store(); if (!$st) { return ['until' => null, 'error' => 'Base de données non configurée.']; }
        try {
            $p = $st->payment($id);
            $r = $st->decidePayment($id, $accept, $admin, mb_substr(trim($note), 0, 200));
        } catch (\Throwable $e) { error_log('[pharmaco][billing] décision : ' . $e->getMessage()); return ['until' => null, 'error' => 'Opération impossible (base indisponible).']; }
        if ($r === null || !$p) { return ['until' => null, 'error' => 'Transaction introuvable ou déjà traitée.']; }
        @unlink(self::cacheFile($p['username'])); @unlink(cache_dir() . '/pay_pending.json');
        error_log("[pharmaco][billing] $admin a " . ($accept ? 'validé' : 'rejeté') . " la transaction {$p['reference']} de « {$p['username']} »");
        return ['until' => $r === '' ? null : $r, 'error' => null];
    }

    /** @return list<array> transactions en attente de validation (plus anciennes d'abord) */
    public static function pendingList(): array {
        $st = self::store(); if (!$st) { return []; }
        return array_reverse($st->payments('submitted', null, 100));
    }
    public static function pendingCount(): int {
        $f = cache_dir() . '/pay_pending.json'; $c = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
        if (is_array($c) && (time() - (int) ($c['t'] ?? 0)) < 15) { return (int) $c['n']; }
        try { $n = count(self::pendingList()); } catch (\Throwable $e) { return 0; }
        @file_put_contents($f, json_encode(['t' => time(), 'n' => $n]), LOCK_EX); return $n;
    }
    /** @return list<array> */
    public static function recent(int $n = 50): array { $st = self::store(); return $st ? $st->payments(null, null, $n) : []; }
}
