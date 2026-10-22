<?php
/**
 * ============================================================================
 * StudentHub - Digital Campus Portal
 * PRACTICAL 11: Student Management CRUD Module with MySQLi Statements
 * ============================================================================
 * 
 * Features & Specifications:
 * 1. ADD Student Record (INSERT via MySQLi Parameterized Prepared Statement)
 * 2. VIEW Student Records (SELECT with Detailed Profile View Modal)
 * 3. EDIT Student Record (UPDATE via MySQLi Parameterized Prepared Statement)
 * 4. DELETE Student Record (DELETE via MySQLi Parameterized Prepared Statement)
 * 5. SEARCH & FILTER:
 *    - Search by Full Name, Username, Email, Mobile, or Registration Number
 *    - Multi-criteria Filter by Course, Academic Year, Role, and Gender
 *    - Dynamic Sorting (Newest, Oldest, Name A-Z, Name Z-A, Reg No, Course)
 * 6. Explicit, Descriptive Success & Failure Alerts for All CRUD Operations
 * 7. Duplicate Checks (Email & Username Uniqueness Validation via MySQLi)
 * 8. Dual Synchronization: Syncs changes across MySQL and flat files (JSON/CSV)
 * 9. Lab Offline Resilience: Graceful fallback to data/registrations.json if MySQL offline
 * ============================================================================
 */

date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth_session.php';

// Start session if not already started
startSecureSession();
$currentUser = getCurrentUser();

$csvFile  = __DIR__ . '/../data/registrations.csv';
$jsonFile = __DIR__ . '/../data/registrations.json';

// Obtain MySQLi Connection
$mysqli = getMySQLiConnection();
$isDbConnected = ($mysqli instanceof mysqli);
if ($isDbConnected) {
    try {
        ensureAuthColumnsExist($mysqli);
    } catch (Exception $e) {
        error_log("Practical 11 Column Check: " . $e->getMessage());
    }
}

