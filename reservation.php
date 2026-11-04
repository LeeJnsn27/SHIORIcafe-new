<?php
session_start();
require_once "frontend/includes/header.php";
?>

<header class="page-header reservation-page-header text-center py-5">
    <div class="container">
        <h1 class="page-title display-5 fw-bold font-serif">Reserve Your Nook</h1>
        <p class="lead text-muted">Open 24/7. Select your preferred seat and custom time slot below.</p>
    </div>
</header>

<section class="container reservation-section pb-5">
    <div class="row justify-content-center g-5">
        <div class="col-lg-7">
            <div id="reservationAlert"></div>

            <form id="reservationForm" class="card reservation-form-card shadow-sm border-0" style="border-radius:16px;">
                <div class="card-body p-4 p-md-5">

                    <!-- Seat Selection Grid moved inside the top of the form -->
                    <div class="mb-4 pb-3 border-bottom">
                        <h4 class="fw-bold mb-2 text-center">Choose Your Seat</h4>
                        <p class="text-center text-muted small mb-3">Tap an available seat below to select it.</p>

                        <div class="seat-legend d-flex justify-content-center gap-3 mb-3 small">
                            <span class="d-flex align-items-center"><span class="seat-swatch seat-swatch-available me-1"></span>Available</span>
                            <span class="d-flex align-items-center"><span class="seat-swatch seat-swatch-occupied me-1"></span>Occupied</span>
                            <span class="d-flex align-items-center"><span class="seat-swatch seat-swatch-selected me-1"></span>Selected</span>
                        </div>

                        <div class="seat-grid d-flex flex-wrap justify-content-center gap-2 p-3 rounded bg-transparent">
                            <button type="button" class="seat-btn" data-seat="Tatami Desk 1" data-type="Tatami Desk">Tatami 1</button>
                            <button type="button" class="seat-btn" data-seat="Tatami Desk 2" data-type="Tatami Desk">Tatami 2</button>
                            <button type="button" class="seat-btn" data-seat="Tatami Desk 3" data-type="Tatami Desk">Tatami 3</button>
                            <button type="button" class="seat-btn" data-seat="Window Nook 1" data-type="Window Nook">Window 1</button>
                            <button type="button" class="seat-btn" data-seat="Window Nook 2" data-type="Window Nook">Window 2</button>
                            <button type="button" class="seat-btn" data-seat="Window Nook 3" data-type="Window Nook">Window 3</button>
                            <button type="button" class="seat-btn" data-seat="Group Table 1" data-type="Group Study Table">Group 1</button>
                            <button type="button" class="seat-btn" data-seat="Group Table 2" data-type="Group Study Table">Group 2</button>
                        </div>
                        <p class="text-center mt-2 mb-0 fw-semibold small text-muted" id="selectedSeatDisplay">No seat selected yet — tap one above.</p>
                    </div>

                    <!-- Hidden Input for selected seat -->
                    <input type="hidden" id="selectedSeatInput" name="nook_type" value="" required>

                    <div class="mb-3">
                        <label for="guest-name" class="form-label fw-bold">Your Name</label>
                        <input type="text" class="form-control" id="guest-name" name="guest_name" placeholder="Full Name" required>
                    </div>

                    <div class="mb-3">
                        <label for="reservation-date" class="form-label fw-bold">Reservation Date</label>
                        <input type="date" class="form-control" id="reservation-date" name="reservation_date" min="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <!-- 24/7 Start and End Time Fields -->
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label for="reservation-start-time" class="form-label fw-bold">Start Time</label>
                            <input type="time" class="form-control" id="reservation-start-time" name="reservation_time" required>
                        </div>
                        <div class="col-sm-6">
                            <label for="reservation-end-time" class="form-label fw-bold">End Time</label>
                            <input type="time" class="form-control" id="reservation-end-time" name="reservation_end_time" required>
                        </div>
                    </div>
                    <div class="form-text small mb-4">Café is open 24/7. Select your custom session window.</div>

                    <div class="mb-4">
                        <label for="guest-email" class="form-label fw-bold">Email Address</label>
                        <input type="email" class="form-control" id="guest-email" name="guest_email" placeholder="you@example.com" required>
                    </div>

                    <button type="submit" class="btn btn-cafe-primary w-100 py-3 fw-bold" id="btnSubmitRes">
                        Confirm Reservation
                    </button>
                </div>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="card visit-card shadow-sm border-0 h-100" style="border-radius:16px;">
                <div class="card-body p-4">
                    <h5 class="card-title fw-bold mb-3">Visit Us</h5>
                    <p class="visit-item mb-2"><i class="bi bi-geo-alt me-2 text-success"></i>12 Bookmark Lane, San Pedro, Laguna</p>
                    <p class="visit-item mb-2"><i class="bi bi-telephone me-2 text-success"></i>(049) 555-0182</p>
                    <p class="visit-item mb-2"><i class="bi bi-envelope me-2 text-success"></i>hello@shioricafe.com</p>
                    <p class="visit-item mb-4"><i class="bi bi-clock me-2 text-success"></i>Open 24 Hours · Monday to Sunday</p>
                    <a class="btn btn-outline-secondary w-100 btn-sm" href="https://maps.google.com" target="_blank" rel="noopener">
                        <i class="bi bi-map me-1"></i>Open Location in Maps
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="frontend/assets/js/reservation.js"></script>

<?php require_once "frontend/includes/footer.php"; ?>
