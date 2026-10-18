<?php
/**
 * PostgreSQL Database Connection Configuration
 * Campus Lost-and-Found Portal
 */

// Database credentials
// Update 'DB_PASS' with your actual PostgreSQL password set during installation
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'campus_lost_found');
define('DB_USER', 'postgres');
define('DB_PASS', 'Newpass_2@85231');

function getDBConnection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', DB_HOST, DB_PORT, DB_NAME);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // In development, display clear diagnostic message
            throw new RuntimeException("Database connection failed: " . $e->getMessage());
        }
    }

    return $pdo;
}
