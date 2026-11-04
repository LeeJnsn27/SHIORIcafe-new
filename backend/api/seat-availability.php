<?php
// Set response header to return JSON
header("Content-Type: application/json");

// Connect to the database
require_once "db.php";

// Get query parameters from URL (sent via GET request)
$date      = trim($_GET["date"] ?? "");
$startTime = trim($_GET["startTime"] ?? "");
$endTime   = trim($_GET["endTime"] ?? "");

if ($date == "" || $startTime == "" || $endTime == "") {
    echo json_encode(["error" => "Date, start time, and end time are required."]);
    exit;
}

// Query all seats that already have an overlapping reservation during that time
$sql = "SELECT seats.seat_name
        FROM reservations
        INNER JOIN seats ON seats.id = reservations.seat_id
        WHERE reservation_date = '$date'
          AND reservation_start_time < '$endTime'
          AND reservation_end_time > '$startTime'";

$result = mysqli_query($conn, $sql);

$occupiedSeats = [];
if ($result) {
    // Collect all occupied seat names into an array
    while ($row = mysqli_fetch_assoc($result)) {
        $occupiedSeats[] = $row["seat_name"];
    }
}

// Return the list of occupied seats to frontend
echo json_encode([
    "success" => true,
    "occupiedSeats" => $occupiedSeats,
    "availability" => ["conflict" => false]
]);

// Close connection
mysqli_close($conn);
?>
