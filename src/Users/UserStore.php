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
    public function create(string $username, string $hash, string $role, string $createdBy, ?string $expiresAt = null, ?string $createdAt = null): void;
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
    /** Enregistre la référence MVola déclarée par l'utilisateur (statut « submitted »). Lève une exception si la référence existe déjà. */
    public function submitPayment(string $id, string $username, int $amount, string $currency, int $days, string $reference, string $sender): void;
    /** @return ?array{id:string,username:string,amount:int,currency:string,days:int,status:string,reference:string,sender:string,created_at:string,decided_by:string,decided_at:string,note:string} */
    public function payment(string $id): ?array;
    /** Transactions, plus récentes d'abord. @return list<array> */
    public function payments(?string $status = null, ?string $username = null, int $limit = 50): array;
    /** Valide ou rejette une transaction « submitted » (une seule fois) ; si validée, prolonge l'abonnement.
     *  @return ?string nouvelle fin d'abonnement UTC (validation), '' (rejet), null si déjà traitée */
    public function decidePayment(string $id, bool $accept, string $by, string $note): ?string;

    // ── Sauvegarde / restauration (fusion non destructive) ──
    /** Fixe la fin d'abonnement à max(valeur actuelle, $until). */
    public function setSubscriptionMax(string $usernameLc, string $until): void;
    /** Réinsère une transaction si ni son identifiant ni sa référence n'existent. @return bool true si ajoutée */
    public function restorePayment(array $p): bool;
    /** Enregistre un instantané JSON et n'en garde que les $keep derniers. */
    public function saveSnapshot(string $json, string $by, int $users, int $payments, int $keep = 14): void;
    /** @return list<array{id:string,created_at:string,created_by:string,users:int,payments:int}> */
    public function snapshots(): array;
    public function snapshot(string $id): ?string;
}
