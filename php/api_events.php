<?php
/**
 * ============================================================================
 * StudentHub - Digital Campus Portal
 * PRACTICAL 13: PHP JSON REST API Endpoint for Events
 * ============================================================================
 * 
 * Endpoints:
 * - GET    /php/api_events.php             -> List all events (supports search, category, status, sort)
 * - GET    /php/api_events.php?id={id}     -> Fetch single event details by ID
 * - POST   /php/api_events.php             -> Add a new event (expects JSON payload or form-data)
 * - PUT    /php/api_events.php?id={id}     -> Update existing event (expects JSON payload)
 * - DELETE /php/api_events.php?id={id}     -> Delete event by ID
 * - OPTIONS /php/api_events.php            -> Preflight CORS handling
 * 
 * Capabilities:
 * - Sends JSON headers (Content-Type: application/json; charset=UTF-8)
 * - Standard HTTP Status Codes: 200 OK, 201 Created, 400 Bad Request, 404 Not Found, 405 Method Not Allowed, 500 Internal Server Error
 * - Parameterized MySQLi Prepared Statements for SQL Injection defense
 * - Automatic dual-synchronization with data/events.json
 * - Offline / Lab fallback: Performs CRUD on data/events.json if MySQL is offline
 * ============================================================================
 */

// Strict error reporting for debugging, but prevent HTML output from corrupting JSON
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Set JSON and CORS response headers
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db_connect.php';

$jsonFile = __DIR__ . '/../data/events.json';

// Helper to output structured JSON response and terminate
function sendJsonResponse(int $statusCode, bool $success, string $message, $data = null, array $extra = []): void {
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

// Helper to parse incoming request data (handles JSON payloads and URL-encoded bodies)
function getRequestPayload(): array {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $json = json_decode($rawInput, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            return $json;
        }
        // Try parsing URL-encoded string if JSON decode didn't yield an array
        parse_str($rawInput, $parsed);
        if (is_array($parsed) && !empty($parsed)) {
            return $parsed;
        }
    }
    return !empty($_POST) ? $_POST : [];
}

