<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

/** @var PDO $pdo */

try {
    $input = file_get_contents('php://input');
    $data  = json_decode($input, true) ?: $_POST;

    $tableId       = intval($data['table_number'] ?? $data['table_id'] ?? 1);
    $orderType     = $data['order_type'] ?? 'Dine-In';
    $reservationId = !empty($data['reservation_id']) ? intval($data['reservation_id']) : null;
    $items         = $data['items'] ?? $data['cart'] ?? [];

    if (empty($items)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Your cart is empty.']);
        exit();
    }

    // Fetch all columns safely without assuming a specific price column name
    $menuStmt = $pdo->query("SELECT * FROM shioricafe_schema.menu_item");
    $dbMenuItems = $menuStmt->fetchAll(PDO::FETCH_ASSOC);

    $menuById = [];
    $menuByName = [];
    foreach ($dbMenuItems as $m) {
        $p = $m['price'] ?? $m['unit_price'] ?? $m['item_price'] ?? $m['rate'] ?? 0;
        $m_id = $m['menu_item_id'] ?? $m['id'] ?? 1;
        $m_name = $m['item_name'] ?? $m['name'] ?? 'Item';

        $menuById[$m_id] = ['id' => $m_id, 'name' => $m_name, 'price' => floatval($p)];
        $menuByName[strtolower(trim($m_name))] = ['id' => $m_id, 'name' => $m_name, 'price' => floatval($p)];
    }

    $defaultId = !empty($dbMenuItems) ? (int)($dbMenuItems[0]['menu_item_id'] ?? $dbMenuItems[0]['id'] ?? 1) : 1;

    $calculatedGross = 0;
    $validatedItems  = [];

    foreach ($items as $item) {
        $rawId     = intval($item['id'] ?? $item['menu_item_id'] ?? 0);
        $itemName  = trim($item['name'] ?? $item['item_name'] ?? 'Café Item');
        $quantity  = intval($item['quantity'] ?? $item['qty'] ?? 1);
        $unitPrice = floatval($item['price'] ?? $item['unit_price'] ?? 0);

        $matchedId = $defaultId;

        if (isset($menuById[$rawId])) {
            $matchedId = $rawId;
            if ($unitPrice <= 0) $unitPrice = $menuById[$rawId]['price'];
        } elseif (isset($menuByName[strtolower($itemName)])) {
            $matchedId = $menuByName[strtolower($itemName)]['id'];
            if ($unitPrice <= 0) $unitPrice = $menuByName[strtolower($itemName)]['price'];
        } else {
            $matchedId = $defaultId;
        }

        $lineTotal = $quantity * $unitPrice;
        $calculatedGross += $lineTotal;

        $validatedItems[] = [
            'id'    => $matchedId,
            'name'  => $itemName,
            'qty'   => $quantity,
            'price' => $unitPrice
        ];
    }

    $clientTotal = floatval($data['total'] ?? $data['gross_order_amount'] ?? 0);
    $finalGross = $clientTotal > 0 ? $clientTotal : $calculatedGross;

    // 1. Insert order into shioricafe_schema."order"
    $stmt = $pdo->prepare("
        INSERT INTO shioricafe_schema.\"order\" (
            table_id, reservation_id, order_type, preparation_status, gross_order_amount, currency, created_at
        ) VALUES (
            :table_id, :reservation_id, :order_type, 'Pending', :amount, 'PHP', CURRENT_TIMESTAMP
        ) RETURNING order_id
    ");

    $stmt->execute([
        ':table_id'       => $tableId,
        ':reservation_id' => $reservationId,
        ':order_type'     => $orderType,
        ':amount'         => $finalGross
    ]);

    $orderId = $stmt->fetchColumn();

    // 2. Insert items into shioricafe_schema.order_item
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
        'message'  => 'Order placed successfully!',
        'order_id' => $orderId,
        'total'    => $finalGross
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Server Error: ' . $e->getMessage()
    ]);
}