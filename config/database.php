<?php
define('DB_HOST', getenv('HEMOPULSE_DB_HOST') ?: 'localhost');
define('DB_USER', getenv('HEMOPULSE_DB_USER') ?: 'root');
define('DB_PASS', getenv('HEMOPULSE_DB_PASS') ?: '');
define('DB_NAME', getenv('HEMOPULSE_DB_NAME') ?: 'hemopulse_db');
define('DB_PORT', getenv('HEMOPULSE_DB_PORT') ?: '3306');

function getDBConnection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            if (!ctype_digit((string)DB_PORT) || (int)DB_PORT < 1 || (int)DB_PORT > 65535) {
                throw new RuntimeException('Invalid database port.');
            }
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $sslCa = getenv('HEMOPULSE_DB_SSL_CA');
            if ($sslCa) {
                if (!is_readable($sslCa)) throw new RuntimeException('Database TLS certificate is not readable.');
                $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
            }
            $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, $options);
            $pdo->exec("SET time_zone = '+08:00'");
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed. Please try again later.', 0, $e);
        }
    }
    return $pdo;
}
