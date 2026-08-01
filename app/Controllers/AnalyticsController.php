<?php
namespace App\Controllers; use App\Models\ReportingModel; use App\Services\EpidemiologyService;
final class AnalyticsController extends BaseController {
 private function datasets(): array {
  extract((new ReportingModel())->analytics());
  $byMonth=[]; foreach($types as $index=>$type){$color=['rgba(10,108,188,.65)','rgba(0,184,148,.65)','rgba(99,102,241,.65)','rgba(245,158,11,.65)','rgba(168,85,247,.65)'][$index%5];$series=[];foreach($months as $month){$value=0;foreach($monthly as $row){if($row['Mois']===$month && $row['Type']===$type){$value=$row['Total'];break;}}$series[]=$value;}$byMonth[]=['label'=>$type,'data'=>$series,'backgroundColor'=>$color,'borderColor'=>$color];}
  $ageLabels=array_values(array_unique(array_column($ages,'Age'))); $genders=array_values(array_unique(array_column($ages,'Genre'))); $byAge=[]; foreach($genders as $index=>$gender){$series=[];foreach($ageLabels as $age){$value=0;foreach($ages as $row){if((string)$row['Age']===(string)$age && $row['Genre']===$gender){$value=$row['Total'];break;}}$series[]=$value;}$byAge[]=['label'=>$gender,'data'=>$series,'backgroundColor'=>['rgba(10,108,188,.65)','rgba(0,184,148,.65)','rgba(245,158,11,.65)'][$index%3]];}
  return compact('medicaments','clients','monthly','ages','totalMeds','totalClients','months','types','byMonth','ageLabels','byAge');
 }
 public function visualisation(){return view('pages/visualisation',$this->datasets()+['title'=>'Visualisation graphique']);}
 public function dashboard(){return view('pages/dashboard',$this->datasets()+['title'=>'Tableau de Bord']);}
 public function analyse(){return view('pages/analyse',(new EpidemiologyService())->analyse()+['title'=>'Analyse épidémiologique']);} public function tdc(){return view('pages/tdc');}
}
