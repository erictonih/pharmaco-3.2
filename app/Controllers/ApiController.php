<?php
namespace App\Controllers; use App\Models\ReportingModel;
final class ApiController extends BaseController {
 public function data(){return $this->response->setJSON((new ReportingModel())->pivotData());}
 public function risks(){ $rows=(new ReportingModel())->riskRows(); $weights=['Antipyrétique'=>3,'Antidiarrhéique'=>2.8,'Antibiotique'=>2,'Antalgique'=>1.2,'VitamineC'=>.5,'Antihistaminique'=>1.5]; $scores=[]; foreach($rows as $r){$scores[$r['District']]=($scores[$r['District']]??0)+$r['total']*($weights[$r['Type']]??1);} foreach($scores as &$s){$s=$s>=200?5:($s>=100?4:($s>=50?3:($s>=20?2:1)));} return $this->response->setJSON($scores); }
}
