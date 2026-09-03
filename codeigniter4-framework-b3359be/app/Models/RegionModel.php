<?php

namespace App\Models;

use CodeIgniter\Model;

final class RegionModel extends Model
{
    protected $table = 'region';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['nom'];

    public function getRegions(): array
    {
        return $this->select('id, nom')->orderBy('nom')->findAll();
    }

    public function getDistricts(int $regionId): array
    {
        return $this->db->table('district')->select('id, nom')->where('region_id', $regionId)->orderBy('nom')->get()->getResultArray();
    }
}
