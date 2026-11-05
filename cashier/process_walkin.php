<?php
session_start();
require_once "../backend/config/db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Security error.");
    }
    
    $order_type = $_POST['order_type'];
    $table_id = !empty($_POST['table_id']) ? $_POST['table_id'] : null;
    $items = $_POST['items'] ?? [];
    
    if (empty($items)) {
        $_SESSION['error_msg'] = "Cannot submit an empty order.";
        header("Location: pos.php");
        exit();
    }

    try {
        $conn->beginTransaction();
        
        // 1. Create Main Order
        $stmt = $conn->prepare("INSERT INTO shioricafe_schema.\"order\" (order_type, table_id, preparation_status, created_at, gross_order_amount) VALUES (?, ?, 'Pending', NOW(), 0) RETURNING order_id");
        $stmt->execute([$order_type, $table_id]);
        $new_order_id = $stmt->fetchColumn();

        // 2. Insert Line Items and Calculate Total
        $total_amount = 0;
        $price_stmt = $conn->prepare("SELECT price FROM shioricafe_schema.menu_item WHERE menu_item_id = ?");
        $insert_item = $conn->prepare("INSERT INTO shioricafe_schema.order_item (order_id, menu_item_id, quantity, subtotal) VALUES (?, ?, ?, ?)");
        
        foreach ($items as $item_id => $qty) {
            $price_stmt->execute([$item_id]);
            $price = $price_stmt->fetchColumn();
            $subtotal = $price * $qty;
            $total_amount += $subtotal;
            
            $insert_item->execute([$new_order_id, $item_id, $qty, $subtotal]);
        }
        
        // 3. Update Final Order Total
        $update_total = $conn->prepare("UPDATE shioricafe_schema.\"order\" SET gross_order_amount = ? WHERE order_id = ?");
        $update_total->execute([$total_amount, $new_order_id]);

        // 4. Reserve Table for Dine-In
        if ($table_id && $order_type === 'Dine-In') {
            $table_stmt = $conn->prepare("INSERT INTO shioricafe_schema.reservation (table_id, status, reservation_start_at, checked_in_at) VALUES (?, 'Occupied', NOW(), NOW())");
            $table_stmt->execute([$table_id]);
        }
        
        $conn->commit();
        $_SESSION['success_msg'] = "Order #$new_order_id submitted successfully.";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error_msg'] = "Transaction failed: " . $e->getMessage();
    }
}
header("Location: queue.php");
exit();
?>