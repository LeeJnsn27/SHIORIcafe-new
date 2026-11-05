<?php
/**
 * SHIORI Café - Centralized PostgreSQL Database Connection
 * Uses PDO and environment variables from the root .env file.
 */

$projectRoot = dirname(__DIR__, 2);
$envPath = $projectRoot . '/.env';

// Check if .env exists
if (!file_exists($envPath)) {
    die("Database configuration error: .env file not found.");
}

// Load environment variables
$env = parse_ini_file($envPath, false, INI_SCANNER_RAW);

if ($env === false) {
    die("Database configuration error: Unable to read .env file.");
}

// Get required environment variables
$required = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'];

foreach ($required as $key) {
    if (!array_key_exists($key, $env)) {
        die("Database configuration error: Missing {$key} in .env.");
    }
}

$host = $env['DB_HOST'];
$port = $env['DB_PORT'];
$dbname = $env['DB_NAME'];
$username = $env['DB_USER'];
$password = $env['DB_PASSWORD'];
$sslmode = $env['DB_SSLMODE'] ?? 'require';
$schema = $env['DB_SCHEMA'] ?? 'public';

try {
    // Connect to PostgreSQL using PDO
    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode={$sslmode}";

    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Set the PostgreSQL schema
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $schema)) {
        throw new RuntimeException("Invalid database schema configuration.");
    }

    $conn->exec('SET search_path TO "' . $schema . '"');

} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Database connection failure. Please check your PostgreSQL configuration.");
} catch (RuntimeException $e) {
    error_log("Database configuration error: " . $e->getMessage());
    die("Database configuration failure.");
}
