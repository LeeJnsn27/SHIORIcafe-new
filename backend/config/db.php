<?php
// backend/config/db.php

// Manually parse .env file if present
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// Set connection parameters
$host     = $_ENV['DB_HOST']     ?? 'localhost';
$port     = $_ENV['DB_PORT']     ?? '5173';
$dbname   = $_ENV['DB_NAME']     ?? 'grp10_shioricafedb';
$schema   = $_ENV['DB_SCHEMA']   ?? 'shioricafe_schema';

// Credentials have no defaults. Read them from .env or real environment variables.
$user     = $_ENV['DB_USER']     ?? getenv('DB_USER');
$password = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD');

$missing = [];
if ($user === false || $user === '') {
    $missing[] = 'DB_USER';
}
if ($password === false || $password === '') {
    $missing[] = 'DB_PASSWORD';
}
if ($missing) {
    error_log('Database configuration error: missing ' . implode(', ', $missing));
    if (!headers_sent()) {
        http_response_code(500);
    }
    die('Database configuration error: DB_USER and DB_PASSWORD must be set.');
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    // Set PostgreSQL schema search path
    $pdo->exec("SET search_path TO $schema, public");
} catch (PDOException $e) {
    // Do not echo $e->getMessage(): it can include the host, database name and user.
    error_log('Database connection failed (code ' . $e->getCode() . ')');
    if (!headers_sent()) {
        http_response_code(500);
    }
    die('Database connection failure.');
}