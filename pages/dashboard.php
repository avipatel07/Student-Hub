<?php
/**
 * ============================================================================
 * StudentHub - Digital Campus Portal
 * PRACTICAL 10: Restricted Student Dashboard
 * ============================================================================
 * 
 * Access Control Enforced:
 * 1. requireLogin('student') restricts access to authenticated students only.
 * 2. Unauthenticated requests are intercepted and redirected to login.php.
 * 3. Enforces session inactivity timeout (15 mins idle check).
 * 4. Displays real-time session diagnostics, role badge, and countdown timer.
 * ============================================================================
 */

require_once __DIR__ . '/../php/auth_session.php';

// Enforce access restriction for students
requireLogin('student', 'login.php');

$currentUser = getCurrentUser();
$sessionId = session_id();
$sessionAgeSeconds = time() - ($currentUser['login_time'] ?? time());
$idleSeconds = time() - ($currentUser['last_activity'] ?? time());
$remainingTimeoutSeconds = max(0, SESSION_TIMEOUT_SECONDS - $idleSeconds);
$hasRememberCookie = !empty($_COOKIE[REMEMBER_ME_COOKIE_NAME]);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Student Dashboard (Protected)</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .dashboard-hero {
            background: linear-gradient(135deg, #0d47a1 0%, #1976d2 100%);
            color: white;
            padding: 28px 32px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: 0 8px 20px rgba(13, 71, 161, 0.18);
        }
        .user-avatar-badge {
            width: 58px;
            height: 58px;
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
        }
        .session-info-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        }
        .stat-grid-modern {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px;
            margin-bottom: 24px;
        }
        .stat-card-modern {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card-modern:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.06);
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
    </style>
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
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="events.html">Events</a>
            <a href="profile.html">Profile</a>
            <a href="contact.html">Contact</a>
            <a href="../php/db_dashboard.php">Practical 8 DB</a>
            <a href="../php/view_registrations.php">Records</a>
            <a href="faq.html">FAQ</a>
            <a href="../php/logout.php" style="color: #fca5a5; font-weight: 700;">Sign Out 🚪</a>
        </div>

        <button class="theme-toggle" aria-label="Toggle dark mode">
            🌙
        </button>
    </div>
</nav>

