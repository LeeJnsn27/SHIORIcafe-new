<?php
// Start session and include database connection and auth guard
require_once "../backend/auth/auth-guard.php";
require_once "../backend/config/db.php";
/** @var PDO $conn */

// Use Philippine time for all PostgreSQL date calculations.
// This affects only the current database connection.
$conn->exec("SET TIME ZONE 'Asia/Manila'");

$currentPage = basename($_SERVER['PHP_SELF']);

// 1. Total Sales: Today vs Yesterday
// Half-open ranges [start, end); voided orders excluded.
$today_rev_query = '
    SELECT COALESCE(SUM(gross_order_amount), 0) AS rev,
           COUNT(*) AS count
    FROM shioricafe_schema."order"
    WHERE voided_at IS NULL
      AND created_at >= CURRENT_DATE
      AND created_at < CURRENT_DATE + 1
';
$today_res = $conn->query($today_rev_query)->fetch(PDO::FETCH_ASSOC);
$today_sales = (float)($today_res['rev'] ?? 0.00);
$today_orders_count = (int)($today_res['count'] ?? 0);

$yest_rev_query = '
    SELECT COALESCE(SUM(gross_order_amount), 0) AS rev
    FROM shioricafe_schema."order"
    WHERE voided_at IS NULL
      AND created_at >= CURRENT_DATE - 1
      AND created_at < CURRENT_DATE
';
$yest_res = $conn->query($yest_rev_query)->fetch(PDO::FETCH_ASSOC);
$yesterday_sales = (float)($yest_res['rev'] ?? 0.00);

// Calculate Today vs Yesterday percentage change
$sales_diff = $today_sales - $yesterday_sales;
$sales_pct_change = ($yesterday_sales > 0)
    ? ($sales_diff / $yesterday_sales) * 100
    : ($today_sales > 0 ? 100 : 0);

