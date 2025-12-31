<?php
require_once __DIR__ . '/../includes/db_config.php';
header('Content-Type: application/json');

$observation_id = isset($_GET['observation_id']) ? (int)$_GET['observation_id'] : 0;

if ($observation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'invalid observation']);
    exit;
}

$comments = [];
$stmt = $conn->prepare("SELECT c.*, u.username FROM observation_comments c JOIN users u ON c.user_id = u.id WHERE c.observation_id = ? ORDER BY c.created_at ASC");
if ($stmt) {
    $stmt->bind_param('i', $observation_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    // build nested tree
    $byId = [];
    $tree = [];
    foreach ($rows as $r) {
        $r['replies'] = [];
        $byId[$r['id']] = $r;
    }
    foreach ($byId as $id => $comment) {
        if (!empty($comment['parent_comment_id'])) {
            $pid = $comment['parent_comment_id'];
            if (isset($byId[$pid])) {
                $byId[$pid]['replies'][] = &$byId[$id];
            } else {
                $tree[] = &$byId[$id];
            }
        } else {
            $tree[] = &$byId[$id];
        }
    }

    $comments = $tree;
}

echo json_encode(['success' => true, 'comments' => $comments]);

