<?php
/**
 * PostgreSQL Database Connection Configuration
 * Campus Lost-and-Found Portal
 * 
 * Credentials are securely loaded from .env (ignored by Git)
 */

if (!function_exists('loadEnv')) {
    /**
     * Parse and load .env file into PHP environment
     */
    function loadEnv(string $filePath): void {
        if (!file_exists($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            // Skip comments and empty lines
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Strip quotes if present
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }

                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Load environment variables from .env
loadEnv(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

// Read database configuration with environment variable support (checks $_ENV, $_SERVER, and getenv)
$envHost = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST');
$envPort = $_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? getenv('DB_PORT');
$envName = $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? getenv('DB_NAME');
$envUser = $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? getenv('DB_USER');
$envPass = $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? (getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

define('DB_HOST', $envHost ?: 'localhost');
define('DB_PORT', $envPort ?: '5432');
define('DB_NAME', $envName ?: 'campus_lost_found');
define('DB_USER', $envUser ?: 'postgres');
define('DB_PASS', $envPass !== null ? (string)$envPass : '');

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
            throw new RuntimeException("Database connection failed: " . $e->getMessage());
        }
    }

    return $pdo;
}
