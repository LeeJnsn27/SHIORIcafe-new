<?php
/**
 * Shiori Café - Authentication & Role-Based Access Control Guard
 * File: backend/auth/auth-guard.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function get_current_role() {
    return strtolower($_SESSION['user_role'] ?? 'guest');
}

function get_current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function require_login() {
    if (!is_logged_in()) {
        header("Location: /SHIORIcafe/admin/login.php?error=unauthenticated");
        exit();
    }
}

function has_role($allowed_roles) {
    if (!is_logged_in()) {
        return false;
    }

    if (is_string($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }

    $current_role  = get_current_role();
    $allowed_roles = array_map('strtolower', $allowed_roles);

    return in_array($current_role, $allowed_roles, true);
}

function require_role($allowed_roles) {
    require_login();

    if (!has_role($allowed_roles)) {
        header("Location: /SHIORIcafe/admin/index.php?error=unauthorized");
        exit();
    }
}

function is_admin() {
    return has_role('admin');
}

function is_cashier() {
    return has_role('cashier');
}

function is_customer() {
    return has_role('customer');
}

function is_cashier_or_admin() {
    return has_role(['admin', 'cashier']);
}