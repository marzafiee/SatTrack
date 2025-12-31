<?php
require_once 'includes/db_config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About - SatTrack</title>
    <link href="https://fonts.googleapis.com/css2?family=EB+Garamond:wght@400;500;600;700&family=Inconsolata:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <!-- Navigation -->
    <nav>
        <a href="index.php" class="logo">SatTrack</a>
        <ul class="nav-links">
            <li><a href="index.php">Home</a></li>
            <li><a href="dashboard.php">Demo</a></li>
            <li><a href="passes.php">Tracker</a></li>
            <li><a href="about.php" class="active">About</a></li>
            <li><a href="help.php">Help</a></li>
        </ul>
    </nav>

    <!-- Animated Stars -->
    <canvas id="stars-container"></canvas>

    <!-- About Container -->
    <div class="login-container" style="max-width: 800px; margin: 100px auto; position: relative; z-index: 10;">
        <div class="login-header">
            <p class="login-subtitle">About SatTrack</p>
            <h1 class="login-title">Real-Time Satellite Tracking</h1>
        </div>

        <div style="font-size: 1.1rem; line-height: 1.8; color: var(--color-text-body);">
            <p style="margin-bottom: 2rem;">
                <strong>SatTrack</strong> is a real-time satellite tracking platform that helps space enthusiasts, 
                amateur astronomers, and curious minds discover and track satellites passing overhead. 
                Whether you're in Lomé or Los Angeles, SatTrack shows you what's flying over YOUR sky.
            </p>

            <h2 style="font-size: 1.5rem; margin: 2rem 0 1rem; color: var(--color-text-heading);">How It Works</h2>
            <ol style="margin-left: 2rem; margin-bottom: 2rem;">
                <li style="margin-bottom: 0.75rem;"><strong>User sets location</strong> → stored in database</li>
                <li style="margin-bottom: 0.75rem;"><strong>Fetches satellite TLE data</strong> from CelesTrak API (updated daily)</li>
                <li style="margin-bottom: 0.75rem;"><strong>Calculates passes</strong> using satellite.js library</li>
                <li style="margin-bottom: 0.75rem;"><strong>3D Visualization</strong> shows satellites orbiting Earth in real-time using Cesium.js</li>
                <li style="margin-bottom: 0.75rem;"><strong>Watchlist stored in DB</strong> → user's favorites</li>
                <li style="margin-bottom: 0.75rem;"><strong>Pass predictions cached</strong> → fast loading</li>
            </ol>

            <p style="margin-top: 2rem;">
                Search thousands of satellites, visualize their orbits in stunning 3D, receive personalized 
                pass predictions for your location, and build a watchlist of favorites.
            </p>
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

