<?php
namespace App\Controllers;
use App\Models\AdminUserModel;
final class UserController extends BaseController {
    private AdminUserModel $users;
    public function __construct() { $this->users = new AdminUserModel(); }
    public function index() { return view('users/index', $this->users->getUsersPageData((string) $this->request->getGet('q'), (string) $this->request->getGet('role'))); }
    public function create() {
        if (! $this->validate(['username'=>'required|max_length[255]|is_unique[utilisateurs.username]','email'=>'required|valid_email|max_length[255]','password'=>'required|min_length[6]','role'=>'required|in_list[Super Admin,Saisie,Visiteur,Visualiseur,Analyste]'])) return redirect()->back()->withInput()->with('error','Informations invalides.');
        $data=['username'=>trim((string)$this->request->getPost('username')),'prenom'=>trim((string)$this->request->getPost('prenom')),'nom'=>trim((string)$this->request->getPost('nom')),'email'=>trim((string)$this->request->getPost('email')),'role'=>(string)$this->request->getPost('role'),'statut'=>'Actif','mdp'=>password_hash((string)$this->request->getPost('password'),PASSWORD_DEFAULT)];
        return $this->users->createUser($data) ? redirect()->to('/utilisateurs')->with('success','Utilisateur créé.') : redirect()->back()->withInput()->with('error','Création impossible.');
    }
    public function toggle(int $id) { $this->users->toggleStatus($id); return redirect()->to('/utilisateurs'); }
    public function update(int $id) {
        if (! $this->validate(['email'=>'required|valid_email|max_length[255]','role'=>'required|in_list[Super Admin,Saisie,Visiteur,Visualiseur,Analyste]'])) return redirect()->back()->withInput()->with('error','Informations invalides.');
        $data=['prenom'=>trim((string)$this->request->getPost('prenom')),'nom'=>trim((string)$this->request->getPost('nom')),'email'=>trim((string)$this->request->getPost('email')),'role'=>(string)$this->request->getPost('role')]; $this->users->updateUser($id, $data); return redirect()->to('/utilisateurs')->with('success','Utilisateur modifié.');
    }
    public function delete(int $id) { if ($id !== (int) session('user_id')) $this->users->deleteUser($id); return redirect()->to('/utilisateurs')->with('success','Utilisateur supprimé.'); }
}
