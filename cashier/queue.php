<?php
session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Cashier') {
    header("Location: login.php");
    exit();
}
require_once "../backend/config/db.php";

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// 1. Updated mi.name to mi.item_name
$query = "
    SELECT o.order_id, o.order_type, t.table_number, o.gross_order_amount, o.preparation_status,
           string_agg(mi.item_name || ' (x' || oi.quantity || ')', ', ') AS order_details
    FROM shioricafe_schema.\"order\" o
    LEFT JOIN shioricafe_schema.\"table\" t ON o.table_id = t.table_id
    LEFT JOIN shioricafe_schema.order_item oi ON o.order_id = oi.order_id
    LEFT JOIN shioricafe_schema.menu_item mi ON oi.menu_item_id = mi.menu_item_id
    WHERE o.voided_at IS NULL AND o.preparation_status != 'Completed'
    GROUP BY o.order_id, t.table_number
    ORDER BY o.created_at ASC
";
$orders = $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Active Order Queue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-5">
    <h2>Active Order Queue</h2>
    
    <!-- 2. Display BOTH success and error messages from session -->
    <?php if (!empty($_SESSION['success_msg'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success_msg']); unset($_SESSION['success_msg']); ?></div>
    <?php endif; ?>
    
    <?php if (!empty($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error_msg']); unset($_SESSION['error_msg']); ?></div>
    <?php endif; ?>

    <table class="table table-white shadow-sm mt-3">
        <thead><tr><th>Order ID</th><th>Details</th><th>Items</th><th>Total</th><th>Status</th><th>Update</th></tr></thead>
        <tbody>
            <?php foreach ($orders as $row): ?>
                <tr>
                    <td class="fw-bold">#<?php echo $row['order_id']; ?></td>
                    <td><?php echo $row['order_type']; ?> <br> <small class="text-muted">Table <?php echo $row['table_number'] ?? 'N/A'; ?></small></td>
                    <td><?php echo htmlspecialchars($row['order_details'] ?? 'No items'); ?></td>
                    <td>₱<?php echo number_format($row['gross_order_amount'], 2); ?></td>
                    <td><span class="badge bg-secondary"><?php echo $row['preparation_status']; ?></span></td>
                    <td>
                        <form action="update_order.php" method="POST" class="d-flex">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="order_id" value="<?php echo $row['order_id']; ?>">
                            <select name="new_status" class="form-select form-select-sm me-2" required>
                                <option value="Preparing">Preparing</option>
                                <option value="Ready">Ready</option>
                                <option value="Served">Served</option>
                                <option value="Completed">Completed</option>
                            </select>
                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>