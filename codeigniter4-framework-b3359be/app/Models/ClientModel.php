<?php

namespace App\Models;

use CodeIgniter\Model;

final class ClientModel extends Model
{
    protected $table = 'client';
    protected $primaryKey = 'Id';
    protected $returnType = 'array';
    protected $useAutoIncrement = false;
    protected $allowedFields = ['Id', 'Nom', 'Prenom', 'Age', 'Genre', 'Type', 'Residence', 'District', 'date_de_sousmission'];

    public function createClient(array $client): bool
    {
        $client['date_de_sousmission'] = date('Y-m-d H:i:s');
        return $this->insert($client) !== false;
    }

    public function findForMedication(int $id): ?array
    {
        return $this->select('Id, Nom, Prenom, date_de_sousmission')->find($id) ?: null;
    }
}
