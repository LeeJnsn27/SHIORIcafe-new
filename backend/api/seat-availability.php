<?php
// backend/api/seat-availability.php - Check available tables and overlapping reservations
header("Content-Type: application/json");

require_once __DIR__ . '/../config/db.php';
/** @var PDO $conn */

$date      = trim($_GET["date"] ?? $_GET["reservation_date"] ?? "");
$startTime = trim($_GET["startTime"] ?? $_GET["time"] ?? "");
$endTime   = trim($_GET["endTime"] ?? "");

// Validate required inputs
if ($date === "" || $startTime === "" || $endTime === "") {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error"   => "Date, start time, and end time are required."
    ]);
    exit;
}

// Strict date and whole-minute time validation
$dateValid  = DateTime::createFromFormat("!Y-m-d", $date);
$startValid = DateTime::createFromFormat("!H:i", $startTime);
$endValid   = DateTime::createFromFormat("!H:i", $endTime);

if (
    !$dateValid ||
    $dateValid->format("Y-m-d") !== $date ||
    !$startValid ||
    $startValid->format("H:i") !== $startTime ||
    !$endValid ||
    $endValid->format("H:i") !== $endTime ||
    $endTime <= $startTime
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error"   => "Invalid date or time interval."
    ]);
    exit;
}

try {
    // Build requested reservation timestamps in Asia/Manila
    $timezone = new DateTimeZone("Asia/Manila");

    $requestedStart = new DateTime("$date $startTime", $timezone);
    $requestedEnd   = new DateTime("$date $endTime", $timezone);

    $sql = '
        SELECT
            t.table_id,
            t.table_number
        FROM shioricafe_schema."table" t
        WHERE t.is_available = TRUE
          AND NOT EXISTS (
              SELECT 1
              FROM shioricafe_schema.reservation r
              WHERE r.table_id = t.table_id
                AND r.status IN (
                    \'Confirmed\',
                    \'Adjustment_Pending\',
                    \'Occupied\'
                )
                AND r.reservation_start_at < CAST(:requested_end AS TIMESTAMP)
                AND r.reservation_end_at > CAST(:requested_start AS TIMESTAMP)
          )
        ORDER BY t.table_number
    ';

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ":requested_start" => $requestedStart->format("Y-m-d H:i:s"),
        ":requested_end"   => $requestedEnd->format("Y-m-d H:i:s")
    ]);

    $availableTables = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success"         => true,
        "availableTables" => $availableTables
    ]);

} catch (PDOException $e) {
    http_response_code(500);

    error_log("Seat availability error: " . $e->getMessage());

    echo json_encode([
        "success" => false,
        "error"   => "Failed to check table availability."
    ]);
}
?>