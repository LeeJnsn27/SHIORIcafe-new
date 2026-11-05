<?php

// Start the session so we can access and destroy it
session_start();

// Remove all session variables (clears out user_id, user_name, user_role, etc.)
$_SESSION = [];

// Expire the session cookie in the browser
if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), "", time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
}

session_destroy();
header("Location: login.php");
exit();
?>