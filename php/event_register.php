<?php
/**
 * ============================================================================
 * StudentHub Event Registration Action Endpoint
 * Handles student registration and cancellation for campus events.
 * Supports both JSON API requests (Fetch) and standard form POSTs.
 * ============================================================================
 */

require_once __DIR__ . '/auth_session.php';

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
    || (isset($_GET['format']) && $_GET['format'] === 'json');

function sendResponse(bool $success, string $message, array $extra = [], bool $isAjax = false, string $redirect = '') {
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

    if (!empty($redirect)) {
        header("Location: $redirect");
    } else {
        header("Location: ../pages/my_registrations.php");
    }
    exit;
}

// 1. Check Authentication
$user = getCurrentUser();
if (!$user) {
    sendResponse(false, 'You must be logged in to register or manage event tickets.', [], $isAjax, '../pages/login.php');
}

$studentId = (int)$user['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? 'register';

// Parse JSON body if applicable
$inputRaw = file_get_contents('php://input');
if (!empty($inputRaw)) {
    $decoded = json_decode($inputRaw, true);
    if (is_array($decoded)) {
        if (isset($decoded['action'])) $action = $decoded['action'];
        if (isset($decoded['event_id'])) $_POST['event_id'] = $decoded['event_id'];
        if (isset($decoded['registration_id'])) $_POST['registration_id'] = $decoded['registration_id'];
    }
}

// 2. Action Routing
switch ($action) {
    case 'register':
        $eventId = (int)($_POST['event_id'] ?? 0);
        if ($eventId <= 0) {
            sendResponse(false, 'Invalid event selected. Please select a valid campus event.', [], $isAjax, '../pages/events.html');
        }

        $res = registerStudentForEvent($studentId, $eventId);
        $redirectUrl = $res['success'] ? '../pages/my_registrations.php' : '../pages/events.html';
        sendResponse($res['success'], $res['message'], $res, $isAjax, $redirectUrl);
        break;

    case 'cancel':
        $regId = (int)($_POST['registration_id'] ?? 0);
        if ($regId <= 0) {
            sendResponse(false, 'Invalid registration ID provided.', [], $isAjax, '../pages/my_registrations.php');
        }

        $res = cancelEventRegistration($studentId, $regId);
        sendResponse($res['success'], $res['message'], $res, $isAjax, '../pages/my_registrations.php');
        break;

    case 'my_registrations':
        $regs = getStudentRegistrations($studentId);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'count' => count($regs),
            'registrations' => $regs
        ]);
        exit;

    case 'check':
        $eventId = (int)($_GET['event_id'] ?? 0);
        $regs = getStudentRegistrations($studentId);
        $isRegistered = false;
        $ticketCode = null;
        foreach ($regs as $r) {
            if ((int)$r['event_id'] === $eventId && $r['attendance_status'] !== 'Cancelled') {
                $isRegistered = true;
                $ticketCode = $r['registration_code'];
                break;
            }
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'is_registered' => $isRegistered,
            'registration_code' => $ticketCode
        ]);
        exit;

    default:
        sendResponse(false, 'Unrecognized action requested.', [], $isAjax);
}
