<?php

namespace App\Controllers;

use App\Models\ClientModel;
use App\Models\MedicationModel;
use Throwable;

final class PatientController extends BaseController
{
    public function create() { return view('patients/create'); }
    public function store()
    {
        $rules = ['Nom'=>'required|max_length[50]', 'Prenom'=>'required|max_length[50]', 'age'=>'required|integer|greater_than_equal_to[0]|less_than_equal_to[150]', 'genre'=>'required|in_list[Masculin,Feminin,Inconnue]', 'type_client'=>'required|in_list[Ambulatoire,Hospitalisé]', 'adresse'=>'required|max_length[100]', 'district'=>'required|max_length[250]'];
        if (! $this->validate($rules)) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        $id = trim((string) $this->request->getPost('identifiant')) ?: (string) (date('dmyHi') . random_int(100, 999));
        try {
            (new ClientModel())->create(['Id'=>(int)$id, 'Nom'=>trim($this->request->getPost('Nom')), 'Prenom'=>trim($this->request->getPost('Prenom')), 'Age'=>(int)$this->request->getPost('age'), 'Genre'=>$this->request->getPost('genre'), 'Type'=>$this->request->getPost('type_client'), 'Residence'=>trim($this->request->getPost('adresse')), 'District'=>trim($this->request->getPost('district'))]);
            session()->set('last_client_id', (int) $id);
            return redirect()->to('/medicaments/nouveau')->with('success', 'Client enregistré. Ajoutez ses médicaments.');
        } catch (Throwable $e) { log_message('error', $e->getMessage()); return redirect()->back()->withInput()->with('error', 'Cet identifiant existe déjà ou les données sont invalides.'); }
    }
    public function medicationForm()
    {
        $client = (new ClientModel())->find((int) session('last_client_id'));
        return view('medications/create', ['client' => $client]);
    }
    public function storeMedications()
    {
        $types = (array) $this->request->getPost('type_medoc'); $client = (new ClientModel())->find((int) session('last_client_id'));
        $allowed = ['Antibiotique','Antipyrétique','Antalgique','Antidiarrhéique','VitamineC'];
        if (! $client || $types === []) return redirect()->back()->with('error', 'Sélectionnez au moins un médicament.');
        $model = new MedicationModel(); foreach ($types as $type) if (in_array($type, $allowed, true)) $model->createForClient((int)$client['Id'], $type, max(0, (int)$this->request->getPost('quantite_'.$type)), $client['date_de_sousmission']);
        return redirect()->to('/')->with('success', 'Médicaments enregistrés avec succès.');
    }
}
