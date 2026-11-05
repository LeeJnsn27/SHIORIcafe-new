<?php
session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Cashier') {
    header("Location: login.php");
    exit();
}
require_once "../backend/config/db.php";

$staffName = !empty($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Cashier';

$query = "SELECT table_id, table_number, table_type, capacity, operational_status FROM shioricafe_schema.\"table\" ORDER BY table_number ASC";
$result = pg_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Table Management | Cashier Terminal</title>
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
                    <li class="nav-item"><a class="nav-link" href="pos.php">New Walk-in</a></li>
                    <li class="nav-item"><a class="nav-link active" href="tables.php">Tables</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <h2 class="mb-4">Café Floor Plan & Occupancy</h2>
        <?php /* Data fetched from shioricafe_schema."table" */ ?>
        <div class="row g-4">
            <?php if ($result && pg_num_rows($result) > 0): ?>
                <?php while ($row = pg_fetch_assoc($result)): 
                    $isAvailable = ($row['operational_status'] === 'Available');
                    $borderClass = $isAvailable ? 'border-success' : 'border-danger';
                    $badgeClass = $isAvailable ? 'bg-success' : 'bg-danger';
                ?>
                    <div class="col-md-3">
                        <div class="admin-card p-3 text-center border-top <?php echo $borderClass; ?> border-4 shadow-sm">
                            <h5 class="fw-bold"><?php echo htmlspecialchars($row['table_number']); ?></h5>
                            <p class="small text-muted mb-2"><?php echo htmlspecialchars($row['table_type']); ?> (Cap: <?php echo htmlspecialchars($row['capacity']); ?>)</p>
                            <span class="badge <?php echo $badgeClass; ?> mb-3"><?php echo htmlspecialchars($row['operational_status']); ?></span>
                            
                            <?php if ($isAvailable): ?>
                                <a href="pos.php?table_id=<?php echo htmlspecialchars($row['table_id']); ?>" class="btn btn-sm btn-outline-primary w-100">Assign Walk-in</a>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-secondary w-100" disabled>Assigned</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>