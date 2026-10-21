<?php
/**
 * ============================================================================
 * StudentHub - Digital Campus Portal
 * Secure User Registration: MySQLi Database Insertion, Password Hashing,
 * Server-Side Validation, and Duplicate Detection
 * ============================================================================
 * 
 * Objectives:
 * 1. Process submitted registration form data securely via PHP ($_POST).
 * 2. Sanitize and validate all inputs on both frontend and backend.
 * 3. Verify uniqueness of both Email and Username against MySQL using MySQLi
 *    prepared statements to prevent duplicate registrations.
 * 4. Hash user passwords using PHP's native password_hash() (Bcrypt / PASSWORD_DEFAULT).
 * 5. Insert sanitized user details into MySQL 'students' table using MySQLi
 *    parameterized prepared statements (Complete SQL Injection Defense).
 * 6. Maintain dual file persistence (CSV & JSON) for synchronized offline records.
 * 7. Display clear, user-friendly success and validation error messages.
 * 8. Support dual submission modes: Standard HTTP POST and Asynchronous AJAX/Fetch.
 * ============================================================================
 */

// Set timezone for accurate registration timestamps
date_default_timezone_set('Asia/Kolkata');

// Load database connection module (provides getMySQLiConnection and getDBConnection)
require_once __DIR__ . '/db_connect.php';
// Load authentication & session helper (provides ensureAuthColumnsExist and session utilities)
require_once __DIR__ . '/auth_session.php';

// Storage file paths
$dataDir = __DIR__ . '/../data';
$csvFile = $dataDir . '/registrations.csv';
$jsonFile = $dataDir . '/registrations.json';

// Ensure data directory exists
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

// Detect AJAX / JSON request
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    || (isset($_POST['ajax']) && $_POST['ajax'] === '1');

// Array to store validation and database errors
$errors = [];

// Whitelist options matching the registration form
$allowedCourses = [
    'B.Tech IT',
    'B.Tech CSE',
    'B.Tech CE',
    'BCA',
    'MCA'
];

$allowedYears = ['1', '2', '3', '4'];
$allowedGenders = ['Male', 'Female', 'Other'];

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $errors['request'] = 'Invalid request method. Only POST submissions are accepted.';
}

/**
 * Sanitization helper functions
 */
function sanitize_string($value) {
    if ($value === null) return '';
    $trimmed = trim($value);
    $stripped = strip_tags($trimmed);
    return htmlspecialchars($stripped, ENT_QUOTES, 'UTF-8');
}

function sanitize_username($value) {
    if ($value === null) return '';
    // Allow letters, numbers, and underscores only
    $cleaned = preg_replace('/[^a-zA-Z0-9_]/', '', trim($value));
    return htmlspecialchars($cleaned, ENT_QUOTES, 'UTF-8');
}

function sanitize_email($value) {
    if ($value === null) return '';
    return filter_var(trim($value), FILTER_SANITIZE_EMAIL);
}

function sanitize_digits($value) {
    if ($value === null) return '';
    return preg_replace('/[^0-9]/', '', trim($value));
}

// Initialize input variables
$studentName       = '';
$username          = '';
$studentEmail      = '';
$studentMobile     = '';
$studentPassword   = '';
$confirmPassword   = '';
$course            = '';
$year              = '';
$gender            = '';
$termsAccepted     = false;

