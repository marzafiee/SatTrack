<?php
require_once 'includes/db_config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// get user's watchlist with satellite info
$stmt = $conn->prepare("
    SELECT s.id, s.name, s.norad_id, s.satellite_type 
    FROM watchlist w 
    JOIN satellites s ON w.satellite_id = s.id 
    WHERE w.user_id = ? 
    ORDER BY w.added_at DESC
");
$watchlist = [];
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $watchlist = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}

// get all satellites for browsing
$all_satellites = [];
$result = $conn->query("SELECT id, name, norad_id, satellite_type, is_active FROM satellites ORDER BY name");
if ($result) {
    $all_satellites = $result->fetch_all(MYSQLI_ASSOC);
}

// get user's location for cesium camera
$user_location = ['lat' => 6.1319, 'lng' => 1.2228]; // default lomé
$stmt = $conn->prepare("SELECT default_latitude, default_longitude FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        if ($row['default_latitude'] && $row['default_longitude']) {
            $user_location = ['lat' => $row['default_latitude'], 'lng' => $row['default_longitude']];
        }
    }
    $stmt->close();
}

// try to resolve a human-friendly location (country/city) from lat/lng for display
$user_location['display'] = "Lat: {$user_location['lat']}, Lng: {$user_location['lng']}";
$user_location['country'] = '';
try {
    $lat = urlencode($user_location['lat']);
    $lng = urlencode($user_location['lng']);
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lng}&zoom=10&addressdetails=1";
    $opts = ['http' => ['header' => "User-Agent: SatTrack/1.0\r\n"]];
    $context = stream_context_create($opts);
    $json = @file_get_contents($url, false, $context);
    if ($json) {
        $j = json_decode($json, true);
        if (!empty($j['address'])) {
            $addr = $j['address'];
            $country = $addr['country'] ?? '';
            $city = $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? '';
            $user_location['country'] = $country;
            $user_location['display'] = $country ? $country : ($city ? $city : $user_location['display']);
        }
    }
} catch (Exception $e) {
    // ignore and keep lat/lng
}

