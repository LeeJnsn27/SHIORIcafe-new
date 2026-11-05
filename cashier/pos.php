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

$staffName = !empty($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Cashier';

// Fetch tables for the dropdown
$tables_query = "SELECT table_id, table_number FROM shioricafe_schema.\"table\" WHERE operational_status = 'Available'";
$tables_result = pg_query($conn, $tables_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Walk-in POS | Cashier Terminal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../frontend/assets/css/admin.css">
</head>
<body>
    <nav class="navbar navbar-expand-md admin-navbar">
        <div class="container">
            <a class="navbar-brand" href="index.php">栞 Terminal: <?php echo htmlspecialchars($staffName); ?></a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Terminal Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="queue.php">Order Queue</a></li>
                    <li class="nav-item"><a class="nav-link active" href="pos.php">New Walk-in</a></li>
                    <li class="nav-item"><a class="nav-link" href="tables.php">Tables</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row g-4">
            <div class="col-md-8">
                <div class="admin-card p-4 shadow-sm h-100">
                    <h4 class="mb-4">Menu Items</h4>
                    <?php /* Requires fetching data from shioricafe_schema.menu_item */ ?>
                    <p class="text-muted">Menu grid placeholder.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="admin-card p-4 shadow-sm h-100 bg-light">
                    <h4 class="mb-4">Current Order</h4>
                    <form action="process_walkin.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        
                        <div class="mb-3">
                            <select class="form-select" name="order_type" id="orderType" required>
                                <option value="" disabled selected>Select Order Type...</option>
                                <option value="Dine-In">Dine-In (Requires Table)</option>
                                <option value="Takeaway">Takeaway</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <select class="form-select" name="table_id" id="tableSelect">
                                <option value="" selected>Select Table (Dine-In Only)...</option>
                                <?php if ($tables_result): while($t = pg_fetch_assoc($tables_result)): ?>
                                    <option value="<?php echo htmlspecialchars($t['table_id']); ?>"><?php echo htmlspecialchars($t['table_number']); ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>

                        <hr>
                        <button type="submit" class="btn btn-matcha w-100 py-2">Charge & Record Cash</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>