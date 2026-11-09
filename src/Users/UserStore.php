<?php
declare(strict_types=1);
namespace Pharmaco\Users;

/**
 * Stockage des comptes de la base de données. Les mots de passe ne sont jamais stockés qu'en hash (password_hash).
 * expires_at : date d'expiration en UTC (« Y-m-d H:i:s ») pour un compte limité, null pour un compte permanent.
 */
interface UserStore
{
    /** Supprime d'abord les comptes expirés, puis renvoie les comptes restants.
     *  @return list<array{username:string,hash:string,role:string,created_at:string,created_by:string,expires_at:?string}> */
    public function all(): array;
    public function create(string $username, string $hash, string $role, string $createdBy, ?string $expiresAt = null): void;
    /** @return bool true si un compte a été supprimé */
    public function delete(string $username): bool;
    /** Test d'écriture/lecture réel sur le stockage (diagnostic de persistance).
     *  @return array{backend:string,host:string,users:int,roundtrip:bool,ms:int} */
    public function health(): array;

    // ── Abonnement VIP (paiements) ──
    /** Date de fin d'abonnement (UTC « Y-m-d H:i:s ») ou null. */
    public function subscriptionUntil(string $username): ?string;
    /** @return array<string,string> identifiant (minuscules) => fin d'abonnement UTC */
    public function subscriptions(): array;
    public function createPayment(string $id, string $username, int $amount, string $currency, int $days): void;
    /** @return ?array{id:string,username:string,amount:int,currency:string,days:int,status:string,created_at:string} */
    public function payment(string $id): ?array;
    /** Confirme une transaction « pending » une seule fois (idempotent) et prolonge l'abonnement. @return ?string nouvelle fin UTC, null si déjà traitée */
    public function confirmPayment(string $id): ?string;
    public function cancelPayment(string $id): void;
}
