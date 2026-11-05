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

// Strict check for empty user_name string
$staffName = !empty($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Cashier';

$query = "
    SELECT r.reservation_id, t.table_number, r.reservation_start_at, r.reservation_end_at, r.status
    FROM shioricafe_schema.reservation r
    LEFT JOIN shioricafe_schema.\"table\" t ON r.table_id = t.table_id
    WHERE r.checked_in_at IS NULL AND r.status IN ('Confirmed', 'Adjustment_Pending')
";
$result = pg_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Check-in | Cashier Terminal</title>
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
                    <li class="nav-item"><a class="nav-link" href="tables.php">Tables</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <h2 class="mb-4">Today's Reservations</h2>
        <div class="admin-card p-4 shadow-sm">
            <?php /* Data fetched from shioricafe_schema.reservation */ ?>
            <table class="table align-middle">
                <thead class="table-light">
                    <tr><th>Res ID</th><th>Table</th><th>Time</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php if ($result && pg_num_rows($result) > 0): ?>
                        <?php while ($row = pg_fetch_assoc($result)): ?>
                            <tr>
                                <td>#RES-<?php echo htmlspecialchars($row['reservation_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['table_number'] ?? 'Unassigned'); ?></td>
                                <td><?php echo date('H:i', strtotime($row['reservation_start_at'])); ?></td>
                                <td><span class="badge bg-primary"><?php echo htmlspecialchars($row['status']); ?></span></td>
                                <td>
                                    <form action="process_checkin.php" method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="reservation_id" value="<?php echo htmlspecialchars($row['reservation_id']); ?>">
                                        <button type="submit" class="btn btn-sm btn-success">Check In Customer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center text-muted">No pending check-ins.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>