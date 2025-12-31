<?php
// yeah so this removes a satellite from user's watchlist
require_once '../includes/db_config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'method not allowed']);
    exit;
}

// get json input
$input = json_decode(file_get_contents('php://input'), true);
$satellite_id = $input['satellite_id'] ?? null;

// validate input - must be numeric
if (!is_numeric($satellite_id)) {
    echo json_encode(['success' => false, 'message' => 'invalid satellite id']);
    exit;
}

$user_id = $_SESSION['user_id'];

// remove from watchlist
$stmt = $conn->prepare("DELETE FROM watchlist WHERE user_id = ? AND satellite_id = ?");
if ($stmt) {
    $stmt->bind_param('ii', $user_id, $satellite_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0) {
        echo json_encode(['success' => true, 'message' => 'removed from watchlist']);
    } else {
        echo json_encode(['success' => false, 'message' => 'not in watchlist']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'database error']);
} 

