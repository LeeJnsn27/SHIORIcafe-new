<?php
/**
 * One-time setup: create the initial Manager account.
 *
 * Usage (from the project root, CLI only):
 *   php backend/database/create-manager.php
 *
 * The password is typed at the prompt, hashed with password_hash(),
 * and never written to a file. The script refuses to run if a Manager
 * already exists. Later staff accounts are created by a Manager.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/db.php';

/** @var PDO $conn */

function prompt(string $label, bool $hidden = false): string
{
    echo $label;
    $canHide = $hidden && PHP_OS_FAMILY !== 'Windows';
    if ($canHide) {
        shell_exec('stty -echo');
    }
    $value = trim((string) fgets(STDIN));
    if ($canHide) {
        shell_exec('stty echo');
        echo PHP_EOL;
    }
    return $value;
}

$exists = $conn->query(
    "SELECT 1 FROM shioricafe_schema.staff WHERE role = 'Manager' LIMIT 1"
)->fetchColumn();

if ($exists !== false) {
    fwrite(STDERR, "A Manager account already exists. Nothing was changed.\n");
    exit(1);
}

$fullName = prompt("Manager full name: ");
$email    = prompt("Manager email: ");
$password = prompt("Password (min 12 characters): ", true);
$confirm  = prompt("Confirm password: ", true);

if ($fullName === '') {
    fwrite(STDERR, "Full name is required.\n");
    exit(1);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Enter a valid email address.\n");
    exit(1);
}
if (strlen($password) < 12) {
    fwrite(STDERR, "Password must be at least 12 characters.\n");
    exit(1);
}
if ($password !== $confirm) {
    fwrite(STDERR, "Passwords do not match.\n");
    exit(1);
}

$stmt = $conn->prepare(
    "INSERT INTO shioricafe_schema.staff (full_name, role, email, password_hash)
     VALUES (:full_name, 'Manager', :email, :password_hash)"
);
$stmt->execute([
    ':full_name'     => $fullName,
    ':email'         => $email,
    ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
]);

echo "Manager account created for {$email}.\n";