<?php

namespace App\Database;

use PDO;

class Connection
{
    private static ?PDO $instance = null;

    public static function get(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                getenv('DB_HOST') ?: 'localhost',
                getenv('DB_PORT') ?: '3306',
                getenv('DB_NAME') ?: 'eventora'
            );

            self::$instance = new PDO(
                $dsn,
                getenv('DB_USER') ?: 'root',
                getenv('DB_PASS') ?: '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false, // real prepared statements — required by the course spec
                ]
            );
        }

        return self::$instance;
    }
}
