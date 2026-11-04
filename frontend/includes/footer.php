<?php
// frontend/includes/footer.php
?>
<footer class="cafe-footer mt-auto py-5 text-white">
    <div class="container">
        <div class="row g-4 justify-content-between align-items-center">
            <div class="col-lg-5">
                <div class="footer-brand mb-2">
                    <span class="brand-kanji text-white">栞</span>
                    <span class="brand-text fw-bold fs-5 ms-1 text-white">SHIORI CAFÉ</span>
                </div>
                <p class="footer-desc text-light small mb-0 opacity-75">
                    A peaceful corner for thoughtful reading, quiet focus, specialty matcha, and artisan brewed coffee.
                </p>
            </div>

            <div class="col-lg-7">
                <div class="footer-inquiry-box p-3 rounded" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255, 255, 255, 0.1);">
                    <h6 class="fw-bold mb-2 small text-uppercase text-white" style="letter-spacing: 0.5px;">Quick Inquiry</h6>
                    <form action="contact.php" method="POST" class="row g-2">
                        <div class="col-md-4">
                            <input type="text" name="name" class="form-control form-control-sm bg-light text-black border-secondary" placeholder="Name" required>
                        </div>
                        <div class="col-md-4">
                            <input type="email" name="email" class="form-control form-control-sm bg-light text-black border-secondary" placeholder="Email" required>
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="subject" class="form-control form-control-sm bg-light text-black border-secondary" placeholder="Subject" required>
                        </div>
                        <div class="col-12 mt-2">
                            <div class="input-group">
                                <input type="text" name="message" class="form-control form-control-sm bg-light text-black border-secondary" placeholder="Type your message or inquiry..." required>
                                <button type="submit" class="btn btn-cafe-primary btn-sm">Send</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <hr class="my-4 border-secondary opacity-50">

        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center small text-light opacity-75">
            <div>&copy; <?php echo date('Y'); ?> SHIORI Café. All rights reserved.</div>
            <div class="mt-2 mt-sm-0">
                <a href="index.php" class="text-light text-decoration-none me-3">Home</a>
                <a href="services.php" class="text-light text-decoration-none me-3">Menu</a>
                <a href="reservation.php" class="text-light text-decoration-none me-3">Reservation</a>
                <a href="contact.php" class="text-light text-decoration-none">Contact</a>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>