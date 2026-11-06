<?php
declare(strict_types=1);
namespace Pharmaco\Users;

use Pharmaco\Auth\Auth;

/**
 * Gestion des comptes : fusionne la base de données (Turso) et les comptes « de secours » de APP_USERS_JSON.
 * Les comptes de APP_USERS_JSON ne sont ni modifiables ni supprimables depuis la page (ils garantissent un accès
 * administrateur même si la base est indisponible) ; en cas de doublon, APP_USERS_JSON l'emporte.
 */
final class UserService
{
    public const MIN_PASSWORD = 12;
    public const TYPES = ['permanent' => 'Permanent', 'limite' => 'Limité (suppression automatique à l’expiration)'];
    public const TZ = 'Indian/Antananarivo';                // heure saisie / affichée pour les expirations (UTC+3)

    public static function store(): ?UserStore {
        if (env_get('APP_ENV') === 'local' && ($f = env_get('USERS_FILE'))) { return new FileUserStore($f); }
        return TursoUserStore::fromEnv();
    }

    /** @return array{users:list<array>,db:bool,error:?string} users: username, role, source (db|env), created_at, created_by, hash */
    public static function listAll(): array {
        $out = []; $error = null; $store = self::store();
        if ($store) {
            try {
                $now = gmdate('Y-m-d H:i:s');
                foreach ($store->all() as $u) {
                    if (!empty($u['expires_at']) && $u['expires_at'] <= $now) { continue; }          // filet de sécurité : jamais d'accès après expiration
                    $out[mb_strtolower($u['username'], 'UTF-8')] = $u + ['source' => 'db'];
                }
            }
            catch (\Throwable $e) { $error = 'Base de données des utilisateurs injoignable.'; error_log('[pharmaco][users] ' . $e->getMessage()); }
        }
        foreach (Auth::envUsers() as $key => $u) {
            $out[$key] = ['username' => $u['name'], 'hash' => $u['hash'], 'role' => $u['role'], 'created_at' => '', 'created_by' => '', 'expires_at' => null, 'source' => 'env'];
        }
        uasort($out, fn($a, $b) => strcasecmp($a['username'], $b['username']));
        return ['users' => array_values($out), 'db' => $store !== null && $error === null, 'error' => $error, 'configured' => $store !== null];
    }

    /** Comptes utilisables pour la connexion (clé = identifiant en minuscules). */
    public static function credentials(): array {
        $r = self::listAll(); $out = [];
        foreach ($r['users'] as $u) { $out[mb_strtolower($u['username'], 'UTF-8')] = ['hash' => $u['hash'], 'role' => $u['role'], 'name' => $u['username']]; }
        return $out;
    }

    /** @return string|null message d'erreur */
    public static function validate(string $username, string $role, string $password, string $confirm): ?string {
        if (!preg_match('/^[A-Za-z0-9._@-]{3,50}$/', $username)) { return 'Identifiant invalide : 3 à 50 caractères (lettres, chiffres, . _ - @).'; }
        if (!in_array($role, Auth::ROLES, true)) { return 'Rôle invalide.'; }
        if (strlen($password) < self::MIN_PASSWORD) { return 'Mot de passe trop court (' . self::MIN_PASSWORD . ' caractères minimum).'; }
        if (strlen($password) > 1024) { return 'Mot de passe trop long.'; }
        if (!hash_equals($password, $confirm)) { return 'La confirmation du mot de passe ne correspond pas.'; }
        return null;
    }

    /** Convertit « 2026-12-31T17:30 » (heure de Madagascar) en UTC « Y-m-d H:i:s ». @return array{0:?string,1:?string} [utc, erreur] */
    public static function expiryUtc(string $type, string $local): array {
        if ($type === 'permanent') { return [null, null]; }
        if ($type !== 'limite') { return [null, 'Type de compte invalide.']; }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', trim($local), new \DateTimeZone(self::TZ));
        if (!$d) { return [null, 'Date d’expiration invalide.']; }
        $utc = $d->setTimezone(new \DateTimeZone('UTC'));
        if ($utc->getTimestamp() < time() + 60) { return [null, 'La date d’expiration doit être dans le futur.']; }
        if ($utc->getTimestamp() > time() + 5 * 366 * 86400) { return [null, 'Date d’expiration trop lointaine (5 ans maximum) : créez plutôt un compte permanent.']; }
        return [$utc->format('Y-m-d H:i:s'), null];
    }

