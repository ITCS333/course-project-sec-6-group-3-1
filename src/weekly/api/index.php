<?php
/**
 * Weekly Course Breakdown API
 *
 * RESTful API for CRUD operations on weekly course content and discussion
 * comments. Uses PDO to interact with the MySQL database defined in
 * schema.sql.
 *
 * Database Tables (ground truth: schema.sql):
 *
 * Table: weeks
 *   id          INT UNSIGNED  PRIMARY KEY AUTO_INCREMENT
 *   title       VARCHAR(200)  NOT NULL
 *   start_date  DATE          NOT NULL
 *   description TEXT
 *   links       TEXT          — JSON-encoded array of URL strings
 *   created_at  TIMESTAMP
 *   updated_at  TIMESTAMP
 *
 * Table: comments_week
 *   id          INT UNSIGNED  PRIMARY KEY AUTO_INCREMENT
 *   week_id     INT UNSIGNED  NOT NULL   — FK → weeks.id (ON DELETE CASCADE)
 *   author      VARCHAR(100)  NOT NULL
 *   text        TEXT          NOT NULL
 *   created_at  TIMESTAMP
 *
 * HTTP Methods Supported:
 *   GET    — Retrieve week(s) or comments
 *   POST   — Create a new week or comment
 *   PUT    — Update an existing week
 *   DELETE — Delete a week (cascade removes its comments) or a single comment
 *
 * URL scheme (all requests go to index.php):
 *
 *   Weeks:
 *     GET    ./api/index.php                  — list all weeks
 *     GET    ./api/index.php?id={id}           — get one week by integer id
 *     POST   ./api/index.php                  — create a new week
 *     PUT    ./api/index.php                  — update a week (id in JSON body)
 *     DELETE ./api/index.php?id={id}           — delete a week
 *
 *   Comments (action parameter selects the comments sub-resource):
 *     GET    ./api/index.php?action=comments&week_id={id}
 *                                             — list comments for a week
 *     POST   ./api/index.php?action=comment   — create a comment
 *     DELETE ./api/index.php?action=delete_comment&comment_id={id}
 *                                             — delete a single comment
 *
 * Query parameters for GET all weeks:
 *   search — filter rows where title LIKE or description LIKE the term
 *   sort   — column to sort by; allowed: title, start_date (default: start_date)
 *   order  — sort direction; allowed: asc, desc (default: asc)
 *
 * Response format: JSON
 *   Success: { "success": true,  "data": ... }
 *   Error:   { "success": false, "message": "..." }
 */

// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../common/db.php';

$db = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];

$rawData = file_get_contents('php://input');
$data    = json_decode($rawData, true) ?? [];

$action    = $_GET['action']     ?? null;
$id        = $_GET['id']         ?? null;
$weekId    = $_GET['week_id']    ?? null;
$commentId = $_GET['comment_id'] ?? null;

// ============================================================================
// WEEKS FUNCTIONS
// ============================================================================

/**
 * Get all weeks (with optional search and sort).
 */
function getAllWeeks(PDO $db): void
{
    $sql = "SELECT id, title, start_date, description, links, created_at FROM weeks";
    $params = [];

    $search = $_GET['search'] ?? '';
    if (!empty($search)) {
        $sql .= " WHERE title LIKE :search OR description LIKE :search";
        $params[':search'] = '%' . $search . '%';
    }

    $sort = $_GET['sort'] ?? 'start_date';
    if (!in_array($sort, ['title', 'start_date'])) {
        $sort = 'start_date';
    }

    $order = strtolower($_GET['order'] ?? 'asc');
    if (!in_array($order, ['asc', 'desc'])) {
        $order = 'asc';
    }

    $sql .= " ORDER BY $sort $order";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($weeks as &$row) {
        $row['links'] = json_decode($row['links'], true) ?? [];
    }

    sendResponse(['success' => true, 'data' => $weeks]);
}

/**
 * Get a single week by its integer primary key.
 */
function getWeekById(PDO $db, $id): void
{
    if (empty($id) || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing week ID'], 400);
    }

    $stmt = $db->prepare("SELECT id, title, start_date, description, links, created_at FROM weeks WHERE id = ?");
    $stmt->execute([$id]);
    $week = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($week) {
        $week['links'] = json_decode($week['links'], true) ?? [];
        sendResponse(['success' => true, 'data' => $week]);
    } else {
        sendResponse(['success' => false, 'message' => 'Week not found'], 404);
    }
}

/**
 * Create a new week.
 */
function createWeek(PDO $db, array $data): void
{
    $title = trim($data['title'] ?? '');
    $startDate = trim($data['start_date'] ?? '');

    if (empty($title) || empty($startDate)) {
        sendResponse(['success' => false, 'message' => 'Title and start_date are required'], 400);
    }

    if (!validateDate($startDate)) {
        sendResponse(['success' => false, 'message' => 'Invalid start_date format. Use YYYY-MM-DD'], 400);
    }

    $description = trim($data['description'] ?? '');
    
    $links = isset($data['links']) && is_array($data['links']) ? json_encode($data['links']) : json_encode([]);

    $stmt = $db->prepare("INSERT INTO weeks (title, start_date, description, links) VALUES (?, ?, ?, ?)");
    $stmt->execute([$title, $startDate, $description, $links]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Week created successfully', 'id' => $db->lastInsertId()], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create week'], 500);
    }
}

/**
 * Update an existing week.
 */
