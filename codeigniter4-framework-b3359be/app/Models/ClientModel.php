<?php

namespace App\Models;

use App\Libraries\PdoConnection;
use PDO;

final class ClientModel
{
    public function create(array $client): void
    {
        $statement = PdoConnection::get()->prepare('INSERT INTO client (Id, Nom, Prenom, Age, Genre, Type, Residence, District, date_de_sousmission) VALUES (:Id, :Nom, :Prenom, :Age, :Genre, :Type, :Residence, :District, NOW())');
        $statement->execute($client);
    }

    public function last(): ?array
    {
        return PdoConnection::get()->query('SELECT Id, Nom, Prenom, date_de_sousmission FROM client ORDER BY date_de_sousmission DESC LIMIT 1')->fetch() ?: null;
    }

    public function find(int $id): ?array
    {
        $statement = PdoConnection::get()->prepare('SELECT Id, Nom, Prenom, date_de_sousmission FROM client WHERE Id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        return $statement->fetch() ?: null;
    }

    public function all(): array
    {
        return PdoConnection::get()->query('SELECT Id, Nom, Prenom, Age, Genre, Type, Residence, District, date_de_sousmission FROM client ORDER BY date_de_sousmission DESC')->fetchAll();
    }
}
