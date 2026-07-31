<?php
namespace App\Controllers;
final class ApiController extends BaseController {
 public function data(){ $rows=db_connect()->query("SELECT c.Id,c.Age,c.Genre,c.Type TypeClient,c.District,m.id_client,m.Type TypeMedicament,m.Nombres Quantite,DATE_FORMAT(m.Date,'%Y-%m') Mois FROM client c INNER JOIN medicament m ON c.Id=m.id_client ORDER BY m.Date DESC")->getResultArray(); return $this->response->setJSON($rows); }
 public function risks(){ $db=db_connect(); $rows=$db->query("SELECT c.District,m.Type,SUM(m.Nombres) total FROM medicament m INNER JOIN client c ON c.Id=m.id_client WHERE c.District != '' AND m.Date != '0000-00-00 00:00:00' GROUP BY c.District,m.Type")->getResultArray(); $weights=['Antipyrétique'=>3,'Antidiarrhéique'=>2.8,'Antibiotique'=>2,'Antalgique'=>1.2,'VitamineC'=>.5,'Antihistaminique'=>1.5]; $scores=[]; foreach($rows as $r){$scores[$r['District']]=($scores[$r['District']]??0)+$r['total']*($weights[$r['Type']]??1);} foreach($scores as &$s){$s=$s>=200?5:($s>=100?4:($s>=50?3:($s>=20?2:1)));} return $this->response->setJSON($scores); }
}
