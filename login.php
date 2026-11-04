<?php
// Start user session
session_start();
// Redirect already logged-in users according to their role
if (isset($_SESSION["user_role"])) {
    if ($_SESSION["user_role"] === "admin") {
        header("Location: admin/index.php");
    } else {
        header("Location: index.php");
    }
    exit();
}

// Connect to database
require_once "backend/config/db.php";

/** @var mysqli $conn */

$error = "";
$email = ""; // Initialize variable to avoid notices

// Process login submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        // Query customer by email
        $sql = "SELECT id, full_name, email, password, role FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        // Verify password
        if ($user && password_verify($password, $user["password"])) {
            $_SESSION["user_id"]   = $user["id"];
            $_SESSION["user_name"] = $user["full_name"];
            $_SESSION["user_role"] = $user["role"];

            // Staff goes to admin dashboard, customers go to home
            if ($user["role"] === "admin") {
                header("Location: admin/index.php");
            } else {
                header("Location: index.php");
            }
            exit();
        } else {
            $error = "Invalid email or password.";
        }
    }
}

// Include unified global header
require_once "frontend/includes/header.php";
?>

<style>
    body {
        background-color: #F7F5F0;
        color: #2C2825;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }
    .login-wrapper {
        min-height: calc(100vh - 200px);
        display: flex;
        align-items: center;
    }
    .login-card {
        background-color: #FFFFFF;
        border: 1px solid rgba(44, 40, 37, 0.1);
        border-radius: 12px;
        box-shadow: 0 4px 14px rgba(44, 40, 37, 0.06);
    }
    .btn-cafe {
        background-color: #4A6B56;
        color: #FFFFFF;
        font-weight: 600;
        border: none;
        padding: 0.6rem 1.2rem;
        border-radius: 8px;
        transition: background-color 0.2s ease;
    }
    .btn-cafe:hover {
        background-color: #3b5645;
        color: #FFFFFF;
    }
</style>

<div class="login-wrapper py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-5">

                <div class="p-4 p-md-5 login-card">
                    <h2 class="text-center mb-1 fw-bold" style="color: #2C2825;">Welcome Back</h2>
                    <p class="text-center text-muted small mb-4">Sign in to your SHIORI Café account</p>

                    <!-- Error notification -->
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger py-2 small" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Customer login form -->
                    <form action="login.php" method="POST">

                        <div class="mb-3">
                            <label for="email" class="form-label small fw-semibold">Email Address</label>
                            <input
                                type="email"
                                class="form-control"
                                id="email"
                                name="email"
                                value="<?php echo htmlspecialchars($email ?? ''); ?>"
                                placeholder="you@example.com"
                                required
                            >
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label small fw-semibold">Password</label>
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
                            <button type="submit" class="btn btn-cafe">Log In</button>
                        </div>

                    </form>

                    <p class="text-center small mt-4 mb-0 text-muted">
                        Don't have an account? <a href="register.php" class="fw-semibold text-decoration-none" style="color: #C1694F;">Create one here</a>
                    </p>

                </div>

            </div>
        </div>
    </div>
</div>

<?php
// Include unified global footer
require_once "frontend/includes/footer.php";
?>