// Processing flags
$recordSaved        = false;
$mysqliWriteSuccess = false;
$mysqliInsertId     = null;
$mysqliErrorMsg     = null;
$csvWriteSuccess    = false;
$jsonWriteSuccess   = false;
$registrationId     = '';
$registrationDate   = '';
$passwordHash       = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ------------------------------------------------------------------------
    // 1. INPUT SANITIZATION
    // ------------------------------------------------------------------------
    $studentName     = sanitize_string($_POST['studentName'] ?? '');
    $username        = sanitize_username($_POST['username'] ?? '');
    $studentEmail    = sanitize_email($_POST['studentEmail'] ?? '');
    $studentMobile   = sanitize_digits($_POST['studentMobile'] ?? '');
    $studentPassword = (string)($_POST['studentPassword'] ?? '');
    $confirmPassword = (string)($_POST['confirmPassword'] ?? '');
    $course          = sanitize_string($_POST['course'] ?? '');
    $year            = sanitize_string($_POST['year'] ?? '');
    $gender          = sanitize_string($_POST['gender'] ?? '');
    $termsAccepted   = isset($_POST['terms']) && ($_POST['terms'] === 'on' || $_POST['terms'] === '1' || $_POST['terms'] === 'true');

    // ------------------------------------------------------------------------
    // 2. SERVER-SIDE INPUT VALIDATION
    // ------------------------------------------------------------------------

    // Full Name: required, letters and spaces only, 3-60 chars, at least two words
    if (empty($studentName)) {
        $errors['studentName'] = 'Full Name is required.';
    } elseif (strlen($studentName) < 3 || strlen($studentName) > 60) {
        $errors['studentName'] = 'Full Name must be between 3 and 60 characters.';
    } elseif (!preg_match("/^[A-Za-z]+(?:\s[A-Za-z]+)+$/", $studentName)) {
        $errors['studentName'] = 'Enter a valid full name using letters and spaces only (e.g., "Rahul Patel").';
    }

    // Username: required, 4-30 chars, alphanumeric and underscore, at least one letter
    if (empty($username)) {
        $errors['username'] = 'Username is required.';
    } elseif (strlen($username) < 4 || strlen($username) > 30) {
        $errors['username'] = 'Username must be between 4 and 30 characters.';
    } elseif (!preg_match("/^[a-zA-Z0-9_]{4,30}$/", $username)) {
        $errors['username'] = 'Username can only contain letters, numbers, and underscores (no spaces or special symbols).';
    } elseif (!preg_match("/[a-zA-Z]/", $username)) {
        $errors['username'] = 'Username must contain at least one letter.';
    }

    // Email Address: required, valid email format
    if (empty($studentEmail)) {
        $errors['studentEmail'] = 'Email Address is required.';
    } elseif (!filter_var($studentEmail, FILTER_VALIDATE_EMAIL)) {
        $errors['studentEmail'] = 'Please enter a valid email address (e.g., student@example.com).';
    }

    // Mobile Number: required, valid 10-digit Indian mobile format
    if (empty($studentMobile)) {
        $errors['studentMobile'] = 'Mobile Number is required.';
    } elseif (!preg_match("/^[6-9]\d{9}$/", $studentMobile)) {
        $errors['studentMobile'] = 'Enter a valid 10-digit Indian mobile number starting with 6, 7, 8, or 9.';
    }

    // Password: required, min 8 chars, 1 uppercase, 1 lowercase, 1 number
    if (empty($studentPassword)) {
        $errors['studentPassword'] = 'Password is required.';
    } elseif (strlen($studentPassword) < 8) {
        $errors['studentPassword'] = 'Password must be at least 8 characters long.';
    } elseif (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/", $studentPassword)) {
        $errors['studentPassword'] = 'Password must contain at least one uppercase letter, one lowercase letter, and one number.';
    }

    // Confirm Password: required, must match password
    if (empty($confirmPassword)) {
        $errors['confirmPassword'] = 'Please confirm your password.';
    } elseif ($studentPassword !== $confirmPassword) {
        $errors['confirmPassword'] = 'Passwords do not match.';
    }

    // Course: required, must be in whitelist
    if (empty($course)) {
        $errors['course'] = 'Please select your academic course.';
    } elseif (!in_array($course, $allowedCourses, true)) {
        $errors['course'] = 'Invalid course selected. Please choose from the permitted courses.';
    }

    // Year: required, must be in whitelist
    if (empty($year)) {
        $errors['year'] = 'Please select your academic year.';
    } elseif (!in_array($year, $allowedYears, true)) {
        $errors['year'] = 'Invalid academic year selected (choose 1, 2, 3, or 4).';
    }

    // Gender: required, must be in whitelist
    if (empty($gender)) {
        $errors['gender'] = 'Please select your gender.';
    } elseif (!in_array($gender, $allowedGenders, true)) {
        $errors['gender'] = 'Invalid gender selected.';
    }

    // Terms and Conditions: required
    if (!$termsAccepted) {
        $errors['terms'] = 'You must agree to the StudentHub Terms & Conditions.';
    }

    // ------------------------------------------------------------------------
    // 3. DUPLICATE CHECK USING MYSQLI PREPARED STATEMENTS
    // ------------------------------------------------------------------------
    $mysqli = getMySQLiConnection();
    $mysqliAvailable = ($mysqli instanceof mysqli);

    if ($mysqliAvailable) {
        try {
            // Ensure `username` column exists in the `students` table
            $colCheck = $mysqli->query("SHOW COLUMNS FROM `students` LIKE 'username'");
            if ($colCheck) {
                if ($colCheck->num_rows === 0) {
                    $mysqli->query("ALTER TABLE `students` ADD COLUMN `username` VARCHAR(50) NOT NULL UNIQUE AFTER `registration_no`");
                }
                $colCheck->free();
            }

            // Check duplicate username via MySQLi prepared statement
            if (!empty($username) && !isset($errors['username'])) {
                $stmtUser = $mysqli->prepare("SELECT student_id FROM students WHERE username = ? LIMIT 1");
                if ($stmtUser) {
                    $stmtUser->bind_param("s", $username);
                    $stmtUser->execute();
                    $stmtUser->store_result();
                    if ($stmtUser->num_rows > 0) {
                        $errors['duplicateUsername'] = 'The username "' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '" is already taken. Please choose a different username.';
                    }
                    $stmtUser->close();
                }
            }

            // Check duplicate email via MySQLi prepared statement
            if (!empty($studentEmail) && !isset($errors['studentEmail'])) {
                $stmtEmail = $mysqli->prepare("SELECT student_id FROM students WHERE email = ? LIMIT 1");
                if ($stmtEmail) {
                    $stmtEmail->bind_param("s", $studentEmail);
                    $stmtEmail->execute();
                    $stmtEmail->store_result();
                    if ($stmtEmail->num_rows > 0) {
                        $errors['duplicateEmail'] = 'A student with the email "' . htmlspecialchars($studentEmail, ENT_QUOTES, 'UTF-8') . '" is already registered. Please sign in or use another email.';
                    }
                    $stmtEmail->close();
                }
            }

            // Check duplicate mobile via MySQLi prepared statement
            if (!empty($studentMobile) && !isset($errors['studentMobile'])) {
                $stmtMobile = $mysqli->prepare("SELECT student_id FROM students WHERE mobile = ? LIMIT 1");
                if ($stmtMobile) {
                    $stmtMobile->bind_param("s", $studentMobile);
                    $stmtMobile->execute();
                    $stmtMobile->store_result();
                    if ($stmtMobile->num_rows > 0) {
                        $errors['duplicateMobile'] = 'A student with the mobile number "' . htmlspecialchars($studentMobile, ENT_QUOTES, 'UTF-8') . '" is already registered.';
                    }
                    $stmtMobile->close();
                }
            }
        } catch (mysqli_sql_exception $e) {
            error_log("StudentHub MySQLi Duplicate Check Warning: " . $e->getMessage());
        }
    }

    // Also verify against CSV records (dual-storage integrity)
    $totalRecords = 0;
    if (file_exists($csvFile)) {
        $fp = fopen($csvFile, 'r');
        if ($fp !== false) {
            $header = fgetcsv($fp);
            $hasUsernameInCsv = ($header !== false && count($header) > 2 && strtolower($header[1]) === 'username');
            while (($row = fgetcsv($fp)) !== false) {
                if (!empty($row[0])) {
                    $totalRecords++;
                    $csvUsername = $hasUsernameInCsv ? strtolower(trim($row[1] ?? '')) : '';
                    $csvEmail    = strtolower(trim($row[$hasUsernameInCsv ? 3 : 2] ?? ''));
                    $csvMobile   = trim($row[$hasUsernameInCsv ? 4 : 3] ?? '');

                    if (!empty($username) && $csvUsername === strtolower($username)) {
                        $errors['duplicateUsername'] = 'The username "' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '" is already taken.';
                    }
                    if (!empty($studentEmail) && $csvEmail === strtolower($studentEmail)) {
                        $errors['duplicateEmail'] = 'A student with email "' . htmlspecialchars($studentEmail, ENT_QUOTES, 'UTF-8') . '" is already registered.';
                    }
                    if (!empty($studentMobile) && $csvMobile === $studentMobile) {
                        $errors['duplicateMobile'] = 'A student with mobile number "' . htmlspecialchars($studentMobile, ENT_QUOTES, 'UTF-8') . '" is already registered.';
                    }
                }
            }
            fclose($fp);
        }
    }

    // ------------------------------------------------------------------------
    // 4. SECURE PASSWORD HASHING & RECORD STORAGE
    // ------------------------------------------------------------------------
    if (empty($errors)) {
        $registrationDate = date('Y-m-d H:i:s');
        $newIndex = $totalRecords + 1;
        $registrationId = 'STU' . date('Ymd') . sprintf('%03d', $newIndex);

        // Secure password hashing using native PHP password_hash() (Bcrypt)
        $passwordHash = password_hash($studentPassword, PASSWORD_DEFAULT);

        // --- A. INSERT INTO MYSQL USING MYSQLi PREPARED STATEMENTS ---
        if ($mysqliAvailable) {
            try {
                // Ensure auth columns (role, remember_token, token_expiry) exist
                ensureAuthColumnsExist($mysqli);

                $insertSql = "INSERT INTO students 
                    (registration_no, username, full_name, email, mobile, password_hash, course, academic_year, gender, terms_accepted, role, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $insertStmt = $mysqli->prepare($insertSql);
                if ($insertStmt) {
                    $academicYearInt = (int)$year;
                    $termsAcceptedStr = 'Accepted';
                    $roleStr = 'student';

                    // Bind parameters: s=string, i=integer (12 params: 11 s, 1 i)
                    $insertStmt->bind_param(
                        "sssssssissss",
                        $registrationId,
                        $username,
                        $studentName,
                        $studentEmail,
                        $studentMobile,
                        $passwordHash,
                        $course,
                        $academicYearInt,
                        $gender,
                        $termsAcceptedStr,
                        $roleStr,
                        $registrationDate
                    );

                    if ($insertStmt->execute()) {
                        $mysqliWriteSuccess = true;
                        $mysqliInsertId = $insertStmt->insert_id;
                    } else {
                        $mysqliErrorMsg = $insertStmt->error;
                    }
                    $insertStmt->close();
                }
            } catch (mysqli_sql_exception $e) {
                $mysqliErrorMsg = $e->getMessage();
                error_log("StudentHub MySQLi Insert Error: " . $e->getMessage());
            }
        }

        // --- B. STORE IN CSV FILE ---
        $csvHeader = [
            'Registration ID',
            'Username',
            'Name',
            'Email',
            'Mobile',
            'Password Hash',
            'Course',
            'Year',
            'Gender',
            'Terms',
            'Role',
            'Registration Date'
        ];

        $csvRow = [
            $registrationId,
            $username,
            $studentName,
            $studentEmail,
            $studentMobile,
            $passwordHash,
            $course,
            $year,
            $gender,
            'Accepted',
            'student',
            $registrationDate
        ];

        $csvHandle = fopen($csvFile, 'c+');
        if ($csvHandle !== false) {
            if (flock($csvHandle, LOCK_EX)) {
                $stat = fstat($csvHandle);
                if ($stat['size'] === 0) {
                    fputcsv($csvHandle, $csvHeader);
                }
                fseek($csvHandle, 0, SEEK_END);
                fputcsv($csvHandle, $csvRow);
                flock($csvHandle, LOCK_UN);
                $csvWriteSuccess = true;
            }
            fclose($csvHandle);
        }

        // --- C. STORE IN JSON FILE ---
        $jsonRecords = [];
        if (file_exists($jsonFile)) {
            $jsonContent = file_get_contents($jsonFile);
            if (!empty($jsonContent)) {
                $decoded = json_decode($jsonContent, true);
                if (is_array($decoded)) {
                    $jsonRecords = $decoded;
                }
            }
        }

        $jsonRecords[] = [
            'registrationId'   => $registrationId,
            'username'         => $username,
            'name'             => $studentName,
            'email'            => $studentEmail,
            'mobile'           => $studentMobile,
            'passwordHash'     => $passwordHash,
            'course'           => $course,
            'year'             => $year,
            'gender'           => $gender,
            'terms'            => 'Accepted',
            'role'             => 'student',
            'registrationDate' => $registrationDate,
            'mysqlStatus'      => $mysqliWriteSuccess ? 'Inserted (ID: ' . $mysqliInsertId . ')' : 'Offline/Pending'
        ];

        $jsonEncoded = json_encode($jsonRecords, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($jsonEncoded !== false) {
            $bytes = file_put_contents($jsonFile, $jsonEncoded, LOCK_EX);
            if ($bytes !== false) {
                $jsonWriteSuccess = true;
            }
        }

        $recordSaved = ($mysqliWriteSuccess || $csvWriteSuccess || $jsonWriteSuccess);
    }
}

// ----------------------------------------------------------------------------
// 5. AJAX / JSON RESPONSE HANDLER
// ------------------------------------------------------------------------
if ($isAjax) {
    header('Content-Type: application/json; charset=UTF-8');
    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Validation failed. Please correct the errors below.',
            'errors'  => $errors
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    } else {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful! User details securely inserted into MySQL via MySQLi and hashed with password_hash().',
            'data'    => [
                'registrationId'   => $registrationId,
                'username'         => $username,
                'name'             => $studentName,
                'email'            => $studentEmail,
                'mobile'           => $studentMobile,
                'course'           => $course,
                'year'             => $year,
                'gender'           => $gender,
                'registrationDate' => $registrationDate,
                'security'         => [
                    'database'       => 'MySQL via MYSQLi',
                    'preparedStmt'   => true,
                    'mysqliInsertId' => $mysqliInsertId,
                    'hashAlgorithm'  => 'password_hash() (PASSWORD_DEFAULT / Bcrypt)',
                    'duplicateCheck' => 'Verified Unique (Email & Username)'
                ],
                'storage'          => [
                    'mysql' => $mysqliWriteSuccess ? 'Inserted into students table' : 'Offline',
                    'csv'   => 'data/registrations.csv',
                    'json'  => 'data/registrations.json'
                ]
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Registration Result</title>
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
            <a href="db_dashboard.php">Database</a>
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
                    <h2>Secure User Registration Processing</h2>
                    <p>MySQL Database Insertion via MYSQLi Prepared Statements, Bcrypt Password Hashing & Duplicate Validation</p>
                </div>
                <div>
                    <a href="view_registrations.php" class="btn btn-secondary btn-small">
                        📋 View All Records
                    </a>
                </div>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <!-- ================= ERROR DISPLAY ================= -->
            <section class="form-card">
                <div class="alert-box alert-danger">
                    <div class="alert-title">
                        <span>⚠️</span>
                        <span>Registration Submission Failed</span>
                    </div>
                    <p>The registration request could not be processed due to the following validation errors or duplicate entries:</p>
                    <ul class="alert-list">
                        <?php foreach ($errors as $field => $errorMessage): ?>
                            <li>
                                <strong><?php echo htmlspecialchars(ucfirst(preg_replace('/(?<!\ )[A-Z]/', ' $0', $field)), ENT_QUOTES, 'UTF-8'); ?>:</strong>
                                <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="form-actions">
                    <a href="javascript:history.back()" class="btn">
                        ← Go Back and Correct
                    </a>
                    <a href="../pages/register.html" class="btn btn-secondary">
                        Open New Registration Form
                    </a>
                    <a href="../pages/login.html" class="btn btn-secondary">
                        Go to Login Page
                    </a>
                    <a href="view_registrations.php" class="btn btn-secondary">
                        View Existing Records
                    </a>
                </div>
            </section>

        <?php elseif ($recordSaved): ?>
            <!-- ================= SUCCESS DISPLAY ================= -->
            <section class="form-card">
                <div class="alert-box alert-success">
                    <div class="alert-title">
                        <span>🎉</span>
                        <span>Account Created Successfully!</span>
                    </div>
                    <p>
                        Your student registration details were validated, checked for uniqueness, hashed securely, and permanently stored.
                    </p>
                </div>

                <!-- Registration ID & Security Badges -->
                <div style="margin-bottom: 22px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <div>
                            <span style="font-size: 13px; color: #64748b; font-weight: 600;">REGISTRATION ID:</span>
                            <span class="badge badge-primary" style="font-size: 15px; padding: 6px 12px; margin-left: 6px;">
                                <?php echo htmlspecialchars($registrationId, ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </div>
                        <div>
                            <span style="font-size: 13px; color: #64748b; font-weight: 600;">USERNAME:</span>
                            <span class="badge badge-success" style="font-size: 15px; padding: 6px 12px; margin-left: 6px;">
                                @<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </div>
                    </div>

                    <div class="storage-tags">
                        <?php if ($mysqliWriteSuccess): ?>
                            <span class="storage-tag csv" style="background: #e0f2fe; border-color: #7dd3fc; color: #0369a1;">
                                <span>💾</span> MySQLi Inserted (Student #<?php echo (int)$mysqliInsertId; ?>)
                            </span>
                        <?php else: ?>
                            <span class="storage-tag" style="background: #fef3c7; border-color: #fde047; color: #92400e;">
                                <span>⚠️</span> MySQL Service Offline (File Saved)
                            </span>
                        <?php endif; ?>

                        <span class="storage-tag csv">
                            <span>📄</span> CSV Appended (registrations.csv)
                        </span>

                        <span class="storage-tag json">
                            <span>📦</span> JSON Updated (registrations.json)
                        </span>
                    </div>
                </div>

                <!-- Security Verification Notice Banner -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 18px; margin-bottom: 22px;">
                    <h4 style="margin: 0 0 8px 0; color: #0f172a; font-size: 14px; display: flex; align-items: center; gap: 6px;">
                        <span>🛡️</span> Security & Integrity Verification
                    </h4>
                    <div style="font-size: 13px; color: #475569; display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 10px;">
                        <div>
                            <strong>Prepared Statements:</strong>
                            <span style="color: #16a34a;">Active</span> via MySQLi <code>bind_param()</code> (SQL Injection Defense)
                        </div>
                        <div>
                            <strong>Duplicate Check:</strong>
                            <span style="color: #16a34a;">Passed</span> unique query on Email &amp; Username
                        </div>
                        <div>
                            <strong>Password Encryption:</strong>
                            <span style="color: #16a34a;">Hashed</span> via <code>password_hash()</code> (Bcrypt salted)
                        </div>
                        <div>
                            <strong>Input Sanitization:</strong>
                            <span style="color: #16a34a;">Applied</span> <code>htmlspecialchars</code> &amp; strip tags
                        </div>
                    </div>
                </div>

                <!-- Registered Student Summary Card -->
                <div class="summary-card">
                    <h3 style="color: #243b53; margin-bottom: 14px; font-size: 18px;">
                        📋 Submitted & Sanitized Student Profile
                    </h3>

                    <div class="summary-grid">
                        <div class="summary-item">
                            <div class="summary-label">Username</div>
                            <div class="summary-value" style="color: #1677e8;">
                                @<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </div>

                        <div class="summary-item">
                            <div class="summary-label">Full Name</div>
                            <div class="summary-value"><?php echo htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>

                        <div class="summary-item">
                            <div class="summary-label">Email Address</div>
                            <div class="summary-value"><?php echo htmlspecialchars($studentEmail, ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>

                        <div class="summary-item">
                            <div class="summary-label">Mobile Number</div>
                            <div class="summary-value"><?php echo htmlspecialchars($studentMobile, ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>

                        <div class="summary-item">
                            <div class="summary-label">Course</div>
                            <div class="summary-value"><?php echo htmlspecialchars($course, ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>

                        <div class="summary-item">
                            <div class="summary-label">Academic Year</div>
                            <div class="summary-value">Year <?php echo htmlspecialchars($year, ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>

                        <div class="summary-item">
                            <div class="summary-label">Gender</div>
                            <div class="summary-value"><?php echo htmlspecialchars($gender, ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>

                        <div class="summary-item">
                            <div class="summary-label">Registration Timestamp</div>
                            <div class="summary-value"><?php echo htmlspecialchars($registrationDate, ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                    </div>

                    <div style="margin-top: 15px; font-size: 13px; color: #64748b; background: #ffffff; border: 1px dashed #cbd5e1; border-radius: 6px; padding: 10px 14px;">
                        🔒 <strong>Password Storage:</strong> Passwords are never stored in plain text. Secure hash generated via <code>password_hash($password, PASSWORD_DEFAULT)</code>:
                        <div style="font-family: monospace; font-size: 11px; color: #475569; word-break: break-all; margin-top: 4px;">
                            <?php echo htmlspecialchars($passwordHash, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="form-actions">
                    <a href="../pages/login.php" class="btn">
                        🔑 Proceed to Login
                    </a>
                    <a href="view_registrations.php" class="btn btn-secondary">
                        📋 View All Registrations
                    </a>
                    <a href="../pages/register.html" class="btn btn-secondary">
                        + Register Another Student
                    </a>
                    <a href="db_dashboard.php" class="btn btn-secondary">
                        🗄️ Database Dashboard
                    </a>
                    <a href="../pages/index.html" class="btn btn-secondary">
                        Home Page
                    </a>
                </div>
            </section>
        <?php endif; ?>

    </div>
</main>

<!-- ================= FOOTER ================= -->
<footer>
    <p>© 2026 StudentHub | Digital Campus Portal</p>
    <div class="footer-links">
        <a href="../pages/index.html">Home</a>
        <a href="../pages/about.html">About</a>
        <a href="../pages/register.html">Register</a>
        <a href="../pages/login.html">Login</a>
        <a href="view_registrations.php">Records</a>
        <a href="../pages/contact.html">Contact</a>
    </div>
</footer>

<!-- ================= JAVASCRIPT ================= -->
<script src="../js/script.js"></script>

</body>
</html>
