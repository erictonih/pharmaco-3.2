<?php

namespace App\Controllers;

use App\Models\ClientModel;
use App\Models\MedicationModel;
use Throwable;

final class PatientController extends BaseController
{
    private ClientModel $clients; private MedicationModel $medications;
    public function __construct() { $this->clients = new ClientModel(); $this->medications = new MedicationModel(); }
    public function create() { return view('patients/create'); }
    public function store()
    {
        $rules = ['Nom'=>'required|max_length[50]', 'Prenom'=>'required|max_length[50]', 'age'=>'required|integer|greater_than_equal_to[0]|less_than_equal_to[150]', 'genre'=>'required|in_list[Masculin,Feminin,Inconnue]', 'type_client'=>'required|in_list[Ambulatoire,Hospitalisé]', 'adresse'=>'required|max_length[100]', 'district'=>'required|max_length[250]'];
        if (! $this->validate($rules)) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        $id = trim((string) $this->request->getPost('identifiant')) ?: (string) (date('dmyHi') . random_int(100, 999));
        $client = ['Id'=>(int)$id, 'Nom'=>trim((string)$this->request->getPost('Nom')), 'Prenom'=>trim((string)$this->request->getPost('Prenom')), 'Age'=>(int)$this->request->getPost('age'), 'Genre'=>(string)$this->request->getPost('genre'), 'Type'=>(string)$this->request->getPost('type_client'), 'Residence'=>trim((string)$this->request->getPost('adresse')), 'District'=>trim((string)$this->request->getPost('district'))];
        try { if (! $this->clients->createClient($client)) throw new \RuntimeException('Insertion du client échouée.'); }
        catch (Throwable $e) { log_message('error', $e->getMessage()); return redirect()->back()->withInput()->with('error', 'Cet identifiant existe déjà ou les données sont invalides.'); }
        session()->set('last_client_id', (int) $id); return redirect()->to('/medicaments/nouveau')->with('success', 'Client enregistré. Ajoutez ses médicaments.');
    }
    public function medicationForm() { return view('medications/create', ['client' => $this->clients->findForMedication((int) session('last_client_id'))]); }
    public function storeMedications()
    {
        $types = (array) $this->request->getPost('type_medoc'); $client = $this->clients->findForMedication((int) session('last_client_id'));
        if ($client === null || $types === []) return redirect()->back()->with('error', 'Sélectionnez au moins un médicament.');
        $quantities = []; foreach ($types as $type) $quantities[(string) $type] = $this->request->getPost('quantite_'.$type);
        if (! $this->medications->saveForClient((int) $client['Id'], $quantities, $client['date_de_sousmission'])) return redirect()->back()->withInput()->with('error', 'Enregistrement des médicaments échoué.');
        return redirect()->to('/')->with('success', 'Médicaments enregistrés avec succès.');
    }
}
