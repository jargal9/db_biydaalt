<?php
require_once __DIR__ . '/security.php';

function isLoggedIn() {
    return isset($_SESSION['user_ID']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../index.php');
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if (!in_array($role, ROLES, true) || ($_SESSION['role'] ?? '') !== $role) {
        header('Location: ../index.php?error=access_denied');
        exit;
    }
}

function currentUser() {
    return $_SESSION ?? [];
}
?>