    /** Affiche une date UTC de la base en heure de Madagascar. */
    public static function localTime(?string $utc): string {
        if (!$utc) { return '—'; }
        return (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone(self::TZ))->format('d/m/Y H:i');
    }

    /** @return string|null message d'erreur */
    public static function create(string $actor, string $username, string $role, string $password, string $confirm, string $type = 'permanent', string $expiresLocal = ''): ?string {
        if ($e = self::validate($username, $role, $password, $confirm)) { return $e; }
        [$expires, $e] = self::expiryUtc($type, $expiresLocal);
        if ($e) { return $e; }
        $store = self::store();
        if (!$store) { return 'Base de données des utilisateurs non configurée (TURSO_DATABASE_URL / TURSO_AUTH_TOKEN).'; }
        $all = self::listAll();
        if ($all['error']) { return $all['error']; }
        foreach ($all['users'] as $u) { if (strcasecmp($u['username'], $username) === 0) { return 'Cet identifiant existe déjà.'; } }
        try { $store->create($username, password_hash($password, PASSWORD_DEFAULT), $role, $actor, $expires); }
        catch (\Throwable $e) { error_log('[pharmaco][users] création : ' . $e->getMessage()); return 'Création impossible (identifiant déjà utilisé ou base indisponible).'; }
        // Relecture : on ne confirme la création que si le compte est réellement retrouvé dans le stockage persistant.
        $found = false;
        try { foreach ($store->all() as $u) { if (strcasecmp($u['username'], $username) === 0) { $found = true; } } } catch (\Throwable $e) { error_log('[pharmaco][users] relecture : ' . $e->getMessage()); }
        if (!$found) { error_log("[pharmaco][users] ALERTE : compte « $username » non retrouvé après écriture"); return 'Le compte n’a pas été enregistré durablement dans la base : réessayez ou lancez le test de persistance.'; }
        error_log("[pharmaco][users] $actor a créé le compte « $username » ($role" . ($expires ? ", expire $expires UTC" : ', permanent') . ')');
        return null;
    }

    /** @return string|null message d'erreur */
    public static function delete(string $actor, string $username): ?string {
        $store = self::store();
        if (!$store) { return 'Base de données des utilisateurs non configurée.'; }
        if (strcasecmp($actor, $username) === 0) { return 'Vous ne pouvez pas supprimer votre propre compte.'; }
        $all = self::listAll();
        if ($all['error']) { return $all['error']; }
        $target = null; $admins = 0;
        foreach ($all['users'] as $u) {
            if ($u['role'] === 'Super Admin') { $admins++; }
            if (strcasecmp($u['username'], $username) === 0) { $target = $u; }
        }
        if (!$target) { return 'Utilisateur introuvable.'; }
        if ($target['source'] === 'env') { return 'Ce compte est défini dans la configuration du serveur (APP_USERS_JSON) : il ne peut pas être supprimé ici.'; }
        if ($target['role'] === 'Super Admin' && $admins <= 1) { return 'Impossible de supprimer le dernier Super Admin.'; }
        try { $ok = $store->delete($username); }
        catch (\Throwable $e) { error_log('[pharmaco][users] suppression : ' . $e->getMessage()); return 'Suppression impossible (base indisponible).'; }
        @unlink(self::cacheFile($username));
        if (!$ok) { return 'Utilisateur introuvable.'; }
        error_log("[pharmaco][users] $actor a supprimé le compte « $username »");
        return null;
    }

    private static function cacheFile(string $u): string { return cache_dir() . '/uv_' . md5(mb_strtolower($u, 'UTF-8')) . '.json'; }

    /**
     * Révocation : un compte supprimé perd son accès en ≤ 30 s (contrôle mis en cache par instance).
     * Si la base est injoignable, on ne coupe pas les sessions valides (disponibilité).
     */
    public static function stillValid(string $username): bool {
        if (isset(Auth::envUsers()[mb_strtolower($username, 'UTF-8')])) { return true; }
        $store = self::store();
        if (!$store) { return true; }
        $f = self::cacheFile($username);
        $c = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
        if (is_array($c) && (time() - (int) ($c['t'] ?? 0)) < 30) { return (bool) $c['ok']; }
        $r = self::listAll();
        if ($r['error']) { return true; }
        $ok = false; foreach ($r['users'] as $u) { if (strcasecmp($u['username'], $username) === 0) { $ok = true; } }
        @file_put_contents($f, json_encode(['t' => time(), 'ok' => $ok]), LOCK_EX);
        return $ok;
    }
}
