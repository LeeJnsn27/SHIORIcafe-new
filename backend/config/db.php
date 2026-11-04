<?php
// Centralized Database Connection
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host     = 'localhost';
$username = 'root';
$password = '';
$database = 'shiori_cafe_db';

try {
    $conn = mysqli_connect($host, $username, $password, $database);
    mysqli_set_charset($conn, 'utf8mb4');
} catch (mysqli_sql_exception $e) {
    die("Database connection failure: " . $e->getMessage());
}