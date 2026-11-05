<?php
session_start();
require_once "../backend/config/db.php";

// Join reservation table where status is Occupied to detect active seatings
$query = "
    SELECT t.table_id, t.table_number, t.capacity, t.operational_status, r.reservation_id 
    FROM shioricafe_schema.\"table\" t 
    LEFT JOIN shioricafe_schema.reservation r ON t.table_id = r.table_id AND r.status = 'Occupied'
    ORDER BY t.table_number ASC
";
$stmt = $conn->query($query);
$tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!-- Inside the grid loop: -->
<?php foreach ($tables as $row): 
    // True availability requires BOTH operational status and no active seated reservation
    $isAvailable = ($row['operational_status'] === 'Available' && empty($row['reservation_id']));
    $statusText = $isAvailable ? 'Available' : (!empty($row['reservation_id']) ? 'Occupied' : 'Maintenance');
?>
    <div class="col-md-3">
        <div class="card p-3 text-center border-top <?php echo $isAvailable ? 'border-success' : 'border-danger'; ?> border-4">
            <h5><?php echo htmlspecialchars($row['table_number']); ?></h5>
            <span class="badge <?php echo $isAvailable ? 'bg-success' : 'bg-danger'; ?> mb-2"><?php echo $statusText; ?></span>
            <?php if ($isAvailable): ?>
                <a href="pos.php?table_id=<?php echo $row['table_id']; ?>" class="btn btn-sm btn-outline-primary">Assign Walk-in</a>
            <?php else: ?>
                <button class="btn btn-sm btn-outline-secondary" disabled>Unavailable</button>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>