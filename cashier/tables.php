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
    <title>Table Management | Cashier Terminal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
                    <li class="nav-item"><a class="nav-link" href="pos.php">New Walk-in</a></li>
                    <li class="nav-item"><a class="nav-link active" href="tables.php">Tables</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <h2 class="mb-4">Café Floor Plan & Occupancy</h2>
        <div class="row g-4">
            <!-- Example Table Card - Iterate over shioricafe_schema."table" -->
            <div class="col-md-3">
                <div class="admin-card p-3 text-center border-top border-success border-4 shadow-sm">
                    <h5 class="fw-bold">TBL-01</h5>
                    <p class="small text-muted mb-2">Solo Study Nook (Cap: 1)</p>
                    <span class="badge bg-success mb-3">Available</span>
                    <button class="btn btn-sm btn-outline-primary w-100">Assign Walk-in</button>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="admin-card p-3 text-center border-top border-danger border-4 shadow-sm">
                    <h5 class="fw-bold">TBL-02</h5>
                    <p class="small text-muted mb-2">Duo Nook (Cap: 2)</p>
                    <span class="badge bg-danger mb-3">Occupied</span>
                    <button class="btn btn-sm btn-outline-secondary w-100" disabled>Assigned</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>