// 2. Total Sales: This Week vs Last Week
// PostgreSQL date_trunc('week') starts on Monday.
$week_rev_query = '
    SELECT COALESCE(SUM(gross_order_amount), 0) AS rev
    FROM shioricafe_schema."order"
    WHERE voided_at IS NULL
      AND created_at >= date_trunc(\'week\', CURRENT_DATE)
      AND created_at < date_trunc(\'week\', CURRENT_DATE)
                            + INTERVAL \'1 week\'
';
$week_sales = (float)(
    $conn->query($week_rev_query)->fetch(PDO::FETCH_ASSOC)['rev'] ?? 0.00
);

$last_week_rev_query = '
    SELECT COALESCE(SUM(gross_order_amount), 0) AS rev
    FROM shioricafe_schema."order"
    WHERE voided_at IS NULL
      AND created_at >= date_trunc(\'week\', CURRENT_DATE)
                            - INTERVAL \'1 week\'
      AND created_at < date_trunc(\'week\', CURRENT_DATE)
';
$last_week_sales = (float)(
    $conn->query($last_week_rev_query)->fetch(PDO::FETCH_ASSOC)['rev'] ?? 0.00
);

$week_pct_change = ($last_week_sales > 0)
    ? (($week_sales - $last_week_sales) / $last_week_sales) * 100
    : ($week_sales > 0 ? 100 : 0);

// 3. Total Sales: This Month
$month_rev_query = '
    SELECT COALESCE(SUM(gross_order_amount), 0) AS rev
    FROM shioricafe_schema."order"
    WHERE voided_at IS NULL
      AND created_at >= date_trunc(\'month\', CURRENT_DATE)
      AND created_at < date_trunc(\'month\', CURRENT_DATE)
                            + INTERVAL \'1 month\'
';
$month_sales = (float)(
    $conn->query($month_rev_query)->fetch(PDO::FETCH_ASSOC)['rev'] ?? 0.00
);

// 4. Total Orders & Pending Orders Count
// Voided orders are excluded.
$total_orders_query = '
    SELECT COUNT(*) AS count
    FROM shioricafe_schema."order"
    WHERE voided_at IS NULL
';
$total_orders = (int)(
    $conn->query($total_orders_query)->fetch(PDO::FETCH_ASSOC)['count'] ?? 0
);

$pend_stmt = $conn->prepare('
    SELECT COUNT(*) AS count
    FROM shioricafe_schema."order"
    WHERE voided_at IS NULL
      AND preparation_status IN (?, ?)
');
$pend_stmt->execute(['Pending', 'Preparing']);
$pending_orders = (int)(
    $pend_stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0
);

// 5. Average Order Value (AOV)
$all_rev_query = '
    SELECT COALESCE(SUM(gross_order_amount), 0) AS total_rev,
           COUNT(*) AS total_count
    FROM shioricafe_schema."order"
    WHERE voided_at IS NULL
';
$all_rev_res = $conn->query($all_rev_query)->fetch(PDO::FETCH_ASSOC);

$grand_total_rev = (float)($all_rev_res['total_rev'] ?? 0.00);
$grand_total_count = (int)($all_rev_res['total_count'] ?? 0);

$avg_order_value = ($grand_total_count > 0)
    ? $grand_total_rev / $grand_total_count
    : 0.00;

// 6. Net Profit / Profit Margin (Estimated standard cafe margin ~45%)
$profit_margin_pct = 45.0;
$estimated_net_profit = $month_sales * ($profit_margin_pct / 100);

// 7. Revenue per Employee (Assuming baseline staff count of 4)
$active_staff_count = 4;
$rev_per_employee = ($active_staff_count > 0)
    ? $month_sales / $active_staff_count
    : 0.00;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SHIORI Café Admin | Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../frontend/assets/css/admin.css">
</head>
<body>

    <!-- Unified Admin Navigation Bar -->
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

    <!-- Main Content Area with Visual Hierarchy -->
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="admin-page-title mb-1">Dashboard Overview</h1>
                <p class="admin-page-subtitle mb-0">Real-time performance metrics and store analytics.</p>
            </div>
            <div>
                <span class="badge bg-secondary px-3 py-2">Live Database Sync</span>
            </div>
        </div>

        <!-- Tier 1: HERO METRIC (Most Important Detail - Today's Sales & Comparison) -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="admin-card p-4 border-start border-success border-5 shadow-sm">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <span class="text-uppercase text-muted fw-bold small tracking-wide">Today's Total Sales</span>
                            <h1 class="display-4 fw-bold text-success my-2">₱<?php echo number_format($today_sales, 2); ?></h1>
                            <p class="text-muted mb-0">
                                Across <strong><?php echo $today_orders_count; ?></strong> orders placed today.
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0 border-start-md">
                            <span class="text-muted small d-block">vs. Yesterday (₱<?php echo number_format($yesterday_sales, 2); ?>)</span>
                            <div class="fs-5 fw-bold <?php echo ($sales_pct_change >= 0) ? 'text-success' : 'text-danger'; ?> mt-1">
                                <i class="bi <?php echo ($sales_pct_change >= 0) ? 'bi-arrow-up-right' : 'bi-arrow-down-right'; ?>"></i>
                                <?php echo number_format(abs($sales_pct_change), 1); ?>% <?php echo ($sales_pct_change >= 0) ? 'increase' : 'decrease'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tier 2: IMPORTANT PERFORMANCE METRICS (Weekly, Monthly, Orders, AOV) -->
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4 mb-4">
            <!-- Weekly Sales -->
            <div class="col">
                <div class="admin-card h-100 p-4">
                    <div class="text-muted small fw-bold mb-1">This Week's Sales</div>
                    <h3 class="fw-bold text-dark mb-2">₱<?php echo number_format($week_sales, 2); ?></h3>
                    <span class="small <?php echo ($week_pct_change >= 0) ? 'text-success' : 'text-danger'; ?>">
                        <i class="bi <?php echo ($week_pct_change >= 0) ? 'bi-arrow-up' : 'bi-arrow-down'; ?>"></i>
                        <?php echo number_format(abs($week_pct_change), 1); ?>% vs last week
                    </span>
                </div>
            </div>

            <!-- Monthly Sales -->
            <div class="col">
                <div class="admin-card h-100 p-4">
                    <div class="text-muted small fw-bold mb-1">This Month's Sales</div>
                    <h3 class="fw-bold text-dark mb-2">₱<?php echo number_format($month_sales, 2); ?></h3>
                    <span class="text-muted small">Current calendar month</span>
                </div>
            </div>

            <!-- Total Orders & Pending -->
            <div class="col">
                <div class="admin-card h-100 p-4">
                    <div class="text-muted small fw-bold mb-1">Total & Pending Orders</div>
                    <h3 class="fw-bold text-primary mb-2"><?php echo $total_orders; ?> <span class="fs-6 fw-normal text-muted">total</span></h3>
                    <span class="badge bg-warning text-dark">
                        <?php echo $pending_orders; ?> pending / active
                    </span>
                </div>
            </div>

            <!-- Average Order Value -->
            <div class="col">
                <div class="admin-card h-100 p-4">
                    <div class="text-muted small fw-bold mb-1">Average Order Value (AOV)</div>
                    <h3 class="fw-bold text-dark mb-2">₱<?php echo number_format($avg_order_value, 2); ?></h3>
                    <span class="text-muted small">Avg spend per checkout</span>
                </div>
            </div>
        </div>

        <!-- Tier 3: PROFITABILITY & EFFICIENCY METRICS -->
        <div class="row row-cols-1 row-cols-md-2 g-4">
            <!-- Net Profit & Margin -->
            <div class="col">
                <div class="admin-card h-100 p-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-bold">Estimated Net Profit (Monthly)</span>
                        <span class="badge bg-success"><?php echo $profit_margin_pct; ?>% Margin</span>
                    </div>
                    <h3 class="fw-bold text-success mb-1">₱<?php echo number_format($estimated_net_profit, 2); ?></h3>
                    <p class="text-muted small mb-0">Calculated based on standard café operating margins against monthly gross revenue.</p>
                </div>
            </div>

            <!-- Revenue per Employee (RevPASH / Staff Productivity) -->
            <div class="col">
                <div class="admin-card h-100 p-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-bold">Revenue Per Employee (Monthly)</span>
                        <span class="badge bg-info text-dark"><?php echo $active_staff_count; ?> Active Staff</span>
                    </div>
                    <h3 class="fw-bold text-dark mb-1">₱<?php echo number_format($rev_per_employee, 2); ?></h3>
                    <p class="text-muted small mb-0">Average monthly output efficiency per service crew member.</p>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
