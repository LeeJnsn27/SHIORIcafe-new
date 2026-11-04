<?php
header("Content-Type: application/json");
require_once "../config/db.php";
/** @var mysqli $conn */

// Support form submissions
$guestName  = trim($_POST["guest_name"] ?? $_POST["guestName"] ?? "");
$guestEmail = trim($_POST["guest_email"] ?? $_POST["guestEmail"] ?? "");
$seatName   = trim($_POST["nook_type"] ?? $_POST["seatName"] ?? "");
$resDate    = trim($_POST["reservation_date"] ?? $_POST["reservationDate"] ?? "");
$startTime  = trim($_POST["reservation_time"] ?? $_POST["reservationStartTime"] ?? "");
$endTime    = trim($_POST["reservation_end_time"] ?? $_POST["reservationEndTime"] ?? "");

if (empty($guestName) || empty($seatName) || empty($resDate) || empty($startTime) || empty($endTime)) {
    echo json_encode(["status" => "error", "message" => "Please fill in all required fields including start and end times."]);
    exit;
}

// Find seat ID if seats table exists
$seatQuery = mysqli_prepare($conn, "SELECT id FROM seats WHERE seat_name = ? LIMIT 1");
$seatId = null;
if ($seatQuery) {
    mysqli_stmt_bind_param($seatQuery, "s", $seatName);
    mysqli_stmt_execute($seatQuery);
    $seatResult = mysqli_stmt_get_result($seatQuery);
    if ($seat = mysqli_fetch_assoc($seatResult)) {
        $seatId = $seat['id'];
    }
    mysqli_stmt_close($seatQuery);
}

// Check for 24/7 schedule overlaps: (existing_start < new_end) AND (existing_end > new_start)
if ($seatId !== null) {
    $overlapQuery = "SELECT reservation_end_time FROM reservations WHERE seat_id = '$seatId' AND reservation_date = '$resDate' AND ('$startTime' < reservation_end_time AND '$endTime' > reservation_start_time) ORDER BY reservation_end_time DESC LIMIT 1";
} else {
    $overlapQuery = "SELECT reservation_end_time FROM reservations WHERE nook_type = '$seatName' AND reservation_date = '$resDate' AND ('$startTime' < reservation_end_time AND '$endTime' > reservation_start_time) ORDER BY reservation_end_time DESC LIMIT 1";
}

$overlapResult = mysqli_query($conn, $overlapQuery);
if ($overlapResult && mysqli_num_rows($overlapResult) > 0) {
    $conflict = mysqli_fetch_assoc($overlapResult);
    $suggestedTime = $conflict['reservation_end_time'];

    // Format time nicely to 12-hour AM/PM (e.g., 3:00 PM)
    $formattedTime = date("g:i A", strtotime($suggestedTime));

    echo json_encode([
        "status" => "error",
        "message" => "This seat is already booked for that time window. The next available time for this seat starts at " . $formattedTime . ".",
        "suggested_time" => $suggestedTime
    ]);
    exit;
}

// Save reservation
if ($seatId !== null) {
    $sql = "INSERT INTO reservations (guest_name, guest_email, seat_id, nook_type, reservation_date, reservation_start_time, reservation_end_time, status)
            VALUES ('$guestName', '$guestEmail', $seatId, '$seatName', '$resDate', '$startTime', '$endTime', 'Confirmed')";
} else {
    $sql = "INSERT INTO reservations (guest_name, guest_email, nook_type, reservation_date, reservation_start_time, reservation_end_time, status)
            VALUES ('$guestName', '$guestEmail', '$seatName', '$resDate', '$startTime', '$endTime', 'Confirmed')";
}

if (mysqli_query($conn, $sql)) {
    echo json_encode(["status" => "success", "success" => true, "message" => "Reservation successfully confirmed!"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to save reservation: " . mysqli_error($conn)]);
}

mysqli_close($conn);
?>
