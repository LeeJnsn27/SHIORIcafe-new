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

// Set connection parameters (defaults set to your local PostgreSQL setup)
$host     = $_ENV['DB_HOST']     ?? 'localhost';
$port     = $_ENV['DB_PORT']     ?? '5173';
$dbname   = $_ENV['DB_NAME']     ?? 'grp10_shioricafedb';
$user     = $_ENV['DB_USER']     ?? 'postgres';
$password = $_ENV['DB_PASSWORD'] ?? 'mypostGRE'; // <--- PUT YOUR POSTGRES PASSWORD HERE
$schema   = $_ENV['DB_SCHEMA']   ?? 'shioricafe_schema';

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    // Set PostgreSQL schema search path
    $pdo->exec("SET search_path TO $schema, public");
} catch (PDOException $e) {
    die("Database connection failure: " . $e->getMessage());
}