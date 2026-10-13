<?php
/**
 * ============================================================================
 * StudentHub - Digital Campus Portal
 * PRACTICAL 7: PHP Form Processing, Server-Side Validation, Sanitization,
 *              and CSV / JSON File Storage
 * ============================================================================
 * 
 * Objectives:
 * 1. Process submitted registration form data using PHP ($_POST).
 * 2. Sanitize and validate all inputs on the server side (name, email, mobile,
 *    password, course, year, gender, terms).
 * 3. Store valid registration records in CSV (registrations.csv) and JSON
 *    (registrations.json) file formats.
 * 4. Check for duplicate registrations by email or mobile.
 * 5. Display clear success or error messages using StudentHub design system.
 * 6. Support both standard form submissions and asynchronous AJAX/Fetch requests.
 * ============================================================================
 */

// Set timezone for accurate registration timestamps
date_default_timezone_set('Asia/Kolkata');

// Storage file paths
$dataDir = __DIR__ . '/../data';
$csvFile = $dataDir . '/registrations.csv';
$jsonFile = $dataDir . '/registrations.json';

// Ensure data directory exists
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

// Check if request is AJAX / JSON
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    || (isset($_POST['ajax']) && $_POST['ajax'] === '1');

// Array to store validation errors
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

function sanitize_email($value) {
    if ($value === null) return '';
    return filter_var(trim($value), FILTER_SANITIZE_EMAIL);
}

function sanitize_digits($value) {
    if ($value === null) return '';
    return preg_replace('/[^0-9]/', '', trim($value));
}

// Initialize input variables
$studentName = '';
$studentEmail = '';
$studentMobile = '';
$studentPassword = '';
$confirmPassword = '';
$course = '';
$year = '';
$gender = '';
$termsAccepted = false;
$recordSaved = false;
$registrationId = '';
$registrationDate = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. SANITIZATION
    $studentName     = sanitize_string($_POST['studentName'] ?? '');
    $studentEmail    = sanitize_email($_POST['studentEmail'] ?? '');
    $studentMobile   = sanitize_digits($_POST['studentMobile'] ?? '');
    $studentPassword = (string)($_POST['studentPassword'] ?? '');
    $confirmPassword = (string)($_POST['confirmPassword'] ?? '');
    $course          = sanitize_string($_POST['course'] ?? '');
    $year            = sanitize_string($_POST['year'] ?? '');
    $gender          = sanitize_string($_POST['gender'] ?? '');
    $termsAccepted   = isset($_POST['terms']) && ($_POST['terms'] === 'on' || $_POST['terms'] === '1' || $_POST['terms'] === 'true');

    // 2. SERVER-SIDE VALIDATION

    // Full Name: required, letters and spaces only, at least two parts (first and last name)
    if (empty($studentName)) {
        $errors['studentName'] = 'Full Name is required.';
    } elseif (strlen($studentName) < 3 || strlen($studentName) > 60) {
        $errors['studentName'] = 'Full Name must be between 3 and 60 characters.';
    } elseif (!preg_match("/^[A-Za-z]+(?:\s[A-Za-z]+)+$/", $studentName)) {
        $errors['studentName'] = 'Enter a valid full name using letters and spaces only (e.g., "Rahul Patel").';
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

    // 3. DUPLICATE CHECK & RECORD COUNT
    // Scan existing CSV / JSON to avoid duplicate email or mobile registrations
    $existingRegistrations = [];
    $totalRecords = 0;

    if (file_exists($csvFile)) {
        $fp = fopen($csvFile, 'r');
        if ($fp !== false) {
            $header = fgetcsv($fp);
            while (($row = fgetcsv($fp)) !== false) {
                if (!empty($row[0])) {
                    $totalRecords++;
                    $existingEmail  = strtolower(trim($row[2] ?? ''));
                    $existingMobile = trim($row[3] ?? '');
                    if (strtolower($studentEmail) === $existingEmail && !empty($studentEmail)) {
                        $errors['duplicateEmail'] = 'A student with this email address (' . htmlspecialchars($studentEmail, ENT_QUOTES, 'UTF-8') . ') is already registered.';
                    }
                    if ($studentMobile === $existingMobile && !empty($studentMobile)) {
                        $errors['duplicateMobile'] = 'A student with this mobile number (' . htmlspecialchars($studentMobile, ENT_QUOTES, 'UTF-8') . ') is already registered.';
                    }
                }
            }
            fclose($fp);
        }
    }

    // 4. STORAGE (CSV and JSON)
    if (empty($errors)) {
        $registrationDate = date('Y-m-d H:i:s');
        $newIndex = $totalRecords + 1;
        $registrationId = 'STU' . date('Ymd') . sprintf('%03d', $newIndex);

        // Secure password hashing
        $passwordHash = password_hash($studentPassword, PASSWORD_DEFAULT);

        // --- A. Store in CSV ---
        $csvHeader = [
            'Registration ID',
            'Name',
            'Email',
            'Mobile',
            'Password Hash',
            'Course',
            'Year',
            'Gender',
            'Terms',
            'Registration Date'
        ];

        $csvRow = [
            $registrationId,
            $studentName,
            $studentEmail,
            $studentMobile,
            $passwordHash,
            $course,
            $year,
            $gender,
            'Accepted',
            $registrationDate
        ];

        $csvWriteSuccess = false;
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

        // --- B. Store in JSON ---
        $jsonWriteSuccess = false;
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
            'name'             => $studentName,
            'email'            => $studentEmail,
            'mobile'           => $studentMobile,
            'passwordHash'     => $passwordHash,
            'course'           => $course,
            'year'             => $year,
            'gender'           => $gender,
            'terms'            => 'Accepted',
            'registrationDate' => $registrationDate
        ];

        $jsonEncoded = json_encode($jsonRecords, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($jsonEncoded !== false) {
            $bytes = file_put_contents($jsonFile, $jsonEncoded, LOCK_EX);
            if ($bytes !== false) {
                $jsonWriteSuccess = true;
            }
        }

        $recordSaved = ($csvWriteSuccess || $jsonWriteSuccess);
    }
}

