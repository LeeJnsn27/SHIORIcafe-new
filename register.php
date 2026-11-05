<?php
// Start user session
session_start();

// Connect to database
require_once "backend/config/db.php";

/** @var PDO $conn */

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

     // Check if email already exists in database (unique index is on LOWER(email))
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare(
                "SELECT 1 FROM shioricafe_schema.customer WHERE LOWER(email) = LOWER(:email)"
            );
            $stmt->execute([":email" => $email]);

            if ($stmt->fetchColumn() !== false) {
                $errors[] = "This email is already registered.";
            }
        } catch (PDOException $e) {
            error_log("Customer email check failed: " . $e->getMessage());
            $errors[] = "Something went wrong. Please try again later.";
        }
    }

    // Hash password and save new customer
    if (empty($errors)) {
        // Securely hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            // Insert new record into customer table (no role column; every row is a Customer)
            $stmt = $conn->prepare(
                "INSERT INTO shioricafe_schema.customer (full_name, email, password_hash)
                 VALUES (:full_name, :email, :password_hash)"
            );
            $stmt->execute([
                ":full_name"     => $full_name,
                ":email"         => $email,
                ":password_hash" => $hashedPassword,
            ]);

            $success = "Registration successful! You can now log in.";
            $full_name = $email = $password = $confirm_password = "";
        } catch (PDOException $e) {
            // 23505 = unique violation: another request registered this email first
            if ($e->getCode() === "23505") {
                $errors[] = "This email is already registered.";
            } else {
                error_log("Customer registration failed: " . $e->getMessage());
                $errors[] = "Something went wrong. Please try again later.";
            }
        }
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