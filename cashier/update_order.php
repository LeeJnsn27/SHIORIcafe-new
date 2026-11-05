<?php
session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Cashier') {
    header("Location: login.php");
    exit();
}

require_once "../backend/config/db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['order_id']) && isset($_POST['new_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['new_status'];

    // Validate allowed statuses based on the PostgreSQL schema constraints
    $allowed_statuses = ['Pending', 'Preparing', 'Ready', 'Served', 'Completed'];
    
    if (in_array($new_status, $allowed_statuses)) {
        $update_query = "
            UPDATE shioricafe_schema.\"order\" 
            SET preparation_status = $1, updated_at = NOW() 
            WHERE order_id = $2
        ";
        
        $result = pg_query_params($conn, $update_query, array($new_status, $order_id));
        
        if ($result) {
            $_SESSION['success_msg'] = "Order #$order_id updated to $new_status.";
        } else {
            $_SESSION['error_msg'] = "Database error updating order.";
        }
    } else {
        $_SESSION['error_msg'] = "Invalid status selected.";
    }
}

// Redirect back to the queue
header("Location: queue.php");
exit();
?>