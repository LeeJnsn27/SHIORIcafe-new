<?php
require_once "../backend/auth/auth-guard.php";
require_once "../backend/config/db.php";
/** @var mysqli $conn */

$currentPage = basename($_SERVER['PHP_SELF']);

// Handle Admin Actions (Update Status or Delete)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'], $_POST['order_id'])) {
    $orderId = (int)$_POST['order_id'];

    if ($_POST['action'] === 'update_status') {
        $newStatus = mysqli_real_escape_string($conn, $_POST['status']);
        $stmt = mysqli_prepare($conn, "UPDATE orders SET payment_method = ? WHERE id = ?"); // Using payment_method or status column if available
        // Note: If you add a status column to orders table, adjust query accordingly.
    } elseif ($_POST['action'] === 'delete_order') {
        $stmt = mysqli_prepare($conn, "DELETE FROM orders WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $orderId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    header("Location: orders.php");
    exit();
}

$orders_query = "SELECT * FROM orders ORDER BY created_at DESC";
$orders_result = mysqli_query($conn, $orders_query);
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

    <nav class="navbar navbar-expand-md admin-navbar">
        <div class="container">
            <a class="navbar-brand" href="index.php">栞 SHIORI Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" style="border-color: rgba(255,255,255,0.3);">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="adminNav">
                <ul class="navbar-nav ms-auto align-items-md-center">
                    <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="inquiries.php">Inquiries</a></li>
                    <li class="nav-item"><a class="nav-link active" href="orders.php">Orders</a></li>
                    <li class="nav-item"><a class="nav-link" href="reservations.php">Reservations</a></li>
                    <li class="nav-item"><a class="nav-link" href="audit-logs.php">Audit Logs</a></li>
                    <li class="nav-item"><a class="nav-link back-to-site" href="../index.php"><i class="bi bi-box-arrow-left"></i> Back to Site</a></li>
                    <li class="nav-item"><a class="nav-link" href="../backend/auth/logout.php" style="color: #C1694F !important;"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <h1 class="admin-page-title">Customer Orders Control</h1>
        <p class="admin-page-subtitle">Manage live checkouts and execute administrative order actions.</p>

        <div class="row row-cols-1 row-cols-md-2 g-4">
            <?php if ($orders_result && mysqli_num_rows($orders_result) > 0): ?>
                <?php while ($order = mysqli_fetch_assoc($orders_result)): ?>
                    <div class="col">
                        <div class="admin-card order-card h-100 p-4">
                            <div class="order-card-header d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <div class="order-table-label fw-bold">Table <?php echo htmlspecialchars($order['table_number']); ?></div>
                                    <div class="order-id text-muted small">Order #<?php echo $order['id']; ?></div>
                                </div>
                                <span class="badge bg-success"><?php echo htmlspecialchars($order['payment_method']); ?></span>
                            </div>

                            <div class="mb-3">
                                <span class="text-muted small">Receipt Email:</span>
                                <div class="fw-semibold"><?php echo !empty($order['receipt_email']) ? htmlspecialchars($order['receipt_email']) : '<em>None</em>'; ?></div>
                            </div>

                            <div class="order-footer d-flex justify-content-between align-items-center border-top pt-3 mt-auto">
                                <div>
                                    <div class="order-total fs-5 fw-bold text-success">₱<?php echo number_format($order['total'], 2); ?></div>
                                    <div class="order-payment text-muted small"><?php echo $order['created_at']; ?></div>
                                </div>
                                <div class="d-flex gap-2">
                                    <form action="orders.php" method="POST" onsubmit="return confirm('Cancel and archive Order #<?php echo $order['id']; ?>?');">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <input type="hidden" name="action" value="delete_order">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-x-circle me-1"></i>Cancel & Archive
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
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
