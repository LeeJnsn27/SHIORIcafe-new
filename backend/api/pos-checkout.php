<?php
// backend/api/pos-checkout.php - Counter POS Checkout Processing API
header('Content-Type: application/json');

require_once __DIR__ . '/../auth/auth-guard.php';
require_role(['admin', 'cashier']); // Restrict checkout execution to authorized staff

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

    // Begin DB Transaction for atomic order execution
    $pdo->beginTransaction();

    // 1. Insert POS order header with status 'Completed'
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

    if (!$orderId) {
        throw new Exception("Failed to generate order record.");
    }

    // 2. Insert line items into order_item table
    $itemStmt = $pdo->prepare("
        INSERT INTO shioricafe_schema.order_item (
            order_id, menu_item_id, quantity, unit_price
        ) VALUES (
            :order_id, :item_id, :qty, :price
        )
    ");

    foreach ($items as $item) {
        $itemId = intval($item['id'] ?? 0);
        $qty    = intval($item['quantity'] ?? 0);
        $price  = floatval($item['price'] ?? 0);

        if ($itemId > 0 && $qty > 0) {
            $itemStmt->execute([
                ':order_id' => $orderId,
                ':item_id'  => $itemId,
                ':qty'      => $qty,
                ':price'    => $price
            ]);
        }
    }

    // Commit transaction if all inserts succeed
    $pdo->commit();

    echo json_encode([
        'status'   => 'success',
        'message'  => 'POS Transaction completed successfully!',
        'order_id' => $orderId
    ]);

} catch (Exception $e) {
    // Roll back transaction on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'POS Transaction error: ' . $e->getMessage()
    ]);
}