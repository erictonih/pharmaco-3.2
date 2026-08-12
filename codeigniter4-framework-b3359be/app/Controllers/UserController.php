<?php
namespace App\Controllers;
use App\Models\AdminUserModel;
final class UserController extends BaseController {
 public function index(){ $m=new AdminUserModel();return view('users/index',['stats'=>$m->stats(),'users'=>$m->list((string)$this->request->getGet('q'),(string)$this->request->getGet('role'))]); }
 public function create(){if(!$this->validate(['username'=>'required|max_length[255]','email'=>'required|valid_email','password'=>'required|min_length[6]']))return redirect()->back()->withInput()->with('error','Informations invalides.');(new AdminUserModel())->create(['username'=>$this->request->getPost('username'),'prenom'=>$this->request->getPost('prenom'),'nom'=>$this->request->getPost('nom'),'email'=>$this->request->getPost('email'),'role'=>$this->request->getPost('role'),'statut'=>'Actif','mdp'=>password_hash((string)$this->request->getPost('password'),PASSWORD_DEFAULT)]);return redirect()->to('/utilisateurs')->with('success','Utilisateur créé.');}
 public function toggle(int $id){(new AdminUserModel())->toggle($id);return redirect()->to('/utilisateurs');}
 public function update(int $id){(new AdminUserModel())->update($id,['prenom'=>$this->request->getPost('prenom'),'nom'=>$this->request->getPost('nom'),'email'=>$this->request->getPost('email'),'role'=>$this->request->getPost('role')]);return redirect()->to('/utilisateurs')->with('success','Utilisateur modifié.');}
 public function delete(int $id){if($id!==(int)session('user_id'))(new AdminUserModel())->delete($id);return redirect()->to('/utilisateurs')->with('success','Utilisateur supprimé.');}
}
