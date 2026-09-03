<?php

namespace App\Controllers;

use App\Models\UserModel;

final class AuthController extends BaseController
{
    private UserModel $users;
    public function __construct() 
    {
         $this->users = new UserModel();
    }
    public function login()
    {
        return session()->get('user_id') ? redirect()->to('/') : view('auth/login');
    }
    public function authenticate()
    {
        if (! $this->validate(['username' => 'required|max_length[255]', 'password' => 'required'])) return redirect()->back()->withInput()->with('error', 'Identifiant et mot de passe requis.');
        $user = $this->users->verifyCredentials(trim((string) $this->request->getPost('username')), (string) $this->request->getPost('password'));
        if ($user === null) return redirect()->back()->withInput()->with('error', 'Identifiants incorrects.');
        session()->regenerate(); $this->users->recordLogin((int) $user['user_id']);
        session()->set(['user_id' => $user['user_id'], 'user_name' => $user['username'], 'role' => $user['role']]);
        return redirect()->to('/');
    }
    public function logout() { session()->destroy(); return redirect()->to('/connexion'); }
}
