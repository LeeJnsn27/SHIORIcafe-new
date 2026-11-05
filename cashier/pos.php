<?php
session_start();
require_once "../backend/config/db.php";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch dynamic catalog
$menu_stmt = $conn->query("
    SELECT m.menu_item_id, m.name, m.price, c.category_name 
    FROM shioricafe_schema.menu_item m 
    LEFT JOIN shioricafe_schema.category c ON m.category_id = c.category_id
    WHERE m.availability_status = 'Available'
");
$menu_items = $menu_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch available tables
$tables_stmt = $conn->query("SELECT table_id, table_number FROM shioricafe_schema.\"table\" WHERE operational_status = 'Available'");
$tables = $tables_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!-- Inside your HTML body where the placeholder was: -->
<div class="row g-3">
    <?php foreach ($menu_items as $item): ?>
        <div class="col-md-4">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body text-center">
                    <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                    <small class="text-muted d-block mb-2"><?php echo htmlspecialchars($item['category_name']); ?></small>
                    <p class="text-success fw-bold mb-3">₱<?php echo number_format($item['price'], 2); ?></p>
                    <button class="btn btn-sm btn-outline-primary w-100" onclick="addToCart(<?php echo $item['menu_item_id']; ?>)">+ Add</button>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>