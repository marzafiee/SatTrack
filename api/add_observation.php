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

$satellite_id = $body['satellite_id'] ?? null;
$observed_at = $body['observed_at'] ?? null;
$visibility = isset($body['visibility']) ? (int)$body['visibility'] : null;
$notes = trim($body['notes'] ?? '');
$is_public = !empty($body['is_public']) ? 1 : 0;

if (!is_numeric($satellite_id)) {
    echo json_encode(['success' => false, 'message' => 'invalid satellite']);
    exit;
}

// validate observed_at
$dt = DateTime::createFromFormat('Y-m-d\TH:i', $observed_at);
if (!$dt) {
    echo json_encode(['success' => false, 'message' => 'invalid date/time']);
    exit;
}
$observed_at_db = $dt->format('Y-m-d H:i:s');

if ($visibility < 1 || $visibility > 5) {
    echo json_encode(['success' => false, 'message' => 'visibility must be 1-5']);
    exit;
}

if (strlen($notes) > 2000) {
    echo json_encode(['success' => false, 'message' => 'notes too long']);
    exit;
}

$user_id = $_SESSION['user_id'];

// ensure satellite exists
$stmt_check = $conn->prepare("SELECT id, name FROM satellites WHERE id = ?");
if ($stmt_check) {
    $stmt_check->bind_param('i', $satellite_id);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();
    if (!$res_check || !$res_check->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'satellite not found']);
        exit;
    }
    $stmt_check->close();
}

$stmt = $conn->prepare("INSERT INTO observations (user_id, satellite_id, observed_at, visibility_rating, notes, is_public) VALUES (?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'database error']);
    exit;
}
// types: i (user_id), i (satellite_id), s (observed_at), i (visibility), s (notes), i (is_public)
$stmt->bind_param('iisisi', $user_id, $satellite_id, $observed_at_db, $visibility, $notes, $is_public);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'failed to save observation']);
    $stmt->close();
    exit;
}
$inserted_id = $stmt->insert_id;
$stmt->close();

// return the new observation joined with user and satellite info
$stmt = $conn->prepare("SELECT o.*, s.name AS satellite_name, u.username FROM observations o JOIN satellites s ON o.satellite_id = s.id JOIN users u ON o.user_id = u.id WHERE o.id = ?");
if ($stmt) {
    $stmt->bind_param('i', $inserted_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row) {
        echo json_encode(['success' => true, 'observation' => $row, 'csrf_new' => generateCSRFToken()]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'unknown error']);
