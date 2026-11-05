<?php
// admin/admin-navbar.php - Unified Navigation Bar
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../backend/auth/auth-guard.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$isAdminUser = is_admin();
?>

<nav class="navbar navbar-expand-md admin-navbar">
    <div class="container">
        <a class="navbar-brand" href="<?= $isAdminUser ? '/SHIORIcafe/admin/index.php' : '/SHIORIcafe/admin/cashier/orders.php'; ?>">栞 SHIORI Admin</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" style="border-color: rgba(255,255,255,0.3);">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNav">
            <ul class="navbar-nav ms-auto align-items-md-center">
                <?php if ($isAdminUser): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentPage === 'index.php') ? 'active' : ''; ?>" href="/SHIORIcafe/admin/index.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentPage === 'inquiries.php') ? 'active' : ''; ?>" href="/SHIORIcafe/admin/inquiries.php">Inquiries</a>
                    </li>
                <?php endif; ?>

                <!-- Accessible by both Admin and Cashier -->
                <?php if (is_cashier_or_admin()): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentPage === 'pos.php') ? 'active' : ''; ?>" href="/SHIORIcafe/admin/cashier/pos.php"><i class="bi bi-calculator me-1"></i>POS Terminal</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentPage === 'checkin.php') ? 'active' : ''; ?>" href="/SHIORIcafe/admin/cashier/checkin.php"><i class="bi bi-person-check me-1"></i>Check-In</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentPage === 'tables.php') ? 'active' : ''; ?>" href="/SHIORIcafe/admin/cashier/tables.php"><i class="bi bi-grid-3x3-gap me-1"></i>Tables</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentPage === 'orders.php') ? 'active' : ''; ?>" href="/SHIORIcafe/admin/cashier/orders.php"><i class="bi bi-list-task me-1"></i>Orders</a>
                    </li>
                <?php endif; ?>

                <?php if ($isAdminUser): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentPage === 'audit-logs.php') ? 'active' : ''; ?>" href="/SHIORIcafe/admin/audit-logs.php">Audit Logs</a>
                    </li>
                <?php endif; ?>

                <li class="nav-item">
                    <a class="nav-link back-to-site" href="/SHIORIcafe/index.php"><i class="bi bi-box-arrow-left"></i> Back to Site</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/SHIORIcafe/admin/logout.php" style="color: #C1694F !important;"><i class="bi bi-box-arrow-right"></i> Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>