function updateWeek(PDO $db, array $data): void
{
    if (empty($data['id']) || !is_numeric($data['id'])) {
        sendResponse(['success' => false, 'message' => 'Valid week ID is required'], 400);
    }

    $id = $data['id'];

    $checkStmt = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $checkStmt->execute([$id]);
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Week not found'], 404);
    }

    $fields = [];
    $params = [];

    if (isset($data['title'])) {
        $fields[] = 'title = ?';
        $params[] = trim($data['title']);
    }

    if (isset($data['start_date'])) {
        $startDate = trim($data['start_date']);
        if (!validateDate($startDate)) {
            sendResponse(['success' => false, 'message' => 'Invalid start_date format'], 400);
        }
        $fields[] = 'start_date = ?';
        $params[] = $startDate;
    }

    if (isset($data['description'])) {
        $fields[] = 'description = ?';
        $params[] = trim($data['description']);
    }

    if (isset($data['links'])) {
        $fields[] = 'links = ?';
        $params[] = is_array($data['links']) ? json_encode($data['links']) : json_encode([]);
    }

    if (empty($fields)) {
        sendResponse(['success' => false, 'message' => 'No updatable fields provided'], 400);
    }

    $params[] = $id;
    $sql = "UPDATE weeks SET " . implode(', ', $fields) . " WHERE id = ?";
    
    $stmt = $db->prepare($sql);
    if ($stmt->execute($params)) {
        sendResponse(['success' => true, 'message' => 'Week updated successfully'], 200);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to update week'], 500);
    }
}

/**
 * Delete a week by integer id.
 */
function deleteWeek(PDO $db, $id): void
{
    if (empty($id) || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Valid week ID is required'], 400);
    }

    $checkStmt = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $checkStmt->execute([$id]);
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Week not found'], 404);
    }

    $stmt = $db->prepare("DELETE FROM weeks WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Week deleted successfully'], 200);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete week'], 500);
    }
}

// ============================================================================
// COMMENTS FUNCTIONS
// ============================================================================

/**
 * Get all comments for a specific week.
 */
function getCommentsByWeek(PDO $db, $weekId): void
{
    if (empty($weekId) || !is_numeric($weekId)) {
        sendResponse(['success' => false, 'message' => 'Valid week_id is required'], 400);
    }

    $stmt = $db->prepare("SELECT id, week_id, author, text, created_at FROM comments_week WHERE week_id = ? ORDER BY created_at ASC");
    $stmt->execute([$weekId]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(['success' => true, 'data' => $comments]);
}

/**
 * Create a new comment.
 */
function createComment(PDO $db, array $data): void
{
    $weekId = $data['week_id'] ?? null;
    $author = trim($data['author'] ?? '');
    $text = trim($data['text'] ?? '');

    if (empty($weekId) || empty($author) || empty($text)) {
        sendResponse(['success' => false, 'message' => 'week_id, author, and text are required'], 400);
    }

    if (!is_numeric($weekId)) {
        sendResponse(['success' => false, 'message' => 'week_id must be numeric'], 400);
    }

    $checkWeek = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $checkWeek->execute([$weekId]);
    if (!$checkWeek->fetch()) {
        sendResponse(['success' => false, 'message' => 'Week not found'], 404);
    }

    $stmt = $db->prepare("INSERT INTO comments_week (week_id, author, text) VALUES (?, ?, ?)");
    $stmt->execute([$weekId, $author, $text]);

    if ($stmt->rowCount() > 0) {
        $newId = $db->lastInsertId();
        
        $newCommentStmt = $db->prepare("SELECT id, week_id, author, text, created_at FROM comments_week WHERE id = ?");
        $newCommentStmt->execute([$newId]);
        $newComment = $newCommentStmt->fetch(PDO::FETCH_ASSOC);

        sendResponse([
            'success' => true, 
            'message' => 'Comment created successfully', 
            'id' => $newId, 
            'data' => $newComment
        ], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create comment'], 500);
    }
}

/**
 * Delete a single comment.
 */
function deleteComment(PDO $db, $commentId): void
{
    if (empty($commentId) || !is_numeric($commentId)) {
        sendResponse(['success' => false, 'message' => 'Valid comment_id is required'], 400);
    }

    $checkStmt = $db->prepare("SELECT id FROM comments_week WHERE id = ?");
    $checkStmt->execute([$commentId]);
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Comment not found'], 404);
    }

    $stmt = $db->prepare("DELETE FROM comments_week WHERE id = ?");
    $stmt->execute([$commentId]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Comment deleted successfully'], 200);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete comment'], 500);
    }
}

// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {
    if ($method === 'GET') {
        if ($action === 'comments') {
            getCommentsByWeek($db, $weekId);
        } elseif (!empty($id)) {
            getWeekById($db, $id);
        } else {
            getAllWeeks($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createWeek($db, $data);
        }
    } elseif ($method === 'PUT') {
        updateWeek($db, $data);
    } elseif ($method === 'DELETE') {
        if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        } else {
            deleteWeek($db, $id);
        }
    } else {
        sendResponse(['success' => false, 'message' => 'Method Not Allowed'], 405);
    }
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'Internal Server Error'], 500);
} catch (Exception $e) {
    error_log("General Error: " . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'Internal Server Error'], 500);
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Send a JSON response and stop execution.
 */
function sendResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

/**
 * Validate a date string against the "YYYY-MM-DD" format.
 */
function validateDate(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Sanitize a string input.
 */
function sanitizeInput(string $data): string
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
