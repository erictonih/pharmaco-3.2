<?php

namespace App\Models;

use CodeIgniter\Model;

final class AdminUserModel extends Model
{
    protected $table = 'utilisateurs';
    protected $primaryKey = 'user_id';
    protected $returnType = 'array';
    protected $allowedFields = ['username', 'prenom', 'nom', 'email', 'role', 'statut', 'mdp', 'derniere_connexion'];

    public function getUsersPageData(string $search = '', string $role = ''): array
    {
        return ['stats' => $this->getStats(), 'users' => $this->getUsers($search, $role), 'search' => $search, 'selectedRole' => $role];
    }

    public function getStats(): array
    {
        $users = $this->db->table($this->table);
        return [
            'total' => $users->countAllResults(),
            'actifs' => $this->db->table($this->table)->where('statut', 'Actif')->countAllResults(),
            'inactifs' => $this->db->table($this->table)->where('statut', 'Inactif')->countAllResults(),
        ];
    }

    public function getUsers(string $search = '', string $role = ''): array
    {
        $builder = $this->select('user_id, username, prenom, nom, email, role, statut, derniere_connexion');
        if ($search !== '') $builder->groupStart()->like('username', $search)->orLike('nom', $search)->orLike('prenom', $search)->orLike('email', $search)->groupEnd();
        if ($role !== '') $builder->where('role', $role);
        return $builder->orderBy('user_id', 'DESC')->findAll();
    }

    public function createUser(array $data): bool { return $this->insert($data) !== false; }
    public function updateUser(int $id, array $data): bool { return $this->update($id, $data); }
    public function deleteUser(int $id): bool { return $this->delete($id); }

    public function toggleStatus(int $id): bool
    {
        $user = $this->find($id); if ($user === null) return false;
        return $this->update($id, ['statut' => $user['statut'] === 'Actif' ? 'Inactif' : 'Actif']);
    }
}
