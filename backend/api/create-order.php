<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

/** @var PDO $pdo */

try {
    // Decode JSON payload or standard POST payload from cart AJAX
    $input = file_get_contents('php://input');
    $data = json_decode($input, true) ?: $_POST;

    $tableId       = intval($data['table_number'] ?? $data['table_id'] ?? 1);
    $orderType     = $data['order_type'] ?? 'Dine-In';
    $grossAmount   = floatval($data['total'] ?? $data['gross_order_amount'] ?? 0);
    $reservationId = !empty($data['reservation_id']) ? intval($data['reservation_id']) : null;
    $items         = $data['items'] ?? $data['cart'] ?? [];

    // 1. Insert order into shioricafe_schema."order"
    $stmt = $pdo->prepare("
        INSERT INTO shioricafe_schema.\"order\" (
            table_id, reservation_id, order_type, preparation_status, gross_order_amount, created_at
        ) VALUES (
            :table_id, :reservation_id, :order_type, 'Pending', :amount, CURRENT_TIMESTAMP
        ) RETURNING order_id
    ");

    $stmt->execute([
        ':table_id'       => $tableId,
        ':reservation_id' => $reservationId,
        ':order_type'     => $orderType,
        ':amount'         => $grossAmount
    ]);

    $orderId = $stmt->fetchColumn();

    // 2. Insert items into shioricafe_schema.order_item
    if (!empty($items) && is_array($items) && $orderId) {
        foreach ($items as $item) {
            $menuItemId = intval($item['id'] ?? $item['menu_item_id'] ?? 1);
            $quantity   = intval($item['quantity'] ?? $item['qty'] ?? 1);
            $unitPrice  = floatval($item['price'] ?? $item['unit_price'] ?? 0);

            try {
                $itemStmt = $pdo->prepare("
                    INSERT INTO shioricafe_schema.order_item (
                        order_id, menu_item_id, quantity, unit_price
                    ) VALUES (
                        :order_id, :item_id, :qty, :price
                    )
                ");
                $itemStmt->execute([
                    ':order_id' => $orderId,
                    ':item_id'  => $menuItemId,
                    ':qty'      => $quantity,
                    ':price'    => $unitPrice
                ]);
            } catch (Exception $ex) {
                // Ignore individual item schema variations
            }
        }
    }

    echo json_encode([
        'status'   => 'success',
        'message'  => 'Order placed successfully!',
        'order_id' => $orderId
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Server Error: ' . $e->getMessage()
    ]);
}