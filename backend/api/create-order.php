
<?php
// Set response header to return JSON
header("Content-Type: application/json");

// Connect to the PostgreSQL database
require_once "../config/db.php";

// Read data sent from the order checkout
$request = json_decode(file_get_contents("php://input"), true);

$tableNumber   = trim($request["tableNumber"] ?? "");
$paymentMethod = trim($request["paymentMethod"] ?? "");
$receiptEmail  = trim($request["receiptEmail"] ?? "");
$items         = $request["items"] ?? [];

// Café menu prices list
$menuPrices = [
    "Hojicha Latte"  => 150.00,
    "Spanish Latte"  => 140.00,
    "Matcha"         => 160.00,
    "Castella Cake"  => 120.00,
    "Genmaicha"      => 130.00,
    "Yuzu Honey Tea" => 135.00,
    "Dorayaki"       => 110.00,
    "Onigiri"        => 95.00
];

// Check basic requirements
if ($tableNumber == "" || empty($items)) {
    echo json_encode(["error" => "Table number and at least one item are required."]);
    exit;
}

// Calculate the total order cost safely
$total = 0.00;
foreach ($items as $item) {
    $name = $item["name"] ?? "";
    $qty  = (int)($item["quantity"] ?? 1);
    if ($name !== "" && isset($menuPrices[$name])) {
        $total += $menuPrices[$name] * $qty;
    }
}

try {
    // Step 1: Save the main order record
    $orderSql = "INSERT INTO orders (table_number, payment_method, receipt_email, total)
                 VALUES (?, ?, ?, ?) RETURNING order_id";

    $stmt = $conn->prepare($orderSql);
    $stmt->execute([$tableNumber, $paymentMethod, $receiptEmail, $total]);

    $orderId = $stmt->fetchColumn();

    // Step 2: Loop through each item and save it in order_items table
    foreach ($items as $item) {
        $itemName  = $item["name"] ?? "Unknown Item";
        $quantity  = (int)($item["quantity"] ?? 1);
        $itemPrice = $menuPrices[$itemName] ?? 0.00;

        $itemSql = "INSERT INTO order_items (order_id, item_name, price, quantity)
                    VALUES (?, ?, ?, ?)";

        $itemStmt = $conn->prepare($itemSql);
        $itemStmt->execute([$orderId, $itemName, $itemPrice, $quantity]);
    }

    echo json_encode(["success" => true, "orderId" => $orderId, "total" => $total]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Order creation failed: " . $e->getMessage());
    echo json_encode(["error" => "Failed to save order."]);
}
?>
