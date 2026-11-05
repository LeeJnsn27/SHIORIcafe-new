<?php
session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Cashier') {
    header("Location: login.php");
    exit();
}
$staffName = $_SESSION['user_name'] ?? 'Cashier';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walk-in POS | Cashier Terminal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../frontend/assets/css/admin.css">
</head>
<body>
    <nav class="navbar navbar-expand-md admin-navbar">
        <div class="container">
            <a class="navbar-brand" href="index.php">栞 Terminal: <?php echo htmlspecialchars($staffName); ?></a>
            <div class="collapse navbar-collapse" id="cashierNav">
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
                    <!-- Menu grid will go here based on shioricafe_schema.menu_item -->
                    <p class="text-muted">Menu grid placeholder. (Requires fetching data from DB).</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="admin-card p-4 shadow-sm h-100 bg-light">
                    <h4 class="mb-4">Current Order</h4>
                    <form action="process_walkin.php" method="POST">
                        <div class="mb-3">
                            <select class="form-select" name="order_type" required>
                                <option value="" disabled selected>Select Order Type...</option>
                                <option value="Dine-In">Dine-In (Requires Table)</option>
                                <option value="Takeaway">Takeaway</option>
                            </select>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <strong>Total:</strong>
                            <strong>₱0.00</strong>
                        </div>
                        <button type="button" class="btn btn-matcha w-100 py-2">Charge & Record Cash</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
