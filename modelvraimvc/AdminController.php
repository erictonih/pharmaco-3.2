<?php

namespace App\Controllers;

use App\Models\AdminModel;

class AdminController extends BaseController
{
    private AdminModel $adminModel;

    public function __construct()
    {
        $this->adminModel = new AdminModel();
    }

    private function ensureAdmin()
    {
        if ((string) session()->get('role') !== 'admin') {
            return redirect()->to('/login')->with('error', 'Acces refuse.');
        }

        return null;
    }

    public function dashboard()
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        return view('admin/dashboard', $this->adminModel->getDashboardData());
    }

    public function employes()
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $annee = (int) ($this->request->getGet('annee') ?? date('Y'));
        $selectedEmployeeId = (int) ($this->request->getGet('edit') ?? 0);

        return view('admin/employes', $this->adminModel->getEmployeesPageData($annee, $selectedEmployeeId));
    }

    public function updateEmploye(int $id)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $rules = [
            'prenom' => 'required|min_length[2]|max_length[100]',
            'nom' => 'required|min_length[2]|max_length[100]',
            'email' => 'required|valid_email|max_length[150]|is_unique[employes.email,id,' . $id . ']',
            'role' => 'required|in_list[employe,rh,admin]',
            'departement_id' => 'permit_empty|integer',
            'date_embauche' => 'required|valid_date[Y-m-d]',
            'actif' => 'required|in_list[0,1]',
            'password' => 'permit_empty|min_length[6]|max_length[255]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'prenom' => trim((string) $this->request->getPost('prenom')),
            'nom' => trim((string) $this->request->getPost('nom')),
            'email' => trim((string) $this->request->getPost('email')),
            'role' => (string) $this->request->getPost('role'),
            'departement_id' => $this->request->getPost('departement_id') !== '' ? (int) $this->request->getPost('departement_id') : null,
            'date_embauche' => (string) $this->request->getPost('date_embauche'),
            'actif' => (int) $this->request->getPost('actif'),
        ];

        $password = trim((string) $this->request->getPost('password'));
        if ($password !== '') {
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        if (! $this->adminModel->updateEmployee($id, $data)) {
            return redirect()->back()->withInput()->with('error', 'La mise a jour de l employe a echoue.');
        }

        if ((int) session()->get('id') === $id) {
            session()->set([
                'prenom' => $data['prenom'],
                'nom' => $data['nom'],
                'email' => $data['email'],
            ]);
        }

        return redirect()->to('/admin/employes?edit=' . $id . '&annee=' . (int) $this->request->getPost('annee'))->with('success', 'Employe mis a jour avec succes.');
    }

    public function initialiseSoldes(int $id)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $annee = (int) ($this->request->getPost('annee') ?? date('Y'));

        $this->adminModel->ensureAnnualBalances($id, $annee);

        return redirect()->to('/admin/employes?edit=' . $id . '&annee=' . $annee)->with('success', 'Les soldes annuels ont ete initialises.');
    }

    public function saveSoldes(int $id)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $annee = (int) ($this->request->getPost('annee') ?? date('Y'));
        $joursAttribues = (array) $this->request->getPost('jours_attribues');
        $joursPris = (array) $this->request->getPost('jours_pris');

        $balances = $this->adminModel->prepareAnnualBalances($joursAttribues, $joursPris);

        if (! $this->adminModel->saveAnnualBalances($id, $annee, $balances)) {
            return redirect()->back()->withInput()->with('error', 'La sauvegarde des soldes a echoue.');
        }

        return redirect()->to('/admin/employes?edit=' . $id . '&annee=' . $annee)->with('success', 'Soldes annuels mis a jour.');
    }

    public function demandes()
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        return view('admin/demandes', $this->adminModel->getRequestsPageData());
    }
}
