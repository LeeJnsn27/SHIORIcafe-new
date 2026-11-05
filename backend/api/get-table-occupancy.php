<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

/** @var PDO $pdo */

try {
    // Query active (Confirmed) reservations that encompass the current timestamp
    $stmt = $pdo->query("
        SELECT r.table_id, r.reservation_start_at, r.reservation_end_at, c.full_name as guest_name
        FROM shioricafe_schema.reservation r
        LEFT JOIN shioricafe_schema.customer c ON r.customer_id = c.customer_id
        WHERE r.status = 'Confirmed'
          AND CURRENT_TIMESTAMP >= r.reservation_start_at
          AND CURRENT_TIMESTAMP < r.reservation_end_at
    ");

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $occupancyMap = [];

    $now = time();

    foreach ($rows as $row) {
        $tableId = intval($row['table_id']);
        $endTimestamp = strtotime($row['reservation_end_at']);
        $diffSeconds = $endTimestamp - $now;
        $remainingMinutes = max(0, ceil($diffSeconds / 60));

        $occupancyMap[$tableId] = [
            'occupied' => true,
            'guest_name' => $row['guest_name'] ?? 'Guest',
            'remaining_minutes' => $remainingMinutes,
            'end_time_formatted' => date('h:i A', $endTimestamp)
        ];
    }

    echo json_encode([
        'status' => 'success',
        'tables' => $occupancyMap
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Occupancy lookup error: ' . $e->getMessage()
    ]);
}