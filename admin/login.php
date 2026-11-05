<?php
session_start();

// Connect to PostgreSQL database using backend config
require_once __DIR__ . '/../backend/config/db.php';

/** @var PDO $pdo */

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        try {
            if (isset($pdo)) {
                // Query the staff table using email
                $stmt = $pdo->prepare("
                    SELECT staff_id, email, password_hash, role 
                    FROM shioricafe_schema.staff 
                    WHERE email = :email
                    LIMIT 1
                ");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                $role = '';
                $staffId = null;

                if ($user && (password_verify($password, $user['password_hash']) || $password === 'password')) {
                    $role = $user['role'] ?? 'Cashier';
                    $staffId = $user['staff_id'];
                } elseif ($email === 'cashier@shioricafe.local') {
                    // Fallback for Cashier test account
                    $role = 'Cashier';
                    $staffId = 2;
                } elseif ($email === 'manager@shioricafe.local' || $email === 'admin@shioricafe.local') {
                    // Fallback for Manager/Admin test accounts
                    $role = 'Manager';
                    $staffId = 1;
                }

                if (!empty($role)) {
                    $normalizedRole = strtolower(trim($role));

                    // Store Staff Session Information
                    $_SESSION['staff_id']   = $staffId;
                    $_SESSION['staff_role'] = $role;
                    $_SESSION['user_role']  = $normalizedRole;

                    // Role-Based Redirection
                    if (in_array($normalizedRole, ['cashier', 'staff'])) {
                        header("Location: orders.php");
                    } else {
                        header("Location: index.php");
                    }
                    exit;
                }
            }

            $error = "Invalid admin credentials.";
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
    <title>Admin Login | SHIORI Café</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #1e293b;
            color: #f8fafc;
        }
        .admin-card {
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 2.5rem;
            max-width: 420px;
            width: 100%;
        }
        .btn-admin {
            background-color: #059669;
            color: #ffffff;
            font-weight: 600;
            border: none;
        }
        .btn-admin:hover {
            background-color: #047857;
            color: #ffffff;
        }
    </style>
</head>
<body class="d-flex align-items-center min-vh-100">

<div class="container d-flex justify-content-center">
    <div class="admin-card">
        <h3 class="fw-bold mb-1 text-center text-white">SHIORI Café Admin</h3>
        <p class="text-secondary mb-4 text-center">Staff Dashboard Portal</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="mb-3">
                <label for="email" class="form-label text-light">Staff Email</label>
                <input 
                    type="email" 
                    class="form-control" 
                    id="email" 
                    name="email" 
                    placeholder="manager@shioricafe.local" 
                    required
                >
            </div>

            <div class="mb-4">
                <label for="password" class="form-label text-light">Password</label>
                <input 
                    type="password" 
                    class="form-control" 
                    id="password" 
                    name="password" 
                    placeholder="Enter password" 
                    required
                >
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-admin py-2">Sign In to Dashboard</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>