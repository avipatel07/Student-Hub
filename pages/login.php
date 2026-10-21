<?php
/**
 * ============================================================================
 * StudentHub - Digital Campus Portal
 * PRACTICAL 10: Secure User Login Page (PHP Sessions & Access Control)
 * ============================================================================
 */

require_once __DIR__ . '/../php/auth_session.php';

// If user is already authenticated, redirect to appropriate role dashboard
if (isLoggedIn()) {
    $role = $_SESSION['role'] ?? 'student';
    if ($role === 'admin') {
        header("Location: admin.php");
        exit;
    } else {
        header("Location: dashboard.php");
        exit;
    }
}

// Retrieve status/error notifications from query string
$errorCode = $_GET['error'] ?? '';
$messageCode = $_GET['message'] ?? '';
$redirectUrl = $_GET['redirect'] ?? '';

$alertType = '';
$alertTitle = '';
$alertMsg = '';

if ($errorCode === 'invalid_credentials') {
    $alertType = 'alert-danger';
    $alertTitle = 'Invalid Credentials';
    $alertMsg = 'The username/email or password you entered is incorrect. Passwords are case-sensitive and verified via password_verify().';
} elseif ($errorCode === 'auth_required') {
    $alertType = 'alert-warning';
    $alertTitle = 'Authentication Required';
    $alertMsg = '🔒 Access to that page is restricted. Please sign in with your credentials to continue.';
} elseif ($errorCode === 'session_timeout') {
    $alertType = 'alert-warning';
    $alertTitle = 'Session Expired';
    $alertMsg = '⏳ Your session timed out due to 15 minutes of inactivity. For security reasons, please log in again.';
} elseif ($errorCode === 'admin_only') {
    $alertType = 'alert-danger';
    $alertTitle = 'Access Denied (Role Restriction)';
    $alertMsg = '⛔ Administrator privileges are required to view the requested page. Your student account does not have sufficient permissions.';
} elseif ($errorCode === 'empty_fields') {
    $alertType = 'alert-danger';
    $alertTitle = 'Missing Information';
    $alertMsg = 'Please enter both your username/email and password.';
}

