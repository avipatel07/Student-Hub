<?php
/**
 * ============================================================================
 * StudentHub Profile Management Action Endpoint
 * Handles profile data updates and password changes.
 * Supports AJAX JSON API and traditional POST requests.
 * ============================================================================
 */

require_once __DIR__ . '/auth_session.php';

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
    || (isset($_GET['format']) && $_GET['format'] === 'json');

function sendProfileResponse(bool $success, string $message, array $extra = [], bool $isAjax = false) {
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge([
            'success' => $success,
            'message' => $message,
            'timestamp' => date('c')
        ], $extra));
        exit;
    }

    if ($success) {
        setFlashMessage('success', $message);
    } else {
        setFlashMessage('error', $message);
    }

    header('Location: ../pages/profile.php');
    exit;
}

$user = getCurrentUser();
if (!$user) {
    sendProfileResponse(false, 'You must be signed in to access profile settings.', [], $isAjax);
}

$studentId = (int)$user['user_id'];
$action = $_POST['action'] ?? 'update_profile';

// Parse JSON body if JSON was posted
$inputRaw = file_get_contents('php://input');
if (!empty($inputRaw)) {
    $decoded = json_decode($inputRaw, true);
    if (is_array($decoded)) {
        if (isset($decoded['action'])) $action = $decoded['action'];
        $_POST = array_merge($_POST, $decoded);
    }
}

if ($action === 'update_profile') {
    $data = [
        'full_name'     => $_POST['full_name'] ?? '',
        'email'         => $_POST['email'] ?? '',
        'mobile'        => $_POST['mobile'] ?? '',
        'course'        => $_POST['course'] ?? '',
        'academic_year' => $_POST['academic_year'] ?? 1,
        'gender'        => $_POST['gender'] ?? 'Male'
    ];

    $res = updateStudentProfile($studentId, $data);
    sendProfileResponse($res['success'], $res['message'], $res, $isAjax);

} elseif ($action === 'change_password') {
    $currPass = $_POST['current_password'] ?? '';
    $newPass  = $_POST['new_password'] ?? '';
    $confPass = $_POST['confirm_password'] ?? '';

    if (empty($currPass)) {
        sendProfileResponse(false, 'Please enter your current password.', [], $isAjax);
    }
    if (strlen($newPass) < 6) {
        sendProfileResponse(false, 'New password must be at least 6 characters long.', [], $isAjax);
    }
    if ($newPass !== $confPass) {
        sendProfileResponse(false, 'New password and confirmation password do not match.', [], $isAjax);
    }

    $res = changeStudentPassword($studentId, $currPass, $newPass);
    sendProfileResponse($res['success'], $res['message'], $res, $isAjax);

} else {
    sendProfileResponse(false, 'Invalid profile action requested.', [], $isAjax);
}
