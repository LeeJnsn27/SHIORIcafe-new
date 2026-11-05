<?php
session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Cashier') {
    header("Location: login.php");
    exit();
}
require_once "../backend/config/db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error_msg'] = "Invalid CSRF token.";
        header("Location: queue.php");
        exit();
    }

    $order_id = $_POST['order_id'];
    $new_status = $_POST['new_status'];

    try {
        $conn->beginTransaction();
        
        // SELECT ... FOR UPDATE applies a row-level lock preventing race conditions
        $lock_stmt = $conn->prepare("SELECT preparation_status FROM shioricafe_schema.\"order\" WHERE order_id = ? FOR UPDATE");
        $lock_stmt->execute([$order_id]);
        
        if ($lock_stmt->fetch(PDO::FETCH_ASSOC)) {
            $update_stmt = $conn->prepare("UPDATE shioricafe_schema.\"order\" SET preparation_status = ?, updated_at = NOW() WHERE order_id = ?");
            $update_stmt->execute([$new_status, $order_id]);
            $_SESSION['success_msg'] = "Order #$order_id advanced to $new_status.";
        }
        
        $conn->commit();
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error_msg'] = "Database error: Failed to update order.";
    }
}
header("Location: queue.php");
exit();
?>