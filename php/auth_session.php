<?php
/**
 * ============================================================================
 * StudentHub - Digital Campus Portal
 * PRACTICAL 10: Secure Authentication, PHP Sessions & Access Control Module
 * ============================================================================
 * 
 * Features:
 * 1. Secure Session Management (strict mode, httpOnly, sameSite, secure cookies).
 * 2. Session Fixation Defense via session_regenerate_id(true) on login.
 * 3. Inactivity Session Timeout (default 15 minutes of idle time).
 * 4. Access Control Guard: Restrict dashboard and protected pages.
 * 5. Role-Based Access Control (RBAC): Student vs. Administrator.
 * 6. Cryptographically Secure Remember-Me Token (SHA-256 hashed, HTTP-only cookie).
 * 7. Clean Session Termination and Token Invalidation on Logout.
 * ============================================================================
 */

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Session configuration constants
defined('SESSION_TIMEOUT_SECONDS') || define('SESSION_TIMEOUT_SECONDS', 900); // 15 minutes
defined('REMEMBER_ME_COOKIE_NAME') || define('REMEMBER_ME_COOKIE_NAME', 'studenthub_remember');
defined('REMEMBER_ME_DAYS')        || define('REMEMBER_ME_DAYS', 30);

// Include database module
require_once __DIR__ . '/db_connect.php';

/**
 * Starts a hardened PHP session with secure cookie parameters.
 */
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    // Configure hardened session cookie params before session_start()
    session_set_cookie_params([
        'lifetime' => 0,              // Session cookie expires when browser closes
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,       // Transmit only over HTTPS if available
        'httponly' => true,           // Inaccessible to JavaScript (XSS mitigation)
        'samesite' => 'Lax'           // Protect against CSRF attacks
    ]);

    // Force strict session ID mode
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_start();
}

/**
 * Regenerates the session ID to defend against Session Fixation attacks.
 */
function regenerateUserSession(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        startSecureSession();
    }
    // Delete the old session file and issue a new random session ID
    session_regenerate_id(true);
}

/**
 * Checks whether the current user is authenticated.
 */
function isLoggedIn(): bool {
    startSecureSession();

    // Check if session has timed out
    if (isset($_SESSION['user_id'])) {
        if (checkSessionTimeout()) {
            return false;
        }
        return true;
    }

    // Try auto-login via remember-me cookie
    if (checkRememberMe()) {
        return true;
    }

    return false;
}

/**
 * Returns current authenticated user array, or null if not logged in.
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }

    return [
        'user_id'         => $_SESSION['user_id'] ?? null,
        'registration_no' => $_SESSION['registration_no'] ?? '',
        'username'        => $_SESSION['username'] ?? '',
        'full_name'       => $_SESSION['full_name'] ?? '',
        'email'           => $_SESSION['email'] ?? '',
        'course'          => $_SESSION['course'] ?? '',
        'academic_year'   => $_SESSION['academic_year'] ?? '',
        'gender'          => $_SESSION['gender'] ?? '',
        'role'            => $_SESSION['role'] ?? 'student',
        'login_time'      => $_SESSION['login_time'] ?? time(),
        'last_activity'   => $_SESSION['last_activity'] ?? time()
    ];
}

/**
 * Enforces session inactivity timeout.
 * Returns true if session timed out and was destroyed.
 */
function checkSessionTimeout(): bool {
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
        return false;
    }

    $elapsed = time() - $_SESSION['last_activity'];

    if ($elapsed > SESSION_TIMEOUT_SECONDS) {
        // Timed out: clear session
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId) {
            clearRememberMe((int)$userId);
        }

        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        return true;
    }

    // Update last activity timestamp on user interaction
    $_SESSION['last_activity'] = time();
    return false;
}

/**
 * Restricts access to a page. Redirects unauthenticated users to login.
 * Optionally enforces role-based authorization (e.g. 'student' or 'admin').
 *
 * @param string|null $requiredRole 'student', 'admin', or null for any logged-in user
 * @param string $loginPath Relative path to login page
 */
