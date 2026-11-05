<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../config/db.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const TZ                 = "Asia/Manila";
const OPEN_TIME          = "05:00";
const LAST_END_TIME      = "23:30";
const MIN_NOTICE_MINUTES = 30;
const MAX_DAYS_AHEAD     = 14;

// Statuses that block a table (business rules section 4)
const ACTIVE_STATUSES = ["Confirmed", "Adjustment_Pending", "Occupied"];

// Required minimum spend per reservation (business rules section 2): [up to 2h, over 2-4h, over 4h]
const MINIMUM_SPEND = [
    "Solo Study Nook"  => [50, 100, 150],
    "Duo Nook"         => [100, 150, 200],
    "Group Study Desk" => [200, 300, 400],
];

date_default_timezone_set(TZ);

function respond(array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

function fail(string $message, int $code = 400, array $extra = []): void {
    respond(array_merge(["status" => "error", "success" => false, "message" => $message], $extra), $code);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    fail("Method not allowed.", 405);
}

// ---------- Authenticated customer only ----------
if (!isset($_SESSION["user_id"], $_SESSION["user_role"])) {
    fail("Please log in to reserve a table.", 401);
}
if ($_SESSION["user_role"] !== "Customer") {
    fail("Only customer accounts can make reservations.", 403);
}
$customerId = (int) $_SESSION["user_id"];

// ---------- Read input ----------
$tableInput  = trim($_POST["table_id"] ?? "");
$resDate     = trim($_POST["reservation_date"] ?? $_POST["reservationDate"] ?? "");
$startInput  = trim($_POST["reservation_time"] ?? $_POST["reservationStartTime"] ?? "");
$endInput    = trim($_POST["reservation_end_time"] ?? $_POST["reservationEndTime"] ?? "");
$partyInput  = trim($_POST["party_size"] ?? $_POST["partySize"] ?? "");

// ---------- Validate ----------
if ($tableInput === "" || $resDate === "" || $startInput === "" || $endInput === "" || $partyInput === "") {
    fail("Please select a table and fill in the date, start time, end time, and party size.");
}

$requestedTableId = filter_var($tableInput, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
if ($requestedTableId === false) {
    fail("Invalid table selection.");
}

$partySize = filter_var($partyInput, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
if ($partySize === false) {
    fail("Party size must be a whole number of at least 1.");
}

$dateObj = DateTime::createFromFormat("!Y-m-d", $resDate);
if (!$dateObj || $dateObj->format("Y-m-d") !== $resDate) {
    fail("Invalid reservation date.");
}

// Minute precision: accept HH:MM, or HH:MM:00
$parseTime = function (string $t): ?string {
    if (preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])(?::00)?$/', $t, $m)) {
        return $m[1] . ":" . $m[2];
    }
    return null;
};
$startTime = $parseTime($startInput);
$endTime   = $parseTime($endInput);
if ($startTime === null || $endTime === null) {
    fail("Invalid start or end time. Use whole minutes (HH:MM).");
}

$startDt = new DateTime("$resDate $startTime:00");
$endDt   = new DateTime("$resDate $endTime:00");
$now     = new DateTime();

if ($endDt <= $startDt) {
    fail("End time must be later than the start time. Overnight reservations are not allowed.");
}
if ($startTime < OPEN_TIME || $endTime > LAST_END_TIME) {
    fail("Reservations must start at or after 5:00 AM and end by 11:30 PM.");
}
if ($startDt < (clone $now)->modify("+" . MIN_NOTICE_MINUTES . " minutes")) {
    fail("Reservations must start at least 30 minutes from now.");
}
if ($startDt > (clone $now)->modify("+" . MAX_DAYS_AHEAD . " days")) {
    fail("Reservations can be made up to 14 days in advance.");
}

$startAt = $startDt->format("Y-m-d H:i:sP");
$endAt   = $endDt->format("Y-m-d H:i:sP");
$durationMinutes = (int) (($endDt->getTimestamp() - $startDt->getTimestamp()) / 60);

// ---------- Database work ----------
try {
    $conn->beginTransaction();

    // 1. Customer must exist
    $custStmt = $conn->prepare("SELECT customer_id FROM shioricafe_schema.customer WHERE customer_id = ?");
    $custStmt->execute([$customerId]);
    if (!$custStmt->fetchColumn()) {
        $conn->rollBack();
        fail("Your account could not be found. Please log in again.", 401);
    }

    // 2. One active reservation per customer
    $in = implode(",", array_fill(0, count(ACTIVE_STATUSES), "?"));
    $activeStmt = $conn->prepare(
        "SELECT 1
           FROM shioricafe_schema.reservation
          WHERE customer_id = ?
            AND is_walkin = FALSE
            AND status IN ($in)
          LIMIT 1"
    );
    $activeStmt->execute(array_merge([$customerId], ACTIVE_STATUSES));
    if ($activeStmt->fetchColumn()) {
        $conn->rollBack();
        fail("You already have an active reservation. Cancel it before booking another.", 409);
    }

    // 3. Table: must exist, be operational, and fit the party (row lock serializes bookings per table)
    $tableStmt = $conn->prepare(
        'SELECT table_id, table_type, capacity, operational_status
           FROM shioricafe_schema."table"
          WHERE table_id = ?
          FOR UPDATE'
    );
    $tableStmt->execute([$requestedTableId]);
    $table = $tableStmt->fetch(PDO::FETCH_ASSOC);

    if (!$table) {
        $conn->rollBack();
        fail("The selected table does not exist.", 404);
    }
    if ($table["operational_status"] !== "Available") {
        $conn->rollBack();
        fail("The selected table is under maintenance and cannot be reserved.", 409);
    }
    if ($partySize > (int) $table["capacity"]) {
        $conn->rollBack();
        fail("The selected table seats up to " . (int) $table["capacity"] . " guest(s).");
    }
    $tableId = $table["table_id"];

    // 4. Overlap check against blocking reservations and occupied sessions (half-open: adjacent is allowed)
    $overlapStmt = $conn->prepare(
        "SELECT MAX(reservation_end_at) AS latest_end
           FROM shioricafe_schema.reservation
          WHERE table_id = ?
            AND status IN ($in)
            AND reservation_start_at < ?
            AND reservation_end_at > ?"
    );
    $overlapStmt->execute(array_merge([$tableId], ACTIVE_STATUSES, [$endAt, $startAt]));
    $latestEnd = $overlapStmt->fetchColumn();

    if ($latestEnd) {
        $conn->rollBack();
        $suggested = new DateTime($latestEnd);
        $suggested->setTimezone(new DateTimeZone(TZ));
        $extra = [];
        $message = "This table is already booked for that time window.";
        if ($suggested->format("Y-m-d") === $resDate && $suggested->format("H:i") <= LAST_END_TIME) {
            $message .= " The next available time for this table starts at " . $suggested->format("g:i A") . ".";
            $extra["suggested_time"] = $suggested->format("H:i");
        }
        fail($message, 409, $extra);
    }

    // 5. Required minimum spend (server-calculated, one amount per reservation)
    $band = $durationMinutes <= 120 ? 0 : ($durationMinutes <= 240 ? 1 : 2);
    $requiredMinimum = MINIMUM_SPEND[$table["table_type"]][$band] ?? null;
    if ($requiredMinimum === null) {
        throw new RuntimeException("No minimum spend defined for table type: " . $table["table_type"]);
    }

    // 6. Insert reservation
    $insert = $conn->prepare(
        "INSERT INTO shioricafe_schema.reservation
             (customer_id, table_id, party_size, reservation_start_at, reservation_end_at,
              status, required_minimum_amount, is_walkin)
         VALUES (?, ?, ?, ?, ?, 'Confirmed', ?, FALSE)
         RETURNING reservation_id"
    );
    $insert->execute([$customerId, $tableId, $partySize, $startAt, $endAt, number_format($requiredMinimum, 2, ".", "")]);
    $reservationId = $insert->fetchColumn();

    $conn->commit();

    respond([
        "status" => "success",
        "success" => true,
        "reservation_id" => $reservationId,
        "required_minimum_amount" => $requiredMinimum,
        "message" => "Reservation confirmed! Minimum spend for this reservation is ₱" . number_format($requiredMinimum, 2)
            . ". If at least 50% of the minimum is spent on drinks, any remaining shortfall is waived. "
            . "Otherwise, the shortfall is paid at checkout."
    ]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $state = $e->getCode();
    // Race-condition backstops from the database constraints
    if ($state === "23P01") { // ex_reservation_no_overlap
        fail("This table was just booked by someone else. Please choose another table or time.", 409);
    }
    if ($state === "23505") { // uq_reservation_one_active_per_customer
        fail("You already have an active reservation. Cancel it before booking another.", 409);
    }
    error_log("Reservation error [$state]: " . $e->getMessage());
    fail("Failed to save reservation.", 500);
} catch (RuntimeException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Reservation error: " . $e->getMessage());
    fail("Failed to save reservation.", 500);
}