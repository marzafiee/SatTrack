<?php
require_once '../includes/db_config.php';
requireLogin();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];

// get tle data for user's watchlist satellites
$stmt = $conn->prepare("
    SELECT s.id, s.norad_id, s.name, s.satellite_type, s.is_active, s.last_updated,
           t.tle_line1, t.tle_line2
    FROM watchlist w
    JOIN satellites s ON w.satellite_id = s.id
    LEFT JOIN tle_data t ON s.id = t.satellite_id
    WHERE w.user_id = ?
    ORDER BY w.added_at DESC
");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'database error']);
    exit;
}

$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

$satellites = [];
$inactiveSatellites = [];
while ($row = $result->fetch_assoc()) {
    // include all satellites, but separate active from inactive
    $satData = [
        'id' => $row['id'],
        'norad_id' => $row['norad_id'],
        'name' => $row['name'],
        'satellite_type' => $row['satellite_type'],
        'is_active' => (bool)($row['is_active'] ?? 1),
        'last_updated' => $row['last_updated'],
        'tle_line1' => $row['tle_line1'] ?? null,
        'tle_line2' => $row['tle_line2'] ?? null
    ];
    
    // if satellite has TLE data and is active, add to satellites array
    if ($satData['tle_line1'] && $satData['tle_line2'] && $satData['is_active']) {
        $satellites[] = $satData;
    } else {
        // inactive or missing TLE data
        $inactiveSatellites[] = $satData;
    }
}

$stmt->close();

echo json_encode([
    'success' => true,
    'satellites' => $satellites,
    'inactive_satellites' => $inactiveSatellites
]);