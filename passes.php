<?php
require_once 'includes/db_config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// get user's location
$stmt = $conn->prepare("SELECT default_latitude, default_longitude FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res ? $res->fetch_assoc() : null;
    $stmt->close();
} else {
    $user = null;
}

$latitude = $user['default_latitude'] ?? null;
$longitude = $user['default_longitude'] ?? null;

// get user's watchlist satellites
$stmt = $conn->prepare("
    SELECT s.id, s.name, s.norad_id, s.satellite_type 
    FROM watchlist w 
    JOIN satellites s ON w.satellite_id = s.id 
    WHERE w.user_id = ? 
    ORDER BY s.name
");
$watchlist = [];
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $watchlist = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}

// get upcoming passes for watchlist satellites only
$upcoming_passes = [];
if (!empty($watchlist) && $latitude && $longitude) {
    // Get only passes for satellites in user's watchlist, future passes only
    $watchlistSatIds = array_column($watchlist, 'id');
    if (!empty($watchlistSatIds)) {
        $placeholders = str_repeat('?,', count($watchlistSatIds) - 1) . '?';
        // Group by satellite_id and pass_start to eliminate true duplicates
    $stmt = $conn->prepare("
            SELECT pp.id, pp.satellite_id, pp.pass_start, pp.pass_end, pp.max_elevation, pp.is_visible, 
                   s.name as satellite_name, s.norad_id 
        FROM pass_predictions pp
        JOIN satellites s ON pp.satellite_id = s.id
            WHERE pp.user_id = ? 
              AND pp.satellite_id IN ($placeholders)
              AND pp.pass_start > NOW()
            GROUP BY pp.satellite_id, pp.pass_start, pp.pass_end
        ORDER BY pp.pass_start ASC
        LIMIT 50
    ");
    if ($stmt) {
            $params = array_merge([$user_id], $watchlistSatIds);
            $types = str_repeat('i', count($params));
            $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $upcoming_passes = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        }
    }
} 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Passes - SatTrack</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .navbar { padding: 0.6rem 1rem; position: sticky; top: 0; z-index: 240; }
        .nav-brand { text-decoration: none !important; }
        .nav-brand::after { display: none !important; }
        .nav-links { gap: 1rem; }
        .nav-links a.active { color: var(--text-primary); font-weight: 500; }
        .nav-avatar-link { display:inline-flex; align-items:center; }
        .nav-avatar { width:36px; height:36px; border-radius:50%; background: var(--bg-card); display:inline-flex; align-items:center; justify-content:center; color: var(--text-primary); font-weight:600; margin-left:0.5rem; text-decoration:none; }
        
        .pass-card {
            padding: 1rem 1.25rem !important;
        }
        
        .pass-header {
            margin-bottom: 0.75rem;
        }
        
        .pass-header h3 {
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }
        
        .pass-time {
            display: flex;
            align-items: baseline;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border);
        }
        
        .time-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .time-value {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .pass-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.75rem;
        }
        
        .pass-info-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .info-value {
            font-size: 1rem;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .info-value.highlight {
            color: var(--accent);
            font-weight: 600;
        }
        
        .info-value.visible {
            color: var(--success);
        }
        
        .info-value.not-visible {
            color: var(--text-secondary);
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="nav-brand">SatTrack</a>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="passes.php" class="active">My Passes</a>
            <a href="observations.php">Observations</a>
            <a href="profile.php" class="nav-avatar-link" title="Profile"><span class="nav-avatar"><?= strtoupper(substr(($username ?? ''), 0, 1)) ?></span></a>
            <a href="logout.php">Logout (<?= escape($username) ?>)</a>
        </div>
    </nav>
    
    <div class="dashboard-container">
        <main class="main-content">
            <h1>Upcoming Passes</h1>
            
            <?php if (!$latitude || !$longitude): ?>
                <div class="error-box">
                    <p>No location set. Please update your profile with coordinates.</p>
                </div>
            <?php elseif (empty($watchlist)): ?>
                <div class="error-box">
                    <p>No satellites in watchlist. Add some from the dashboard!</p>
                </div>
            <?php elseif (empty($upcoming_passes)): ?>
                <div class="info-box">
                    <p>No upcoming passes calculated yet. Predictions will appear here once calculated.</p>
                </div>
            <?php else: ?>
                <div class="passes-list">
                    <?php foreach($upcoming_passes as $pass): ?>
                        <?php
                        $startTime = strtotime($pass['pass_start']);
                        $endTime = strtotime($pass['pass_end']);
                        $duration = round(($endTime - $startTime) / 60); // duration in minutes
                        
                        $now = time();
                        $isToday = date('Y-m-d', $startTime) === date('Y-m-d', $now);
                        $isTomorrow = date('Y-m-d', $startTime) === date('Y-m-d', strtotime('+1 day', $now));
                        
                        // Format date/time labels
                        if ($isToday) {
                            $timeLabel = 'Today';
                            $timeValue = date('g:i A', $startTime);
                            $formattedEnd = date('g:i A', $endTime);
                        } elseif ($isTomorrow) {
                            $timeLabel = 'Tomorrow';
                            $timeValue = date('g:i A', $startTime);
                            $formattedEnd = date('g:i A', $endTime);
                        } else {
                            $timeLabel = date('M j, Y', $startTime);
                            $timeValue = date('g:i A', $startTime);
                            $endDate = date('Y-m-d', $endTime);
                            $startDate = date('Y-m-d', $startTime);
                            if ($endDate === $startDate) {
                        $formattedEnd = date('g:i A', $endTime);
                            } else {
                                $formattedEnd = date('M j, g:i A', $endTime);
                            }
                        }
                        ?>
                        <div class="pass-card">
                            <div class="pass-header">
                                <h3><?= escape($pass['satellite_name']) ?></h3>
                                <span class="sat-type">NORAD: <?= escape($pass['norad_id']) ?></span>
                            </div>
                            <div class="pass-details">
                                <div class="pass-time">
                                    <span class="time-label"><?= $timeLabel ?></span>
                                    <span class="time-value"><?= $timeValue ?></span>
                                </div>
                                <div class="pass-info-grid">
                                    <div class="pass-info-item">
                                        <span class="info-label">Duration</span>
                                        <span class="info-value"><?= $duration ?> min</span>
                                    </div>
                                    <div class="pass-info-item">
                                        <span class="info-label">Max Elevation</span>
                                        <span class="info-value highlight"><?= escape($pass['max_elevation']) ?>°</span>
                                    </div>
                                    <div class="pass-info-item">
                                        <span class="info-label">Ends</span>
                                        <span class="info-value"><?= $formattedEnd ?></span>
                                    </div>
                                    <div class="pass-info-item">
                                        <span class="info-label">Visibility</span>
                                        <span class="info-value <?= $pass['is_visible'] ? 'visible' : 'not-visible' ?>">
                                            <?= $pass['is_visible'] ? '✓ Visible' : 'Not visible' ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>

