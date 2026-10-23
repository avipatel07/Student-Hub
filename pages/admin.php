<?php
/**
 * ============================================================================
 * StudentHub Modern Portal - Administrator Dashboard
 * Strict Server-Side RBAC Guard, Real Database Metrics, Registration Feeds,
 * Event Capacity Analytics, and Administration Shortcuts.
 * ============================================================================
 */

require_once __DIR__ . '/../php/auth_session.php';

// Strict Role-Based Access Control: Admin only
requireLogin('admin', 'login.php');

$currentUser = getCurrentUser();
$sessionId = session_id();

$mysqli = getMySQLiConnection();
$totalUsers = 0;
$totalStudents = 0;
$totalAdmins = 0;
$totalEvents = 0;
$totalRegistrations = 0;
$recentStudents = [];
$recentRegistrations = [];

if ($mysqli instanceof mysqli && !@$mysqli->connect_errno) {
    try {
        ensureAuthColumnsExist($mysqli);

        // User stats
        $uRes = $mysqli->query("SELECT role, COUNT(*) as cnt FROM students GROUP BY role");
        if ($uRes) {
            while ($row = $uRes->fetch_assoc()) {
                if ($row['role'] === 'admin') $totalAdmins = (int)$row['cnt'];
                else $totalStudents += (int)$row['cnt'];
                $totalUsers += (int)$row['cnt'];
            }
            $uRes->free();
        }

        // Event stats
        $eRes = $mysqli->query("SELECT COUNT(*) FROM events");
        if ($eRes) {
            $totalEvents = (int)($eRes->fetch_array()[0] ?? 0);
            $eRes->free();
        }

        // Event registration passes count
        $rRes = $mysqli->query("SELECT COUNT(*) FROM event_registrations");
        if ($rRes) {
            $totalRegistrations = (int)($rRes->fetch_array()[0] ?? 0);
            $rRes->free();
        }

        // Recent students
        $sRes = $mysqli->query("SELECT student_id, username, full_name, email, mobile, course, academic_year, role, created_at FROM students ORDER BY student_id DESC LIMIT 8");
        if ($sRes) {
            while ($row = $sRes->fetch_assoc()) {
                $recentStudents[] = $row;
            }
            $sRes->free();
        }

        // Recent event registrations
        $erRes = $mysqli->query("
            SELECT er.registration_id, er.registration_code, er.attendance_status, er.registration_date,
                   s.full_name AS student_name, s.course,
                   e.title AS event_title, e.event_date
            FROM event_registrations er
            JOIN students s ON er.student_id = s.student_id
            JOIN events e ON er.event_id = e.event_id
            ORDER BY er.registration_id DESC
            LIMIT 6
        ");
        if ($erRes) {
            while ($row = $erRes->fetch_assoc()) {
                $recentRegistrations[] = $row;
            }
            $erRes->free();
        }

    } catch (Exception $e) {
        error_log("Admin DB Query Error: " . $e->getMessage());
    }
}

// Fallback if offline
if (empty($recentStudents)) {
    $jsonFile = __DIR__ . '/../data/registrations.json';
    if (file_exists($jsonFile)) {
        $records = json_decode(file_get_contents($jsonFile), true);
        if (is_array($records)) {
            $totalStudents = count($records);
            $totalUsers = $totalStudents + 1;
            foreach (array_slice($records, 0, 8) as $idx => $r) {
                $recentStudents[] = [
                    'student_id' => $idx + 1,
                    'username' => $r['username'] ?? 'student',
                    'full_name' => $r['name'] ?? 'Student',
                    'email' => $r['email'] ?? '',
                    'mobile' => $r['mobile'] ?? '',
                    'course' => $r['course'] ?? 'B.Tech IT',
                    'academic_year' => $r['year'] ?? 1,
                    'role' => $r['role'] ?? 'student',
                    'created_at' => $r['registrationDate'] ?? date('Y-m-d')
                ];
            }
        }
    }
}

$flashSuccess = getFlashMessage('success');
$flashError   = getFlashMessage('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - StudentHub Campus Portal</title>
    <link rel="stylesheet" href="../css/portal.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="portal-layout">
        <!-- Modern Role-Aware Sidebar -->
        <?= renderPortalSidebar('admin_dashboard', '../pages/') ?>

        <div class="portal-main">
            <!-- Modern Topbar -->
            <?= renderPortalTopbar('Admin Dashboard', 'Administration', '../pages/') ?>

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

                <!-- Welcome Header -->
                <div class="portal-welcome-card" style="margin-bottom:24px;">
                    <div class="portal-welcome-content">
                        <h2>Administrator Operations Console 🛡️</h2>
                        <p>
                            Authenticated as <strong><?= htmlspecialchars($currentUser['full_name']) ?></strong> (@<?= htmlspecialchars($currentUser['username']) ?>)
                            &nbsp;•&nbsp; Active RBAC Level: <span class="topbar-role-badge badge-admin" style="display:inline-block;">Full Administrator</span>
                        </p>
                    </div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <a href="student_manager.html" class="btn-portal-primary">
                            <span>👥 Manage Students</span>
                        </a>
                        <a href="event_manager.html" class="btn-portal-secondary">
                            <span>🎪 Manage Events</span>
                        </a>
                        <a href="api_crud.html" class="btn-portal-ghost" style="border:1px solid var(--sh-border-color);">
                            <span>⚡ REST API</span>
                        </a>
                        <a href="../php/admin_dashboard.php" class="btn-portal-ghost" style="border:1px solid var(--sh-border-color);" title="Detailed Practical 14 Analytics">
                            <span>📊 Detailed Stats</span>
                        </a>
                    </div>
                </div>

                <!-- Real Database KPI Metrics -->
                <div class="portal-kpi-grid" style="margin-bottom:24px;">
                    <div class="kpi-card">
                        <div class="kpi-icon-wrapper" style="background:#eff6ff;color:#1e40af;">👥</div>
                        <div class="kpi-info">
                            <span class="kpi-label">Registered Students</span>
                            <span class="kpi-value"><?= $totalStudents ?></span>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-icon-wrapper" style="background:#f0fdf4;color:#16a34a;">📅</div>
                        <div class="kpi-info">
                            <span class="kpi-label">Campus Events</span>
                            <span class="kpi-value"><?= $totalEvents ?></span>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-icon-wrapper" style="background:#fef3c7;color:#d97706;">🎟️</div>
                        <div class="kpi-info">
                            <span class="kpi-label">Event Passes Issued</span>
                            <span class="kpi-value"><?= $totalRegistrations ?></span>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-icon-wrapper" style="background:#fef2f2;color:#dc2626;">🛡️</div>
                        <div class="kpi-info">
                            <span class="kpi-label">Portal Administrators</span>
                            <span class="kpi-value"><?= max(1, $totalAdmins) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Admin Action Shortcuts Grid -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:16px;margin-bottom:24px;">
                    <a href="student_manager.html" class="portal-card" style="padding:16px;text-decoration:none;display:flex;align-items:center;gap:14px;transition:transform 0.15s,box-shadow 0.15s;">
                        <div style="font-size:28px;">👥</div>
                        <div>
                            <div style="font-weight:700;color:var(--sh-text-primary);font-size:14px;">Student Records</div>
                            <div style="font-size:12px;color:var(--sh-text-muted);">CRUD, search &amp; filter students</div>
                        </div>
                    </a>

                    <a href="event_manager.html" class="portal-card" style="padding:16px;text-decoration:none;display:flex;align-items:center;gap:14px;transition:transform 0.15s,box-shadow 0.15s;">
                        <div style="font-size:28px;">🎪</div>
                        <div>
                            <div style="font-weight:700;color:var(--sh-text-primary);font-size:14px;">Event Management</div>
                            <div style="font-size:12px;color:var(--sh-text-muted);">Posters, schedules &amp; capacity</div>
                        </div>
                    </a>

                    <a href="api_crud.html" class="portal-card" style="padding:16px;text-decoration:none;display:flex;align-items:center;gap:14px;transition:transform 0.15s,box-shadow 0.15s;">
                        <div style="font-size:28px;">⚡</div>
                        <div>
                            <div style="font-weight:700;color:var(--sh-text-primary);font-size:14px;">REST API Console</div>
                            <div style="font-size:12px;color:var(--sh-text-muted);">Practical 13 endpoints &amp; tester</div>
                        </div>
                    </a>

                    <a href="../php/db_dashboard.php" class="portal-card" style="padding:16px;text-decoration:none;display:flex;align-items:center;gap:14px;transition:transform 0.15s,box-shadow 0.15s;">
                        <div style="font-size:28px;">🗄️</div>
                        <div>
                            <div style="font-weight:700;color:var(--sh-text-primary);font-size:14px;">Database Center</div>
                            <div style="font-size:12px;color:var(--sh-text-muted);">Tables, indexes &amp; diagnostics</div>
                        </div>
                    </a>
                </div>

                <div class="grid-2-cols" style="align-items:start;">
                    <!-- Recent Students Table -->
                    <div class="portal-card">
                        <div class="portal-card-header">
                            <div>
                                <h3 class="portal-card-title">Enrolled Students</h3>
                                <p class="portal-card-subtitle">Latest student accounts registered in MySQL.</p>
                            </div>
                            <a href="student_manager.html" class="btn-portal-ghost" style="font-size:12px;">Manage All</a>
                        </div>
                        <div class="portal-card-body" style="padding:0;">
                            <div class="portal-table-container">
                                <table class="portal-table">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>Program</th>
                                            <th>Role</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recentStudents)): ?>
                                            <tr>
                                                <td colspan="3" style="text-align:center;padding:24px;color:var(--sh-text-muted);">No student records found.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recentStudents as $s): ?>
                                                <tr>
                                                    <td>
                                                        <div style="font-weight:600;font-size:13px;color:var(--sh-text-primary);">
                                                            <?= htmlspecialchars($s['full_name']) ?>
                                                        </div>
                                                        <div style="font-size:11px;color:var(--sh-text-muted);">
                                                            <?= htmlspecialchars($s['email']) ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div style="font-size:12px;color:var(--sh-text-primary);">
                                                            <?= htmlspecialchars($s['course']) ?>
                                                        </div>
                                                        <div style="font-size:11px;color:var(--sh-text-muted);">
                                                            Year <?= (int)$s['academic_year'] ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="status-badge <?= $s['role'] === 'admin' ? 'badge-admin' : 'badge-student' ?>" style="font-size:11px;">
                                                            <?= htmlspecialchars($s['role']) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Event Registrations Feed -->
                    <div class="portal-card">
                        <div class="portal-card-header">
                            <div>
                                <h3 class="portal-card-title">Recent Event Passes</h3>
                                <p class="portal-card-subtitle">Live bookings across technical and campus events.</p>
                            </div>
                            <a href="event_manager.html" class="btn-portal-ghost" style="font-size:12px;">Events</a>
                        </div>
                        <div class="portal-card-body" style="padding:0;">
                            <div class="portal-table-container">
                                <table class="portal-table">
                                    <thead>
                                        <tr>
                                            <th>Ticket ID</th>
                                            <th>Student &amp; Event</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recentRegistrations)): ?>
                                            <tr>
                                                <td colspan="3" style="text-align:center;padding:24px;color:var(--sh-text-muted);">No event passes issued yet.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recentRegistrations as $reg): 
                                                $status = $reg['attendance_status'];
                                                $bClass = $status === 'Confirmed' ? 'badge-confirmed' : ($status === 'Cancelled' ? 'badge-cancelled' : 'badge-pending');
                                            ?>
                                                <tr>
                                                    <td>
                                                        <span class="code-badge" style="font-size:11px;"><?= htmlspecialchars($reg['registration_code']) ?></span>
                                                    </td>
                                                    <td>
                                                        <div style="font-weight:600;font-size:13px;color:var(--sh-text-primary);">
                                                            <?= htmlspecialchars($reg['student_name']) ?>
                                                        </div>
                                                        <div style="font-size:11px;color:var(--sh-text-muted);">
                                                            🎪 <?= htmlspecialchars($reg['event_title']) ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="status-badge <?= $bClass ?>" style="font-size:11px;">
                                                            <?= htmlspecialchars($status) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
