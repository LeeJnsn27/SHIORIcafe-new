<?php
require_once __DIR__ . '/../backend/config/db.php';
echo "Connected successfully to " . get_class($conn) . "\n";
$stmt = $conn->query("SELECT current_database(), current_schema()");
print_r($stmt->fetch());