// ----------------------------------------------------------------------------
// HELPER: Sync student to JSON file
// ----------------------------------------------------------------------------
function syncJsonFile(string $jsonFile, array $record, string $operation = 'add'): void {
    if (!file_exists($jsonFile)) return;
    $content = file_get_contents($jsonFile);
    $records = !empty($content) ? json_decode($content, true) : [];
    if (!is_array($records)) $records = [];

    if ($operation === 'add') {
        $records[] = [
            'registrationId'   => $record['registration_no'] ?? '',
            'username'         => $record['username'] ?? '',
            'name'             => $record['full_name'] ?? '',
            'email'            => $record['email'] ?? '',
            'mobile'           => $record['mobile'] ?? '',
            'passwordHash'     => $record['password_hash'] ?? '',
            'course'           => $record['course'] ?? '',
            'year'             => (string)($record['academic_year'] ?? 1),
            'gender'           => $record['gender'] ?? 'Other',
            'terms'            => 'Accepted',
            'role'             => $record['role'] ?? 'student',
            'registrationDate' => $record['created_at'] ?? date('Y-m-d H:i:s')
        ];
    } elseif ($operation === 'edit') {
        $targetId = $record['registration_no'] ?? '';
        foreach ($records as &$r) {
            if (($r['registrationId'] ?? '') === $targetId || ($r['username'] ?? '') === ($record['username'] ?? '')) {
                $r['name']   = $record['full_name'] ?? $r['name'];
                $r['email']  = $record['email'] ?? $r['email'];
                $r['mobile'] = $record['mobile'] ?? $r['mobile'];
                $r['course'] = $record['course'] ?? $r['course'];
                $r['year']   = (string)($record['academic_year'] ?? $r['year']);
                $r['gender'] = $record['gender'] ?? $r['gender'];
                $r['role']   = $record['role'] ?? $r['role'];
                if (!empty($record['password_hash'])) {
                    $r['passwordHash'] = $record['password_hash'];
                }
                break;
            }
        }
        unset($r);
    } elseif ($operation === 'delete') {
        $targetId = $record['registration_no'] ?? '';
        $targetUser = $record['username'] ?? '';
        $records = array_values(array_filter($records, function ($r) use ($targetId, $targetUser) {
            return ($r['registrationId'] ?? '') !== $targetId && ($r['username'] ?? '') !== $targetUser;
        }));
    }

    file_put_contents($jsonFile, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

// ----------------------------------------------------------------------------
// API ENDPOINT: AJAX Fetch Single Student Record (For Edit & View Modals)
// ----------------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'get_student') {
    header('Content-Type: application/json');
    $studentId = (int)($_GET['id'] ?? 0);

    if ($studentId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid student identifier.']);
        exit;
    }

    $foundStudent = null;

    if ($isDbConnected) {
        try {
            $stmt = $mysqli->prepare("
                SELECT student_id, registration_no, username, full_name, email, mobile, 
                       course, academic_year, gender, role, terms_accepted, created_at, updated_at 
                FROM students 
                WHERE student_id = ? 
                LIMIT 1
            ");
            if ($stmt) {
                $stmt->bind_param("i", $studentId);
                $stmt->execute();
                $res = $stmt->get_result();
                $foundStudent = $res->fetch_assoc();
                $stmt->close();
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'MySQLi query error: ' . $e->getMessage()]);
            exit;
        }
    }

    // Offline JSON Fallback
    if (!$foundStudent && file_exists($jsonFile)) {
        $content = file_get_contents($jsonFile);
        $records = json_decode($content, true) ?: [];
        $idx = $studentId - 1;
        if (isset($records[$idx])) {
            $r = $records[$idx];
            $foundStudent = [
                'student_id'      => $studentId,
                'registration_no' => $r['registrationId'] ?? 'STU' . sprintf('%03d', $studentId),
                'username'        => $r['username'] ?? 'student' . $studentId,
                'full_name'       => $r['name'] ?? 'Student',
                'email'           => $r['email'] ?? '',
                'mobile'          => $r['mobile'] ?? '',
                'course'          => $r['course'] ?? 'General',
                'academic_year'   => (int)($r['year'] ?? 1),
                'gender'          => $r['gender'] ?? 'Other',
                'role'            => $r['role'] ?? 'student',
                'terms_accepted'  => 'Accepted',
                'created_at'      => $r['registrationDate'] ?? date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s')
            ];
        }
    }

    if ($foundStudent) {
        echo json_encode(['success' => true, 'student' => $foundStudent]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Student record not found.']);
    }
    exit;
}

// ----------------------------------------------------------------------------
// POST HANDLER: ADD, EDIT, DELETE OPERATIONS (MySQLi Prepared Statements)
// ----------------------------------------------------------------------------
$flashMessage = '';
$flashType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ------------------------------------------------------------------------
    // 1. ADD STUDENT RECORD (MySQLi INSERT)
    // ------------------------------------------------------------------------
    if ($action === 'add') {
        $fullName     = trim($_POST['full_name'] ?? '');
        $username     = strtolower(trim($_POST['username'] ?? ''));
        $email        = strtolower(trim($_POST['email'] ?? ''));
        $mobile       = trim($_POST['mobile'] ?? '');
        $course       = trim($_POST['course'] ?? '');
        $academicYear = (int)($_POST['academic_year'] ?? 1);
        $gender       = trim($_POST['gender'] ?? 'Other');
        $role         = trim($_POST['role'] ?? 'student');
        $password     = (string)($_POST['password'] ?? '');

        // Validation
        $errors = [];
        if (empty($fullName)) $errors[] = 'Full Name is required.';
        if (empty($username) || strlen($username) < 4) $errors[] = 'Username must be at least 4 characters.';
        if (!preg_match('/^[a-zA-Z0-9_]{4,30}$/', $username)) $errors[] = 'Username can only contain alphanumeric characters and underscores.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
        if (empty($mobile) || !preg_match('/^[6-9]\d{9}$/', $mobile)) $errors[] = 'Mobile must be a valid 10-digit Indian phone number.';
        if (empty($course)) $errors[] = 'Please select a course.';
        if ($academicYear < 1 || $academicYear > 4) $errors[] = 'Academic Year must be between 1 and 4.';
        if (empty($password)) $password = 'Student@123';

        if (!empty($errors)) {
            $flashType = 'danger';
            $flashMessage = 'Validation failed: ' . implode(' ', $errors);
        } else {
            $regNo = 'STU' . date('Ymd') . sprintf('%03d', rand(100, 999));
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $createdAt = date('Y-m-d H:i:s');

            if ($isDbConnected) {
                try {
                    // Check duplicate username or email via MySQLi Prepared Statement
                    $dupStmt = $mysqli->prepare("SELECT student_id, username, email FROM students WHERE username = ? OR email = ? LIMIT 1");
                    if ($dupStmt) {
                        $dupStmt->bind_param("ss", $username, $email);
                        $dupStmt->execute();
                        $dupRes = $dupStmt->get_result();

                        if ($dupRes && $dupRes->num_rows > 0) {
                            $existing = $dupRes->fetch_assoc();
                            $dupField = (strtolower($existing['username']) === $username) ? "username '@{$username}'" : "email '{$email}'";
                            $flashType = 'danger';
                            $flashMessage = "Duplicate record error: A student with {$dupField} already exists in the database.";
                            $dupStmt->close();
                        } else {
                            $dupStmt->close();

                            // Execute Parameterized MySQLi INSERT
                            $insStmt = $mysqli->prepare("
                                INSERT INTO students 
                                    (registration_no, username, full_name, email, mobile, password_hash, course, academic_year, gender, terms_accepted, role, created_at) 
                                VALUES 
                                    (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Accepted', ?, ?)
                            ");

                            if ($insStmt) {
                                $insStmt->bind_param(
                                    "ssssssissss",
                                    $regNo,
                                    $username,
                                    $fullName,
                                    $email,
                                    $mobile,
                                    $passwordHash,
                                    $course,
                                    $academicYear,
                                    $gender,
                                    $role,
                                    $createdAt
                                );

                                if ($insStmt->execute()) {
                                    $flashType = 'success';
                                    $flashMessage = "🎉 Student record added successfully! Registered <strong>{$fullName}</strong> as ID <code>{$regNo}</code> (MySQLi Prepared INSERT).";

                                    // Sync to flat JSON
                                    syncJsonFile($jsonFile, [
                                        'registration_no' => $regNo,
                                        'username'        => $username,
                                        'full_name'       => $fullName,
                                        'email'           => $email,
                                        'mobile'          => $mobile,
                                        'password_hash'   => $passwordHash,
                                        'course'          => $course,
                                        'academic_year'   => $academicYear,
                                        'gender'          => $gender,
                                        'role'            => $role,
                                        'created_at'      => $createdAt
                                    ], 'add');
                                } else {
                                    $flashType = 'danger';
                                    $flashMessage = "MySQLi INSERT execution failed: " . htmlspecialchars($insStmt->error);
                                }
                                $insStmt->close();
                            }
                        }
                    }
                } catch (Exception $e) {
                    $flashType = 'danger';
                    $flashMessage = "Database operation error: " . htmlspecialchars($e->getMessage());
                }
            } else {
                // Offline fallback mode
                syncJsonFile($jsonFile, [
                    'registration_no' => $regNo,
                    'username'        => $username,
                    'full_name'       => $fullName,
                    'email'           => $email,
                    'mobile'          => $mobile,
                    'password_hash'   => $passwordHash,
                    'course'          => $course,
                    'academic_year'   => $academicYear,
                    'gender'          => $gender,
                    'role'            => $role,
                    'created_at'      => $createdAt
                ], 'add');

                $flashType = 'success';
                $flashMessage = "Student record registered in offline storage: <strong>{$fullName}</strong> (<code>{$regNo}</code>). Connect MySQL for live table insertion.";
            }
        }
    }

    // ------------------------------------------------------------------------
    // 2. EDIT STUDENT RECORD (MySQLi UPDATE)
    // ------------------------------------------------------------------------
    elseif ($action === 'edit') {
        $studentId    = (int)($_POST['student_id'] ?? 0);
        $fullName     = trim($_POST['full_name'] ?? '');
        $username     = strtolower(trim($_POST['username'] ?? ''));
        $email        = strtolower(trim($_POST['email'] ?? ''));
        $mobile       = trim($_POST['mobile'] ?? '');
        $course       = trim($_POST['course'] ?? '');
        $academicYear = (int)($_POST['academic_year'] ?? 1);
        $gender       = trim($_POST['gender'] ?? 'Other');
        $role         = trim($_POST['role'] ?? 'student');
        $newPassword  = trim($_POST['password'] ?? '');

        if ($studentId <= 0) {
            $flashType = 'danger';
            $flashMessage = 'Invalid student record ID for update.';
        } elseif (empty($fullName) || empty($username) || empty($email) || empty($mobile) || empty($course)) {
            $flashType = 'danger';
            $flashMessage = 'All required student fields must be populated.';
        } else {
            if ($isDbConnected) {
                try {
                    // Check duplicate username or email on OTHER records
                    $dupStmt = $mysqli->prepare("
                        SELECT student_id, username, email 
                        FROM students 
                        WHERE (username = ? OR email = ?) AND student_id != ? 
                        LIMIT 1
                    ");

                    if ($dupStmt) {
                        $dupStmt->bind_param("ssi", $username, $email, $studentId);
                        $dupStmt->execute();
                        $dupRes = $dupStmt->get_result();

                        if ($dupRes && $dupRes->num_rows > 0) {
                            $flashType = 'danger';
                            $flashMessage = "Update conflict: Another student already owns that username or email address.";
                            $dupStmt->close();
                        } else {
                            $dupStmt->close();

                            // Parameterized UPDATE
                            if (!empty($newPassword)) {
                                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                                $updStmt = $mysqli->prepare("
                                    UPDATE students 
                                    SET full_name = ?, username = ?, email = ?, mobile = ?, 
                                        course = ?, academic_year = ?, gender = ?, role = ?, password_hash = ? 
                                    WHERE student_id = ?
                                ");
                                if ($updStmt) {
                                    $updStmt->bind_param(
                                        "sssssisssi",
                                        $fullName, $username, $email, $mobile, $course, 
                                        $academicYear, $gender, $role, $newHash, $studentId
                                    );
                                }
                            } else {
                                $updStmt = $mysqli->prepare("
                                    UPDATE students 
                                    SET full_name = ?, username = ?, email = ?, mobile = ?, 
                                        course = ?, academic_year = ?, gender = ?, role = ? 
                                    WHERE student_id = ?
                                ");
                                if ($updStmt) {
                                    $updStmt->bind_param(
                                        "sssssissi",
                                        $fullName, $username, $email, $mobile, $course, 
                                        $academicYear, $gender, $role, $studentId
                                    );
                                }
                            }

                            if ($updStmt && $updStmt->execute()) {
                                $flashType = 'success';
                                $flashMessage = "✏️ Student record for <strong>{$fullName}</strong> (ID #{$studentId}) updated successfully via MySQLi Prepared Statement!";
                                $updStmt->close();

                                // Retrieve regNo for JSON sync
                                $syncReg = '';
                                $rStmt = $mysqli->prepare("SELECT registration_no FROM students WHERE student_id = ?");
                                if ($rStmt) {
                                    $rStmt->bind_param("i", $studentId);
                                    $rStmt->execute();
                                    $rRes = $rStmt->get_result();
                                    if ($rRow = $rRes->fetch_assoc()) $syncReg = $rRow['registration_no'];
                                    $rStmt->close();
                                }

                                syncJsonFile($jsonFile, [
                                    'registration_no' => $syncReg,
                                    'username'        => $username,
                                    'full_name'       => $fullName,
                                    'email'           => $email,
                                    'mobile'          => $mobile,
                                    'course'          => $course,
                                    'academic_year'   => $academicYear,
                                    'gender'          => $gender,
                                    'role'            => $role,
                                    'password_hash'   => !empty($newPassword) ? password_hash($newPassword, PASSWORD_DEFAULT) : null
                                ], 'edit');
                            } else {
                                $flashType = 'danger';
                                $flashMessage = "MySQLi UPDATE execution failed: " . htmlspecialchars($mysqli->error);
                            }
                        }
                    }
                } catch (Exception $e) {
                    $flashType = 'danger';
                    $flashMessage = "Database update error: " . htmlspecialchars($e->getMessage());
                }
            } else {
                // Offline fallback
                syncJsonFile($jsonFile, [
                    'username'        => $username,
                    'full_name'       => $fullName,
                    'email'           => $email,
                    'mobile'          => $mobile,
                    'course'          => $course,
                    'academic_year'   => $academicYear,
                    'gender'          => $gender,
                    'role'            => $role
                ], 'edit');
                $flashType = 'success';
                $flashMessage = "Updated record for <strong>{$fullName}</strong> in offline storage.";
            }
        }
    }

    // ------------------------------------------------------------------------
    // 3. DELETE STUDENT RECORD (MySQLi DELETE)
    // ------------------------------------------------------------------------
    elseif ($action === 'delete') {
        $studentId   = (int)($_POST['student_id'] ?? 0);
        $studentName = trim($_POST['student_name'] ?? 'Student');
        $regNo       = trim($_POST['registration_no'] ?? '');

        if ($studentId <= 0 && empty($regNo)) {
            $flashType = 'danger';
            $flashMessage = 'Invalid student record identifier for deletion.';
        } else {
            if ($isDbConnected && $studentId > 0) {
                try {
                    $delStmt = $mysqli->prepare("DELETE FROM students WHERE student_id = ?");
                    if ($delStmt) {
                        $delStmt->bind_param("i", $studentId);
                        if ($delStmt->execute()) {
                            if ($delStmt->affected_rows > 0) {
                                $flashType = 'success';
                                $flashMessage = "🗑️ Student record <strong>{$studentName}</strong> (ID #{$studentId}) was permanently deleted via MySQLi Prepared Statement.";
                                syncJsonFile($jsonFile, ['registration_no' => $regNo], 'delete');
                            } else {
                                $flashType = 'warning';
                                $flashMessage = "No record matched ID #{$studentId} for deletion.";
                            }
                        } else {
                            $flashType = 'danger';
                            $flashMessage = "MySQLi DELETE failed: " . htmlspecialchars($delStmt->error);
                        }
                        $delStmt->close();
                    }
                } catch (Exception $e) {
                    $flashType = 'danger';
                    $flashMessage = "Database deletion notice: " . htmlspecialchars($e->getMessage());
                }
            } else {
                // Offline fallback
                syncJsonFile($jsonFile, ['registration_no' => $regNo, 'username' => $studentName], 'delete');
                $flashType = 'success';
                $flashMessage = "Deleted student record <strong>{$studentName}</strong> from offline storage.";
            }
        }
    }
}

// ----------------------------------------------------------------------------
// 4. QUERY STUDENTS WITH DYNAMIC SEARCH, FILTERING, & SORTING (MySQLi)
// ----------------------------------------------------------------------------
$searchQuery  = trim($_GET['search'] ?? '');
$filterCourse = trim($_GET['course'] ?? '');
$filterYear   = trim($_GET['year'] ?? '');
$filterRole   = trim($_GET['role'] ?? '');
$filterGender = trim($_GET['gender'] ?? '');
$sortBy       = trim($_GET['sort'] ?? 'newest');

$studentsList = [];
$totalStudentsCount = 0;
$allCourses = [];

if ($isDbConnected) {
    try {
        // Collect distinct courses for filter dropdown
        $cRes = $mysqli->query("SELECT DISTINCT course FROM students WHERE course IS NOT NULL AND course != '' ORDER BY course ASC");
        if ($cRes) {
            while ($cRow = $cRes->fetch_row()) {
                $allCourses[] = $cRow[0];
            }
            $cRes->free();
        }

        // Base Query with Parameterized Filters
        $sql = "
            SELECT student_id, registration_no, username, full_name, email, mobile, 
                   course, academic_year, gender, role, terms_accepted, created_at, updated_at 
            FROM students 
            WHERE 1=1
        ";
        $bindTypes = "";
        $bindValues = [];

        // Search Filter (Full Name, Username, Email, Reg No)
        if (!empty($searchQuery)) {
            $sql .= " AND (full_name LIKE ? OR username LIKE ? OR email LIKE ? OR registration_no LIKE ? OR mobile LIKE ?)";
            $searchPattern = '%' . $searchQuery . '%';
            $bindTypes .= "sssss";
            $bindValues[] = $searchPattern;
            $bindValues[] = $searchPattern;
            $bindValues[] = $searchPattern;
            $bindValues[] = $searchPattern;
            $bindValues[] = $searchPattern;
        }

        // Course Filter
        if (!empty($filterCourse)) {
            $sql .= " AND course = ?";
            $bindTypes .= "s";
            $bindValues[] = $filterCourse;
        }

        // Year Filter
        if (!empty($filterYear)) {
            $sql .= " AND academic_year = ?";
            $bindTypes .= "i";
            $bindValues[] = (int)$filterYear;
        }

        // Role Filter
        if (!empty($filterRole)) {
            $sql .= " AND role = ?";
            $bindTypes .= "s";
            $bindValues[] = $filterRole;
        }

        // Gender Filter
        if (!empty($filterGender)) {
            $sql .= " AND gender = ?";
            $bindTypes .= "s";
            $bindValues[] = $filterGender;
        }

        // Dynamic Sorting
        switch ($sortBy) {
            case 'oldest':
                $sql .= " ORDER BY student_id ASC";
                break;
            case 'name_asc':
                $sql .= " ORDER BY full_name ASC";
                break;
            case 'name_desc':
                $sql .= " ORDER BY full_name DESC";
                break;
            case 'reg_no':
                $sql .= " ORDER BY registration_no ASC";
                break;
            case 'course':
                $sql .= " ORDER BY course ASC, academic_year ASC";
                break;
            case 'newest':
            default:
                $sql .= " ORDER BY student_id DESC";
                break;
        }

        // Prepare and execute dynamic MySQLi query
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            if (!empty($bindTypes)) {
                $stmt->bind_param($bindTypes, ...$bindValues);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $studentsList[] = $row;
                }
                $res->free();
            }
            $stmt->close();
        }

        // Fetch overall database count
        $cntRes = $mysqli->query("SELECT COUNT(*) FROM students");
        if ($cntRes) {
            $totalStudentsCount = (int)($cntRes->fetch_row()[0] ?? 0);
            $cntRes->free();
        }

    } catch (Exception $e) {
        error_log("Practical 11 Search Notice: " . $e->getMessage());
    }
}

// Offline JSON fallback if empty or offline
if (empty($studentsList) && file_exists($jsonFile)) {
    $content = file_get_contents($jsonFile);
    $records = !empty($content) ? json_decode($content, true) : [];
    if (is_array($records)) {
        foreach ($records as $idx => $r) {
            $cName = $r['course'] ?? 'General';
            if (!in_array($cName, $allCourses)) $allCourses[] = $cName;

            $sObj = [
                'student_id'      => $idx + 1,
                'registration_no' => $r['registrationId'] ?? 'STU' . sprintf('%03d', $idx + 1),
                'username'        => $r['username'] ?? 'student' . ($idx + 1),
                'full_name'       => $r['name'] ?? 'Student',
                'email'           => $r['email'] ?? '',
                'mobile'          => $r['mobile'] ?? '',
                'course'          => $cName,
                'academic_year'   => (int)($r['year'] ?? 1),
                'gender'          => $r['gender'] ?? 'Other',
                'role'            => $r['role'] ?? 'student',
                'terms_accepted'  => 'Accepted',
                'created_at'      => $r['registrationDate'] ?? date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s')
            ];

            // Filter in-memory
            $matches = true;
            if (!empty($searchQuery)) {
                $sq = strtolower($searchQuery);
                $haystack = strtolower($sObj['full_name'] . ' ' . $sObj['username'] . ' ' . $sObj['email'] . ' ' . $sObj['registration_no'] . ' ' . $sObj['mobile']);
                if (strpos($haystack, $sq) === false) $matches = false;
            }
            if (!empty($filterCourse) && $sObj['course'] !== $filterCourse) $matches = false;
            if (!empty($filterYear) && (string)$sObj['academic_year'] !== (string)$filterYear) $matches = false;
            if (!empty($filterRole) && $sObj['role'] !== $filterRole) $matches = false;
            if (!empty($filterGender) && $sObj['gender'] !== $filterGender) $matches = false;

            if ($matches) {
                $studentsList[] = $sObj;
            }
        }
        $totalStudentsCount = count($records);
    }
}

$hasActiveFilters = (!empty($searchQuery) || !empty($filterCourse) || !empty($filterYear) || !empty($filterRole) || !empty($filterGender) || $sortBy !== 'newest');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Practical 11: Student Management (MySQLi CRUD)</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .crud-hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #2563eb 100%);
            color: white;
            padding: 26px 30px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.2);
        }
        .driver-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        .driver-online {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
        }
        .driver-offline {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        .filter-toolbar {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 18px 20px;
            margin-bottom: 22px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
        }
        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr auto;
            gap: 10px;
            align-items: end;
        }
        @media (max-width: 1024px) {
            .filter-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 640px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
        }
        .table-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #e2e8f0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }
        .action-btn-group {
            display: flex;
            gap: 6px;
            align-items: center;
            white-space: nowrap;
        }
        .btn-action {
            padding: 5px 9px;
            border-radius: 6px;
            border: 1px solid transparent;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-view {
            background: #eff6ff;
            color: #1e40af;
            border-color: #bfdbfe;
        }
        .btn-view:hover {
            background: #1e40af;
            color: white;
        }
        .btn-edit {
            background: #fefce8;
            color: #854d0e;
            border-color: #fde047;
        }
        .btn-edit:hover {
            background: #854d0e;
            color: white;
        }
        .btn-delete {
            background: #fef2f2;
            color: #991b1b;
            border-color: #fecaca;
        }
        .btn-delete:hover {
            background: #dc2626;
            color: white;
        }
        /* Modal Window */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-box {
            background: white;
            border-radius: 12px;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
            animation: modalFadeIn 0.25s ease-out;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-16px) scale(0.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .modal-header {
            padding: 18px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-body {
            padding: 22px 24px;
        }
        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            background: #f8fafc;
            border-radius: 0 0 12px 12px;
        }
        .close-modal-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #64748b;
        }
        .close-modal-btn:hover { color: #0f172a; }
        .form-grid-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        @media (max-width: 540px) {
            .form-grid-2col { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body>

<!-- ================= HEADER ================= -->
<header class="site-header">
    <div class="logo-icon">🎓</div>
    <h1>StudentHub Management</h1>
    <p>PRACTICAL 11: MySQLi Student Management CRUD Module</p>
</header>

<!-- ================= NAVBAR ================= -->
<nav class="navbar">
    <div class="nav-wrapper">
        <button class="menu-toggle" aria-label="Open navigation menu" aria-expanded="false">☰</button>

        <div class="nav-container">
            <a href="../pages/index.html">Home</a>
            <a href="../pages/about.html">About</a>
            <a href="../pages/register.html">Register</a>
            <a href="../pages/login.html">Login</a>
            <a href="../pages/dashboard.html">Dashboard</a>
            <a href="student_manager.php" class="active">Students (CRUD)</a>
            <a href="event_manager.php">Events (Admin CRUD)</a>
            <a href="../pages/admin.html">Admin</a>
            <a href="db_dashboard.php">Practical 8 DB</a>
            <a href="view_registrations.php">Records</a>
            <a href="../pages/contact.html">Contact</a>
        </div>

        <button class="theme-toggle" aria-label="Toggle dark mode">🌙</button>
    </div>
</nav>

<!-- ================= MAIN ================= -->
<main>
    <div class="container" style="max-width: 1200px; margin: 25px auto 40px auto;">

        <!-- Hero Section -->
        <section class="crud-hero">
            <div>
                <div style="font-size: 13px; color: #93c5fd; font-weight: 600; text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 4px;">
                    Practical 11 &bull; Complete Student Management Module
                </div>
                <h2 style="margin: 0; font-size: 24px; color: white;">
                    Student Records Administration
                </h2>
                <p style="margin: 6px 0 0 0; font-size: 13px; opacity: 0.9;">
                    Add, View, Edit, Delete, Search and Filter student records using Parameterized MySQLi Prepared Statements.
                </p>
            </div>

            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <?php if ($isDbConnected): ?>
                    <span class="driver-badge driver-online" title="MySQL native server-side prepared statements active">
                        🟢 MySQLi Connected (Prepared Statements)
                    </span>
                <?php else: ?>
                    <span class="driver-badge driver-offline" title="Syncing changes to data/registrations.json">
                        🟡 Offline Lab Mode (data/registrations.json)
                    </span>
                <?php endif; ?>

                <button type="button" class="btn" onclick="openAddModal()" style="background: #10b981; border: none; font-weight: 700; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">
                    + Add New Student
                </button>
            </div>
        </section>

        <!-- Flash Message Notification Alert -->
        <?php if (!empty($flashMessage)): ?>
            <div class="alert-box alert-<?php echo $flashType; ?>" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div class="alert-title">
                        <span><?php echo $flashType === 'success' ? '✅' : '⚠️'; ?></span>
                        <span><?php echo $flashType === 'success' ? 'Operation Completed' : 'Operation Alert'; ?></span>
                    </div>
                    <p style="margin: 0; font-size: 13px;"><?php echo $flashMessage; ?></p>
                </div>
                <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; font-size:18px; cursor:pointer;">&times;</button>
            </div>
        <?php endif; ?>

        <!-- KPI Summary Cards -->
        <div class="summary-grid" style="margin-bottom: 22px;">
            <div class="summary-item" style="border-left: 4px solid #3b82f6;">
                <div class="summary-label">Total Registered Students</div>
                <div style="font-size: 26px; font-weight: 700; color: #0f172a; margin-top: 2px;">
                    <?php echo $totalStudentsCount; ?>
                </div>
                <div style="font-size: 12px; color: #64748b;">Overall student database records</div>
            </div>

            <div class="summary-item" style="border-left: 4px solid #10b981;">
                <div class="summary-label">Filtered Results</div>
                <div style="font-size: 26px; font-weight: 700; color: #0f172a; margin-top: 2px;">
                    <?php echo count($studentsList); ?>
                </div>
                <div style="font-size: 12px; color: #64748b;">Matching current search &amp; filter criteria</div>
            </div>

            <div class="summary-item" style="border-left: 4px solid #8b5cf6;">
                <div class="summary-label">Active Academic Courses</div>
                <div style="font-size: 26px; font-weight: 700; color: #0f172a; margin-top: 2px;">
                    <?php echo count($allCourses); ?>
                </div>
                <div style="font-size: 12px; color: #64748b;">B.Tech IT, B.Tech CSE, BCA, etc.</div>
            </div>

            <div class="summary-item" style="border-left: 4px solid #f59e0b;">
                <div class="summary-label">Database Driver</div>
                <div style="font-size: 18px; font-weight: 700; color: #0f172a; margin-top: 6px;">
                    MySQLi Native
                </div>
                <div style="font-size: 12px; color: #64748b;">bind_param() injection defense</div>
            </div>
        </div>

        <!-- Search & Filter Controls Toolbar -->
        <section class="filter-toolbar">
            <form method="GET" action="student_manager.php" id="filterForm">
                <div style="margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                    <div style="font-size: 14px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 6px;">
                        <span>🔍</span> Search &amp; Multi-Criteria Filters
                    </div>
                    <?php if ($hasActiveFilters): ?>
                        <a href="student_manager.php" style="font-size: 12px; color: #ef4444; font-weight: 600; text-decoration: underline;">
                            ✕ Clear All Filters
                        </a>
                    <?php endif; ?>
                </div>

                <div class="filter-grid">
                    <!-- Search Input -->
                    <div>
                        <label for="searchQuery" style="font-size: 12px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">
                            Search Student
                        </label>
                        <input
                            type="text"
                            id="searchQuery"
                            name="search"
                            value="<?php echo htmlspecialchars($searchQuery); ?>"
                            placeholder="Name, username, email, ID..."
                            style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                    </div>

                    <!-- Course Filter -->
                    <div>
                        <label for="filterCourse" style="font-size: 12px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">
                            Course
                        </label>
                        <select
                            id="filterCourse"
                            name="course"
                            style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                            <option value="">All Courses</option>
                            <?php foreach ($allCourses as $c): ?>
                                <option value="<?php echo htmlspecialchars($c); ?>" <?php echo ($filterCourse === $c) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Year Filter -->
                    <div>
                        <label for="filterYear" style="font-size: 12px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">
                            Year
                        </label>
                        <select
                            id="filterYear"
                            name="year"
                            style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                            <option value="">All Years</option>
                            <option value="1" <?php echo ($filterYear === '1') ? 'selected' : ''; ?>>Year 1</option>
                            <option value="2" <?php echo ($filterYear === '2') ? 'selected' : ''; ?>>Year 2</option>
                            <option value="3" <?php echo ($filterYear === '3') ? 'selected' : ''; ?>>Year 3</option>
                            <option value="4" <?php echo ($filterYear === '4') ? 'selected' : ''; ?>>Year 4</option>
                        </select>
                    </div>

                    <!-- Role Filter -->
                    <div>
                        <label for="filterRole" style="font-size: 12px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">
                            Role
                        </label>
                        <select
                            id="filterRole"
                            name="role"
                            style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                            <option value="">All Roles</option>
                            <option value="student" <?php echo ($filterRole === 'student') ? 'selected' : ''; ?>>Student</option>
                            <option value="admin" <?php echo ($filterRole === 'admin') ? 'selected' : ''; ?>>Administrator</option>
                        </select>
                    </div>

                    <!-- Gender Filter -->
                    <div>
                        <label for="filterGender" style="font-size: 12px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">
                            Gender
                        </label>
                        <select
                            id="filterGender"
                            name="gender"
                            style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                            <option value="">All Genders</option>
                            <option value="Male" <?php echo ($filterGender === 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo ($filterGender === 'Female') ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo ($filterGender === 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <!-- Sort Dropdown -->
                    <div>
                        <label for="sortBy" style="font-size: 12px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">
                            Sort By
                        </label>
                        <select
                            id="sortBy"
                            name="sort"
                            style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                            <option value="newest" <?php echo ($sortBy === 'newest') ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo ($sortBy === 'oldest') ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="name_asc" <?php echo ($sortBy === 'name_asc') ? 'selected' : ''; ?>>Name (A-Z)</option>
                            <option value="name_desc" <?php echo ($sortBy === 'name_desc') ? 'selected' : ''; ?>>Name (Z-A)</option>
                            <option value="reg_no" <?php echo ($sortBy === 'reg_no') ? 'selected' : ''; ?>>Reg. Number</option>
                            <option value="course" <?php echo ($sortBy === 'course') ? 'selected' : ''; ?>>Course & Year</option>
                        </select>
                    </div>

                    <!-- Filter Action Button -->
                    <div style="display: flex; gap: 6px;">
                        <button type="submit" class="btn" style="padding: 8px 16px; font-size: 13px;">
                            Apply
                        </button>
                    </div>
                </div>
            </form>
        </section>

        <!-- Records Table Section -->
        <section class="summary-card" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h3 style="margin: 0; color: #1e293b; font-size: 18px;">
                        📋 Student Directory Records
                    </h3>
                    <div style="font-size: 13px; color: #64748b; margin-top: 3px;">
                        Showing <?php echo count($studentsList); ?> of <?php echo $totalStudentsCount; ?> total student profiles
                    </div>
                </div>

                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <a href="view_registrations.php?action=download_csv" class="btn btn-secondary btn-small" title="Download records as CSV">
                        ⬇️ Export CSV
                    </a>
                    <a href="view_registrations.php?action=view_json" target="_blank" class="btn btn-secondary btn-small" title="View raw JSON data">
                        🔍 Export JSON
                    </a>
                </div>
            </div>

            <div class="table-responsive" style="margin: 0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student Profile</th>
                            <th>Reg. Number</th>
                            <th>Username</th>
                            <th>Contact Info</th>
                            <th>Course &amp; Year</th>
                            <th>Gender</th>
                            <th>Role</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($studentsList)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 36px 20px; color: #64748b;">
                                    <div style="font-size: 32px; margin-bottom: 8px;">🔍</div>
                                    <strong style="font-size: 15px; color: #334155;">No student records found</strong>
                                    <p style="margin: 4px 0 14px 0; font-size: 13px;">
                                        No students matched your search query or filter parameters.
                                    </p>
                                    <a href="student_manager.php" class="btn btn-secondary btn-small">
                                        Reset Filters
                                    </a>
                                    <button type="button" class="btn btn-small" onclick="openAddModal()" style="margin-left: 8px;">
                                        + Add New Student
                                    </button>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($studentsList as $stu): 
                                $sId = (int)$stu['student_id'];
                                $regNo = htmlspecialchars($stu['registration_no'] ?? '');
                                $uName = htmlspecialchars($stu['username'] ?? '');
                                $fName = htmlspecialchars($stu['full_name'] ?? '');
                                $email = htmlspecialchars($stu['email'] ?? '');
                                $mobile = htmlspecialchars($stu['mobile'] ?? '');
                                $course = htmlspecialchars($stu['course'] ?? '');
                                $year = (int)($stu['academic_year'] ?? 1);
                                $gender = htmlspecialchars($stu['gender'] ?? 'Other');
                                $role = htmlspecialchars($stu['role'] ?? 'student');
                                $avatar = ($gender === 'Female') ? '👩‍🎓' : (($role === 'admin') ? '🛡️' : '👨‍🎓');
                            ?>
                                <tr>
                                    <!-- Student Profile -->
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div class="table-avatar"><?php echo $avatar; ?></div>
                                            <div>
                                                <div style="font-weight: 700; color: #1e293b;"><?php echo $fName; ?></div>
                                                <div style="font-size: 11px; color: #64748b;">ID #<?php echo $sId; ?></div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Reg No -->
                                    <td>
                                        <span class="badge badge-primary" style="font-family: monospace; font-size: 11px;">
                                            <?php echo $regNo; ?>
                                        </span>
                                    </td>

                                    <!-- Username -->
                                    <td>
                                        <span class="badge badge-success" style="font-size: 11px;">
                                            @<?php echo $uName; ?>
                                        </span>
                                    </td>

                                    <!-- Contact Info -->
                                    <td>
                                        <div style="font-size: 13px; color: #1e293b;"><?php echo $email; ?></div>
                                        <div style="font-size: 11px; color: #64748b;">📱 <?php echo $mobile; ?></div>
                                    </td>

                                    <!-- Course & Year -->
                                    <td>
                                        <div style="font-weight: 600; font-size: 13px; color: #1e293b;"><?php echo $course; ?></div>
                                        <div style="font-size: 11px; color: #64748b;">Year <?php echo $year; ?> of 4</div>
                                    </td>

                                    <!-- Gender -->
                                    <td>
                                        <span class="storage-tag" style="padding: 2px 8px; font-size: 11px;">
                                            <?php echo $gender; ?>
                                        </span>
                                    </td>

                                    <!-- Role -->
                                    <td>
                                        <?php if ($role === 'admin'): ?>
                                            <span class="badge" style="background: #fee2e2; color: #991b1b; font-weight: 700; font-size: 11px;">
                                                Administrator
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-primary" style="font-size: 11px;">
                                                Student
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Actions -->
                                    <td style="text-align: right;">
                                        <div class="action-btn-group" style="justify-content: flex-end;">
                                            <button type="button" class="btn-action btn-view" onclick="openViewModal(<?php echo $sId; ?>)" title="View complete student profile">
                                                <span>👁️</span> View
                                            </button>
                                            <button type="button" class="btn-action btn-edit" onclick="openEditModal(<?php echo $sId; ?>)" title="Edit student record">
                                                <span>✏️</span> Edit
                                            </button>
                                            <button type="button" class="btn-action btn-delete" onclick="confirmDelete(<?php echo $sId; ?>, '<?php echo addslashes($fName); ?>', '<?php echo $regNo; ?>')" title="Delete student record">
                                                <span>🗑️</span> Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </div>
</main>

<!-- =========================================================================
     MODAL 1: ADD NEW STUDENT (MySQLi INSERT)
     ========================================================================= -->
<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 style="margin: 0; font-size: 18px; color: #1e293b;">
                ➕ Add New Student Record (MySQLi INSERT)
            </h3>
            <button type="button" class="close-modal-btn" onclick="closeModal('addModal')">&times;</button>
        </div>

        <form method="POST" action="student_manager.php">
            <input type="hidden" name="action" value="add">

            <div class="modal-body">
                <div class="form-grid-2col">
                    <div class="form-group">
                        <label for="addFullName">Full Name <span class="required">*</span></label>
                        <input type="text" id="addFullName" name="full_name" placeholder="e.g. Yashvi Sharma" required>
                    </div>

                    <div class="form-group">
                        <label for="addUsername">Username <span class="required">*</span></label>
                        <input type="text" id="addUsername" name="username" placeholder="e.g. yashvi_s" pattern="^[a-zA-Z0-9_]{4,30}$" title="4-30 characters, letters, numbers, underscore only" required>
                    </div>
                </div>

                <div class="form-grid-2col">
                    <div class="form-group">
                        <label for="addEmail">Email Address <span class="required">*</span></label>
                        <input type="email" id="addEmail" name="email" placeholder="e.g. yashvi@example.com" required>
                    </div>

                    <div class="form-group">
                        <label for="addMobile">Mobile Number <span class="required">*</span></label>
                        <input type="tel" id="addMobile" name="mobile" placeholder="10-digit phone e.g. 9876543210" pattern="^[6-9]\d{9}$" required>
                    </div>
                </div>

                <div class="form-grid-2col">
                    <div class="form-group">
                        <label for="addCourse">Academic Course <span class="required">*</span></label>
                        <select id="addCourse" name="course" required>
                            <option value="">-- Select Course --</option>
                            <option value="B.Tech IT">B.Tech IT</option>
                            <option value="B.Tech CSE">B.Tech CSE</option>
                            <option value="BCA">BCA</option>
                            <option value="B.Tech CE">B.Tech CE</option>
                            <option value="MCA">MCA</option>
                            <option value="Administration">Administration</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="addAcademicYear">Academic Year <span class="required">*</span></label>
                        <select id="addAcademicYear" name="academic_year" required>
                            <option value="1">Year 1</option>
                            <option value="2">Year 2</option>
                            <option value="3" selected>Year 3</option>
                            <option value="4">Year 4</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid-2col">
                    <div class="form-group">
                        <label for="addGender">Gender <span class="required">*</span></label>
                        <select id="addGender" name="gender" required>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="addRole">System Role <span class="required">*</span></label>
                        <select id="addRole" name="role" required>
                            <option value="student" selected>Student</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="addPassword">Initial Password (Default: Student@123)</label>
                    <input type="password" id="addPassword" name="password" placeholder="Leave blank to use default Student@123">
                    <small style="color: #64748b; font-size: 11px;">Will be securely hashed via Bcrypt (password_hash()) before database insertion.</small>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn" style="background: #10b981; border-color: #10b981;">Save Student</button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL 2: EDIT STUDENT (MySQLi UPDATE)
     ========================================================================= -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 style="margin: 0; font-size: 18px; color: #1e293b;">
                ✏️ Edit Student Record (MySQLi UPDATE)
            </h3>
            <button type="button" class="close-modal-btn" onclick="closeModal('editModal')">&times;</button>
        </div>

        <form method="POST" action="student_manager.php">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" id="editStudentId" name="student_id" value="">

            <div class="modal-body">
                <div style="background: #f1f5f9; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; color: #475569;">
                    Editing Student: <strong id="editBadgeRegNo" style="font-family: monospace; color: #0284c7;">STU...</strong>
                </div>

                <div class="form-grid-2col">
                    <div class="form-group">
                        <label for="editFullName">Full Name <span class="required">*</span></label>
                        <input type="text" id="editFullName" name="full_name" required>
                    </div>

                    <div class="form-group">
                        <label for="editUsername">Username <span class="required">*</span></label>
                        <input type="text" id="editUsername" name="username" required>
                    </div>
                </div>

                <div class="form-grid-2col">
                    <div class="form-group">
                        <label for="editEmail">Email Address <span class="required">*</span></label>
                        <input type="email" id="editEmail" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="editMobile">Mobile Number <span class="required">*</span></label>
                        <input type="tel" id="editMobile" name="mobile" pattern="^[6-9]\d{9}$" required>
                    </div>
                </div>

                <div class="form-grid-2col">
                    <div class="form-group">
                        <label for="editCourse">Course <span class="required">*</span></label>
                        <select id="editCourse" name="course" required>
                            <option value="B.Tech IT">B.Tech IT</option>
                            <option value="B.Tech CSE">B.Tech CSE</option>
                            <option value="BCA">BCA</option>
                            <option value="B.Tech CE">B.Tech CE</option>
                            <option value="MCA">MCA</option>
                            <option value="Administration">Administration</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="editAcademicYear">Academic Year <span class="required">*</span></label>
                        <select id="editAcademicYear" name="academic_year" required>
                            <option value="1">Year 1</option>
                            <option value="2">Year 2</option>
                            <option value="3">Year 3</option>
                            <option value="4">Year 4</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid-2col">
                    <div class="form-group">
                        <label for="editGender">Gender <span class="required">*</span></label>
                        <select id="editGender" name="gender" required>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="editRole">System Role <span class="required">*</span></label>
                        <select id="editRole" name="role" required>
                            <option value="student">Student</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="editPassword">Change Password (Optional)</label>
                    <input type="password" id="editPassword" name="password" placeholder="Leave blank to keep existing password">
                    <small style="color: #64748b; font-size: 11px;">If entered, password will be re-hashed via Bcrypt.</small>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn" style="background: #2563eb; border-color: #2563eb;">Update Record</button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL 3: VIEW STUDENT DETAILS
     ========================================================================= -->
<div class="modal-overlay" id="viewModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 style="margin: 0; font-size: 18px; color: #1e293b;">
                👁️ Student Record Details
            </h3>
            <button type="button" class="close-modal-btn" onclick="closeModal('viewModal')">&times;</button>
        </div>

        <div class="modal-body" id="viewModalBody">
            <div style="text-align: center; padding: 20px;">
                <div style="font-size: 32px;">⏳</div>
                <p>Loading student profile...</p>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('viewModal')">Close</button>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 4: CONFIRM DELETE (MySQLi DELETE)
     ========================================================================= -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box" style="max-width: 440px;">
        <div class="modal-header" style="background: #fef2f2;">
            <h3 style="margin: 0; font-size: 17px; color: #991b1b;">
                ⚠️ Confirm Record Deletion
            </h3>
            <button type="button" class="close-modal-btn" onclick="closeModal('deleteModal')">&times;</button>
        </div>

        <form method="POST" action="student_manager.php">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" id="deleteStudentId" name="student_id" value="">
            <input type="hidden" id="deleteStudentName" name="student_name" value="">
            <input type="hidden" id="deleteRegNo" name="registration_no" value="">

            <div class="modal-body">
                <p style="margin: 0; font-size: 14px; color: #334155;">
                    Are you sure you want to delete student record for <strong id="deleteDisplayTarget"></strong>?
                </p>
                <div style="background: #fff1f2; border: 1px solid #fecdd3; border-radius: 8px; padding: 12px; margin-top: 14px; font-size: 12px; color: #9f1239;">
                    🚨 <strong>Warning:</strong> This executes a native MySQLi <code>DELETE FROM students WHERE student_id = ?</code>. This action is irreversible.
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="btn" style="background: #dc2626; border-color: #dc2626;">Permanently Delete</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= FOOTER ================= -->
<footer>
    <p>© 2026 StudentHub | Digital Campus Portal</p>
    <div class="footer-links">
        <a href="../pages/index.html">Home</a>
        <a href="../pages/about.html">About</a>
        <a href="../pages/register.html">Register</a>
        <a href="student_manager.php">Students</a>
        <a href="../pages/admin.html">Admin</a>
        <a href="view_registrations.php">Records</a>
    </div>
</footer>

<script src="../js/script.js"></script>
<script>
// Modal Helpers
function openAddModal() {
    document.getElementById('addModal').classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

// Close modal on escape key or clicking outside
window.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));
    }
});

document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function (e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// Open Edit Modal & Fetch Student via AJAX
function openEditModal(studentId) {
    fetch(`student_manager.php?action=get_student&id=${studentId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.student) {
                const s = data.student;
                document.getElementById('editStudentId').value = s.student_id;
                document.getElementById('editBadgeRegNo').textContent = s.registration_no;
                document.getElementById('editFullName').value = s.full_name;
                document.getElementById('editUsername').value = s.username;
                document.getElementById('editEmail').value = s.email;
                document.getElementById('editMobile').value = s.mobile;
                document.getElementById('editCourse').value = s.course;
                document.getElementById('editAcademicYear').value = s.academic_year;
                document.getElementById('editGender').value = s.gender;
                document.getElementById('editRole').value = s.role;
                document.getElementById('editPassword').value = '';

                document.getElementById('editModal').classList.add('active');
            } else {
                alert(data.message || 'Error fetching student record.');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Failed to connect to student details endpoint.');
        });
}

// Open View Modal & Populate Profile
function openViewModal(studentId) {
    const body = document.getElementById('viewModalBody');
    body.innerHTML = `
        <div style="text-align: center; padding: 20px;">
            <div style="font-size: 32px;">⏳</div>
            <p>Loading student profile...</p>
        </div>
    `;
    document.getElementById('viewModal').classList.add('active');

    fetch(`student_manager.php?action=get_student&id=${studentId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.student) {
                const s = data.student;
                const roleBadge = s.role === 'admin' 
                    ? '<span class="badge" style="background:#fee2e2; color:#991b1b; font-weight:700;">Administrator</span>' 
                    : '<span class="badge badge-primary">Student</span>';

                body.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #e2e8f0;">
                        <div style="width: 58px; height: 58px; border-radius: 50%; background: #e0f2fe; display: flex; align-items: center; justify-content: center; font-size: 26px;">
                            ${s.gender === 'Female' ? '👩‍🎓' : (s.role === 'admin' ? '🛡️' : '👨‍🎓')}
                        </div>
                        <div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <h3 style="margin: 0; font-size: 19px; color: #1e293b;">${s.full_name}</h3>
                                ${roleBadge}
                            </div>
                            <div style="font-size: 13px; color: #64748b; margin-top: 3px;">
                                @${s.username} &bull; Reg ID: <code style="color: #0284c7;">${s.registration_no}</code>
                            </div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; font-size: 13px;">
                        <div style="background: #f8fafc; padding: 10px 12px; border-radius: 6px;">
                            <span style="color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 600; display: block;">Email Address</span>
                            <span style="color: #0f172a; font-weight: 600;">${s.email}</span>
                        </div>

                        <div style="background: #f8fafc; padding: 10px 12px; border-radius: 6px;">
                            <span style="color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 600; display: block;">Mobile Number</span>
                            <span style="color: #0f172a; font-weight: 600;">+91 ${s.mobile}</span>
                        </div>

                        <div style="background: #f8fafc; padding: 10px 12px; border-radius: 6px;">
                            <span style="color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 600; display: block;">Academic Course</span>
                            <span style="color: #0f172a; font-weight: 600;">${s.course}</span>
                        </div>

                        <div style="background: #f8fafc; padding: 10px 12px; border-radius: 6px;">
                            <span style="color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 600; display: block;">Academic Year</span>
                            <span style="color: #0f172a; font-weight: 600;">Year ${s.academic_year} of 4</span>
                        </div>

                        <div style="background: #f8fafc; padding: 10px 12px; border-radius: 6px;">
                            <span style="color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 600; display: block;">Gender</span>
                            <span style="color: #0f172a; font-weight: 600;">${s.gender}</span>
                        </div>

                        <div style="background: #f8fafc; padding: 10px 12px; border-radius: 6px;">
                            <span style="color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 600; display: block;">Registered Date</span>
                            <span style="color: #0f172a; font-weight: 600;">${s.created_at || 'Recently'}</span>
                        </div>
                    </div>
                `;
            } else {
                body.innerHTML = `<p style="color: red;">${data.message || 'Record not found.'}</p>`;
            }
        })
        .catch(err => {
            console.error(err);
            body.innerHTML = `<p style="color: red;">Failed to load student details.</p>`;
        });
}

// Confirm Delete Modal
function confirmDelete(studentId, fullName, regNo) {
    document.getElementById('deleteStudentId').value = studentId;
    document.getElementById('deleteStudentName').value = fullName;
    document.getElementById('deleteRegNo').value = regNo;
    document.getElementById('deleteDisplayTarget').textContent = `${fullName} (${regNo})`;
    document.getElementById('deleteModal').classList.add('active');
}
</script>

</body>
</html>
