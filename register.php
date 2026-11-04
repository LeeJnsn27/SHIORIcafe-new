<?php
// Start user session
session_start();

// Connect to database
require_once "backend/config/db.php";

/** @var mysqli $conn */

$errors = [];
$success = "";

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Get and trim form inputs
    $full_name        = trim($_POST["full_name"] ?? "");
    $email            = trim($_POST["email"] ?? "");
    $password         = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    // Validate required fields and formats
    if (empty($full_name)) {
        $errors[] = "Full name is required.";
    }

    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // Check if email already exists in database
    if (empty($errors)) {
        $checkEmailSql = "SELECT id FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $checkEmailSql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {
            $errors[] = "This email is already registered.";
        }
        mysqli_stmt_close($stmt);
    }

    // Hash password and save new user
    if (empty($errors)) {
        // Securely hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert new record into users table
        $insertSql = "INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'customer')";
        $stmt = mysqli_prepare($conn, $insertSql);
        mysqli_stmt_bind_param($stmt, "sss", $full_name, $email, $hashedPassword);

        if (mysqli_stmt_execute($stmt)) {
            $success = "Registration successful! You can now log in.";
            $full_name = $email = $password = $confirm_password = "";
        } else {
            $errors[] = "Something went wrong. Please try again later.";
        }

        mysqli_stmt_close($stmt);
    }
}

// Load global header
require_once "frontend/includes/header.php";
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">

            <div class="p-4 register-card">
                <h2 class="text-center mb-4">Create an Account</h2>

                <!-- Success notification -->
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success" role="alert">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <!-- Error notifications -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Registration form -->
                <form action="register.php" method="POST">

                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input
                            type="text"
                            class="form-control"
                            id="full_name"
                            name="full_name"
                            value="<?php echo htmlspecialchars($full_name ?? ''); ?>"
                            placeholder="Enter your full name"
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
                            placeholder="you@example.com"
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
                        >
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input
                            type="password"
                            class="form-control"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="Re-enter your password"
                        >
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-cafe">Register</button>
                    </div>

                </form>

                <p class="text-center mt-3 mb-0">
                    Already have an account? <a href="login.php">Log in here</a>
                </p>

            </div>

        </div>
    </div>
</div>

<?php
// Include unified global footer
require_once "frontend/includes/footer.php";
?>