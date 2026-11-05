<?php
// backend/api/seat-availability.php - Check overlapping reservations
header("Content-Type: application/json");

require_once __DIR__ . '/../config/db.php';
/** @var PDO $pdo */

$date      = trim($_GET["date"] ?? $_GET["reservation_date"] ?? "");
$startTime = trim($_GET["startTime"] ?? $_GET["time"] ?? "");
$endTime   = trim($_GET["endTime"] ?? "");

if (empty($date) || empty($startTime)) {
    echo json_encode([
        "success"       => true,
        "occupiedSeats" => [],
        "availability"  => ["conflict" => false]
    ]);
    exit;
}

// If end time wasn't provided, default to +2 hours
if (empty($endTime)) {
    $endTime = date('H:i', strtotime("+2 hours", strtotime("$date $startTime")));
}

try {
    $startAt = date('Y-m-d H:i:s', strtotime("$date $startTime"));
    $endAt   = date('Y-m-d H:i:s', strtotime("$date $endTime"));

    // Query ONLY tables that have an active (non-cancelled) reservation overlapping this window
    $stmt = $pdo->prepare("
        SELECT DISTINCT table_id 
        FROM shioricafe_schema.reservation 
        WHERE status != 'Cancelled' 
          AND (reservation_start_at < :end_at AND reservation_end_at > :start_at)
    ");

    $stmt->execute([
        ':start_at' => $startAt,
        ':end_at'   => $endAt
    ]);

    $occupiedTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        "success"       => true,
        "occupiedSeats" => array_map('intval', $occupiedTables),
        "availability"  => [
            "conflict"       => count($occupiedTables) > 0,
            "query_start"    => $startAt,
            "query_end"      => $endAt
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success"       => false,
        "occupiedSeats" => [],
        "error"         => $e->getMessage()
    ]);
}
?>