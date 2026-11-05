<?php
// admin/cashier/orders.php - Active Kitchen & Order Queue
require_once __DIR__ . '/../../backend/auth/auth-guard.php';
require_role(['admin', 'cashier']);

require_once __DIR__ . '/../../backend/config/db.php';
/** @var PDO $pdo */

$db = isset($pdo) ? $pdo : (isset($conn) ? $conn : null);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$orders = [];
$error_message = null;

try {
    $query = "
        SELECT o.order_id, o.order_type, t.table_number, o.gross_order_amount, o.preparation_status, o.created_at,
               string_agg(COALESCE(mi.name, mi.item_name, 'Item') || ' (x' || oi.quantity || ')', ', ') AS order_details
        FROM shioricafe_schema.\"order\" o
        LEFT JOIN shioricafe_schema.\"table\" t ON o.table_id = t.table_id
        LEFT JOIN shioricafe_schema.order_item oi ON o.order_id = oi.order_id
        LEFT JOIN shioricafe_schema.menu_item mi ON oi.menu_item_id = mi.menu_item_id
        WHERE o.voided_at IS NULL AND o.preparation_status != 'Completed'
        GROUP BY o.order_id, o.order_type, t.table_number, o.gross_order_amount, o.preparation_status, o.created_at
        ORDER BY o.created_at ASC
    ";
    $stmt = $db->query($query);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Error fetching order queue: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SHIORI Café Admin | Active Order Queue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../../frontend/assets/css/admin.css">
</head>
<body class="bg-light">

    <?php require_once __DIR__ . "/../admin-navbar.php"; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1"><i class="bi bi-list-task me-2"></i>Active Order Queue</h2>
                <p class="text-muted mb-0">Monitor and update active kitchen & counter order preparation statuses</p>
            </div>
            <a href="pos.php" class="btn btn-success fw-semibold">
                <i class="bi bi-calculator me-1"></i> Open POS Terminal
            </a>
        </div>

        <!-- Session Feedback Alerts -->
        <?php if (!empty($_SESSION['success_msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($_SESSION['success_msg']); unset($_SESSION['success_msg']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($_SESSION['error_msg'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($_SESSION['error_msg']); unset($_SESSION['error_msg']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="card-title fw-bold mb-0">Pending Orders</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Order ID</th>
                                <th>Type / Table</th>
                                <th>Ordered Items</th>
                                <th>Total</th>
                                <th>Current Status</th>
                                <th style="width: 240px;">Update Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="bi bi-check2-circle fs-3 d-block mb-2 text-success"></i>
                                        All orders are completed! Queue is clear.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $row): 
                                    $status = $row['preparation_status'];
                                    $badgeClass = match($status) {
                                        'Pending'   => 'bg-warning text-dark',
                                        'Preparing' => 'bg-info text-dark',
                                        'Ready'     => 'bg-primary',
                                        'Served'    => 'bg-secondary',
                                        default     => 'bg-dark'
                                    };
                                ?>
                                    <tr>
                                        <td class="fw-bold">#<?= htmlspecialchars($row['order_id']) ?></td>
                                        <td>
                                            <span class="fw-semibold"><?= htmlspecialchars($row['order_type']) ?></span>
                                            <br>
                                            <small class="text-muted">Table <?= htmlspecialchars($row['table_number'] ?? 'N/A') ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($row['order_details'] ?? 'No items') ?></td>
                                        <td class="fw-semibold text-success">₱<?= number_format(floatval($row['gross_order_amount']), 2) ?></td>
                                        <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span></td>
                                        <td>
                                            <form action="../../backend/api/update-order.php" method="POST" class="d-flex align-items-center gap-2">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="order_id" value="<?= htmlspecialchars($row['order_id']) ?>">
                                                <select name="new_status" class="form-select form-select-sm" required>
                                                    <option value="Preparing" <?= $status === 'Preparing' ? 'selected' : '' ?>>Preparing</option>
                                                    <option value="Ready" <?= $status === 'Ready' ? 'selected' : '' ?>>Ready</option>
                                                    <option value="Served" <?= $status === 'Served' ? 'selected' : '' ?>>Served</option>
                                                    <option value="Completed" <?= $status === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                                </select>
                                                <button type="submit" class="btn btn-sm btn-primary px-3 fw-semibold">Save</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>