<?php

namespace App\Models;

use App\Libraries\PdoConnection;

final class MedicationModel
{
    public function createForClient(int $clientId, string $type, int $quantity, string $date): void
    {
        $statement = PdoConnection::get()->prepare('INSERT INTO medicament (id_client, Type, Nombres, Date) VALUES (:client, :type, :quantity, :date)');
        $statement->execute(['client' => $clientId, 'type' => $type, 'quantity' => $quantity, 'date' => $date]);
    }

    public function dashboard(): array
    {
        $pdo = PdoConnection::get();
        return [
            'medicaments' => (int) $pdo->query('SELECT COALESCE(SUM(Nombres), 0) FROM medicament')->fetchColumn(),
            'clients' => (int) $pdo->query('SELECT COUNT(*) FROM client')->fetchColumn(),
            'types' => (int) $pdo->query('SELECT COUNT(DISTINCT Type) FROM medicament')->fetchColumn(),
            'mois' => (int) $pdo->query("SELECT COUNT(DISTINCT DATE_FORMAT(Date, '%Y-%m')) FROM medicament")->fetchColumn(),
        ];
    }

    public function records(): array
    {
        return PdoConnection::get()->query("SELECT c.Id, c.Nom, c.Prenom, c.Age, c.Genre, c.Type AS TypeClient, c.District, m.Type AS TypeMedicament, m.Nombres AS Quantite, DATE_FORMAT(m.Date, '%Y-%m') AS Mois FROM client c INNER JOIN medicament m ON m.id_client = c.Id ORDER BY m.Date DESC")->fetchAll();
    }

    public function byDistrict(): array
    {
        return PdoConnection::get()->query("SELECT c.District, SUM(m.Nombres) AS total FROM medicament m INNER JOIN client c ON c.Id=m.id_client WHERE m.Date >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY c.District ORDER BY total DESC")->fetchAll();
    }

    public function chartData(): array
    {
        $pdo = PdoConnection::get();
        $medications = $pdo->query('SELECT Type AS label, SUM(Nombres) AS value FROM medicament GROUP BY Type ORDER BY Type')->fetchAll();
        $clients = $pdo->query('SELECT Type AS label, COUNT(*) AS value FROM client GROUP BY Type ORDER BY Type')->fetchAll();
        $months = $pdo->query("SELECT DATE_FORMAT(Date, '%Y-%m') AS month, Type, SUM(Nombres) AS value FROM medicament GROUP BY month, Type ORDER BY month, Type")->fetchAll();
        $ages = $pdo->query('SELECT Age, Genre, COUNT(*) AS value FROM client WHERE Age > 0 GROUP BY Age, Genre ORDER BY Age, Genre')->fetchAll();
        return compact('medications', 'clients', 'months', 'ages');
    }
}
