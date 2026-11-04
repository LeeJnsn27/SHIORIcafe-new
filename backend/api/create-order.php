<?php
// Set response header to return JSON
header("Content-Type: application/json");

// Connect to the database (goes up one level from api/ to backend/, then into config/)
require_once "../config/db.php";
/** @var mysqli $conn */

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

// Step 1: Save the main order record
$orderSql = "INSERT INTO orders (table_number, payment_method, receipt_email, total)
             VALUES (?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $orderSql);
mysqli_stmt_bind_param($stmt, "sssd", $tableNumber, $paymentMethod, $receiptEmail, $total);

if (mysqli_stmt_execute($stmt)) {
    $orderId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    // Step 2: Loop through each item and save it in order_items table
    foreach ($items as $item) {
        $itemName  = $item["name"] ?? "Unknown Item";
        $quantity  = (int)($item["quantity"] ?? 1);
        $itemPrice = $menuPrices[$itemName] ?? 0.00;

        $itemSql = "INSERT INTO order_items (order_id, item_name, price, quantity)
                    VALUES (?, ?, ?, ?)";
        $itemStmt = mysqli_prepare($conn, $itemSql);
        mysqli_stmt_bind_param($itemStmt, "isdi", $orderId, $itemName, $itemPrice, $quantity);
        mysqli_stmt_execute($itemStmt);
        mysqli_stmt_close($itemStmt);
    }

    echo json_encode(["success" => true, "orderId" => $orderId, "total" => $total]);
} else {
    echo json_encode(["error" => "Failed to save order: " . mysqli_error($conn)]);
}

mysqli_close($conn);
?>
