<?php
// handles adding comments to observations
// supports nested replies too which was fun to figure out
require_once __DIR__ . '/../includes/db_config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'method not allowed']);
    exit;
}

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'login required']);
    exit;
}

// get the comment data from the request
$body = json_decode(file_get_contents('php://input'), true);
$csrf = $body['csrf'] ?? '';
if (!verifyCSRFToken($csrf)) {
    echo json_encode(['success' => false, 'message' => 'invalid csrf token']);
    exit;
}

$observation_id = isset($body['observation_id']) ? (int)$body['observation_id'] : 0;
$comment_text = trim($body['comment_text'] ?? '');
$parent_id = isset($body['parent_comment_id']) ? (int)$body['parent_comment_id'] : 0;

if ($observation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'invalid observation']);
    exit;
}

// make sure the comment isn't empty or too long
if (empty($comment_text) || strlen($comment_text) > 1000) {
    echo json_encode(['success' => false, 'message' => 'invalid comment']);
    exit;
}

$user_id = $_SESSION['user_id'];

// if this is a reply, make sure the parent comment is actually for this observation
if ($parent_id > 0) {
    $chk = $conn->prepare("SELECT observation_id FROM observation_comments WHERE id = ?");
    if ($chk) {
        $chk->bind_param('i', $parent_id);
        $chk->execute();
        $reschk = $chk->get_result();
        $row = $reschk ? $reschk->fetch_assoc() : null;
        $chk->close();
        if (!$row || (int)$row['observation_id'] !== $observation_id) {
            echo json_encode(['success' => false, 'message' => 'invalid parent comment']);
            exit;
        }
    }
}

if ($parent_id > 0) {
    $stmt = $conn->prepare("INSERT INTO observation_comments (user_id, observation_id, comment_text, parent_comment_id) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'database error']);
        exit;
    }
    $stmt->bind_param('iisi', $user_id, $observation_id, $comment_text, $parent_id);
} else {
    $stmt = $conn->prepare("INSERT INTO observation_comments (user_id, observation_id, comment_text) VALUES (?, ?, ?)");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'database error']);
        exit;
    }
    $stmt->bind_param('iis', $user_id, $observation_id, $comment_text);
}

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'failed to save comment']);
    $stmt->close();
    exit;
}

$inserted_id = $stmt->insert_id;
$stmt->close();

// get the comment with username
$stmt = $conn->prepare("SELECT c.*, u.username FROM observation_comments c JOIN users u ON c.user_id = u.id WHERE c.id = ?");
$stmt->bind_param('i', $inserted_id);
$stmt->execute();
$res = $stmt->get_result();
$comment = $res ? $res->fetch_assoc() : null;
$stmt->close();

if ($comment) {
    echo json_encode(['success' => true, 'comment' => $comment, 'csrf_new' => generateCSRFToken()]);
} else {
    echo json_encode(['success' => false, 'message' => 'unknown error']);
}

