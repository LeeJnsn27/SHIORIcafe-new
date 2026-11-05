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
    <title>Check-in | Cashier Terminal</title>
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
                    <li class="nav-item"><a class="nav-link" href="tables.php">Tables</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <h2 class="mb-4">Today's Reservations</h2>
        <div class="admin-card p-4 shadow-sm">
            <table class="table align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Reservation ID</th>
                        <th>Table</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Example Row - Requires fetching from shioricafe_schema.reservation -->
                    <tr>
                        <td>#RES-005</td>
                        <td>TBL-03</td>
                        <td>14:00 - 16:00</td>
                        <td><span class="badge bg-primary">Confirmed</span></td>
                        <td>
                            <form action="process_checkin.php" method="POST">
                                <input type="hidden" name="reservation_id" value="5">
                                <button type="submit" class="btn btn-sm btn-success">Check In Customer</button>
                            </form>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>