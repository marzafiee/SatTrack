<?php
require_once '../includes/db_config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$share_code = $input['share_code'] ?? null;

// validate share code format as at now, it should be 12 char alphanumeric
if (!$share_code || !preg_match('/^[a-f0-9]{12}$/', $share_code)) {
    echo json_encode(['success' => false, 'message' => 'invalid share code format']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // find the user who owns this share code
    $stmt = $conn->prepare("SELECT user_id FROM user_watchlist_shares WHERE share_code = ?");
    $stmt->bind_param('s', $share_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result->num_rows) {
        echo json_encode(['success' => false, 'message' => 'share code not found']);
        exit;
    }
    
    $owner = $result->fetch_assoc();
    $owner_id = $owner['user_id'];
    $stmt->close();
    
    // get all satellites from owner's watchlist
    $stmt = $conn->prepare("SELECT satellite_id FROM watchlist WHERE user_id = ?");
    $stmt->bind_param('i', $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $imported_count = 0;
    
    // add each satellite to current user's watchlist
    while ($row = $result->fetch_assoc()) {
        $sat_id = $row['satellite_id'];
        
        // try to insert and ignore if already exists
        $insert = $conn->prepare("INSERT IGNORE INTO watchlist (user_id, satellite_id) VALUES (?, ?)");
        $insert->bind_param('ii', $user_id, $sat_id);
        
        if ($insert->execute() && $insert->affected_rows > 0) {
            $imported_count++;
        }
        
        $insert->close();
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => "imported {$imported_count} satellites",
        'count' => $imported_count
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'database error']);
}