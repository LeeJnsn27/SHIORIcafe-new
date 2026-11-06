<?php
// admin/cashier/checkin.php - Guest Reservation Check-In & Walk-in Terminal
require_once __DIR__ . '/../../backend/auth/auth-guard.php';
require_role(['admin', 'cashier']);

require_once __DIR__ . '/../../backend/config/db.php';
/** @var PDO $pdo */

$db = isset($pdo) ? $pdo : (isset($conn) ? $conn : null);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$reservations = [];
$error_message = null;

try {
    $stmt = $db->query("
        SELECT r.reservation_id, t.table_number, r.reservation_start_at, r.status, c.full_name as guest_name
        FROM shioricafe_schema.reservation r
        LEFT JOIN shioricafe_schema.\"table\" t ON r.table_id = t.table_id
        LEFT JOIN shioricafe_schema.customer c ON r.customer_id = c.customer_id
        WHERE r.checked_in_at IS NULL AND r.status IN ('Confirmed', 'Adjustment_Pending')
        ORDER BY r.reservation_start_at ASC
    ");
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Error retrieving reservations: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SHIORI Café Admin | Guest Check-In</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../../frontend/assets/css/admin.css">
</head>
<body class="bg-light">

    <?php require_once __DIR__ . "/../admin-navbar.php"; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1"><i class="bi bi-person-check me-2"></i>Guest Check-In Terminal</h2>
                <p class="text-muted mb-0">Verify arriving guests and trigger live nook timers</p>
            </div>
            <a href="tables.php" class="btn btn-outline-secondary">
                <i class="bi bi-grid-3x3-gap me-1"></i> View Table Layout
            </a>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="card-title fw-bold mb-0">Pending Reservation Check-Ins</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Res ID</th>
                                <th>Guest Name</th>
                                <th>Assigned Table</th>
                                <th>Scheduled Start</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reservations)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                        No pending reservations waiting for check-in.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($reservations as $row): ?>
                                    <tr>
                                        <td class="fw-bold">#<?= htmlspecialchars($row['reservation_id']) ?></td>
                                        <td><?= htmlspecialchars($row['guest_name'] ?? 'Walk-In Guest') ?></td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                Table <?= htmlspecialchars($row['table_number'] ?? 'N/A') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <i class="bi bi-clock me-1 text-muted"></i>
                                            <?= date('h:i A', strtotime($row['reservation_start_at'])) ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark">
                                                <?= htmlspecialchars($row['status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <form action="../../backend/api/process-checkin.php" method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="reservation_id" value="<?= htmlspecialchars($row['reservation_id']) ?>">
                                                <button type="submit" class="btn btn-sm btn-success fw-semibold">
                                                    <i class="bi bi-box-arrow-in-right me-1"></i> Check In
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>