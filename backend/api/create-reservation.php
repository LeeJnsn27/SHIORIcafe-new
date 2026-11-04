
<?php
header("Content-Type: application/json");
require_once "../config/db.php";

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

try {
    // Find seat ID if seats table exists
    $seatId = null;

    try {
        $seatQuery = $conn->prepare("SELECT id FROM seats WHERE seat_name = ? LIMIT 1");
        $seatQuery->execute([$seatName]);

        $seat = $seatQuery->fetch(PDO::FETCH_ASSOC);

        if ($seat) {
            $seatId = $seat["id"];
        }
    } catch (PDOException $e) {
        // Continue using nook_type if seats table does not exist
    }

    // Check for 24/7 schedule overlaps
    if ($seatId !== null) {
        $overlapQuery = "SELECT reservation_end_time
                         FROM reservations
                         WHERE seat_id = ?
                         AND reservation_date = ?
                         AND (? < reservation_end_time
                         AND ? > reservation_start_time)
                         ORDER BY reservation_end_time DESC
                         LIMIT 1";

        $overlapStmt = $conn->prepare($overlapQuery);
        $overlapStmt->execute([$seatId, $resDate, $startTime, $endTime]);

    } else {
        $overlapQuery = "SELECT reservation_end_time
                         FROM reservations
                         WHERE nook_type = ?
                         AND reservation_date = ?
                         AND (? < reservation_end_time
                         AND ? > reservation_start_time)
                         ORDER BY reservation_end_time DESC
                         LIMIT 1";

        $overlapStmt = $conn->prepare($overlapQuery);
        $overlapStmt->execute([$seatName, $resDate, $startTime, $endTime]);
    }

    $conflict = $overlapStmt->fetch(PDO::FETCH_ASSOC);

    if ($conflict) {
        $suggestedTime = $conflict["reservation_end_time"];

        // Format time nicely to 12-hour AM/PM
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
        $sql = "INSERT INTO reservations
                (guest_name, guest_email, seat_id, nook_type, reservation_date,
                 reservation_start_time, reservation_end_time, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Confirmed')";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $guestName,
            $guestEmail,
            $seatId,
            $seatName,
            $resDate,
            $startTime,
            $endTime
        ]);

    } else {
        $sql = "INSERT INTO reservations
                (guest_name, guest_email, nook_type, reservation_date,
                 reservation_start_time, reservation_end_time, status)
                VALUES (?, ?, ?, ?, ?, ?, 'Confirmed')";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $guestName,
            $guestEmail,
            $seatName,
            $resDate,
            $startTime,
            $endTime
        ]);
    }

    echo json_encode([
        "status" => "success",
        "success" => true,
        "message" => "Reservation successfully confirmed!"
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Reservation error: " . $e->getMessage());

    echo json_encode([
        "status" => "error",
        "message" => "Failed to save reservation."
    ]);
}
?>
