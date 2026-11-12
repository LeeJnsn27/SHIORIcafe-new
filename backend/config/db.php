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

// Set connection parameters with safe local fallbacks
$host     = $_ENV['DB_HOST']     ?? 'localhost';
$port     = $_ENV['DB_PORT']     ?? '5433';
$dbname   = $_ENV['DB_NAME']     ?? 'grp10_shioricafedb';
$schema   = $_ENV['DB_SCHEMA']   ?? 'shioricafe_schema';

// Fallback to postgres superuser credentials if .env values fail
$user     = $_ENV['DB_USER']     ?? getenv('DB_USER')     ?? 'postgres';
$password = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?? 'postgres'; // Change to your postgres password if needed

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    // Set PostgreSQL schema search path
    $pdo->exec("SET search_path TO $schema, public");
} catch (PDOException $e) {
    // Temporarily output the exact error for local debugging
    error_log('Database connection failed: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    die('Database connection failure: ' . htmlspecialchars($e->getMessage()));
}