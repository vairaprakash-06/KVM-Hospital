<?php
/**
 * KVM Hospital Management System — NexusCare
 * Centralized Database Connection using PDO
 */

declare(strict_types=1);

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $port = getenv('DB_PORT') ?: '3306';
            $dbname = getenv('DB_DATABASE') ?: 'kvm_hospital';
            $username = getenv('DB_USERNAME') ?: 'root';
            $password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$instance = new PDO($dsn, $username, $password, $options);
            } catch (PDOException $e) {
                // Return clear error without exposing credentials
                error_log("Database connection failure: " . $e->getMessage());
                die(json_encode([
                    'error' => true,
                    'message' => 'NexusCare Central Database connection failed. Please verify MySQL service is running and credentials in config/database.php are correct.'
                ]));
            }
        }

        return self::$instance;
    }

    /**
     * Prevent cloning or instantiation
     */
    private function __construct() {}
    private function __clone() {}
}

function get_db(): PDO {
    return Database::getConnection();
}
