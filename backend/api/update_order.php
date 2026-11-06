<?php
// backend/api/update-order.php - Order Preparation Status Update Handler

require_once __DIR__ . '/../auth/auth-guard.php';
require_role(['admin', 'cashier']); // Restrict to Cashier and Admin staff

require_once __DIR__ . '/../config/db.php';
/** @var PDO $pdo */

$db = isset($pdo) ? $pdo : (isset($conn) ? $conn : null);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // 1. Verify CSRF Token
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (empty($submittedToken) || !hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
        $_SESSION['error_msg'] = "Invalid CSRF security token.";
        header("Location: /SHIORIcafe/admin/cashier/orders.php");
        exit();
    }

    $order_id   = intval($_POST['order_id'] ?? 0);
    $new_status = trim($_POST['new_status'] ?? '');

    $allowedStatuses = ['Pending', 'Preparing', 'Ready', 'Served', 'Completed'];

    if ($order_id > 0 && in_array($new_status, $allowedStatuses, true)) {
        try {
            $db->beginTransaction();

            // Row-level lock to prevent concurrent update conflicts
            $lock_stmt = $db->prepare("
                SELECT preparation_status
                FROM shioricafe_schema.\"order\"
                WHERE order_id = :order_id
                FOR UPDATE
            ");
            $lock_stmt->execute([':order_id' => $order_id]);

            if ($lock_stmt->fetch(PDO::FETCH_ASSOC)) {
                $update_stmt = $db->prepare("
                    UPDATE shioricafe_schema.\"order\"
                    SET preparation_status = :status,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE order_id = :order_id
                ");
                $update_stmt->execute([
                    ':status'   => $new_status,
                    ':order_id' => $order_id
                ]);

                $db->commit();
                $_SESSION['success_msg'] = "Order #$order_id advanced to $new_status.";
            } else {
                $db->rollBack();
                $_SESSION['error_msg'] = "Order #$order_id not found.";
            }
        } catch (Exception $e) {
            if ($db && $db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['error_msg'] = "Database error: Failed to update order status.";
        }
    } else {
        $_SESSION['error_msg'] = "Invalid order data or status selection.";
    }
}

// Fallback redirect back to the active order queue screen
header("Location: /SHIORIcafe/admin/cashier/orders.php");
exit();