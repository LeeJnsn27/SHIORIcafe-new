document.addEventListener("DOMContentLoaded", function () {
    const resForm = document.getElementById("reservationForm");
    if (!resForm) return; // Guard: prevents execution on non-reservation pages

    const seatButtons = document.querySelectorAll(".seat-btn");
    const seatDisplay = document.getElementById("selectedSeatDisplay");
    const seatInput   = document.getElementById("selectedSeatInput");
    const alertBox    = document.getElementById("reservationAlert");
    const submitBtn   = document.getElementById("btnSubmitRes");

    const dateInput   = document.getElementById("reservation_date") || document.querySelector('input[name="reservation_date"]') || document.querySelector('input[name="date"]');
    const timeInput   = document.getElementById("reservation_time") || document.querySelector('input[name="reservation_time"]') || document.querySelector('input[name="time"]');
    const durationInput = document.getElementById("duration_hours") || document.querySelector('select[name="duration_hours"]');

    // 1. Real-time Availability Checker
    function checkSeatAvailability() {
        if (!dateInput || !timeInput) return;

        const date = dateInput.value;
        const startTime = timeInput.value;
        const duration = durationInput ? durationInput.value : 2;

        if (!date || !startTime) return;

        // Calculate end time dynamically (+duration hours)
        const startDateTime = new Date(`${date}T${startTime}`);
        if (isNaN(startDateTime.getTime())) return;

        const endDateTime = new Date(startDateTime.getTime() + (duration * 60 * 60 * 1000));
        const endTime = endDateTime.toTimeString().substring(0, 5);

        // Fetch active occupied seats from PostgreSQL backend
        fetch(`backend/api/seat-availability.php?date=${encodeURIComponent(date)}&startTime=${encodeURIComponent(startTime)}&endTime=${encodeURIComponent(endTime)}`)
            .then(res => res.json())
            .then(data => {
                const occupiedSeats = data.occupiedSeats || data.occupied_tables || [];

                seatButtons.forEach(btn => {
                    // Extract table identifier (supports data-table-id, data-seat-id, value, or seat name)
                    const tableId = parseInt(btn.getAttribute("data-table-id") || btn.getAttribute("data-seat-id") || btn.value);
                    const seatName = btn.getAttribute("data-seat") || btn.innerText.trim();

                    const isOccupied = occupiedSeats.includes(tableId) || occupiedSeats.includes(seatName);

                    if (isOccupied) {
                        btn.className = "btn btn-danger seat-btn px-3 py-2 disabled";
                        btn.disabled = true;
                        btn.title = "Occupied for the selected timeslot";

                        // If user previously selected a seat that is now occupied, clear choice
                        if (seatInput.value.includes(seatName)) {
                            seatInput.value = "";
                            if (seatDisplay) {
                                seatDisplay.textContent = "Selected seat is occupied. Please pick another seat.";
                            }
                        }
                    } else {
                        // Restore availability styling if not currently selected
                        if (!btn.classList.contains("btn-warning")) {
                            btn.className = "btn btn-outline-success seat-btn px-3 py-2";
                            btn.disabled = false;
                            btn.title = "Available";
                        }
                    }
                });
            })
            .catch(err => console.error("Availability check error:", err));
    }

    // 2. Attach Availability Listeners
    if (dateInput) dateInput.addEventListener("change", checkSeatAvailability);
    if (timeInput) timeInput.addEventListener("change", checkSeatAvailability);
    if (durationInput) durationInput.addEventListener("change", checkSeatAvailability);

    // Initial check on load
    checkSeatAvailability();

    // 3. Seat Selection Logic
    seatButtons.forEach(button => {
        button.addEventListener("click", function () {
            if (this.disabled || this.classList.contains("disabled")) return;

            // Reset other non-occupied buttons
            seatButtons.forEach(btn => {
                if (!btn.disabled && !btn.classList.contains("disabled")) {
                    btn.className = "btn btn-outline-success seat-btn px-3 py-2";
                }
            });

            // Highlight selected button in yellow/amber
            this.className = "btn btn-warning text-dark fw-bold seat-btn px-3 py-2";

            const seatName = this.getAttribute("data-seat") || this.innerText.trim();
            const nookType = this.getAttribute("data-type") || "Nook";
            const tableId  = this.getAttribute("data-table-id") || this.value || "1";

            seatInput.value = `${nookType} (${seatName})`;
            
            // Set hidden table_id field if present in form
            const hiddenTableInput = document.getElementById("table_id") || document.querySelector('input[name="table_id"]');
            if (hiddenTableInput) {
                hiddenTableInput.value = tableId;
            }

            if (seatDisplay) {
                seatDisplay.innerHTML = `<span class="text-success fw-bold"><i class="bi bi-check2-circle"></i> Selected: ${nookType} — ${seatName}</span>`;
            }
        });
    });

    // 4. Form Submission Handler
    resForm.addEventListener("submit", function (e) {
        e.preventDefault();

        if (!seatInput.value) {
            alertBox.innerHTML = `
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> Please choose an available seat from the grid above first.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>`;
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Reserving...';
        alertBox.innerHTML = "";

        const formData = new FormData(resForm);

        fetch("backend/api/create-reservation.php", {
            method: "POST",
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Confirm Reservation';

            if (data.status === "success" || data.success) {
                alertBox.innerHTML = `
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> ${data.message || "Reservation successfully confirmed!"}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>`;
                
                resForm.reset();
                seatInput.value = "";
                if (seatDisplay) {
                    seatDisplay.textContent = "No seat selected yet — tap one above.";
                }

                // Re-check grid availability after successful booking
                checkSeatAvailability();
            } else {
                alertBox.innerHTML = `
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-x-circle-fill me-2"></i> ${data.message || "Could not reserve seat."}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>`;
            }
        })
        .catch(err => {
            console.error(err);
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Confirm Reservation';
            alertBox.innerHTML = `
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-octagon-fill me-2"></i> Server communication error. Please try again.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>`;
        });
    });
});