<?php
session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Cashier') {
    header("Location: login.php");
    exit();
}

require_once "../backend/config/db.php";
$staffName = $_SESSION['user_name'] ?? 'Cashier';

// Fetch active orders (excluding Completed/Voided) from PostgreSQL
$query = "
    SELECT 
        o.order_id, 
        o.order_type, 
        t.table_number, 
        o.gross_order_amount, 
        o.preparation_status,
        o.created_at
    FROM shioricafe_schema.\"order\" o
    LEFT JOIN shioricafe_schema.\"table\" t ON o.table_id = t.table_id
    WHERE o.preparation_status NOT IN ('Completed') 
      AND o.voided_at IS NULL
    ORDER BY o.created_at ASC
";

$result = pg_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Queue | Cashier Terminal</title>
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
                    <li class="nav-item"><a class="nav-link active" href="queue.php">Order Queue</a></li>
                    <li class="nav-item"><a class="nav-link" href="pos.php">New Walk-in</a></li>
                    <li class="nav-item"><a class="nav-link" href="tables.php">Tables</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <h2 class="mb-4">Live Order Queue</h2>
        <div class="admin-card p-4 shadow-sm">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Order ID</th>
                        <th>Type</th>
                        <th>Table</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && pg_num_rows($result) > 0): ?>
                        <?php while ($row = pg_fetch_assoc($result)): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($row['order_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['order_type']); ?></td>
                                <td><?php echo htmlspecialchars($row['table_number'] ?? 'N/A'); ?></td>
                                <td>₱<?php echo number_format($row['gross_order_amount'], 2); ?></td>
                                <td>
                                    <?php 
                                        $badgeClass = 'bg-secondary';
                                        if ($row['preparation_status'] === 'Pending') $badgeClass = 'bg-danger';
                                        if ($row['preparation_status'] === 'Preparing') $badgeClass = 'bg-warning text-dark';
                                        if ($row['preparation_status'] === 'Ready') $badgeClass = 'bg-info text-dark';
                                        if ($row['preparation_status'] === 'Served') $badgeClass = 'bg-success';
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?>">
                                        <?php echo htmlspecialchars($row['preparation_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <form action="update_order.php" method="POST" class="d-inline">
                                        <input type="hidden" name="order_id" value="<?php echo $row['order_id']; ?>">
                                        
                                        <select name="new_status" class="form-select form-select-sm d-inline w-auto" required>
                                            <option value="" disabled selected>Update...</option>
                                            <option value="Preparing">Preparing</option>
                                            <option value="Ready">Ready</option>
                                            <option value="Served">Served</option>
                                            <option value="Completed">Completed</option>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-primary ms-1">Save</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">No active orders in the queue.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>