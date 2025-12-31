<?php
// adds a satellite to the user's watchlist
// basically like a favorites list
require_once '../includes/db_config.php';
requireLogin();

header('Content-Type: application/json');

// only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'method not allowed']);
    exit;
}

// get the satellite id from the request
$input = json_decode(file_get_contents('php://input'), true);
$satellite_id = $input['satellite_id'] ?? null;

// make sure it's a valid number
if (!is_numeric($satellite_id)) {
    echo json_encode(['success' => false, 'message' => 'invalid satellite id']);
    exit;
}

$user_id = $_SESSION['user_id'];

// check if the satellite actually exists and is active
$stmt = $conn->prepare("SELECT id, name, is_active FROM satellites WHERE id = ?");
if ($stmt) {
    $stmt->bind_param('i', $satellite_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'satellite not found']);
        exit;
    }
    if (isset($row['is_active']) && !$row['is_active']) {
        echo json_encode(['success' => false, 'message' => 'satellite appears inactive or decommissioned']);
        exit;
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'database error']);
    exit;
}

// add it to the watchlist
$stmt = $conn->prepare("INSERT INTO watchlist (user_id, satellite_id) VALUES (?, ?)");
if ($stmt) {
    $stmt->bind_param('ii', $user_id, $satellite_id);
    if ($stmt->execute()) {
        // get all the satellite info to send back to the frontend
        $stmt_s = $conn->prepare("SELECT id, name, norad_id, satellite_type, tle_line1, tle_line2 FROM satellites WHERE id = ?");
        $satellite = null;
        if ($stmt_s) {
            $stmt_s->bind_param('i', $satellite_id);
            $stmt_s->execute();
            $res_s = $stmt_s->get_result();
            $satellite = $res_s ? $res_s->fetch_assoc() : null;
            $stmt_s->close();
        }

        echo json_encode(['success' => true, 'message' => 'added to watchlist', 'satellite' => $satellite]);
    } else {
        // error code 1062 means duplicate entry
        if ($conn->errno == 1062) {
            echo json_encode(['success' => false, 'message' => 'already in watchlist']);
        } else {
            echo json_encode(['success' => false, 'message' => 'database error']);
        }
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'database error']);
} 

