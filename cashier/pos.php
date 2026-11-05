<?php
session_start();
require_once "../backend/config/db.php";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$menu_stmt = $conn->query("SELECT menu_item_id, name, price FROM shioricafe_schema.menu_item WHERE availability_status = 'Available'");
$menu_items = $menu_stmt->fetchAll(PDO::FETCH_ASSOC);

$tables_stmt = $conn->query("SELECT table_id, table_number FROM shioricafe_schema.\"table\" WHERE operational_status = 'Available'");
$tables = $tables_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>POS | Cashier Terminal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body class="bg-light">
    <!-- Navbar placeholder (keep your existing navbar here) -->
    
    <div class="container py-4">
        <div class="row g-4">
            <!-- Menu Grid -->
            <div class="col-md-8">
                <div class="row g-3">
                    <?php foreach ($menu_items as $item): ?>
                        <div class="col-md-4">
                            <div class="card h-100 shadow-sm border-0 text-center p-3">
                                <h6 class="fw-bold"><?php echo htmlspecialchars($item['name']); ?></h6>
                                <p class="text-success mb-2">₱<?php echo number_format($item['price'], 2); ?></p>
                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                    onclick="addToCart(<?php echo $item['menu_item_id']; ?>, '<?php echo addslashes($item['name']); ?>', <?php echo $item['price']; ?>)">
                                    + Add
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Cart Panel -->
            <div class="col-md-4">
                <div class="card p-4 shadow-sm h-100">
                    <h5 class="fw-bold mb-3">Current Order</h5>
                    <table class="table table-sm text-start mb-4">
                        <thead><tr><th>Item</th><th>Qty</th><th>Sub</th></tr></thead>
                        <tbody id="cartBody">
                            <!-- Populated via jQuery -->
                        </tbody>
                        <tfoot>
                            <tr><th colspan="2">Total:</th><th id="cartTotal">₱0.00</th></tr>
                        </tfoot>
                    </table>

                    <form action="process_walkin.php" method="POST" id="checkoutForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div id="cartInputs"></div> <!-- Hidden inputs injected here -->
                        
                        <select class="form-select mb-3" name="order_type" required>
                            <option value="" disabled selected>Order Type...</option>
                            <option value="Dine-In">Dine-In</option>
                            <option value="Takeaway">Takeaway</option>
                        </select>
                        
                        <select class="form-select mb-4" name="table_id">
                            <option value="" selected>Select Table (Dine-In)...</option>
                            <?php foreach($tables as $t): ?>
                                <option value="<?php echo $t['table_id']; ?>"><?php echo $t['table_number']; ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                        <button type="submit" class="btn btn-success w-100 py-2">Submit Order</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        let cart = {};

        function addToCart(id, name, price) {
            if (cart[id]) {
                cart[id].qty++;
            } else {
                cart[id] = { name: name, price: parseFloat(price), qty: 1 };
            }
            renderCart();
        }

        function renderCart() {
            let tbody = $('#cartBody');
            let hiddenInputs = $('#cartInputs');
            tbody.empty();
            hiddenInputs.empty();
            let total = 0;
            
            $.each(cart, function(id, item) {
                let subtotal = item.price * item.qty;
                total += subtotal;
                
                tbody.append(`<tr><td>${item.name}</td><td>${item.qty}</td><td>₱${subtotal.toFixed(2)}</td></tr>`);
                hiddenInputs.append(`<input type="hidden" name="items[${id}]" value="${item.qty}">`);
            });
            
            $('#cartTotal').text('₱' + total.toFixed(2));
        }

        $('#checkoutForm').on('submit', function(e) {
            if ($.isEmptyObject(cart)) {
                e.preventDefault();
                alert("Cart is empty!");
            }
        });
    </script>
</body>
</html>