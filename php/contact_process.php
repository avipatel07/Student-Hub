<?php
/**
 * ============================================================================
 * StudentHub - Digital Campus Portal
 * PRACTICAL 7: Contact Form PHP Processing, Server-Side Validation,
 *              Sanitization, and CSV / JSON Storage
 * ============================================================================
 */

date_default_timezone_set('Asia/Kolkata');

$dataDir = __DIR__ . '/../data';
$csvFile = $dataDir . '/contacts.csv';
$jsonFile = $dataDir . '/contacts.json';

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

$errors = [];

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

$name = '';
$email = '';
$mobile = '';
$subject = '';
$message = '';
$messageId = '';
$submissionDate = '';
$recordSaved = false;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $errors['request'] = 'Invalid request method. Only POST submissions are allowed.';
} else {

    // 1. Sanitization
    $name    = sanitize_string($_POST['contactName'] ?? '');
    $email   = sanitize_email($_POST['contactEmail'] ?? '');
    $mobile  = sanitize_digits($_POST['contactMobile'] ?? '');
    $subject = sanitize_string($_POST['contactSubject'] ?? '');
    $message = sanitize_string($_POST['contactMessage'] ?? '');

    // 2. Validation
    if (empty($name)) {
        $errors['name'] = 'Full Name is required.';
    } elseif (strlen($name) < 3) {
        $errors['name'] = 'Full Name must be at least 3 characters long.';
    } elseif (!preg_match("/^[A-Za-z]+(?:\s[A-Za-z]+)*$/", $name)) {
        $errors['name'] = 'Full Name must contain letters and spaces only.';
    }

    if (empty($email)) {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please provide a valid email address.';
    }

    if (!empty($mobile) && !preg_match("/^[6-9]\d{9}$/", $mobile)) {
        $errors['mobile'] = 'Mobile number must be a valid 10-digit Indian number starting with 6-9.';
    }

    if (empty($subject)) {
        $errors['subject'] = 'Subject is required.';
    } elseif (strlen($subject) < 3) {
        $errors['subject'] = 'Subject must be at least 3 characters long.';
    }

    if (empty($message)) {
        $errors['message'] = 'Message is required.';
    } elseif (strlen($message) < 10) {
        $errors['message'] = 'Message must be at least 10 characters long.';
    }

    // 3. Storage
    if (empty($errors)) {
        $submissionDate = date('Y-m-d H:i:s');

        // Count existing records
        $totalContacts = 0;
        if (file_exists($csvFile)) {
            $fp = fopen($csvFile, 'r');
            if ($fp !== false) {
                while (($row = fgetcsv($fp)) !== false) {
                    if (!empty($row[0])) $totalContacts++;
                }
                fclose($fp);
            }
        }

        $messageId = 'MSG' . date('Ymd') . sprintf('%03d', $totalContacts + 1);

        // A. CSV Storage
        $csvHeader = ['Contact ID', 'Name', 'Email', 'Mobile', 'Subject', 'Message', 'Submission Date'];
        $csvRow = [$messageId, $name, $email, $mobile, $subject, $message, $submissionDate];

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
            }
            fclose($csvHandle);
        }

        // B. JSON Storage
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
            'contactId'      => $messageId,
            'name'           => $name,
            'email'          => $email,
            'mobile'         => $mobile,
            'subject'        => $subject,
            'message'        => $message,
            'submissionDate' => $submissionDate
        ];

        file_put_contents($jsonFile, json_encode($jsonRecords, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        $recordSaved = true;
    }
}

if ($isAjax) {
    header('Content-Type: application/json; charset=UTF-8');
    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'errors' => $errors]);
    } else {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'messageId' => $messageId,
            'message' => 'Your message has been received and saved.'
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Message Processing</title>
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

        <div class="page-title">
            <h2>Contact Message Submission</h2>
            <p>Practical 7 - Server-Side PHP Validation & CSV/JSON Data Persistence</p>
        </div>

        <?php if (!empty($errors)): ?>
            <section class="form-card">
                <div class="alert-box alert-danger">
                    <div class="alert-title">
                        <span>⚠️</span>
                        <span>Submission Failed</span>
                    </div>
                    <p>Please resolve the following errors and submit again:</p>
                    <ul class="alert-list">
                        <?php foreach ($errors as $field => $err): ?>
                            <li><strong><?php echo htmlspecialchars(ucfirst($field), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="form-actions">
                    <a href="javascript:history.back()" class="btn">
                        ← Back to Contact Form
                    </a>
                    <a href="../pages/index.html" class="btn btn-secondary">
                        Home Page
                    </a>
                </div>
            </section>
        <?php elseif ($recordSaved): ?>
            <section class="form-card">
                <div class="alert-box alert-success">
                    <div class="alert-title">
                        <span>✅</span>
                        <span>Message Sent Successfully!</span>
                    </div>
                    <p>Thank you for reaching out. Your query has been logged and stored on the server.</p>
                </div>

                <div style="margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <span style="font-size: 13px; color: #64748b; font-weight: 600;">REFERENCE ID:</span>
                        <span class="badge badge-primary" style="font-size: 15px; padding: 6px 12px; margin-left: 6px;">
                            <?php echo htmlspecialchars($messageId, ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </div>

                    <div class="storage-tags">
                        <span class="storage-tag csv">📁 contacts.csv</span>
                        <span class="storage-tag json">📦 contacts.json</span>
                    </div>
                </div>

                <div class="summary-card">
                    <h3 style="color: #243b53; margin-bottom: 12px; font-size: 18px;">
                        📝 Message Details
                    </h3>

                    <div class="summary-grid">
                        <div class="summary-item">
                            <div class="summary-label">Sender Name</div>
                            <div class="summary-value"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>

                        <div class="summary-item">
                            <div class="summary-label">Email Address</div>
                            <div class="summary-value"><?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>

                        <div class="summary-item">
                            <div class="summary-label">Mobile Number</div>
                            <div class="summary-value"><?php echo htmlspecialchars($mobile ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>

                        <div class="summary-item">
                            <div class="summary-label">Subject</div>
                            <div class="summary-value"><?php echo htmlspecialchars($subject, ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>

                        <div class="summary-item" style="grid-column: 1 / -1;">
                            <div class="summary-label">Message</div>
                            <div class="summary-value" style="font-weight: normal; margin-top: 4px;">
                                <?php echo nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')); ?>
                            </div>
                        </div>

                        <div class="summary-item">
                            <div class="summary-label">Submitted On</div>
                            <div class="summary-value"><?php echo htmlspecialchars($submissionDate, ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="../pages/contact.html" class="btn">
                        Send Another Message
                    </a>
                    <a href="../pages/index.html" class="btn btn-secondary">
                        Back to Home
                    </a>
                    <a href="view_registrations.php" class="btn btn-secondary">
                        View Registrations
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
        <a href="../pages/contact.html">Contact</a>
        <a href="view_registrations.php">Records</a>
    </div>
</footer>

<script src="../js/script.js"></script>

</body>
</html>
