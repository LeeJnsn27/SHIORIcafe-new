document.addEventListener("DOMContentLoaded", function () {
    const addButtons = document.querySelectorAll(".add-to-table-btn");
    const orderList = document.getElementById("order-list");
    const emptyMsg = document.getElementById("empty-order-message");
    const orderTotal = document.getElementById("order-total");
    const tableNumberInput = document.getElementById("table-number");
    const receiptEmailInput = document.getElementById("receipt-email");
    const orderWarning = document.getElementById("order-warning");
    const cancelOrderBtn = document.getElementById("cancel-order-btn");
    const confirmOrderBtn = document.getElementById("confirm-order-btn");
    const confirmationBanner = document.getElementById("order-confirmation-banner");
    const confirmationDetails = document.getElementById("order-confirmation-details");

    let cart = JSON.parse(localStorage.getItem("cafe_cart")) || [];
    renderCart();

    // 1. Handle clicking "+ Add to Table" on menu items
    addButtons.forEach(button => {
        button.addEventListener("click", function () {
            const name = this.getAttribute("data-name");
            const price = parseFloat(this.getAttribute("data-price"));

            let existing = cart.find(item => item.name === name);
            if (existing) {
                existing.quantity += 1;
            } else {
                cart.push({ name: name, price: price, quantity: 1 });
            }

            localStorage.setItem("cafe_cart", JSON.stringify(cart));
            renderCart();

            // Visual feedback animation on button
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="bi bi-check"></i> Added';
            this.classList.add("btn-success");
            this.classList.remove("btn-cafe-secondary");
            setTimeout(() => {
                this.innerHTML = originalText;
                this.classList.remove("btn-success");
                this.classList.add("btn-cafe-secondary");
            }, 800);
        });
    });

    // 2. Render cart items dynamically in the sidebar UI
    function renderCart() {
        // Remove previously rendered dynamic items
        const itemRows = orderList.querySelectorAll(".cart-item-row");
        itemRows.forEach(row => row.remove());

        let total = 0;

        if (cart.length === 0) {
            if (emptyMsg) emptyMsg.classList.remove("d-none");
        } else {
            if (emptyMsg) emptyMsg.classList.add("d-none");

            cart.forEach((item, index) => {
                let itemTotal = item.price * item.quantity;
                total += itemTotal;

                let li = document.createElement("li");
                li.className = "list-group-item d-flex justify-content-between align-items-center cart-item-row py-2 px-0";
                li.innerHTML = `
                    <div>
                        <span class="fw-semibold small">${item.name}</span><br>
                        <small class="text-muted">₱${item.price} × ${item.quantity}</small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="small fw-bold">₱${itemTotal.toFixed(2)}</span>
                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 remove-item-btn" data-index="${index}" title="Remove">&times;</button>
                    </div>
                `;
                orderList.appendChild(li);
            });

            // Bind remove buttons for individual items
            document.querySelectorAll(".remove-item-btn").forEach(btn => {
                btn.addEventListener("click", function () {
                    let idx = parseInt(this.getAttribute("data-index"));
                    cart.splice(idx, 1);
                    localStorage.setItem("cafe_cart", JSON.stringify(cart));
                    renderCart();
                });
            });
        }

        if (orderTotal) {
            orderTotal.textContent = total.toFixed(2);
        }
    }

    // 3. Handle Cancel Order button
    if (cancelOrderBtn) {
        cancelOrderBtn.addEventListener("click", function () {
            cart = [];
            localStorage.removeItem("cafe_cart");
            renderCart();
            if (tableNumberInput) tableNumberInput.value = "";
            if (receiptEmailInput) receiptEmailInput.value = "";
            document.querySelectorAll("input[name='paymentMethod']").forEach(r => r.checked = false);
            if (orderWarning) orderWarning.classList.add("d-none");
            if (confirmationBanner) confirmationBanner.classList.add("d-none");
        });
    }

    // 4. Handle Confirm Order button (Submits to backend database script)
    if (confirmOrderBtn) {
        confirmOrderBtn.addEventListener("click", function () {
            if (cart.length === 0) {
                showWarning("Please add at least one item to your table order.");
                return;
            }

            const tableNumber = tableNumberInput ? tableNumberInput.value.trim() : "";
            if (!tableNumber) {
                showWarning("Please enter your table number.");
                return;
            }

            const selectedPayment = document.querySelector("input[name='paymentMethod']:checked");
            const paymentMethod = selectedPayment ? selectedPayment.value : "Cash on Table";
            const receiptEmail = receiptEmailInput ? receiptEmailInput.value.trim() : "";

            if (orderWarning) orderWarning.classList.add("d-none");

            const payload = {
                tableNumber: tableNumber,
                paymentMethod: paymentMethod,
                receiptEmail: receiptEmail,
                items: cart
            };

            // Posts data to your backend checkout/order script
            fetch("backend/api/create-order.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (confirmationBanner && confirmationDetails) {
                        confirmationDetails.innerHTML = `Order <strong>#${data.orderId}</strong> for Table <strong>${tableNumber}</strong> has been confirmed!<br>Total: <strong>₱${parseFloat(data.total).toFixed(2)}</strong>`;
                        confirmationBanner.classList.remove("d-none");
                    }
                    cart = [];
                    localStorage.removeItem("cafe_cart");
                    renderCart();
                } else {
                    showWarning(data.error || "Failed to process order.");
                }
            })
            .catch(err => {
                console.error("Checkout network error:", err);
                showWarning("An error occurred while connecting to the server.");
            });
        });
    }

    function showWarning(msg) {
        if (orderWarning) {
            orderWarning.textContent = msg;
            orderWarning.classList.remove("d-none");
            orderWarning.className = "order-warning alert alert-danger py-2 small mt-2";
        }
    }
});
