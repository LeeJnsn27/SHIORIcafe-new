<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

const MAX_LINE_QUANTITY   = 99;
const CAFE_TIMEZONE       = 'Asia/Manila';
const SESSION_OPEN_TIME   = '05:00';   // café opens
const SESSION_LATEST_END  = '23:30';   // sessions must end 30 minutes before midnight closing
const MAX_STAY_MINUTES    = 1440;

final class ApiError extends RuntimeException
{
}

function respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body + ['success' => $status < 400]);
    exit();
}

function fail(int $status, string $message): never
{
    throw new ApiError($message, $status);
}

/** Returns a positive integer for an int or digit-only string, otherwise null. */
function parsePositiveInt($value): ?int
{
    if (is_int($value)) {
        return $value > 0 ? $value : null;
    }
    if (is_string($value) && ctype_digit($value) && strlen($value) <= 18) {
        $int = (int)$value;
        return $int > 0 ? $int : null;
    }
    return null;
}

// ---------------------------------------------------------------------------
// Authorization (runs before the database is opened or any input is read).
// POS checkout is staff only (Cashier or Manager). Same session keys and
// lowercase role normalization as create-order.php and backend/auth/auth-guard.php.
// ---------------------------------------------------------------------------
$rawRole = $_SESSION['user_role'] ?? $_SESSION['staff_role'] ?? $_SESSION['role'] ?? null;
$role    = $rawRole !== null ? strtolower(trim((string)$rawRole)) : null;
$staffId = parsePositiveInt($_SESSION['staff_id'] ?? null);

if ($staffId === null || !in_array($role, ['cashier', 'manager'], true)) {
    if ($role !== null || $staffId !== null || isset($_SESSION['customer_id'])) {
        respond(403, ['status' => 'error', 'message' => 'Only a Cashier or Manager can use POS checkout.']);
    }
    respond(401, ['status' => 'error', 'message' => 'Authentication required.']);
}

require_once __DIR__ . '/../config/db.php';

/** @var PDO $pdo */

/**
 * Validates cart lines. Client names, prices and totals are never trusted:
 * a line is identified by menu item ID, or by exact menu item name only when no ID is sent.
 */
function parseCartLines($items): array
{
    if (!is_array($items) || $items === []) {
        fail(400, 'Your cart is empty.');
    }
    $lines = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            fail(400, 'Invalid cart item.');
        }
        $quantity = parsePositiveInt($item['quantity'] ?? $item['qty'] ?? null);
        if ($quantity === null || $quantity > MAX_LINE_QUANTITY) {
            fail(400, 'Quantity must be a whole number from 1 to ' . MAX_LINE_QUANTITY . '.');
        }

        $rawId = $item['id'] ?? $item['menu_item_id'] ?? null;
        if ($rawId !== null) {
            $id = parsePositiveInt($rawId);
            if ($id === null) {
                fail(400, 'Invalid menu item ID.');
            }
            $lines[] = ['id' => $id, 'name' => null, 'qty' => $quantity];
        } else {
            $name = $item['name'] ?? $item['item_name'] ?? null;
            if (!is_string($name) || trim($name) === '' || strlen($name) > 200) {
                fail(400, 'Each cart item needs a menu item ID or name.');
            }
            $lines[] = ['id' => null, 'name' => trim($name), 'qty' => $quantity];
        }
    }
    return $lines;
}

/**
 * Resolves each line against shioricafe_schema.menu_item (active, in stock only).
 * Returns menu_item_id => [name, unit_cents, is_drink, qty]. Any unresolved line rejects the order.
 */