// Helper to read events from fallback JSON file
function readJsonEvents(string $jsonFile): array {
    if (!file_exists($jsonFile)) {
        return [];
    }
    $raw = file_get_contents($jsonFile);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// Helper to save events to JSON file
function writeJsonEvents(string $jsonFile, array $events): bool {
    $encoded = json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return file_put_contents($jsonFile, $encoded) !== false;
}

// Helper to normalize an event record array for API consistency
function normalizeEventRecord(array $row): array {
    $id = isset($row['event_id']) ? (int)$row['event_id'] : (int)($row['id'] ?? 0);
    $title = $row['title'] ?? '';
    $category = $row['category'] ?? 'Technology';
    $date = $row['event_date'] ?? ($row['date'] ?? '');
    $time = $row['event_time'] ?? ($row['time'] ?? '');
    $location = $row['location'] ?? '';
    $description = $row['description'] ?? '';
    $organizer = $row['organizer'] ?? '';
    $seats = isset($row['total_seats']) ? (int)$row['total_seats'] : (int)($row['seats'] ?? 0);
    $availableSeats = isset($row['available_seats']) ? (int)$row['available_seats'] : $seats;
    $status = $row['status'] ?? 'Upcoming';
    $poster = $row['poster_image'] ?? 'uploads/posters/default_poster.jpg';

    return [
        'id'              => $id,
        'event_id'        => $id,
        'title'           => $title,
        'category'        => $category,
        'date'            => $date,
        'event_date'      => $date,
        'time'            => $time,
        'event_time'      => $time,
        'location'        => $location,
        'description'     => $description,
        'organizer'       => $organizer,
        'seats'           => $seats,
        'total_seats'     => $seats,
        'available_seats' => $availableSeats,
        'status'          => $status,
        'poster_image'    => $poster
    ];
}

// Determine HTTP Request Method (supports method override for environments where PUT/DELETE are limited)
$method = $_SERVER['REQUEST_METHOD'];
$payload = getRequestPayload();

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

// Get MySQLi connection
$mysqli = null;
$dbConnected = false;
try {
    $mysqli = getMySQLiConnection(false);
    $dbConnected = ($mysqli instanceof mysqli && !@$mysqli->connect_errno);
} catch (Throwable $e) {
    $dbConnected = false;
    $mysqli = null;
}

// ============================================================================
// ROUTING BASED ON HTTP METHOD
// ============================================================================

switch ($method) {

    // ------------------------------------------------------------------------
    // 1. GET: Fetch list of events or single event
    // ------------------------------------------------------------------------
    case 'GET':
        $requestedId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $search      = isset($_GET['search']) ? trim($_GET['search']) : '';
        $category    = isset($_GET['category']) ? trim($_GET['category']) : 'all';
        $status      = isset($_GET['status']) ? trim($_GET['status']) : 'all';
        $sort        = isset($_GET['sort']) ? trim($_GET['sort']) : 'dateAsc';

        // 1A. Fetch Single Event by ID
        if ($requestedId > 0) {
            if ($dbConnected) {
                try {
                    $stmt = $mysqli->prepare("SELECT * FROM events WHERE event_id = ? LIMIT 1");
                    $stmt->bind_param("i", $requestedId);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($row = $res->fetch_assoc()) {
                        sendJsonResponse(200, true, "Event details retrieved successfully.", normalizeEventRecord($row), [
                            'source' => 'mysql'
                        ]);
                    }
                } catch (Throwable $e) {
                    error_log("API GET Single Error: " . $e->getMessage());
                }
            }

            // Fallback to JSON file
            $jsonEvents = readJsonEvents($jsonFile);
            foreach ($jsonEvents as $ev) {
                $eid = (int)($ev['id'] ?? ($ev['event_id'] ?? 0));
                if ($eid === $requestedId) {
                    sendJsonResponse(200, true, "Event details retrieved from data store.", normalizeEventRecord($ev), [
                        'source' => 'json_file'
                    ]);
                }
            }

            sendJsonResponse(404, false, "Event with ID #{$requestedId} was not found.");
        }

        // 1B. Fetch List of Events (with optional filters)
        $eventsList = [];
        $dataSource = 'json_file';

        if ($dbConnected) {
            try {
                $sql = "SELECT * FROM events WHERE 1=1";
                $types = "";
                $params = [];

                if ($category !== '' && $category !== 'all') {
                    $sql .= " AND category = ?";
                    $types .= "s";
                    $params[] = $category;
                }

                if ($status !== '' && $status !== 'all') {
                    $sql .= " AND status = ?";
                    $types .= "s";
                    $params[] = $status;
                }

                if ($search !== '') {
                    $sql .= " AND (title LIKE ? OR description LIKE ? OR location LIKE ? OR organizer LIKE ?)";
                    $types .= "ssss";
                    $term = "%{$search}%";
                    $params[] = $term;
                    $params[] = $term;
                    $params[] = $term;
                    $params[] = $term;
                }

                // Sorting
                switch ($sort) {
                    case 'dateDesc':
                        $sql .= " ORDER BY event_date DESC, event_time DESC";
                        break;
                    case 'nameAsc':
                        $sql .= " ORDER BY title ASC";
                        break;
                    case 'nameDesc':
                        $sql .= " ORDER BY title DESC";
                        break;
                    case 'seatsDesc':
                        $sql .= " ORDER BY total_seats DESC";
                        break;
                    case 'dateAsc':
                    default:
                        $sql .= " ORDER BY event_date ASC, event_time ASC";
                        break;
                }

                $stmt = $mysqli->prepare($sql);
                if (!empty($params)) {
                    $stmt->bind_param($types, ...$params);
                }
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $eventsList[] = normalizeEventRecord($row);
                }
                $dataSource = 'mysql';
            } catch (Throwable $e) {
                error_log("API GET List DB Error: " . $e->getMessage());
                $eventsList = [];
                $dataSource = 'json_file';
            }
        }

        // Fallback or verify with JSON file if DB returned empty or offline
        if (empty($eventsList)) {
            $rawEvents = readJsonEvents($jsonFile);
            foreach ($rawEvents as $ev) {
                $norm = normalizeEventRecord($ev);

                // Filter Category
                if ($category !== 'all' && $category !== '' && strcasecmp($norm['category'], $category) !== 0) {
                    continue;
                }
                // Filter Status
                if ($status !== 'all' && $status !== '' && strcasecmp($norm['status'], $status) !== 0) {
                    continue;
                }
                // Filter Search
                if ($search !== '') {
                    $s = mb_strtolower($search);
                    $match = str_contains(mb_strtolower($norm['title']), $s) ||
                             str_contains(mb_strtolower($norm['description']), $s) ||
                             str_contains(mb_strtolower($norm['location']), $s) ||
                             str_contains(mb_strtolower($norm['organizer']), $s);
                    if (!$match) continue;
                }
                $eventsList[] = $norm;
            }

            // Client/JSON Sort
            usort($eventsList, function ($a, $b) use ($sort) {
                switch ($sort) {
                    case 'dateDesc':
                        return strcmp($b['date'], $a['date']);
                    case 'nameAsc':
                        return strcasecmp($a['title'], $b['title']);
                    case 'nameDesc':
                        return strcasecmp($b['title'], $a['title']);
                    case 'seatsDesc':
                        return ($b['seats'] <=> $a['seats']);
                    case 'dateAsc':
                    default:
                        return strcmp($a['date'], $b['date']);
                }
            });
            $dataSource = 'json_file';
        }

        sendJsonResponse(200, true, "Events retrieved successfully.", $eventsList, [
            'count'  => count($eventsList),
            'source' => $dataSource,
            'driver' => $dbConnected ? 'MySQLi Prepared Statements' : 'Flat JSON File Storage'
        ]);
        break;

    // ------------------------------------------------------------------------
    // 2. POST: Add a new event
    // ------------------------------------------------------------------------
    case 'POST':
        $errors = [];

        $title       = trim($payload['title'] ?? '');
        $category    = trim($payload['category'] ?? '');
        $date        = trim($payload['date'] ?? ($payload['event_date'] ?? ''));
        $time        = trim($payload['time'] ?? ($payload['event_time'] ?? ''));
        $location    = trim($payload['location'] ?? '');
        $description = trim($payload['description'] ?? '');
        $organizer   = trim($payload['organizer'] ?? '');
        $seats       = isset($payload['seats']) ? (int)$payload['seats'] : (isset($payload['total_seats']) ? (int)$payload['total_seats'] : 0);
        $availSeats  = isset($payload['available_seats']) ? (int)$payload['available_seats'] : $seats;
        $status      = trim($payload['status'] ?? 'Upcoming');
        $posterImage = trim($payload['poster_image'] ?? 'uploads/posters/default_poster.jpg');

        // Validation Rules
        if (mb_strlen($title) < 3) {
            $errors[] = "Title must be at least 3 characters long.";
        }
        $validCategories = ['Technology', 'Competition', 'Workshop', 'Sports', 'Cultural', 'Career'];
        if (!in_array($category, $validCategories)) {
            $errors[] = "Please select a valid category (" . implode(', ', $validCategories) . ").";
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $errors[] = "Event date must be formatted as YYYY-MM-DD.";
        }
        if (empty($time)) {
            $errors[] = "Event time is required.";
        }
        if (empty($location)) {
            $errors[] = "Location / venue is required.";
        }
        if (mb_strlen($description) < 10) {
            $errors[] = "Description must be at least 10 characters long.";
        }
        if (empty($organizer)) {
            $errors[] = "Organizer is required.";
        }
        if ($seats <= 0) {
            $errors[] = "Total capacity seats must be greater than 0.";
        }
        $validStatuses = ['Upcoming', 'Ongoing', 'Completed', 'Cancelled'];
        if (!in_array($status, $validStatuses)) {
            $status = 'Upcoming';
        }

        if (!empty($errors)) {
            sendJsonResponse(400, false, "Validation failed.", null, [
                'errors' => $errors
            ]);
        }

        $newId = 0;
        $savedInDb = false;

        // Save in MySQL via Prepared Statement
        if ($dbConnected) {
            try {
                $stmt = $mysqli->prepare("
                    INSERT INTO events 
                        (title, category, event_date, event_time, location, description, organizer, total_seats, available_seats, status, poster_image)
                    VALUES 
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "sssssssiiss",
                    $title,
                    $category,
                    $date,
                    $time,
                    $location,
                    $description,
                    $organizer,
                    $seats,
                    $availSeats,
                    $status,
                    $posterImage
                );
                if ($stmt->execute()) {
                    $newId = (int)$mysqli->insert_id;
                    $savedInDb = true;
                }
            } catch (Throwable $e) {
                error_log("API POST MySQL Insert Error: " . $e->getMessage());
                $savedInDb = false;
            }
        }

        // Sync with JSON file & fallback ID generation if needed
        $jsonEvents = readJsonEvents($jsonFile);
        if ($newId <= 0) {
            $maxId = 0;
            foreach ($jsonEvents as $ev) {
                $eid = (int)($ev['id'] ?? ($ev['event_id'] ?? 0));
                if ($eid > $maxId) $maxId = $eid;
            }
            $newId = $maxId + 1;
        }

        $newRecord = [
            'id'              => $newId,
            'event_id'        => $newId,
            'title'           => $title,
            'category'        => $category,
            'date'            => $date,
            'event_date'      => $date,
            'time'            => $time,
            'event_time'      => $time,
            'location'        => $location,
            'description'     => $description,
            'organizer'       => $organizer,
            'seats'           => $seats,
            'total_seats'     => $seats,
            'available_seats' => $availSeats,
            'status'          => $status,
            'poster_image'    => $posterImage
        ];

        // Add to JSON file dataset
        $jsonEvents[] = $newRecord;
        writeJsonEvents($jsonFile, $jsonEvents);

        sendJsonResponse(201, true, "Event created successfully.", $newRecord, [
            'event_id' => $newId,
            'source'   => $savedInDb ? 'mysql' : 'json_file'
        ]);
        break;

    // ------------------------------------------------------------------------
    // 3. PUT: Update an existing event
    // ------------------------------------------------------------------------
    case 'PUT':
        $targetId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($payload['id'] ?? ($payload['event_id'] ?? 0));

        if ($targetId <= 0) {
            sendJsonResponse(400, false, "Event ID parameter is required for PUT update operation.");
        }

        // Check if event exists
        $jsonEvents = readJsonEvents($jsonFile);
        $foundIndex = -1;
        $existingRecord = null;
        foreach ($jsonEvents as $idx => $ev) {
            $eid = (int)($ev['id'] ?? ($ev['event_id'] ?? 0));
            if ($eid === $targetId) {
                $foundIndex = $idx;
                $existingRecord = $ev;
                break;
            }
        }

        $title       = trim($payload['title'] ?? ($existingRecord['title'] ?? ''));
        $category    = trim($payload['category'] ?? ($existingRecord['category'] ?? 'Technology'));
        $date        = trim($payload['date'] ?? ($payload['event_date'] ?? ($existingRecord['date'] ?? '')));
        $time        = trim($payload['time'] ?? ($payload['event_time'] ?? ($existingRecord['time'] ?? '')));
        $location    = trim($payload['location'] ?? ($existingRecord['location'] ?? ''));
        $description = trim($payload['description'] ?? ($existingRecord['description'] ?? ''));
        $organizer   = trim($payload['organizer'] ?? ($existingRecord['organizer'] ?? ''));
        $seats       = isset($payload['seats']) ? (int)$payload['seats'] : (isset($payload['total_seats']) ? (int)$payload['total_seats'] : (int)($existingRecord['seats'] ?? 50));
        $availSeats  = isset($payload['available_seats']) ? (int)$payload['available_seats'] : ($existingRecord['available_seats'] ?? $seats);
        $status      = trim($payload['status'] ?? ($existingRecord['status'] ?? 'Upcoming'));
        $posterImage = trim($payload['poster_image'] ?? ($existingRecord['poster_image'] ?? 'uploads/posters/default_poster.jpg'));

        $errors = [];
        if (mb_strlen($title) < 3) $errors[] = "Title must be at least 3 characters long.";
        if (empty($location)) $errors[] = "Location is required.";
        if ($seats <= 0) $errors[] = "Total capacity seats must be greater than 0.";

        if (!empty($errors)) {
            sendJsonResponse(400, false, "Validation failed.", null, ['errors' => $errors]);
        }

        $updatedInDb = false;
        if ($dbConnected) {
            try {
                $stmt = $mysqli->prepare("
                    UPDATE events 
                    SET title = ?, category = ?, event_date = ?, event_time = ?, location = ?,
                        description = ?, organizer = ?, total_seats = ?, available_seats = ?,
                        status = ?, poster_image = ?
                    WHERE event_id = ?
                ");
                $stmt->bind_param(
                    "sssssssiissi",
                    $title,
                    $category,
                    $date,
                    $time,
                    $location,
                    $description,
                    $organizer,
                    $seats,
                    $availSeats,
                    $status,
                    $posterImage,
                    $targetId
                );
                $stmt->execute();
                $updatedInDb = true;
            } catch (Throwable $e) {
                error_log("API PUT MySQL Error: " . $e->getMessage());
            }
        }

        // Update JSON file
        $updatedRecord = [
            'id'              => $targetId,
            'event_id'        => $targetId,
            'title'           => $title,
            'category'        => $category,
            'date'            => $date,
            'event_date'      => $date,
            'time'            => $time,
            'event_time'      => $time,
            'location'        => $location,
            'description'     => $description,
            'organizer'       => $organizer,
            'seats'           => $seats,
            'total_seats'     => $seats,
            'available_seats' => $availSeats,
            'status'          => $status,
            'poster_image'    => $posterImage
        ];

        if ($foundIndex >= 0) {
            $jsonEvents[$foundIndex] = $updatedRecord;
        } else {
            $jsonEvents[] = $updatedRecord;
        }
        writeJsonEvents($jsonFile, $jsonEvents);

        sendJsonResponse(200, true, "Event updated successfully.", $updatedRecord, [
            'event_id' => $targetId,
            'source'   => $updatedInDb ? 'mysql' : 'json_file'
        ]);
        break;

    // ------------------------------------------------------------------------
    // 4. DELETE: Delete an event
    // ------------------------------------------------------------------------
    case 'DELETE':
        $targetId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($payload['id'] ?? ($payload['event_id'] ?? 0));

        if ($targetId <= 0) {
            sendJsonResponse(400, false, "Event ID parameter is required for DELETE operation.");
        }

        $deletedInDb = false;
        if ($dbConnected) {
            try {
                $stmt = $mysqli->prepare("DELETE FROM events WHERE event_id = ?");
                $stmt->bind_param("i", $targetId);
                $stmt->execute();
                if ($stmt->affected_rows > 0) {
                    $deletedInDb = true;
                }
            } catch (Throwable $e) {
                error_log("API DELETE MySQL Error: " . $e->getMessage());
            }
        }

        // Update JSON file
        $jsonEvents = readJsonEvents($jsonFile);
        $originalCount = count($jsonEvents);
        $jsonEvents = array_values(array_filter($jsonEvents, function ($ev) use ($targetId) {
            $eid = (int)($ev['id'] ?? ($ev['event_id'] ?? 0));
            return $eid !== $targetId;
        }));
        $deletedInJson = (count($jsonEvents) < $originalCount);
        writeJsonEvents($jsonFile, $jsonEvents);

        if (!$deletedInDb && !$deletedInJson) {
            sendJsonResponse(404, false, "Event with ID #{$targetId} was not found.");
        }

        sendJsonResponse(200, true, "Event #{$targetId} deleted successfully.", null, [
            'deleted_id' => $targetId,
            'source'     => $deletedInDb ? 'mysql' : 'json_file'
        ]);
        break;

    // ------------------------------------------------------------------------
    // Default: Method Not Allowed
    // ------------------------------------------------------------------------
    default:
        header('Allow: GET, POST, PUT, DELETE, OPTIONS');
        sendJsonResponse(405, false, "HTTP Method '{$method}' is not allowed on this endpoint.", null, [
            'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']
        ]);
        break;
}
