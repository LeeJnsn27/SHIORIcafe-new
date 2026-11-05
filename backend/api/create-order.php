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

    $tableNumber = intval($data['table_number'] ?? $data['table_id'] ?? $data['table'] ?? 1);
    if ($tableNumber < 1 || $tableNumber > 9) $tableNumber = 1;

    $totalAmount = floatval($data['total_amount'] ?? $data['total'] ?? $data['gross_order_amount'] ?? 0.00);
    $email       = trim($data['email'] ?? $_SESSION['email'] ?? 'guest@shioricafe.local');
    $guestName   = trim($_SESSION['user_name'] ?? 'Guest Customer');

    // 1. Resolve customer_id
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
            $stmtNewCust->execute([':name' => $guestName, ':email' => $email, ':hash' => $defaultHash]);
            $customerId = $stmtNewCust->fetchColumn();
        }
    }

    // 2. Fetch or create a valid reservation_id (handling multiple reservations cleanly)
    $stmtRes = $pdo->prepare("
        SELECT reservation_id 
        FROM shioricafe_schema.reservation 
        WHERE customer_id = :customer_id AND status = 'Confirmed' 
        ORDER BY reservation_id DESC LIMIT 1
    ");
    $stmtRes->execute([':customer_id' => $customerId]);
    $reservationId = $stmtRes->fetchColumn();

    if (!$reservationId) {
        $stmtNewRes = $pdo->prepare("
            INSERT INTO shioricafe_schema.reservation (
                customer_id, table_id, party_size, reservation_start_at, reservation_end_at, status, is_walkin
            ) VALUES (
                :customer_id, :table_id, 1, NOW(), NOW() + INTERVAL '2 hours', 'Confirmed', false
            )
            RETURNING reservation_id
        ");
        $stmtNewRes->execute([
            ':customer_id' => $customerId,
            ':table_id'    => $tableNumber
        ]);
        $reservationId = $stmtNewRes->fetchColumn();
    }

    // 3. Insert into shioricafe_schema.order
    $sql = "
        INSERT INTO shioricafe_schema.\"order\" (
            reservation_id,
            table_id,
            order_type,
            preparation_status,
            gross_order_amount,
            currency
        ) VALUES (
            :reservation_id,
            :table_id,
            'Dine-In',
            'Pending',
            :gross_order_amount,
            'PHP'
        )
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':reservation_id'     => $reservationId,
        ':table_id'           => $tableNumber,
        ':gross_order_amount' => $totalAmount
    ]);

    $orderId = $pdo->lastInsertId();

    echo json_encode([
        'status'       => 'success',
        'success'      => true,
        'order_id'     => $orderId,
        'total_amount' => $totalAmount,
        'message'      => 'Order placed successfully!'
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'success' => false,
        'message' => 'Order failed: ' . $e->getMessage()
    ]);
    exit;
}