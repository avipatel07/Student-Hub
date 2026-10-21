<?php
/**
 * ============================================================================
 * StudentHub - Digital Campus Portal
 * PRACTICAL 8: MySQL Schema, ER Model, PDO Connectivity & Prepared Statements
 * ============================================================================
 */

date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/db_connect.php';

// Run connection diagnostics (Both PDO and MySQLi)
$dbDiag = testDBConnection();
$mysqliDiag = testMySQLiConnection();
$pdo = getDBConnection();
$mysqli = getMySQLiConnection();

$actionResult = null;
$actionError = null;

// Handle Live Event Registration Demo (Prepared Statement + Transaction)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_event_demo') {
    if ($pdo instanceof PDO) {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $eventId   = (int)($_POST['event_id'] ?? 0);

        if ($studentId > 0 && $eventId > 0) {
            try {
                // BEGIN TRANSACTION
                $pdo->beginTransaction();

                // 1. Prepared Statement: Check if already registered
                $checkStmt = $pdo->prepare("
                    SELECT registration_id 
                    FROM event_registrations 
                    WHERE student_id = :student_id AND event_id = :event_id
                ");
                $checkStmt->execute([
                    ':student_id' => $studentId,
                    ':event_id'   => $eventId
                ]);

                if ($checkStmt->fetch()) {
                    throw new Exception("Student is already registered for this event (prevented by unique constraint & prepared check).");
                }

                // 2. Prepared Statement: Check seat availability
                $seatStmt = $pdo->prepare("
                    SELECT title, available_seats 
                    FROM events 
                    WHERE event_id = :event_id 
                    FOR UPDATE
                ");
                $seatStmt->execute([':event_id' => $eventId]);
                $eventData = $seatStmt->fetch();

                if (!$eventData) {
                    throw new Exception("Event not found.");
                }

                if ((int)$eventData['available_seats'] <= 0) {
                    throw new Exception("Event is fully booked! No available seats.");
                }

                // 3. Prepared Statement: Insert into event_registrations
                $regCode = 'REG-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
                $insertStmt = $pdo->prepare("
                    INSERT INTO event_registrations 
                        (student_id, event_id, registration_code, attendance_status, remarks) 
                    VALUES 
                        (:student_id, :event_id, :code, 'Confirmed', 'Registered via Practical 8 Prepared Statement Demo')
                ");
                $insertStmt->execute([
                    ':student_id' => $studentId,
                    ':event_id'   => $eventId,
                    ':code'       => $regCode
                ]);

                // 4. Prepared Statement: Decrement available seats
                $updateSeatStmt = $pdo->prepare("
                    UPDATE events 
                    SET available_seats = available_seats - 1 
                    WHERE event_id = :event_id
                ");
                $updateSeatStmt->execute([':event_id' => $eventId]);

                // COMMIT TRANSACTION
                $pdo->commit();

                $actionResult = "Registration Successful! Code: <strong>{$regCode}</strong> for '<strong>" . htmlspecialchars($eventData['title']) . "</strong>'. Available seats decremented atomically using PDO transaction.";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $actionError = $e->getMessage();
            }
        } else {
            $actionError = "Please select both a student and an event.";
        }
    } else {
        $actionError = "Database not connected. Please ensure MySQL is running.";
    }
}

// Demo 1: Prepared Statement for Filtered Student Search
$filterCourse = isset($_GET['course']) ? trim($_GET['course']) : 'All';
$studentsList = [];

if ($pdo instanceof PDO) {
    if ($filterCourse !== 'All' && !empty($filterCourse)) {
        // PREPARED STATEMENT with Named Placeholder
        $searchStmt = $pdo->prepare("
            SELECT student_id, registration_no, full_name, email, mobile, course, academic_year, gender 
            FROM students 
            WHERE course = :course 
            ORDER BY student_id ASC
        ");
        $searchStmt->execute([':course' => $filterCourse]);
        $studentsList = $searchStmt->fetchAll();
    } else {
        $searchStmt = $pdo->prepare("
            SELECT student_id, registration_no, full_name, email, mobile, course, academic_year, gender 
            FROM students 
            ORDER BY student_id ASC
        ");
        $searchStmt->execute();
        $studentsList = $searchStmt->fetchAll();
    }
}

// Demo 2: Prepared Statement with INNER JOIN (Students + Events + Registrations)
$joinList = [];
if ($pdo instanceof PDO) {
    $joinStmt = $pdo->prepare("
        SELECT 
            r.registration_id,
            r.registration_code,
            s.full_name AS student_name,
            s.course,
            e.title AS event_title,
            e.category AS event_category,
            e.event_date,
            r.attendance_status,
            r.registration_date
        FROM event_registrations r
        INNER JOIN students s ON r.student_id = s.student_id
        INNER JOIN events e ON r.event_id = e.event_id
        ORDER BY r.registration_date DESC
    ");
    $joinStmt->execute();
    $joinList = $joinStmt->fetchAll();
}

// Fetch all events for the dropdown
$allEvents = [];
$allStudents = [];
if ($pdo instanceof PDO) {
    $eStmt = $pdo->query("SELECT event_id, title, available_seats FROM events ORDER BY event_id ASC");
    $allEvents = $eStmt->fetchAll();

    $sStmt = $pdo->query("SELECT student_id, full_name, course FROM students ORDER BY student_id ASC");
    $allStudents = $sStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Practical 8: Database & Prepared Statements</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .code-snippet {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 14px 16px;
            border-radius: 8px;
            font-family: Consolas, "Courier New", monospace;
            font-size: 13px;
            overflow-x: auto;
            margin: 12px 0;
            line-height: 1.5;
        }
        .code-keyword { color: #569cd6; font-weight: bold; }
        .code-string { color: #ce9178; }
        .code-comment { color: #6a9955; font-style: italic; }
        .code-var { color: #9cdcfe; }
        .status-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 6px;
        }
        .status-dot.online { background: #10b981; box-shadow: 0 0 8px #10b981; }
        .status-dot.offline { background: #ef4444; box-shadow: 0 0 8px #ef4444; }
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
            <a href="../pages/index.html">Home</a>
            <a href="../pages/about.html">About</a>
            <a href="../pages/register.html">Register</a>
            <a href="../pages/login.html">Login</a>
            <a href="../pages/dashboard.html">Dashboard</a>
            <a href="../pages/events.html">Events</a>
            <a href="../pages/contact.html">Contact</a>
            <a href="view_registrations.php">Records</a>
            <a href="db_dashboard.php" class="active">Practical 8 (Database)</a>
            <a href="../pages/faq.html">FAQ</a>
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
                    <h2>Practical 8: MySQL Schema, ER Model & PDO Prepared Statements</h2>
                    <p>Relational Database Architecture & Secure Parameterized Database Access</p>
                </div>
                <div>
                    <a href="../database/schema.sql" download class="btn btn-small">
                        ⬇️ Download schema.sql
                    </a>
                </div>
            </div>
        </div>

        <!-- ================= 1. PDO CONNECTION DIAGNOSTIC ================= -->
        <section class="summary-card">
            <h3 style="color: #243b53; margin-bottom: 12px; font-size: 19px; display: flex; align-items: center;">
                <?php if ($dbDiag['success']): ?>
                    <span class="status-dot online"></span> PDO Connection Status: Connected
                <?php else: ?>
                    <span class="status-dot offline"></span> PDO Connection Status: Disconnected
                <?php endif; ?>
            </h3>

            <?php if ($dbDiag['success']): ?>
                <div class="alert-box alert-success" style="margin-bottom: 14px;">
                    <div class="alert-title">
                        <span>✅</span> PDO Database Driver Active
                    </div>
                    <p><?php echo htmlspecialchars($dbDiag['message']); ?> Connected to database <strong><?php echo htmlspecialchars($dbDiag['database']); ?></strong> at <strong><?php echo htmlspecialchars($dbDiag['host']); ?></strong> (Latency: <?php echo $dbDiag['latency_ms']; ?> ms).</p>
                </div>

                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-label">MySQL Server Version</div>
                        <div class="summary-value"><?php echo htmlspecialchars($dbDiag['version']); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">PDO Driver</div>
                        <div class="summary-value"><?php echo htmlspecialchars($dbDiag['driver']); ?> (utf8mb4)</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Prepared Statement Mode</div>
                        <div class="summary-value"><span class="badge badge-success">Native Server-Side</span></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">SQL Injection Shield</div>
                        <div class="summary-value"><span class="badge badge-primary">Active (Emulation Disabled)</span></div>
                    </div>
                </div>

                <!-- Table Counts -->
                <div style="margin-top: 14px;">
                    <span style="font-size: 13px; color: #64748b; font-weight: 600;">ACTIVE RELATIONAL TABLES:</span>
                    <div class="storage-tags" style="margin-top: 6px;">
                        <?php foreach ($dbDiag['tables'] as $tbl => $cnt): ?>
                            <span class="storage-tag csv">
                                📊 <strong><?php echo htmlspecialchars($tbl); ?></strong>: <?php echo $cnt; ?> rows
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php else: ?>
                <div class="alert-box alert-warning">
                    <div class="alert-title">
                        <span>ℹ️</span> MySQL Server Offline or Database Not Yet Created
                    </div>
                    <p>MySQL connection cannot be established at <code>localhost:3306</code>. To activate live database operations:</p>
                    <ol style="margin: 10px 0 0 20px;">
                        <li>Open <strong>XAMPP Control Panel</strong> and click <strong>Start</strong> next to <strong>MySQL</strong>.</li>
                        <li>Open <strong>phpMyAdmin</strong> (<a href="http://localhost/phpmyadmin" target="_blank" style="text-decoration: underline;">http://localhost/phpmyadmin</a>).</li>
                        <li>Import the script <a href="../database/schema.sql" download><strong>database/schema.sql</strong></a> to create the <code>studenthub_db</code> database and tables.</li>
                    </ol>
                </div>
            <?php endif; ?>
        </section>

        <!-- ================= MYSQLI PREPARED STATEMENT REGISTRATION SHOWCASE ================= -->
        <section class="summary-card" style="border-left: 4px solid #1677e8;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h3 style="color: #243b53; font-size: 19px; display: flex; align-items: center; gap: 8px;">
                        <span>🔐</span> Secure User Registration Architecture (MYSQLi + password_hash)
                    </h3>
                    <p style="color: #64748b; font-size: 13px;">
                        Implemented in <code>php/register_process.php</code> using parameterized MySQLi statements and native Bcrypt hashing.
                    </p>
                </div>
                <div>
                    <a href="../pages/register.html" class="btn btn-small">
                        + Test Registration Form
                    </a>
                </div>
            </div>

            <div class="summary-grid" style="margin-top: 14px;">
                <div class="summary-item">
                    <div class="summary-label">Database Extension</div>
                    <div class="summary-value">MySQLi (MySQL Improved)</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Duplicate Prevention</div>
                    <div class="summary-value">Prepared SELECT on Email &amp; Username</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Password Hashing</div>
                    <div class="summary-value"><code>password_hash(PASSWORD_DEFAULT)</code></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Injection Defense</div>
                    <div class="summary-value"><span class="badge badge-success">100% Parameterized (bind_param)</span></div>
                </div>
            </div>

            <!-- Code Architecture Snippet -->
            <div class="code-snippet" style="margin-top: 14px;">
// 1. MySQLi Parameterized Duplicate Check (Email &amp; Username)
$stmt = $mysqli->prepare("SELECT student_id FROM students WHERE username = ? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) { $errors['username'] = "Username is already taken."; }

// 2. Cryptographic Password Hashing (Bcrypt)
$passwordHash = password_hash($studentPassword, PASSWORD_DEFAULT);

// 3. MySQLi Parameterized INSERT Query
$insertSql = "INSERT INTO students 
    (registration_no, username, full_name, email, mobile, password_hash, course, academic_year, gender, terms_accepted, created_at) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $mysqli->prepare($insertSql);
$stmt->bind_param("sssssssisss", $regId, $username, $name, $email, $mobile, $passwordHash, $course, $year, $gender, $terms, $date);
$stmt->execute();
            </div>
        </section>

        <!-- ================= 2. LIVE PREPARED STATEMENTS DEMO 1 ================= -->
        <section class="summary-card">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h3 style="color: #243b53; font-size: 18px;">
                        🔍 Demo 1: Parameterized Prepared SELECT (Filter Students by Course)
                    </h3>
                    <p style="color: #64748b; font-size: 13px;">
                        Demonstrates <code>$stmt = $pdo-&gt;prepare(...)</code> with named parameter binding <code>:course</code> to eliminate SQL Injection.
                    </p>
                </div>
                <div>
                    <!-- Filter form -->
                    <form method="GET" style="display: flex; gap: 8px;">
                        <select name="course" style="width: auto; padding: 7px 12px; font-size: 13px;">
                            <option value="All" <?php echo $filterCourse === 'All' ? 'selected' : ''; ?>>All Courses</option>
                            <option value="B.Tech IT" <?php echo $filterCourse === 'B.Tech IT' ? 'selected' : ''; ?>>B.Tech IT</option>
                            <option value="B.Tech CSE" <?php echo $filterCourse === 'B.Tech CSE' ? 'selected' : ''; ?>>B.Tech CSE</option>
                            <option value="B.Tech CE" <?php echo $filterCourse === 'B.Tech CE' ? 'selected' : ''; ?>>B.Tech CE</option>
                            <option value="BCA" <?php echo $filterCourse === 'BCA' ? 'selected' : ''; ?>>BCA</option>
                            <option value="MCA" <?php echo $filterCourse === 'MCA' ? 'selected' : ''; ?>>MCA</option>
                        </select>
                        <button type="submit" class="btn btn-small">Execute Prepared Query</button>
                    </form>
                </div>
            </div>

            <!-- Prepared Statement Code Snippet -->
            <div class="code-snippet">
<span class="code-comment">// 1. Prepare SQL statement with named placeholder</span>
<span class="code-var">$stmt</span> = <span class="code-var">$pdo</span>-&gt;<span class="code-keyword">prepare</span>(<span class="code-string">"SELECT student_id, registration_no, full_name, email, course, academic_year FROM students WHERE course = :course"</span>);

<span class="code-comment">// 2. Execute with sanitized, bound parameter array</span>
<span class="code-var">$stmt</span>-&gt;<span class="code-keyword">execute</span>([<span class="code-string">':course'</span> =&gt; <span class="code-var">$filterCourse</span>]);
<span class="code-var">$students</span> = <span class="code-var">$stmt</span>-&gt;<span class="code-keyword">fetchAll</span>();
            </div>

            <!-- Query Output -->
            <?php if (!empty($studentsList)): ?>
                <div class="table-responsive" style="margin-top: 10px;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Registration No</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Mobile</th>
                                <th>Course</th>
                                <th>Academic Year</th>
                                <th>Gender</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($studentsList as $stu): ?>
                                <tr>
                                    <td><?php echo $stu['student_id']; ?></td>
                                    <td><span class="badge badge-primary"><?php echo htmlspecialchars($stu['registration_no']); ?></span></td>
                                    <td><strong><?php echo htmlspecialchars($stu['full_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($stu['email']); ?></td>
                                    <td><?php echo htmlspecialchars($stu['mobile']); ?></td>
                                    <td><?php echo htmlspecialchars($stu['course']); ?></td>
                                    <td>Year <?php echo htmlspecialchars($stu['academic_year']); ?></td>
                                    <td><?php echo htmlspecialchars($stu['gender']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="color: #64748b; font-size: 14px; margin-top: 10px;"><em>(Connect MySQL and import schema.sql to view live query results)</em></p>
            <?php endif; ?>
        </section>

        <!-- ================= 3. LIVE PREPARED STATEMENTS DEMO 2 ================= -->
        <section class="summary-card">
            <h3 style="color: #243b53; font-size: 18px; margin-bottom: 6px;">
                🔗 Demo 2: Relational INNER JOIN Prepared Statement
            </h3>
            <p style="color: #64748b; font-size: 13px;">
                Demonstrates multi-table join across <code>students</code>, <code>events</code>, and the associative entity <code>event_registrations</code>.
            </p>

            <div class="code-snippet">
<span class="code-var">$stmt</span> = <span class="code-var">$pdo</span>-&gt;<span class="code-keyword">prepare</span>(<span class="code-string">"
    SELECT r.registration_code, s.full_name, s.course, e.title, e.event_date, r.attendance_status 
    FROM event_registrations r
    INNER JOIN students s ON r.student_id = s.student_id
    INNER JOIN events e ON r.event_id = e.event_id
    ORDER BY r.registration_date DESC
"</span>);
<span class="code-var">$stmt</span>-&gt;<span class="code-keyword">execute</span>();
            </div>

            <?php if (!empty($joinList)): ?>
                <div class="table-responsive" style="margin-top: 10px;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Ticket Code</th>
                                <th>Student Name</th>
                                <th>Course</th>
                                <th>Event Title</th>
                                <th>Category</th>
                                <th>Event Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($joinList as $row): ?>
                                <tr>
                                    <td><span class="badge badge-primary"><?php echo htmlspecialchars($row['registration_code']); ?></span></td>
                                    <td><strong><?php echo htmlspecialchars($row['student_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['course']); ?></td>
                                    <td><?php echo htmlspecialchars($row['event_title']); ?></td>
                                    <td><?php echo htmlspecialchars($row['event_category']); ?></td>
                                    <td><?php echo htmlspecialchars($row['event_date']); ?></td>
                                    <td><span class="badge badge-success"><?php echo htmlspecialchars($row['attendance_status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="color: #64748b; font-size: 14px; margin-top: 10px;"><em>(Connect MySQL to view live relational join results)</em></p>
            <?php endif; ?>
        </section>

        <!-- ================= 4. LIVE PREPARED STATEMENTS DEMO 3 ================= -->
        <section class="summary-card">
            <h3 style="color: #243b53; font-size: 18px; margin-bottom: 6px;">
                ⚡ Demo 3: Atomic Transaction & Prepared INSERT Demo
            </h3>
            <p style="color: #64748b; font-size: 13px;">
                Demonstrates <code>beginTransaction()</code>, prepared check, prepared insert, prepared decrement of available seats, and <code>commit()</code>.
            </p>

            <?php if ($actionResult): ?>
                <div class="alert-box alert-success" style="margin: 12px 0;">
                    <?php echo $actionResult; ?>
                </div>
            <?php endif; ?>

            <?php if ($actionError): ?>
                <div class="alert-box alert-danger" style="margin: 12px 0;">
                    ⚠️ <?php echo htmlspecialchars($actionError); ?>
                </div>
            <?php endif; ?>

            <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; margin-top: 14px;">
                <input type="hidden" name="action" value="register_event_demo">

                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px; color: #243b53;">
                        Select Registered Student:
                    </label>
                    <select name="student_id" required>
                        <option value="">-- Choose Student --</option>
                        <?php foreach ($allStudents as $st): ?>
                            <option value="<?php echo $st['student_id']; ?>">
                                <?php echo htmlspecialchars($st['full_name']); ?> (<?php echo htmlspecialchars($st['course']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px; color: #243b53;">
                        Select Campus Event:
                    </label>
                    <select name="event_id" required>
                        <option value="">-- Choose Event --</option>
                        <?php foreach ($allEvents as $ev): ?>
                            <option value="<?php echo $ev['event_id']; ?>">
                                <?php echo htmlspecialchars($ev['title']); ?> (<?php echo $ev['available_seats']; ?> seats left)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: flex; align-items: flex-end;">
                    <button type="submit" class="btn" style="width: 100%;">
                        📝 Execute Prepared Registration Transaction
                    </button>
                </div>
            </form>
        </section>

        <!-- ================= 5. ER MODEL SUMMARY ================= -->
        <section class="summary-card">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h3 style="color: #243b53; font-size: 18px;">
                        📐 Entity-Relationship (ER) Architecture
                    </h3>
                    <p style="color: #64748b; font-size: 13px;">
                        Complete documentation available in <a href="../docs/ER_MODEL.md" target="_blank" style="text-decoration: underline;"><strong>docs/ER_MODEL.md</strong></a>.
                    </p>
                </div>
                <div>
                    <a href="../docs/ER_MODEL.md" target="_blank" class="btn btn-secondary btn-small">
                        View ER Model Spec
                    </a>
                </div>
            </div>

            <div class="summary-grid" style="margin-top: 16px;">
                <div class="summary-item">
                    <div class="summary-label">Entity 1</div>
                    <div class="summary-value"><code>students</code> (1)</div>
                    <p style="font-size: 12px; color: #64748b; margin-top: 4px;">PK: student_id | Unique: registration_no, email, mobile</p>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Entity 2</div>
                    <div class="summary-value"><code>events</code> (1)</div>
                    <p style="font-size: 12px; color: #64748b; margin-top: 4px;">PK: event_id | Capacity & seat tracking</p>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Associative Entity (Junction)</div>
                    <div class="summary-value"><code>event_registrations</code> (M:N)</div>
                    <p style="font-size: 12px; color: #64748b; margin-top: 4px;">FK: student_id, event_id | ON DELETE CASCADE</p>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Entity 4</div>
                    <div class="summary-value"><code>contact_inquiries</code></div>
                    <p style="font-size: 12px; color: #64748b; margin-top: 4px;">PK: inquiry_id | Helpdesk inquiries storage</p>
                </div>
            </div>
        </section>

    </div>
</main>

<!-- ================= FOOTER ================= -->
<footer>
    <p>© 2026 StudentHub | Digital Campus Portal</p>
    <div class="footer-links">
        <a href="../pages/index.html">Home</a>
        <a href="../pages/register.html">Register</a>
        <a href="view_registrations.php">CSV/JSON Records</a>
        <a href="db_dashboard.php">Practical 8 Database</a>
        <a href="../pages/contact.html">Contact</a>
    </div>
</footer>

<script src="../js/script.js"></script>

</body>
</html>
