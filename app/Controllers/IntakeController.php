<?php
namespace App\Controllers;
use App\Models\ClientModel; use App\Models\MedicationModel;
final class IntakeController extends BaseController {
 public function clientForm(){return view('intake/client');}
 public function storeClient(){
  if (! $this->validate(['Nom'=>'required','Prenom'=>'required','age'=>'required|integer','genre'=>'required','type_client'=>'required','adresse'=>'required'])) return redirect()->back()->withInput()->with('error','Veuillez remplir tous les champs obligatoires.');
  $id=trim((string)$this->request->getPost('identifiant')) ?: date('dmyHi').random_int(100,999);
  (new ClientModel())->insert(['Id'=>$id,'Nom'=>trim((string)$this->request->getPost('Nom')),'Prenom'=>trim((string)$this->request->getPost('Prenom')),'Age'=>(int)$this->request->getPost('age'),'Genre'=>$this->request->getPost('genre'),'Type'=>$this->request->getPost('type_client'),'Residence'=>trim((string)$this->request->getPost('adresse')),'District'=>trim((string)$this->request->getPost('district')),'date_de_sousmission'=>date('Y-m-d H:i:s')]);
  session()->set('last_client_id',$id); return redirect()->to('/saisie/medicaments')->with('success','Client enregistré avec succès.');
 }
 public function medicationForm(){ $client=(new ClientModel())->orderBy('date_de_sousmission','DESC')->first(); return view('intake/medications',['client'=>$client]); }
 public function storeMedications(){
  $types=$this->request->getPost('type_medoc'); if (! is_array($types) || $types===[]) return redirect()->back()->with('error','Aucun médicament sélectionné.');
  $client=(new ClientModel())->orderBy('date_de_sousmission','DESC')->first(); if (! $client) return redirect()->to('/saisie/client')->with('error','Aucun client trouvé.');
  $items=[]; foreach($types as $type){$items[]=['Type'=>$type,'Nombres'=>(int)$this->request->getPost('autre_question_'.$type),'id_client'=>$client['Id'],'Date'=>$client['date_de_sousmission']];} (new MedicationModel())->insertBatch($items);
  return redirect()->to('/')->with('success',count($items).' médicament(s) enregistré(s) avec succès.');
 }
}