// generate shareable watchlist code if doesn't exist
$share_code = '';
$stmt = $conn->prepare("SELECT share_code FROM user_watchlist_shares WHERE user_id = ?");
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $share_code = $row['share_code'];
    } else {
        // create new share code
        $share_code = substr(bin2hex(random_bytes(8)), 0, 12);
        $insert = $conn->prepare("INSERT INTO user_watchlist_shares (user_id, share_code) VALUES (?, ?)");
        if ($insert) {
            $insert->bind_param('is', $user_id, $share_code);
            $insert->execute();
            $insert->close();
        }
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SatTrack</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <!-- cesium css -->
    <link rel="stylesheet" href="https://cesium.com/downloads/cesiumjs/releases/1.111/Build/Cesium/Widgets/widgets.css">
    <style>
        /* dashboard specific styles */
        /* compact dashboard layout (inspired by the image) */
        .navbar .nav-brand { font-size: 1.15rem; font-weight: 600; }
        .navbar .nav-links a { font-size: 0.95rem; color: var(--text-secondary); }

        :root { --navbar-height: 56px; --topbar-height: 56px; }
        .dashboard-layout {
            display: grid;
            grid-template-columns: var(--sidebar-width, 320px) 1fr; /* adjustable sidebar width */
            align-items: start; /* keep globe from stretching full height */
            height: calc(100vh - var(--navbar-height) - var(--topbar-height)); /* account for navbar + top-bar */
            background: var(--bg-primary);
        }

        /* top bar under navbar for controls and search (compact) */
        .top-bar {
            position: sticky;
            top: var(--navbar-height, 56px);
            z-index: 220;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.45rem 1rem;
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border);
            min-height: 56px;
        }
        .top-controls { display:flex; gap:0.5rem; align-items:center; }
        .top-center { display:flex; flex-direction:column; align-items:center; justify-content:center; flex:1; gap:4px; }
        .globe-slider-label { font-size: 0.95rem; color: var(--text-secondary); }
        #globeSizeSlider { width: 320px; }

        /* align top-bar to start after the watchlist sidebar on wide screens */
        @media (min-width: 900px) {
            .top-bar { padding-left: calc(1rem + var(--sidebar-width, 320px)); }
            .navbar { position: sticky; top: 0; z-index: 240; }
        }
        .top-search { display:flex; align-items:center; }
        .top-search .search-input { width: 300px; padding: 0.45rem 0.6rem; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-primary); color: white; font-size: 0.95rem; }

        /* watchlist header menu */
        .watchlist-header { display:flex; align-items:center; justify-content:space-between; gap:0.5rem; }
        .watchlist-header h2 { margin: 0; text-transform: none; font-size: 1rem; }
        .watchlist-menu { position: relative; }
        .watchlist-menu .menu { position: absolute; right: 0; top: 36px; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 6px; padding: 0.25rem; display:none; min-width: 180px; z-index: 60; }
        .watchlist-menu .menu button { display:block; width:100%; text-align:left; padding: 0.5rem; border:none; background:transparent; color: white; cursor:pointer; }
        .watchlist-menu .menu.show { display:block; }

        /* satellite search in sidebar */
        .location-block { margin-bottom: 1rem; }
        .sat-search { margin-bottom: 1rem; }
        .sat-search input { width: calc(100% - 80px); padding: 0.5rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-primary); color: white; }
        .sat-search .add-btn { width: 72px; padding: 0.5rem; }

        /* smaller control buttons */
        .control-btn { padding: 0.35rem 0.6rem; font-size: 0.8rem; border-radius: 6px; }
        .control-btn:hover { transform: translateY(-1px); }

        .navbar { padding: 0.6rem 1rem; position: sticky; top: 0; z-index: 240; }
        .nav-brand { text-decoration: none !important; }
        .nav-brand::after { display: none !important; }
        .nav-links { gap: 1rem; }
        .nav-links a.active { color: var(--text-primary); font-weight: 500; }
        .nav-avatar-link { display:inline-flex; align-items:center; }
        .nav-avatar { width:36px; height:36px; border-radius:50%; background: var(--bg-card); display:inline-flex; align-items:center; justify-content:center; color: var(--text-primary); font-weight:600; margin-left:0.5rem; text-decoration:none; }
        
        /* left sidebar for My Watchlist */
        .sidebar-left {
            background: var(--bg-secondary);
            border-right: 1px solid var(--border);
            padding: 1.5rem;
            transition: width 220ms ease, padding 220ms ease;
            position: sticky;
            top: var(--navbar-height, 56px);
            z-index: 230; /* sit at same stacking as main layout */
            height: calc(100vh - var(--navbar-height, 56px));
            box-shadow: 0 12px 36px rgba(0,0,0,0.06);
            width: var(--sidebar-width, 320px);
            max-width: 48vw;
            display: flex;
            flex-direction: column;
        }
        .sidebar-left::-webkit-scrollbar { width: 8px; }
        .sidebar-left::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.06); border-radius: 8px; }
        .sidebar-left.collapsed { padding: 0.5rem; }

        /* inner scrollable area so help button can stick to bottom */
        .sidebar-content { overflow-y: auto; flex: 1; padding-right: 0.25rem; }

        /* help button sits at bottom */
        .sidebar-help { margin-top: auto; position: static; left: auto; bottom: auto; padding-top: 0.5rem; }
        .sidebar-resizer {
            position: absolute;
            right: -6px;
            top: 0;
            width: 12px;
            height: 100%;
            cursor: ew-resize;
            z-index: 260;
        }
        .sidebar-left.collapsed .watchlist-header h2,
        .sidebar-left.collapsed .sat-search,
        .sidebar-left.collapsed .watchlist-item { display: none; }
        .sidebar-left.collapsed { width: 64px; }
        .sidebar-left.collapsed { padding: 0.5rem; }
        .sidebar-left.collapsed .watchlist-header h2,
        .sidebar-left.collapsed .sat-search,
        .sidebar-left.collapsed .watchlist-item { display: none; }
        .sidebar-left.collapsed { width: 64px; }

        .help-btn { padding: 0.5rem 1rem; border-radius: 6px; background: rgba(255,255,255,0.03); border: 1px solid var(--border); color: var(--text-primary); font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; font-size: 0.9rem; }
        .help-btn:hover { background: rgba(255,255,255,0.06); }

        /* help modal overrides (re-use modal classes) */
        .help-modal .modal-content { max-width: 520px; }
        .help-modal h3 { margin-bottom: 0.75rem; }
        .help-modal ul { margin-left: 1rem; color: var(--text-secondary); }
        .help-modal p { color: var(--text-secondary); margin-bottom: 0.5rem; }
        
        /* center COLUMN cesium globe (restored to previous constrained sizing) */
        .globe-container {
            position: relative;
            background: #000;
            max-height: 520px; /* constrain globe size */
            margin: 1rem;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 12px 36px rgba(0,0,0,0.6);
        }
        
        #cesiumContainer {
            width: 100%;
            height: 520px; /* constrained height */
            min-height: 360px;
        }
        
        /* right sidebar was originally the satellite list */
        .sidebar-right {
            background: var(--bg-secondary);
            border-left: 1px solid var(--border);
            overflow-y: auto;
            padding: 1.5rem;
        }
        
        /* controls overlay on globe */
        .globe-controls {
            position: absolute;
            top: 1rem;
            left: 1rem;
            z-index: 100;
            display: flex;
            gap: 0.5rem;
        }
        
        .control-btn {
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .control-btn:hover {
            background: rgba(99, 96, 255, 0.3);
            border-color: var(--accent);
        }
        
        .control-btn.active {
            background: var(--accent) !important;
            color: white !important;
            border-color: var(--accent) !important;
        }
        
        /* watchlist items */
        .watchlist-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .share-btn {
            background: var(--accent);
            color: white;
            border: none;
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
        }
        
        .share-btn:hover {
            opacity: 0.9;
        }
        
        /* satellite card compact */
        .sat-card-compact {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .sat-card-compact:hover {
            border-color: var(--accent);
            transform: translateX(4px);
        }
        
        .sat-card-compact h4 {
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }
        
        .sat-card-compact .meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        .meta-actions { display:flex; gap:0.5rem; align-items:center; }
        .btn-show { background: transparent; border: 1px solid var(--border); color: white; padding: 0.25rem 0.5rem; border-radius: 6px; cursor: pointer; }
        .btn-show:hover { background: rgba(255,255,255,0.02); }
        .btn-remove { background: transparent; border: none; color: var(--text-secondary); cursor: pointer; font-size: 1rem; padding: 0.15rem 0.5rem; border-radius: 4px; }
        
        /* loading overlay */
        .loading-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.8);
            padding: 2rem;
            border-radius: 12px;
            z-index: 1000;
            text-align: center;
        }
        
        /* share modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
        }
        
        .modal-content h3 {
            margin-bottom: 1rem;
        }
        
        .share-code-box {
            background: var(--bg-primary);
            border: 1px solid var(--accent);
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            font-size: 1.5rem;
            letter-spacing: 0.1em;
            margin: 1rem 0;
            color: var(--accent);
        }
        
        .modal-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .modal-actions button {
            flex: 1;
            padding: 0.75rem;
            border-radius: 8px;
            cursor: pointer;
            border: none;
        }

        /* footer styling (commented out) */
        /*
        .site-footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 600;
            background: #000;
            color: #fff;
            padding: 18px 24px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-top: 1px solid rgba(255,255,255,0.04);
        }
        .site-footer .footer-left { display:flex; gap:1rem; align-items:flex-start; }
        .site-footer .footer-subtitle { color: rgba(255,255,255,0.85); margin-top: 0.25rem; max-width: 420px; }
        .site-footer .footer-right ul { list-style: none; margin: 0; padding: 0; display:flex; gap: 1.25rem; }
        .site-footer .footer-right a { color: #ddd; text-decoration:none; }
        .site-footer .footer-bottom { position: absolute; left: 0; right: 0; bottom: -32px; text-align: center; color: #999; font-size: 0.85rem; }

        /* removed body bottom padding as footer is disabled */
        /* body { padding-bottom: 96px; } */
        */
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="nav-brand">SatTrack</a>
        <div class="nav-links">
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="passes.php">My Passes</a>
            <a href="observations.php">Observations</a>
            <a href="profile.php" class="nav-avatar-link" title="Profile"><span class="nav-avatar"><?= strtoupper(substr(($username ?? ''), 0, 1)) ?></span></a>
            <a href="logout.php">Logout (<?= escape($username) ?>)</a>
        </div>
    </nav>

    <div class="top-bar">
        <div class="top-controls">
            <button id="collapseSidebarBtnTop" class="control-btn" title="Toggle sidebar">⇤</button>
            <button class="control-btn" onclick="resetCamera()">reset view</button>
            <button class="control-btn" onclick="toggleSatelliteLabels()">toggle labels</button>
            <button class="control-btn" onclick="toggleOrbits()">toggle orbits</button>
            <button id="calculatePassesBtn" class="control-btn" style="background: var(--accent); color: white;" onclick="calculatePasses()">calculate passes</button>
        </div>
    </div>
    
    <div class="dashboard-layout">
        <!-- LEFT SIDEBAR: Watchlist -->
        <aside class="sidebar-left">
            <div class="location-block">
                <div style="font-size:0.95rem; color:var(--text-secondary); margin-bottom:0.5rem;">Your location</div>
                <div style="display:flex; gap:0.5rem; align-items:center;">
                    <div id="userLocationDisplay" style="flex:1; padding:0.45rem; background:var(--bg-primary); border:1px solid var(--border); border-radius:6px; color:white;"><?= htmlspecialchars($user_location['display'] ?? "") ?></div>
                    <button id="changeLocationBtn" class="control-btn">Change</button>
                </div>
            </div>

            <div class="sat-search">
                <label for="satSearch" style="display:block; margin-bottom:0.5rem; color:var(--text-secondary);">Add Satellite</label>
                <div style="position: relative; display:flex; gap:0.5rem;">
                    <div style="position: relative; flex: 1;">
                        <input list="satList" id="satSearch" placeholder="Search or select satellite..." style="width: 100%; padding: 0.5rem 2.5rem 0.5rem 0.5rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-primary); color: white;" />
                        <button id="satDropdownToggle" style="position: absolute; right: 4px; top: 50%; transform: translateY(-50%); background: transparent; border: none; color: var(--text-secondary); cursor: pointer; padding: 0.25rem; font-size: 1.2rem;" title="Show all satellites">▼</button>
                        <datalist id="satList"></datalist>
                        <div id="satDropdown" style="display: none; position: absolute; top: 100%; left: 0; right: 0; max-height: 300px; overflow-y: auto; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 6px; margin-top: 4px; z-index: 100; box-shadow: 0 4px 12px rgba(0,0,0,0.3);">
                            <div id="satDropdownList" style="padding: 0.5rem;"></div>
                        </div>
                    </div>
                    <button id="addSatelliteBtn" class="control-btn add-btn">Add</button>
                </div>
            </div>
            <div class="sidebar-resizer" title="Drag to resize"></div> <!-- handle element -->

            <div class="watchlist-header">
                <h2>My Watchlist</h2>
                <div style="display:flex; gap:0.5rem; align-items:center;">
                    <div class="watchlist-menu">
                        <button id="watchlistMenuToggle" class="control-btn">⋯</button>
                        <div id="watchlistMenu" class="menu">
                            <button onclick="openShareModal()">Share Watchlist</button>
                            <button onclick="openImportModal()">Import Watchlist</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="sidebar-content">
                <div id="watchlistContainer">
                    <?php if (empty($watchlist)): ?>
                        <p class="empty-state">no satellites yet. click satellites on the right to add!</p>
                    <?php else: ?>
                        <?php foreach($watchlist as $sat): ?>
                            <div class="watchlist-item sat-card-compact" data-sat-id="<?= $sat['id'] ?>" data-norad="<?= $sat['norad_id'] ?>" data-name="<?= htmlspecialchars($sat['name'], ENT_QUOTES) ?>" onclick="focusSatellite(<?= $sat['norad_id'] ?>)">
                                <h4><?= escape($sat['name']) ?></h4>
                                <div class="meta">
                                    <span class="sat-type"><?= escape($sat['satellite_type']) ?></span>
                                    <div class="meta-actions">
                                        <button class="btn-show" onclick="event.stopPropagation(); toggleSatelliteVisibility(<?= $sat['norad_id'] ?>, this)">Show</button>
                                        <button class="btn-remove" onclick="event.stopPropagation(); removeFromWatchlist(<?= $sat['id'] ?>)">×</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- help button fixed to bottom of sidebar -->
            <div class="sidebar-help">
                <button id="helpToggle" class="help-btn" title="Help">
                    <span style="font-weight: 700; font-style: italic;">i</span>
                    <span>Help</span>
                </button>
            </div>
        </aside>
        
        <!-- CENTER: Cesium Globe -->
        <main class="globe-container">
            <div id="cesiumContainer"></div>
            <!-- loading indicator -->
            <div id="loadingOverlay" class="loading-overlay">
                <h2>loading satellites...</h2>
                <p style="color: var(--text-secondary); margin-top: 0.5rem;">fetching orbital data</p>
            </div>
        </main>
    </div>
    
    <!-- SHARE MODAL -->
    <div id="shareModal" class="modal">
        <div class="modal-content">
            <h3>share your watchlist</h3>
            <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                share this code with friends so they can import your satellite watchlist
            </p>
            <div class="share-code-box"><?= escape($share_code) ?></div>
            <div class="modal-actions">
                <button onclick="copyShareCode()" style="background: var(--accent); color: white;">copy code</button>
                <button onclick="closeShareModal()" style="background: var(--bg-card); color: white;">close</button>
            </div>
        </div>
    </div>
    
    <!-- IMPORT MODAL -->
    <div id="importModal" class="modal">
        <div class="modal-content">
            <h3>import watchlist</h3>
            <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                enter a share code from a friend to import their satellites
            </p>
            <input type="text" id="importCode" placeholder="enter share code" style="width: 100%; padding: 0.75rem; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; color: white; margin-bottom: 1rem;">
            <div class="modal-actions">
                <button onclick="importWatchlist()" style="background: var(--success); color: white;">import</button>
                <button onclick="closeImportModal()" style="background: var(--bg-card); color: white;">cancel</button>
            </div>
        </div>
    </div>

    <!-- HELP MODAL -->
    <div id="helpModal" class="modal help-modal" aria-hidden="true">
        <div class="modal-content">
            <h3>Dashboard Help</h3>
            <p>Quick tips to get the most out of SatTrack:</p>
            <ul>
                <li><strong>Your location:</strong> set a country/city to center the globe and get relevant pass predictions.</li>
                <li><strong>Add Satellite:</strong> select an active satellite from the dropdown and click <em>Add</em> to include it in your watchlist.</li>
                <li><strong>Watchlist:</strong> click a satellite to focus it; use the <em>Show</em>/<em>Hide</em> button to toggle visibility; click × to remove it.</li>
                <li><strong>Globe interaction:</strong> scroll to zoom, click+drag to rotate, and use the controls to reset view or toggle labels and orbits.</li>
                <li><strong>Toggle orbits:</strong> shows predicted path trails for satellites.</li>
                <li><strong>Share / Import:</strong> share your watchlist code or import a friend’s watchlist via the menu.</li>
            </ul>
            <div class="modal-actions">
                <button onclick="closeHelpModal()" style="background: var(--bg-card); color: white;">Close</button>
            </div>
        </div>
    </div>
    
    <!-- cesium library -->
    <script src="https://cesium.com/downloads/cesiumjs/releases/1.111/Build/Cesium/Cesium.js"></script>
    
    <script>
        // pass php data to javascript
        const userLocation = <?= json_encode($user_location) ?>;
        const watchlistSatellites = <?= json_encode($watchlist) ?>;
        const allSatellites = <?= json_encode($all_satellites) ?>;
        const shareCode = '<?= escape($share_code) ?>';
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/satellite.js@4.1.3/dist/satellite.min.js"></script>
    <!-- main dashboard javascript -->
    <script src="assets/js/dashboard.js"></script>

    <!--
    <footer class="site-footer">
        <div class="footer-left">
            <div class="footer-logo">
                <img src="assets/images/sattrack-logo-black.png" alt="SatTrack" style="height:44px; display:block;" />
                <div class="footer-subtitle">Real-time satellite tracking for observers everywhere.</div>
            </div>
        </div>
        <div class="footer-right">
            <ul>
                <li><a href="#">Demo</a></li>
                <li><a href="#">About</a></li>
                <li><a href="#">Contact Us</a></li>
                <li><a href="#">Help</a></li>
                <li><a href="https://github.com/marzafiee">GitHub (@marzafiee)</a></li>
            </ul>
        </div>
        <div class="footer-bottom">© 2025 SatTrack. Built with orbital data from N2YO.</div>
    </footer>
    -->
</body>
</html>