
document.addEventListener("DOMContentLoaded", function () {
    const resForm = document.getElementById("reservationForm");
    if (!resForm) return;

    const seatButtons = document.querySelectorAll(".seat-btn");
    const seatDisplay = document.getElementById("selectedSeatDisplay");
    const seatInput = document.getElementById("selectedSeatInput");
    const partyInput = document.getElementById("party-size");
    const partyHint = document.getElementById("partySizeHint");
    const alertBox = document.getElementById("reservationAlert");
    const submitBtn = document.getElementById("btnSubmitRes");

    // Actual input IDs from reservation.php
    const dateInput = document.getElementById("reservation-date");
    const startInput = document.getElementById("reservation-start-time");
    const endInput = document.getElementById("reservation-end-time");

    const showAlert = (type, icon, message) => {
        const div = document.createElement("div");
        div.className = `alert alert-${type} alert-dismissible fade show`;
        div.setAttribute("role", "alert");
        div.innerHTML = `<i class="bi bi-${icon} me-2"></i><span></span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        div.querySelector("span").textContent = message;
        alertBox.replaceChildren(div);
    };

    const resetSeatButtons = () => {
        seatButtons.forEach(btn => {
            if (
                btn.dataset.operationalStatus === "Available" &&
                btn.dataset.available !== "false"
            ) {
                btn.className = "btn btn-outline-success seat-btn px-3 py-2";
            }
        });
    };

    const clearSelection = () => {
        seatInput.value = "";
        seatDisplay.textContent = "No table selected yet — tap one above.";
        seatDisplay.className =
            "text-center mt-2 mb-0 fw-semibold small text-muted";
        partyInput.removeAttribute("max");
        partyHint.textContent = "Select a table to see its capacity.";
        resetSeatButtons();
    };

    // Prevent outdated availability responses from changing the UI
    let availabilityRequest = 0;

    const refreshAvailability = async () => {
        if (!dateInput || !startInput || !endInput) return;

        const date = dateInput.value;
        const startTime = startInput.value;
        const endTime = endInput.value;

        const requestId = ++availabilityRequest;

        const validDate = /^\d{4}-\d{2}-\d{2}$/.test(date);
        const validTime = value =>
            /^([01]\d|2[0-3]):[0-5]\d$/.test(value);

        if (
            !validDate ||
            !validTime(startTime) ||
            !validTime(endTime) ||
            endTime <= startTime
        ) {
            seatButtons.forEach(btn => {
                if (btn.dataset.operationalStatus === "Available") {
                    btn.dataset.available = "true";
                    btn.disabled = false;
                    btn.classList.remove("disabled");
                }
            });

            clearSelection();
            return;
        }

        try {
            const params = new URLSearchParams({
                date: date,
                startTime: startTime,
                endTime: endTime
            });

            const response = await fetch(
                `backend/api/seat-availability.php?${params.toString()}`,
                { credentials: "same-origin" }
            );

            const data = await response.json();

            // Ignore responses for previous date/time selections
            if (requestId !== availabilityRequest) return;

            if (!response.ok || !data.success) {
                throw new Error(
                    data.error || "Unable to check table availability."
                );
            }

            const availableIds = new Set(
                (data.availableTables || []).map(table =>
                    String(table.table_id)
                )
            );

            seatButtons.forEach(btn => {
                // Never enable tables under maintenance
                if (btn.dataset.operationalStatus !== "Available") {
                    btn.dataset.available = "false";
                    btn.disabled = true;
                    btn.classList.add("disabled");
                    return;
                }

                const tableId = String(btn.dataset.tableId);
                const available = availableIds.has(tableId);

                btn.dataset.available = available ? "true" : "false";
                btn.disabled = !available;
                btn.classList.toggle("disabled", !available);

                if (!available) {
                    btn.className =
                        "btn btn-secondary seat-btn px-3 py-2 disabled";

                    if (seatInput.value === tableId) {
                        clearSelection();
                    }
                } else if (seatInput.value !== tableId) {
                    btn.className =
                        "btn btn-outline-success seat-btn px-3 py-2";
                }
            });

        } catch (error) {
            if (requestId !== availabilityRequest) return;

            console.error("Availability error:", error);

            // Fail closed: don't allow selection when availability is unknown
            seatButtons.forEach(btn => {
                if (btn.dataset.operationalStatus === "Available") {
                    btn.dataset.available = "false";
                    btn.disabled = true;
                    btn.classList.add("disabled");
                }
            });

            clearSelection();

            showAlert(
                "danger",
                "exclamation-triangle-fill",
                "Unable to load table availability. Please change the date or time and try again."
            );
        }
    };

    // Refresh when reservation date or time changes
    [dateInput, startInput, endInput].forEach(input => {
        if (input) {
            input.addEventListener("change", refreshAvailability);
        }
    });

    // Table selection
    seatButtons.forEach(button => {
        button.addEventListener("click", function () {
            if (
                this.disabled ||
                this.dataset.operationalStatus !== "Available" ||
                this.dataset.available !== "true"
            ) {
                return;
            }

            resetSeatButtons();

            this.className =
                "btn btn-warning text-dark fw-bold seat-btn px-3 py-2";

            const capacity = parseInt(this.dataset.capacity, 10);
            seatInput.value = this.dataset.tableId;

            seatDisplay.textContent =
                `Selected: ${this.dataset.type} — ${this.dataset.tableNumber}`;

            seatDisplay.className =
                "text-center mt-2 mb-0 fw-semibold small text-success";

            partyInput.max = capacity;

            if (parseInt(partyInput.value, 10) > capacity) {
                partyInput.value = capacity;
            }

            partyHint.textContent =
                `This table seats up to ${capacity} guest${capacity > 1 ? "s" : ""}.`;
        });
    });

    // Form submission: server-side overlap validation remains authoritative
    resForm.addEventListener("submit", function (e) {
        e.preventDefault();

        if (!seatInput.value) {
            showAlert(
                "warning",
                "exclamation-triangle-fill",
                "Please choose an available table first."
            );
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2"></span>Reserving...';

        alertBox.innerHTML = "";

        fetch("backend/api/create-reservation.php", {
            method: "POST",
            body: new FormData(resForm),
            credentials: "same-origin"
        })
        .then(async response => {
            const data = await response.json();
            return { response, data };
        })
        .then(({ response, data }) => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = "Confirm Reservation";

            if (
                response.ok &&
                (data.status === "success" || data.success)
            ) {
                showAlert(
                    "success",
                    "check-circle-fill",
                    data.message || "Reservation successfully confirmed!"
                );

                resForm.reset();
                clearSelection();
                refreshAvailability();

            } else {
                showAlert(
                    "danger",
                    "x-circle-fill",
                    data.message || data.error || "Could not reserve table."
                );

                // Refresh availability after a possible booking conflict
                refreshAvailability();
            }
        })
        .catch(error => {
            console.error("Reservation submission error:", error);

            submitBtn.disabled = false;
            submitBtn.innerHTML = "Confirm Reservation";

            showAlert(
                "danger",
                "exclamation-octagon-fill",
                "Server communication error. Please try again."
            );
        });
    });

    // Initialize table availability
    refreshAvailability();
});
