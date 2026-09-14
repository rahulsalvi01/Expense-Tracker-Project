<?php
session_start();

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

// NEW: Generate a unique CSRF token for the session
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// NEW: Verify the CSRF token on form submissions
function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        // In a real app, you might redirect with an error. For now, we abort.
        die("Security Error: CSRF token validation failed. Please go back and try again.");
    }
}
?>