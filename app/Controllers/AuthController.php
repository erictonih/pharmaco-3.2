<?php
namespace App\Controllers;
use App\Models\UserModel;
final class AuthController extends BaseController {
 public function login(){ return session()->get('user_id') ? redirect()->to('/') : view('auth/login'); }
 public function attempt(){
  $rules=['username'=>'required','password'=>'required']; if (! $this->validate($rules)) return redirect()->back()->withInput()->with('error','Veuillez remplir tous les champs.');
  $model = new UserModel();
  $user = $model->where('username', trim((string) $this->request->getPost('username')))->first();
  $password = (string) $this->request->getPost('password');
  if (! $user) return redirect()->back()->withInput()->with('error', 'Identifiants incorrects.');

  // La table historique contient des mots de passe en clair. Au premier login
  // réussi, ils sont remplacés par un hash sécurisé sans changer les comptes.
  $stored = (string) $user['mdp'];
  $valid = password_get_info($stored)['algo'] !== null
      ? password_verify($password, $stored)
      : hash_equals($stored, $password);
  if (! $valid) return redirect()->back()->withInput()->with('error', 'Identifiants incorrects.');
  if (password_get_info($stored)['algo'] === null) {
      $model->update($user['user_id'], ['mdp' => password_hash($password, PASSWORD_DEFAULT)]);
  }
  session()->set(['user_id'=>$user['user_id'],'user_name'=>$user['username'],'role'=>'Utilisateur']); return redirect()->to('/');
 }
 public function logout(){ session()->destroy(); return redirect()->to('/connexion'); }
}
