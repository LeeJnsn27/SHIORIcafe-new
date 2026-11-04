<?php
// Use the shared auth guard and database connection
require_once "../backend/auth/auth-guard.php";
require_once "../backend/config/db.php";
/** @var mysqli $conn */

$currentPage = basename($_SERVER['PHP_SELF']);

// Handle Admin Actions (Cancel with reason or Adjust schedule)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'], $_POST['reservation_id'])) {
    $resId = (int)$_POST['reservation_id'];

    if ($_POST['action'] === 'cancel_reservation') {
        $reason = trim($_POST['cancellation_reason'] ?? 'No reason provided');

        // Update status to Cancelled and save the reason why
        $stmt = mysqli_prepare($conn, "UPDATE reservations SET status = 'Cancelled', cancellation_reason = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $reason, $resId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

    } elseif ($_POST['action'] === 'adjust_reservation') {
        $newDate = trim($_POST['new_date']);
        $newTime = trim($_POST['new_time']);
        $reason  = trim($_POST['adjustment_reason'] ?? 'Schedule adjusted by admin');

        // Update the schedule and log the adjustment reason
        $stmt = mysqli_prepare($conn, "UPDATE reservations SET reservation_date = ?, reservation_time = ?, cancellation_reason = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "sssi", $newDate, $newTime, $reason, $resId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    header("Location: reservations.php");
    exit();
}

$res_query = "SELECT * FROM reservations ORDER BY created_at DESC";
$res_result = mysqli_query($conn, $res_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SHIORI Café Admin | Reservations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../frontend/assets/css/admin.css">
</head>
<body>

    <nav class="navbar navbar-expand-md admin-navbar">
        <div class="container">
            <a class="navbar-brand" href="index.php">栞 SHIORI Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" style="border-color: rgba(255,255,255,0.3);">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="adminNav">
                <ul class="navbar-nav ms-auto align-items-md-center">
                    <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="inquiries.php">Inquiries</a></li>
                    <li class="nav-item"><a class="nav-link" href="orders.php">Orders</a></li>
                    <li class="nav-item"><a class="nav-link active" href="reservations.php">Reservations</a></li>
                    <li class="nav-item"><a class="nav-link" href="audit-logs.php">Audit Logs</a></li>
                    <li class="nav-item"><a class="nav-link back-to-site" href="../index.php"><i class="bi bi-box-arrow-left"></i> Back to Site</a></li>
                    <li class="nav-item"><a class="nav-link" href="../backend/auth/logout.php" style="color: #C1694F !important;"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <h1 class="admin-page-title">Study Nook Reservations Control</h1>
        <p class="admin-page-subtitle">Review bookings, adjust schedules, or log cancellation feedback.</p>

        <div class="admin-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table admin-table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nook Details</th>
                            <th>Guest Info</th>
                            <th>Date & Time</th>
                            <th>Status & Notes</th>
                            <th class="text-end">Admin Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($res_result && mysqli_num_rows($res_result) > 0): ?>
                            <?php while ($reservation = mysqli_fetch_assoc($res_result)): ?>
                                <tr>
                                    <td><strong>#<?php echo $reservation['id']; ?></strong></td>
                                    <td>
                                        <div class="nook-name fw-bold"><?php echo htmlspecialchars($reservation['nook_name'] ?? 'Study Nook'); ?></div>
                                        <div class="nook-type text-muted small"><?php echo htmlspecialchars($reservation['nook_type'] ?? 'Standard Nook'); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($reservation['name'] ?? 'Guest'); ?></div>
                                        <div class="guest-email text-muted small"><?php echo htmlspecialchars($reservation['email'] ?? ''); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($reservation['reservation_date'] ?? ''); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($reservation['reservation_time'] ?? ''); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo ($reservation['status'] === 'Cancelled') ? 'bg-danger' : 'bg-warning text-dark'; ?>">
                                            <?php echo htmlspecialchars($reservation['status'] ?? 'Upcoming'); ?>
                                        </span>
                                        <?php if (!empty($reservation['cancellation_reason'])): ?>
                                            <div class="text-muted small mt-1 fst-italic">Note: "<?php echo htmlspecialchars($reservation['cancellation_reason']); ?>"</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <!-- Adjust Schedule Button Trigger Modal -->
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#adjustModal<?php echo $reservation['id']; ?>">
                                                Adjust
                                            </button>

                                            <!-- Cancel Button Trigger Modal -->
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal<?php echo $reservation['id']; ?>">
                                                Cancel
                                            </button>
                                        </div>

                                        <!-- Cancel Modal with Reason Input -->
                                        <div class="modal fade text-start" id="cancelModal<?php echo $reservation['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form action="reservations.php" method="POST">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Cancel Reservation #<?php echo $reservation['id']; ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                                                            <input type="hidden" name="action" value="cancel_reservation">
                                                            <div class="mb-3">
                                                                <label class="form-label">Why is this being cancelled or adjusted?</label>
                                                                <textarea class="form-control" name="cancellation_reason" rows="3" placeholder="e.g., Customer requested via chat / No show / Admin conflict" required></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                                            <button type="submit" class="btn btn-danger btn-sm">Confirm Cancellation</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Adjust Schedule Modal -->
                                        <div class="modal fade text-start" id="adjustModal<?php echo $reservation['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form action="reservations.php" method="POST">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Adjust Schedule for #<?php echo $reservation['id']; ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                                                            <input type="hidden" name="action" value="adjust_reservation">
                                                            <div class="mb-3">
                                                                <label class="form-label">New Date</label>
                                                                <input type="date" class="form-control" name="new_date" value="<?php echo $reservation['reservation_date']; ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">New Time</label>
                                                                <input type="text" class="form-control" name="new_time" value="<?php echo $reservation['reservation_time']; ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Reason for Adjustment</label>
                                                                <textarea class="form-control" name="adjustment_reason" rows="2" placeholder="e.g., Customer requested time change" required></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                                            <button type="submit" class="btn btn-success btn-sm">Save Adjustments</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No reservations found in the database.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
