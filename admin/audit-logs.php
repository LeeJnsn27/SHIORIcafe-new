<?php
// admin/audit-logs.php - Audit Log & Cancellation History Viewer
require_once "../backend/auth/auth-guard.php";
require_once "../backend/config/db.php";
/** @var PDO $pdo */

$currentPage = basename($_SERVER['PHP_SELF']);

$auditLogs = [];

try {
    // 1. Fetch Cancelled Orders from PostgreSQL
    $stmtOrders = $pdo->query("
        SELECT order_id, gross_order_amount, created_at 
        FROM shioricafe_schema.\"order\" 
        WHERE preparation_status = 'Cancelled' 
        ORDER BY created_at DESC
    ");
    $cancelledOrders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cancelledOrders as $ord) {
        $auditLogs[] = [
            'type'        => 'Order',
            'reference'   => 'Order #' . $ord['order_id'],
            'note'        => 'Order cancelled & archived — Amount: ₱' . number_format($ord['gross_order_amount'] ?? 0, 2),
            'archivedBy'  => 'Admin / Cashier',
            'date'        => date('M d, Y, h:i A', strtotime($ord['created_at'])),
            'timestamp'   => strtotime($ord['created_at'])
        ];
    }

    // 2. Fetch Cancelled Reservations from PostgreSQL
    $stmtRes = $pdo->query("
        SELECT reservation_id, cancellation_reason, reservation_start_at 
        FROM shioricafe_schema.reservation 
        WHERE status = 'Cancelled' 
        ORDER BY reservation_start_at DESC
    ");
    $cancelledReservations = $stmtRes->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cancelledReservations as $res) {
        $auditLogs[] = [
            'type'        => 'Reservation',
            'reference'   => 'Reservation #' . $res['reservation_id'],
            'note'        => !empty($res['cancellation_reason']) ? $res['cancellation_reason'] : 'Cancelled by Admin',
            'archivedBy'  => 'Admin / Manager',
            'date'        => date('M d, Y, h:i A', strtotime($res['reservation_start_at'])),
            'timestamp'   => strtotime($res['reservation_start_at'])
        ];
    }

    // 3. Sort combined logs chronologically (newest first)
    usort($auditLogs, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });

} catch (Exception $e) {
    // Failover if database connection or tables fail
    $auditLogs = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SHIORI Café Admin | Audit Logs</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../frontend/assets/css/admin.css">
</head>
<body>

    <?php require_once "admin-navbar.php"; ?>
    <div class="container py-5">

        <h1 class="admin-page-title">Audit Logs</h1>
        <p class="admin-page-subtitle">Archived cancellations — read-only history, nothing here can be edited.</p>

        <div class="admin-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Reference</th>
                            <th>Note / Reason</th>
                            <th>Archived By</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($auditLogs)): ?>
                            <?php foreach ($auditLogs as $log): ?>
                                <tr>
                                    <td>
                                        <span class="badge <?php echo ($log['type'] === 'Order') ? 'bg-danger' : 'bg-warning text-dark'; ?>">
                                            <?php echo htmlspecialchars($log['type']); ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($log['reference']); ?></strong></td>
                                    <td class="log-note text-muted"><?php echo htmlspecialchars($log['note']); ?></td>
                                    <td class="archived-badge fw-semibold"><?php echo htmlspecialchars($log['archivedBy']); ?></td>
                                    <td class="log-date text-muted small"><?php echo htmlspecialchars($log['date']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No archived cancellations found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>