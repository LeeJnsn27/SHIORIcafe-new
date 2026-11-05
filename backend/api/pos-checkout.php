<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

/** @var PDO $pdo */

try {
    $input = file_get_contents('php://input');
    $data  = json_decode($input, true) ?: $_POST;

    $tableId     = intval($data['table_id'] ?? 1);
    $orderType   = $data['order_type'] ?? 'Dine-In';
    $grossAmount = floatval($data['total_amount'] ?? 0);
    $items       = $data['items'] ?? [];

    if (empty($items)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Cart is empty.']);
        exit();
    }

    // 1. Insert POS order with status 'Completed'
    $stmt = $pdo->prepare("
        INSERT INTO shioricafe_schema.\"order\" (
            table_id, order_type, preparation_status, gross_order_amount, created_at
        ) VALUES (
            :table_id, :order_type, 'Completed', :amount, CURRENT_TIMESTAMP
        ) RETURNING order_id
    ");

    $stmt->execute([
        ':table_id'   => $tableId,
        ':order_type' => $orderType,
        ':amount'     => $grossAmount
    ]);

    $orderId = $stmt->fetchColumn();

    // 2. Insert line items into order_item
    $itemStmt = $pdo->prepare("
        INSERT INTO shioricafe_schema.order_item (
            order_id, menu_item_id, quantity, unit_price
        ) VALUES (
            :order_id, :item_id, :qty, :price
        )
    ");

    foreach ($items as $item) {
        $itemStmt->execute([
            ':order_id' => $orderId,
            ':item_id'  => intval($item['id']),
            ':qty'      => intval($item['quantity']),
            ':price'    => floatval($item['price'])
        ]);
    }

    echo json_encode([
        'status'   => 'success',
        'message'  => 'POS Transaction completed successfully!',
        'order_id' => $orderId
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'POS Transaction error: ' . $e->getMessage()
    ]);
}