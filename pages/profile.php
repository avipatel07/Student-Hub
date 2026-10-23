<?php
/**
 * ============================================================================
 * StudentHub Modern Portal - Student & Admin Profile
 * Provides profile view, editable details, password management, and registration history.
 * ============================================================================
 */

require_once __DIR__ . '/../php/auth_session.php';

// Require login
$user = requireLogin();
$studentId = (int)$user['user_id'];

// Retrieve latest data from DB
$mysqli = getMySQLiConnection();
$profile = $user;
if ($mysqli instanceof mysqli && !@$mysqli->connect_errno) {
    $stmt = $mysqli->prepare("SELECT * FROM students WHERE student_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $profile = array_merge($profile, $row);
        }
        $stmt->close();
    }
}

// Get user registrations
$registrations = getStudentRegistrations($studentId);

// Flash messages
$flashSuccess = getFlashMessage('success');
$flashError   = getFlashMessage('error');

// Generate initials
$initials = '';
$words = explode(' ', $profile['full_name'] ?? 'User');
foreach ($words as $w) {
    if (!empty($w)) $initials .= strtoupper($w[0]);
    if (strlen($initials) >= 2) break;
}
if (empty($initials)) $initials = 'SH';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - StudentHub Campus Portal</title>
    <link rel="stylesheet" href="../css/portal.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="portal-layout">
        <!-- Modern Role-Aware Sidebar -->
        <?= renderPortalSidebar('profile', '../pages/') ?>

        <div class="portal-main">
            <!-- Modern Responsive Topbar -->
            <?= renderPortalTopbar('My Profile', 'Account Settings', '../pages/') ?>

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

                <!-- Profile Header Banner -->
                <div class="portal-welcome-card" style="margin-bottom:24px;">
                    <div class="portal-welcome-content">
                        <div style="display:flex;align-items:center;gap:16px;">
                            <div class="user-avatar-circle" style="width:64px;height:64px;font-size:24px;border:3px solid #ffffff;box-shadow:var(--sh-shadow-sm);">
                                <?= htmlspecialchars($initials) ?>
                            </div>
                            <div>
                                <h1 style="font-size:22px;color:var(--sh-text-primary);margin-bottom:4px;">
                                    <?= htmlspecialchars($profile['full_name']) ?>
                                </h1>
                                <p style="font-size:13px;color:var(--sh-text-secondary);margin:0;">
                                    <span class="topbar-role-badge <?= $profile['role'] === 'admin' ? 'badge-admin' : 'badge-student' ?>">
                                        <?= $profile['role'] === 'admin' ? '🛡️ Campus Administrator' : '🎓 Enrolled Student' ?>
                                    </span>
                                    &nbsp;•&nbsp; Member since <?= date('M Y', strtotime($profile['created_at'] ?? 'now')) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <a href="my_registrations.php" class="btn-portal-secondary">
                            <span>🎟️ View Registrations (<?= count($registrations) ?>)</span>
                        </a>
                    </div>
                </div>

                <div class="grid-2-cols" style="align-items:start;">
                    <!-- Left Column: Personal Information Form -->
                    <div class="portal-card">
                        <div class="portal-card-header">
                            <div>
                                <h3 class="portal-card-title">Personal Information</h3>
                                <p class="portal-card-subtitle">Keep your official student records up to date.</p>
                            </div>
                        </div>
                        <div class="portal-card-body">
                            <form action="../php/profile_action.php" method="POST">
                                <input type="hidden" name="action" value="update_profile">

                                <div class="portal-form-group">
                                    <label class="portal-label">Student ID / System ID</label>
                                    <input type="text" class="portal-input" value="STD-<?= str_pad((string)$profile['student_id'], 4, '0', STR_PAD_LEFT) ?>" disabled style="background:#f1f5f9;cursor:not-allowed;">
                                </div>

                                <div class="portal-form-group">
                                    <label class="portal-label">Portal Username</label>
                                    <input type="text" class="portal-input" value="<?= htmlspecialchars($profile['username']) ?>" disabled style="background:#f1f5f9;cursor:not-allowed;">
                                    <span class="portal-form-hint">Username is your permanent login identifier.</span>
                                </div>

                                <div class="portal-form-group">
                                    <label class="portal-label" for="full_name">Full Name <span style="color:var(--sh-danger);">*</span></label>
                                    <input type="text" id="full_name" name="full_name" class="portal-input" value="<?= htmlspecialchars($profile['full_name']) ?>" required minlength="3">
                                </div>

                                <div class="portal-form-group">
                                    <label class="portal-label" for="email">Institutional Email <span style="color:var(--sh-danger);">*</span></label>
                                    <input type="email" id="email" name="email" class="portal-input" value="<?= htmlspecialchars($profile['email']) ?>" required>
                                </div>

                                <div class="portal-form-group">
                                    <label class="portal-label" for="mobile">Mobile Number <span style="color:var(--sh-danger);">*</span></label>
                                    <input type="tel" id="mobile" name="mobile" class="portal-input" value="<?= htmlspecialchars($profile['mobile']) ?>" required pattern="[0-9]{10}">
                                    <span class="portal-form-hint">10-digit primary phone number.</span>
                                </div>

                                <div class="grid-2-cols" style="margin-bottom:16px;">
                                    <div>
                                        <label class="portal-label" for="course">Program / Course <span style="color:var(--sh-danger);">*</span></label>
                                        <select id="course" name="course" class="portal-select" required>
                                            <option value="B.Tech Computer Science" <?= ($profile['course'] ?? '') === 'B.Tech Computer Science' ? 'selected' : '' ?>>B.Tech Computer Science</option>
                                            <option value="B.Tech Information Technology" <?= ($profile['course'] ?? '') === 'B.Tech Information Technology' ? 'selected' : '' ?>>B.Tech Information Technology</option>
                                            <option value="BCA" <?= ($profile['course'] ?? '') === 'BCA' ? 'selected' : '' ?>>BCA</option>
                                            <option value="MCA" <?= ($profile['course'] ?? '') === 'MCA' ? 'selected' : '' ?>>MCA</option>
                                            <option value="B.Sc IT" <?= ($profile['course'] ?? '') === 'B.Sc IT' ? 'selected' : '' ?>>B.Sc IT</option>
                                            <option value="M.Tech Software Engineering" <?= ($profile['course'] ?? '') === 'M.Tech Software Engineering' ? 'selected' : '' ?>>M.Tech Software Engineering</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="portal-label" for="academic_year">Academic Year <span style="color:var(--sh-danger);">*</span></label>
                                        <select id="academic_year" name="academic_year" class="portal-select" required>
                                            <option value="1" <?= (int)($profile['academic_year'] ?? 1) === 1 ? 'selected' : '' ?>>1st Year (Freshman)</option>
                                            <option value="2" <?= (int)($profile['academic_year'] ?? 1) === 2 ? 'selected' : '' ?>>2nd Year (Sophomore)</option>
                                            <option value="3" <?= (int)($profile['academic_year'] ?? 1) === 3 ? 'selected' : '' ?>>3rd Year (Junior)</option>
                                            <option value="4" <?= (int)($profile['academic_year'] ?? 1) === 4 ? 'selected' : '' ?>>4th Year (Senior)</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="portal-form-group">
                                    <label class="portal-label" for="gender">Gender</label>
                                    <select id="gender" name="gender" class="portal-select">
                                        <option value="Male" <?= ($profile['gender'] ?? 'Male') === 'Male' ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= ($profile['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                        <option value="Other" <?= ($profile['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>

                                <div style="display:flex;justify-content:flex-end;margin-top:20px;">
                                    <button type="submit" class="btn-portal-primary">
                                        💾 Save Profile Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Right Column: Security & Quick Registration Summary -->
                    <div style="display:flex;flex-direction:column;gap:24px;">
                        <!-- Password Management Card -->
                        <div class="portal-card">
                            <div class="portal-card-header">
                                <div>
                                    <h3 class="portal-card-title">Security &amp; Password</h3>
                                    <p class="portal-card-subtitle">Ensure your portal login remains secure.</p>
                                </div>
                            </div>
                            <div class="portal-card-body">
                                <form action="../php/profile_action.php" method="POST">
                                    <input type="hidden" name="action" value="change_password">

                                    <div class="portal-form-group">
                                        <label class="portal-label" for="current_password">Current Password <span style="color:var(--sh-danger);">*</span></label>
                                        <input type="password" id="current_password" name="current_password" class="portal-input" placeholder="••••••••" required>
                                    </div>

                                    <div class="portal-form-group">
                                        <label class="portal-label" for="new_password">New Password <span style="color:var(--sh-danger);">*</span></label>
                                        <input type="password" id="new_password" name="new_password" class="portal-input" placeholder="Minimum 6 characters" minlength="6" required>
                                    </div>

                                    <div class="portal-form-group">
                                        <label class="portal-label" for="confirm_password">Confirm New Password <span style="color:var(--sh-danger);">*</span></label>
                                        <input type="password" id="confirm_password" name="confirm_password" class="portal-input" placeholder="Repeat new password" minlength="6" required>
                                    </div>

                                    <div style="display:flex;justify-content:flex-end;">
                                        <button type="submit" class="btn-portal-secondary">
                                            🔒 Update Password
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Active Registrations Preview -->
                        <div class="portal-card">
                            <div class="portal-card-header">
                                <div>
                                    <h3 class="portal-card-title">Registered Events</h3>
                                    <p class="portal-card-subtitle">Events linked to your account.</p>
                                </div>
                                <a href="my_registrations.php" class="btn-portal-ghost" style="font-size:12px;">View All</a>
                            </div>
                            <div class="portal-card-body">
                                <?php if (empty($registrations)): ?>
                                    <div class="portal-empty-state" style="padding:24px 12px;">
                                        <div class="empty-icon">🎪</div>
                                        <div class="empty-title">No Events Registered Yet</div>
                                        <div class="empty-desc">You have not registered for any campus events yet. Explore upcoming hackathons, workshops, and sports.</div>
                                        <a href="events.html" class="btn-portal-primary" style="margin-top:12px;">Browse Events</a>
                                    </div>
                                <?php else: ?>
                                    <div style="display:flex;flex-direction:column;gap:10px;">
                                        <?php foreach (array_slice($registrations, 0, 3) as $reg): ?>
                                            <div style="padding:10px;background:#f8fafc;border-radius:var(--sh-radius-sm);border:1px solid var(--sh-border-color);display:flex;justify-content:space-between;align-items:center;">
                                                <div>
                                                    <div style="font-weight:600;font-size:13px;color:var(--sh-text-primary);">
                                                        <?= htmlspecialchars($reg['title']) ?>
                                                    </div>
                                                    <div style="font-size:11px;color:var(--sh-text-muted);">
                                                        📅 <?= date('M d, Y', strtotime($reg['event_date'])) ?> &nbsp;•&nbsp; 📍 <?= htmlspecialchars($reg['venue']) ?>
                                                    </div>
                                                </div>
                                                <span class="status-badge <?= $reg['attendance_status'] === 'Confirmed' ? 'badge-confirmed' : ($reg['attendance_status'] === 'Cancelled' ? 'badge-cancelled' : 'badge-pending') ?>" style="font-size:11px;">
                                                    <?= htmlspecialchars($reg['attendance_status']) ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
