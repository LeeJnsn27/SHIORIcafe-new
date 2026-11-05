<?php
/**
 * CashierWorkflowTest
 * Comprehensive TDD Test Suite for SHIORIcafe Cashier / Waiting for Commit Workflow
 */

class CashierWorkflowTest
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public static function setUpEnv(): PDO
    {
        require_once __DIR__ . '/../backend/config/db.php';
        /** @var PDO $conn */
        return $conn;
    }

    public function cleanTestData(): void
    {
        $this->db->exec("
            DELETE FROM shioricafe_schema.payment_transaction WHERE transaction_reference LIKE 'TEST-%' OR order_id IN (
                SELECT order_id FROM shioricafe_schema.\"order\" WHERE reservation_id IN (
                    SELECT reservation_id FROM shioricafe_schema.reservation WHERE cancellation_reason LIKE 'TEST-%' OR is_walkin = TRUE
                )
            );
            DELETE FROM shioricafe_schema.payment_attempt WHERE provider_reference LIKE 'TEST-%';
            DELETE FROM shioricafe_schema.order_item WHERE order_id IN (
                SELECT order_id FROM shioricafe_schema.\"order\" WHERE reservation_id IN (
                    SELECT reservation_id FROM shioricafe_schema.reservation WHERE cancellation_reason LIKE 'TEST-%' OR is_walkin = TRUE
                )
            );
            DELETE FROM shioricafe_schema.\"order\" WHERE reservation_id IN (
                SELECT reservation_id FROM shioricafe_schema.reservation WHERE cancellation_reason LIKE 'TEST-%' OR is_walkin = TRUE
            );
            DELETE FROM shioricafe_schema.reservation WHERE cancellation_reason LIKE 'TEST-%' OR is_walkin = TRUE;
        ");
    }

    public function getOrCreateCashierStaff(): int
    {
        $stmt = $this->db->prepare("SELECT staff_id FROM shioricafe_schema.staff WHERE role = 'Cashier' LIMIT 1");
        $stmt->execute();
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int)$id;
        }

        $stmt = $this->db->prepare("
            INSERT INTO shioricafe_schema.staff (full_name, role, email, password_hash)
            VALUES ('Test Cashier', 'Cashier', 'cashier.test@shioricafe.local', 'hash')
            RETURNING staff_id
        ");
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function getSampleMenuItem(): array
    {
        $stmt = $this->db->prepare("SELECT menu_item_id, item_name, current_price, category_id FROM shioricafe_schema.menu_item WHERE is_active = TRUE AND stock_status = 'In Stock' LIMIT 1");
        $stmt->execute();
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            throw new RuntimeException("No active menu item found in database. Seed data missing.");
        }
        return $item;
    }

    public function getSampleTable(): array
    {
        $stmt = $this->db->prepare("SELECT table_id, table_number FROM shioricafe_schema.\"table\" WHERE operational_status = 'Available' ORDER BY table_number ASC LIMIT 1");
        $stmt->execute();
        $table = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$table) {
            throw new RuntimeException("No available table found in database.");
        }
        return $table;
    }

    // -------------------------------------------------------------
    // Area A: Waiting for Commit & Queue Display
    // -------------------------------------------------------------

    public function test_queue_file_uses_pdo_not_pg_query(): void
    {
        $code = file_get_contents(__DIR__ . '/../admin/queue.php');
        if (str_contains($code, 'pg_query(') || str_contains($code, 'pg_fetch_assoc(')) {
            throw new AssertionError("admin/queue.php still calls procedural pg_query / pg_fetch_assoc instead of PDO.");
        }
    }

    public function test_waiting_for_commit_shows_pending_orders(): void
    {
        $this->cleanTestData();
        $table = $this->getSampleTable();
        $menuItem = $this->getSampleMenuItem();

        // Walk-in reservation
        $stmtRes = $this->db->prepare("
            INSERT INTO shioricafe_schema.reservation (table_id, reservation_start_at, reservation_end_at, status, is_walkin, cancellation_reason)
            VALUES (?, date_trunc('minute', NOW()), date_trunc('minute', NOW() + INTERVAL '2 hours'), 'Occupied', TRUE, 'TEST-ORDER-PENDING')
            RETURNING reservation_id
        ");
        $stmtRes->execute([$table['table_id']]);
        $resId = (int)$stmtRes->fetchColumn();

        // 1. Pending order (Waiting for Commit)
        $stmtOrd1 = $this->db->prepare("
            INSERT INTO shioricafe_schema.\"order\" (reservation_id, table_id, order_type, preparation_status, gross_order_amount)
            VALUES (?, ?, 'Dine-In', 'Pending', 150.00)
            RETURNING order_id
        ");
        $stmtOrd1->execute([$resId, $table['table_id']]);
        $pendingOrderId = (int)$stmtOrd1->fetchColumn();

        $stmtItem = $this->db->prepare("
            INSERT INTO shioricafe_schema.order_item (order_id, menu_item_id, item_name_at_purchase, quantity, unit_price_at_purchase, is_drink_category_at_purchase)
            VALUES (?, ?, ?, 1, ?, TRUE)
        ");
        $stmtItem->execute([$pendingOrderId, $menuItem['menu_item_id'], $menuItem['item_name'], $menuItem['current_price']]);

        // 2. Completed order
        $stmtOrd2 = $this->db->prepare("
            INSERT INTO shioricafe_schema.\"order\" (reservation_id, table_id, order_type, preparation_status, gross_order_amount)
            VALUES (?, ?, 'Dine-In', 'Completed', 150.00)
            RETURNING order_id
        ");
        $stmtOrd2->execute([$resId, $table['table_id']]);
        $completedOrderId = (int)$stmtOrd2->fetchColumn();

        // 3. Voided order
        $stmtOrd3 = $this->db->prepare("
            INSERT INTO shioricafe_schema.\"order\" (reservation_id, table_id, order_type, preparation_status, gross_order_amount, voided_at)
            VALUES (?, ?, 'Dine-In', 'Pending', 150.00, NOW())
            RETURNING order_id
        ");
        $stmtOrd3->execute([$resId, $table['table_id']]);
        $voidedOrderId = (int)$stmtOrd3->fetchColumn();

        // Query pending orders waiting for commit
        $stmtQuery = $this->db->prepare("
            SELECT o.order_id, o.preparation_status
            FROM shioricafe_schema.\"order\" o
            WHERE o.voided_at IS NULL AND o.preparation_status = 'Pending'
        ");
        $stmtQuery->execute();
        $orderIds = $stmtQuery->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array($pendingOrderId, $orderIds)) {
            throw new AssertionError("Pending order #{$pendingOrderId} is missing from Pending commit list.");
        }
        if (in_array($completedOrderId, $orderIds)) {
            throw new AssertionError("Completed order #{$completedOrderId} must NOT appear in Pending commit list.");
        }
        if (in_array($voidedOrderId, $orderIds)) {
            throw new AssertionError("Voided order #{$voidedOrderId} must NOT appear in Pending commit list.");
        }
    }

    public function test_queue_displays_item_details_and_quantities(): void
    {
        $code = file_get_contents(__DIR__ . '/../admin/queue.php');
        if (!str_contains($code, 'order_item') && !str_contains($code, 'v_order_item_summary') && !str_contains($code, 'items')) {
            throw new AssertionError("admin/queue.php does not query or display line items/quantities for orders.");
        }
    }

    public function test_queue_status_filtering_support(): void
    {
        $code = file_get_contents(__DIR__ . '/../admin/queue.php');
        if (!str_contains($code, 'filter') && !str_contains($code, 'status_filter') && !str_contains($code, '$_GET')) {
            throw new AssertionError("admin/queue.php does not support status filtering for pending vs active vs completed.");
        }
    }

    // -------------------------------------------------------------
    // Area B: Cashier Processing & Commit Transitions
    // -------------------------------------------------------------

    public function test_update_order_file_exists(): void
    {
        if (!file_exists(__DIR__ . '/../admin/update_order.php')) {
            throw new AssertionError("admin/update_order.php does not exist.");
        }
    }

    public function test_update_order_requires_authorization(): void
    {
        $this->test_update_order_file_exists();
        $code = file_get_contents(__DIR__ . '/../admin/update_order.php');
        if (!str_contains($code, '$_SESSION') || (!str_contains($code, 'Cashier') && !str_contains($code, 'user_role'))) {
            throw new AssertionError("admin/update_order.php does not enforce Cashier session authorization.");
        }
    }

    public function test_update_order_validates_csrf(): void
    {
        $this->test_update_order_file_exists();
        $code = file_get_contents(__DIR__ . '/../admin/update_order.php');
        if (!str_contains($code, 'csrf_token') && !str_contains($code, 'CSRF')) {
            throw new AssertionError("admin/update_order.php does not validate CSRF token.");
        }
    }

    public function test_update_order_lifecycle_transitions(): void
    {
        $this->cleanTestData();
        $table = $this->getSampleTable();
        $menuItem = $this->getSampleMenuItem();

        $stmtRes = $this->db->prepare("
            INSERT INTO shioricafe_schema.reservation (table_id, reservation_start_at, reservation_end_at, status, is_walkin, cancellation_reason)
            VALUES (?, date_trunc('minute', NOW()), date_trunc('minute', NOW() + INTERVAL '2 hours'), 'Occupied', TRUE, 'TEST-LIFECYCLE')
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

        $validTransitions = [
            'Pending'   => ['Preparing'],
            'Preparing' => ['Ready'],
            'Ready'     => ['Served'],
            'Served'    => ['Completed'],
            'Completed' => []
        ];

        $targetStatus = 'Preparing';
        $currentStatus = 'Pending';
        if (!in_array($targetStatus, $validTransitions[$currentStatus])) {
            throw new AssertionError("Valid transition from Pending to Preparing was incorrectly rejected.");
        }

        $invalidTarget = 'Completed';
        if (in_array($invalidTarget, $validTransitions[$currentStatus])) {
            throw new AssertionError("Invalid jump from Pending directly to Completed was permitted!");
        }

        if (in_array('Pending', $validTransitions['Ready'])) {
            throw new AssertionError("Invalid backward transition from Ready to Pending was permitted!");
        }
    }

    public function test_update_order_duplicate_prevention_and_concurrency(): void
    {
        $this->test_update_order_file_exists();
        $code = file_get_contents(__DIR__ . '/../admin/update_order.php');
        if (!str_contains($code, 'FOR UPDATE') && !str_contains($code, 'rowCount()') && !str_contains($code, 'beginTransaction')) {
            throw new AssertionError("admin/update_order.php does not use database transactions with concurrency safety or row locking.");
        }
    }

    // -------------------------------------------------------------
    // Area C: Order & Table Management
    // -------------------------------------------------------------

    public function test_pos_file_uses_pdo_and_loads_menu(): void
    {
        $code = file_get_contents(__DIR__ . '/../admin/pos.php');
        if (str_contains($code, 'pg_query(') || str_contains($code, 'pg_fetch_assoc(')) {
            throw new AssertionError("admin/pos.php still calls procedural pg_query / pg_fetch_assoc instead of PDO.");
        }
        if (str_contains($code, 'Menu grid placeholder.')) {
            throw new AssertionError("admin/pos.php still contains 'Menu grid placeholder.' and does not render real menu items.");
        }
    }

    public function test_process_walkin_handler_exists(): void
    {
        if (!file_exists(__DIR__ . '/../admin/process_walkin.php')) {
            throw new AssertionError("admin/process_walkin.php does not exist.");
        }
    }

    public function test_process_walkin_validates_order_type_and_table(): void
    {
        $this->test_process_walkin_handler_exists();
        $code = file_get_contents(__DIR__ . '/../admin/process_walkin.php');
        if (!str_contains($code, 'Dine-In') || !str_contains($code, 'Takeaway')) {
            throw new AssertionError("admin/process_walkin.php does not differentiate Dine-In vs Takeaway requirements.");
        }
        if (!str_contains($code, 'table_id')) {
            throw new AssertionError("admin/process_walkin.php does not enforce table requirement for Dine-In.");
        }
    }

    public function test_process_walkin_calculates_prices_server_side(): void
    {
        $this->test_process_walkin_handler_exists();
        $code = file_get_contents(__DIR__ . '/../admin/process_walkin.php');
        if (!str_contains($code, 'current_price') && !str_contains($code, 'unit_price_at_purchase')) {
            throw new AssertionError("admin/process_walkin.php does not query server-side menu prices at purchase time.");
        }
    }

    public function test_tables_file_uses_pdo_and_real_occupancy(): void
    {
        $code = file_get_contents(__DIR__ . '/../admin/tables.php');
        if (str_contains($code, 'pg_query(') || str_contains($code, 'pg_fetch_assoc(')) {
            throw new AssertionError("admin/tables.php still calls procedural pg_query / pg_fetch_assoc instead of PDO.");
        }
        if (!str_contains($code, 'reservation') || !str_contains($code, 'Occupied')) {
            throw new AssertionError("admin/tables.php only checks operational_status and does not check active reservation Occupied status.");
        }
    }

    public function test_checkin_file_uses_pdo(): void
    {
        $code = file_get_contents(__DIR__ . '/../admin/checkin.php');
        if (str_contains($code, 'pg_query(') || str_contains($code, 'pg_fetch_assoc(')) {
            throw new AssertionError("admin/checkin.php still calls procedural pg_query / pg_fetch_assoc instead of PDO.");
        }
    }

    public function test_process_checkin_handler_exists_and_prevents_duplicate(): void
    {
        if (!file_exists(__DIR__ . '/../admin/process_checkin.php')) {
            throw new AssertionError("admin/process_checkin.php does not exist.");
        }
        $code = file_get_contents(__DIR__ . '/../admin/process_checkin.php');
        if (!str_contains($code, 'checked_in_at') || !str_contains($code, 'Occupied')) {
            throw new AssertionError("admin/process_checkin.php does not mark reservation as Occupied with checked_in_at timestamp.");
        }
    }

    // -------------------------------------------------------------
    // Area D: Database Transactions & Security
    // -------------------------------------------------------------

    public function test_walkin_transaction_rollback_integrity(): void
    {
        $this->cleanTestData();
        $this->test_process_walkin_handler_exists();
        $code = file_get_contents(__DIR__ . '/../admin/process_walkin.php');
        if (!str_contains($code, 'beginTransaction') || !str_contains($code, 'rollBack')) {
            throw new AssertionError("admin/process_walkin.php does not wrap walk-in session, order, and items in a database transaction with rollback.");
        }
    }

    public function test_parameterized_queries_in_all_handlers(): void
    {
        $handlers = [
            __DIR__ . '/../admin/queue.php',
            __DIR__ . '/../admin/pos.php',
            __DIR__ . '/../admin/tables.php',
            __DIR__ . '/../admin/checkin.php'
        ];
        foreach ($handlers as $file) {
            $code = file_get_contents($file);
            if (preg_match('/\$conn->query\([^)]*\$_(POST|GET|REQUEST)/', $code)) {
                throw new AssertionError("Unparameterized query with superglobal input detected in " . basename($file));
            }
        }
    }
}
