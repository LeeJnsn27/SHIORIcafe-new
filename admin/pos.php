<?php
// admin/pos.php - Cashier Point of Sale Counter Terminal with Live Nook Timers
require_once "../backend/auth/auth-guard.php";
require_once "../backend/config/db.php";
/** @var PDO $pdo */

$currentPage = basename($_SERVER['PHP_SELF']);

// Fetch sellable menu items (same availability rule as backend/api/pos-checkout.php)
$menuError = false;
try {
    $stmt = $pdo->query("
        SELECT m.menu_item_id, m.item_name AS name, m.current_price AS price, c.category_name
        FROM shioricafe_schema.menu_item m
        JOIN shioricafe_schema.category c ON c.category_id = m.category_id
        WHERE m.is_active = TRUE
          AND m.stock_status = 'In Stock'
        ORDER BY c.category_name ASC, m.item_name ASC
    ");
    $menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('admin/pos.php menu query failed: ' . $e->getMessage());
    $menuItems = [];
    $menuError = true;
}

// Fetch tables that can be assigned to a dine-in walk-in
try {
    $tables = $pdo->query("
        SELECT table_id, table_number, table_type, capacity
        FROM shioricafe_schema.\"table\"
        WHERE operational_status = 'Available'
        ORDER BY table_number ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('admin/pos.php table query failed: ' . $e->getMessage());
    $tables = [];
}

$categories = [];
foreach ($menuItems as $item) {
    $cat = !empty($item['category_name']) ? $item['category_name'] : 'General';
    $categories[$cat][] = $item;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SHIORI Café Admin | POS Terminal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../frontend/assets/css/admin.css">
    <style>
        .pos-menu-card {
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            border: 1px solid #e2e8f0;
            background: #ffffff;
        }
        .pos-menu-card:hover {
            transform: translateY(-3px);
            border-color: #059669;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.15);
        }
        .ticket-card {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            position: sticky;
            top: 20px;
        }
        .ticket-item-row {
            border-bottom: 1px dashed #e2e8f0;
            padding: 8px 0;
        }
        .nook-status-box {
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.85rem;
        }
    </style>
</head>
<body class="bg-light">

    <?php require_once "admin-navbar.php"; ?>

    <div class="container-fluid py-4 px-lg-5">
        <div class="row g-4">
            
            <!-- LEFT: MENU PRODUCT CATALOG -->
            <div class="col-lg-7 col-xl-8">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="admin-page-title mb-0">Counter POS Terminal</h2>
                    <input type="text" id="posSearch" class="form-control w-50" placeholder="Search menu item...">
                </div>

                <?php if ($menuError): ?>
                    <div class="alert alert-danger">The menu could not be loaded. Reload the page or contact a Manager.</div>
                <?php elseif (!$menuItems): ?>
                    <div class="alert alert-warning">No menu items are available to sell right now.</div>
                <?php endif; ?>

                <?php foreach ($categories as $catName => $items): ?>
                    <h5 class="fw-bold text-muted mb-3 mt-4"><i class="bi bi-tag me-1"></i><?php echo htmlspecialchars($catName); ?></h5>
                    <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-3 pos-category-group">
                        <?php foreach ($items as $item): 
                            $itemId = $item['menu_item_id'] ?? $item['id'];
                            $itemName = $item['name'];
                            $itemPrice = floatval($item['price']);
                        ?>
                            <div class="col pos-item-col" data-name="<?php echo strtolower(htmlspecialchars($itemName)); ?>">
                                <div class="card pos-menu-card h-100 p-3 text-center" onclick="addToTicket(<?php echo $itemId; ?>, '<?php echo addslashes(htmlspecialchars($itemName)); ?>', <?php echo $itemPrice; ?>)">
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($itemName); ?></div>
                                    <div class="text-success fw-semibold mt-1">₱<?php echo number_format($itemPrice, 2); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- RIGHT: CURRENT ORDER TICKET -->
            <div class="col-lg-5 col-xl-4">
                <div class="card ticket-card p-4 shadow-sm">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="fw-bold mb-0"><i class="bi bi-receipt me-2"></i>Current Order</h4>
                        <button class="btn btn-sm btn-outline-danger" onclick="clearTicket()">Clear All</button>
                    </div>

                    <!-- Order Settings & Nook Status -->
                    <div class="row g-2 mb-2">
                         <div class="col-6" id="posTableGroup">
                            <label class="form-label small fw-semibold">Table / Nook</label>
                            <select id="posTableNum" class="form-select form-select-sm" onchange="updateNookOccupancyStatus()">
                                <?php foreach ($tables as $t): ?>
                                    <option value="<?php echo (int)$t['table_id']; ?>">
                                        <?php echo htmlspecialchars($t['table_number'] . ' (' . $t['table_type'] . ', seats ' . $t['capacity'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Order Type</label>
                            <select id="posOrderType" class="form-select form-select-sm" onchange="updateOrderTypeFields()">
                                <option value="Dine-In">Dine-In</option>
                                <option value="Takeaway">Takeaway</option>
                            </select>
                        </div>
                        <div class="col-12" id="posDurationGroup">
                            <label class="form-label small fw-semibold" for="posExpectedMinutes">Expected stay (minutes)</label>
                            <input type="number" id="posExpectedMinutes" class="form-control form-control-sm" min="1" max="1440" step="1" placeholder="Ask the customer, e.g. 90">
                        </div>
                    </div>

                    <!-- LIVE OCCUPANCY & REMAINING TIME BADGE -->
                    <div id="nookStatusBadge" class="nook-status-box bg-secondary text-white mb-3 text-center">
                        <i class="bi bi-hourglass-split me-1"></i> Checking table status...
                    </div>

                    <!-- Ticket Line Items -->
                    <div id="ticketItems" class="my-3 overflow-auto" style="max-height: 280px;">
                        <p class="text-muted text-center py-4 mb-0" id="emptyTicketMsg">No items added to ticket.</p>
                    </div>

                    <!-- Order Totals -->
                    <div class="border-top pt-3">
                        <div class="d-flex justify-content-between fs-5 fw-bold text-dark mb-3">
                            <span>Total Due:</span>
                            <span class="text-success" id="posGrandTotal">₱0.00</span>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Payment Method</label>
                            <select id="posPaymentMethod" class="form-select">
                                <option value="Cash">Cash on Counter</option>
                                <option value="GCash">GCash QR</option>
                                <option value="Card">Debit / Credit Card</option>
                            </select>
                        </div>

                        <button id="btnProcessPayment" class="btn btn-success w-100 py-2 fw-bold" onclick="processPOSTransaction()" disabled>
                            <i class="bi bi-check-circle me-1"></i> Complete & Print Receipt
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let ticket = [];
        let liveOccupancyData = {};

        // Fetch active table occupancy & remaining time from API
        function fetchLiveOccupancy() {
            fetch('../backend/api/get-table-occupancy.php')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        liveOccupancyData = data.tables || {};
                        updateNookOccupancyStatus();
                    }
                })
                .catch(err => console.error("Occupancy fetch error:", err));
        }

        // Takeaway has no table or expected stay; Dine-In needs both.
        function updateOrderTypeFields() {
            const dineIn = document.getElementById('posOrderType').value === 'Dine-In';
            document.getElementById('posTableGroup').classList.toggle('d-none', !dineIn);
            document.getElementById('posDurationGroup').classList.toggle('d-none', !dineIn);
            updateNookOccupancyStatus();
        }

        function updateNookOccupancyStatus() {
            const badge = document.getElementById('nookStatusBadge');
            if (document.getElementById('posOrderType').value === 'Takeaway') {
                badge.classList.add('d-none');
                return;
            }
            const selectedTable = parseInt(document.getElementById('posTableNum').value);
            const info = liveOccupancyData[selectedTable];

            if (info && info.occupied) {
                badge.className = "nook-status-box bg-danger text-white mb-3";
                badge.innerHTML = `
                    <div class="fw-bold"><i class="bi bi-person-fill me-1"></i> Occupied by: <span class="guest-name"></span></div>
                    <div><i class="bi bi-clock-history me-1"></i> <strong>${info.remaining_minutes} min remaining</strong> (until ${info.end_time_formatted})</div>
                `;
                badge.querySelector('.guest-name').textContent = info.guest_name;
            } else {
                badge.className = "nook-status-box bg-success text-white mb-3 text-center";
                badge.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> Table #${selectedTable} is currently Available`;
            }
        }

        // Poll API every 15 seconds for real-time countdown updates
        updateOrderTypeFields();
        fetchLiveOccupancy();
        setInterval(fetchLiveOccupancy, 15000);

        function addToTicket(id, name, price) {
            let existing = ticket.find(i => i.id === id);
            if (existing) {
                existing.quantity++;
            } else {
                ticket.push({ id: id, name: name, price: price, quantity: 1 });
            }
            renderTicket();
        }

        function updateQuantity(id, delta) {
            let item = ticket.find(i => i.id === id);
            if (item) {
                item.quantity += delta;
                if (item.quantity <= 0) {
                    ticket = ticket.filter(i => i.id !== id);
                }
            }
            renderTicket();
        }

        function clearTicket() {
            ticket = [];
            renderTicket();
        }

        function renderTicket() {
            const container = document.getElementById('ticketItems');
            const totalEl = document.getElementById('posGrandTotal');
            const btnPay = document.getElementById('btnProcessPayment');

            if (ticket.length === 0) {
                container.innerHTML = '<p class="text-muted text-center py-4 mb-0">No items added to ticket.</p>';
                totalEl.innerText = '₱0.00';
                btnPay.disabled = true;
                return;
            }

            let html = '';
            let total = 0;

            ticket.forEach(item => {
                let lineTotal = item.price * item.quantity;
                total += lineTotal;
                html += `
                    <div class="ticket-item-row d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold small text-dark">${item.name}</div>
                            <div class="text-muted small">₱${item.price.toFixed(2)} × ${item.quantity}</div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-bold text-success small">₱${lineTotal.toFixed(2)}</span>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-secondary px-2" onclick="updateQuantity(${item.id}, -1)">-</button>
                                <button class="btn btn-outline-secondary px-2" onclick="updateQuantity(${item.id}, 1)">+</button>
                            </div>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
            totalEl.innerText = '₱' + total.toFixed(2);
            btnPay.disabled = false;
        }

        document.getElementById('posSearch').addEventListener('input', function(e) {
            let query = e.target.value.toLowerCase().trim();
            document.querySelectorAll('.pos-item-col').forEach(col => {
                let name = col.getAttribute('data-name');
                col.style.display = name.includes(query) ? 'block' : 'none';
            });
        });

        function processPOSTransaction() {
            if (!ticket || ticket.length === 0) {
                alert('Your cart/ticket is empty!');
                return;
            }

            const orderType = document.getElementById('posOrderType').value;

            // The server prices every line from the menu table; send only item IDs and quantities.
            const payload = {
                order_type: orderType,
                items: ticket.map(item => ({ id: item.id, quantity: item.quantity }))
            };

            if (orderType === 'Dine-In') {
                const tableId = parseInt(document.getElementById('posTableNum').value, 10);
                const minutes = parseInt(document.getElementById('posExpectedMinutes').value, 10);
                if (!tableId) {
                    alert('Select a table for a dine-in order.');
                    return;
                }
                if (!minutes || minutes <= 0) {
                    alert('Enter how many minutes the customer expects to stay.');
                    return;
                }
                payload.table_id = tableId;
                payload.expected_duration_minutes = minutes;
            }

            const btn = document.getElementById('btnProcessPayment');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';
            }

            fetch('../backend/api/pos-checkout.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('Order #' + data.order_id + ' successfully recorded! Total: ₱' + Number(data.total).toFixed(2));
                    if (typeof clearTicket === 'function') clearTicket();
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                    if (btn) btn.disabled = false;
                }
            })
            .catch(err => {
                console.error("Fetch Error:", err);
                alert('Server connection error. Please try again.');
                if (btn) btn.disabled = false;
            })
            .finally(() => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Complete & Print Receipt';
                }
            });
        }
    </script>
</body>
</html>