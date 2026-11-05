<?php
session_start();
require_once "../backend/config/db.php";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$stmt = $conn->query("
    SELECT r.reservation_id, t.table_number, r.reservation_start_at, r.status
    FROM shioricafe_schema.reservation r
    LEFT JOIN shioricafe_schema.\"table\" t ON r.table_id = t.table_id
    WHERE r.checked_in_at IS NULL AND r.status IN ('Confirmed', 'Adjustment_Pending')
");
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Build the HTML table using a foreach ($reservations as $row) loop...
?>