<?php
/**
 * ============================================================================
 * StudentHub Modern Portal - Student Dashboard
 * Fully integrated with real MySQL database queries, active session diagnostics,
 * live registrations, and upcoming campus events.
 * ============================================================================
 */

require_once __DIR__ . '/../php/auth_session.php';

// Enforce student access control (Practical 10 requirement)
$currentUser = requireLogin('student', 'login.php');
$studentId = (int)$currentUser['user_id'];

// Diagnostics
$sessionId = session_id();
$idleSeconds = time() - ($currentUser['last_activity'] ?? time());
$remainingTimeoutSeconds = max(0, SESSION_TIMEOUT_SECONDS - $idleSeconds);
$hasRememberCookie = !empty($_COOKIE[REMEMBER_ME_COOKIE_NAME]);

// Fetch REAL counts and data from MySQL
$mysqli = getMySQLiConnection();

$totalEventsCount = 0;
$upcomingEvents = [];
$activeRegistrations = getStudentRegistrations($studentId);

if ($mysqli instanceof mysqli && !@$mysqli->connect_errno) {
    // Total events count
    $evRes = $mysqli->query("SELECT COUNT(*) AS total FROM events");
    if ($evRes && $row = $evRes->fetch_assoc()) {
        $totalEventsCount = (int)$row['total'];
    }

    // Upcoming events
    $upRes = $mysqli->query("SELECT * FROM events ORDER BY event_date ASC LIMIT 4");
    if ($upRes) {
        while ($row = $upRes->fetch_assoc()) {
            $upcomingEvents[] = $row;
        }
    }
}

// Fallback to json if DB had 0 events
if (empty($upcomingEvents)) {
    $eventsJsonPath = __DIR__ . '/../data/events.json';
    if (file_exists($eventsJsonPath)) {
        $decoded = json_decode(file_get_contents($eventsJsonPath), true);
        if (is_array($decoded)) {
            $upcomingEvents = array_slice($decoded, 0, 4);
            $totalEventsCount = count($decoded);
        }
    }
}

// Count confirmed passes
$confirmedPasses = 0;
foreach ($activeRegistrations as $r) {
    if (($r['attendance_status'] ?? '') === 'Confirmed') {
        $confirmedPasses++;
    }
}

