<?php
// admin/cashier/tables.php - Live Table & Nook Occupancy Floor Plan
require_once __DIR__ . '/../../backend/auth/auth-guard.php';
require_role(['admin', 'cashier']);

require_once __DIR__ . '/../../backend/config/db.php';
/** @var PDO $pdo */

$db = isset($pdo) ? $pdo : (isset($conn) ? $conn : null);

$tables = [];
$error_message = null;

try {
    $query = "
        SELECT t.table_id, t.table_number, t.capacity, t.operational_status, r.reservation_id 
        FROM shioricafe_schema.\"table\" t 
        LEFT JOIN shioricafe_schema.reservation r ON t.table_id = r.table_id AND r.status = 'Occupied'
        ORDER BY t.table_number ASC
    ";
    $stmt = $db->query($query);
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Error fetching table status: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SHIORI Café Admin | Live Table Layout</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../../frontend/assets/css/admin.css">
</head>
<body class="bg-light">

    <?php require_once __DIR__ . "/../admin-navbar.php"; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1"><i class="bi bi-grid-3x3-gap me-2"></i>Live Floor Plan & Nook Status</h2>
                <p class="text-muted mb-0">Monitor table availability, capacity, and seat walk-in guests</p>
            </div>
            <a href="checkin.php" class="btn btn-outline-secondary">
                <i class="bi bi-person-check me-1"></i> Guest Check-In Terminal
            </a>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <?php if (empty($tables)): ?>
                <div class="col-12 text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    No table configurations found in database.
                </div>
            <?php else: ?>
                <?php foreach ($tables as $row): 
                    $isAvailable = ($row['operational_status'] === 'Available' && empty($row['reservation_id']));
                    $isOccupied  = !empty($row['reservation_id']);
                    
                    $statusText  = $isAvailable ? 'Available' : ($isOccupied ? 'Occupied' : 'Maintenance');
                    $borderClass = $isAvailable ? 'border-success' : ($isOccupied ? 'border-danger' : 'border-warning');
                    $badgeClass  = $isAvailable ? 'bg-success' : ($isOccupied ? 'bg-danger' : 'bg-warning text-dark');
                ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="card h-100 p-3 text-center shadow-sm border-top <?= $borderClass ?> border-4">
                            <h5 class="fw-bold text-dark mb-1">
                                Table #<?= htmlspecialchars($row['table_number']); ?>
                            </h5>
                            <div class="small text-muted mb-2">
                                <i class="bi bi-people me-1"></i>Capacity: <?= htmlspecialchars($row['capacity'] ?? 'N/A'); ?> seats
                            </div>
                            <div class="mb-3">
                                <span class="badge <?= $badgeClass ?> px-3 py-2"><?= $statusText; ?></span>
                            </div>
                            <?php if ($isAvailable): ?>
                                <a href="pos.php?table_id=<?= htmlspecialchars($row['table_id']); ?>" class="btn btn-sm btn-outline-success fw-semibold w-100 mt-auto">
                                    <i class="bi bi-plus-circle me-1"></i>Assign Walk-In
                                </a>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-secondary w-100 mt-auto" disabled>
                                    <i class="bi bi-slash-circle me-1"></i>Unavailable
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>