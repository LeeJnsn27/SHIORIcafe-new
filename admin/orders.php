<?php
require_once "../backend/auth/auth-guard.php";
require_once "../backend/config/db.php";
/** @var PDO $pdo */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'], $_POST['order_id'])) {
    $orderId = (int)$_POST['order_id'];

    if ($_POST['action'] === 'update_status') {
        $newStatus = $_POST['status'] ?? 'Pending';
        $stmt = $pdo->prepare("UPDATE shioricafe_schema.\"order\" SET preparation_status = :status WHERE order_id = :id");
        $stmt->execute([':status' => $newStatus, ':id' => $orderId]);
    } elseif ($_POST['action'] === 'delete_order') {
        $stmtOrder = $pdo->prepare("UPDATE shioricafe_schema.\"order\" SET preparation_status = 'Cancelled' WHERE order_id = :id");
        $stmtOrder->execute([':id' => $orderId]);
    }

    header("Location: orders.php");
    exit();
}

try {
    $stmt = $pdo->query("SELECT * FROM shioricafe_schema.\"order\" ORDER BY created_at DESC");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch line items safely using a direct query on order_item
    foreach ($orders as &$order) {
        try {
            $itemStmt = $pdo->prepare("
                SELECT oi.* 
                FROM shioricafe_schema.order_item oi 
                WHERE oi.order_id = :order_id
            ");
            $itemStmt->execute([':order_id' => $order['order_id']]);
            $rawItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

            // Normalize item keys so the view can always read them
            $formattedItems = [];
            foreach ($rawItems as $ri) {
                $formattedItems[] = [
                    'item_name'  => $ri['item_name_at_purchase'] ?? $ri['item_name'] ?? 'Café Item',
                    'quantity'   => $ri['quantity'] ?? $ri['qty'] ?? 1,
                    'unit_price' => $ri['unit_price_at_purchase'] ?? $ri['unit_price'] ?? 0
                ];
            }
            $order['line_items'] = $formattedItems;
        } catch (Exception $ex) {
            $order['line_items'] = [];
        }
    }
    unset($order);

} catch (Exception $e) {
    $orders = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SHIORI Café Admin | Orders</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../frontend/assets/css/admin.css">
</head>
<body>

    <?php require_once "admin-navbar.php"; ?>

    <div class="container py-5">
        <h1 class="admin-page-title">Customer Orders Control</h1>
        <p class="admin-page-subtitle">Manage live checkouts and execute administrative order actions.</p>

        <div class="row row-cols-1 row-cols-md-2 g-4">
            <?php if (!empty($orders)): ?>
                <?php foreach ($orders as $order): 
                    $status = $order['preparation_status'] ?? 'Pending';
                    $badgeClass = ($status === 'Cancelled') ? 'bg-danger' : (($status === 'Completed') ? 'bg-success' : 'bg-warning text-dark');
                    
                    $orderItems = $order['line_items'] ?? [];
                    $amount = floatval($order['gross_order_amount'] ?? 0);

                    // Fallback amount calculation if gross amount is zero
                    if ($amount <= 0 && !empty($orderItems)) {
                        foreach ($orderItems as $it) {
                            $amount += floatval($it['unit_price']) * intval($it['quantity']);
                        }
                    }
                ?>
                    <div class="col">
                        <div class="admin-card order-card h-100 p-4">
                            <div class="order-card-header d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <div class="order-table-label fw-bold">Table <?php echo htmlspecialchars($order['table_id'] ?? 'N/A'); ?></div>
                                    <div class="order-id text-muted small">Order #<?php echo $order['order_id']; ?></div>
                                </div>
                                <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                            </div>

                            <div class="mb-3">
                                <span class="text-muted small">Order Type:</span>
                                <div class="fw-semibold mb-2"><?php echo htmlspecialchars($order['order_type'] ?? 'Dine-In'); ?></div>

                                <span class="text-muted small">Ordered Items:</span>
                                <ul class="list-unstyled small bg-light p-2 rounded mb-0">
                                    <?php if (!empty($orderItems)): ?>
                                        <?php foreach ($orderItems as $item): 
                                            $lineTotal = floatval($item['unit_price']) * intval($item['quantity']);
                                        ?>
                                            <li class="d-flex justify-content-between border-bottom pb-1 mb-1">
                                                <span><?php echo htmlspecialchars($item['item_name']); ?> <span class="text-muted">x<?php echo $item['quantity']; ?></span></span>
                                                <span class="fw-semibold">₱<?php echo number_format($lineTotal, 2); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li class="text-muted fst-italic">No line items found.</li>
                                    <?php endif; ?>
                                </ul>
                            </div>

                            <div class="order-footer d-flex justify-content-between align-items-center border-top pt-3 mt-auto">
                                <div>
                                    <div class="order-total fs-5 fw-bold text-success">₱<?php echo number_format($amount, 2); ?></div>
                                    <div class="order-payment text-muted small">
                                        <?php echo date('M d, Y h:i A', strtotime($order['created_at'] ?? 'now')); ?>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <?php if ($status !== 'Cancelled'): ?>
                                        <form action="orders.php" method="POST" onsubmit="return confirm('Cancel and archive Order #<?php echo $order['order_id']; ?>?');">
                                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                            <input type="hidden" name="action" value="delete_order">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-x-circle me-1"></i>Cancel & Archive
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small fst-italic">Archived</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="admin-card text-center py-5">
                        <p class="text-muted mb-0">No active customer orders found in the database.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>