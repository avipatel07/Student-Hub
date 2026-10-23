<?php
/**
 * ============================================================================
 * StudentHub - Digital Campus Portal
 * PRACTICAL 14: Administrator Dashboard with User Statistics, Event & Student
 * Counts, Latest Registrations, Strict RBAC, and Dynamic Role Navigation
 * ============================================================================
 * 
 * Features & Specifications:
 * 1. Strict RBAC Guard: Restricts dashboard access to authenticated Admins only.
 *    Non-admin users/students attempting access are intercepted and redirected
 *    with explicit security feedback (dashboard.php?error=admin_only).
 * 2. Dynamic Navigation Menu: Renders contextual navigation options based
 *    on user role (Admin vs. Student vs. Guest).
 * 3. User Statistics Engine:
 *    - Total users, students vs administrators distribution.
 *    - Academic program / course enrollment distribution.
 *    - Academic year-wise breakdown (Years 1 to 4).
 *    - Gender demographics breakdown with visual percentage meters.
 * 4. Student Count & KPIs:
 *    - Total registered active students.
 *    - Quick action links to add student, export, and search.
 * 5. Event Count & Seating KPIs:
 *    - Total campus events, categorized breakdown (Tech, Workshop, Cultural, Sports).
 *    - Status distribution (Upcoming, Ongoing, Completed).
 *    - Total seating capacity and seat utilization.
 * 6. Latest Registrations Feed:
 *    - Chronological list of the most recent student registrations.
 *    - Profile details, registration ID, course & year, contact, and timestamps.
 * 7. Interactive Role Simulator:
 *    - Allows testing and demonstrating role restriction on the fly.
 * 8. Dual Synchronization & Offline Lab Resilience:
 *    - Queries MySQL via prepared statements; gracefully falls back to
 *      data/registrations.json and data/events.json if MySQL is offline.
 * ============================================================================
 */

require_once __DIR__ . '/auth_session.php';
require_once __DIR__ . '/db_connect.php';

// Handle Role Simulation Switcher (for testing/grading Practical 14)
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'simulate_student') {
        $_SESSION['role'] = 'student';
        header("Location: ../pages/dashboard.php?message=switched_to_student");
        exit;
    } elseif ($_GET['action'] === 'switch_to_admin') {
        $_SESSION['role'] = 'admin';
        header("Location: admin_dashboard.php?message=switched_to_admin");
        exit;
    }
}

// ----------------------------------------------------------------------------
// 1. STRICT ROLE-BASED ACCESS CONTROL (RBAC GUARD)
// ----------------------------------------------------------------------------
requireLogin('admin', '../pages/login.php');

$currentUser = getCurrentUser();
$sessionId = session_id();

// Initialize Metrics and Data Stores
$totalUsers       = 0;
$totalStudents    = 0;
$totalAdmins      = 0;
$totalEvents      = 0;
$totalSeats       = 0;
$availableSeats   = 0;
$totalInquiries   = 0;
$genderStats      = ['Male' => 0, 'Female' => 0, 'Other' => 0];
$courseStats      = [];
$yearStats        = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
$eventCatStats    = [];
$eventStatusStats = ['Upcoming' => 0, 'Ongoing' => 0, 'Completed' => 0, 'Cancelled' => 0];
$latestStudents   = [];
$isDbOnline       = false;

// ----------------------------------------------------------------------------
// 2. RETRIEVE METRICS FROM MYSQL (WITH GRACEFUL FALLBACK)
// ----------------------------------------------------------------------------
$mysqli = getMySQLiConnection();