// Flash messages
$flashSuccess = getFlashMessage('success');
$flashError   = getFlashMessage('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - StudentHub Campus Portal</title>
    <link rel="stylesheet" href="../css/portal.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="portal-layout">
        <!-- Modern Role-Aware Sidebar -->
        <?= renderPortalSidebar('dashboard', '../pages/') ?>

        <div class="portal-main">
            <!-- Modern Topbar -->
            <?= renderPortalTopbar('Dashboard', 'Student Portal', '../pages/') ?>

            <main class="portal-content">
                <?php if ($flashSuccess): ?>
                    <div class="portal-alert portal-alert-success" style="margin-bottom:20px;">
                        <span style="font-size:18px;">✅</span>
                        <div><?= htmlspecialchars($flashSuccess) ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($flashError): ?>
                    <div class="portal-alert portal-alert-error" style="margin-bottom:20px;">
                        <span style="font-size:18px;">⚠️</span>
                        <div><?= htmlspecialchars($flashError) ?></div>
                    </div>
                <?php endif; ?>

                <!-- Welcome Card -->
                <div class="portal-welcome-card" style="margin-bottom:24px;">
                    <div class="portal-welcome-content">
                        <h2>Welcome back, <?= htmlspecialchars($currentUser['full_name']) ?>! 👋</h2>
                        <p>
                            Enrolled in <strong><?= htmlspecialchars($currentUser['course']) ?></strong> (Year <?= htmlspecialchars($currentUser['academic_year']) ?>)
                            &nbsp;•&nbsp; Registration ID: <strong><?= htmlspecialchars($currentUser['registration_no'] ?? 'STD-' . $studentId) ?></strong>
                        </p>
                    </div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <a href="events.html" class="btn-portal-primary">
                            <span>🔍 Explore Events</span>
                        </a>
                        <a href="my_registrations.php" class="btn-portal-secondary">
                            <span>🎟️ My Passes (<?= count($activeRegistrations) ?>)</span>
                        </a>
                        <a href="profile.php" class="btn-portal-ghost" style="border:1px solid var(--sh-border-color);">
                            <span>👤 Edit Profile</span>
                        </a>
                    </div>
                </div>

                <!-- Real Database KPI Metric Grid -->
                <div class="portal-kpi-grid" style="margin-bottom:24px;">
                    <div class="kpi-card">
                        <div class="kpi-icon-wrapper" style="background:#eff6ff;color:#1e40af;">📅</div>
                        <div class="kpi-info">
                            <span class="kpi-label">Available Campus Events</span>
                            <span class="kpi-value"><?= $totalEventsCount ?></span>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-icon-wrapper" style="background:#ecfdf5;color:#059669;">🎟️</div>
                        <div class="kpi-info">
                            <span class="kpi-label">Active Event Passes</span>
                            <span class="kpi-value"><?= $confirmedPasses ?></span>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-icon-wrapper" style="background:#fef3c7;color:#d97706;">🎓</div>
                        <div class="kpi-info">
                            <span class="kpi-label">Academic Program</span>
                            <span class="kpi-value" style="font-size:15px;line-height:1.2;font-weight:600;margin-top:4px;">
                                <?= htmlspecialchars($currentUser['course']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-icon-wrapper" style="background:#f5f3ff;color:#7c3aed;">⏱️</div>
                        <div class="kpi-info">
                            <span class="kpi-label">Session Idle Timer</span>
                            <span class="kpi-value" id="timeoutCountdown" style="color:#7c3aed;">
                                <?= gmdate("i:s", $remainingTimeoutSeconds) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="grid-2-cols" style="align-items:start;">
                    <!-- Left: My Active Registrations & Session Security -->
                    <div style="display:flex;flex-direction:column;gap:24px;">
                        <!-- Active Passes Card -->
                        <div class="portal-card">
                            <div class="portal-card-header">
                                <div>
                                    <h3 class="portal-card-title">My Registered Event Passes</h3>
                                    <p class="portal-card-subtitle">Verified tickets issued to your student account.</p>
                                </div>
                                <a href="my_registrations.php" class="btn-portal-ghost" style="font-size:12px;">View All</a>
                            </div>
                            <div class="portal-card-body">
                                <?php if (empty($activeRegistrations)): ?>
                                    <div class="portal-empty-state" style="padding:24px 12px;">
                                        <div class="empty-icon">🎫</div>
                                        <div class="empty-title">No Active Passes Found</div>
                                        <div class="empty-desc">You have not registered for any upcoming events. Check out the latest campus workshops and hackathons!</div>
                                        <a href="events.html" class="btn-portal-primary" style="margin-top:12px;font-size:13px;">Browse Events</a>
                                    </div>
                                <?php else: ?>
                                    <div style="display:flex;flex-direction:column;gap:12px;">
                                        <?php foreach (array_slice($activeRegistrations, 0, 3) as $reg): 
                                            $isConfirmed = ($reg['attendance_status'] === 'Confirmed');
                                        ?>
                                            <div style="padding:14px;background:#f8fafc;border-radius:var(--sh-radius-sm);border:1px solid var(--sh-border-color);display:flex;justify-content:space-between;align-items:center;gap:12px;">
                                                <div>
                                                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                                                        <span class="code-badge" style="font-size:11px;"><?= htmlspecialchars($reg['registration_code']) ?></span>
                                                        <span class="status-badge <?= $isConfirmed ? 'badge-confirmed' : 'badge-cancelled' ?>" style="font-size:11px;">
                                                            <?= htmlspecialchars($reg['attendance_status']) ?>
                                                        </span>
                                                    </div>
                                                    <div style="font-weight:600;font-size:14px;color:var(--sh-text-primary);">
                                                        <?= htmlspecialchars($reg['title']) ?>
                                                    </div>
                                                    <div style="font-size:12px;color:var(--sh-text-muted);margin-top:2px;">
                                                        📅 <?= date('M d, Y', strtotime($reg['event_date'])) ?> &nbsp;•&nbsp; 📍 <?= htmlspecialchars($reg['venue']) ?>
                                                    </div>
                                                </div>
                                                <div>
                                                    <?php if ($isConfirmed): ?>
                                                        <form action="../php/event_register.php" method="POST" onsubmit="return confirm('Cancel registration for this event?');">
                                                            <input type="hidden" name="action" value="cancel">
                                                            <input type="hidden" name="registration_id" value="<?= (int)$reg['registration_id'] ?>">
                                                            <button type="submit" class="btn-portal-danger" style="font-size:11px;padding:4px 8px;">Cancel</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Session Security Diagnostics Card (Practical 10 Requirement) -->
                        <div class="portal-card">
                            <div class="portal-card-header">
                                <div>
                                    <h3 class="portal-card-title">Active Session Security (Practical 10)</h3>
                                    <p class="portal-card-subtitle">Server-side session validation &amp; inactivity protection.</p>
                                </div>
                                <span class="status-badge badge-confirmed">Protected</span>
                            </div>
                            <div class="portal-card-body">
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13px;">
                                    <div style="background:#f8fafc;padding:10px;border-radius:6px;border:1px solid var(--sh-border-color);">
                                        <span style="color:var(--sh-text-muted);display:block;font-size:11px;">Session ID (Regenerated)</span>
                                        <code style="font-size:12px;color:var(--sh-primary);"><?= substr($sessionId, 0, 16) . '...' ?></code>
                                    </div>
                                    <div style="background:#f8fafc;padding:10px;border-radius:6px;border:1px solid var(--sh-border-color);">
                                        <span style="color:var(--sh-text-muted);display:block;font-size:11px;">RBAC User Role</span>
                                        <strong style="color:var(--sh-text-primary);">Student (Verified)</strong>
                                    </div>
                                    <div style="background:#f8fafc;padding:10px;border-radius:6px;border:1px solid var(--sh-border-color);">
                                        <span style="color:var(--sh-text-muted);display:block;font-size:11px;">Remember-Me Token</span>
                                        <strong><?= $hasRememberCookie ? 'Active (SHA-256)' : 'Standard Session' ?></strong>
                                    </div>
                                    <div style="background:#f8fafc;padding:10px;border-radius:6px;border:1px solid var(--sh-border-color);">
                                        <span style="color:var(--sh-text-muted);display:block;font-size:11px;">Idle Timeout Action</span>
                                        <span>15m Auto-termination</span>
                                    </div>
                                </div>
                                <div style="margin-top:14px;display:flex;justify-content:flex-end;">
                                    <button type="button" class="btn-portal-secondary" style="font-size:12px;padding:6px 12px;" onclick="resetInactivityTimer()">
                                        🔄 Keep Session Alive
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Upcoming Events Showcase -->
                    <div class="portal-card">
                        <div class="portal-card-header">
                            <div>
                                <h3 class="portal-card-title">Featured Upcoming Events</h3>
                                <p class="portal-card-subtitle">Seminars, workshops, and campus activities.</p>
                            </div>
                            <a href="events.html" class="btn-portal-ghost" style="font-size:12px;">Catalog</a>
                        </div>
                        <div class="portal-card-body">
                            <?php if (empty($upcomingEvents)): ?>
                                <div class="portal-empty-state">
                                    <div class="empty-icon">🎪</div>
                                    <div class="empty-title">No Events Available</div>
                                    <div class="empty-desc">Check back soon for upcoming campus schedules.</div>
                                </div>
                            <?php else: ?>
                                <div style="display:flex;flex-direction:column;gap:14px;">
                                    <?php foreach ($upcomingEvents as $ev): 
                                        $evId = (int)$ev['event_id'];
                                        // Check if already registered
                                        $alreadyReg = false;
                                        foreach ($activeRegistrations as $r) {
                                            if ((int)$r['event_id'] === $evId && $r['attendance_status'] !== 'Cancelled') {
                                                $alreadyReg = true;
                                                break;
                                            }
                                        }
                                    ?>
                                        <div style="border:1px solid var(--sh-border-color);border-radius:var(--sh-radius-md);padding:14px;background:#ffffff;box-shadow:var(--sh-shadow-sm);transition:transform 0.15s,box-shadow 0.15s;">
                                            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;">
                                                <span style="background:#e0e7ff;color:#3730a3;font-size:11px;font-weight:600;padding:2px 8px;border-radius:4px;">
                                                    <?= htmlspecialchars($ev['category'] ?? 'General') ?>
                                                </span>
                                                <span style="font-size:12px;color:var(--sh-text-muted);">
                                                    <?= (int)($ev['available_seats'] ?? 0) ?> seats left
                                                </span>
                                            </div>
                                            <h4 style="margin:8px 0 4px 0;font-size:15px;color:var(--sh-text-primary);">
                                                <?= htmlspecialchars($ev['title']) ?>
                                            </h4>
                                            <p style="margin:0 0 10px 0;font-size:12px;color:var(--sh-text-secondary);line-height:1.4;">
                                                <?= htmlspecialchars(mb_strimwidth($ev['description'] ?? '', 0, 100, '...')) ?>
                                            </p>
                                            <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;color:var(--sh-text-muted);border-top:1px solid #f1f5f9;padding-top:10px;">
                                                <div>
                                                    📅 <?= date('M d, Y', strtotime($ev['event_date'])) ?> &bull; 📍 <?= htmlspecialchars($ev['venue']) ?>
                                                </div>
                                                <div>
                                                    <?php if ($alreadyReg): ?>
                                                        <span class="status-badge badge-confirmed" style="font-size:11px;">Already Registered</span>
                                                    <?php else: ?>
                                                        <form action="../php/event_register.php" method="POST" style="display:inline;">
                                                            <input type="hidden" name="action" value="register">
                                                            <input type="hidden" name="event_id" value="<?= $evId ?>">
                                                            <button type="submit" class="btn-portal-primary" style="font-size:11px;padding:4px 10px;">
                                                                Register Now
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        let remainingSeconds = <?= $remainingTimeoutSeconds ?>;
        const countdownEl = document.getElementById('timeoutCountdown');

        setInterval(() => {
            if (remainingSeconds > 0) {
                remainingSeconds--;
                const mins = String(Math.floor(remainingSeconds / 60)).padStart(2, '0');
                const secs = String(remainingSeconds % 60).padStart(2, '0');
                if (countdownEl) countdownEl.textContent = `${mins}:${secs}`;
            } else {
                window.location.href = '../php/logout.php?reason=timeout';
            }
        }, 1000);

        function resetInactivityTimer() {
            fetch('../php/auth_session.php?action=ping')
                .then(() => {
                    remainingSeconds = 900;
                    alert('Session inactivity timer refreshed (15 minutes).');
                })
                .catch(() => {
                    remainingSeconds = 900;
                });
        }
    </script>
</body>
</html>
