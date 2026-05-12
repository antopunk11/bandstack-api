<?php
// =============================================================
// config/Database.php — Singleton PDO para MySQL
// =============================================================

class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $host   = getenv('DB_HOST')     ?: 'localhost';
            $port   = getenv('DB_PORT')     ?: '3306';
            $dbname = getenv('DB_NAME')     ?: 'bandstack';
            $user   = getenv('DB_USER')     ?: 'root';
            $pass   = getenv('DB_PASS')     ?: '';

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                // No exponer detalles de conexión en producción
                $message = APP_DEBUG ? $e->getMessage() : 'Error de conexión a la base de datos.';
                http_response_code(503);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $message]);
                exit;
            }
        }

        return self::$instance;
    }
}
