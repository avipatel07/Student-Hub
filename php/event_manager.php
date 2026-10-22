<?php
/**
 * ============================================================================
 * StudentHub - Digital Campus Portal
 * PRACTICAL 12: Event Management CRUD Module for Administrators
 * With File Type & File Size Poster Upload Validation & MySQLi Statements
 * ============================================================================
 * 
 * Capabilities:
 * 1. Admin RBAC Authorization Guard (Restricted to 'admin' users)
 * 2. ADD Event (MySQLi Parameterized INSERT + Multipart Poster Upload)
 * 3. VIEW Event (Interactive modal with full-size poster preview & metadata)
 * 4. UPDATE Event (MySQLi Parameterized UPDATE with optional poster replacement)
 * 5. DELETE Event (MySQLi Parameterized DELETE with safe poster cleanup)
 * 6. POSTER UPLOAD VALIDATION:
 *    - Strict File Type Validation (MIME type check: image/jpeg, image/png, image/webp, image/gif)
 *    - Strict File Extension Validation (.jpg, .jpeg, .png, .webp, .gif)
 *    - Strict File Size Validation (Max 2MB with explicit rejection message)
 *    - Cryptographically randomized safe file naming (prevents traversal / overwrite)
 * 7. Multi-criteria Search & Filtering (Keyword, Category, Status, Dynamic Sorting)
 * 8. Dual Synchronization: Keeps MySQL table and data/events.json in perfect sync
 * 9. Lab Offline Resilience: Graceful fallback to data/events.json if MySQL offline
 * ============================================================================
 */

date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth_session.php';

// Start session
startSecureSession();

// Strict RBAC: Admin only
requireLogin('admin', '../pages/login.php');
$currentUser = getCurrentUser();

$eventsJsonFile = __DIR__ . '/../data/events.json';
$uploadDir      = __DIR__ . '/../uploads/posters/';

// Ensure uploads/posters directory exists
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

// Obtain MySQLi Connection
$mysqli = getMySQLiConnection();
$isDbConnected = ($mysqli instanceof mysqli);

if ($isDbConnected) {
    try {
        ensureAuthColumnsExist($mysqli);
        ensureEventPosterColumnExists($mysqli);
    } catch (Exception $e) {
        error_log("Practical 12 DB Column Check: " . $e->getMessage());
    }
}

// ----------------------------------------------------------------------------
// POSTER FILE VALIDATION & UPLOAD HELPER
// Enforces File Type (MIME & Extension) + File Size (Max 2MB)
// ----------------------------------------------------------------------------
function handlePosterUpload(array $file, string $uploadDir, ?string $existingPoster = null): array {
    $maxBytes = 2 * 1024 * 1024; // 2 Megabytes
    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif'
    ];
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    // 1. Check if a file was actually submitted
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        // Return existing poster if editing, or default poster if new
        return [
            'success'  => true,
            'filename' => $existingPoster ?: 'default_poster.jpg',
            'uploaded' => false,
            'message'  => 'No new file uploaded. Kept current poster.'
        ];
    }

    // 2. Check for standard PHP upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE specified in the HTML form.',
            UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary upload directory on server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write upload file to server disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.'
        ];
        return [
            'success' => false,
            'error'   => $errorMessages[$file['error']] ?? 'Unknown upload error occurred.'
        ];
    }

    // 3. FILE SIZE VALIDATION (Max 2MB)
    $fileSize = (int)$file['size'];
    if ($fileSize <= 0) {
        return [
            'success' => false,
            'error'   => 'Uploaded file is empty (0 bytes). Please select a valid image.'
        ];
    }

    if ($fileSize > $maxBytes) {
        $actualMb = round($fileSize / (1024 * 1024), 2);
        return [
            'success' => false,
            'error'   => "File size validation failed: Selected poster is {$actualMb} MB. The maximum allowed limit is 2.00 MB."
        ];
    }

    // 4. FILE EXTENSION VALIDATION
    $originalName = (string)$file['name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExts, true)) {
        return [
            'success' => false,
            'error'   => "Invalid file extension (.{$extension}). Only JPG, JPEG, PNG, WEBP, and GIF image files are allowed."
        ];
    }

    // 5. MIME TYPE VALIDATION (Server-Side Content Inspection)
    $tmpPath = (string)$file['tmp_name'];
    $detectedMime = '';

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detectedMime = (string)finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
        }
    } elseif (function_exists('mime_content_type')) {
        $detectedMime = (string)mime_content_type($tmpPath);
    }

    // Fallback image size inspection if MIME tools missing
    if (empty($detectedMime) && function_exists('getimagesize')) {
        $imgInfo = @getimagesize($tmpPath);
        if ($imgInfo && !empty($imgInfo['mime'])) {
            $detectedMime = $imgInfo['mime'];
        }
    }

    if (!empty($detectedMime) && !array_key_exists($detectedMime, $allowedMimes)) {
        return [
            'success' => false,
            'error'   => "MIME type validation failed ({$detectedMime}). The uploaded file content does not match a valid image format."
        ];
    }

    // 6. SANITIZED RANDOM FILENAME GENERATION
    // Generate secure random file name: poster_YYYYMMDD_HHMMSS_8hex.ext
    $safeName = 'poster_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . ($allowedMimes[$detectedMime] ?? $extension);
    $targetPath = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $safeName;

    // 7. SECURE MOVE OPERATION
    if (!move_uploaded_file($tmpPath, $targetPath)) {
        return [
            'success' => false,
            'error'   => 'Failed to move uploaded poster to server storage directory. Check folder permissions.'
        ];
    }

    // Also replicate copy to images/events for multi-page static access
    $eventsImgDir = __DIR__ . '/../images/events/';
    if (is_dir($eventsImgDir)) {
        @copy($targetPath, $eventsImgDir . $safeName);
    }

    // 8. OPTIONAL CLEANUP OF PREVIOUS CUSTOM POSTER
    if (!empty($existingPoster) && $existingPoster !== 'default_poster.jpg' && !in_array($existingPoster, ['tech_summit.jpg', 'coding_challenge.jpg', 'cultural_night.jpg'])) {
        $oldFile = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . basename($existingPoster);
        if (file_exists($oldFile) && is_file($oldFile)) {
            @unlink($oldFile);
        }
    }

    return [
        'success'  => true,
        'filename' => $safeName,
        'uploaded' => true,
        'message'  => "Poster '{$originalName}' uploaded and validated successfully ({$actualMb = round($fileSize/1024, 1)} KB)."
    ];
}

