<?php
define('DB_HOST', getenv('HEMOPULSE_DB_HOST') ?: 'localhost');
define('DB_USER', getenv('HEMOPULSE_DB_USER') ?: 'root');
define('DB_PASS', getenv('HEMOPULSE_DB_PASS') ?: '');
define('DB_NAME', getenv('HEMOPULSE_DB_NAME') ?: 'hemopulse_db');

function getDBConnection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec("SET time_zone = '+08:00'");
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed. Please try again later.', 0, $e);
        }
    }
    return $pdo;
}