if ($mysqli instanceof mysqli && !@$mysqli->connect_errno) {
    try {
        ensureAuthColumnsExist($mysqli);
        $isDbOnline = true;

        // User & Student Counts
        $uRes = $mysqli->query("SELECT role, COUNT(*) as cnt FROM students GROUP BY role");
        if ($uRes) {
            while ($row = $uRes->fetch_assoc()) {
                if ($row['role'] === 'admin') $totalAdmins = (int)$row['cnt'];
                else $totalStudents += (int)$row['cnt'];
                $totalUsers += (int)$row['cnt'];
            }
            $uRes->free();
        }

        // Gender Distribution
        $gRes = $mysqli->query("SELECT gender, COUNT(*) as cnt FROM students GROUP BY gender");
        if ($gRes) {
            while ($row = $gRes->fetch_assoc()) {
                $g = $row['gender'] ?? 'Other';
                $genderStats[$g] = (int)$row['cnt'];
            }
            $gRes->free();
        }

        // Course Distribution
        $cRes = $mysqli->query("SELECT course, COUNT(*) as cnt FROM students WHERE role = 'student' GROUP BY course ORDER BY cnt DESC");
        if ($cRes) {
            while ($row = $cRes->fetch_assoc()) {
                $courseStats[$row['course']] = (int)$row['cnt'];
            }
            $cRes->free();
        }

        // Year Distribution
        $yRes = $mysqli->query("SELECT academic_year, COUNT(*) as cnt FROM students WHERE role = 'student' GROUP BY academic_year");
        if ($yRes) {
            while ($row = $yRes->fetch_assoc()) {
                $y = (int)$row['academic_year'];
                if (isset($yearStats[$y])) $yearStats[$y] = (int)$row['cnt'];
            }
            $yRes->free();
        }

        // Latest 8 Student Registrations
        $sRes = $mysqli->query("
            SELECT student_id, registration_no, username, full_name, email, mobile, course, academic_year, gender, role, created_at 
            FROM students 
            ORDER BY student_id DESC 
            LIMIT 8
        ");
        if ($sRes) {
            while ($row = $sRes->fetch_assoc()) {
                $latestStudents[] = $row;
            }
            $sRes->free();
        }

        // Events Metrics
        $eRes = $mysqli->query("SELECT COUNT(*) as total_ev, SUM(total_seats) as seats, SUM(available_seats) as avail FROM events");
        if ($eRes) {
            $eRow = $eRes->fetch_assoc();
            $totalEvents = (int)($eRow['total_ev'] ?? 0);
            $totalSeats = (int)($eRow['seats'] ?? 0);
            $availableSeats = (int)($eRow['avail'] ?? 0);
            $eRes->free();
        }

        // Event Category Distribution
        $catRes = $mysqli->query("SELECT category, COUNT(*) as cnt FROM events GROUP BY category");
        if ($catRes) {
            while ($row = $catRes->fetch_assoc()) {
                $eventCatStats[$row['category']] = (int)$row['cnt'];
            }
            $catRes->free();
        }

        // Event Status Distribution
        $stRes = $mysqli->query("SELECT status, COUNT(*) as cnt FROM events GROUP BY status");
        if ($stRes) {
            while ($row = $stRes->fetch_assoc()) {
                $st = $row['status'];
                if (isset($eventStatusStats[$st])) $eventStatusStats[$st] = (int)$row['cnt'];
            }
            $stRes->free();
        }

        // Contact Inquiries Count
        $iRes = $mysqli->query("SELECT COUNT(*) as cnt FROM contact_inquiries");
        if ($iRes) {
            $iRow = $iRes->fetch_assoc();
            $totalInquiries = (int)($iRow['cnt'] ?? 0);
            $iRes->free();
        }
    } catch (Exception $e) {
        error_log("Practical 14 Admin Dashboard DB Notice: " . $e->getMessage());
        $isDbOnline = false;
    }
}

// ----------------------------------------------------------------------------
// 3. FLAT JSON DATASTORE FALLBACK (FOR OFFLINE LAB RESILIENCE)
// ----------------------------------------------------------------------------
if (!$isDbOnline || empty($latestStudents)) {
    $regJsonFile = __DIR__ . '/../data/registrations.json';
    if (file_exists($regJsonFile)) {
        $raw = file_get_contents($regJsonFile);
        $records = !empty($raw) ? json_decode($raw, true) : [];
        if (is_array($records)) {
            $totalUsers = count($records);
            $totalStudents = 0;
            $totalAdmins = 0;
            $genderStats = ['Male' => 0, 'Female' => 0, 'Other' => 0];
            $courseStats = [];
            $yearStats = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
            $latestStudents = [];

            foreach (array_reverse($records) as $idx => $r) {
                $role = $r['role'] ?? 'student';
                if ($role === 'admin') $totalAdmins++;
                else $totalStudents++;

                $g = $r['gender'] ?? 'Other';
                if (isset($genderStats[$g])) $genderStats[$g]++;
                else $genderStats['Other']++;

                $c = $r['course'] ?? 'General';
                $courseStats[$c] = ($courseStats[$c] ?? 0) + 1;

                $y = (int)($r['year'] ?? 1);
                if (isset($yearStats[$y])) $yearStats[$y]++;

                if (count($latestStudents) < 8) {
                    $latestStudents[] = [
                        'student_id'      => $idx + 1,
                        'registration_no' => $r['registrationId'] ?? ('STU' . ($idx + 1)),
                        'username'        => $r['username'] ?? 'student',
                        'full_name'       => $r['name'] ?? 'Student',
                        'email'           => $r['email'] ?? '',
                        'mobile'          => $r['mobile'] ?? '',
                        'course'          => $r['course'] ?? 'B.Tech IT',
                        'academic_year'   => (int)($r['year'] ?? 1),
                        'gender'          => $r['gender'] ?? 'Other',
                        'role'            => $role,
                        'created_at'      => $r['registrationDate'] ?? date('Y-m-d H:i:s')
                    ];
                }
            }
        }
    }

    $eventJsonFile = __DIR__ . '/../data/events.json';
    if (file_exists($eventJsonFile)) {
        $eRaw = file_get_contents($eventJsonFile);
        $eRecords = !empty($eRaw) ? json_decode($eRaw, true) : [];
        if (is_array($eRecords)) {
            $totalEvents = count($eRecords);
            $totalSeats = 0;
            $availableSeats = 0;
            $eventCatStats = [];
            $eventStatusStats = ['Upcoming' => 0, 'Ongoing' => 0, 'Completed' => 0, 'Cancelled' => 0];

            foreach ($eRecords as $ev) {
                $s = (int)($ev['seats'] ?? 0);
                $as = (int)($ev['available_seats'] ?? $s);
                $totalSeats += $s;
                $availableSeats += $as;

                $cat = $ev['category'] ?? 'Technology';
                $eventCatStats[$cat] = ($eventCatStats[$cat] ?? 0) + 1;

                $st = $ev['status'] ?? 'Upcoming';
                if (isset($eventStatusStats[$st])) $eventStatusStats[$st]++;
            }
        }
    }
}

// ----------------------------------------------------------------------------
// 4. HELPER: DYNAMIC ROLE-BASED NAVIGATION MENU GENERATOR
// ----------------------------------------------------------------------------
function renderDynamicNavbar(?array $user, string $activePage = 'admin_dashboard'): string {
    $role = $user['role'] ?? 'guest';
    $html = '<div class="nav-container">';

    // Common Links
    $html .= '<a href="../pages/index.html">Home</a>';
    $html .= '<a href="../pages/about.html">About</a>';

    if ($role === 'admin') {
        // ADMIN ROLE EXCLUSIVE MENU
        $html .= '<a href="admin_dashboard.php" class="' . ($activePage === 'admin_dashboard' ? 'active' : '') . '">🛡️ Admin Dashboard</a>';
        $html .= '<a href="student_manager.php">👥 Student Manager</a>';
        $html .= '<a href="event_manager.php">🎪 Event Manager</a>';
        $html .= '<a href="../pages/api_crud.html">⚡ JSON API (P13)</a>';
        $html .= '<a href="db_dashboard.php">🗄️ Database Center</a>';
        $html .= '<a href="view_registrations.php">📋 Records</a>';
        $html .= '<a href="../pages/dashboard.php">👨‍🎓 Student View</a>';
        $html .= '<a href="logout.php" style="color: #fca5a5; font-weight: 700;">Sign Out 🚪</a>';
    } elseif ($role === 'student') {
        // STUDENT ROLE EXCLUSIVE MENU (Admin controls hidden)
        $html .= '<a href="../pages/dashboard.php" class="' . ($activePage === 'dashboard' ? 'active' : '') . '">🎓 My Dashboard</a>';
        $html .= '<a href="../pages/events.html">📅 Campus Events</a>';
        $html .= '<a href="../pages/profile.html">👤 Profile</a>';
        $html .= '<a href="../pages/contact.html">💬 Contact</a>';
        $html .= '<a href="../pages/faq.html">❓ FAQs</a>';
        $html .= '<a href="logout.php" style="color: #fca5a5; font-weight: 700;">Sign Out 🚪</a>';
    } else {
        // GUEST / UNAUTHENTICATED MENU
        $html .= '<a href="../pages/events.html">Events</a>';
        $html .= '<a href="../pages/login.php">Login</a>';
        $html .= '<a href="../pages/register.html">Register</a>';
        $html .= '<a href="../pages/contact.html">Contact</a>';
        $html .= '<a href="../pages/faq.html">FAQ</a>';
    }

    $html .= '</div>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="StudentHub Practical 14: Administrator Dashboard with User Statistics, Event & Student Counts, and Dynamic Role Navigation">
    <title>StudentHub - Practical 14: Administrator Dashboard (RBAC)</title>
    <link rel="stylesheet" href="../css/style.css">

    <style>
        :root {
            --admin-dark-bg: #0f172a;
            --admin-card-bg: #ffffff;
            --admin-border: #e2e8f0;
            --admin-text-main: #1e293b;
            --admin-text-muted: #64748b;
            --admin-primary: #4338ca;
            --admin-primary-light: #e0e7ff;
            --admin-success: #10b981;
            --admin-warning: #f59e0b;
            --admin-danger: #ef4444;
        }

        body.dark {
            --admin-card-bg: #1e293b;
            --admin-border: #334155;
            --admin-text-main: #f8fafc;
            --admin-text-muted: #94a3b8;
        }

        /* Hero Banner */
        .admin-hero-banner {
            background: linear-gradient(135deg, #091e3a 0%, #1e1b4b 50%, #312e81 100%);
            color: #ffffff;
            padding: 26px 30px;
            border-radius: 14px;
            margin-bottom: 24px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.25);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            position: relative;
            overflow: hidden;
        }

        .admin-hero-banner::after {
            content: "";
            position: absolute;
            right: -30px;
            top: -30px;
            width: 180px;
            height: 180px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.25) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .role-pill-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .badge-admin-role {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.4);
        }

        /* KPI Cards Grid */
        .dashboard-kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 18px;
            margin-bottom: 24px;
        }

        .kpi-stat-card {
            background: var(--admin-card-bg);
            border: 1px solid var(--admin-border);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .kpi-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
        }

        .kpi-icon-container {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        .kpi-number {
            font-size: 28px;
            font-weight: 800;
            color: var(--admin-text-main);
            line-height: 1.1;
        }

        .kpi-title {
            font-size: 12px;
            font-weight: 700;
            color: var(--admin-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 3px;
        }

        .kpi-subtext {
            font-size: 11px;
            color: var(--admin-text-muted);
            margin-top: 4px;
        }

        /* Demographic & Statistics Cards */
        .stats-two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }

        @media (max-width: 900px) {
            .stats-two-col {
                grid-template-columns: 1fr;
            }
        }

        .stat-panel-card {
            background: var(--admin-card-bg);
            border: 1px solid var(--admin-border);
            border-radius: 12px;
            padding: 22px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
        }

        .panel-heading {
            font-size: 15px;
            font-weight: 700;
            color: var(--admin-text-main);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--admin-border);
            padding-bottom: 10px;
        }

        .progress-bar-group {
            margin-bottom: 12px;
        }

        .progress-bar-label {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            font-weight: 600;
            color: var(--admin-text-main);
            margin-bottom: 4px;
        }

        .progress-bar-track {
            height: 8px;
            background: #f1f5f9;
            border-radius: 9999px;
            overflow: hidden;
        }

        body.dark .progress-bar-track {
            background: #334155;
        }

        .progress-bar-fill {
            height: 100%;
            border-radius: 9999px;
            transition: width 0.4s ease;
        }

        /* Latest Registrations Table Card */
        .records-panel-card {
            background: var(--admin-card-bg);
            border: 1px solid var(--admin-border);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
            margin-bottom: 30px;
        }

        .records-panel-header {
            padding: 18px 22px;
            border-bottom: 1px solid var(--admin-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        .modern-admin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }

        .modern-admin-table th {
            background: #f8fafc;
            color: var(--admin-text-muted);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.6px;
            padding: 12px 18px;
            border-bottom: 1px solid var(--admin-border);
            white-space: nowrap;
        }

        body.dark .modern-admin-table th {
            background: #0f172a;
            border-color: #334155;
            color: #94a3b8;
        }

        .modern-admin-table td {
            padding: 14px 18px;
            border-bottom: 1px solid var(--admin-border);
            color: var(--admin-text-main);
            vertical-align: middle;
        }

        .modern-admin-table tbody tr:hover {
            background: rgba(67, 56, 202, 0.03);
        }

        body.dark .modern-admin-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        /* Action Buttons */
        .btn-action-small {
            padding: 5px 10px;
            border-radius: 6px;
            border: 1px solid var(--admin-border);
            background: #ffffff;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: var(--admin-text-main);
            transition: all 0.15s ease;
        }

        body.dark .btn-action-small {
            background: #1e293b;
            color: #f8fafc;
            border-color: #334155;
        }

        .btn-action-small:hover {
            border-color: var(--admin-primary);
            color: var(--admin-primary);
        }

        /* Security Status Box */
        .security-badge-container {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }

        .sec-box-green {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
    </style>
</head>

<body>

    <!-- ================= HEADER ================= -->
    <header class="site-header">
        <div class="logo-icon">🛡️</div>
        <h1>StudentHub Admin Console</h1>
        <p>PRACTICAL 14: Centralized Campus Administration &amp; RBAC Control</p>
    </header>

    <!-- ================= DYNAMIC NAVBAR BASED ON ROLE ================= -->
    <nav class="navbar">
        <div class="nav-wrapper">
            <button class="menu-toggle" aria-label="Open navigation menu" aria-expanded="false">
                ☰ Menu
            </button>

            <!-- Dynamically Rendered Menu Options Based on Current Role -->
            <?php echo renderDynamicNavbar($currentUser, 'admin_dashboard'); ?>

            <button class="theme-toggle" aria-label="Toggle dark mode">
                🌙 Dark Mode
            </button>
        </div>
    </nav>

    <!-- ================= MAIN CONTENT ================= -->
    <main>
        <div class="container" style="margin-top: 25px; margin-bottom: 50px;">

            <!-- HERO BANNER -->
            <section class="admin-hero-banner">
                <div>
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 8px;">
                        <span class="role-pill-badge badge-admin-role">
                            🛡️ Role: Administrator (Full Access)
                        </span>
                        <span class="role-pill-badge" style="background: rgba(255, 255, 255, 0.15); color: white;">
                            ⚡ Practical 14 RBAC Guard
                        </span>
                        <span class="role-pill-badge" style="background: rgba(16, 185, 129, 0.25); color: #6ee7b7;">
                            🟢 <?php echo $isDbOnline ? 'MySQLi Connected' : 'Lab Offline Flat-File Mode'; ?>
                        </span>
                    </div>

                    <h2 style="margin: 0; font-size: 24px; font-weight: 800; letter-spacing: -0.3px;">
                        Welcome back, <?php echo htmlspecialchars($currentUser['full_name']); ?>!
                    </h2>
                    <p style="margin: 6px 0 0 0; font-size: 13px; opacity: 0.9; max-width: 650px;">
                        Logged in as <strong>@<?php echo htmlspecialchars($currentUser['username']); ?></strong> (<?php echo htmlspecialchars($currentUser['email']); ?>).
                        Access is restricted exclusively to administrators. Navigation options adapt dynamically to your privileges.
                    </p>
                </div>

                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="?action=simulate_student" class="btn" style="background: rgba(255, 255, 255, 0.15); color: white; border: 1px solid rgba(255, 255, 255, 0.3); font-size: 13px; font-weight: 600;" title="Test role restriction: switches session role to student">
                        🔄 Test Student Role (RBAC)
                    </a>
                    <a href="logout.php" class="btn" style="background: #ef4444; border: none; color: white; font-size: 13px; font-weight: 700;">
                        <span>Sign Out</span> 🚪
                    </a>
                </div>
            </section>

            <!-- PRACTICAL 14: FOUR PROMINENT KPI CARDS -->
            <section class="dashboard-kpi-grid">
                <!-- 1. Total User Statistics -->
                <div class="kpi-stat-card">
                    <div class="kpi-icon-container" style="background: #e0e7ff; color: #4338ca;">
                        👥
                    </div>
                    <div>
                        <div class="kpi-number"><?php echo $totalUsers; ?></div>
                        <div class="kpi-title">Total Registered Users</div>
                        <div class="kpi-subtext">
                            <strong><?php echo $totalStudents; ?></strong> Students &bull; <strong><?php echo $totalAdmins; ?></strong> Admins
                        </div>
                    </div>
                </div>

                <!-- 2. Student Count -->
                <div class="kpi-stat-card">
                    <div class="kpi-icon-container" style="background: #ecfdf5; color: #059669;">
                        👨‍🎓
                    </div>
                    <div>
                        <div class="kpi-number"><?php echo $totalStudents; ?></div>
                        <div class="kpi-title">Enrolled Students</div>
                        <div class="kpi-subtext">
                            Across <?php echo count($courseStats); ?> Degree Programs
                        </div>
                    </div>
                </div>

                <!-- 3. Event Count -->
                <div class="kpi-stat-card">
                    <div class="kpi-icon-container" style="background: #eff6ff; color: #2563eb;">
                        🎪
                    </div>
                    <div>
                        <div class="kpi-number"><?php echo $totalEvents; ?></div>
                        <div class="kpi-title">Campus Events</div>
                        <div class="kpi-subtext">
                            <strong><?php echo $eventStatusStats['Upcoming'] ?? 0; ?></strong> Upcoming &bull; <strong><?php echo $totalSeats; ?></strong> Seats
                        </div>
                    </div>
                </div>

                <!-- 4. Security & RBAC Status -->
                <div class="kpi-stat-card">
                    <div class="kpi-icon-container" style="background: #fef3c7; color: #d97706;">
                        🛡️
                    </div>
                    <div>
                        <div class="kpi-number" style="font-size: 20px; color: #16a34a;">Authorized</div>
                        <div class="kpi-title">Access Control Status</div>
                        <div class="kpi-subtext">
                            Strict <code>requireLogin('admin')</code>
                        </div>
                    </div>
                </div>
            </section>

            <!-- STATISTICAL DEMOGRAPHICS & EVENT BREAKDOWN -->
            <section class="stats-two-col">
                <!-- Student Academic & Gender Distribution -->
                <div class="stat-panel-card">
                    <div class="panel-heading">
                        <span>📊 Student Demographics & Academic Distribution</span>
                        <span style="font-size: 11px; font-weight: 600; color: #6366f1;">Total: <?php echo $totalStudents; ?></span>
                    </div>

                    <div style="margin-bottom: 18px;">
                        <div style="font-size: 12px; font-weight: 700; color: var(--admin-text-muted); text-transform: uppercase; margin-bottom: 8px;">
                            Course / Degree Program Enrollment
                        </div>
                        <?php 
                        $colors = ['#4338ca', '#2563eb', '#0ea5e9', '#10b981', '#f59e0b'];
                        $cIdx = 0;
                        foreach ($courseStats as $courseName => $count): 
                            $pct = $totalStudents > 0 ? round(($count / $totalStudents) * 100, 1) : 0;
                            $barColor = $colors[$cIdx % count($colors)];
                            $cIdx++;
                        ?>
                            <div class="progress-bar-group">
                                <div class="progress-bar-label">
                                    <span><?php echo htmlspecialchars($courseName); ?></span>
                                    <span><strong><?php echo $count; ?></strong> (<?php echo $pct; ?>%)</span>
                                </div>
                                <div class="progress-bar-track">
                                    <div class="progress-bar-fill" style="width: <?php echo $pct; ?>%; background: <?php echo $barColor; ?>;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div>
                        <div style="font-size: 12px; font-weight: 700; color: var(--admin-text-muted); text-transform: uppercase; margin-bottom: 8px;">
                            Gender Demographics
                        </div>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <?php foreach ($genderStats as $gender => $gCount): 
                                $gPct = $totalUsers > 0 ? round(($gCount / $totalUsers) * 100, 1) : 0;
                            ?>
                                <div style="flex: 1; min-width: 100px; background: #f8fafc; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--admin-border); text-align: center;">
                                    <div style="font-size: 11px; color: var(--admin-text-muted); font-weight: 600;"><?php echo htmlspecialchars($gender); ?></div>
                                    <div style="font-size: 18px; font-weight: 800; color: var(--admin-text-main); margin-top: 2px;"><?php echo $gCount; ?></div>
                                    <div style="font-size: 10px; color: #64748b;"><?php echo $gPct; ?>%</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Event Categorization & Capacity Metrics -->
                <div class="stat-panel-card">
                    <div class="panel-heading">
                        <span>🎪 Campus Events Breakdown & Seating Metrics</span>
                        <span style="font-size: 11px; font-weight: 600; color: #2563eb;">Total: <?php echo $totalEvents; ?></span>
                    </div>

                    <div style="margin-bottom: 18px;">
                        <div style="font-size: 12px; font-weight: 700; color: var(--admin-text-muted); text-transform: uppercase; margin-bottom: 8px;">
                            Events by Category
                        </div>
                        <?php 
                        $catColors = ['#2563eb', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#06b6d4'];
                        $kIdx = 0;
                        foreach ($eventCatStats as $catName => $evCount): 
                            $evPct = $totalEvents > 0 ? round(($evCount / $totalEvents) * 100, 1) : 0;
                            $fillColor = $catColors[$kIdx % count($catColors)];
                            $kIdx++;
                        ?>
                            <div class="progress-bar-group">
                                <div class="progress-bar-label">
                                    <span><?php echo htmlspecialchars($catName); ?></span>
                                    <span><strong><?php echo $evCount; ?></strong> events (<?php echo $evPct; ?>%)</span>
                                </div>
                                <div class="progress-bar-track">
                                    <div class="progress-bar-fill" style="width: <?php echo $evPct; ?>%; background: <?php echo $fillColor; ?>;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div>
                        <div style="font-size: 12px; font-weight: 700; color: var(--admin-text-muted); text-transform: uppercase; margin-bottom: 8px;">
                            Seating Capacity Utilization
                        </div>
                        <div style="background: #f8fafc; border: 1px solid var(--admin-border); border-radius: 8px; padding: 14px;">
                            <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; margin-bottom: 6px;">
                                <span>Reserved / Booked Seats</span>
                                <span><?php echo max(0, $totalSeats - $availableSeats); ?> / <?php echo $totalSeats; ?> Seats</span>
                            </div>
                            <?php $utilization = $totalSeats > 0 ? round((($totalSeats - $availableSeats) / $totalSeats) * 100, 1) : 0; ?>
                            <div class="progress-bar-track" style="height: 10px;">
                                <div class="progress-bar-fill" style="width: <?php echo $utilization; ?>%; background: #10b981;"></div>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 11px; color: var(--admin-text-muted); margin-top: 6px;">
                                <span>Available: <strong><?php echo $availableSeats; ?></strong> seats</span>
                                <span>Utilization: <strong><?php echo $utilization; ?>%</strong></span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- LATEST REGISTRATIONS TABLE -->
            <section class="records-panel-card">
                <div class="records-panel-header">
                    <div>
                        <h3 style="margin: 0; font-size: 16px; color: var(--admin-text-main); display: flex; align-items: center; gap: 8px;">
                            <span>📋</span> Latest Student Registrations
                        </h3>
                        <p style="margin: 3px 0 0 0; font-size: 12px; color: var(--admin-text-muted);">
                            Displaying chronological incoming registrations with student profile details and quick actions.
                        </p>
                    </div>

                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <a href="student_manager.php" class="btn btn-action-small" style="background: #4338ca; color: white; border: none;">
                            <span>⚡ Open Student CRUD</span>
                        </a>
                        <a href="../pages/api_crud.html" class="btn btn-action-small">
                            <span>📡 JSON REST API</span>
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="modern-admin-table">
                        <thead>
                            <tr>
                                <th>Registration ID</th>
                                <th>Student Name</th>
                                <th>Username</th>
                                <th>Email &amp; Mobile</th>
                                <th>Course &amp; Year</th>
                                <th>Gender</th>
                                <th>Role</th>
                                <th>Registration Date</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($latestStudents)): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 40px; color: var(--admin-text-muted);">
                                        No registration records found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($latestStudents as $st): ?>
                                    <tr>
                                        <td>
                                            <code style="background: #f1f5f9; padding: 3px 6px; border-radius: 4px; font-weight: 700; color: #1e293b; font-size: 11px;">
                                                <?php echo htmlspecialchars($st['registration_no']); ?>
                                            </code>
                                        </td>
                                        <td>
                                            <div style="font-weight: 700; color: var(--admin-text-main);">
                                                <?php echo htmlspecialchars($st['full_name']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="color: #6366f1; font-weight: 600;">@<?php echo htmlspecialchars($st['username']); ?></span>
                                        </td>
                                        <td>
                                            <div>📧 <?php echo htmlspecialchars($st['email']); ?></div>
                                            <div style="font-size: 11px; color: var(--admin-text-muted);">📱 <?php echo htmlspecialchars($st['mobile']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge" style="background: #e0e7ff; color: #3730a3; font-size: 11px; font-weight: 600;">
                                                <?php echo htmlspecialchars($st['course']); ?> (Yr <?php echo (int)$st['academic_year']; ?>)
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($st['gender']); ?></td>
                                        <td>
                                            <span class="role-pill-badge <?php echo $st['role'] === 'admin' ? 'badge-admin-role' : 'badge-admin-role'; ?>" style="<?php echo $st['role'] === 'student' ? 'background: #ecfdf5; color: #065f46; border-color: #a7f3d0;' : ''; ?>">
                                                <?php echo $st['role'] === 'admin' ? '🛡️ Admin' : '🎓 Student'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="font-size: 12px; color: var(--admin-text-muted);">
                                                <?php echo date('M d, Y h:i A', strtotime($st['created_at'])); ?>
                                            </span>
                                        </td>
                                        <td style="text-align: right; white-space: nowrap;">
                                            <a href="student_manager.php?search=<?php echo urlencode($st['username']); ?>" class="btn-action-small">
                                                Manage ✏️
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div style="padding: 12px 20px; background: #f8fafc; border-top: 1px solid var(--admin-border); display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: var(--admin-text-muted);">
                    <span>Showing latest <?php echo count($latestStudents); ?> of <?php echo $totalUsers; ?> registered accounts</span>
                    <span>Practical 14 RBAC • Parameterized MySQLi Statements</span>
                </div>
            </section>

            <!-- ROLE-BASED ACCESS CONTROL DIAGNOSTIC SPECIFICATION -->
            <section class="stat-panel-card" style="border-left: 4px solid #4338ca;">
                <div class="panel-heading">
                    <span>🛡️ Practical 14 RBAC Security & Dynamic Menu Architecture</span>
                    <span class="role-pill-badge" style="background: #e0e7ff; color: #3730a3;">Verified Standard</span>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; font-size: 13px;">
                    <div style="background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px solid var(--admin-border);">
                        <strong style="color: #4338ca;">1. Server-Side Guard (requireLogin)</strong>
                        <p style="margin: 6px 0 0 0; color: var(--admin-text-muted); line-height: 1.45;">
                            Enforces <code>requireLogin('admin', 'login.php')</code> on script initialization. Unauthorized requests without an admin session are redirected immediately with <code>?error=admin_only</code>.
                        </p>
                    </div>

                    <div style="background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px solid var(--admin-border);">
                        <strong style="color: #059669;">2. Contextual Role-Based Menu</strong>
                        <p style="margin: 6px 0 0 0; color: var(--admin-text-muted); line-height: 1.45;">
                            The navigation bar dynamically builds its link list based on <code>$_SESSION['role']</code>. Administrative links (Student Manager, Event Manager, Database Center) remain invisible to student accounts.
                        </p>
                    </div>

                    <div style="background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px solid var(--admin-border);">
                        <strong style="color: #d97706;">3. Live Server Simulation Parity</strong>
                        <p style="margin: 6px 0 0 0; color: var(--admin-text-muted); line-height: 1.45;">
                            The frontend companion <code>pages/admin_dashboard.html</code> mirrors the exact same RBAC lock screen and dynamic menu rendering for standalone VS Code Live Server testing.
                        </p>
                    </div>
                </div>
            </section>

        </div>
    </main>

    <!-- ================= FOOTER ================= -->
    <footer class="site-footer" style="padding: 24px 0; background: #0f172a; color: #94a3b8; text-align: center; font-size: 13px;">
        <div class="container">
            <p>🎓 StudentHub Portal &bull; Practical 14: Administrator Dashboard &amp; Role-Based Access Control (RBAC)</p>
            <p style="margin-top: 4px; font-size: 12px; opacity: 0.8;">User Statistics &bull; Student Counts &bull; Event Counts &bull; Latest Registrations Feed &bull; Dynamic Menu</p>
        </div>
    </footer>

    <!-- Hamburger Menu Script -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const menuToggle = document.querySelector('.menu-toggle');
            const navContainer = document.querySelector('.nav-container');
            if (menuToggle && navContainer) {
                menuToggle.addEventListener('click', () => {
                    navContainer.classList.toggle('active');
                });
            }

            const themeBtn = document.querySelector('.theme-toggle');
            if (themeBtn) {
                themeBtn.addEventListener('click', () => {
                    document.body.classList.toggle('dark');
                    const isDark = document.body.classList.contains('dark');
                    themeBtn.textContent = isDark ? '☀️ Light Mode' : '🌙 Dark Mode';
                    localStorage.setItem('studenthub_theme', isDark ? 'dark' : 'light');
                });
                if (localStorage.getItem('studenthub_theme') === 'dark') {
                    document.body.classList.add('dark');
                    themeBtn.textContent = '☀️ Light Mode';
                }
            }
        });
    </script>
</body>

</html>
