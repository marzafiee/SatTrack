<?php
require_once __DIR__ . '/../includes/db_config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'login required']);
    exit;
}

$token = generateCSRFToken();
if ($token) {
    echo json_encode(['success' => true, 'csrf' => $token]);
} else {
    echo json_encode(['success' => false, 'message' => 'failed to generate token']);
}
