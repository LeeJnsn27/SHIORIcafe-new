<?php
session_start();

require_once __DIR__ . '/backend/config/db.php';

/** @var PDO $pdo */

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $login_input = trim($_POST["login_input"] ?? "");
    $password    = $_POST["password"] ?? "";

    if (empty($login_input) || empty($password)) {
        $error = "Please enter both your email address and password.";
    } else {
        try {
            if (!isset($pdo)) {
                throw new Exception("Database connection is not initialized.");
            }

            // Query customer by email
            $stmt = $pdo->prepare("
                SELECT customer_id, full_name, email, password_hash 
                FROM shioricafe_schema.customer 
                WHERE email = :email 
                LIMIT 1
            ");
            $stmt->execute([':email' => $login_input]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id']     = $user['customer_id'];
                $_SESSION['customer_id'] = $user['customer_id'];
                $_SESSION['user_name']   = !empty($user['full_name']) ? $user['full_name'] : explode('@', $user['email'])[0];
                $_SESSION['email']       = $user['email'];

                header("Location: index.php");
                exit;
            } else {
                $error = "Invalid email or password.";
            }
        } catch (Exception $e) {
            $error = "Login error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | SHIORI Café</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f6f0;
            color: #2b2b2b;
        }
        .login-card {
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
    <div class="login-card">
        <h2 class="fw-bold mb-1 text-center">Welcome Back</h2>
        <p class="text-muted mb-4 text-center">Sign in to your SHIORI Café account</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="mb-3">
                <label for="login_input" class="form-label fw-semibold">Email Address</label>
                <input 
                    type="email" 
                    class="form-control" 
                    id="login_input" 
                    name="login_input" 
                    value="<?php echo htmlspecialchars($_POST['login_input'] ?? ''); ?>" 
                    placeholder="shirainebituin@gmail.com" 
                    required
                >
            </div>

            <div class="mb-4">
                <label for="password" class="form-label fw-semibold">Password</label>
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
                <button type="submit" class="btn btn-cafe py-2">Log In</button>
            </div>

            <div class="text-center">
                <small class="text-muted">Don't have an account? <a href="register.php" class="text-decoration-none fw-semibold">Create one here</a></small>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>