<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

/** @var PDO $pdo */

try {
    $input = file_get_contents('php://input');
    $data  = json_decode($input, true) ?: $_POST;

    $tableId   = intval($data['table_id'] ?? $data['table_number'] ?? 1);
    $orderType = $data['order_type'] ?? 'Dine-In';
    $items     = $data['items'] ?? $data['cart'] ?? [];

    if (empty($items)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Your cart/ticket is empty.']);
        exit();
    }

    // Get a valid default menu_item_id as fallback
    $fallbackStmt = $pdo->query("SELECT menu_item_id FROM shioricafe_schema.menu_item ORDER BY menu_item_id ASC LIMIT 1");
    $validDefaultId = $fallbackStmt->fetchColumn() ?: 1;

    $calculatedGross = 0;
    $validatedItems  = [];

    foreach ($items as $item) {
        $itemId    = intval($item['id'] ?? $item['menu_item_id'] ?? $validDefaultId);
        $quantity  = intval($item['quantity'] ?? $item['qty'] ?? 1);
        $unitPrice = floatval($item['price'] ?? $item['unit_price'] ?? 0);
        $itemName  = $item['name'] ?? $item['item_name'] ?? 'Café Item';

        // Verify menu item exists to prevent foreign key errors
        $checkStmt = $pdo->prepare("SELECT menu_item_id FROM shioricafe_schema.menu_item WHERE menu_item_id = :id");
        $checkStmt->execute([':id' => $itemId]);
        if (!$checkStmt->fetchColumn()) {
            $itemId = $validDefaultId;
        }

        $lineTotal = $quantity * $unitPrice;
        $calculatedGross += $lineTotal;

        $validatedItems[] = [
            'id'    => $itemId,
            'name'  => $itemName,
            'qty'   => $quantity,
            'price' => $unitPrice
        ];
    }

    $clientTotal = floatval($data['total_amount'] ?? $data['total'] ?? 0);
    $finalGross = $clientTotal > 0 ? $clientTotal : $calculatedGross;

    // 1. Insert order into shioricafe_schema."order"
    $stmt = $pdo->prepare("
        INSERT INTO shioricafe_schema.\"order\" (
            table_id, order_type, preparation_status, gross_order_amount, currency, created_at
        ) VALUES (
            :table_id, :order_type, 'Pending', :amount, 'PHP', CURRENT_TIMESTAMP
        ) RETURNING order_id
    ");

    $stmt->execute([
        ':table_id'   => $tableId,
        ':order_type' => $orderType,
        ':amount'     => $finalGross
    ]);

    $orderId = $stmt->fetchColumn();

    // 2. Insert line items into shioricafe_schema.order_item
    if (!empty($validatedItems) && $orderId) {
        $itemStmt = $pdo->prepare("
            INSERT INTO shioricafe_schema.order_item (
                order_id, menu_item_id, item_name_at_purchase, quantity, unit_price_at_purchase, is_drink_category_at_purchase
            ) VALUES (
                :order_id, :item_id, :item_name, :qty, :price, 't'
            )
        ");

        foreach ($validatedItems as $vItem) {
            $itemStmt->execute([
                ':order_id'  => $orderId,
                ':item_id'   => $vItem['id'],
                ':item_name' => $vItem['name'],
                ':qty'       => $vItem['qty'],
                ':price'     => $vItem['price']
            ]);
        }
    }

    echo json_encode([
        'status'   => 'success',
        'message'  => 'Order successfully recorded!',
        'order_id' => $orderId,
        'total'    => $finalGross
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database Exception: ' . $e->getMessage()
    ]);
}