<?php
session_start();
require_once "../backend/config/db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // CSRF Check...
    
    $order_type = $_POST['order_type'];
    $table_id = !empty($_POST['table_id']) ? $_POST['table_id'] : null;
    
    try {
        $conn->beginTransaction();
        
        // 1. Insert Order
        $stmt = $conn->prepare("INSERT INTO shioricafe_schema.\"order\" (order_type, table_id, preparation_status, created_at) VALUES (?, ?, 'Pending', NOW()) RETURNING order_id");
        $stmt->execute([$order_type, $table_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        $new_order_id = $order['order_id'];

        // 2. Process Cart Items & Price Calculations here (Requires order_item inserts based on POS POST payload)
        
        // 3. Mark Table as Occupied if Dine-In
        if ($table_id && $order_type === 'Dine-In') {
            $table_stmt = $conn->prepare("INSERT INTO shioricafe_schema.reservation (table_id, status, reservation_start_at, checked_in_at) VALUES (?, 'Occupied', NOW(), NOW())");
            $table_stmt->execute([$table_id]);
        }
        
        $conn->commit();
        $_SESSION['success_msg'] = "Walk-in order created successfully.";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error_msg'] = "Transaction failed.";
    }
}
header("Location: queue.php");
exit();
?>