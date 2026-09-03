<?php

namespace App\Models;

use CodeIgniter\Model;

final class MedicationModel extends Model
{
    protected $table = 'medicament';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['id_client', 'Type', 'Nombres', 'Date'];

    public function getDashboardData(): array
    {
        $stats = [
            'medicaments' => (int) ($this->db->table('medicament')->selectSum('Nombres')->get()->getRowArray()['Nombres'] ?? 0),
            'clients' => $this->db->table('client')->countAllResults(),
            'types' => $this->db->table('medicament')->select('Type')->distinct()->countAllResults(),
            'mois' => $this->db->table('medicament')->select("DATE_FORMAT(Date, '%Y-%m') AS month", false)->distinct()->countAllResults(),
        ];
        return ['stats' => $stats, 'alertCount' => count($this->getRiskAnalysis()['alerts'])];
    }

    public function getTablePageData(): array { return ['rows' => $this->getRecords()]; }
    public function getChartsPageData(): array { return ['charts' => $this->getChartData()]; }
    public function getAnalysisPageData(): array { return ['analysis' => $this->getRiskAnalysis()]; }

    public function getRecords(): array
    {
        return $this->db->table('client c')->select("c.Id, c.Nom, c.Prenom, c.Age, c.Genre, c.Type AS TypeClient, c.District, m.Type AS TypeMedicament, m.Nombres AS Quantite, DATE_FORMAT(m.Date, '%Y-%m') AS Mois", false)->join('medicament m', 'm.id_client = c.Id')->orderBy('m.Date', 'DESC')->get()->getResultArray();
    }

    public function saveForClient(int $clientId, array $quantities, string $date): bool
    {
        $allowedTypes = ['Antibiotique', 'Antipyrétique', 'Antalgique', 'Antidiarrhéique', 'VitamineC'];
        $this->db->transStart();
        foreach ($quantities as $type => $quantity) if (in_array($type, $allowedTypes, true)) $this->insert(['id_client' => $clientId, 'Type' => $type, 'Nombres' => max(0, (int) $quantity), 'Date' => $date]);
        $this->db->transComplete();
        return $this->db->transStatus();
    }

    public function getRiskAnalysis(): array
    {
        $rows = $this->db->table('medicament m')->select('c.District, m.Type, SUM(m.Nombres) AS total', false)->join('client c', 'c.Id = m.id_client')->where('c.District <>', '')->groupBy(['c.District', 'm.Type'])->orderBy('c.District')->orderBy('total', 'DESC')->get()->getResultArray();
        $trendRows = $this->db->table('medicament m')->select("c.District, DATE_FORMAT(m.Date, '%Y-%m') AS month, SUM(m.Nombres) AS total", false)->join('client c', 'c.Id = m.id_client')->where('c.District <>', '')->groupBy(['c.District', 'month'])->orderBy('c.District')->orderBy('month')->get()->getResultArray();
        $weights = ['Antipyrétique'=>3.0, 'Antidiarrhéique'=>2.8, 'Antibiotique'=>2.0, 'Antalgique'=>1.2, 'VitamineC'=>0.5, 'Antihistaminique'=>1.5];
        $byDistrict = []; $series = []; $monthly = []; $byType = [];
        foreach ($rows as $row) { $byDistrict[$row['District']][] = $row; $byType[$row['Type']] = ($byType[$row['Type']] ?? 0) + (int) $row['total']; }
        foreach ($trendRows as $row) { $series[$row['District']][] = (int) $row['total']; $monthly[$row['month']] = ($monthly[$row['month']] ?? 0) + (int) $row['total']; }
        ksort($monthly); arsort($byType); $districts = []; $alerts = [];
        foreach ($byDistrict as $district => $items) {
            $base = 0; $total = 0; foreach ($items as $item) { $total += (int) $item['total']; $base += (int) $item['total'] * ($weights[$item['Type']] ?? 1.0); }
            $factor = count($items) >= 3 ? 1.3 : (count($items) >= 2 ? 1.15 : 1.0); $values = $series[$district] ?? []; $trend = 1.0;
            if (count($values) >= 2) { $before = $values[count($values)-2]; $last = $values[count($values)-1]; $trend = $before > 0 && $last > $before * 1.5 ? 1.5 : ($before > 0 && $last > $before * 1.2 ? 1.25 : 1.0); }
            $score = $base * $factor * $trend; $level = $score >= 200 ? 5 : ($score >= 100 ? 4 : ($score >= 50 ? 3 : ($score >= 20 ? 2 : 1)));
            $entry = compact('district', 'total', 'score', 'level', 'trend') + ['dominant' => $items[0]['Type'] ?? 'Inconnu']; $districts[] = $entry; if ($level >= 3) $alerts[] = $entry;
        }
        usort($districts, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        return compact('districts', 'alerts', 'monthly', 'byType');
    }

    private function getChartData(): array
    {
        $medications = $this->db->table('medicament')->select('Type AS label, SUM(Nombres) AS value', false)->groupBy('Type')->orderBy('Type')->get()->getResultArray();
        $clients = $this->db->table('client')->select('Type AS label, COUNT(*) AS value', false)->groupBy('Type')->orderBy('Type')->get()->getResultArray();
        $months = $this->db->table('medicament')->select("DATE_FORMAT(Date, '%Y-%m') AS month, Type, SUM(Nombres) AS value", false)->groupBy(['month', 'Type'])->orderBy('month')->orderBy('Type')->get()->getResultArray();
        $ages = $this->db->table('client')->select('Age, Genre, COUNT(*) AS value', false)->where('Age >', 0)->groupBy(['Age', 'Genre'])->orderBy('Age')->orderBy('Genre')->get()->getResultArray();
        return compact('medications', 'clients', 'months', 'ages');
    }
}
