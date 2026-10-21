<?php
/**
 * ============================================================================
 * StudentHub - Digital Campus Portal
 * PRACTICAL 10: Secure Logout Script
 * ============================================================================
 * 
 * Objectives:
 * 1. Terminate active PHP session safely.
 * 2. Unset all session variables.
 * 3. Delete session cookie from client browser.
 * 4. Invalidate database Remember-Me token and remove cookie.
 * 5. Destroy server-side session data via session_destroy().
 * 6. Redirect user to login page with clear logout confirmation message.
 * ============================================================================
 */

require_once __DIR__ . '/auth_session.php';

// Check if request is AJAX
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

// Execute comprehensive logout
logoutUser();

if ($isAjax) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success'     => true,
        'message'     => 'Logged out successfully.',
        'redirectUrl' => '../pages/login.php?message=logged_out'
    ]);
    exit;
}

header("Location: ../pages/login.php?message=logged_out");
exit;
