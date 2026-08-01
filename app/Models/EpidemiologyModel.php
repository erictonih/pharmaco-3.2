<?php
namespace App\Models;
use CodeIgniter\Model;
final class EpidemiologyModel extends Model {
 public function districtMedicationTotals(): array{return $this->db->query("SELECT c.District,m.Type,SUM(m.Nombres) Total,MAX(m.Date) DernierAchat,MIN(m.Date) PremierAchat FROM medicament m INNER JOIN client c ON c.Id=m.id_client WHERE c.District<>'' AND m.Date<>'0000-00-00 00:00:00' GROUP BY c.District,m.Type ORDER BY c.District,Total DESC")->getResultArray();}
 public function monthlyDistrictTotals(): array{return $this->db->query("SELECT c.District,DATE_FORMAT(m.Date,'%Y-%m') Mois,SUM(m.Nombres) Total FROM medicament m INNER JOIN client c ON c.Id=m.id_client WHERE c.District<>'' AND m.Date<>'0000-00-00 00:00:00' GROUP BY c.District,Mois ORDER BY c.District,Mois")->getResultArray();}
}
