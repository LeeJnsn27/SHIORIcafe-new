<?php
// backend/auth/auth-guard.php - Restricts access to admin-only pages
// Ensure a session exists before checking any session data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to root login if no admin role is present in the session
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}