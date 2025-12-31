<?php
require_once __DIR__ . '/../includes/db_config.php';
requireLogin();
header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);
if (!$body || empty($body['place'])) {
    echo json_encode(['success' => false, 'message' => 'missing place']);
    exit;
}
$place = trim($body['place']);

// call Nominatim to resolve place => lat/lon, try to get country/display name
$enc = urlencode($place);
$url = "https://nominatim.openstreetmap.org/search?q={$enc}&format=json&limit=1&addressdetails=1";
$opts = ['http' => ['header' => "User-Agent: SatTrack/1.0\r\n"]];
$context = stream_context_create($opts);
$json = @file_get_contents($url, false, $context);
if (!$json) {
    echo json_encode(['success' => false, 'message' => 'geocoding service unavailable']);
    exit;
}
$res = json_decode($json, true);
if (empty($res[0])) {
    echo json_encode(['success' => false, 'message' => 'location not found']);
    exit;
}
$loc = $res[0];
$lat = (float) $loc['lat'];
$lng = (float) $loc['lon'];
$display = $loc['display_name'] ?? $place;
$country = '';
if (!empty($loc['address'])) {
    $country = $loc['address']['country'] ?? '';
}

// save to user's profile
$stmt = $conn->prepare("UPDATE users SET default_latitude = ?, default_longitude = ? WHERE id = ?");
if ($stmt) {
    $stmt->bind_param('ddi', $lat, $lng, $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
}

echo json_encode(['success' => true, 'lat' => $lat, 'lng' => $lng, 'display' => $display, 'country' => $country]);
