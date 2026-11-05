<?php
/**
 * CashierWorkflowIntegrationTest
 * End-to-end integration tests for SHIORIcafe Cashier handlers using isolated subprocess execution.
 */

require_once __DIR__ . '/CashierWorkflowTest.php';

class CashierWorkflowIntegrationTest
{
    private PDO $db;
    private CashierWorkflowTest $helper;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->helper = new CashierWorkflowTest($db);
    }

    private function runSubprocess(string $scriptFile, array $session, array $post): array
    {
        $runnerCode = '<?php
session_start();
$_SESSION = ' . var_export($session, true) . ';
$_SERVER["REQUEST_METHOD"] = "POST";
$_POST = ' . var_export($post, true) . ';
register_shutdown_function(function() {
    echo "\n---SESSION_START---\n";
    echo json_encode($_SESSION);
    echo "\n---SESSION_END---\n";
});
require ' . var_export($scriptFile, true) . ';
';

        $tempScript = __DIR__ . '/temp_runner_' . bin2hex(random_bytes(6)) . '.php';
        file_put_contents($tempScript, $runnerCode);

        $output = [];
        $exitCode = 0;
        exec("php -dextension=pdo_pgsql -dextension=pgsql " . escapeshellarg($tempScript), $output, $exitCode);
        @unlink($tempScript);

        $rawOutput = implode("\n", $output);
        $finalSession = [];
        if (preg_match('/---SESSION_START---\s*(.*?)\s*---SESSION_END---/s', $rawOutput, $matches)) {
            $finalSession = json_decode($matches[1], true) ?? [];
        }

        return [
            'exitCode' => $exitCode,
            'output'   => $rawOutput,
            'session'  => $finalSession
        ];
    }

    public function test_e2e_walkin_takeaway_creation_and_payment(): void
    {
        $this->helper->cleanTestData();
        $menuItem = $this->helper->getSampleMenuItem();
        $token = 'test-token-takeaway';

        $session = [
            'user_role'  => 'Cashier',
            'user_id'    => 1,
            'user_name'  => 'Test Cashier',
            'csrf_token' => $token
        ];

        $post = [
            'csrf_token' => $token,
            'order_type' => 'Takeaway',
            'items' => [
                $menuItem['menu_item_id'] => 2
            ]
        ];

        $res = $this->runSubprocess(__DIR__ . '/../admin/process_walkin.php', $session, $post);

        // Verify Reservation
        $stmtRes = $this->db->prepare("
            SELECT reservation_id, table_id, status, is_walkin, completed_at
            FROM shioricafe_schema.reservation
            WHERE is_walkin = TRUE
            ORDER BY reservation_id DESC LIMIT 1
        ");
        $stmtRes->execute();
        $dbRes = $stmtRes->fetch(PDO::FETCH_ASSOC);

        if (!$dbRes) {
            throw new AssertionError("Walk-in reservation was not created for takeaway order. Subprocess output: " . $res['output']);
        }
        if ($dbRes['table_id'] !== null) {
            throw new AssertionError("Takeaway reservation must have table_id = NULL.");
        }
        if ($dbRes['status'] !== 'Completed') {
            throw new AssertionError("Takeaway reservation status must be 'Completed', got: {$dbRes['status']}");
        }

        // Verify Order
        $stmtOrd = $this->db->prepare("
            SELECT order_id, reservation_id, table_id, order_type, preparation_status, gross_order_amount
            FROM shioricafe_schema.\"order\"
            WHERE reservation_id = ?
        ");
        $stmtOrd->execute([$dbRes['reservation_id']]);
        $order = $stmtOrd->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            throw new AssertionError("Order record was not created.");
        }
        if ($order['order_type'] !== 'Takeaway') {
            throw new AssertionError("Order type must be Takeaway.");
        }
        $expectedTotal = round((float)$menuItem['current_price'] * 2, 2);
        if ((float)$order['gross_order_amount'] !== $expectedTotal) {
            throw new AssertionError("Expected order gross amount {$expectedTotal}, got {$order['gross_order_amount']}");
        }

        // Verify Order Items
        $stmtItems = $this->db->prepare("
            SELECT order_item_id, menu_item_id, quantity, unit_price_at_purchase, line_gross_amount
            FROM shioricafe_schema.order_item
            WHERE order_id = ?
        ");
        $stmtItems->execute([$order['order_id']]);
        $item = $stmtItems->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            throw new AssertionError("Order item was not recorded.");
        }
        if ((int)$item['quantity'] !== 2) {
            throw new AssertionError("Expected quantity 2, got {$item['quantity']}");
        }
        if ((float)$item['unit_price_at_purchase'] !== (float)$menuItem['current_price']) {
            throw new AssertionError("Unit price snapshot must equal current menu catalog price.");
        }

        // Verify Captured Payment
        $stmtTx = $this->db->prepare("
            SELECT payment_transaction_id, payment_method, amount, status
            FROM shioricafe_schema.payment_transaction
            WHERE order_id = ?
        ");
        $stmtTx->execute([$order['order_id']]);
        $tx = $stmtTx->fetch(PDO::FETCH_ASSOC);

        if (!$tx) {
            throw new AssertionError("Cash payment transaction was not recorded.");
        }
        if ($tx['payment_method'] !== 'Cash' || $tx['status'] !== 'CAPTURED') {
            throw new AssertionError("Payment tender must be Cash and status CAPTURED.");
        }
        if ((float)$tx['amount'] !== $expectedTotal) {
            throw new AssertionError("Payment amount must match order total.");
        }
    }

    public function test_e2e_walkin_dinein_requires_and_occupies_table(): void
    {
        $this->helper->cleanTestData();
        $table = $this->helper->getSampleTable();
        $menuItem = $this->helper->getSampleMenuItem();
        $token = 'test-token-dinein';

        $session = [
            'user_role'  => 'Cashier',
            'user_id'    => 1,
            'user_name'  => 'Test Cashier',
            'csrf_token' => $token
        ];

        $post = [
            'csrf_token' => $token,
            'order_type' => 'Dine-In',
            'table_id'   => $table['table_id'],
            'duration_minutes' => 120,
            'items' => [
                $menuItem['menu_item_id'] => 1
            ]
        ];

        $res = $this->runSubprocess(__DIR__ . '/../admin/process_walkin.php', $session, $post);

        // Verify reservation is Occupied on the table
        $stmtRes = $this->db->prepare("
            SELECT reservation_id, table_id, status, is_walkin, reservation_end_at
            FROM shioricafe_schema.reservation
            WHERE table_id = ? AND status = 'Occupied'
        ");
        $stmtRes->execute([$table['table_id']]);
        $dbRes = $stmtRes->fetch(PDO::FETCH_ASSOC);

        if (!$dbRes) {
            throw new AssertionError("Dine-In reservation was not created or not Occupied. Output: " . $res['output']);
        }
        if ($dbRes['reservation_end_at'] === null) {
            throw new AssertionError("Dine-In reservation must have reservation_end_at set.");
        }

        // Now test duplicate dine-in on same occupied table must be rejected
        $dupRes = $this->runSubprocess(__DIR__ . '/../admin/process_walkin.php', $session, $post);
        $errMsg = $dupRes['session']['error_msg'] ?? '';
        if (!str_contains($errMsg, 'occupied')) {
            throw new AssertionError("Dine-in on an already occupied table was not rejected. Error: {$errMsg}");
        }
    }

    public function test_e2e_cashier_order_lifecycle_commits(): void
    {
        $this->helper->cleanTestData();
        $table = $this->helper->getSampleTable();
        $token = 'test-token-update';

        // Insert pending order
        $stmtRes = $this->db->prepare("
            INSERT INTO shioricafe_schema.reservation (table_id, reservation_start_at, reservation_end_at, status, is_walkin, cancellation_reason)
            VALUES (?, date_trunc('minute', NOW()), date_trunc('minute', NOW() + INTERVAL '2 hours'), 'Occupied', TRUE, 'TEST-COMMIT')
            RETURNING reservation_id
        ");
        $stmtRes->execute([$table['table_id']]);
        $resId = (int)$stmtRes->fetchColumn();

        $stmtOrd = $this->db->prepare("
            INSERT INTO shioricafe_schema.\"order\" (reservation_id, table_id, order_type, preparation_status, gross_order_amount)
            VALUES (?, ?, 'Dine-In', 'Pending', 150.00)
            RETURNING order_id
        ");
        $stmtOrd->execute([$resId, $table['table_id']]);
        $orderId = (int)$stmtOrd->fetchColumn();

        $session = [
            'user_role'  => 'Cashier',
            'user_id'    => 1,
            'user_name'  => 'Test Cashier',
            'csrf_token' => $token
        ];

        // Step 1: Cashier commits order to preparation (Pending -> Preparing)
        $post = [
            'csrf_token' => $token,
            'order_id'   => $orderId,
            'new_status' => 'Preparing'
        ];
        $this->runSubprocess(__DIR__ . '/../admin/update_order.php', $session, $post);

        $status = $this->db->query("SELECT preparation_status FROM shioricafe_schema.\"order\" WHERE order_id = {$orderId}")->fetchColumn();
        if ($status !== 'Preparing') {
            throw new AssertionError("Order failed to transition from Pending to Preparing. Status: {$status}");
        }

        // Step 2: Duplicate commit attempt (Preparing -> Preparing)
        $dupRes = $this->runSubprocess(__DIR__ . '/../admin/update_order.php', $session, $post);
        $errMsg = $dupRes['session']['error_msg'] ?? '';
        if (!str_contains($errMsg, 'Duplicate update prevented')) {
            throw new AssertionError("Duplicate commit was not caught. Error: {$errMsg}");
        }

        // Step 3: Advance to Ready
        $post['new_status'] = 'Ready';
        $this->runSubprocess(__DIR__ . '/../admin/update_order.php', $session, $post);
        $status = $this->db->query("SELECT preparation_status FROM shioricafe_schema.\"order\" WHERE order_id = {$orderId}")->fetchColumn();
        if ($status !== 'Ready') {
            throw new AssertionError("Order failed to transition to Ready. Status: {$status}");
        }

        // Step 4: Advance to Served
        $post['new_status'] = 'Served';
        $this->runSubprocess(__DIR__ . '/../admin/update_order.php', $session, $post);
        $status = $this->db->query("SELECT preparation_status FROM shioricafe_schema.\"order\" WHERE order_id = {$orderId}")->fetchColumn();
        if ($status !== 'Served') {
            throw new AssertionError("Order failed to transition to Served. Status: {$status}");
        }

        // Step 5: Advance to Completed
        $post['new_status'] = 'Completed';
        $this->runSubprocess(__DIR__ . '/../admin/update_order.php', $session, $post);
        $status = $this->db->query("SELECT preparation_status FROM shioricafe_schema.\"order\" WHERE order_id = {$orderId}")->fetchColumn();
        if ($status !== 'Completed') {
            throw new AssertionError("Order failed to transition to Completed. Status: {$status}");
        }
    }

    public function test_e2e_checkin_flow_and_duplicate_prevention(): void
    {
        $this->helper->cleanTestData();
        $cashierId = $this->helper->getOrCreateCashierStaff();
        $table = $this->helper->getSampleTable();
        $token = 'test-token-checkin';

        // Create dummy customer
        $stmtCust = $this->db->prepare("
            INSERT INTO shioricafe_schema.customer (full_name, email, password_hash)
            VALUES ('Guest Checkin', 'checkin.test@shioricafe.local', 'hash')
            ON CONFLICT (LOWER(email)) DO UPDATE SET full_name = EXCLUDED.full_name
            RETURNING customer_id
        ");
        $stmtCust->execute();
        $custId = (int)$stmtCust->fetchColumn();

        // Create confirmed online reservation
        $stmtRes = $this->db->prepare("
            INSERT INTO shioricafe_schema.reservation (
                customer_id, table_id, party_size, reservation_start_at, reservation_end_at, status, required_minimum_amount, is_walkin, cancellation_reason
            ) VALUES (
                ?, ?, 1, date_trunc('minute', NOW()), date_trunc('minute', NOW() + INTERVAL '2 hours'), 'Confirmed', 100.00, FALSE, 'TEST-CHECKIN'
            ) RETURNING reservation_id
        ");
        $stmtRes->execute([$custId, $table['table_id']]);
        $resId = (int)$stmtRes->fetchColumn();

        $session = [
            'user_role'  => 'Cashier',
            'user_id'    => $cashierId,
            'user_name'  => 'Test Cashier',
            'csrf_token' => $token
        ];

        $post = [
            'csrf_token'     => $token,
            'reservation_id' => $resId
        ];

        $this->runSubprocess(__DIR__ . '/../admin/process_checkin.php', $session, $post);

        // Verify status is Occupied and checked_in_at is set
        $stmtCheck = $this->db->prepare("
            SELECT status, checked_in_at, checked_in_by_staff_id
            FROM shioricafe_schema.reservation
            WHERE reservation_id = ?
        ");
        $stmtCheck->execute([$resId]);
        $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($row['status'] !== 'Occupied') {
            throw new AssertionError("Checked in reservation must have status 'Occupied', got: {$row['status']}");
        }
        if ($row['checked_in_at'] === null) {
            throw new AssertionError("checked_in_at timestamp must not be null.");
        }

        // Second check-in attempt (Duplicate check-in prevention)
        $dupRes = $this->runSubprocess(__DIR__ . '/../admin/process_checkin.php', $session, $post);
        $errMsg = $dupRes['session']['error_msg'] ?? '';
        if (!str_contains($errMsg, 'Duplicate check-in prevented') && !str_contains($errMsg, 'already checked in')) {
            throw new AssertionError("Duplicate check-in was not prevented. Error: {$errMsg}");
        }
    }

    public function test_e2e_unauthorized_guest_rejected(): void
    {
        $token = 'test-token-unauth';
        // Test with Customer role
        $session = [
            'user_role' => 'Customer',
            'csrf_token' => $token
        ];
        $post = [
            'csrf_token' => $token,
            'order_id' => 99999,
            'new_status' => 'Preparing'
        ];

        $res = $this->runSubprocess(__DIR__ . '/../admin/update_order.php', $session, $post);
        // Expect redirect to login.php, no changes
        if (!empty($res['session']['success_msg'])) {
            throw new AssertionError("Customer role should never be authorized to update order status.");
        }
    }

    public function test_e2e_invalid_csrf_token_rejected(): void
    {
        $session = [
            'user_role'  => 'Cashier',
            'csrf_token' => 'legitimate-token-123'
        ];
        $post = [
            'csrf_token' => 'forged-token-456',
            'order_id'   => 1,
            'new_status' => 'Preparing'
        ];

        $res = $this->runSubprocess(__DIR__ . '/../admin/update_order.php', $session, $post);
        $errMsg = $res['session']['error_msg'] ?? '';
        if (!str_contains($errMsg, 'CSRF') && !str_contains($errMsg, 'Security validation')) {
            throw new AssertionError("Request with invalid CSRF token was not rejected. Error: {$errMsg}");
        }
    }
}
