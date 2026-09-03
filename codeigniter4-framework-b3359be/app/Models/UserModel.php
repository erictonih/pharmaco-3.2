<?php

namespace App\Models;

use CodeIgniter\Model;

final class UserModel extends Model
{
    protected $table = 'utilisateurs';
    protected $primaryKey = 'user_id';
    protected $returnType = 'array';
    protected $allowedFields = ['username', 'mdp', 'prenom', 'nom', 'email', 'role', 'statut', 'derniere_connexion'];

    public function findActiveByUsername(string $username): ?array
    {
        return $this->select('user_id, username, mdp, statut, role')->where('username', $username)->where('statut', 'Actif')->first() ?: null;
    }

    public function verifyCredentials(string $username, string $password): ?array
    {
        $user = $this->findActiveByUsername($username);
        if ($user === null || (! password_verify($password, $user['mdp']) && ! hash_equals($user['mdp'], $password))) return null;
        return $user;
    }

    public function recordLogin(int $id): bool
    {
        return $this->update($id, ['derniere_connexion' => date('Y-m-d H:i:s')]);
    }
}
