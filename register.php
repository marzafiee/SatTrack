<?php
require_once 'includes/db_config.php';

// if already logged in redirect to dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // verify csrf token first #security check 1
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid security token. Please try again.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $latitude = $_POST['latitude'] ?? null;
        $longitude = $_POST['longitude'] ?? null;
        
        // validate all inputs - yeah this is important
        if (!validateUsername($username)) {
            $errors[] = "Username must be 3-20 characters, alphanumeric and underscores only.";
        }
        
        if (!validateEmail($email)) {
            $errors[] = "Invalid email format.";
        }
        
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters.";
        }
        
        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match.";
        }
        
        if ($latitude !== null && $longitude !== null) {
            if (!validateCoordinates($latitude, $longitude)) {
                $errors[] = "Invalid coordinates.";
            }
        }
        
        // check if username or email already exists
        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            if ($stmt) {
                $stmt->bind_param('ss', $username, $email);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->fetch_assoc()) {
                    $errors[] = "Username or email already exists.";
                }
                $stmt->close();
            } else {
                $errors[] = "Database error. Please try again.";
            }
        }
        
        // if everything checks out create the account
        if (empty($errors)) {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            
            $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, default_latitude, default_longitude) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param('sssss', $username, $email, $password_hash, $latitude, $longitude);
                if ($stmt->execute()) {
                    $success = "Account created! Redirecting to login...";
                    header("Refresh: 2; url=login.php");
                } else {
                    $errors[] = "Registration failed. Please try again.";
                }
                $stmt->close();
            } else {
                $errors[] = "Database error. Please try again.";
            }
        }
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - SatTrack</title>
    <link href="https://fonts.googleapis.com/css2?family=EB+Garamond:wght@400;500;600;700&family=Inconsolata:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="auth-page">
    <!-- Navigation -->
    <nav>
        <a href="index.php" class="logo">SatTrack</a>
        <ul class="nav-links">
            <li><a href="index.php">Home</a></li>
            <li><a href="dashboard.php">Demo</a></li>
            <li><a href="about.php">About</a></li>
            <li><a href="help.php">Help</a></li>
            <li><a href="login.php">Login</a></li>
        </ul>
    </nav>

    <!-- Animated Stars -->
    <canvas id="stars-container"></canvas>

    <!-- Register Container -->
    <div class="register-container">
        <div class="register-header">
            <p class="register-subtitle">Join SatTrack</p>
            <h1 class="register-title">Create Account</h1>
            <p class="register-description">Start tracking satellites over your sky</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <?php foreach($errors as $error): ?>
                    <p><?= escape($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-box">
                <p><?= escape($success) ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="registerForm">
            <input type="hidden" name="csrf_token" value="<?= escape($csrf_token) ?>">
            <div class="form-group">
                <label>username</label>
                <input type="text" name="username" required maxlength="20" 
                       pattern="[a-zA-Z0-9_]{3,20}" 
                       value="<?= escape($_POST['username'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label>email</label>
                <input type="email" name="email" required 
                       value="<?= escape($_POST['email'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label>password</label>
                <input type="password" name="password" required minlength="8">
            </div>
            
            <div class="form-group">
                <label>confirm password</label>
                <input type="password" name="confirm_password" required minlength="8">
            </div>
            
            <!-- hidden fields for auto-detected location -->
            <input type="hidden" name="latitude" id="latitude">
            <input type="hidden" name="longitude" id="longitude">
            
            <div id="locationStatus" class="location-status">
                detecting your location...
            </div>
            
            <button type="submit" class="btn-register">Create Account</button>
        </form>

        <div class="form-footer">
            Already have an account? <a href="login.php">Sign In</a>
        </div>
    </div>

    <script>
        // Animated stars background
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

        // auto-detect location using browser geolocation api
        const locationStatus = document.getElementById('locationStatus');
        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');
        
        if (locationStatus && latInput && lngInput) {
            // try browser geolocation first - most accurate
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        latInput.value = position.coords.latitude.toFixed(6);
                        lngInput.value = position.coords.longitude.toFixed(6);
                        locationStatus.textContent = 'location detected ✓';
                        locationStatus.style.color = '#4ade80';
                    },
                    (error) => {
                        // fallback to ip-based geolocation
                        fetch('https://ipapi.co/json/')
                            .then(res => res.json())
                            .then(data => {
                                latInput.value = data.latitude;
                                lngInput.value = data.longitude;
                                locationStatus.textContent = 'location detected (approximate) ✓';
                                locationStatus.style.color = '#fbbf24';
                            })
                            .catch(() => {
                                locationStatus.textContent = 'location detection failed (will use default)';
                                locationStatus.style.color = '#ef4444';
                            });
                    }
                );
            } else {
                // no geolocation api support - use ip fallback
                fetch('https://ipapi.co/json/')
                    .then(res => res.json())
                    .then(data => {
                        latInput.value = data.latitude;
                        lngInput.value = data.longitude;
                        locationStatus.textContent = 'location detected (approximate) ✓';
                        locationStatus.style.color = '#fbbf24';
                    })
                    .catch(() => {
                        locationStatus.textContent = 'location detection failed (will use default)';
                        locationStatus.style.color = '#ef4444';
                    });
            }
        }
        
        // Form validation
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const password = document.getElementById('password');
                const confirmPassword = document.getElementById('confirm_password');
                
                if (password && confirmPassword && password.value !== confirmPassword.value) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                    return false;
                }
            });
        }
    </script>
</body>
</html>