<!-- ================= MAIN ================= -->
<main>
    <div class="container" style="margin-top: 25px; margin-bottom: 40px;">

        <!-- Dashboard Hero Banner -->
        <section class="dashboard-hero">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="user-avatar-badge">👨‍🎓</div>
                <div>
                    <div style="font-size: 13px; opacity: 0.85; text-transform: uppercase; letter-spacing: 0.5px;">
                        Student Portal &bull; Practical 10 Protected Session
                    </div>
                    <h2 style="margin: 4px 0 2px 0; font-size: 24px;">
                        Welcome back, <?php echo htmlspecialchars($currentUser['full_name']); ?>!
                    </h2>
                    <div style="font-size: 14px; opacity: 0.95;">
                        <span class="badge badge-success" style="background: rgba(255,255,255,0.25); border: 1px solid rgba(255,255,255,0.4); color: white;">
                            @<?php echo htmlspecialchars($currentUser['username']); ?>
                        </span>
                        <span style="margin-left: 8px;">Reg No: <strong><?php echo htmlspecialchars($currentUser['registration_no']); ?></strong></span>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="../php/logout.php" class="btn" style="background: #ef4444; color: white; border: none; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);">
                    🚪 Secure Logout
                </a>
            </div>
        </section>

        <!-- Access Control & Session Security Diagnostics Card -->
        <section class="session-info-card" style="border-left: 4px solid #1677e8;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 14px;">
                <h3 style="margin: 0; font-size: 16px; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                    <span>🛡️</span> Active Session Security State (Practical 10 Verification)
                </h3>
                <span class="badge badge-success">Session Authenticated</span>
            </div>

            <div class="summary-grid" style="margin-bottom: 12px;">
                <div class="summary-item">
                    <div class="summary-label">Session ID (Regenerated)</div>
                    <div class="summary-value" style="font-family: monospace; font-size: 12px;">
                        <?php echo substr($sessionId, 0, 16) . '...'; ?>
                    </div>
                </div>

                <div class="summary-item">
                    <div class="summary-label">Assigned Role</div>
                    <div class="summary-value">
                        <span class="badge badge-primary">Student (RBAC)</span>
                    </div>
                </div>

                <div class="summary-item">
                    <div class="summary-label">Inactivity Timer</div>
                    <div class="summary-value" id="timeoutCountdown" style="color: #0284c7; font-weight: 700;">
                        <?php echo gmdate("i:s", $remainingTimeoutSeconds); ?>
                    </div>
                </div>

                <div class="summary-item">
                    <div class="summary-label">Remember-Me Token</div>
                    <div class="summary-value">
                        <?php if ($hasRememberCookie): ?>
                            <span class="badge badge-success">Active (30-day SHA-256)</span>
                        <?php else: ?>
                            <span class="badge" style="background: #e2e8f0; color: #475569;">Session Only</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div style="font-size: 12px; color: #64748b; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                <span>⏱️ Inactivity guard automatically terminates your session after 15 minutes of idle time.</span>
                <button type="button" class="btn btn-secondary btn-small" onclick="keepSessionAlive()">
                    🔄 Reset Inactivity Timer
                </button>
            </div>
        </section>

        <!-- Academic Overview Modern Metrics -->
        <div class="stat-grid-modern">
            <div class="stat-card-modern">
                <div class="stat-icon" style="background: #e0f2fe; color: #0284c7;">📚</div>
                <div>
                    <div style="font-size: 24px; font-weight: 700; color: #0f172a;">
                        <?php echo htmlspecialchars($currentUser['course']); ?>
                    </div>
                    <div style="font-size: 13px; color: #64748b;">
                        Academic Year <?php echo htmlspecialchars($currentUser['academic_year']); ?>
                    </div>
                </div>
            </div>

            <div class="stat-card-modern">
                <div class="stat-icon" style="background: #f0fdf4; color: #16a34a;">🎯</div>
                <div>
                    <div style="font-size: 24px; font-weight: 700; color: #0f172a;">94.2%</div>
                    <div style="font-size: 13px; color: #64748b;">Semester Attendance</div>
                </div>
            </div>

            <div class="stat-card-modern">
                <div class="stat-icon" style="background: #fef3c7; color: #d97706;">🎪</div>
                <div>
                    <div style="font-size: 24px; font-weight: 700; color: #0f172a;">03</div>
                    <div style="font-size: 13px; color: #64748b;">Registered Campus Events</div>
                </div>
            </div>

            <div class="stat-card-modern">
                <div class="stat-icon" style="background: #f3e8ff; color: #9333ea;">📝</div>
                <div>
                    <div style="font-size: 24px; font-weight: 700; color: #0f172a;">08</div>
                    <div style="font-size: 13px; color: #64748b;">Assignments Completed</div>
                </div>
            </div>
        </div>

        <!-- Registered Student Profile Card -->
        <section class="summary-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                <h3 style="margin: 0; color: #243b53; font-size: 18px;">
                    📋 Authenticated Student Profile Details
                </h3>
                <a href="profile.html" class="btn btn-secondary btn-small">Edit Profile</a>
            </div>

            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-label">Full Name</div>
                    <div class="summary-value"><?php echo htmlspecialchars($currentUser['full_name']); ?></div>
                </div>

                <div class="summary-item">
                    <div class="summary-label">Username</div>
                    <div class="summary-value" style="color: #1677e8;">
                        @<?php echo htmlspecialchars($currentUser['username']); ?>
                    </div>
                </div>

                <div class="summary-item">
                    <div class="summary-label">Institutional Email</div>
                    <div class="summary-value"><?php echo htmlspecialchars($currentUser['email']); ?></div>
                </div>

                <div class="summary-item">
                    <div class="summary-label">Enrolled Course</div>
                    <div class="summary-value"><?php echo htmlspecialchars($currentUser['course']); ?></div>
                </div>

                <div class="summary-item">
                    <div class="summary-label">Academic Year</div>
                    <div class="summary-value">Year <?php echo htmlspecialchars($currentUser['academic_year']); ?></div>
                </div>

                <div class="summary-item">
                    <div class="summary-label">Gender</div>
                    <div class="summary-value"><?php echo htmlspecialchars($currentUser['gender']); ?></div>
                </div>
            </div>

            <!-- Dashboard Quick Links -->
            <div style="margin-top: 22px; display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="events.html" class="btn">
                    🎟️ Explore & Register for Events
                </a>
                <a href="../php/view_registrations.php" class="btn btn-secondary">
                    📋 View Stored Records
                </a>
                <a href="../php/db_dashboard.php" class="btn btn-secondary">
                    🗄️ Database Operations
                </a>
                <a href="../php/logout.php" class="btn btn-secondary" style="color: #ef4444; border-color: #fca5a5;">
                    Sign Out
                </a>
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
        <a href="dashboard.php">Dashboard</a>
        <a href="../php/logout.php">Logout</a>
        <a href="../php/view_registrations.php">Records</a>
        <a href="contact.html">Contact</a>
    </div>
</footer>

<script src="../js/script.js"></script>
<script>
// Live Inactivity Countdown Timer
let remainingSeconds = <?php echo $remainingTimeoutSeconds; ?>;
const timerEl = document.getElementById('timeoutCountdown');

function updateTimer() {
    if (remainingSeconds <= 0) {
        timerEl.textContent = "Session Expired!";
        timerEl.style.color = "#ef4444";
        alert("Your session has timed out due to 15 minutes of inactivity. Redirecting to login...");
        window.location.href = "login.php?error=session_timeout";
        return;
    }

    const mins = Math.floor(remainingSeconds / 60);
    const secs = remainingSeconds % 60;
    timerEl.textContent = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    remainingSeconds--;
}

setInterval(updateTimer, 1000);

// Ping keep-alive
function keepSessionAlive() {
    fetch('../php/auth_session.php?ping=1')
        .then(() => {
            remainingSeconds = 900;
            timerEl.style.color = "#0284c7";
            alert("Session refreshed! 15-minute inactivity timer has been reset.");
        })
        .catch(() => {
            window.location.reload();
        });
}
</script>

</body>
</html>
