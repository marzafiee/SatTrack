<?php
require_once 'includes/db_config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// get user info
$stmt = $conn->prepare("SELECT email, default_latitude, default_longitude, created_at FROM users WHERE id = ?");
$user = null;
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();
}

$email = $user['email'] ?? '';
$latitude = $user['default_latitude'] ?? null;
$longitude = $user['default_longitude'] ?? null;
$created_at = $user['created_at'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - SatTrack</title>
    <link href="https://fonts.googleapis.com/css2?family=EB+Garamond:wght@400;500;600;700&family=Inconsolata:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .navbar { padding: 0.6rem 1rem; position: sticky; top: 0; z-index: 240; }
        .nav-brand { text-decoration: none !important; }
        .nav-brand::after { display: none !important; }
        .nav-links { gap: 1rem; }
        .nav-links a.active { color: var(--text-primary); font-weight: 500; }
        .nav-avatar-link { display:inline-flex; align-items:center; }
        .nav-avatar { width:36px; height:36px; border-radius:50%; background: var(--bg-card); display:inline-flex; align-items:center; justify-content:center; color: var(--text-primary); font-weight:600; margin-left:0.5rem; text-decoration:none; }
        
        .profile-container {
            max-width: 800px;
            margin: 120px auto 60px;
            padding: 0 2rem;
            position: relative;
            z-index: 10;
        }
        
        .profile-section {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border: 1px solid var(--color-card-border);
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
        }
        
        .profile-section h2 {
            font-size: 1.8rem;
            color: var(--color-text-heading);
            margin-bottom: 1.5rem;
            font-weight: 600;
        }
        
        .profile-info {
            display: grid;
            gap: 1rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 8px;
        }
        
        .info-label {
            color: var(--color-text-subheading);
            font-size: 0.95rem;
        }
        
        .info-value {
            color: var(--color-text-heading);
            font-weight: 500;
        }
        
        .section-link {
            display: block;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 8px;
            color: var(--color-text-body);
            text-decoration: none;
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }
        
        .section-link:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: var(--color-accent);
            transform: translateX(4px);
        }
        
        .section-link strong {
            color: var(--color-text-heading);
            display: block;
            margin-bottom: 0.25rem;
        }
        
        .section-link span {
            font-size: 0.9rem;
            color: var(--color-text-subheading);
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="nav-brand">SatTrack</a>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="passes.php">My Passes</a>
            <a href="observations.php">Observations</a>
            <a href="profile.php" class="active nav-avatar-link" title="Profile"><span class="nav-avatar"><?= strtoupper(substr(($username ?? ''), 0, 1)) ?></span></a>
            <a href="logout.php">Logout (<?= escape($username) ?>)</a>
        </div>
    </nav>
    
    <!-- twinkling stars -->
    <canvas id="stars-container"></canvas>
    
    <div class="profile-container">
        <div class="profile-section">
            <h2>Account Settings</h2>
            <div class="profile-info">
                <div class="info-item">
                    <span class="info-label">Username</span>
                    <span class="info-value"><?= escape($username) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?= escape($email) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Location</span>
                    <span class="info-value">
                        <?php if ($latitude && $longitude): ?>
                            <?= round($latitude, 4) ?>, <?= round($longitude, 4) ?>
                        <?php else: ?>
                            Not set
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Member Since</span>
                    <span class="info-value">
                        <?php if ($created_at): ?>
                            <?= date('F Y', strtotime($created_at)) ?>
                        <?php else: ?>
                            Unknown
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="profile-section">
            <h2>Resources</h2>
            <a href="help.php" class="section-link">
                <strong>Help & Support</strong>
                <span>Frequently asked questions and getting started guide</span>
            </a>
            <a href="about.php" class="section-link">
                <strong>About SatTrack</strong>
                <span>Learn more about our platform and mission</span>
            </a>
            <a href="#privacy" class="section-link" onclick="showPrivacyPolicy(); return false;">
                <strong>Privacy Policy</strong>
                <span>How we handle your data and privacy</span>
            </a>
            <a href="#terms" class="section-link" onclick="showTermsOfService(); return false;">
                <strong>Terms of Service</strong>
                <span>Terms and conditions for using SatTrack</span>
            </a>
        </div>
    </div>
    
    <!-- Privacy Policy Modal -->
    <div id="privacyModal" class="modal" style="display: none;">
        <div class="logout-container" style="max-width: 600px;">
            <h2 class="logout-title">Privacy Policy</h2>
            <div style="text-align: left; color: var(--color-text-body); line-height: 1.7; max-height: 60vh; overflow-y: auto;">
                <p><strong>Last Updated:</strong> <?= date('F j, Y') ?></p>
                <h3 style="margin-top: 1.5rem; margin-bottom: 0.5rem;">Data Collection</h3>
                <p>SatTrack collects minimal data necessary for functionality:</p>
                <ul style="margin-left: 1.5rem; margin-top: 0.5rem;">
                    <li>Account information (username, email)</li>
                    <li>Location coordinates (for pass predictions)</li>
                    <li>Satellite watchlist preferences</li>
                </ul>
                <h3 style="margin-top: 1.5rem; margin-bottom: 0.5rem;">Data Usage</h3>
                <p>Your data is used solely to provide satellite tracking services. We do not sell or share your personal information with third parties.</p>
                <h3 style="margin-top: 1.5rem; margin-bottom: 0.5rem;">Data Security</h3>
                <p>We implement industry-standard security measures to protect your information.</p>
            </div>
            <div class="logout-actions" style="margin-top: 2rem;">
                <button onclick="closeModal()" class="btn btn-primary">Close</button>
            </div>
        </div>
    </div>
    
    <!-- Terms Modal -->
    <div id="termsModal" class="modal" style="display: none;">
        <div class="logout-container" style="max-width: 600px;">
            <h2 class="logout-title">Terms of Service</h2>
            <div style="text-align: left; color: var(--color-text-body); line-height: 1.7; max-height: 60vh; overflow-y: auto;">
                <p><strong>Last Updated:</strong> <?= date('F j, Y') ?></p>
                <h3 style="margin-top: 1.5rem; margin-bottom: 0.5rem;">Acceptance of Terms</h3>
                <p>By using SatTrack, you agree to these terms of service.</p>
                <h3 style="margin-top: 1.5rem; margin-bottom: 0.5rem;">Service Description</h3>
                <p>SatTrack provides satellite tracking and pass prediction services. Predictions are based on TLE data and may have accuracy limitations.</p>
                <h3 style="margin-top: 1.5rem; margin-bottom: 0.5rem;">User Responsibilities</h3>
                <p>Users are responsible for maintaining account security and providing accurate location information.</p>
                <h3 style="margin-top: 1.5rem; margin-bottom: 0.5rem;">Limitations</h3>
                <p>SatTrack provides predictions "as is" without warranty. Actual satellite passes may vary due to orbital changes.</p>
            </div>
            <div class="logout-actions" style="margin-top: 2rem;">
                <button onclick="closeModal()" class="btn btn-primary">Close</button>
            </div>
        </div>
    </div>
    
    <script>
        // animated stars background
        const canvas = document.getElementById('stars-container');
        const ctx = canvas.getContext('2d');
        
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        
        const stars = [];
        const starCount = 200;
        
        for (let i = 0; i < starCount; i++) {
            stars.push({
                x: Math.random() * canvas.width,
                y: Math.random() * canvas.height,
                radius: Math.random() * 1.5,
                speed: Math.random() * 0.2 + 0.05,
                opacity: Math.random() * 0.5 + 0.3
            });
        }
        
        function animateStars() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            stars.forEach(star => {
                ctx.beginPath();
                ctx.arc(star.x, star.y, star.radius, 0, Math.PI * 2);
                ctx.fillStyle = `rgba(255, 255, 255, ${star.opacity})`;
                ctx.fill();
                
                star.y += star.speed;
                if (star.y > canvas.height) {
                    star.y = 0;
                    star.x = Math.random() * canvas.width;
                }
            });
            
            requestAnimationFrame(animateStars);
        }
        
        animateStars();
        
        window.addEventListener('resize', () => {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        });
        
        function showPrivacyPolicy() {
            document.getElementById('privacyModal').style.display = 'flex';
        }
        
        function showTermsOfService() {
            document.getElementById('termsModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('privacyModal').style.display = 'none';
            document.getElementById('termsModal').style.display = 'none';
        }
        
        // close modal on outside click
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                closeModal();
            }
        });
    </script>
    <style>
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: 3000;
            align-items: center;
            justify-content: center;
        }
    </style>
</body>
</html>
