document.addEventListener("DOMContentLoaded", function () {
    const resForm = document.getElementById("reservationForm");
    if (!resForm) return; // Guard: prevents crashing other pages

    const seatButtons = document.querySelectorAll(".seat-btn");
    const seatDisplay = document.getElementById("selectedSeatDisplay");
    const seatInput = document.getElementById("selectedSeatInput");
    const alertBox = document.getElementById("reservationAlert");
    const submitBtn = document.getElementById("btnSubmitRes");

    // Seat click logic
    seatButtons.forEach(button => {
        button.addEventListener("click", function () {
            if (this.disabled || this.classList.contains("disabled")) return;

            // Reset other buttons
            seatButtons.forEach(btn => {
                if (!btn.disabled && !btn.classList.contains("disabled")) {
                    btn.className = "btn btn-outline-success seat-btn px-3 py-2";
                }
            });

            // Highlight selected button
            this.className = "btn btn-warning text-dark fw-bold seat-btn px-3 py-2";

            const seatName = this.getAttribute("data-seat");
            const nookType = this.getAttribute("data-type");

            seatInput.value = `${nookType} (${seatName})`;
            seatDisplay.innerHTML = `<span class="text-success fw-bold"><i class="bi bi-check2-circle"></i> Selected: ${nookType} — ${seatName}</span>`;
        });
    });

    // Form submission
    resForm.addEventListener("submit", function (e) {
        e.preventDefault();

        if (!seatInput.value) {
            alertBox.innerHTML = `
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> Please choose a seat from the grid above first.
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
                seatDisplay.textContent = "No seat selected yet — tap one above.";
                seatButtons.forEach(btn => {
                    if (!btn.disabled) btn.className = "btn btn-outline-success seat-btn px-3 py-2";
                });
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