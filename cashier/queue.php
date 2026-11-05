<?php
session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Cashier') {
    header("Location: login.php");
    exit();
}
require_once "../backend/config/db.php";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Group items using PostgreSQL string_agg and fetch all orders
$query = "
    SELECT o.order_id, o.order_type, t.table_number, o.gross_order_amount, o.preparation_status,
           string_agg(mi.name || ' (x' || oi.quantity || ')', ', ') AS order_details
    FROM shioricafe_schema.\"order\" o
    LEFT JOIN shioricafe_schema.\"table\" t ON o.table_id = t.table_id
    LEFT JOIN shioricafe_schema.order_item oi ON o.order_id = oi.order_id
    LEFT JOIN shioricafe_schema.menu_item mi ON oi.menu_item_id = mi.menu_item_id
    WHERE o.voided_at IS NULL
    GROUP BY o.order_id, t.table_number
    ORDER BY o.created_at ASC
";
$stmt = $conn->query($query);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Queue | Cashier Terminal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <h2 class="mb-4">Live Order Queue</h2>
        
        <ul class="nav nav-tabs mb-4" id="queueTabs" role="tablist">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#pending">Pending</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#active">Active/Preparing</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#completed">Completed</a></li>
        </ul>

        <div class="tab-content">
            <!-- Repeated for each tab section with PHP conditional filtering -->
            <div class="tab-pane fade show active" id="pending">
                <table class="table table-hover">
                    <thead><tr><th>Order ID</th><th>Type</th><th>Items</th><th>Total</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($orders as $row): if ($row['preparation_status'] === 'Pending'): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($row['order_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['order_type']); ?></td>
                                <td><?php echo htmlspecialchars($row['order_details'] ?? 'No items'); ?></td>
                                <td>₱<?php echo number_format($row['gross_order_amount'], 2); ?></td>
                                <td><span class="badge bg-warning text-dark"><?php echo htmlspecialchars($row['preparation_status']); ?></span></td>
                                <td>
                                    <form action="update_order.php" method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($row['order_id']); ?>">
                                        <select name="new_status" class="form-select form-select-sm d-inline w-auto" required>
                                            <option value="Preparing">Move to Preparing</option>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-primary">Commit</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endif; endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Additional tab divs (Active, Completed) follow the exact same structure filtering by preparation_status -->
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>