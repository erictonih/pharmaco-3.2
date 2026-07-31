<?php
namespace App\Models;
use CodeIgniter\Model;
final class UserModel extends Model {
    protected $table = 'utilisateurs'; protected $primaryKey = 'user_id';
    protected $allowedFields = ['username', 'mdp'];
}
