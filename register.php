<?php
session_start();

// Connect to PostgreSQL database using backend config
require_once __DIR__ . '/backend/config/db.php';

/** @var PDO $pdo */

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST["username"] ?? ""); // Maps the form "Username" field to full_name in DB
    $email     = trim($_POST["email"] ?? "");
    $password  = $_POST["password"] ?? "";

    if (empty($full_name) || empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    } else {
        try {
            if (!isset($pdo)) {
                throw new Exception("Database connection is not initialized.");
            }

            // Check if email already exists using customer_id instead of non-existent id column
            $checkStmt = $pdo->prepare("
                SELECT customer_id 
                FROM shioricafe_schema.customer 
                WHERE email = :email 
                LIMIT 1
            ");
            $checkStmt->execute([':email' => $email]);

            if ($checkStmt->fetch()) {
                $error = "An account with that email address already exists.";
            } else {
                // Hash password and insert record matching PostgreSQL customer schema
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $insertStmt = $pdo->prepare("
                    INSERT INTO shioricafe_schema.customer (full_name, email, password_hash) 
                    VALUES (:full_name, :email, :password_hash)
                ");
                $insertStmt->execute([
                    ':full_name'     => $full_name,
                    ':email'         => $email,
                    ':password_hash' => $hashedPassword
                ]);

                $success = "Account created successfully! You can now <a href='login.php' class='alert-link'>Log In</a>.";
            }
        } catch (Exception $e) {
            $error = "Registration failed: " . $e->getMessage();
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

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST">
            <div class="mb-3">
                <label for="username" class="form-label">Full Name</label>
                <input 
                    type="text" 
                    class="form-control" 
                    id="username" 
                    name="username" 
                    value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
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
                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                    placeholder="user@example.com" 
                    required
                >
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input 
                    type="password" 
                    class="form-control" 
                    id="password" 
                    name="password" 
                    placeholder="Enter password" 
                    required
                >
            </div>

            <div class="d-grid mb-3">
                <button type="submit" class="btn btn-cafe py-2">Sign Up</button>
            </div>

            <div class="text-center">
                <small class="text-muted">Already have an account? <a href="login.php" class="text-decoration-none fw-semibold">Log In</a></small>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>