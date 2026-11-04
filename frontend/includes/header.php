<?php
// frontend/includes/header.php
// Ensure session is started if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SHIORI Café</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="frontend/assets/css/main.css">
    <link rel="stylesheet" href="frontend/assets/css/components.css">
</head>
<body class="d-flex flex-column min-vh-100">

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm py-2">
        <div class="container">
            <a class="navbar-brand fw-bold font-serif me-3" href="index.php">
                <span class="brand-kanji">栞</span> SHIORI CAFÉ
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto align-items-lg-center">
                    <li class="nav-item">
                        <a class="nav-link px-2" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-2" href="services.php">Menu</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link px-2" href="contact.php">Contact</a>
                    </li>
                </ul>

                <!-- Right Side Actions: Primary CTA + Dynamic Auth -->
                <div class="d-flex align-items-center gap-2 my-2 my-lg-0">
                    <a href="reservation.php" class="btn btn-cafe-primary px-3 fw-bold shadow-sm btn-sm">
                        Reserve a Nook
                    </a>

                    <?php if (isset($_SESSION["user_id"])): ?>
                        <!-- Shown when user IS logged in -->
                        <span class="navbar-text small fw-semibold text-secondary ms-2">
                            Hi, <?php echo htmlspecialchars($_SESSION["user_name"] ?? "User"); ?>
                        </span>
                        <a href="logout.php" class="btn btn-outline-danger px-2.5 btn-sm ms-1">
                            Log Out
                        </a>
                    <?php else: ?>
                        <!-- Shown when user is NOT logged in -->
                        <a href="login.php" class="btn btn-outline-secondary px-2.5 btn-sm ms-2">
                            Login
                        </a>
                        <a href="register.php" class="btn btn-outline-dark px-2.5 btn-sm">
                            Sign Up
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
