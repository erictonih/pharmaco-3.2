<?php

namespace App\Models;

use App\Libraries\PdoConnection;

final class RegionModel
{
    public function all(): array
    {
        return PdoConnection::get()
            ->query('SELECT id, nom FROM region ORDER BY nom')
            ->fetchAll();
    }

    public function districts(int $regionId): array
    {
        $statement = PdoConnection::get()->prepare(
            'SELECT id, nom FROM district WHERE region_id = :region_id ORDER BY nom'
        );
        $statement->execute(['region_id' => $regionId]);

        return $statement->fetchAll();
    }
}
