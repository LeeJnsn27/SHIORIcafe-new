<?php
// admin/admin-navbar.php - Dynamic RBAC Navigation Bar
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);
$rawRole = $_SESSION['user_role'] ?? $_SESSION['staff_role'] ?? $_SESSION['role'] ?? 'Cashier';
$userRole = strtolower(trim($rawRole));
$isAdminUser = in_array($userRole, ['admin', 'manager', 'administrator']);
?>

<!-- Fix for Issue 104: Prevent content overlap beneath the fixed navbar -->
<style>
    body {
        padding-top: 75px !important;
    }
    .admin-navbar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1030;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
</style>

<nav class="navbar navbar-expand-md admin-navbar bg-dark navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="<?php echo $isAdminUser ? 'index.php' : 'orders.php'; ?>">栞 SHIORI Admin</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" style="border-color: rgba(255,255,255,0.3);">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNav">
            <ul class="navbar-nav ms-auto align-items-md-center">
                <?php if ($isAdminUser): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentPage === 'index.php') ? 'active' : ''; ?>" href="index.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentPage === 'inquiries.php') ? 'active' : ''; ?>" href="inquiries.php">Inquiries</a>
                    </li>
                <?php endif; ?>

                <!-- Accessible by both Admin and Cashier -->
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage === 'pos.php') ? 'active' : ''; ?>" href="pos.php"><i class="bi bi-calculator me-1"></i>POS Terminal</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage === 'orders.php') ? 'active' : ''; ?>" href="orders.php">Orders</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage === 'reservations.php') ? 'active' : ''; ?>" href="reservations.php">Reservations</a>
                </li>

                <?php if ($isAdminUser): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentPage === 'audit-logs.php') ? 'active' : ''; ?>" href="audit-logs.php">Audit Logs</a>
                    </li>
                <?php endif; ?>

                <li class="nav-item">
                    <a class="nav-link back-to-site" href="../index.php"><i class="bi bi-box-arrow-left"></i> Back to Site</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php" style="color: #C1694F !important;"><i class="bi bi-box-arrow-right"></i> Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>