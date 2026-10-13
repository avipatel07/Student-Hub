<?php
/**
 * ============================================================================
 * StudentHub - Digital Campus Portal
 * PRACTICAL 7: View Stored Student Registrations (CSV & JSON)
 * ============================================================================
 */

date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/db_connect.php';

$csvFile = __DIR__ . '/../data/registrations.csv';
$jsonFile = __DIR__ . '/../data/registrations.json';

// Check database count if available
$dbCount = 0;
$pdo = getDBConnection();
if ($pdo instanceof PDO) {
    try {
        $dbCountStmt = $pdo->query("SELECT COUNT(*) FROM students");
        $dbCount = (int)$dbCountStmt->fetchColumn();
    } catch (Exception $e) {}
}

// Handle download CSV
if (isset($_GET['action']) && $_GET['action'] === 'download_csv') {
    if (file_exists($csvFile)) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="studenthub_registrations.csv"');
        readfile($csvFile);
        exit;
    }
}

// Handle raw JSON view
if (isset($_GET['action']) && $_GET['action'] === 'view_json') {
    if (file_exists($jsonFile)) {
        header('Content-Type: application/json');
        readfile($jsonFile);
        exit;
    }
}

// Read CSV data
$csvRecords = [];
$csvHeader = [];
if (file_exists($csvFile)) {
    $fp = fopen($csvFile, 'r');
    if ($fp !== false) {
        $header = fgetcsv($fp);
        if ($header !== false) {
            $csvHeader = $header;
        }
        while (($row = fgetcsv($fp)) !== false) {
            if (!empty($row[0])) {
                $csvRecords[] = $row;
            }
        }
        fclose($fp);
    }
}

// Read JSON data
$jsonRecords = [];
if (file_exists($jsonFile)) {
    $content = file_get_contents($jsonFile);
    if (!empty($content)) {
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            $jsonRecords = $decoded;
        }
    }
}

$totalStudents = count($csvRecords);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Registered Students Records</title>
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
            <a href="../pages/index.html">Home</a>
            <a href="../pages/about.html">About</a>
            <a href="../pages/register.html">Register</a>
            <a href="../pages/login.html">Login</a>
            <a href="../pages/dashboard.html">Dashboard</a>
            <a href="../pages/events.html">Events</a>
            <a href="../pages/profile.html">Profile</a>
            <a href="../pages/contact.html">Contact</a>
            <a href="../pages/admin.html">Admin</a>
            <a href="db_dashboard.php">Practical 8 (DB)</a>
            <a href="../pages/faq.html">FAQ</a>
            <a href="../pages/feedback.html">Feedback</a>
        </div>

        <button class="theme-toggle" aria-label="Toggle dark mode">
            🌙
        </button>
    </div>
</nav>

<!-- ================= MAIN ================= -->
<main>
    <div class="container">

        <!-- Page Heading -->
        <div class="page-title">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h2>Stored Student Registrations</h2>
                    <p>Live Records Stored via PHP in CSV & JSON File Formats (Practical 7) & MySQL (Practical 8)</p>
                </div>
                <div>
                    <span class="badge badge-primary" style="font-size: 15px; padding: 7px 14px;">
                        Total Registered: <?php echo $totalStudents; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Controls / Actions -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 20px;">
            <div class="storage-tags">
                <span class="storage-tag csv">
                    <span>📁</span> File: data/registrations.csv (<?php echo count($csvRecords); ?> records)
                </span>
                <span class="storage-tag json">
                    <span>📦</span> File: data/registrations.json (<?php echo count($jsonRecords); ?> records)
                </span>
                <a href="db_dashboard.php" class="storage-tag csv" style="text-decoration: none; background: #e0f2fe; border-color: #7dd3fc; color: #0369a1;">
                    <span>💾</span> MySQL Database (<?php echo $dbCount; ?> in `students`)
                </a>
            </div>

            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <a href="db_dashboard.php" class="btn btn-secondary btn-small">
                    🗄️ Practical 8 DB
                </a>
                <a href="?action=download_csv" class="btn btn-secondary btn-small">
                    ⬇️ Download CSV
                </a>
                <a href="?action=view_json" target="_blank" class="btn btn-secondary btn-small">
                    🔍 View Raw JSON
                </a>
                <a href="../pages/register.html" class="btn btn-small">
                    + Register Student
                </a>
            </div>
        </div>

        <!-- Table Display -->
        <section class="summary-card" style="padding: 16px;">
            <?php if ($totalStudents === 0): ?>
                <div class="alert-box alert-warning">
                    <div class="alert-title">No Registrations Found</div>
                    <p>No student registrations have been recorded yet. Submit the registration form to add records.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive" style="margin: 0;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Reg. ID</th>
                                <th>Student Name</th>
                                <th>Email</th>
                                <th>Mobile</th>
                                <th>Course</th>
                                <th>Year</th>
                                <th>Gender</th>
                                <th>Status</th>
                                <th>Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($csvRecords as $index => $row): ?>
                                <tr>
                                    <td><strong><?php echo $index + 1; ?></strong></td>
                                    <td>
                                        <span class="badge badge-primary">
                                            <?php echo htmlspecialchars($row[0] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($row[1] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row[2] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($row[3] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($row[5] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>Year <?php echo htmlspecialchars($row[6] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($row[7] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <span class="badge badge-success">
                                            <?php echo htmlspecialchars($row[8] ?? 'Accepted', ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td style="font-size: 13px; color: #64748b; white-space: nowrap;">
                                        <?php echo htmlspecialchars($row[9] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- Back to Actions -->
        <div style="margin-top: 25px; display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="../pages/register.html" class="btn">
                ← Go to Registration Form
            </a>
            <a href="../pages/dashboard.html" class="btn btn-secondary">
                Student Dashboard
            </a>
            <a href="../pages/contact.html" class="btn btn-secondary">
                Contact Page
            </a>
        </div>

    </div>
</main>

<!-- ================= FOOTER ================= -->
<footer>
    <p>© 2026 StudentHub | Digital Campus Portal</p>
    <div class="footer-links">
        <a href="../pages/index.html">Home</a>
        <a href="../pages/about.html">About</a>
        <a href="../pages/register.html">Register</a>
        <a href="view_registrations.php">Records</a>
        <a href="../pages/contact.html">Contact</a>
    </div>
</footer>

<script src="../js/script.js"></script>

</body>
</html>
