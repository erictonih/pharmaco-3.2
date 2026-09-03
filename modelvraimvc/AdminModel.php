<?php

namespace App\Models;

use CodeIgniter\Model;

class AdminModel extends Model
{
    protected $table = 'employes';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'nom',
        'prenom',
        'email',
        'password',
        'role',
        'departement_id',
        'date_embauche',
        'actif',
    ];

    public function getDashboardData(): array
    {
        return [
            'stats' => $this->getStats(),
            'recentRequests' => $this->getRecentRequests(),
            'absentsToday' => $this->getAbsentsToday(),
            'criticalBalances' => $this->getCriticalBalances(),
        ];
    }

    /**
     * Regroups all data required by the employee administration page.
     * The controller only supplies the HTTP parameters and renders the view.
     */
    public function getEmployeesPageData(int $annee, int $selectedEmployeeId = 0): array
    {
        $employes = $this->getEmployees($annee);
        if ($selectedEmployeeId <= 0 && $employes !== []) {
            $selectedEmployeeId = (int) $employes[0]['id'];
        }

        $selectedEmployee = $selectedEmployeeId > 0 ? $this->getEmployeeById($selectedEmployeeId) : null;
        if ($selectedEmployee !== null) {
            $this->ensureAnnualBalances($selectedEmployeeId, $annee);
        }

        return [
            'annee' => $annee,
            'selectedEmployeeId' => $selectedEmployeeId,
            'selectedEmployee' => $selectedEmployee,
            'selectedBalances' => $selectedEmployeeId > 0 ? $this->getEmployeeBalances($selectedEmployeeId, $annee) : [],
            'employes' => $employes,
            'departements' => $this->getDepartments(),
            'typesConge' => $this->getTypesConge(),
        ];
    }

    /** @param array<int|string, mixed> $joursAttribues @param array<int|string, mixed> $joursPris */
    public function prepareAnnualBalances(array $joursAttribues, array $joursPris): array
    {
        $balances = [];
        foreach ($this->getTypesConge() as $type) {
            $typeId = (int) ($type['id'] ?? 0);
            if ($typeId > 0) {
                $balances[$typeId] = [
                    'jours_attribues' => $joursAttribues[$typeId] ?? $joursAttribues[(string) $typeId] ?? 0,
                    'jours_pris' => $joursPris[$typeId] ?? $joursPris[(string) $typeId] ?? 0,
                ];
            }
        }

        return $balances;
    }

    public function getRequestsPageData(): array
    {
        $demandes = $this->getAllRequests();
        $stats = ['total' => count($demandes), 'en_attente' => 0, 'approuvee' => 0, 'refusee' => 0, 'annulee' => 0];
        foreach ($demandes as $demande) {
            $statut = (string) ($demande['statut'] ?? '');
            if (array_key_exists($statut, $stats)) {
                $stats[$statut]++;
            }
        }

        return compact('demandes', 'stats');
    }

    public function getEmployeeById(int $employeeId): ?array
    {
        $row = $this->db->table('employes e')
            ->select('e.id, e.nom, e.prenom, e.email, e.role, e.departement_id, e.date_embauche, e.actif, d.nom AS departement_nom')
            ->join('departments d', 'd.id = e.departement_id', 'left')
            ->where('e.id', $employeeId)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    public function getStats(): array
    {
        $db = $this->db;

        $activeEmployees = (int) $db->table('employes')->where('actif', 1)->countAllResults();
        $pendingRequests = (int) $db->table('conges')->where('statut', 'en_attente')->countAllResults();

        $approvedThisMonth = (int) $db->table('conges')
            ->where('statut', 'approuvee')
            ->where("strftime('%Y-%m', created_at)", date('Y-m'))
            ->countAllResults();

        $departments = (int) $db->table('departments')->countAllResults();
        $absentsToday = (int) $db->table('conges')
            ->whereIn('statut', ['en_attente', 'approuvee'])
            ->where('date_debut <=', date('Y-m-d'))
            ->where('date_fin >=', date('Y-m-d'))
            ->countAllResults();

        return [
            'activeEmployees' => $activeEmployees,
            'pendingRequests' => $pendingRequests,
            'approvedThisMonth' => $approvedThisMonth,
            'departments' => $departments,
            'absentsToday' => $absentsToday,
        ];
    }

    public function getRecentRequests(int $limit = 3): array
    {
        return $this->db->table('conges c')
            ->select('c.id, c.date_debut, c.date_fin, c.nb_jours, c.statut, t.libelle AS type_libelle, e.prenom, e.nom')
            ->join('types_conge t', 't.id = c.type_conge_id')
            ->join('employes e', 'e.id = c.employe_id')
            ->orderBy('c.created_at', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    public function getAbsentsToday(): array
    {
        return $this->db->table('conges c')
            ->select('e.prenom, e.nom, t.libelle AS type_libelle, c.date_fin')
            ->join('employes e', 'e.id = c.employe_id')
            ->join('types_conge t', 't.id = c.type_conge_id')
            ->whereIn('c.statut', ['en_attente', 'approuvee'])
            ->where('c.date_debut <=', date('Y-m-d'))
            ->where('c.date_fin >=', date('Y-m-d'))
            ->orderBy('c.date_fin', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function getCriticalBalances(): array
    {
        return $this->db->query(
            'SELECT e.prenom, e.nom, t.libelle, (s.jours_attribues - s.jours_pris) AS restants
             FROM soldes s
             INNER JOIN employes e ON e.id = s.employe_id
             INNER JOIN types_conge t ON t.id = s.type_conge_id
             WHERE (s.jours_attribues - s.jours_pris) <= ?
             ORDER BY restants ASC',
            [2]
        )->getResultArray();
    }

    public function getEmployees(?int $annee = null): array
    {
        $annee = $annee ?? (int) date('Y');

        return $this->db->table('employes e')
            ->select('e.id, e.nom, e.prenom, e.email, e.role, e.date_embauche, e.actif, d.nom AS departement_nom, COALESCE(SUM(s.jours_attribues), 0) AS total_attribues, COALESCE(SUM(s.jours_pris), 0) AS total_pris')
            ->join('departments d', 'd.id = e.departement_id', 'left')
            ->join('soldes s', 's.employe_id = e.id AND s.annee = ' . (int) $annee, 'left')
            ->groupBy('e.id')
            ->orderBy('e.prenom', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function updateEmployee(int $employeeId, array $data): bool
    {
        return (bool) $this->db->table('employes')
            ->where('id', $employeeId)
            ->update($data);
    }

    public function getEmployeeBalances(int $employeeId, int $annee): array
    {
        return $this->db->table('types_conge t')
            ->select('t.id AS type_conge_id, t.libelle, t.jours_annuels, t.deductible, COALESCE(s.jours_attribues, t.jours_annuels) AS jours_attribues, COALESCE(s.jours_pris, 0) AS jours_pris')
            ->join('soldes s', 's.type_conge_id = t.id AND s.employe_id = ' . (int) $employeeId . ' AND s.annee = ' . (int) $annee, 'left')
            ->orderBy('t.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function ensureAnnualBalances(int $employeeId, int $annee): void
    {
        $types = $this->getTypesConge();

        foreach ($types as $type) {
            $typeId = (int) ($type['id'] ?? 0);
            if ($typeId <= 0) {
                continue;
            }

            $exists = $this->db->table('soldes')
                ->select('id')
                ->where('employe_id', $employeeId)
                ->where('type_conge_id', $typeId)
                ->where('annee', $annee)
                ->get()
                ->getRowArray();

            if ($exists !== null) {
                continue;
            }

            $this->db->table('soldes')->insert([
                'employe_id' => $employeeId,
                'type_conge_id' => $typeId,
                'annee' => $annee,
                'jours_attribues' => (int) ($type['jours_annuels'] ?? 0),
                'jours_pris' => 0,
            ]);
        }
    }

    /**
     * @param array<int, array{jours_attribues?: int|string, jours_pris?: int|string}> $balances
     */
    public function saveAnnualBalances(int $employeeId, int $annee, array $balances): bool
    {
        $db = $this->db;
        $db->transStart();

        foreach ($balances as $typeId => $values) {
            $typeId = (int) $typeId;
            if ($typeId <= 0 || ! is_array($values)) {
                continue;
            }

            $joursAttribues = max(0, (int) ($values['jours_attribues'] ?? 0));
            $joursPris = max(0, (int) ($values['jours_pris'] ?? 0));

            $exists = $db->table('soldes')
                ->select('id')
                ->where('employe_id', $employeeId)
                ->where('type_conge_id', $typeId)
                ->where('annee', $annee)
                ->get()
                ->getRowArray();

            $payload = [
                'employe_id' => $employeeId,
                'type_conge_id' => $typeId,
                'annee' => $annee,
                'jours_attribues' => $joursAttribues,
                'jours_pris' => $joursPris,
            ];

            if ($exists !== null) {
                $db->table('soldes')
                    ->where('id', (int) $exists['id'])
                    ->update($payload);
            } else {
                $db->table('soldes')->insert($payload);
            }
        }

        $db->transComplete();

        return $db->transStatus();
    }

    public function getAllRequests(): array
    {
        return $this->db->table('conges c')
            ->select('c.id, c.date_debut, c.date_fin, c.nb_jours, c.motif, c.statut, c.commentaire_rh, c.created_at, c.traite_par, t.libelle AS type_libelle, e.prenom AS employe_prenom, e.nom AS employe_nom, e.email AS employe_email, d.nom AS departement_nom, r.prenom AS rh_prenom, r.nom AS rh_nom')
            ->join('types_conge t', 't.id = c.type_conge_id')
            ->join('employes e', 'e.id = c.employe_id')
            ->join('departments d', 'd.id = e.departement_id', 'left')
            ->join('employes r', 'r.id = c.traite_par', 'left')
            ->orderBy('c.created_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    public function getDepartments(): array
    {
        return $this->db->table('departments')
            ->orderBy('nom', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function getTypesConge(): array
    {
        return $this->db->table('types_conge')
            ->orderBy('libelle', 'ASC')
            ->get()
            ->getResultArray();
    }
}