// Handle AJAX / JSON response
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
            'message' => 'Registration successful! Record stored in CSV and JSON formats.',
            'data'    => [
                'registrationId'   => $registrationId,
                'name'             => $studentName,
                'email'            => $studentEmail,
                'mobile'           => $studentMobile,
                'course'           => $course,
                'year'             => $year,
                'gender'           => $gender,
                'registrationDate' => $registrationDate,
                'storage'          => [
                    'csv'  => 'data/registrations.csv',
                    'json' => 'data/registrations.json'
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
    <title>StudentHub - Registration Processing</title>
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
            <h2>Practical 7 - Server-Side Form Processing</h2>
            <p>PHP Validation, Sanitization, and File-Based Record Storage (CSV / JSON)</p>
        </div>

        <?php if (!empty($errors)): ?>
            <!-- ================= ERROR DISPLAY ================= -->
            <section class="form-card">
                <div class="alert-box alert-danger">
                    <div class="alert-title">
                        <span>⚠️</span>
                        <span>Registration Submission Failed</span>
                    </div>
                    <p>The submitted data could not be processed due to the following server-side validation errors:</p>
                    <ul class="alert-list">
                        <?php foreach ($errors as $field => $errorMessage): ?>
                            <li><strong><?php echo htmlspecialchars(ucfirst(preg_replace('/(?<!\ )[A-Z]/', ' $0', $field)), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></li>
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
                        <span>Registration Completed Successfully!</span>
                    </div>
                    <p>
                        Your student registration was validated, sanitized on the server, and stored permanently.
                    </p>
                </div>

                <!-- Registration ID Badge & Tags -->
                <div style="margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <span style="font-size: 13px; color: #64748b; font-weight: 600;">REGISTRATION ID:</span>
                        <span class="badge badge-primary" style="font-size: 15px; padding: 6px 12px; margin-left: 6px;">
                            <?php echo htmlspecialchars($registrationId, ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </div>

                    <div class="storage-tags">
                        <span class="storage-tag csv">
                            <span>📄</span> CSV Appended (registrations.csv)
                        </span>
                        <span class="storage-tag json">
                            <span>📦</span> JSON Updated (registrations.json)
                        </span>
                    </div>
                </div>

                <!-- Registered Student Summary Card -->
                <div class="summary-card">
                    <h3 style="color: #243b53; margin-bottom: 12px; font-size: 18px;">
                        📋 Submitted & Sanitized Record Details
                    </h3>

                    <div class="summary-grid">
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
                            <div class="summary-label">Terms Accepted</div>
                            <div class="summary-value"><span class="badge badge-success">Accepted</span></div>
                        </div>

                        <div class="summary-item">
                            <div class="summary-label">Registration Timestamp</div>
                            <div class="summary-value"><?php echo htmlspecialchars($registrationDate, ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                    </div>

                    <div style="margin-top: 15px; font-size: 13px; color: #64748b;">
                        🔒 <em>Password has been securely hashed using bcrypt (<code>PASSWORD_DEFAULT</code>) before file storage.</em>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="form-actions">
                    <a href="view_registrations.php" class="btn">
                        View All Registrations
                    </a>
                    <a href="../pages/register.html" class="btn btn-secondary">
                        Register Another Student
                    </a>
                    <a href="../pages/dashboard.html" class="btn btn-secondary">
                        Go to Dashboard
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
        <a href="view_registrations.php">Records</a>
        <a href="../pages/contact.html">Contact</a>
    </div>
</footer>

<!-- ================= JAVASCRIPT ================= -->
<script src="../js/script.js"></script>

</body>
</html>
