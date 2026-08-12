<?php

namespace App\Libraries;

use PDO;

/** A single, lazily-created PDO connection for the whole request. */
final class PdoConnection
{
    private static ?PDO $connection = null;

    public static function get(): PDO
    {
        if (self::$connection === null) {
            $host = env('database.default.hostname', '127.0.0.1');
            $port = env('database.default.port', '3306');
            $database = env('database.default.database');
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            self::$connection = new PDO($dsn, env('database.default.username'), env('database.default.password'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }

        return self::$connection;
    }
}
