<?php
// backend/api/process-walkin.php - Walk-In Reservation & Order Processing Endpoint

require_once __DIR__ . '/../auth/auth-guard.php';
require_role(['admin', 'cashier']); // Restrict to Cashiers and Admins

require_once __DIR__ . '/../config/db.php';
/** @var PDO $pdo */

$db = isset($pdo) ? $pdo : (isset($conn) ? $conn : null);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // 1. CSRF Security Check
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (empty($submittedToken) || !hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
        $_SESSION['error_msg'] = "Security error: Invalid CSRF token.";
        header("Location: /SHIORIcafe/admin/cashier/pos.php");
        exit();
    }

    $order_type = $_POST['order_type'] ?? 'Dine-In';
    $table_id   = !empty($_POST['table_id']) ? intval($_POST['table_id']) : null;
    $items      = $_POST['items'] ?? [];
    $staff_id   = get_current_user_id() ?? 1; // Log current session user ID

    if (empty($items)) {
        $_SESSION['error_msg'] = "Cannot submit an empty order.";
        header("Location: /SHIORIcafe/admin/cashier/pos.php");
        exit();
    }

    try {
        $db->beginTransaction();

        // 1. Create Walk-In Reservation FIRST to satisfy DB foreign key requirements
        $res_stmt = $db->prepare("
            INSERT INTO shioricafe_schema.reservation 
            (table_id, status, reservation_start_at, checked_in_at, reservation_type, checked_in_by) 
            VALUES (:table_id, 'Occupied', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 'Walk-in', :staff_id) 
            RETURNING reservation_id
        ");
        $res_stmt->execute([
            ':table_id' => $table_id,
            ':staff_id' => $staff_id
        ]);
        $reservation_id = $res_stmt->fetchColumn();

        // 2. Create Order linked with the newly generated reservation_id
        $stmt = $db->prepare("
            INSERT INTO shioricafe_schema.\"order\" 
            (order_type, table_id, reservation_id, preparation_status, created_at, gross_order_amount) 
            VALUES (:order_type, :table_id, :res_id, 'Pending', CURRENT_TIMESTAMP, 0) 
            RETURNING order_id
        ");
        $stmt->execute([
            ':order_type' => $order_type,
            ':table_id'   => $table_id,
            ':res_id'     => $reservation_id
        ]);
        $new_order_id = $stmt->fetchColumn();

        // 3. Process Cart items and compute totals
        $total_amount = 0;
        $price_stmt = $db->prepare("
            SELECT price FROM shioricafe_schema.menu_item WHERE menu_item_id = :item_id
        ");
        $insert_item = $db->prepare("
            INSERT INTO shioricafe_schema.order_item 
            (order_id, menu_item_id, quantity, unit_price) 
            VALUES (:order_id, :item_id, :qty, :unit_price)
        ");

        foreach ($items as $item_id => $qty) {
            $item_id = intval($item_id);
            $qty     = intval($qty);
            if ($item_id <= 0 || $qty <= 0) continue;

            $price_stmt->execute([':item_id' => $item_id]);
            $price = floatval($price_stmt->fetchColumn());
            $subtotal = $price * $qty;
            $total_amount += $subtotal;

            $insert_item->execute([
                ':order_id'   => $new_order_id,
                ':item_id'    => $item_id,
                ':qty'        => $qty,
                ':unit_price' => $price
            ]);
        }

        // 4. Update Gross Order Total
        $update_total = $db->prepare("
            UPDATE shioricafe_schema.\"order\" 
            SET gross_order_amount = :total 
            WHERE order_id = :order_id
        ");
        $update_total->execute([
            ':total'    => $total_amount,
            ':order_id' => $new_order_id
        ]);

        $db->commit();
        $_SESSION['success_msg'] = "Walk-in order #$new_order_id submitted successfully.";
        header("Location: /SHIORIcafe/admin/cashier/orders.php");
        exit();

    } catch (Exception $e) {
        if ($db && $db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['error_msg'] = "Transaction failed: " . $e->getMessage();
        header("Location: /SHIORIcafe/admin/cashier/pos.php");
        exit();
    }
}

// Fallback redirect if accessed without POST
header("Location: /SHIORIcafe/admin/cashier/pos.php");
exit();