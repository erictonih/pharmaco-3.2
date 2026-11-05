<?php
declare(strict_types=1);
namespace Pharmaco\Users;

/** Stockage des comptes de la base de données. Les mots de passe ne sont jamais stockés qu'en hash (password_hash). */
interface UserStore
{
    /** @return list<array{username:string,hash:string,role:string,created_at:string,created_by:string}> */
    public function all(): array;
    public function create(string $username, string $hash, string $role, string $createdBy): void;
    /** @return bool true si un compte a été supprimé */
    public function delete(string $username): bool;
}
