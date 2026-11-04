
<?php
// Set response header to return JSON
header("Content-Type: application/json");

// Connect to the PostgreSQL database
require_once "../config/db.php";

// Get query parameters from URL (sent via GET request)
$date      = trim($_GET["date"] ?? "");
$startTime = trim($_GET["startTime"] ?? "");
$endTime   = trim($_GET["endTime"] ?? "");

if ($date == "" || $startTime == "" || $endTime == "") {
    echo json_encode(["error" => "Date, start time, and end time are required."]);
    exit;
}

try {
    // Query all seats that already have an overlapping reservation during that time
    $sql = "SELECT seats.seat_name
            FROM reservations
            INNER JOIN seats ON seats.id = reservations.seat_id
            WHERE reservation_date = ?
              AND reservation_start_time < ?
              AND reservation_end_time > ?";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$date, $endTime, $startTime]);

    $occupiedSeats = [];

    // Collect all occupied seat names into an array
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $occupiedSeats[] = $row["seat_name"];
    }

    // Return the list of occupied seats to frontend
    echo json_encode([
        "success" => true,
        "occupiedSeats" => $occupiedSeats,
        "availability" => ["conflict" => !empty($occupiedSeats)]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Seat availability error: " . $e->getMessage());

    echo json_encode([
        "success" => false,
        "error" => "Failed to check seat availability."
    ]);
}
?>
