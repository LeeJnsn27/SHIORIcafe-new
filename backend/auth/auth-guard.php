<?php
// backend/auth/auth-guard.php - Role-Based Access Control Guard
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Verify Authentication
$isLoggedIn = isset($_SESSION['user_role']) || isset($_SESSION['staff_id']) || isset($_SESSION['staff_role']) || isset($_SESSION['role']);

if (!$isLoggedIn) {
    header("Location: login.php");
    exit();
}

// 2. Identify Role (Normalized to lowercase)
$rawRole = $_SESSION['user_role'] ?? $_SESSION['staff_role'] ?? $_SESSION['role'] ?? 'Cashier';
$userRole = strtolower(trim($rawRole));

$isAdmin = in_array($userRole, ['admin', 'manager', 'administrator']);
$isCashier = in_array($userRole, ['cashier', 'staff']);

// 3. Define Access Permissions
$currentPage = basename($_SERVER['PHP_SELF']);
$cashierAllowedPages = ['pos.php', 'orders.php', 'reservations.php', 'logout.php'];

// If a Cashier attempts to access Admin-only pages, redirect them to POS terminal or Orders
if ($isCashier && !in_array($currentPage, $cashierAllowedPages)) {
    header("Location: pos.php");
    exit();
}
?>