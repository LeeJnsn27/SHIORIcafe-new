<?php
// backend/api/create-reservation.php - Study Nook Reservation Creation API
header('Content-Type: application/json');

require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../config/db.php';
/** @var PDO $pdo */

// Ensure customer is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in to reserve a study nook.']);
    exit();
}

try {
    $input = file_get_contents('php://input');
    $data  = json_decode($input, true) ?: $_POST;

    $customerId = get_current_user_id();
    $tableId    = intval($data['table_id'] ?? 0);
    $date       = trim($data['reservation_date'] ?? $data['date'] ?? '');
    $startTime  = trim($data['reservation_time'] ?? $data['startTime'] ?? '');
    $duration   = intval($data['duration_hours'] ?? 2);
    $partySize  = intval($data['party_size'] ?? 1);

    if ($tableId <= 0 || empty($date) || empty($startTime)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Table, date, and start time are required.']);
        exit();
    }

    // Set timezone and calculate timestamps
    $timezone = new DateTimeZone("Asia/Manila");
    $startDateTime = new DateTime("$date $startTime", $timezone);
    $startAtStr = $startDateTime->format('Y-m-d H:i:s');

    $endDateTime = clone $startDateTime;
    $endDateTime->modify("+{$duration} hours");
    $endAtStr = $endDateTime->format('Y-m-d H:i:s');

    $pdo->beginTransaction();

    // Check for overlapping confirmed reservations on the same table
    $conflictStmt = $pdo->prepare("
        SELECT reservation_id
        FROM shioricafe_schema.reservation
        WHERE table_id = :table_id
          AND status IN ('Confirmed', 'Adjustment_Pending', 'Occupied')
          AND reservation_start_at < :end_at
          AND reservation_end_at > :start_at
        LIMIT 1
    ");
    $conflictStmt->execute([
        ':table_id' => $tableId,
        ':start_at' => $startAtStr,
        ':end_at'   => $endAtStr
    ]);

    if ($conflictStmt->fetch()) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Selected table is already reserved for this timeslot. Please choose another time or nook.']);
        exit();
    }

    // Insert reservation record
    $stmt = $pdo->prepare("
        INSERT INTO shioricafe_schema.reservation (
            customer_id, table_id, reservation_start_at, reservation_end_at, status, party_size, created_at
        ) VALUES (
            :customer_id, :table_id, :start_at, :end_at, 'Confirmed', :party_size, CURRENT_TIMESTAMP
        ) RETURNING reservation_id
    ");

    $stmt->execute([
        ':customer_id' => $customerId,
        ':table_id'    => $tableId,
        ':start_at'    => $startAtStr,
        ':end_at'      => $endAtStr,
        ':party_size'  => $partySize
    ]);

    $reservationId = $stmt->fetchColumn();

    if (!$reservationId) {
        throw new Exception("Failed to record reservation.");
    }

    $pdo->commit();

    echo json_encode([
        'success'        => true,
        'status'         => 'success',
        'message'        => 'Study nook reservation confirmed successfully!',
        'reservation_id' => $reservationId
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Reservation error: ' . $e->getMessage()
    ]);
}