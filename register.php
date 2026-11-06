<?php
// Start user session
session_start();

// Connect to PostgreSQL database using backend config
require_once "backend/config/db.php";

/** @var PDO $conn */

$errors = [];$success = "";
$full_name = "";
$email = "";

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Get and trim form inputs (supporting both full_name and username field naming conventions)
    $full_name        = trim($_POST["full_name"] ?? $_POST["username"] ?? "");
    $email            = trim($_POST["email"] ?? "");
    $password         =$_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? $password;

    // Validate required fields and formats
    if (empty($full_name)) {$errors[] = "Full name is required.";
    }

    if (empty($email)) {$errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {$errors[] = "Please enter a valid email address.";
    }

    if (empty($password)) {$errors[] = "Password is required.";
    } elseif (strlen($password) < 6) {$errors[] = "Password must be at least 6 characters long.";
    }

    if ($password !== $confirm_password) {$errors[] = "Passwords do not match.";
    }

    // Check if email already exists in database (unique index is on LOWER(email))
    if (empty($errors)) {
        try {
            $stmt =$conn->prepare(
                "SELECT 1 FROM shioricafe_schema.customer WHERE LOWER(email) = LOWER(:email)"
            );
            $stmt->execute([":email" => $email]);

            if ($stmt->fetchColumn() !== false) {$errors[] = "This email is already registered.";
            }
        } catch (PDOException $e) {
            error_log("Customer email check failed: " . $e->getMessage());$errors[] = "Something went wrong. Please try again later.";
        }
    }

    // Hash password and save new customer
    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt =$conn->prepare(
                "INSERT INTO shioricafe_schema.customer (full_name, email, password_hash)
                 VALUES (:full_name, :email, :password_hash)"
            );
            $stmt->execute([
                ":full_name"     => $full_name,
                ":email"         => $email,
                ":password_hash" => $hashedPassword,
            ]);

            $success = "Registration successful! You can now <a href='login.php' class='alert-link'>Log In</a>.";
            $full_name =$email = "";
        } catch (PDOException $e) {
            // 23505 = unique violation: another request registered this email first
            if ($e->getCode() === "23505") {
                $errors[] = "This email is already registered.";
            } else {
                error_log("Customer registration failed: " . $e->getMessage());$errors[] = "Something went wrong. Please try again later.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Register | SHIORI Café</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="frontend/assets/css/main.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f6f0;
            color: #2b2b2b;
        }
        .register-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            padding: 2.5rem;
            max-width: 440px;
            width: 100%;
        }
        .btn-cafe {
            background-color: #2d4a3e;
            color: #ffffff;
            font-weight: 600;
            border: none;
        }
        .btn-cafe:hover {
            background-color: #1e332a;
            color: #ffffff;
        }
    </style>
</head>
<body class="d-flex align-items-center min-vh-100 py-5">

<div class="container d-flex justify-content-center">
    <div class="register-card">
        <h2 class="fw-bold mb-1 text-center">Create Account</h2>
        <p class="text-muted mb-4 text-center">Join SHIORI Café to place orders and book seats.</p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">
                    <?php foreach ($errors as$error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST">
            <div class="mb-3">
                <label for="full_name" class="form-label">Full Name</label>
                <input
                    type="text"
                    class="form-control"
                    id="full_name"
                    name="full_name"
                    value="<?php echo htmlspecialchars($full_name ?? ''); ?>"
                    placeholder="e.g. Shiraine Bituin"
                    required
                >
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input
                    type="email"
                    class="form-control"
                    id="email"
                    name="email"
                    value="<?php echo htmlspecialchars($email ?? ''); ?>"
                    placeholder="user@example.com"
                    required
                >
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input
                    type="password"
                    class="form-control"
                    id="password"
                    name="password"
                    placeholder="At least 6 characters"
                    required
                >
            </div>

            <div class="mb-4">
                <label for="confirm_password" class="form-label">