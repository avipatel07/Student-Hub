<?php
/**
 * ============================================================================
 * StudentHub Modern Portal - Student Registrations & Tickets
 * Displays all active and historical event tickets with cancellation support.
 * ============================================================================
 */

require_once __DIR__ . '/../php/auth_session.php';

// Enforce login
$user = requireLogin();
$studentId = (int)$user['user_id'];

// Get user registrations from real database
$registrations = getStudentRegistrations($studentId);

// Filter counts
$totalRegs = count($registrations);
$confirmedCount = 0;
$cancelledCount = 0;
foreach ($registrations as $r) {
    if ($r['attendance_status'] === 'Confirmed') $confirmedCount++;
    elseif ($r['attendance_status'] === 'Cancelled') $cancelledCount++;
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
    <title>My Registrations - StudentHub Campus Portal</title>
    <link rel="stylesheet" href="../css/portal.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="portal-layout">
        <!-- Modern Sidebar -->
        <?= renderPortalSidebar('my_registrations', '../pages/') ?>

        <div class="portal-main">
            <!-- Modern Topbar -->
            <?= renderPortalTopbar('My Registrations', 'Events & Passes', '../pages/') ?>

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

                <!-- Header Welcome Banner -->
                <div class="portal-welcome-card" style="margin-bottom:24px;">
                    <div class="portal-welcome-content">
                        <h2>My Event Passes &amp; Registrations 🎟️</h2>
                        <p>View your confirmed campus event tickets, admission passes, and participation records.</p>
                    </div>
                    <div>
                        <a href="events.html" class="btn-portal-primary">
                            <span>🔍 Browse More Events</span>
                        </a>
                    </div>
                </div>

                <!-- KPI Metric Cards -->
                <div class="portal-kpi-grid" style="margin-bottom:24px;">
                    <div class="kpi-card">
                        <div class="kpi-icon-wrapper" style="background:#eff6ff;color:#1e40af;">🎟️</div>
                        <div class="kpi-info">
                            <span class="kpi-label">Total Registrations</span>
                            <span class="kpi-value"><?= $totalRegs ?></span>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-icon-wrapper" style="background:#ecfdf5;color:#059669;">✅</div>
                        <div class="kpi-info">
                            <span class="kpi-label">Confirmed Passes</span>
                            <span class="kpi-value"><?= $confirmedCount ?></span>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-icon-wrapper" style="background:#fef2f2;color:#dc2626;">❌</div>
                        <div class="kpi-info">
                            <span class="kpi-label">Cancelled Bookings</span>
                            <span class="kpi-value"><?= $cancelledCount ?></span>
                        </div>
                    </div>
                </div>

                <!-- Registrations Table Card -->
                <div class="portal-card">
                    <div class="portal-card-header">
                        <div>
                            <h3 class="portal-card-title">Registration History</h3>
                            <p class="portal-card-subtitle">All events you have signed up for through the StudentHub portal.</p>
                        </div>
                    </div>

                    <?php if (empty($registrations)): ?>
                        <div class="portal-empty-state">
                            <div class="empty-icon">🎫</div>
                            <div class="empty-title">You Haven't Registered For Any Events Yet</div>
                            <div class="empty-desc">Discover technical workshops, hackathons, guest lectures, and cultural fests happening across campus.</div>
                            <a href="events.html" class="btn-portal-primary" style="margin-top:16px;">
                                <span>Browse Upcoming Events</span>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="portal-table-container">
                            <table class="portal-table">
                                <thead>
                                    <tr>
                                        <th>Ticket Pass ID</th>
                                        <th>Event Title &amp; Details</th>
                                        <th>Schedule &amp; Venue</th>
                                        <th>Status</th>
                                        <th>Registered On</th>
                                        <th style="text-align:right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($registrations as $reg): 
                                        $status = $reg['attendance_status'];
                                        $badgeClass = $status === 'Confirmed' ? 'badge-confirmed' : ($status === 'Cancelled' ? 'badge-cancelled' : 'badge-pending');
                                        $isCancelled = ($status === 'Cancelled');
                                    ?>
                                        <tr>
                                            <td>
                                                <span class="code-badge"><?= htmlspecialchars($reg['registration_code']) ?></span>
                                            </td>
                                            <td>
                                                <div style="font-weight:600;color:var(--sh-text-primary);font-size:14px;">
                                                    <?= htmlspecialchars($reg['title']) ?>
                                                </div>
                                                <div style="font-size:12px;color:var(--sh-text-muted);display:flex;align-items:center;gap:6px;margin-top:2px;">
                                                    <span style="background:#e0e7ff;color:#3730a3;padding:2px 6px;border-radius:4px;font-weight:500;">
                                                        <?= htmlspecialchars($reg['category']) ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-size:13px;color:var(--sh-text-primary);font-weight:500;">
                                                    📅 <?= date('M d, Y', strtotime($reg['event_date'])) ?>
                                                </div>
                                                <div style="font-size:12px;color:var(--sh-text-muted);">
                                                    ⏰ <?= htmlspecialchars(substr($reg['event_time'] ?? '10:00:00', 0, 5)) ?> &nbsp;•&nbsp; 📍 <?= htmlspecialchars($reg['venue']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="status-badge <?= $badgeClass ?>">
                                                    <?= htmlspecialchars($status) ?>
                                                </span>
                                            </td>
                                            <td style="font-size:12px;color:var(--sh-text-secondary);">
                                                <?= date('M d, Y', strtotime($reg['registration_date'])) ?>
                                            </td>
                                            <td style="text-align:right;">
                                                <?php if (!$isCancelled): ?>
                                                    <form action="../php/event_register.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to cancel your pass for <?= addslashes(htmlspecialchars($reg['title'])) ?>? Your seat will be released for other students.');">
                                                        <input type="hidden" name="action" value="cancel">
                                                        <input type="hidden" name="registration_id" value="<?= (int)$reg['registration_id'] ?>">
                                                        <button type="submit" class="btn-portal-danger" style="font-size:12px;padding:6px 12px;">
                                                            Cancel Pass
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span style="font-size:12px;color:var(--sh-text-muted);font-style:italic;">Pass Cancelled</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
