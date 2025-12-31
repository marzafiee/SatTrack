<?php
require_once 'includes/db_config.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid security token.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // rate limiting check - prevent brute force
        if (!checkRateLimit($email)) {
            $errors[] = "Too many failed attempts. Please try again in 15 minutes.";
        } else {
            $stmt = $conn->prepare("SELECT id, password_hash, username FROM users WHERE email = ?");
            if ($stmt) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $res = $stmt->get_result();
                $user = $res ? $res->fetch_assoc() : null;
                $stmt->close();
            } else {
                $user = null;
            }
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // login successful - create session
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                
                // reset failed attempts
                $stmt = $conn->prepare("UPDATE users SET failed_login_attempts = 0 WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param('i', $user['id']);
                    $stmt->execute();
                    $stmt->close();
                }
                
                header('Location: dashboard.php');
                exit;
            } else {
                // track failed attempt
                if ($user) {
                    $stmt = $conn->prepare("UPDATE users SET failed_login_attempts = failed_login_attempts + 1, last_failed_login = NOW() WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param('i', $user['id']);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
                $errors[] = "Invalid email or password.";
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
    <title>Login - SatTrack</title>
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
            <li><a href="register.php">Register</a></li>
        </ul>
    </nav>

    <!-- Animated Stars -->
    <canvas id="stars-container"></canvas>

    <!-- Login Container -->
    <div class="login-container">
        <div class="login-header">
            <p class="login-subtitle">Welcome Back</p>
            <h1 class="login-title">Sign In</h1>
            <p class="login-description">Continue tracking satellites</p>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= escape($csrf_token) ?>">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="your@email.com" required 
                       value="<?= escape($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="error-message show">
                    <?php foreach($errors as $error): ?>
                        <?= escape($error) ?><br>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn-login">Sign In</button>
        </form>

        <div class="divider">or</div>

        <div class="form-footer">
            Don't have an account? <a href="register.php">Create one</a>
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

    </script>
</body>
</html>

