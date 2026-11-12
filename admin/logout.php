<?php
// admin/logout.php - Secure Logout and Session/Cart Cleansing
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Unset all session variables related to cart and authentication
$_SESSION = [];

// 2. Destroy the session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destroy the server-side session
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Logging Out...</title>
    <script>
        // Clear any client-side cart or session storage persistence
        localStorage.removeItem('cart_items');
        localStorage.removeItem('shiori_cart');
        localStorage.removeItem('cart');
        sessionStorage.clear();

        // Redirect back to the login page securely
        window.location.replace('login.php');
    </script>
</head>
<body>
    <p style="text-align: center; margin-top: 20vh; font-family: sans-serif; color: #666;">Logging out and clearing session data...</p>
</body>
</html>