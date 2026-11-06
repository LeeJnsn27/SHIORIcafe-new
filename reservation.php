<?php
session_start();
date_default_timezone_set("Asia/Manila");
require_once "backend/config/db.php";

$isCustomer = isset($_SESSION["user_id"], $_SESSION["user_role"]) && $_SESSION["user_role"] === "Customer";

// Load real tables from the database (only customers need them)
$tables = [];
$tableLoadError = false;
if ($isCustomer) {
    try {
        $tables = $conn->query(
            'SELECT table_id, table_number, table_type, capacity, operational_status
               FROM shioricafe_schema."table"
              ORDER BY table_number'
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Reservation page table load failed: " . $e->getMessage());
        $tableLoadError = true;
    }
}

$minDate = date("Y-m-d");
$maxDate = date("Y-m-d", strtotime("+14 days"));

require_once "frontend/includes/header.php";
?>

<header class="page-header reservation-page-header text-center py-5">
    <div class="container">
        <h1 class="page-title display-5 fw-bold font-serif">Reserve Your Nook</h1>
        <p class="lead text-muted">Open daily from 5:00 AM to midnight. Reservations must end by 11:30 PM.</p>
    </div>
</header>

<section class="container reservation-section pb-5">
    <div class="row justify-content-center g-5">
        <div class="col-lg-7">
            <div id="reservationAlert"></div>

            <?php if (!$isCustomer): ?>
                <div class="card reservation-form-card shadow-sm border-0" style="border-radius:16px;">
                    <div class="card-body p-4 p-md-5 text-center">
                        <h4 class="fw-bold mb-2">Please log in to reserve</h4>
                        <p class="text-muted mb-4">Online reservations are available to registered customers.</p>
                        <a href="login.php" class="btn btn-cafe-primary px-4 me-2">Log In</a>
                        <a href="register.php" class="btn btn-outline-secondary px-4">Create Account</a>
                    </div>
                </div>
            <?php elseif ($tableLoadError || !$tables): ?>
                <div class="alert alert-warning">Tables are unavailable right now. Please try again later.</div>
            <?php else: ?>
            <form id="reservationForm" class="card reservation-form-card shadow-sm border-0" style="border-radius:16px;">
                <div class="card-body p-4 p-md-5">

                    <div class="mb-4 pb-3 border-bottom">
                        <h4 class="fw-bold mb-2 text-center">Choose Your Table</h4>
                        <p class="text-center text-muted small mb-3">Tap an available table below to select it.</p>

                        <div class="seat-legend d-flex justify-content-center gap-3 mb-3 small">
                            <span class="d-flex align-items-center"><span class="seat-swatch seat-swatch-available me-1"></span>Available</span>
                            <span class="d-flex align-items-center"><span class="seat-swatch seat-swatch-occupied me-1"></span>Under maintenance</span>
                            <span class="d-flex align-items-center"><span class="seat-swatch seat-swatch-selected me-1"></span>Selected</span>
                        </div>

                        <div class="seat-grid d-flex flex-wrap justify-content-center gap-2 p-3 rounded bg-transparent">
                            <?php foreach ($tables as $t):
                                $available = $t["operational_status"] === "Available"; ?>
                                <button type="button"
                                        class="seat-btn<?php echo $available ? "" : " disabled"; ?>"
                                        data-table-id="<?php echo (int) $t["table_id"]; ?>"
                                        data-table-number="<?php echo htmlspecialchars($t["table_number"]); ?>"
                                        data-type="<?php echo htmlspecialchars($t["table_type"]); ?>"
                                        data-capacity="<?php echo (int) $t["capacity"]; ?>"
                                        <?php echo $available ? "" : "disabled"; ?>>
                                    <?php echo htmlspecialchars($t["table_number"]); ?>
                                    <small class="d-block"><?php echo htmlspecialchars($t["table_type"]); ?> · <?php echo (int) $t["capacity"]; ?> seat<?php echo (int) $t["capacity"] > 1 ? "s" : ""; ?></small>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <p class="text-center mt-2 mb-0 fw-semibold small text-muted" id="selectedSeatDisplay">No table selected yet — tap one above.</p>
                    </div>

                    <!-- Selected table_id submitted to the server -->
                    <input type="hidden" id="selectedSeatInput" name="table_id" value="" required>

                    <p class="small text-muted mb-3">
                        Booking as <strong><?php echo htmlspecialchars($_SESSION["user_name"] ?? "Customer"); ?></strong>
                    </p>

                    <div class="mb-3">
                        <label for="party-size" class="form-label fw-bold">Party Size</label>
                        <input type="number" class="form-control" id="party-size" name="party_size" min="1" step="1" value="1" required>
                        <div class="form-text" id="partySizeHint">Select a table to see its capacity.</div>
                    </div>

                    <div class="mb-3">
                        <label for="reservation-date" class="form-label fw-bold">Reservation Date</label>
                        <input type="date" class="form-control" id="reservation-date" name="reservation_date"
                               min="<?php echo $minDate; ?>" max="<?php echo $maxDate; ?>" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label for="reservation-start-time" class="form-label fw-bold">Start Time</label>
                            <input type="time" class="form-control" id="reservation-start-time" name="reservation_time"
                                   min="05:00" max="23:30" step="60" required>
                        </div>
                        <div class="col-sm-6">
                            <label for="reservation-end-time" class="form-label fw-bold">End Time</label>
                            <input type="time" class="form-control" id="reservation-end-time" name="reservation_end_time"
                                   min="05:00" max="23:30" step="60" required>
                        </div>
                    </div>
                    <div class="form-text small mb-4">
                        Start at least 30 minutes from now, up to 14 days ahead. Sessions must end by 11:30 PM.
                    </div>

                    <button type="submit" class="btn btn-cafe-primary w-100 py-3 fw-bold" id="btnSubmitRes">
                        Confirm Reservation
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="card visit-card shadow-sm border-0 h-100" style="border-radius:16px;">
                <div class="card-body p-4">
                    <h5 class="card-title fw-bold mb-3">Visit Us</h5>
                    <p class="visit-item mb-2"><i class="bi bi-geo-alt me-2 text-success"></i>12 Bookmark Lane, San Pedro, Laguna</p>
                    <p class="visit-item mb-2"><i class="bi bi-telephone me-2 text-success"></i>(049) 555-0182</p>
                    <p class="visit-item mb-2"><i class="bi bi-envelope me-2 text-success"></i>hello@shioricafe.com</p>
                    <p class="visit-item mb-4"><i class="bi bi-clock me-2 text-success"></i>Daily · 5:00 AM to midnight</p>
                    <a class="btn btn-outline-secondary w-100 btn-sm" href="https://maps.google.com" target="_blank" rel="noopener">
                        <i class="bi bi-map me-1"></i>Open Location in Maps
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="frontend/assets/js/reservation.js"></script>

<?php require_once "frontend/includes/footer.php";
?>