function requireLogin(?string $requiredRole = null, string $loginPath = '../pages/login.php'): void {
    startSecureSession();

    // 1. Check authentication status (handles timeout & auto remember-me)
    if (!isLoggedIn()) {
        $redirectUrl = $_SERVER['REQUEST_URI'] ?? '';
        $target = $loginPath . '?error=auth_required';
        if (!empty($redirectUrl)) {
            $target .= '&redirect=' . urlencode($redirectUrl);
        }
        header("Location: " . $target);
        exit;
    }

    // 2. Role-Based Access Control (RBAC)
    if ($requiredRole !== null) {
        $currentRole = $_SESSION['role'] ?? 'student';

        if ($currentRole !== $requiredRole) {
            // Role mismatch redirection
            if ($requiredRole === 'admin') {
                // Non-admin tried to access admin page
                header("Location: ../pages/dashboard.php?error=admin_only");
                exit;
            } elseif ($requiredRole === 'student' && $currentRole === 'admin') {
                // Admin visiting student dashboard: allowed or redirected with info
                // We permit admin or redirect to admin panel
            }
        }
    }
}

/**
 * Creates a cryptographically secure Remember-Me token in database and sets HTTP-only cookie.
 */
function createRememberMeToken(int $userId): bool {
    try {
        $mysqli = getMySQLiConnection();
        if (!($mysqli instanceof mysqli)) {
            return false;
        }

        ensureAuthColumnsExist($mysqli);

        // Generate high-entropy 64-char random token
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiryTime = date('Y-m-d H:i:s', time() + (REMEMBER_ME_DAYS * 86400));

        // Store hashed token in database
        $stmt = $mysqli->prepare("UPDATE students SET remember_token = ?, token_expiry = ? WHERE student_id = ?");
        if ($stmt) {
            $stmt->bind_param("ssi", $tokenHash, $expiryTime, $userId);
            $stmt->execute();
            $stmt->close();

            // Set cookie formatted as userId:rawToken
            $cookieValue = base64_encode($userId . ':' . $rawToken);
            $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');

            setcookie(
                REMEMBER_ME_COOKIE_NAME,
                $cookieValue,
                [
                    'expires'  => time() + (REMEMBER_ME_DAYS * 86400),
                    'path'     => '/',
                    'domain'   => '',
                    'secure'   => $isHttps,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );

            return true;
        }
    } catch (Exception $e) {
        error_log("StudentHub Remember-Me Error: " . $e->getMessage());
    }

    return false;
}

/**
 * Validates Remember-Me cookie and automatically restores user session if valid.
 */
function checkRememberMe(): bool {
    if (empty($_COOKIE[REMEMBER_ME_COOKIE_NAME])) {
        return false;
    }

    $decoded = base64_decode($_COOKIE[REMEMBER_ME_COOKIE_NAME], true);
    if (!$decoded || strpos($decoded, ':') === false) {
        return false;
    }

    list($userIdStr, $rawToken) = explode(':', $decoded, 2);
    $userId = (int)$userIdStr;

    if ($userId <= 0 || empty($rawToken)) {
        return false;
    }

    try {
        $mysqli = getMySQLiConnection();
        if (!($mysqli instanceof mysqli)) {
            return false;
        }

        ensureAuthColumnsExist($mysqli);

        $stmt = $mysqli->prepare("
            SELECT student_id, registration_no, username, full_name, email, course, academic_year, gender, role, remember_token, token_expiry 
            FROM students 
            WHERE student_id = ? 
            LIMIT 1
        ");

        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            $user = $res ? $res->fetch_assoc() : null;
            $stmt->close();

            if ($user && !empty($user['remember_token']) && !empty($user['token_expiry'])) {
                // Verify token expiry
                if (strtotime($user['token_expiry']) > time()) {
                    // Timing attack safe hash comparison
                    $providedHash = hash('sha256', $rawToken);
                    if (hash_equals($user['remember_token'], $providedHash)) {
                        // Success: restore session and regenerate ID
                        regenerateUserSession();
                        $_SESSION['user_id']         = (int)$user['student_id'];
                        $_SESSION['registration_no'] = $user['registration_no'];
                        $_SESSION['username']        = $user['username'];
                        $_SESSION['full_name']       = $user['full_name'];
                        $_SESSION['email']           = $user['email'];
                        $_SESSION['course']          = $user['course'];
                        $_SESSION['academic_year']   = (int)$user['academic_year'];
                        $_SESSION['gender']          = $user['gender'];
                        $_SESSION['role']            = $user['role'] ?? 'student';
                        $_SESSION['login_time']      = time();
                        $_SESSION['last_activity']   = time();
                        return true;
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("StudentHub Check Remember-Me Error: " . $e->getMessage());
    }

    return false;
}

/**
 * Clears Remember-Me token in database and deletes browser cookie.
 */
function clearRememberMe(?int $userId = null): void {
    if (!empty($_COOKIE[REMEMBER_ME_COOKIE_NAME])) {
        $params = session_get_cookie_params();
        setcookie(
            REMEMBER_ME_COOKIE_NAME,
            '',
            time() - 3600,
            $params["path"],
            $params["domain"],
            $params["secure"],
            true
        );
    }

    if ($userId !== null && $userId > 0) {
        try {
            $mysqli = getMySQLiConnection();
            if ($mysqli instanceof mysqli) {
                ensureAuthColumnsExist($mysqli);
                $stmt = $mysqli->prepare("UPDATE students SET remember_token = NULL, token_expiry = NULL WHERE student_id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        } catch (Exception $e) {
            error_log("StudentHub Clear Remember-Me DB Notice: " . $e->getMessage());
        }
    }
}

/**
 * Fully logs out the user: destroys session, clears cookie, updates DB.
 */
function logoutUser(): void {
    startSecureSession();

    $userId = $_SESSION['user_id'] ?? null;
    clearRememberMe($userId ? (int)$userId : null);

    // Unset all session variables
    $_SESSION = [];

    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // Destroy session storage
    session_destroy();
}

/**
 * Ensures table `students` has the `role`, `remember_token`, and `token_expiry` columns.
 * Non-destructive, idempotent check.
 */
function ensureAuthColumnsExist(mysqli $mysqli): void {
    static $checked = false;
    if ($checked) return;

    try {
        // Check role column
        $rCheck = $mysqli->query("SHOW COLUMNS FROM `students` LIKE 'role'");
        if ($rCheck && $rCheck->num_rows === 0) {
            $mysqli->query("ALTER TABLE `students` ADD COLUMN `role` ENUM('student', 'admin') NOT NULL DEFAULT 'student' AFTER `terms_accepted`");
        }
        if ($rCheck) $rCheck->free();

        // Check remember_token column
        $tCheck = $mysqli->query("SHOW COLUMNS FROM `students` LIKE 'remember_token'");
        if ($tCheck && $tCheck->num_rows === 0) {
            $mysqli->query("ALTER TABLE `students` ADD COLUMN `remember_token` VARCHAR(64) DEFAULT NULL AFTER `role`");
        }
        if ($tCheck) $tCheck->free();

        // Check token_expiry column
        $eCheck = $mysqli->query("SHOW COLUMNS FROM `students` LIKE 'token_expiry'");
        if ($eCheck && $eCheck->num_rows === 0) {
            $mysqli->query("ALTER TABLE `students` ADD COLUMN `token_expiry` DATETIME DEFAULT NULL AFTER `remember_token`");
        }
        if ($eCheck) $eCheck->free();

        $checked = true;
    } catch (Exception $e) {
        error_log("StudentHub Ensure Columns Notice: " . $e->getMessage());
    }
}
