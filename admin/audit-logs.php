<?php
// Use the shared auth guard instead of a manual session check
require_once "../backend/auth/auth-guard.php";

// Audit Log Viewer — mock data only, no database yet. Read-only, no action buttons.
$currentPage = basename($_SERVER['PHP_SELF']);

$auditLogs = [
    ['id' => 1, 'type' => 'Order', 'reference' => 'Order #98', 'note' => 'Cancelled — guest left before payment', 'archivedBy' => 'Staff: Lena', 'date' => 'Sep 13, 2026, 2:10 PM'],
    ['id' => 2, 'type' => 'Reservation', 'reference' => 'Nook 2 — Window Nook', 'note' => 'Cancelled — guest did not arrive within grace period', 'archivedBy' => 'Staff: Noah', 'date' => 'Sep 12, 2026, 4:45 PM'],
    ['id' => 3, 'type' => 'Order', 'reference' => 'Order #91', 'note' => 'Cancelled — duplicate order entered by mistake', 'archivedBy' => 'Staff: Lena', 'date' => 'Sep 12, 2026, 11:20 AM'],
    ['id' => 4, 'type' => 'Reservation', 'reference' => 'Nook 4 — Group Study Table', 'note' => 'Cancelled — guest rescheduled by phone', 'archivedBy' => 'Staff: Marco', 'date' => 'Sep 11, 2026, 6:05 PM'],
    ['id' => 5, 'type' => 'Order', 'reference' => 'Order #85', 'note' => 'Cancelled — item out of stock, refunded in cash', 'archivedBy' => 'Staff: Noah', 'date' => 'Sep 10, 2026, 9:40 AM'],
];

$typeClasses = [
    'Order' => 'badge-type-order',
    'Reservation' => 'badge-type-reservation',
];
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

    <nav class="navbar navbar-expand-md admin-navbar">
        <div class="container">
            <a class="navbar-brand" href="index.php">栞 SHIORI Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" style="border-color: rgba(255,255,255,0.3);">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="adminNav">
                <ul class="navbar-nav ms-auto align-items-md-center">
                    <li class="nav-item"><a class="nav-link <?php echo ($currentPage === 'index.php') ? 'active' : ''; ?>" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($currentPage === 'inquiries.php') ? 'active' : ''; ?>" href="inquiries.php">Inquiries</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($currentPage === 'orders.php') ? 'active' : ''; ?>" href="orders.php">Orders</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($currentPage === 'reservations.php') ? 'active' : ''; ?>" href="reservations.php">Reservations</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($currentPage === 'audit-logs.php') ? 'active' : ''; ?>" href="audit-logs.php">Audit Logs</a></li>
                    <li class="nav-item"><a class="nav-link back-to-site" href="../index.php"><i class="bi bi-box-arrow-left"></i> Back to Site</a></li>
                    <li class="nav-item"><a class="nav-link" href="../logout.php" style="color: #C1694F !important;"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">

        <h1 class="admin-page-title">Audit Logs</h1>
        <p class="admin-page-subtitle">Archived cancellations — read-only history, nothing here can be edited.</p>

        <div class="admin-card">
            <div class="table-responsive">
                <table class="table admin-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Reference</th>
                            <th>Note</th>
                            <th>Archived By</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditLogs as $log) { ?>
                            <tr>
                                <td>
                                    <span class="badge-type <?php echo $typeClasses[$log['type']]; ?>">
                                        <?php echo $log['type']; ?>
                                    </span>
                                </td>
                                <td><strong><?php echo $log['reference']; ?></strong></td>
                                <td class="log-note"><?php echo $log['note']; ?></td>
                                <td class="archived-badge"><?php echo $log['archivedBy']; ?></td>
                                <td class="log-date"><?php echo $log['date']; ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>