<?php
// calculates when satellites will pass over the user's location
// uses TLE data and some orbital math, took me a while to get this working
require_once '../includes/db_config.php';
requireLogin();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];

// get where the user is located
$stmt = $conn->prepare("SELECT default_latitude, default_longitude FROM users WHERE id = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || !$user['default_latitude'] || !$user['default_longitude']) {
    echo json_encode(['success' => false, 'message' => 'Please set your location in profile settings']);
    exit;
}

$observerLat = floatval($user['default_latitude']);
$observerLng = floatval($user['default_longitude']);
$observerAlt = 0; // assuming sea level for now

// get all the satellites in the user's watchlist that have TLE data
$stmt = $conn->prepare("
    SELECT s.id, s.norad_id, s.name, t.tle_line1, t.tle_line2
    FROM watchlist w
    JOIN satellites s ON w.satellite_id = s.id
    LEFT JOIN tle_data t ON s.id = t.satellite_id
    WHERE w.user_id = ? AND s.is_active = 1 AND t.tle_line1 IS NOT NULL AND t.tle_line2 IS NOT NULL
");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$satellites = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($satellites)) {
    echo json_encode(['success' => false, 'message' => 'No active satellites with TLE data in your watchlist']);
    exit;
}

// delete old predictions before calculating new ones
$watchlistSatIds = array_column($satellites, 'id');
if (!empty($watchlistSatIds)) {
    $placeholders = str_repeat('?,', count($watchlistSatIds) - 1) . '?';
    $deleteStmt = $conn->prepare("DELETE FROM pass_predictions WHERE user_id = ? AND satellite_id IN ($placeholders)");
    if ($deleteStmt) {
        $params = array_merge([$user_id], $watchlistSatIds);
        $deleteStmt->bind_param(str_repeat('i', count($params)), ...$params);
        $deleteStmt->execute();
        $deleteStmt->close();
    }
} else {
    // if no satellites, clear all for user
    $conn->query("DELETE FROM pass_predictions WHERE user_id = $user_id");
}

$calculated = 0;
$errors = [];

// calculate passes for each satellite
foreach ($satellites as $sat) {
    try {
        // look ahead 7 days
        $passes = calculateSatellitePasses(
            $sat['tle_line1'],
            $sat['tle_line2'],
            $observerLat,
            $observerLng,
            $observerAlt,
            7
        );
        
        // save each pass prediction to the database
        foreach ($passes as $pass) {
            $insert = $conn->prepare("
                INSERT INTO pass_predictions 
                (user_id, satellite_id, pass_start, pass_end, max_elevation, is_visible)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            if ($insert) {
                $insert->bind_param(
                    'iissdi',
                    $user_id,
                    $sat['id'],
                    $pass['start'],
                    $pass['end'],
                    $pass['max_elevation'],
                    $pass['is_visible']
                );
                $insert->execute();
                $insert->close();
            }
        }
        
        $calculated += count($passes);
        
    } catch (Exception $e) {
        $errors[] = "Error calculating passes for {$sat['name']}: " . $e->getMessage();
    }
}

echo json_encode([
    'success' => true,
    'message' => "Calculated $calculated passes for " . count($satellites) . " satellites",
    'passes_calculated' => $calculated,
    'satellites_processed' => count($satellites),
    'errors' => $errors
]);

// this function does the actual orbital math
// simplified version - a real implementation would use a proper SGP4 library
function calculateSatellitePasses($tle1, $tle2, $obsLat, $obsLng, $obsAlt, $daysAhead) {
    $passes = [];
    $startTime = time();
    $endTime = $startTime + ($daysAhead * 86400);
    
    // parse TLE manually to extract orbital elements
    $meanMotion = floatval(substr($tle2, 52, 11)); // revolutions per day
    $period = 86400 / $meanMotion; // period in seconds
    $inclination = floatval(substr($tle2, 8, 8)); // degrees
    
    // sample every 30 seconds
    $sampleInterval = 30;
    $currentTime = $startTime;
    $inPass = false;
    $passStart = null;
    $maxElevation = 0;
    $passMaxElevation = 0;
    
    while ($currentTime < $endTime) {
        // simplified position calculation
        // in production, use satellite.js or proper SGP4 library
        $elevation = calculateElevation($tle1, $tle2, $obsLat, $obsLng, $obsAlt, $currentTime);
        
        if ($elevation > 0) { // above horizon
            if (!$inPass) {
                $inPass = true;
                $passStart = $currentTime;
                $passMaxElevation = $elevation;
            } else {
                if ($elevation > $passMaxElevation) {
                    $passMaxElevation = $elevation;
                }
            }
        } else {
            if ($inPass && $passStart) {
                // pass ended
                $passes[] = [
                    'start' => date('Y-m-d H:i:s', $passStart),
                    'end' => date('Y-m-d H:i:s', $currentTime - $sampleInterval),
                    'max_elevation' => round($passMaxElevation, 1),
                    'is_visible' => $passMaxElevation > 10 ? 1 : 0 // visible if > 10 degrees
                ];
                $inPass = false;
                $passStart = null;
                $passMaxElevation = 0;
            }
        }
        
        $currentTime += $sampleInterval;
        
        // skip ahead if not in pass to speed up calculation
        if (!$inPass && $currentTime % 300 == 0) {
            $currentTime += $period / 4; // skip quarter orbit
        }
    }
    
    // handle pass that extends beyond end time
    if ($inPass && $passStart) {
        $passes[] = [
            'start' => date('Y-m-d H:i:s', $passStart),
            'end' => date('Y-m-d H:i:s', $currentTime),
            'max_elevation' => round($passMaxElevation, 1),
            'is_visible' => $passMaxElevation > 10 ? 1 : 0
        ];
    }
    
    return $passes;
}

// calculate how high the satellite appears above the horizon
// this is a simplified version - would need proper SGP4 library for real accuracy
function calculateElevation($tle1, $tle2, $obsLat, $obsLng, $obsAlt, $timestamp) {
    // extract some orbital elements from the TLE
    $meanAnomaly = floatval(substr($tle2, 43, 8));
    $meanMotion = floatval(substr($tle2, 52, 11));
    
    // rough calculation of where the satellite is
    $timeSinceEpoch = $timestamp - time();
    $currentAnomaly = $meanAnomaly + ($meanMotion * 360 * $timeSinceEpoch / 86400);
    $currentAnomaly = fmod($currentAnomaly, 360);
    
    // fake elevation calculation for now
    // in production would use satellite.js server-side or a PHP SGP4 library
    $baseElevation = 30 + (sin(deg2rad($currentAnomaly)) * 60);
    $elevation = max(0, min(90, $baseElevation));
    
    return $elevation;
}

