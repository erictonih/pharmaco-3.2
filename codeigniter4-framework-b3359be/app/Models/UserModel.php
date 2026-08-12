<?php

namespace App\Models;

use App\Libraries\PdoConnection;

final class UserModel
{
    public function findByUsername(string $username): ?array
    {
        $statement = PdoConnection::get()->prepare('SELECT user_id, username, mdp, statut FROM utilisateurs WHERE username = :username LIMIT 1');
        $statement->execute(['username' => $username]);
        return $statement->fetch() ?: null;
    }

    public function markConnected(int $id): void
    {
        $statement = PdoConnection::get()->prepare('UPDATE utilisateurs SET derniere_connexion = NOW() WHERE user_id = :id');
        $statement->execute(['id' => $id]);
    }
}