function resolveMenuItems(PDO $pdo, array $lines): array
{
    $base = "
        SELECT m.menu_item_id, m.item_name, m.current_price, c.is_drink_category
        FROM shioricafe_schema.menu_item m
        JOIN shioricafe_schema.category c ON c.category_id = m.category_id
        WHERE m.is_active = TRUE
          AND m.stock_status = 'In Stock'
    ";

    $byId = [];
    $ids  = array_values(array_unique(array_filter(array_column($lines, 'id'))));
    if ($ids) {
        $stmt = $pdo->prepare($base . ' AND m.menu_item_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
        $stmt->execute($ids);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $byId[(int)$row['menu_item_id']] = $row;
        }
    }

    // menu_item.item_name is unique case-insensitively (uq_menu_item_name_lower), so a name matches at most one item.
    $byName    = [];
    $nameStmt  = $pdo->prepare($base . ' AND LOWER(m.item_name) = LOWER(?)');
    foreach ($lines as $line) {
        if ($line['name'] !== null && !array_key_exists($line['name'], $byName)) {
            $nameStmt->execute([$line['name']]);
            $byName[$line['name']] = $nameStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    }

    $resolved = [];
    foreach ($lines as $line) {
        $row = $line['id'] !== null ? ($byId[$line['id']] ?? null) : ($byName[$line['name']] ?? null);
        if ($row === null) {
            fail(400, $line['id'] !== null
                ? 'Menu item ' . $line['id'] . ' is not available.'
                : 'Menu item "' . $line['name'] . '" is not available.');
        }
        $menuItemId = (int)$row['menu_item_id'];
        if (!isset($resolved[$menuItemId])) {
            $resolved[$menuItemId] = [
                'name'       => $row['item_name'],
                'unit_cents' => (int)round(((float)$row['current_price']) * 100),
                'is_drink'   => filter_var($row['is_drink_category'], FILTER_VALIDATE_BOOLEAN),
                'qty'        => 0,
            ];
        }
        $resolved[$menuItemId]['qty'] += $line['qty'];
        if ($resolved[$menuItemId]['qty'] > MAX_LINE_QUANTITY) {
            fail(400, 'Quantity must be a whole number from 1 to ' . MAX_LINE_QUANTITY . '.');
        }
    }
    return $resolved;
}

/**
 * Walk-in order through staff: creates a unique walk-in session (customer_id NULL, is_walkin TRUE).
 * Dine-In needs a table and an expected stay that ends by 23:30 (Asia/Manila), on an Available table
 * with no overlapping active reservation, and the session is Occupied.
 * Takeaway has no table and no end time, and its session is Completed when the transaction ends
 * (BUSINESS_RULES.md section 7), so it is created Completed with completed_at and completed_by_staff_id set.
 * Differs from create-order.php only in this takeaway status.
 */
function createWalkInSession(PDO $pdo, int $staffId, string $orderType, array $data): array
{
    $now   = new DateTimeImmutable('now', new DateTimeZone(CAFE_TIMEZONE));
    $start = $now->setTime((int)$now->format('H'), (int)$now->format('i'), 0);
    $end   = null;
    $table = null;

    if ($orderType === 'Dine-In') {
        $minutes = parsePositiveInt($data['expected_duration_minutes'] ?? null);
        if ($minutes === null || $minutes > MAX_STAY_MINUTES) {
            fail(400, 'Dine-in walk-ins need expected_duration_minutes (a positive whole number).');
        }
        $table = parsePositiveInt($data['table_id'] ?? null);
        if ($table === null) {
            fail(400, 'Dine-in walk-ins need a table_id.');
        }

        [$openH, $openM]   = array_map('intval', explode(':', SESSION_OPEN_TIME));
        [$closeH, $closeM] = array_map('intval', explode(':', SESSION_LATEST_END));
        $end = $start->modify("+$minutes minutes");
        if ($start < $start->setTime($openH, $openM, 0) || $end > $start->setTime($closeH, $closeM, 0)) {
            fail(400, 'Walk-in sessions must start after ' . SESSION_OPEN_TIME . ' and end by ' . SESSION_LATEST_END . ' the same day.');
        }

        $tableStmt = $pdo->prepare('SELECT operational_status FROM shioricafe_schema."table" WHERE table_id = :id FOR UPDATE');
        $tableStmt->execute([':id' => $table]);
        $status = $tableStmt->fetchColumn();
        if ($status === false) {
            fail(404, 'Table not found.');
        }
        if ($status !== 'Available') {
            fail(409, 'That table is not available.');
        }

        $overlap = $pdo->prepare("
            SELECT 1
            FROM shioricafe_schema.reservation
            WHERE table_id = :table_id
              AND status IN ('Confirmed', 'Adjustment_Pending', 'Occupied')
              AND reservation_start_at < CAST(:end_at AS timestamptz)
              AND reservation_end_at   > CAST(:start_at AS timestamptz)
            LIMIT 1
        ");
        $overlap->execute([
            ':table_id' => $table,
            ':start_at' => $start->format('Y-m-d H:i:sP'),
            ':end_at'   => $end->format('Y-m-d H:i:sP'),
        ]);
        if ($overlap->fetchColumn() !== false) {
            fail(409, 'That table is occupied or reserved during the expected stay.');
        }
    }

    // ck_reservation_completion: status = 'Completed' exactly when completed_at is set.
    $completed = $orderType === 'Takeaway';

    $stmt = $pdo->prepare("
        INSERT INTO shioricafe_schema.reservation (
            table_id, reservation_start_at, reservation_end_at, status, is_walkin,
            checked_in_at, checked_in_by_staff_id, completed_at, completed_by_staff_id
        ) VALUES (
            :table_id, CAST(:start_at AS timestamptz), CAST(:end_at AS timestamptz), :status, TRUE,
            CURRENT_TIMESTAMP, :staff_id, " . ($completed ? 'CURRENT_TIMESTAMP' : 'NULL') . ", :completed_by
        ) RETURNING reservation_id
    ");
    $stmt->execute([
        ':table_id'     => $table,
        ':start_at'     => $start->format('Y-m-d H:i:sP'),
        ':end_at'       => $end?->format('Y-m-d H:i:sP'),
        ':status'       => $completed ? 'Completed' : 'Occupied',
        ':staff_id'     => $staffId,
        ':completed_by' => $completed ? $staffId : null,
    ]);
    $reservationId = $stmt->fetchColumn();
    $stmt->closeCursor();

    return [
        'reservation_id' => (int)$reservationId,
        'table_id'       => $table,
        'order_type'     => $orderType,
    ];
}

try {
    $input = file_get_contents('php://input');
    $data  = json_decode($input, true) ?: $_POST;

    $orderType = $data['order_type'] ?? null;
    if (!in_array($orderType, ['Dine-In', 'Takeaway'], true)) {
        fail(400, 'order_type must be Dine-In or Takeaway.');
    }

    // Validate the cart and price it from the menu table before opening a transaction.
    $lines = parseCartLines($data['items'] ?? $data['cart'] ?? []);
    $menu  = resolveMenuItems($pdo, $lines);

    $totalCents = 0;
    foreach ($menu as $m) {
        $totalCents += $m['unit_cents'] * $m['qty'];
    }
    $grossAmount = number_format($totalCents / 100, 2, '.', '');

    // Walk-in session, order and order items are saved in one transaction.
    $pdo->beginTransaction();
    try {
        $session = createWalkInSession($pdo, $staffId, $orderType, $data);

        $stmt = $pdo->prepare("
            INSERT INTO shioricafe_schema.\"order\" (
                table_id, reservation_id, order_type, preparation_status, gross_order_amount, currency, created_at
            ) VALUES (
                :table_id, :reservation_id, :order_type, 'Pending', :amount, 'PHP', CURRENT_TIMESTAMP
            ) RETURNING order_id
        ");
        $stmt->execute([
            ':table_id'       => $session['table_id'],
            ':reservation_id' => $session['reservation_id'],
            ':order_type'     => $session['order_type'],
            ':amount'         => $grossAmount,
        ]);
        $orderId = $stmt->fetchColumn();
        $stmt->closeCursor();
        if (!$orderId) {
            throw new RuntimeException('Order insert did not return an order ID.');
        }

        $itemStmt = $pdo->prepare("
            INSERT INTO shioricafe_schema.order_item (
                order_id, menu_item_id, item_name_at_purchase, quantity, unit_price_at_purchase, is_drink_category_at_purchase
            ) VALUES (
                :order_id, :item_id, :item_name, :qty, :price, :is_drink
            )
        ");
        foreach ($menu as $menuItemId => $m) {
            $itemStmt->bindValue(':order_id', $orderId);
            $itemStmt->bindValue(':item_id', $menuItemId, PDO::PARAM_INT);
            $itemStmt->bindValue(':item_name', $m['name']);
            $itemStmt->bindValue(':qty', $m['qty'], PDO::PARAM_INT);
            $itemStmt->bindValue(':price', number_format($m['unit_cents'] / 100, 2, '.', ''));
            $itemStmt->bindValue(':is_drink', $m['is_drink'], PDO::PARAM_BOOL);
            $itemStmt->execute();
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    respond(200, [
        'status'         => 'success',
        'message'        => 'Order recorded successfully!',
        'order_id'       => $orderId,
        'reservation_id' => $session['reservation_id'],
        'total'          => (float)$grossAmount,
        'total_amount'   => (float)$grossAmount,
    ]);

} catch (ApiError $e) {
    respond($e->getCode(), ['status' => 'error', 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    $sqlState = $e->errorInfo[0] ?? (string)$e->getCode();
    if ($sqlState === '23P01') { // exclusion_violation: table already booked for that interval
        respond(409, ['status' => 'error', 'message' => 'That table is occupied or reserved during the expected stay.']);
    }
    error_log('pos-checkout.php database error: ' . $e->getMessage());
    respond(500, ['status' => 'error', 'message' => 'Server error. The order was not placed.']);
} catch (Throwable $e) {
    error_log('pos-checkout.php error: ' . $e->getMessage());
    respond(500, ['status' => 'error', 'message' => 'Server error. The order was not placed.']);
}
