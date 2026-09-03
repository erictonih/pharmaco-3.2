<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddUserAdministrationFields extends Migration
{
    public function up()
    {
        $columns = [
            'prenom' => 'VARCHAR(100) NULL', 'nom' => 'VARCHAR(100) NULL',
            'email' => 'VARCHAR(255) NULL', 'role' => "VARCHAR(50) NOT NULL DEFAULT 'Saisie'",
            'statut' => "VARCHAR(20) NOT NULL DEFAULT 'Actif'", 'derniere_connexion' => 'DATETIME NULL',
        ];
        foreach ($columns as $name => $definition) {
            $exists = $this->db->query("SHOW COLUMNS FROM utilisateurs LIKE ?", [$name])->getRowArray();
            if ($exists === null) $this->db->query("ALTER TABLE utilisateurs ADD COLUMN {$name} {$definition}");
        }
    }

    public function down()
    {
        foreach (['prenom', 'nom', 'email', 'role', 'statut', 'derniere_connexion'] as $name) {
            $exists = $this->db->query("SHOW COLUMNS FROM utilisateurs LIKE ?", [$name])->getRowArray();
            if ($exists !== null) $this->db->query("ALTER TABLE utilisateurs DROP COLUMN {$name}");
        }
    }
}
