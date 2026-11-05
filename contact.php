<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "backend/config/db.php";
/** @var PDO $pdo */

$contactSuccess = "";
$contactError = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name    = trim($_POST["name"] ?? "");
    $email   = trim($_POST["email"] ?? "");
    $subject = trim($_POST["subject"] ?? "");
    $message = trim($_POST["message"] ?? "");

    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $contactError = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $contactError = "Please enter a valid email address.";
    } else {
        try {
            // 1. Ensure shioricafe_schema.inquiry exists in PostgreSQL
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS shioricafe_schema.inquiry (
                    inquiry_id SERIAL PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    subject VARCHAR(255) NOT NULL,
                    message TEXT NOT NULL,
                    status VARCHAR(50) DEFAULT 'Unread',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // 2. Insert inquiry record
            $sql = "INSERT INTO shioricafe_schema.inquiry (name, email, subject, message, status) 
                    VALUES (:name, :email, :subject, :message, 'Unread')";
            $stmt = $pdo->prepare($sql);
            $executed = $stmt->execute([
                ':name'    => $name,
                ':email'   => $email,
                ':subject' => $subject,
                ':message' => $message
            ]);

            if ($executed) {
                $contactSuccess = "Your inquiry has been sent successfully!";
                $name = $email = $subject = $message = "";
            } else {
                $contactError = "Failed to send inquiry. Please try again.";
            }
        } catch (Exception $e) {
            $contactError = "Database Error: " . htmlspecialchars($e->getMessage());
        }
    }
}

require_once "frontend/includes/header.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SHIORI Café | Contact & Reservations</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="frontend/assets/css/main.css">
    <link rel="stylesheet" href="frontend/assets/css/components.css">
</head>
<body>

    <header class="page-header text-center">
        <div class="container">
            <h1 class="page-title">Get in Touch</h1>
            <p class="lead">Reserve a seat or send us a message — we'd love to hear from you.</p>
        </div>
    </header>

    <!-- CONTACT / INQUIRY SECTION -->
    <section class="container mb-5">
        <h2 class="text-center mb-4">Send Us a Message</h2>

        <div class="row justify-content-center g-5">
            <div class="col-lg-7">

                <?php if (!empty($contactSuccess)): ?>
                    <div class="alert alert-success py-2 mb-3"><?php echo htmlspecialchars($contactSuccess); ?></div>
                <?php endif; ?>
                <?php if (!empty($contactError)): ?>
                    <div class="alert alert-danger py-2 mb-3"><?php echo htmlspecialchars($contactError); ?></div>
                <?php endif; ?>

                <form class="contact-form" action="contact.php" method="POST">
                    <div class="card reservation-form-card">
                        <div class="card-body">

                            <div class="mb-3">
                                <label for="name" class="form-label">Your Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject</label>
                                <input type="text" class="form-control" id="subject" name="subject" value="<?php echo htmlspecialchars($subject ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="message" class="form-label">Message</label>
                                <textarea class="form-control" id="message" name="message" rows="5" required><?php echo htmlspecialchars($message ?? ''); ?></textarea>
                            </div>

                            <button type="submit" class="btn btn-cafe-primary w-100">Send Message</button>
                        </div>
                    </div>
                </form>

            </div>

            <!-- VISIT US -->
            <div class="col-lg-4">
                <div class="card visit-card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Visit Us</h5>

                        <p class="visit-item"><i class="bi bi-geo-alt"></i><a href="https://www.google.com/maps/search/?api=1&amp;query=12+Bookmark+Lane+San+Pedro+Laguna" target="_blank" rel="noopener">12 Bookmark Lane, San Pedro, Laguna</a></p>
                        <p class="visit-item"><i class="bi bi-telephone"></i><a href="tel:+63495550182">(049) 555-0182</a></p>
                        <p class="visit-item"><i class="bi bi-envelope"></i><a href="mailto:hello@shioricafe.com">hello@shioricafe.com</a></p>
                        <p class="visit-item"><i class="bi bi-clock"></i> Mon–Fri 7AM–9PM · Sat–Sun 8AM–10PM</p>

                        <a class="visit-map-placeholder mt-3" href="https://www.google.com/maps/search/?api=1&amp;query=12+Bookmark+Lane+San+Pedro+Laguna" target="_blank" rel="noopener">
                            <i class="bi bi-map"></i>
                            <span>Open location in Google Maps</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php require_once "frontend/includes/footer.php"; ?>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="src/scripts/app.js"></script>
    <script src="frontend/assets/js/reservation.js"></script>

</body>
</html>