// ----------------------------------------------------------------------------
// JSON DUAL SYNCHRONIZATION HELPER
// ----------------------------------------------------------------------------
function syncEventsJson(string $jsonFile, array $record, string $operation = 'add'): void {
    if (!file_exists($jsonFile)) return;
    $content = file_get_contents($jsonFile);
    $events  = !empty($content) ? json_decode($content, true) : [];
    if (!is_array($events)) $events = [];

    $targetId = (int)($record['event_id'] ?? 0);

    if ($operation === 'add') {
        $newId = 1;
        foreach ($events as $e) {
            if (($e['id'] ?? 0) >= $newId) {
                $newId = ($e['id'] ?? 0) + 1;
            }
        }
        $events[] = [
            'id'              => $targetId > 0 ? $targetId : $newId,
            'title'           => $record['title'] ?? '',
            'category'        => $record['category'] ?? 'General',
            'date'            => $record['event_date'] ?? date('Y-m-d'),
            'time'            => $record['event_time'] ?? '10:00 AM',
            'location'        => $record['location'] ?? 'Campus',
            'description'     => $record['description'] ?? '',
            'organizer'       => $record['organizer'] ?? 'StudentHub',
            'seats'           => (int)($record['total_seats'] ?? 100),
            'available_seats' => (int)($record['available_seats'] ?? ($record['total_seats'] ?? 100)),
            'status'          => $record['status'] ?? 'Upcoming',
            'poster_image'    => !empty($record['poster_image']) ? 'uploads/posters/' . basename($record['poster_image']) : 'uploads/posters/default_poster.jpg'
        ];
    } elseif ($operation === 'edit') {
        foreach ($events as &$e) {
            if ((int)($e['id'] ?? 0) === $targetId) {
                $e['title']           = $record['title'] ?? $e['title'];
                $e['category']        = $record['category'] ?? $e['category'];
                $e['date']            = $record['event_date'] ?? $e['date'];
                $e['time']            = $record['event_time'] ?? $e['time'];
                $e['location']        = $record['location'] ?? $e['location'];
                $e['description']     = $record['description'] ?? $e['description'];
                $e['organizer']       = $record['organizer'] ?? $e['organizer'];
                $e['seats']           = (int)($record['total_seats'] ?? $e['seats']);
                $e['available_seats'] = (int)($record['available_seats'] ?? $e['available_seats']);
                $e['status']          = $record['status'] ?? $e['status'];
                if (!empty($record['poster_image'])) {
                    $e['poster_image'] = 'uploads/posters/' . basename($record['poster_image']);
                }
                break;
            }
        }
        unset($e);
    } elseif ($operation === 'delete') {
        $events = array_values(array_filter($events, function ($e) use ($targetId) {
            return (int)($e['id'] ?? 0) !== $targetId;
        }));
    }

    file_put_contents($jsonFile, json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

// ----------------------------------------------------------------------------
// API ENDPOINT: AJAX Fetch Single Event Record (For Edit & View Modals)
// ----------------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'get_event') {
    header('Content-Type: application/json');
    $eventId = (int)($_GET['id'] ?? 0);

    if ($eventId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid event identifier.']);
        exit;
    }

    $foundEvent = null;

    if ($isDbConnected) {
        try {
            $stmt = $mysqli->prepare("
                SELECT event_id, title, category, event_date, event_time, location, 
                       description, organizer, total_seats, available_seats, status, poster_image, created_at 
                FROM events 
                WHERE event_id = ? 
                LIMIT 1
            ");
            if ($stmt) {
                $stmt->bind_param("i", $eventId);
                $stmt->execute();
                $res = $stmt->get_result();
                $foundEvent = $res->fetch_assoc();
                $stmt->close();
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'MySQLi query error: ' . $e->getMessage()]);
            exit;
        }
    }

    // Offline JSON Fallback
    if (!$foundEvent && file_exists($eventsJsonFile)) {
        $content = file_get_contents($eventsJsonFile);
        $events = json_decode($content, true) ?: [];
        foreach ($events as $ev) {
            if ((int)($ev['id'] ?? 0) === $eventId) {
                $foundEvent = [
                    'event_id'        => $ev['id'],
                    'title'           => $ev['title'] ?? 'Event',
                    'category'        => $ev['category'] ?? 'General',
                    'event_date'      => $ev['date'] ?? date('Y-m-d'),
                    'event_time'      => $ev['time'] ?? '10:00 AM',
                    'location'        => $ev['location'] ?? 'Campus',
                    'description'     => $ev['description'] ?? '',
                    'organizer'       => $ev['organizer'] ?? 'StudentHub',
                    'total_seats'     => $ev['seats'] ?? 100,
                    'available_seats' => $ev['available_seats'] ?? ($ev['seats'] ?? 100),
                    'status'          => $ev['status'] ?? 'Upcoming',
                    'poster_image'    => basename($ev['poster_image'] ?? 'default_poster.jpg'),
                    'created_at'      => date('Y-m-d H:i:s')
                ];
                break;
            }
        }
    }

    if ($foundEvent) {
        // Normalize poster image URL
        $posterName = basename($foundEvent['poster_image'] ?? 'default_poster.jpg');
        $foundEvent['poster_url'] = '../uploads/posters/' . $posterName;
        echo json_encode(['success' => true, 'event' => $foundEvent]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Event record not found.']);
    }
    exit;
}

// ----------------------------------------------------------------------------
// POST HANDLER: ADD, EDIT, DELETE (MySQLi Prepared Statements + Poster Upload)
// ----------------------------------------------------------------------------
$flashMessage = '';
$flashType    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ========================================================================
    // 1. ADD EVENT (MySQLi Parameterized INSERT + Poster Upload & Validation)
    // ========================================================================
    if ($action === 'add') {
        $title          = trim($_POST['title'] ?? '');
        $category       = trim($_POST['category'] ?? '');
        $eventDate      = trim($_POST['event_date'] ?? '');
        $eventTime      = trim($_POST['event_time'] ?? '');
        $location       = trim($_POST['location'] ?? '');
        $organizer      = trim($_POST['organizer'] ?? '');
        $totalSeats     = (int)($_POST['total_seats'] ?? 0);
        $availableSeats = isset($_POST['available_seats']) && $_POST['available_seats'] !== '' ? (int)$_POST['available_seats'] : $totalSeats;
        $status         = trim($_POST['status'] ?? 'Upcoming');
        $description    = trim($_POST['description'] ?? '');

        // Validation
        $errors = [];
        if (empty($title) || strlen($title) < 3) $errors[] = 'Event Title must be at least 3 characters.';
        if (empty($category)) $errors[] = 'Event Category is required.';
        if (empty($eventDate)) $errors[] = 'Event Date is required.';
        if (empty($eventTime)) $errors[] = 'Event Time is required.';
        if (empty($location)) $errors[] = 'Location / Venue is required.';
        if (empty($organizer)) $errors[] = 'Organizer name is required.';
        if ($totalSeats <= 0) $errors[] = 'Total Seats must be a positive integer.';
        if ($availableSeats < 0 || $availableSeats > $totalSeats) $errors[] = 'Available Seats must be between 0 and Total Seats.';
        if (empty($description)) $errors[] = 'Event description is required.';

        // Process Poster Upload & File Validation
        $posterFile = $_FILES['poster'] ?? null;
        $posterUploadResult = null;

        if ($posterFile && isset($posterFile['error']) && $posterFile['error'] !== UPLOAD_ERR_NO_FILE) {
            $posterUploadResult = handlePosterUpload($posterFile, $uploadDir);
            if (!$posterUploadResult['success']) {
                $errors[] = $posterUploadResult['error'];
            }
        }

        if (!empty($errors)) {
            $flashType = 'danger';
            $flashMessage = 'Validation Failed: ' . implode(' ', $errors);
        } else {
            $posterImageName = ($posterUploadResult && $posterUploadResult['success']) ? $posterUploadResult['filename'] : 'default_poster.jpg';
            $insertedId = 0;

            if ($isDbConnected) {
                try {
                    $insStmt = $mysqli->prepare("
                        INSERT INTO events 
                            (title, category, event_date, event_time, location, description, organizer, total_seats, available_seats, status, poster_image) 
                        VALUES 
                            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    if ($insStmt) {
                        $insStmt->bind_param(
                            "sssssssiiss",
                            $title,
                            $category,
                            $eventDate,
                            $eventTime,
                            $location,
                            $description,
                            $organizer,
                            $totalSeats,
                            $availableSeats,
                            $status,
                            $posterImageName
                        );

                        if ($insStmt->execute()) {
                            $insertedId = $insStmt->insert_id;
                            $flashType = 'success';
                            $posterNote = ($posterUploadResult && $posterUploadResult['uploaded']) ? "with verified poster (<code>{$posterImageName}</code>)" : "with default poster";
                            $flashMessage = "🎉 Event <strong>" . htmlspecialchars($title) . "</strong> created successfully (ID #{$insertedId}) {$posterNote} via MySQLi Prepared Statement!";

                            // Sync to JSON
                            syncEventsJson($eventsJsonFile, [
                                'event_id'        => $insertedId,
                                'title'           => $title,
                                'category'        => $category,
                                'event_date'      => $eventDate,
                                'event_time'      => $eventTime,
                                'location'        => $location,
                                'description'     => $description,
                                'organizer'       => $organizer,
                                'total_seats'     => $totalSeats,
                                'available_seats' => $availableSeats,
                                'status'          => $status,
                                'poster_image'    => $posterImageName
                            ], 'add');
                        } else {
                            $flashType = 'danger';
                            $flashMessage = "MySQLi INSERT execution failed: " . htmlspecialchars($insStmt->error);
                        }
                        $insStmt->close();
                    }
                } catch (Exception $e) {
                    $flashType = 'danger';
                    $flashMessage = "Database operation error: " . htmlspecialchars($e->getMessage());
                }
            } else {
                // Offline fallback mode
                syncEventsJson($eventsJsonFile, [
                    'title'           => $title,
                    'category'        => $category,
                    'event_date'      => $eventDate,
                    'event_time'      => $eventTime,
                    'location'        => $location,
                    'description'     => $description,
                    'organizer'       => $organizer,
                    'total_seats'     => $totalSeats,
                    'available_seats' => $availableSeats,
                    'status'          => $status,
                    'poster_image'    => $posterImageName
                ], 'add');

                $flashType = 'success';
                $flashMessage = "Event <strong>" . htmlspecialchars($title) . "</strong> saved to offline storage with poster validation. Connect MySQL for live table insertion.";
            }
        }
    }

    // ========================================================================
    // 2. UPDATE EVENT (MySQLi Parameterized UPDATE with Optional Poster Upload)
    // ========================================================================
    elseif ($action === 'edit') {
        $eventId        = (int)($_POST['event_id'] ?? 0);
        $title          = trim($_POST['title'] ?? '');
        $category       = trim($_POST['category'] ?? '');
        $eventDate      = trim($_POST['event_date'] ?? '');
        $eventTime      = trim($_POST['event_time'] ?? '');
        $location       = trim($_POST['location'] ?? '');
        $organizer      = trim($_POST['organizer'] ?? '');
        $totalSeats     = (int)($_POST['total_seats'] ?? 0);
        $availableSeats = (int)($_POST['available_seats'] ?? 0);
        $status         = trim($_POST['status'] ?? 'Upcoming');
        $description    = trim($_POST['description'] ?? '');
        $currentPoster  = trim($_POST['current_poster'] ?? 'default_poster.jpg');

        if ($eventId <= 0) {
            $flashType = 'danger';
            $flashMessage = 'Invalid event identifier for update.';
        } elseif (empty($title) || empty($category) || empty($eventDate) || empty($location) || $totalSeats <= 0) {
            $flashType = 'danger';
            $flashMessage = 'All required event fields must be populated.';
        } else {
            // Check if replacement poster was uploaded
            $posterFile = $_FILES['poster'] ?? null;
            $newPosterUploaded = false;
            $newPosterName = $currentPoster;

            if ($posterFile && isset($posterFile['error']) && $posterFile['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadRes = handlePosterUpload($posterFile, $uploadDir, $currentPoster);
                if (!$uploadRes['success']) {
                    $flashType = 'danger';
                    $flashMessage = 'Poster Upload Error: ' . $uploadRes['error'];
                } else {
                    $newPosterUploaded = true;
                    $newPosterName = $uploadRes['filename'];
                }
            }

            // Proceed with database update if no poster error
            if ($flashType !== 'danger') {
                if ($isDbConnected) {
                    try {
                        if ($newPosterUploaded) {
                            $updStmt = $mysqli->prepare("
                                UPDATE events 
                                SET title = ?, category = ?, event_date = ?, event_time = ?, location = ?, 
                                    description = ?, organizer = ?, total_seats = ?, available_seats = ?, 
                                    status = ?, poster_image = ? 
                                WHERE event_id = ?
                            ");
                            if ($updStmt) {
                                $updStmt->bind_param(
                                    "sssssssiissi",
                                    $title, $category, $eventDate, $eventTime, $location,
                                    $description, $organizer, $totalSeats, $availableSeats,
                                    $status, $newPosterName, $eventId
                                );
                            }
                        } else {
                            $updStmt = $mysqli->prepare("
                                UPDATE events 
                                SET title = ?, category = ?, event_date = ?, event_time = ?, location = ?, 
                                    description = ?, organizer = ?, total_seats = ?, available_seats = ?, 
                                    status = ? 
                                WHERE event_id = ?
                            ");
                            if ($updStmt) {
                                $updStmt->bind_param(
                                    "sssssssiisi",
                                    $title, $category, $eventDate, $eventTime, $location,
                                    $description, $organizer, $totalSeats, $availableSeats,
                                    $status, $eventId
                                );
                            }
                        }

                        if ($updStmt && $updStmt->execute()) {
                            $flashType = 'success';
                            $posterText = $newPosterUploaded ? " and replaced poster (<code>{$newPosterName}</code>)" : "";
                            $flashMessage = "✏️ Event <strong>" . htmlspecialchars($title) . "</strong> (ID #{$eventId}) updated successfully{$posterText} via MySQLi Prepared Statement!";
                            $updStmt->close();

                            // Sync to JSON
                            syncEventsJson($eventsJsonFile, [
                                'event_id'        => $eventId,
                                'title'           => $title,
                                'category'        => $category,
                                'event_date'      => $eventDate,
                                'event_time'      => $eventTime,
                                'location'        => $location,
                                'description'     => $description,
                                'organizer'       => $organizer,
                                'total_seats'     => $totalSeats,
                                'available_seats' => $availableSeats,
                                'status'          => $status,
                                'poster_image'    => $newPosterName
                            ], 'edit');
                        } else {
                            $flashType = 'danger';
                            $flashMessage = "MySQLi UPDATE execution failed: " . htmlspecialchars($mysqli->error);
                        }
                    } catch (Exception $e) {
                        $flashType = 'danger';
                        $flashMessage = "Database update error: " . htmlspecialchars($e->getMessage());
                    }
                } else {
                    // Offline JSON fallback
                    syncEventsJson($eventsJsonFile, [
                        'event_id'        => $eventId,
                        'title'           => $title,
                        'category'        => $category,
                        'event_date'      => $eventDate,
                        'event_time'      => $eventTime,
                        'location'        => $location,
                        'description'     => $description,
                        'organizer'       => $organizer,
                        'total_seats'     => $totalSeats,
                        'available_seats' => $availableSeats,
                        'status'          => $status,
                        'poster_image'    => $newPosterName
                    ], 'edit');

                    $flashType = 'success';
                    $flashMessage = "Updated event <strong>" . htmlspecialchars($title) . "</strong> in offline storage.";
                }
            }
        }
    }

    // ========================================================================
    // 3. DELETE EVENT (MySQLi Parameterized DELETE with Poster File Cleanup)
    // ========================================================================
    elseif ($action === 'delete') {
        $eventId    = (int)($_POST['event_id'] ?? 0);
        $eventTitle = trim($_POST['event_title'] ?? 'Event');
        $posterImg  = trim($_POST['poster_image'] ?? '');

        if ($eventId <= 0) {
            $flashType = 'danger';
            $flashMessage = 'Invalid event identifier for deletion.';
        } else {
            if ($isDbConnected) {
                try {
                    $delStmt = $mysqli->prepare("DELETE FROM events WHERE event_id = ?");
                    if ($delStmt) {
                        $delStmt->bind_param("i", $eventId);
                        if ($delStmt->execute()) {
                            if ($delStmt->affected_rows > 0) {
                                $flashType = 'success';
                                $flashMessage = "🗑️ Event <strong>" . htmlspecialchars($eventTitle) . "</strong> (ID #{$eventId}) was permanently deleted via MySQLi Prepared Statement.";

                                // Clean up uploaded poster if custom
                                if (!empty($posterImg) && $posterImg !== 'default_poster.jpg' && !in_array($posterImg, ['tech_summit.jpg', 'coding_challenge.jpg', 'cultural_night.jpg'])) {
                                    $pPath = $uploadDir . basename($posterImg);
                                    if (file_exists($pPath) && is_file($pPath)) {
                                        @unlink($pPath);
                                    }
                                }

                                syncEventsJson($eventsJsonFile, ['event_id' => $eventId], 'delete');
                            } else {
                                $flashType = 'warning';
                                $flashMessage = "No event found matching ID #{$eventId} for deletion.";
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
                syncEventsJson($eventsJsonFile, ['event_id' => $eventId], 'delete');
                $flashType = 'success';
                $flashMessage = "Deleted event <strong>" . htmlspecialchars($eventTitle) . "</strong> from offline storage.";
            }
        }
    }
}

// ----------------------------------------------------------------------------
// 4. QUERY EVENTS WITH DYNAMIC SEARCH, FILTERING, & SORTING (MySQLi)
// ----------------------------------------------------------------------------
$searchQuery    = trim($_GET['search'] ?? '');
$filterCategory = trim($_GET['category'] ?? '');
$filterStatus   = trim($_GET['status'] ?? '');
$sortBy         = trim($_GET['sort'] ?? 'dateAsc');

$eventsList = [];
$totalEventsCount = 0;
$allCategories = [];

if ($isDbConnected) {
    try {
        // Collect distinct categories for filter
        $catRes = $mysqli->query("SELECT DISTINCT category FROM events WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
        if ($catRes) {
            while ($cRow = $catRes->fetch_row()) {
                $allCategories[] = $cRow[0];
            }
            $catRes->free();
        }

        // Base Query
        $sql = "
            SELECT event_id, title, category, event_date, event_time, location, 
                   description, organizer, total_seats, available_seats, status, poster_image, created_at 
            FROM events 
            WHERE 1=1
        ";
        $bindTypes = "";
        $bindValues = [];

        // Search Filter
        if (!empty($searchQuery)) {
            $sql .= " AND (title LIKE ? OR description LIKE ? OR location LIKE ? OR organizer LIKE ?)";
            $searchParam = "%{$searchQuery}%";
            $bindTypes .= "ssss";
            $bindValues[] = $searchParam;
            $bindValues[] = $searchParam;
            $bindValues[] = $searchParam;
            $bindValues[] = $searchParam;
        }

        // Category Filter
        if (!empty($filterCategory) && $filterCategory !== 'all') {
            $sql .= " AND category = ?";
            $bindTypes .= "s";
            $bindValues[] = $filterCategory;
        }

        // Status Filter
        if (!empty($filterStatus) && $filterStatus !== 'all') {
            $sql .= " AND status = ?";
            $bindTypes .= "s";
            $bindValues[] = $filterStatus;
        }

        // Sorting
        switch ($sortBy) {
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
        if ($stmt) {
            if (!empty($bindTypes) && !empty($bindValues)) {
                $stmt->bind_param($bindTypes, ...$bindValues);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $eventsList[] = $row;
            }
            $stmt->close();
        }

        // Total count
        $cntRes = $mysqli->query("SELECT COUNT(*) FROM events");
        if ($cntRes) {
            $totalEventsCount = (int)($cntRes->fetch_row()[0] ?? count($eventsList));
            $cntRes->free();
        }
    } catch (Exception $e) {
        error_log("Event Manager Query Exception: " . $e->getMessage());
    }
}

// Offline fallback if DB empty or unavailable
if (empty($eventsList) && file_exists($eventsJsonFile)) {
    $content = file_get_contents($eventsJsonFile);
    $rawEvents = json_decode($content, true) ?: [];

    foreach ($rawEvents as $re) {
        $category = $re['category'] ?? 'General';
        if (!in_array($category, $allCategories, true)) {
            $allCategories[] = $category;
        }

        // Apply filters
        $matchesSearch = true;
        if (!empty($searchQuery)) {
            $q = strtolower($searchQuery);
            $matchesSearch = (
                str_contains(strtolower($re['title'] ?? ''), $q) ||
                str_contains(strtolower($re['description'] ?? ''), $q) ||
                str_contains(strtolower($re['location'] ?? ''), $q) ||
                str_contains(strtolower($re['organizer'] ?? ''), $q)
            );
        }

        $matchesCategory = empty($filterCategory) || $filterCategory === 'all' || ($re['category'] ?? '') === $filterCategory;
        $matchesStatus   = empty($filterStatus) || $filterStatus === 'all' || ($re['status'] ?? 'Upcoming') === $filterStatus;

        if ($matchesSearch && $matchesCategory && $matchesStatus) {
            $eventsList[] = [
                'event_id'        => $re['id'] ?? 1,
                'title'           => $re['title'] ?? 'Event',
                'category'        => $re['category'] ?? 'General',
                'event_date'      => $re['date'] ?? date('Y-m-d'),
                'event_time'      => $re['time'] ?? '10:00 AM',
                'location'        => $re['location'] ?? 'Campus',
                'description'     => $re['description'] ?? '',
                'organizer'       => $re['organizer'] ?? 'StudentHub',
                'total_seats'     => $re['seats'] ?? 100,
                'available_seats' => $re['available_seats'] ?? ($re['seats'] ?? 100),
                'status'          => $re['status'] ?? 'Upcoming',
                'poster_image'    => basename($re['poster_image'] ?? 'default_poster.jpg'),
                'created_at'      => date('Y-m-d H:i:s')
            ];
        }
    }

    $totalEventsCount = count($rawEvents);

    // Sort offline events
    usort($eventsList, function ($a, $b) use ($sortBy) {
        if ($sortBy === 'dateDesc') return strcmp($b['event_date'], $a['event_date']);
        if ($sortBy === 'nameAsc')  return strcmp($a['title'], $b['title']);
        if ($sortBy === 'nameDesc') return strcmp($b['title'], $a['title']);
        if ($sortBy === 'seatsDesc') return $b['total_seats'] <=> $a['total_seats'];
        return strcmp($a['event_date'], $b['event_date']);
    });
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Practical 12: Event Management & Poster Uploads</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .event-hero {
            background: linear-gradient(135deg, #091e3a 0%, #2b3990 50%, #4a00e0 100%);
            color: white;
            padding: 26px 30px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: 0 10px 25px rgba(9, 30, 58, 0.25);
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
        .driver-mysql {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        .driver-offline {
            background: #fef3c7;
            color: #b45309;
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
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 12px;
            align-items: end;
        }
        @media (max-width: 900px) {
            .filter-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 600px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
        }
        .poster-thumb {
            width: 70px;
            height: 48px;
            border-radius: 6px;
            object-fit: cover;
            border: 1px solid #cbd5e1;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            cursor: pointer;
            transition: transform 0.2s;
            background: #f1f5f9;
        }
        .poster-thumb:hover {
            transform: scale(1.08);
        }
        .table-event-title {
            font-weight: 700;
            color: #1e293b;
            font-size: 14px;
            display: block;
        }
        .table-event-cat {
            font-size: 11px;
            color: #2563eb;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
            background: #fef9c3;
            color: #854d0e;
            border-color: #fde047;
        }
        .btn-edit:hover {
            background: #ca8a04;
            color: white;
        }
        .btn-delete {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fca5a5;
        }
        .btn-delete:hover {
            background: #dc2626;
            color: white;
        }
        .badge-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-upcoming {
            background: #dcfce7;
            color: #15803d;
        }
        .status-ongoing {
            background: #e0e7ff;
            color: #4338ca;
        }
        .status-completed {
            background: #f1f5f9;
            color: #475569;
        }
        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Modal Styles */
        .modal-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .modal-backdrop.active {
            display: flex;
        }
        .modal-card {
            background: white;
            border-radius: 12px;
            max-width: 650px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            animation: modalFadeIn 0.25s ease-out;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px) scale(0.96); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .modal-header {
            padding: 18px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
            border-radius: 12px 12px 0 0;
        }
        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            color: #1e293b;
        }
        .modal-close {
            background: transparent;
            border: none;
            font-size: 22px;
            line-height: 1;
            color: #64748b;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
        }
        .modal-close:hover {
            color: #0f172a;
            background: #e2e8f0;
        }
        .modal-body {
            padding: 24px;
        }
        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 580px) {
            .form-grid-2 {
                grid-template-columns: 1fr;
            }
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
        }
        .form-control {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        .form-control:focus {
            border-color: #2563eb;
            outline: none;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .poster-upload-zone {
            border: 2px dashed #94a3b8;
            border-radius: 8px;
            padding: 16px;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        .poster-upload-zone:hover {
            border-color: #2563eb;
            background: #eff6ff;
        }
        .poster-preview-box {
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 14px;
            background: white;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        .poster-preview-img {
            width: 90px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #cbd5e1;
        }
        .validation-hint {
            font-size: 11px;
            color: #64748b;
            margin-top: 4px;
            display: block;
        }
        .validation-error-text {
            color: #dc2626;
            font-weight: 600;
            font-size: 12px;
            margin-top: 4px;
            display: none;
        }
    </style>
</head>

<body>

<!-- ================= SITE HEADER ================= -->
<header class="site-header">
    <div class="logo-icon">🎓</div>
    <h1>StudentHub Events</h1>
    <p>PRACTICAL 12: Admin Event Management & Poster Uploads</p>
</header>

<!-- ================= NAVIGATION ================= -->
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
            <a href="../pages/dashboard.php">Dashboard</a>
            <a href="student_manager.php">Students (CRUD)</a>
            <a href="event_manager.php" class="active">Events (Admin CRUD)</a>
            <a href="../pages/events.html">Events (Public)</a>
            <a href="../pages/admin.php">Admin Console</a>
            <a href="db_dashboard.php">Database</a>
            <a href="../pages/contact.html">Contact</a>
        </div>

        <button class="theme-toggle" aria-label="Toggle dark mode">
            🌙
        </button>
    </div>
</nav>

<!-- ================= MAIN CONTENT ================= -->
<main>
    <div class="container" style="margin-top: 25px; margin-bottom: 50px;">

        <!-- FLASH NOTIFICATION ALERT -->
        <?php if (!empty($flashMessage)): ?>
            <div class="alert alert-<?php echo htmlspecialchars($flashType); ?>" style="margin-bottom: 22px; border-radius: 8px; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 12px rgba(0,0,0,0.06);">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 20px;">
                        <?php 
                        if ($flashType === 'success') echo '✅';
                        elseif ($flashType === 'danger') echo '❌';
                        elseif ($flashType === 'warning') echo '⚠️';
                        else echo 'ℹ️';
                        ?>
                    </span>
                    <span><?php echo $flashMessage; ?></span>
                </div>
                <button type="button" onclick="this.parentElement.style.display='none'" style="background: transparent; border: none; font-size: 18px; cursor: pointer; color: inherit;">×</button>
            </div>
        <?php endif; ?>

        <!-- HERO ADMIN HEADER -->
        <section class="event-hero">
            <div>
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <span style="font-size: 11px; font-weight: 700; letter-spacing: 1px; background: rgba(255,255,255,0.2); padding: 3px 10px; border-radius: 20px; text-transform: uppercase;">
                        Practical 12 • Admin Event Management
                    </span>
                    <?php if ($isDbConnected): ?>
                        <span class="driver-badge driver-mysql">🟢 MySQLi Connected</span>
                    <?php else: ?>
                        <span class="driver-badge driver-offline">🟡 Offline Lab Mode (events.json)</span>
                    <?php endif; ?>
                </div>
                <h2 style="margin: 8px 0 4px 0; font-size: 24px; color: #ffffff;">
                    Campus Events Administration & Poster Validation
                </h2>
                <p style="margin: 0; font-size: 13px; opacity: 0.9;">
                    Add, View, Update, and Delete campus events. Upload event posters with strict file type (JPG/PNG/WEBP) and file size (&le; 2MB) validation.
                </p>
            </div>

            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <a href="../pages/event_manager.html" class="btn" style="background: rgba(255,255,255,0.18); color: white; border: 1px solid rgba(255,255,255,0.4); font-size: 13px; font-weight: 600;">
                    ⚡ Live Server Preview (HTML)
                </a>
                <button type="button" class="btn" onclick="openAddModal()" style="background: #10b981; color: white; font-weight: 700; border: none; font-size: 13px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);">
                    + Add New Event
                </button>
            </div>
        </section>

        <!-- KPI METRICS -->
        <section class="dashboard-grid" style="margin-bottom: 24px;">
            <div class="stat-card">
                <div class="number" style="color: #2563eb;"><?php echo $totalEventsCount; ?></div>
                <div class="label">Total Campus Events</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: #059669;"><?php echo count($eventsList); ?></div>
                <div class="label">Filtered Matches</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: #7c3aed;"><?php echo count($allCategories); ?></div>
                <div class="label">Event Categories</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: #d97706; font-size: 20px;">Max 2MB</div>
                <div class="label">Poster Upload Policy</div>
            </div>
        </section>

        <!-- SEARCH & MULTI-CRITERIA FILTERS -->
        <section class="filter-toolbar">
            <form method="GET" action="event_manager.php" id="filterForm">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <div style="font-weight: 700; font-size: 14px; color: #1e293b; display: flex; align-items: center; gap: 6px;">
                        <span>🔎</span> Search & Filter Events
                    </div>
                    <?php if (!empty($searchQuery) || (!empty($filterCategory) && $filterCategory !== 'all') || (!empty($filterStatus) && $filterStatus !== 'all') || $sortBy !== 'dateAsc'): ?>
                        <a href="event_manager.php" style="font-size: 12px; color: #dc2626; text-decoration: none; font-weight: 600;">
                            ✕ Clear All Filters
                        </a>
                    <?php endif; ?>
                </div>

                <div class="filter-grid">
                    <div>
                        <label for="searchInput" style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">Search Keyword</label>
                        <input type="text" id="searchInput" name="search" class="form-control" placeholder="Search by title, location, organizer..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                    </div>

                    <div>
                        <label for="categoryFilter" style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">Category</label>
                        <select id="categoryFilter" name="category" class="form-control" onchange="document.getElementById('filterForm').submit();">
                            <option value="all">All Categories</option>
                            <?php foreach ($allCategories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php if ($filterCategory === $cat) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="statusFilter" style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">Status</label>
                        <select id="statusFilter" name="status" class="form-control" onchange="document.getElementById('filterForm').submit();">
                            <option value="all">All Statuses</option>
                            <option value="Upcoming" <?php if ($filterStatus === 'Upcoming') echo 'selected'; ?>>Upcoming</option>
                            <option value="Ongoing" <?php if ($filterStatus === 'Ongoing') echo 'selected'; ?>>Ongoing</option>
                            <option value="Completed" <?php if ($filterStatus === 'Completed') echo 'selected'; ?>>Completed</option>
                            <option value="Cancelled" <?php if ($filterStatus === 'Cancelled') echo 'selected'; ?>>Cancelled</option>
                        </select>
                    </div>

                    <div>
                        <label for="sortFilter" style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">Sort By</label>
                        <select id="sortFilter" name="sort" class="form-control" onchange="document.getElementById('filterForm').submit();">
                            <option value="dateAsc" <?php if ($sortBy === 'dateAsc') echo 'selected'; ?>>Date: Earliest First</option>
                            <option value="dateDesc" <?php if ($sortBy === 'dateDesc') echo 'selected'; ?>>Date: Latest First</option>
                            <option value="nameAsc" <?php if ($sortBy === 'nameAsc') echo 'selected'; ?>>Name: A - Z</option>
                            <option value="nameDesc" <?php if ($sortBy === 'nameDesc') echo 'selected'; ?>>Name: Z - A</option>
                            <option value="seatsDesc" <?php if ($sortBy === 'seatsDesc') echo 'selected'; ?>>Capacity: High to Low</option>
                        </select>
                    </div>

                    <div>
                        <button type="submit" class="btn btn-primary" style="padding: 9px 18px; font-size: 13px; font-weight: 600;">
                            Apply
                        </button>
                    </div>
                </div>
            </form>
        </section>

        <!-- EVENTS DATA TABLE -->
        <section class="card" style="padding: 22px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); border-radius: 10px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h3 style="margin: 0; color: #1e293b; font-size: 17px;">
                        🎪 Campus Events Directory
                    </h3>
                    <p style="margin: 3px 0 0 0; font-size: 13px; color: #64748b;">
                        Showing <?php echo count($eventsList); ?> of <?php echo $totalEventsCount; ?> registered events
                    </p>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table class="records-table" style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; text-align: left;">
                            <th style="padding: 12px 14px; width: 80px;">Poster</th>
                            <th style="padding: 12px 14px;">Event Details</th>
                            <th style="padding: 12px 14px;">Schedule</th>
                            <th style="padding: 12px 14px;">Location & Organizer</th>
                            <th style="padding: 12px 14px;">Seats</th>
                            <th style="padding: 12px 14px;">Status</th>
                            <th style="padding: 12px 14px; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($eventsList)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 36px; color: #94a3b8;">
                                    <div style="font-size: 32px; margin-bottom: 8px;">🎪</div>
                                    <div style="font-weight: 600; font-size: 15px; color: #475569;">No events match the selected criteria</div>
                                    <p style="margin: 4px 0 0 0; font-size: 12px;">Try adjusting your keyword, category, or status filter.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($eventsList as $ev): ?>
                                <?php 
                                $posterFile = basename($ev['poster_image'] ?? 'default_poster.jpg');
                                $posterPath = '../uploads/posters/' . $posterFile;
                                $statusClass = 'status-' . strtolower($ev['status'] ?? 'upcoming');
                                ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <!-- POSTER THUMBNAIL -->
                                    <td style="padding: 12px 14px;">
                                        <img src="<?php echo htmlspecialchars($posterPath); ?>" 
                                             alt="<?php echo htmlspecialchars($ev['title']); ?>" 
                                             class="poster-thumb"
                                             title="Click to view full poster"
                                             onclick="openViewModal(<?php echo (int)$ev['event_id']; ?>)"
                                             onerror="this.src='../uploads/posters/default_poster.jpg'">
                                    </td>

                                    <!-- TITLE & CATEGORY -->
                                    <td style="padding: 12px 14px;">
                                        <span class="table-event-title"><?php echo htmlspecialchars($ev['title']); ?></span>
                                        <span class="table-event-cat"><?php echo htmlspecialchars($ev['category']); ?></span>
                                        <div style="color: #64748b; font-size: 12px; margin-top: 2px; max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?php echo htmlspecialchars($ev['description']); ?>
                                        </div>
                                    </td>

                                    <!-- SCHEDULE -->
                                    <td style="padding: 12px 14px; white-space: nowrap;">
                                        <div style="font-weight: 600; color: #1e293b;">
                                            📅 <?php echo date('d M Y', strtotime($ev['event_date'])); ?>
                                        </div>
                                        <div style="color: #64748b; font-size: 12px; margin-top: 2px;">
                                            ⏰ <?php echo htmlspecialchars($ev['event_time']); ?>
                                        </div>
                                    </td>

                                    <!-- LOCATION & ORGANIZER -->
                                    <td style="padding: 12px 14px;">
                                        <div style="font-weight: 500; color: #1e293b;">
                                            📍 <?php echo htmlspecialchars($ev['location']); ?>
                                        </div>
                                        <div style="color: #64748b; font-size: 12px; margin-top: 2px;">
                                            👤 <?php echo htmlspecialchars($ev['organizer']); ?>
                                        </div>
                                    </td>

                                    <!-- SEATS -->
                                    <td style="padding: 12px 14px;">
                                        <div style="font-weight: 600;">
                                            <?php echo (int)($ev['available_seats'] ?? $ev['total_seats']); ?> / <?php echo (int)$ev['total_seats']; ?>
                                        </div>
                                        <div style="font-size: 11px; color: #64748b;">available seats</div>
                                    </td>

                                    <!-- STATUS -->
                                    <td style="padding: 12px 14px;">
                                        <span class="badge-status <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($ev['status'] ?? 'Upcoming'); ?>
                                        </span>
                                    </td>

                                    <!-- ACTIONS -->
                                    <td style="padding: 12px 14px; text-align: center;">
                                        <div class="action-btn-group" style="justify-content: center;">
                                            <button type="button" class="btn-action btn-view" title="View Event Profile & Poster" onclick="openViewModal(<?php echo (int)$ev['event_id']; ?>)">
                                                👁️ View
                                            </button>
                                            <button type="button" class="btn-action btn-edit" title="Edit Event Details or Replace Poster" onclick="openEditModal(<?php echo (int)$ev['event_id']; ?>)">
                                                ✏️ Edit
                                            </button>
                                            <button type="button" class="btn-action btn-delete" title="Delete Event Record" onclick="openDeleteModal(<?php echo (int)$ev['event_id']; ?>, '<?php echo addslashes($ev['title']); ?>', '<?php echo htmlspecialchars($posterFile); ?>')">
                                                🗑️ Delete
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

<!-- ========================================================================= -->
<!-- 1. ADD EVENT MODAL (With File Size & File Type Client-Side Validator)       -->
<!-- ========================================================================= -->
<div id="addModal" class="modal-backdrop">
    <div class="modal-card">
        <div class="modal-header">
            <h3>🎪 Add New Campus Event</h3>
            <button type="button" class="modal-close" onclick="closeModal('addModal')">✕</button>
        </div>

        <form method="POST" action="event_manager.php" enctype="multipart/form-data" onsubmit="return validateEventForm(this, 'addPosterInput')">
            <input type="hidden" name="action" value="add">

            <div class="modal-body">
                <div class="form-group">
                    <label for="addTitle">Event Title *</label>
                    <input type="text" id="addTitle" name="title" class="form-control" placeholder="e.g. National Hackathon 2026" required minlength="3">
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="addCategory">Category *</label>
                        <select id="addCategory" name="category" class="form-control" required>
                            <option value="">Select Category</option>
                            <option value="Technology">Technology</option>
                            <option value="Competition">Competition</option>
                            <option value="Workshop">Workshop</option>
                            <option value="Sports">Sports</option>
                            <option value="Cultural">Cultural</option>
                            <option value="Career">Career</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="addStatus">Status *</label>
                        <select id="addStatus" name="status" class="form-control" required>
                            <option value="Upcoming" selected>Upcoming</option>
                            <option value="Ongoing">Ongoing</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="addDate">Event Date *</label>
                        <input type="date" id="addDate" name="event_date" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="addTime">Event Time *</label>
                        <input type="text" id="addTime" name="event_time" class="form-control" placeholder="e.g. 10:00 AM" required>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="addLocation">Venue / Location *</label>
                        <input type="text" id="addLocation" name="location" class="form-control" placeholder="e.g. Main Auditorium" required>
                    </div>

                    <div class="form-group">
                        <label for="addOrganizer">Organizing Body / Department *</label>
                        <input type="text" id="addOrganizer" name="organizer" class="form-control" placeholder="e.g. Computer Engineering Dept" required>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="addSeats">Total Capacity (Seats) *</label>
                        <input type="number" id="addSeats" name="total_seats" class="form-control" min="1" max="5000" value="100" required>
                    </div>

                    <div class="form-group">
                        <label for="addAvailSeats">Available Seats</label>
                        <input type="number" id="addAvailSeats" name="available_seats" class="form-control" min="0" max="5000" value="100">
                    </div>
                </div>

                <div class="form-group">
                    <label for="addDesc">Event Description *</label>
                    <textarea id="addDesc" name="description" class="form-control" rows="3" placeholder="Describe the objectives, agenda, and participant benefits..." required></textarea>
                </div>

                <!-- POSTER UPLOAD WITH FILE VALIDATION -->
                <div class="form-group">
                    <label>🖼️ Event Poster Upload (File Size & Type Validated)</label>
                    <div class="poster-upload-zone" onclick="document.getElementById('addPosterInput').click()">
                        <div style="font-size: 26px;">📁</div>
                        <div style="font-weight: 600; font-size: 13px; color: #1e293b; margin-top: 4px;">
                            Click to select an event poster image
                        </div>
                        <span class="validation-hint">
                            Allowed formats: <strong>JPG, JPEG, PNG, WEBP, GIF</strong> • Max size: <strong>2.0 MB</strong>
                        </span>
                    </div>

                    <input type="file" id="addPosterInput" name="poster" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif" style="display: none;" onchange="previewPoster(this, 'addPreviewContainer', 'addPreviewImg', 'addPreviewName', 'addPreviewError')">
                    
                    <div id="addPreviewError" class="validation-error-text"></div>

                    <div id="addPreviewContainer" class="poster-preview-box" style="display: none;">
                        <img id="addPreviewImg" src="" alt="Poster preview" class="poster-preview-img">
                        <div style="flex-grow: 1;">
                            <div id="addPreviewName" style="font-size: 13px; font-weight: 600; color: #1e293b;"></div>
                            <div style="font-size: 11px; color: #15803d; font-weight: 600;">✓ Validated & ready for upload</div>
                        </div>
                        <button type="button" onclick="clearPosterInput('addPosterInput', 'addPreviewContainer')" style="background: none; border: none; color: #dc2626; cursor: pointer; font-size: 16px;">✕</button>
                    </div>
                </div>
            </div>

            <div style="padding: 16px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; border-radius: 0 0 12px 12px; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background: #10b981; border: none; font-weight: 700;">Save & Publish Event</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 2. EDIT EVENT MODAL (Supports Modifying Fields & Optional Poster Replace)  -->
<!-- ========================================================================= -->
<div id="editModal" class="modal-backdrop">
    <div class="modal-card">
        <div class="modal-header">
            <h3>✏️ Edit Campus Event</h3>
            <button type="button" class="modal-close" onclick="closeModal('editModal')">✕</button>
        </div>

        <form method="POST" action="event_manager.php" enctype="multipart/form-data" onsubmit="return validateEventForm(this, 'editPosterInput')">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" id="editId" name="event_id" value="">
            <input type="hidden" id="editCurrentPoster" name="current_poster" value="">

            <div class="modal-body">
                <div class="form-group">
                    <label for="editTitle">Event Title *</label>
                    <input type="text" id="editTitle" name="title" class="form-control" required minlength="3">
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="editCategory">Category *</label>
                        <select id="editCategory" name="category" class="form-control" required>
                            <option value="Technology">Technology</option>
                            <option value="Competition">Competition</option>
                            <option value="Workshop">Workshop</option>
                            <option value="Sports">Sports</option>
                            <option value="Cultural">Cultural</option>
                            <option value="Career">Career</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="editStatus">Status *</label>
                        <select id="editStatus" name="status" class="form-control" required>
                            <option value="Upcoming">Upcoming</option>
                            <option value="Ongoing">Ongoing</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="editDate">Event Date *</label>
                        <input type="date" id="editDate" name="event_date" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="editTime">Event Time *</label>
                        <input type="text" id="editTime" name="event_time" class="form-control" required>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="editLocation">Venue / Location *</label>
                        <input type="text" id="editLocation" name="location" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="editOrganizer">Organizing Body *</label>
                        <input type="text" id="editOrganizer" name="organizer" class="form-control" required>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="editSeats">Total Capacity *</label>
                        <input type="number" id="editSeats" name="total_seats" class="form-control" min="1" required>
                    </div>

                    <div class="form-group">
                        <label for="editAvailSeats">Available Seats</label>
                        <input type="number" id="editAvailSeats" name="available_seats" class="form-control" min="0">
                    </div>
                </div>

                <div class="form-group">
                    <label for="editDesc">Event Description *</label>
                    <textarea id="editDesc" name="description" class="form-control" rows="3" required></textarea>
                </div>

                <!-- POSTER REPLACEMENT ZONE -->
                <div class="form-group">
                    <label>🖼️ Replace Event Poster (Optional)</label>
                    <div class="poster-upload-zone" onclick="document.getElementById('editPosterInput').click()">
                        <div style="font-size: 24px;">🔄</div>
                        <div style="font-weight: 600; font-size: 13px; color: #1e293b; margin-top: 4px;">
                            Click to upload new poster (Leaves current poster if unchanged)
                        </div>
                        <span class="validation-hint">Max size: <strong>2.0 MB</strong> • Formats: <strong>JPG, PNG, WEBP, GIF</strong></span>
                    </div>

                    <input type="file" id="editPosterInput" name="poster" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif" style="display: none;" onchange="previewPoster(this, 'editPreviewContainer', 'editPreviewImg', 'editPreviewName', 'editPreviewError')">
                    
                    <div id="editPreviewError" class="validation-error-text"></div>

                    <!-- Current & New Poster Preview -->
                    <div id="editPreviewContainer" class="poster-preview-box">
                        <img id="editPreviewImg" src="../uploads/posters/default_poster.jpg" alt="Poster preview" class="poster-preview-img">
                        <div style="flex-grow: 1;">
                            <div id="editPreviewName" style="font-size: 13px; font-weight: 600; color: #1e293b;">Current Poster</div>
                            <div style="font-size: 11px; color: #64748b;">Select a file above to replace this image</div>
                        </div>
                    </div>
                </div>
            </div>

            <div style="padding: 16px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; border-radius: 0 0 12px 12px; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="font-weight: 700;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 3. VIEW EVENT PROFILE MODAL (With Large High-Res Poster)                  -->
<!-- ========================================================================= -->
<div id="viewModal" class="modal-backdrop">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="viewModalHeader">Event Profile</h3>
            <button type="button" class="modal-close" onclick="closeModal('viewModal')">✕</button>
        </div>

        <div class="modal-body" style="padding: 20px 24px;">
            <!-- LARGE POSTER -->
            <div style="width: 100%; height: 240px; border-radius: 8px; overflow: hidden; background: #0f172a; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.15); display: flex; align-items: center; justify-content: center;">
                <img id="viewPosterImg" src="" alt="Event Poster" style="width: 100%; height: 100%; object-fit: cover;">
            </div>

            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 12px; flex-wrap: wrap;">
                <div>
                    <h2 id="viewTitle" style="margin: 0; font-size: 20px; color: #1e293b;"></h2>
                    <div style="display: flex; gap: 8px; align-items: center; margin-top: 4px;">
                        <span id="viewCategoryBadge" class="table-event-cat" style="background: #eff6ff; padding: 2px 8px; border-radius: 4px;"></span>
                        <span id="viewStatusBadge" class="badge-status"></span>
                    </div>
                </div>
                <div style="text-align: right;">
                    <div id="viewSeats" style="font-size: 18px; font-weight: 700; color: #2563eb;"></div>
                    <div style="font-size: 11px; color: #64748b;">Available Seats</div>
                </div>
            </div>

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; margin-bottom: 16px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 13px;">
                    <div>📅 <strong id="viewDate"></strong></div>
                    <div>⏰ <strong id="viewTime"></strong></div>
                    <div>📍 <strong id="viewLocation"></strong></div>
                    <div>👤 <strong id="viewOrganizer"></strong></div>
                </div>
            </div>

            <h4 style="margin: 0 0 6px 0; font-size: 13px; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">About the Event</h4>
            <p id="viewDescription" style="margin: 0; font-size: 14px; line-height: 1.6; color: #334155;"></p>
        </div>

        <div style="padding: 14px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; border-radius: 0 0 12px 12px; display: flex; justify-content: flex-end;">
            <button type="button" class="btn btn-secondary" onclick="closeModal('viewModal')">Close</button>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 4. DELETE CONFIRMATION MODAL                                              -->
<!-- ========================================================================= -->
<div id="deleteModal" class="modal-backdrop">
    <div class="modal-card" style="max-width: 450px;">
        <div class="modal-header" style="background: #fee2e2;">
            <h3 style="color: #991b1b;">⚠️ Confirm Event Deletion</h3>
            <button type="button" class="modal-close" onclick="closeModal('deleteModal')">✕</button>
        </div>

        <form method="POST" action="event_manager.php">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" id="deleteId" name="event_id" value="">
            <input type="hidden" id="deleteTitle" name="event_title" value="">
            <input type="hidden" id="deletePoster" name="poster_image" value="">

            <div class="modal-body" style="text-align: center; padding: 26px 20px;">
                <div style="font-size: 42px; margin-bottom: 12px;">🗑️</div>
                <h3 style="margin: 0 0 8px 0; color: #1e293b;">Delete Event Record?</h3>
                <p style="margin: 0; color: #64748b; font-size: 13px;">
                    Are you sure you want to permanently delete event <strong id="deleteEventNameText" style="color: #1e293b;"></strong>?
                </p>
                <div style="margin-top: 12px; font-size: 12px; color: #dc2626; background: #fef2f2; padding: 8px 12px; border-radius: 6px; border: 1px solid #fecaca;">
                    This operation will remove the database record and delete any uploaded poster from the server.
                </div>
            </div>

            <div style="padding: 14px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; border-radius: 0 0 12px 12px; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background: #dc2626; border: none; font-weight: 700;">Permanently Delete</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- JAVASCRIPT: MODALS & FILE SIZE / FILE TYPE VALIDATION                     -->
<!-- ========================================================================= -->
<script>
    // Max file upload limit: 2 Megabytes
    const MAX_FILE_SIZE_BYTES = 2 * 1024 * 1024;
    const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    // Close any modal
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.remove('active');
    }

    // Open Add Modal
    function openAddModal() {
        document.getElementById('addModal').classList.add('active');
    }

    // Client-side File Validation & Live Thumbnail Preview
    function previewPoster(fileInput, containerId, imgId, nameId, errorId) {
        const errorEl = document.getElementById(errorId);
        const container = document.getElementById(containerId);
        const img = document.getElementById(imgId);
        const nameEl = document.getElementById(nameId);

        errorEl.style.display = 'none';
        errorEl.textContent = '';

        if (!fileInput.files || fileInput.files.length === 0) {
            return;
        }

        const file = fileInput.files[0];

        // 1. FILE EXTENSION VALIDATION
        const ext = file.name.split('.').pop().toLowerCase();
        if (!ALLOWED_EXTENSIONS.includes(ext)) {
            errorEl.textContent = `❌ Invalid file format (.${ext}). Only JPG, PNG, WEBP, and GIF image files are allowed.`;
            errorEl.style.display = 'block';
            fileInput.value = '';
            if (container) container.style.display = 'none';
            return;
        }

        // 2. FILE SIZE VALIDATION (Max 2MB)
        if (file.size > MAX_FILE_SIZE_BYTES) {
            const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
            errorEl.textContent = `❌ File size too large: Selected image is ${sizeMb} MB. Maximum allowed limit is 2.00 MB.`;
            errorEl.style.display = 'block';
            fileInput.value = '';
            if (container) container.style.display = 'none';
            return;
        }

        // 3. READ & DISPLAY LIVE THUMBNAIL
        const reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            nameEl.textContent = `${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
            container.style.display = 'flex';
        };
        reader.readAsDataURL(file);
    }

    // Clear input
    function clearPosterInput(inputId, containerId) {
        const input = document.getElementById(inputId);
        const container = document.getElementById(containerId);
        if (input) input.value = '';
        if (container) container.style.display = 'none';
    }

    // Form Submission Validation Guard
    function validateEventForm(form, inputId) {
        const input = document.getElementById(inputId);
        if (input && input.files && input.files.length > 0) {
            const file = input.files[0];
            const ext = file.name.split('.').pop().toLowerCase();

            if (!ALLOWED_EXTENSIONS.includes(ext)) {
                alert(`Upload Rejected: Invalid file type (.${ext}). Only JPG, PNG, WEBP, and GIF are allowed.`);
                return false;
            }

            if (file.size > MAX_FILE_SIZE_BYTES) {
                alert(`Upload Rejected: Selected file size (${(file.size / (1024 * 1024)).toFixed(2)} MB) exceeds the 2.0 MB maximum limit.`);
                return false;
            }
        }
        return true;
    }

    // Open View Modal (Fetch via AJAX)
    function openViewModal(eventId) {
        fetch(`event_manager.php?action=get_event&id=${eventId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.event) {
                    const ev = data.event;
                    document.getElementById('viewModalHeader').textContent = `🎪 ${ev.title}`;
                    document.getElementById('viewTitle').textContent = ev.title;
                    document.getElementById('viewCategoryBadge').textContent = ev.category;
                    document.getElementById('viewSeats').textContent = `${ev.available_seats || ev.total_seats} / ${ev.total_seats}`;
                    document.getElementById('viewDate').textContent = ev.event_date;
                    document.getElementById('viewTime').textContent = ev.event_time;
                    document.getElementById('viewLocation').textContent = ev.location;
                    document.getElementById('viewOrganizer').textContent = ev.organizer;
                    document.getElementById('viewDescription').textContent = ev.description;

                    const statusBadge = document.getElementById('viewStatusBadge');
                    statusBadge.textContent = ev.status;
                    statusBadge.className = `badge-status status-${ev.status.toLowerCase()}`;

                    const posterImg = document.getElementById('viewPosterImg');
                    posterImg.src = ev.poster_url;
                    posterImg.onerror = function() {
                        this.src = '../uploads/posters/default_poster.jpg';
                    };

                    document.getElementById('viewModal').classList.add('active');
                } else {
                    alert(data.message || 'Unable to load event details.');
                }
            })
            .catch(err => {
                console.error("View modal fetch error:", err);
                alert("Failed to communicate with server.");
            });
    }

    // Open Edit Modal (Fetch via AJAX)
    function openEditModal(eventId) {
        fetch(`event_manager.php?action=get_event&id=${eventId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.event) {
                    const ev = data.event;
                    document.getElementById('editId').value = ev.event_id;
                    document.getElementById('editTitle').value = ev.title;
                    document.getElementById('editCategory').value = ev.category;
                    document.getElementById('editStatus').value = ev.status;
                    document.getElementById('editDate').value = ev.event_date;
                    document.getElementById('editTime').value = ev.event_time;
                    document.getElementById('editLocation').value = ev.location;
                    document.getElementById('editOrganizer').value = ev.organizer;
                    document.getElementById('editSeats').value = ev.total_seats;
                    document.getElementById('editAvailSeats').value = ev.available_seats;
                    document.getElementById('editDesc').value = ev.description;

                    const posterName = ev.poster_image || 'default_poster.jpg';
                    document.getElementById('editCurrentPoster').value = posterName;
                    document.getElementById('editPreviewImg').src = ev.poster_url;
                    document.getElementById('editPreviewName').textContent = `Current: ${posterName}`;

                    document.getElementById('editModal').classList.add('active');
                } else {
                    alert(data.message || 'Unable to load event record for editing.');
                }
            })
            .catch(err => {
                console.error("Edit modal fetch error:", err);
                alert("Failed to communicate with server.");
            });
    }

    // Open Delete Modal
    function openDeleteModal(eventId, title, posterImage) {
        document.getElementById('deleteId').value = eventId;
        document.getElementById('deleteTitle').value = title;
        document.getElementById('deletePoster').value = posterImage;
        document.getElementById('deleteEventNameText').textContent = `"${title}" (ID #${eventId})`;
        document.getElementById('deleteModal').classList.add('active');
    }

    // Close on backdrop click
    document.querySelectorAll('.modal-backdrop').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
    });
</script>

</body>
</html>
