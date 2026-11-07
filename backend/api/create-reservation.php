<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

/** @var PDO $pdo */

try {
    $input = file_get_contents('php://input');
    $data  = json_decode($input, true) ?: $_POST;

    // Only signed-in customers may create reservations (BUSINESS_RULES.md section 9).
    // Guests are not given a customer row, and no other customer is substituted.
    $customerId = intval($_SESSION['customer_id'] ?? 0);
    if ($customerId <= 0) {
        http_response_code(401);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Please sign in as a customer to reserve a nook.'
        ]);
        exit();
    }

    // Parse inputs
    $tableId   = intval($data['table_id'] ?? $data['nook_id'] ?? 1);
    $partySize = intval($data['party_size'] ?? $data['guests'] ?? 1);
    
    $resDate   = trim($data['reservation_date'] ?? $data['date'] ?? date('Y-m-d'));
    $startTime = trim($data['reservation_time'] ?? $data['time'] ?? '12:00');
    $duration  = intval($data['duration_hours'] ?? $data['duration'] ?? 2);
    if ($duration <= 0) { $duration = 2; }

    $startTimestamp = strtotime("$resDate $startTime");
    if (!$startTimestamp) { $startTimestamp = time(); }
    
    $startAt = date('Y-m-d H:i:s', $startTimestamp);
    $endAt   = date('Y-m-d H:i:s', strtotime("+{$duration} hours", $startTimestamp));

    // Insert Reservation
    $stmt = $pdo->prepare("
        INSERT INTO shioricafe_schema.reservation (
            customer_id, table_id, party_size, reservation_start_at, reservation_end_at, status, is_walkin, required_minimum_amount
        ) VALUES (
            :cid, :tid, :party, :start_at, :end_at, 'Confirmed', false, 0.00
        ) RETURNING reservation_id
    ");

    $stmt->execute([
        ':cid'      => $customerId,
        ':tid'      => $tableId,
        ':party'    => $partySize,
        ':start_at' => $startAt,
        ':end_at'   => $endAt
    ]);

    $reservationId = $stmt->fetchColumn();

    echo json_encode([
        'status'         => 'success',
        'message'        => 'Reservation created successfully!',
        'reservation_id' => $reservationId
    ]);

} catch (Exception $e) {
    http_response_code(400);
    $msg = $e->getMessage();
    
    if (strpos($msg, 'ex_reservation_no_overlap') !== false || strpos($msg, '23P01') !== false) {
        $msg = "This table is already reserved for the selected timeslot.";
    }

    echo json_encode([
        'status'  => 'error',
        'message' => $msg
    ]);
}