<?php
/**
 * ============================================================================
 * StudentHub - Digital Campus Portal
 * PRACTICAL 13: PHP JSON REST API Endpoint for Students
 * ============================================================================
 * 
 * Endpoints:
 * - GET    /php/api_students.php             -> List all students (supports search, course, year, sort)
 * - GET    /php/api_students.php?id={id}     -> Fetch single student by ID or Registration Number
 * - POST   /php/api_students.php             -> Add a new student record
 * - PUT    /php/api_students.php?id={id}     -> Update student record
 * - DELETE /php/api_students.php?id={id}     -> Delete student record
 * - OPTIONS /php/api_students.php            -> Preflight CORS handling
 * ============================================================================
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db_connect.php';

$jsonFile = __DIR__ . '/../data/registrations.json';

function sendStudentJsonResponse(int $statusCode, bool $success, string $message, $data = null, array $extra = []): void {
    http_response_code($statusCode);
    $response = array_merge([
        'success'   => $success,
        'status'    => $statusCode,
        'message'   => $message,
        'timestamp' => date('c')
    ], $extra);

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function getStudentPayload(): array {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $json = json_decode($rawInput, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            return $json;
        }
        parse_str($rawInput, $parsed);
        if (is_array($parsed) && !empty($parsed)) {
            return $parsed;
        }
    }
    return !empty($_POST) ? $_POST : [];
}

function readJsonStudents(string $jsonFile): array {
    if (!file_exists($jsonFile)) return [];
    $raw = file_get_contents($jsonFile);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function writeJsonStudents(string $jsonFile, array $students): bool {
    return file_put_contents($jsonFile, json_encode($students, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false;
}

function normalizeStudentRecord(array $row): array {
    $id = isset($row['student_id']) ? (int)$row['student_id'] : 0;
    $regNo = $row['registration_no'] ?? ($row['registrationId'] ?? '');
    $username = $row['username'] ?? '';
    $name = $row['full_name'] ?? ($row['name'] ?? '');
    $email = $row['email'] ?? '';
    $mobile = $row['mobile'] ?? '';
    $course = $row['course'] ?? '';
    $year = (int)($row['academic_year'] ?? ($row['year'] ?? 1));
    $gender = $row['gender'] ?? 'Other';
    $role = $row['role'] ?? 'student';
    $date = $row['created_at'] ?? ($row['registrationDate'] ?? date('Y-m-d H:i:s'));

    return [
        'id'              => $id > 0 ? $id : $regNo,
        'student_id'      => $id,
        'registration_no' => $regNo,
        'registrationId'  => $regNo,
        'username'        => $username,
        'name'            => $name,
        'full_name'       => $name,
        'email'           => $email,
        'mobile'          => $mobile,
        'course'          => $course,
        'year'            => $year,
        'academic_year'   => $year,
        'gender'          => $gender,
        'role'            => $role,
        'created_at'      => $date,
        'registrationDate'=> $date
    ];
}

$method = $_SERVER['REQUEST_METHOD'];
$payload = getStudentPayload();

if ($method === 'POST') {
    if (isset($payload['_method'])) {
        $method = strtoupper(trim($payload['_method']));
    } elseif (isset($_POST['_method'])) {
        $method = strtoupper(trim($_POST['_method']));
    } elseif (isset($_GET['_method'])) {
        $method = strtoupper(trim($_GET['_method']));
    } elseif (isset($payload['action'])) {
        if ($payload['action'] === 'update') $method = 'PUT';
        elseif ($payload['action'] === 'delete') $method = 'DELETE';
    }
}

$mysqli = null;
$dbConnected = false;
try {
    $mysqli = getMySQLiConnection(false);
    $dbConnected = ($mysqli instanceof mysqli && !@$mysqli->connect_errno);
} catch (Throwable $e) {
    $dbConnected = false;
    $mysqli = null;
}

switch ($method) {

    // ------------------------------------------------------------------------
    // 1. GET: List students or single student
    // ------------------------------------------------------------------------
    case 'GET':
        $requestedId = isset($_GET['id']) ? trim($_GET['id']) : '';
        $search      = isset($_GET['search']) ? trim($_GET['search']) : '';
        $course      = isset($_GET['course']) ? trim($_GET['course']) : 'all';
        $year        = isset($_GET['year']) ? trim($_GET['year']) : 'all';
        $sort        = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';

        // Single student lookup
        if ($requestedId !== '') {
            if ($dbConnected) {
                try {
                    $stmt = is_numeric($requestedId) 
                        ? $mysqli->prepare("SELECT * FROM students WHERE student_id = ? OR registration_no = ? LIMIT 1")
                        : $mysqli->prepare("SELECT * FROM students WHERE registration_no = ? OR username = ? LIMIT 1");
                    $stmt->bind_param("ss", $requestedId, $requestedId);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($row = $res->fetch_assoc()) {
                        sendStudentJsonResponse(200, true, "Student record retrieved.", normalizeStudentRecord($row), ['source' => 'mysql']);
                    }
                } catch (Throwable $e) {
                    error_log("API GET Single Student Error: " . $e->getMessage());
                }
            }

            $students = readJsonStudents($jsonFile);
            foreach ($students as $s) {
                $norm = normalizeStudentRecord($s);
                if ((string)$norm['id'] === $requestedId || $norm['registration_no'] === $requestedId || $norm['username'] === $requestedId) {
                    sendStudentJsonResponse(200, true, "Student record retrieved from data store.", $norm, ['source' => 'json_file']);
                }
            }
            sendStudentJsonResponse(404, false, "Student with identifier '{$requestedId}' not found.");
        }

        // List students
        $studentsList = [];
        $source = 'json_file';

        if ($dbConnected) {
            try {
                $sql = "SELECT * FROM students WHERE 1=1";
                $types = "";
                $params = [];

                if ($course !== 'all' && $course !== '') {
                    $sql .= " AND course = ?";
                    $types .= "s";
                    $params[] = $course;
                }
                if ($year !== 'all' && $year !== '') {
                    $sql .= " AND academic_year = ?";
                    $types .= "i";
                    $params[] = (int)$year;
                }
                if ($search !== '') {
                    $sql .= " AND (full_name LIKE ? OR username LIKE ? OR email LIKE ? OR registration_no LIKE ? OR mobile LIKE ?)";
                    $types .= "sssss";
                    $term = "%{$search}%";
                    $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
                }

                if ($sort === 'nameAsc') $sql .= " ORDER BY full_name ASC";
                elseif ($sort === 'nameDesc') $sql .= " ORDER BY full_name DESC";
                elseif ($sort === 'oldest') $sql .= " ORDER BY student_id ASC";
                else $sql .= " ORDER BY student_id DESC";

                $stmt = $mysqli->prepare($sql);
                if (!empty($params)) {
                    $stmt->bind_param($types, ...$params);
                }
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $studentsList[] = normalizeStudentRecord($row);
                }
                $source = 'mysql';
            } catch (Throwable $e) {
                $studentsList = [];
            }
        }

        if (empty($studentsList)) {
            $raw = readJsonStudents($jsonFile);
            foreach ($raw as $s) {
                $norm = normalizeStudentRecord($s);
                if ($course !== 'all' && $course !== '' && strcasecmp($norm['course'], $course) !== 0) continue;
                if ($year !== 'all' && $year !== '' && (int)$norm['year'] !== (int)$year) continue;
                if ($search !== '') {
                    $q = mb_strtolower($search);
                    $m = str_contains(mb_strtolower($norm['name']), $q) ||
                         str_contains(mb_strtolower($norm['username']), $q) ||
                         str_contains(mb_strtolower($norm['email']), $q) ||
                         str_contains(mb_strtolower($norm['registration_no']), $q) ||
                         str_contains(mb_strtolower($norm['mobile']), $q);
                    if (!$m) continue;
                }
                $studentsList[] = $norm;
            }
            $source = 'json_file';
        }

        sendStudentJsonResponse(200, true, "Students retrieved successfully.", $studentsList, [
            'count'  => count($studentsList),
            'source' => $source,
            'driver' => $dbConnected ? 'MySQLi Prepared Statements' : 'Flat JSON File Storage'
        ]);
        break;

    // ------------------------------------------------------------------------
    // 2. POST: Add a new student
    // ------------------------------------------------------------------------
    case 'POST':
        $name     = trim($payload['name'] ?? ($payload['full_name'] ?? ''));
        $username = trim($payload['username'] ?? '');
        $email    = trim($payload['email'] ?? '');
        $mobile   = trim($payload['mobile'] ?? '');
        $course   = trim($payload['course'] ?? '');
        $year     = (int)($payload['year'] ?? ($payload['academic_year'] ?? 1));
        $gender   = trim($payload['gender'] ?? 'Male');
        $role     = trim($payload['role'] ?? 'student');
        $password = trim($payload['password'] ?? 'student123');

        $errors = [];
        if (mb_strlen($name) < 3) $errors[] = "Full name must be at least 3 characters.";
        if (empty($username) || !preg_match('/^[a-zA-Z0-9_]{3,25}$/', $username)) {
            $errors[] = "Username must be 3-25 alphanumeric characters or underscores.";
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Please provide a valid email address.";
        if (!preg_match('/^[0-9]{10}$/', $mobile)) $errors[] = "Mobile number must be exactly 10 digits.";
        if (empty($course)) $errors[] = "Course is required.";
        if ($year < 1 || $year > 4) $errors[] = "Academic year must be between 1 and 4.";

        if (!empty($errors)) {
            sendStudentJsonResponse(400, false, "Validation failed.", null, ['errors' => $errors]);
        }

        $regNo = 'STU' . date('Ymd') . sprintf("%03d", rand(10, 999));
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $savedDb = false;
        $newId = 0;

        if ($dbConnected) {
            try {
                // Check duplicates
                $chk = $mysqli->prepare("SELECT student_id FROM students WHERE email = ? OR username = ? LIMIT 1");
                $chk->bind_param("ss", $email, $username);
                $chk->execute();
                if ($chk->get_result()->fetch_assoc()) {
                    sendStudentJsonResponse(409, false, "A student with this email or username already exists.");
                }

                $stmt = $mysqli->prepare("
                    INSERT INTO students 
                        (registration_no, username, full_name, email, mobile, password_hash, course, academic_year, gender, terms_accepted, role) 
                    VALUES 
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Accepted', ?)
                ");
                $stmt->bind_param("ssssssisss", $regNo, $username, $name, $email, $mobile, $passwordHash, $course, $year, $gender, $role);
                if ($stmt->execute()) {
                    $newId = (int)$mysqli->insert_id;
                    $savedDb = true;
                }
            } catch (Throwable $e) {
                error_log("API POST Student MySQL Error: " . $e->getMessage());
            }
        }

        $jsonStudents = readJsonStudents($jsonFile);
        $newRecord = [
            'registrationId'   => $regNo,
            'username'         => $username,
            'name'             => $name,
            'email'            => $email,
            'mobile'           => $mobile,
            'passwordHash'     => $passwordHash,
            'course'           => $course,
            'year'             => (string)$year,
            'gender'           => $gender,
            'terms'            => 'Accepted',
            'role'             => $role,
            'registrationDate' => date('Y-m-d H:i:s')
        ];
        $jsonStudents[] = $newRecord;
        writeJsonStudents($jsonFile, $jsonStudents);

        sendStudentJsonResponse(201, true, "Student record created successfully.", normalizeStudentRecord(array_merge($newRecord, ['student_id' => $newId])), [
            'registration_no' => $regNo,
            'source'          => $savedDb ? 'mysql' : 'json_file'
        ]);
        break;

    // ------------------------------------------------------------------------
    // 3. PUT: Update student record
    // ------------------------------------------------------------------------
    case 'PUT':
        $targetId = isset($_GET['id']) ? trim($_GET['id']) : (string)($payload['id'] ?? ($payload['registration_no'] ?? ($payload['registrationId'] ?? '')));

        if ($targetId === '') {
            sendStudentJsonResponse(400, false, "Student identifier is required for PUT update.");
        }

        $jsonStudents = readJsonStudents($jsonFile);
        $foundIdx = -1;
        $existing = null;
        foreach ($jsonStudents as $idx => $s) {
            $norm = normalizeStudentRecord($s);
            if ((string)$norm['id'] === $targetId || $norm['registration_no'] === $targetId || $norm['username'] === $targetId) {
                $foundIdx = $idx;
                $existing = $s;
                break;
            }
        }

        $name   = trim($payload['name'] ?? ($payload['full_name'] ?? ($existing['name'] ?? '')));
        $email  = trim($payload['email'] ?? ($existing['email'] ?? ''));
        $mobile = trim($payload['mobile'] ?? ($existing['mobile'] ?? ''));
        $course = trim($payload['course'] ?? ($existing['course'] ?? ''));
        $year   = (int)($payload['year'] ?? ($payload['academic_year'] ?? ($existing['year'] ?? 1)));
        $gender = trim($payload['gender'] ?? ($existing['gender'] ?? 'Male'));
        $role   = trim($payload['role'] ?? ($existing['role'] ?? 'student'));

        $updatedDb = false;
        if ($dbConnected) {
            try {
                $stmt = is_numeric($targetId)
                    ? $mysqli->prepare("UPDATE students SET full_name = ?, email = ?, mobile = ?, course = ?, academic_year = ?, gender = ?, role = ? WHERE student_id = ? OR registration_no = ?")
                    : $mysqli->prepare("UPDATE students SET full_name = ?, email = ?, mobile = ?, course = ?, academic_year = ?, gender = ?, role = ? WHERE registration_no = ? OR username = ?");
                $stmt->bind_param("ssssissss", $name, $email, $mobile, $course, $year, $gender, $role, $targetId, $targetId);
                $stmt->execute();
                $updatedDb = true;
            } catch (Throwable $e) {
                error_log("API PUT Student DB Error: " . $e->getMessage());
            }
        }

        if ($foundIdx >= 0) {
            $jsonStudents[$foundIdx]['name']   = $name;
            $jsonStudents[$foundIdx]['email']  = $email;
            $jsonStudents[$foundIdx]['mobile'] = $mobile;
            $jsonStudents[$foundIdx]['course'] = $course;
            $jsonStudents[$foundIdx]['year']   = (string)$year;
            $jsonStudents[$foundIdx]['gender'] = $gender;
            $jsonStudents[$foundIdx]['role']   = $role;
            writeJsonStudents($jsonFile, $jsonStudents);
        }

        sendStudentJsonResponse(200, true, "Student record updated successfully.", [
            'id'              => $targetId,
            'name'            => $name,
            'email'           => $email,
            'mobile'          => $mobile,
            'course'          => $course,
            'year'            => $year,
            'gender'          => $gender,
            'role'            => $role
        ], ['source' => $updatedDb ? 'mysql' : 'json_file']);
        break;

    // ------------------------------------------------------------------------
    // 4. DELETE: Delete student record
    // ------------------------------------------------------------------------
    case 'DELETE':
        $targetId = isset($_GET['id']) ? trim($_GET['id']) : (string)($payload['id'] ?? ($payload['registration_no'] ?? ($payload['registrationId'] ?? '')));

        if ($targetId === '') {
            sendStudentJsonResponse(400, false, "Student identifier is required for DELETE operation.");
        }

        $deletedDb = false;
        if ($dbConnected) {
            try {
                $stmt = is_numeric($targetId)
                    ? $mysqli->prepare("DELETE FROM students WHERE student_id = ? OR registration_no = ?")
                    : $mysqli->prepare("DELETE FROM students WHERE registration_no = ? OR username = ?");
                $stmt->bind_param("ss", $targetId, $targetId);
                $stmt->execute();
                if ($stmt->affected_rows > 0) $deletedDb = true;
            } catch (Throwable $e) {
                error_log("API DELETE Student DB Error: " . $e->getMessage());
            }
        }

        $jsonStudents = readJsonStudents($jsonFile);
        $initCount = count($jsonStudents);
        $jsonStudents = array_values(array_filter($jsonStudents, function ($s) use ($targetId) {
            $norm = normalizeStudentRecord($s);
            return (string)$norm['id'] !== $targetId && $norm['registration_no'] !== $targetId && $norm['username'] !== $targetId;
        }));
        $deletedJson = (count($jsonStudents) < $initCount);
        writeJsonStudents($jsonFile, $jsonStudents);

        if (!$deletedDb && !$deletedJson) {
            sendStudentJsonResponse(404, false, "Student '{$targetId}' not found.");
        }

        sendStudentJsonResponse(200, true, "Student '{$targetId}' deleted successfully.", null, [
            'deleted_id' => $targetId,
            'source'     => $deletedDb ? 'mysql' : 'json_file'
        ]);
        break;

    default:
        header('Allow: GET, POST, PUT, DELETE, OPTIONS');
        sendStudentJsonResponse(405, false, "Method '{$method}' not allowed.");
        break;
}
