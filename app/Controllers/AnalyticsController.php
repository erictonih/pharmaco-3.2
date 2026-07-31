<?php
namespace App\Controllers;
final class AnalyticsController extends BaseController {
 private function datasets(): array {
  $db=db_connect(); $fix=['Antidiarrh?ique','AntidiarrhÃ©ique','AntipyrÃ©tique','Antipyr?tique']; $rep=['Antidiarrhéique','Antidiarrhéique','Antipyrétique','Antipyrétique'];
  $medicaments=[];$totalMeds=0; foreach($db->query('SELECT Type,SUM(Nombres) Nombre FROM medicament WHERE id_client IS NOT NULL GROUP BY Type')->getResultArray() as $r){$r['Type']=str_replace($fix,$rep,$r['Type']);$r['Nombre']=(int)$r['Nombre'];$medicaments[]=$r;$totalMeds+=$r['Nombre'];}
  $clients=[];$totalClients=0;foreach($db->query('SELECT Type,COUNT(*) Nombre FROM client GROUP BY Type')->getResultArray() as $r){$r['Type']=str_replace('Hospitalis?','Hospitalisé',$r['Type']);$r['Nombre']=(int)$r['Nombre'];$clients[]=$r;$totalClients+=$r['Nombre'];}
  $months=[];$types=[];$monthly=[];foreach($db->query("SELECT DATE_FORMAT(Date,'%Y-%m') Mois,Type,SUM(Nombres) Total FROM medicament WHERE Date!='0000-00-00 00:00:00' GROUP BY Mois,Type ORDER BY Mois,Type")->getResultArray() as $r){$r['Type']=str_replace($fix,$rep,$r['Type']);$r['Total']=(int)$r['Total'];$monthly[]=$r;if(!in_array($r['Mois'],$months,true))$months[]=$r['Mois'];if(!in_array($r['Type'],$types,true))$types[]=$r['Type'];}
  $ages=$db->query("SELECT Age,Genre,COUNT(*) Total FROM client WHERE Age!='0' GROUP BY Age,Genre ORDER BY Age,Genre")->getResultArray(); foreach($ages as &$r)$r['Total']=(int)$r['Total'];
  return compact('medicaments','clients','monthly','ages','totalMeds','totalClients','months','types');
 }
 public function visualisation(){return view('pages/visualisation',$this->datasets());}
 public function dashboard(){return view('pages/dashboard',$this->datasets());}
 public function analyse(){return view('pages/analyse');} public function tdc(){return view('pages/tdc');}
}
