<?php
// Start session
session_start();

// Skip login if already an active Manager
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Manager') {
    header("Location: index.php");
    exit();
}

// Connect to database
require_once "../backend/config/db.php";

/** @var PDO $conn */

$error = "";

// Process form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email    = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        try {
            // Query staff record by email (unique index is on LOWER(email))
            $stmt = $conn->prepare(
                "SELECT staff_id, full_name, email, role, password_hash
                 FROM shioricafe_schema.staff
                 WHERE LOWER(email) = LOWER(:email)"
            );
            $stmt->execute([":email" => $email]);
            $staff = $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Staff login query failed: " . $e->getMessage());
            $staff = false;
            $error = "Something went wrong. Please try again later.";
        }

        if ($error === "") {
            // Verify password hash
            if ($staff && password_verify($password, $staff["password_hash"])) {

                // Restrict this admin area strictly to Managers
                if ($staff["role"] !== "Manager") {
                    $error = "Access denied. Manager credentials required.";
                } else {
                    // Prevent session fixation
                    session_regenerate_id(true);

                    $_SESSION["user_id"]   = $staff["staff_id"];
                    $_SESSION["user_name"] = $staff["full_name"];
                    $_SESSION["user_role"] = "Manager";

                    header("Location: index.php");
                    exit();
                }
            } else {
                $error = "Invalid email or password.";
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
    <title>Admin Login | Cafe Dashboard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../frontend/assets/css/admin.css">
</head>
<body class="admin-login-body">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-auto">

            <div class="login-card">
                <span class="badge badge-admin mb-3">ADMIN ACCESS</span>

                <h1 class="mb-1">Dashboard Login</h1>
                <p class="subtitle mb-4">Sign in with your administrator credentials.</p>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-terracotta" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST">

                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input
                            type="email"
                            class="form-control"
                            id="email"
                            name="email"
                            value="<?php echo htmlspecialchars($email ?? ''); ?>"
                            placeholder="admin@cafe.com"
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
                            placeholder="Enter your password"
                            required
                        >
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-matcha py-2">Log In</button>
                    </div>

                </form>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>