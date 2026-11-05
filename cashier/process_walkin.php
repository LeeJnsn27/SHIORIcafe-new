<?php
session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Cashier') {
    header("Location: login.php");
    exit();
}
require_once "../backend/config/db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Security error.");
    }
    
    $order_type = $_POST['order_type'];
    $table_id = !empty($_POST['table_id']) ? $_POST['table_id'] : null;
    $items = $_POST['items'] ?? [];
    $staff_id = $_SESSION['user_id'] ?? 1; // Fallback to 1 if session user_id isn't set yet
    
    if (empty($items)) {
        $_SESSION['error_msg'] = "Cannot submit an empty order.";
        header("Location: pos.php");
        exit();
    }

    try {
        $conn->beginTransaction();
        
        // 1. Create Reservation FIRST to satisfy database constraints
        $res_stmt = $conn->prepare("
            INSERT INTO shioricafe_schema.reservation 
            (table_id, status, reservation_start_at, checked_in_at, reservation_type, checked_in_by) 
            VALUES (?, 'Occupied', NOW(), NOW(), 'Walk-in', ?) 
            RETURNING reservation_id
        ");
        $res_stmt->execute([$table_id, $staff_id]);
        $reservation_id = $res_stmt->fetchColumn();

        // 2. Create Order WITH the newly generated reservation_id
        $stmt = $conn->prepare("
            INSERT INTO shioricafe_schema.\"order\" 
            (order_type, table_id, reservation_id, preparation_status, created_at, gross_order_amount) 
            VALUES (?, ?, ?, 'Pending', NOW(), 0) 
            RETURNING order_id
        ");
        $stmt->execute([$order_type, $table_id, $reservation_id]);
        $new_order_id = $stmt->fetchColumn();

        // 3. Process Cart with 'current_price' column fix
        $total_amount = 0;
        $price_stmt = $conn->prepare("SELECT current_price FROM shioricafe_schema.menu_item WHERE menu_item_id = ?");
        $insert_item = $conn->prepare("INSERT INTO shioricafe_schema.order_item (order_id, menu_item_id, quantity, subtotal) VALUES (?, ?, ?, ?)");
        
        foreach ($items as $item_id => $qty) {
            $price_stmt->execute([$item_id]);
            $price = $price_stmt->fetchColumn();
            $subtotal = $price * $qty;
            $total_amount += $subtotal;
            
            $insert_item->execute([$new_order_id, $item_id, $qty, $subtotal]);
        }
        
        // 4. Finalize Total
        $update_total = $conn->prepare("UPDATE shioricafe_schema.\"order\" SET gross_order_amount = ? WHERE order_id = ?");
        $update_total->execute([$total_amount, $new_order_id]);
        
        $conn->commit();
        $_SESSION['success_msg'] = "Walk-in order #$new_order_id submitted successfully.";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error_msg'] = "Transaction failed: " . $e->getMessage();
    }
}
header("Location: queue.php");
exit();
?>