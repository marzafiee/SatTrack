<?php
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

$body = json_decode(file_get_contents('php://input'), true);
$csrf = $body['csrf'] ?? '';
if (!verifyCSRFToken($csrf)) {
    echo json_encode(['success' => false, 'message' => 'invalid csrf token']);
    exit;
}

$observation_id = isset($body['observation_id']) ? (int)$body['observation_id'] : 0;
$user_id = $_SESSION['user_id'];

if ($observation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'invalid observation']);
    exit;
}

// check if already liked
$stmt = $conn->prepare("SELECT id FROM observation_likes WHERE user_id = ? AND observation_id = ?");
$stmt->bind_param('ii', $user_id, $observation_id);
$stmt->execute();
$res = $stmt->get_result();
$existing = $res->fetch_assoc();
$stmt->close();

if ($existing) {
    // unlike
    $stmt = $conn->prepare("DELETE FROM observation_likes WHERE user_id = ? AND observation_id = ?");
    $stmt->bind_param('ii', $user_id, $observation_id);
    $stmt->execute();
    $stmt->close();
    $liked = false;
} else {
    // like
    $stmt = $conn->prepare("INSERT INTO observation_likes (user_id, observation_id) VALUES (?, ?)");
    $stmt->bind_param('ii', $user_id, $observation_id);
    $stmt->execute();
    $stmt->close();
    $liked = true;
}

// get updated count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM observation_likes WHERE observation_id = ?");
$stmt->bind_param('i', $observation_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$like_count = $row['count'] ?? 0;
$stmt->close();

echo json_encode(['success' => true, 'liked' => $liked, 'like_count' => $like_count, 'csrf_new' => generateCSRFToken()]);

