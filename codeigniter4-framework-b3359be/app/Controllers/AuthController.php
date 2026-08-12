<?php

namespace App\Controllers;

use App\Models\UserModel;

final class AuthController extends BaseController
{
    public function login()
    {
        if (session()->get('user_id')) return redirect()->to('/');
        return view('auth/login');
    }

    public function authenticate()
    {
        $rules = ['username' => 'required|max_length[255]', 'password' => 'required'];
        if (! $this->validate($rules)) return redirect()->back()->withInput()->with('error', 'Identifiant et mot de passe requis.');
        $user = (new UserModel())->findByUsername((string) $this->request->getPost('username'));
        $password = (string) $this->request->getPost('password');
        // Supports bcrypt users and the legacy SQL dump until passwords are migrated.
        if (! $user || $user['statut'] !== 'Actif' || (! password_verify($password, $user['mdp']) && ! hash_equals($user['mdp'], $password))) {
            return redirect()->back()->withInput()->with('error', 'Identifiants incorrects.');
        }
        session()->regenerate();
        (new UserModel())->markConnected((int) $user['user_id']);
        session()->set(['user_id' => $user['user_id'], 'user_name' => $user['username']]);
        return redirect()->to('/');
    }

    public function logout() { session()->destroy(); return redirect()->to('/connexion'); }
}
