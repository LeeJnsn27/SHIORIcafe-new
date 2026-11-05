<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../config/db.php';

/** @var PDO $pdo */

try {
    if (!isset($pdo)) {
        throw new Exception("Database connection failure.");
    }

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;

    $resDate   = trim($data['reservation_date'] ?? date('Y-m-d'));
    $startTime = trim($data['reservation_time'] ?? '10:00');
    $endTime   = trim($data['reservation_end_time'] ?? '12:00');
    $guestName = trim($data['guest_name'] ?? $_SESSION['user_name'] ?? 'Guest Customer');
    $email     = trim($data['guest_email'] ?? $_SESSION['email'] ?? 'guest@shioricafe.local');
    $nookSeat  = trim($data['nook_type'] ?? 'Tatami Desk 1');

    if (strlen($startTime) === 5) $startTime .= ':00';
    if (strlen($endTime) === 5)   $endTime   .= ':00';

    $startAt = "$resDate $startTime";
    $endAt   = "$resDate $endTime";

    // Map UI seat selection to valid table_id
    $tableId = 1;
    if (strpos($nookSeat, '2') !== false) $tableId = 2;
    elseif (strpos($nookSeat, '3') !== false) $tableId = 3;
    elseif (strpos($nookSeat, 'Window Nook 1') !== false) $tableId = 4;
    elseif (strpos($nookSeat, 'Window Nook 2') !== false) $tableId = 5;
    elseif (strpos($nookSeat, 'Window Nook 3') !== false) $tableId = 6;
    elseif (strpos($nookSeat, 'Group Table 1') !== false) $tableId = 7;
    elseif (strpos($nookSeat, 'Group Table 2') !== false) $tableId = 8;

    // Resolve or auto-create customer_id
    $customerId = $_SESSION['user_id'] ?? $_SESSION['customer_id'] ?? null;

    if (!$customerId) {
        $stmtCust = $pdo->prepare("SELECT customer_id FROM shioricafe_schema.customer WHERE email = :email LIMIT 1");
        $stmtCust->execute([':email' => $email]);
        $existingCust = $stmtCust->fetch();

        if ($existingCust) {
            $customerId = $existingCust['customer_id'];
        } else {
            $defaultHash = password_hash('GuestPass123!', PASSWORD_DEFAULT);
            $stmtNewCust = $pdo->prepare("
                INSERT INTO shioricafe_schema.customer (full_name, email, password_hash) 
                VALUES (:name, :email, :hash) 
                RETURNING customer_id
            ");
            $stmtNewCust->execute([
                ':name'  => $guestName, 
                ':email' => $email,
                ':hash'  => $defaultHash
            ]);
            $customerId = $stmtNewCust->fetchColumn();
        }
    }

    $sql = "
        INSERT INTO shioricafe_schema.reservation (
            customer_id,
            table_id,
            party_size,
            reservation_start_at,
            reservation_end_at,
            status,
            is_walkin
        ) VALUES (
            :customer_id,
            :table_id,
            1,
            :start_at,
            :end_at,
            'Confirmed',
            false
        )
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':customer_id' => $customerId,
        ':table_id'    => $tableId,
        ':start_at'    => $startAt,
        ':end_at'      => $endAt
    ]);

    echo json_encode([
        'status'  => 'success',
        'success' => true,
        'message' => 'Nook reserved successfully!'
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'success' => false,
        'message' => 'Reservation failed: ' . $e->getMessage()
    ]);
    exit;
}