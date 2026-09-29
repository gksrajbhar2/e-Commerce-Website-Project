<?php
/**
 * Database configuration.
 * Update these four constants to match your MySQL/MariaDB setup.
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'esewa_ecommerce');
define('DB_USER', 'root');
define('DB_PASS', '');

/**
 * Returns a shared PDO connection (created once per request).
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Never leak DB credentials or raw exception details to visitors.
            error_log('Database connection failed: ' . $e->getMessage());
            die('Sorry, something went wrong connecting to the database. Please try again later.');
        }
    }

    return $pdo;
}