if ($messageCode === 'logged_out') {
    $alertType = 'alert-success';
    $alertTitle = 'Logged Out Successfully';
    $alertMsg = '👋 Your session has been safely destroyed, session cookie cleared, and remember-me token invalidated.';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Secure Sign In (Practical 10)</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body>

<!-- ================= HEADER ================= -->
<header class="site-header">
    <div class="logo-icon">🎓</div>
    <h1>StudentHub</h1>
    <p>Your Digital Campus Portal</p>
</header>

<!-- ================= NAVBAR ================= -->
<nav class="navbar">
    <div class="nav-wrapper">
        <button class="menu-toggle" aria-label="Open navigation menu" aria-expanded="false">
            ☰
        </button>

        <div class="nav-container">
            <a href="index.html">Home</a>
            <a href="about.html">About</a>
            <a href="register.html">Register</a>
            <a href="login.php" class="active">Login</a>
            <a href="dashboard.php">Dashboard</a>
            <a href="events.html">Events</a>
            <a href="profile.html">Profile</a>
            <a href="contact.html">Contact</a>
            <a href="admin.php">Admin</a>
            <a href="../php/db_dashboard.php">Database</a>
            <a href="faq.html">FAQ</a>
            <a href="feedback.html">Feedback</a>
        </div>

        <button class="theme-toggle" aria-label="Toggle dark mode">
            🌙
        </button>
    </div>
</nav>

<!-- ================= MAIN ================= -->
<main>
    <div class="container" style="max-width: 560px; margin: 30px auto;">

        <!-- Page Heading -->
        <div class="page-title" style="text-align: center;">
            <h2>Practical 10: Secure Authentication</h2>
            <p>PHP Sessions, password_verify(), RBAC & Inactivity Timeout</p>
        </div>

        <!-- Notification Alert Box if present -->
        <?php if (!empty($alertMsg)): ?>
            <div class="alert-box <?php echo $alertType; ?>" style="margin-bottom: 20px;">
                <div class="alert-title">
                    <span><?php echo ($alertType === 'alert-success') ? '✅' : '⚠️'; ?></span>
                    <span><?php echo htmlspecialchars($alertTitle); ?></span>
                </div>
                <p style="margin: 0; font-size: 13px;"><?php echo htmlspecialchars($alertMsg); ?></p>
            </div>
        <?php endif; ?>

        <!-- Login Card -->
        <section class="form-card" style="box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08); border-radius: 12px; padding: 30px;">

            <!-- Security Architecture Ribbon -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; margin-bottom: 22px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; font-size: 12px; color: #475569;">
                <span>🔒 <strong>Protected:</strong> password_verify()</span>
                <span>⚡ <strong>Regeneration:</strong> Fixation Proof</span>
                <span>⏱️ <strong>Timeout:</strong> 15 mins</span>
            </div>

            <form id="loginForm" action="../php/login_process.php" method="POST" novalidate>

                <?php if (!empty($redirectUrl)): ?>
                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirectUrl); ?>">
                <?php endif; ?>

                <!-- Identifier (Username or Email) -->
                <div class="form-group">
                    <label for="loginIdentifier">
                        Username or Email Address <span class="required">*</span>
                    </label>
                    <div class="input-with-icon">
                        <span class="input-icon-prefix">👤</span>
                        <input
                            type="text"
                            id="loginIdentifier"
                            name="identifier"
                            placeholder="e.g. rahul_patel or rahul@example.com"
                            autocomplete="username"
                            required>
                    </div>
                    <small class="error-message" id="loginIdentifierError"></small>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label for="loginPassword" style="margin-bottom: 0;">
                            Password <span class="required">*</span>
                        </label>
                        <a href="#" onclick="alert('Password reset link will be sent to your registered email address.'); return false;" style="font-size: 12px; color: #1677e8; font-weight: 500;">
                            Forgot Password?
                        </a>
                    </div>

                    <div class="input-with-icon">
                        <span class="input-icon-prefix">🔒</span>
                        <input
                            type="password"
                            id="loginPassword"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required>
                        <button
                            type="button"
                            id="togglePasswordBtn"
                            onclick="togglePasswordVisibility()"
                            class="password-toggle-btn"
                            aria-label="Toggle password visibility">
                            👁️
                        </button>
                    </div>
                    <small class="error-message" id="loginPasswordError"></small>
                </div>

                <!-- Remember Me & Security Status -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin: 18px 0; font-size: 13px; flex-wrap: wrap; gap: 8px;">
                    <label style="display: flex; align-items: center; gap: 7px; cursor: pointer; color: #334155; font-weight: 500;">
                        <input type="checkbox" id="rememberMe" name="remember_me" style="accent-color: #1677e8; width: 16px; height: 16px;">
                        <span>Remember this device (30 days)</span>
                    </label>
                    <span style="color: #16a34a; font-size: 12px; font-weight: 600;">
                        🛡️ SHA-256 Token
                    </span>
                </div>

                <!-- Actions -->
                <div class="form-actions" style="margin-top: 20px;">
                    <button type="submit" class="btn-submit-premium" style="width: 100%;">
                        <span>Sign In to StudentHub</span>
                        <span>🔑</span>
                    </button>
                </div>

                <!-- Link to Register -->
                <div style="margin-top: 22px; text-align: center; font-size: 14px; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 18px;">
                    Don't have a StudentHub account?
                    <br>
                    <a href="register.html" style="color: #1677e8; font-weight: 700; display: inline-block; margin-top: 6px;">
                        + Register as a New Student →
                    </a>
                </div>

            </form>

        </section>

        <!-- Test Accounts Card for Practical Grading -->
        <section class="summary-card" style="margin-top: 22px; padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h4 style="margin: 0; font-size: 14px; color: #243b53;">
                    💡 Practical 10 Demo Credentials (Role-Based Testing)
                </h4>
                <span class="badge badge-primary">RBAC Demo</span>
            </div>

            <div style="font-size: 13px; color: #475569; display: grid; gap: 10px;">
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong>🎓 Student Role:</strong>
                        <code>rahul_patel</code> &bull; Password: <code>password</code>
                    </div>
                    <button type="button" class="btn btn-secondary btn-small" onclick="fillCredentials('rahul_patel', 'password')">
                        Auto-Fill
                    </button>
                </div>

                <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 8px 12px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong style="color: #991b1b;">🛡️ Administrator Role:</strong>
                        <code>admin</code> &bull; Password: <code>Admin@123</code>
                    </div>
                    <button type="button" class="btn btn-secondary btn-small" onclick="fillCredentials('admin', 'Admin@123')">
                        Auto-Fill
                    </button>
                </div>
            </div>
        </section>

    </div>
</main>

<!-- ================= FOOTER ================= -->
<footer>
    <p>© 2026 StudentHub | Digital Campus Portal</p>
    <div class="footer-links">
        <a href="index.html">Home</a>
        <a href="about.html">About</a>
        <a href="register.html">Register</a>
        <a href="login.php">Login</a>
        <a href="../php/view_registrations.php">Records</a>
        <a href="contact.html">Contact</a>
    </div>
</footer>

<script src="../js/script.js"></script>
<script>
function togglePasswordVisibility() {
    const pwdInput = document.getElementById('loginPassword');
    const btn = document.getElementById('togglePasswordBtn');
    if (pwdInput.type === 'password') {
        pwdInput.type = 'text';
        btn.textContent = '🙈';
    } else {
        pwdInput.type = 'password';
        btn.textContent = '👁️';
    }
}

function fillCredentials(identifier, pwd) {
    document.getElementById('loginIdentifier').value = identifier;
    document.getElementById('loginPassword').value = pwd;
}

// Client-side quick check
document.getElementById('loginForm')?.addEventListener('submit', function (e) {
    const idVal = document.getElementById('loginIdentifier').value.trim();
    const pwdVal = document.getElementById('loginPassword').value;
    let valid = true;

    document.getElementById('loginIdentifierError').textContent = '';
    document.getElementById('loginPasswordError').textContent = '';

    if (!idVal) {
        document.getElementById('loginIdentifierError').textContent = 'Please enter your username or email.';
        valid = false;
    }
    if (!pwdVal) {
        document.getElementById('loginPasswordError').textContent = 'Please enter your password.';
        valid = false;
    }

    if (!valid) {
        e.preventDefault();
    }
});
</script>

</body>
</html>
