<?php
// backend/api/create-order.php - Customer Online Order Processing API
header('Content-Type: application/json');

require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../config/db.php';
/** @var PDO $pdo */

// Ensure customer is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Please log in to place an order.']);
    exit();
}

try {
    $input = file_get_contents('php://input');
    $data  = json_decode($input, true) ?: $_POST;

    $customerId = get_current_user_id();
    $tableId    = intval($data['table_id'] ?? 1);
    $orderType  = $data['order_type'] ?? 'Dine-In';
    $items      = $data['items'] ?? $data['cart'] ?? [];

    if (empty($items)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Your cart is empty.']);
        exit();
    }

    // Begin atomic transaction
    $pdo->beginTransaction();

    // Calculate gross amount & validate items
    $calculatedGross = 0.0;
    $validatedItems = [];

    foreach ($items as $item) {
        $itemId    = intval($item['id'] ?? $item['menu_item_id'] ?? 0);
        $quantity  = intval($item['quantity'] ?? $item['qty'] ?? 1);
        $unitPrice = floatval($item['price'] ?? $item['unit_price'] ?? 0);
        $itemName  = $item['name'] ?? $item['item_name'] ?? 'Café Item';

        if ($itemId <= 0 || $quantity <= 0) {
            continue;
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

    if (empty($validatedItems)) {
        throw new Exception("No valid items found in order.");
    }

    // Insert order header into shioricafe_schema."order"
    $stmt = $pdo->prepare("
        INSERT INTO shioricafe_schema.\"order\" (
            table_id, customer_id, order_type, preparation_status, gross_order_amount, currency, created_at
        ) VALUES (
            :table_id, :customer_id, :order_type, 'Pending', :amount, 'PHP', CURRENT_TIMESTAMP
        ) RETURNING order_id
    ");

    $stmt->execute([
        ':table_id'    => $tableId,
        ':customer_id' => $customerId,
        ':order_type'  => $orderType,
        ':amount'      => $calculatedGross
    ]);

    $orderId = $stmt->fetchColumn();

    if (!$orderId) {
        throw new Exception("Failed to generate order record.");
    }

    // Insert line items into shioricafe_schema.order_item
    $itemStmt = $pdo->prepare("
        INSERT INTO shioricafe_schema.order_item (
            order_id, menu_item_id, item_name_at_purchase, quantity, unit_price_at_purchase
        ) VALUES (
            :order_id, :item_id, :item_name, :qty, :price
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

    $pdo->commit();

    echo json_encode([
        'status'   => 'success',
        'message'  => 'Order successfully placed!',
        'order_id' => $orderId,
        'total'    => $calculatedGross
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Order creation error: ' . $e->getMessage()
    ]);
}