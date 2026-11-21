<?php
declare(strict_types=1);
namespace Pharmaco\Users;

use Pharmaco\Auth\Auth;

/**
 * Sauvegarde / restauration des données applicatives (comptes, abonnements, transactions MVola).
 * La restauration est une FUSION non destructive : elle ajoute ce qui manque, ne supprime ni n'écrase jamais rien
 * (un abonnement n'est jamais raccourci). Les sauvegardes contiennent les empreintes de mots de passe : à conserver en lieu sûr.
 */
final class BackupService
{
    public const VERSION = 1;
    private const MAX_BYTES = 4_000_000;

    /** @return array<string,mixed> */
    public static function export(UserStore $st): array {
        return [
            'format' => 'pharmaco-backup', 'version' => self::VERSION, 'exported_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'users' => $st->all(), 'subscriptions' => $st->subscriptions(), 'payments' => $st->payments(null, null, 5000),
        ];
    }

    /** Sauvegarde automatique (cron) ou manuelle dans la base. @return array{users:int,payments:int,subscriptions:int} */
    public static function snapshot(UserStore $st, string $by): array {
        $d = self::export($st); $json = (string) json_encode($d, JSON_UNESCAPED_UNICODE);
        $c = ['users' => count($d['users']), 'payments' => count($d['payments']), 'subscriptions' => count($d['subscriptions'])];
        $st->saveSnapshot($json, $by, $c['users'], $c['payments']);
        return $c;
    }

    /**
     * Restaure (fusion). Une sauvegarde de sécurité de l'état actuel est faite avant toute écriture.
     * @return array{error:?string,users:int,subscriptions:int,payments:int,skipped:int}
     */
    public static function import(UserStore $st, string $json, string $by): array {
        $r = ['error' => null, 'users' => 0, 'subscriptions' => 0, 'payments' => 0, 'skipped' => 0];
        if (strlen($json) > self::MAX_BYTES) { $r['error'] = 'Fichier trop volumineux.'; return $r; }
        $d = json_decode($json, true);
        if (!is_array($d) || ($d['format'] ?? '') !== 'pharmaco-backup' || (int) ($d['version'] ?? 0) !== self::VERSION
            || !is_array($d['users'] ?? null) || !is_array($d['subscriptions'] ?? null) || !is_array($d['payments'] ?? null)) { $r['error'] = 'Fichier de sauvegarde invalide.'; return $r; }
        $dt = static fn($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $v) === 1;
        $users = []; $subs = []; $pays = [];
        foreach ($d['users'] as $u) {
            if (!is_array($u) || !is_string($u['username'] ?? null) || !preg_match('/^[A-Za-z0-9._-]{3,40}$/', $u['username'])
                || !in_array($u['role'] ?? '', Auth::ROLES, true) || !is_string($u['hash'] ?? null) || !preg_match('/^\$(2y|2a|2b|argon2i|argon2id)\$/', $u['hash'])
                || (($u['expires_at'] ?? null) !== null && !$dt($u['expires_at'])) || !$dt($u['created_at'] ?? null)) { $r['error'] = 'Fichier invalide : compte mal formé.'; return $r; }
            $users[] = $u;
        }
        foreach ($d['subscriptions'] as $k => $v) {
            if (!is_string($k) || !preg_match('/^[a-z0-9._-]{3,40}$/', $k) || !$dt($v)) { $r['error'] = 'Fichier invalide : abonnement mal formé.'; return $r; }
            $subs[$k] = $v;
        }
        foreach ($d['payments'] as $p) {
            if (!is_array($p) || !is_string($p['id'] ?? null) || !preg_match('/^[a-f0-9]{32}$/', $p['id']) || !is_string($p['username'] ?? null) || !is_int($p['amount'] ?? null) || !is_int($p['days'] ?? null)
                || !in_array($p['status'] ?? '', ['submitted', 'confirmed', 'rejected'], true) || !$dt($p['created_at'] ?? null) || !is_string($p['currency'] ?? null) || !preg_match('/^[A-Z]{3}$/', $p['currency'])) { $r['error'] = 'Fichier invalide : transaction mal formée.'; return $r; }
            $pays[] = ['id' => $p['id'], 'username' => mb_strtolower($p['username'], 'UTF-8'), 'amount' => $p['amount'], 'currency' => $p['currency'], 'days' => $p['days'], 'status' => $p['status'],
                       'reference' => is_string($p['reference'] ?? null) ? $p['reference'] : '', 'sender' => (string) ($p['sender'] ?? ''), 'created_at' => $p['created_at'],
                       'decided_by' => (string) ($p['decided_by'] ?? ''), 'decided_at' => (string) ($p['decided_at'] ?? ''), 'note' => (string) ($p['note'] ?? '')];
        }
        try {
            self::snapshot($st, $by . ' (avant restauration)');
            $have = []; foreach ($st->all() as $u) { $have[mb_strtolower($u['username'], 'UTF-8')] = true; }
            foreach (array_keys(Auth::envUsers()) as $k) { $have[$k] = true; }          // comptes de secours (APP_USERS_JSON) : jamais dupliqués en base
            foreach ($users as $u) {
                if (isset($have[mb_strtolower($u['username'], 'UTF-8')])) { $r['skipped']++; continue; }
                if (($u['expires_at'] ?? null) !== null && $u['expires_at'] <= gmdate('Y-m-d H:i:s')) { $r['skipped']++; continue; }      // compte limité déjà expiré
                $st->create($u['username'], $u['hash'], $u['role'], (string) ($u['created_by'] ?? ''), $u['expires_at'] ?? null, $u['created_at']); $r['users']++;
            }
            foreach ($subs as $k => $v) { $st->setSubscriptionMax($k, $v); $r['subscriptions']++; }
            foreach ($pays as $p) { if ($st->restorePayment($p)) { $r['payments']++; } else { $r['skipped']++; } }
        } catch (\Throwable $e) { error_log('[pharmaco][backup] restauration : ' . $e->getMessage()); $r['error'] = 'Restauration interrompue (base indisponible) : relancez-la, la fusion est sans risque.'; }
        error_log("[pharmaco][backup] $by a restauré : {$r['users']} compte(s), {$r['subscriptions']} abonnement(s), {$r['payments']} transaction(s)");
        return $r;
    }
}
