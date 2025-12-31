<?php require_once 'includes/db_config.php'; 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SatTrack - Track Satellites Over Your Sky</title>
    <link href="https://fonts.googleapis.com/css2?family=EB+Garamond:wght@400;500;600;700&family=Inconsolata:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <!-- Tailwind CSS (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        satcyan: '#38BDF8'
                    }
                }
            }
        }
    </script>
</head>
<body>
    <!-- nav bar -->
    <nav id="mainNav" style="position: fixed; top: 0; width: 100%; z-index: 100; padding: 1.5rem 3rem; display: flex; justify-content: space-between; align-items: center; background: linear-gradient(180deg, rgba(13, 13, 13, 0.85) 0%, rgba(13, 13, 13, 0.85) 100%); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255, 255, 255, 0.05); transition: all 0.3s ease;">
        <a href="#" class="logo" id="navLogo" style="opacity: 0; transition: opacity 0.3s ease;">SatTrack</a>
        <ul class="nav-links">
            <li><a href="#demo">Demo</a></li>
            <li><a href="about.php">About</a></li>
            <li><a href="help.php">Help</a></li>
        </ul>
    </nav>

    <!-- hero section -->
    <section class="hero">
        <div class="hero-content">
            <p class="hero-subtitle">Real-Time Satellite Tracking</p>
            <h1 class="hero-title">
                <span class="highlight">SatTrack</span>
            </h1>
            <p class="hero-description">
                Discover what's flying over YOUR sky.
                Track satellites in real-time, 
                visualize <span class="scramble-text" data-text="3D orbits">3D orbits</span>, and get 
                personalized pass predictions. Whether you're in Lomé or Los Angeles, SatTrack shows 
                you what's <span class="scramble-text" data-text="above">above</span>.
            </p>
            <div class="hero-buttons">
                <a href="register.php" class="btn-primary">GET STARTED</a>
                <a href="login.php" class="btn-play" aria-label="Login" title="Login">
                    <span class="btn-play-text" style="opacity: 0; width: 0; overflow: hidden; transition: all 0.3s ease; white-space: nowrap; margin-left: 0;">Log In</span>
                </a>
            </div>
        </div>
        <div id="earth-container"></div>
    </section>

    <!-- features section w/ 3 main features -->
    <section class="features-section" style="position: relative; z-index: 10; padding: 6rem 3rem; background: var(--color-background);">
        <div style="max-width: 1200px; margin: 0 auto;">
            <p class="hero-subtitle" style="text-align: center; margin-bottom: 1rem;">Powerful Features</p>
            <h2 class="hero-title" style="text-align: center; font-size: 3.5rem; margin-bottom: 1.5rem;">
                <span class="scramble-text" data-text="Built for Curious Minds">Built for Curious Minds</span>
            </h2>
            <p class="hero-description" style="text-align: center; max-width: 600px; margin: 0 auto 4rem;">
                Visualize, track, and predict satellites effortlessly. Everything you need to explore what's above.
            </p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 3rem; margin-top: 4rem;">
                <!-- Feature 1 -->
                <div class="feature-card group" style="text-align: center;">
                    <div style="margin-bottom: 2rem; overflow: hidden; border-radius: 12px;">
                        <img src="assets/images/satellite-around-earth.png" alt="Satellite Orbits" class="feature-image" style="width: 100%; max-width: 400px; height: auto; border-radius: 12px; filter: grayscale(100%) brightness(1.2) contrast(0.9); transition: all 300ms ease; transform: scale(1);">
                    </div>
                    <h3 style="font-size: 1.5rem; font-weight: 600; color: var(--color-text-heading); margin-bottom: 1rem;">Live 3D Satellite Orbits</h3>
                    <p style="color: var(--color-text-body); line-height: 1.7; font-size: 1.05rem;">
                        Visualize satellites moving around Earth in real time using accurate orbital data. Watch as satellites trace their paths across the globe.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="feature-card group" style="text-align: center;">
                    <div style="margin-bottom: 2rem; overflow: hidden; border-radius: 12px;">
                        <img src="assets/images/pass-predictions.png" alt="Pass Predictions" class="feature-image" style="width: 100%; max-width: 400px; height: auto; border-radius: 12px; filter: grayscale(100%) brightness(1.2) contrast(0.9); transition: all 300ms ease; transform: scale(1);">
                    </div>
                    <h3 style="font-size: 1.5rem; font-weight: 600; color: var(--color-text-heading); margin-bottom: 1rem;">Pass Predictions for Your Sky</h3>
                    <p style="color: var(--color-text-body); line-height: 1.7; font-size: 1.05rem;">
                        Get upcoming passes for your location, including rise time, max elevation, and direction.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="feature-card group" style="text-align: center;">
                    <div style="margin-bottom: 2rem; overflow: hidden; border-radius: 12px;">
                        <img src="assets/images/watchlist.png" alt="Watchlist" class="feature-image" style="width: 100%; max-width: 400px; height: auto; border-radius: 12px; filter: grayscale(100%) brightness(1.2) contrast(0.9); transition: all 300ms ease; transform: scale(1);">
                    </div>
                    <h3 style="font-size: 1.5rem; font-weight: 600; color: var(--color-text-heading); margin-bottom: 1rem;">Your Personal Watchlist</h3>
                    <p style="color: var(--color-text-body); line-height: 1.7; font-size: 1.05rem;">
                        Track only the satellites you care about, see upcoming passes at a glance, never miss an ISS flyover.
                    </p>
                </div>
            </div>
        </div>
    </section>
    
    <style>
        .feature-card.group:hover .feature-image {
            filter: grayscale(0%) brightness(1) contrast(1) !important;
            transform: scale(1.02) !important;
        }
    </style>

    <!-- Demo Video Section -->
    <section id="demo" style="position: relative; z-index: 10; padding: 6rem 3rem; min-height: 600px; display: flex; align-items: center; justify-content: center;">
        <canvas id="demoStarfield" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1;"></canvas>
        <div style="max-width: 1200px; width: 100%; text-align: center; position: relative; z-index: 2;">
            <p class="hero-subtitle" style="margin-bottom: 1rem;">See It In Action</p>
            <h2 class="hero-title" style="font-size: 3.5rem; margin-bottom: 2rem;">
                <span class="scramble-text" data-text="Watch the Demo">Watch the Demo</span>
            </h2>
            <p class="hero-description" style="max-width: 600px; margin: 0 auto 3rem;">
                See how SatTrack works in this quick demonstration video.
            </p>
            <!-- Placeholder for video -->
            <div style="background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(10px); border: 1px solid var(--color-card-border); border-radius: 20px; padding: 3rem; min-height: 400px; display: flex; align-items: center; justify-content: center;">
                <p style="color: var(--color-text-subheading); font-size: 1.1rem;">Demo video will be embedded here</p>
            </div>
        </div>
    </section>

    <!-- FAQ section -->
    <section style="position: relative; z-index: 10; padding: 6rem 3rem; background: var(--color-background);">
        <div style="max-width: 900px; margin: 0 auto;">
            <p class="hero-subtitle" style="text-align: center; margin-bottom: 1rem;">Common Questions</p>
            <h2 class="hero-title" style="text-align: center; font-size: 3.5rem; margin-bottom: 1.5rem;">
                <span class="scramble-text" data-text="Frequently Asked Questions">Frequently Asked Questions</span>
            </h2>
            <p class="hero-description" style="text-align: center; max-width: 600px; margin: 0 auto 4rem;">
                Everything you need to know about SatTrack and satellite tracking.
            </p>

            <div class="faq-list" style="display: flex; flex-direction: column; gap: 1rem; margin-top: 3rem;">
                <!-- FAQ Item 1 -->
                <div class="faq-item" style="background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(20px); border: 1px solid var(--color-card-border); border-radius: 12px; overflow: hidden; transition: all 0.3s ease;">
                    <button class="faq-question" style="padding: 1.5rem; cursor: pointer; display: flex; justify-content: space-between; align-items: center; background: transparent; border: none; width: 100%; text-align: left; color: var(--color-text-heading); font-size: 1.1rem; font-weight: 500; font-family: var(--font-body); transition: color 0.3s ease;">
                        What are TLEs?
                        <span style="font-size: 1.5rem; color: var(--color-accent); transition: transform 0.3s ease; font-weight: 300; line-height: 1;">+</span>
                    </button>
                    <div class="faq-answer" style="max-height: 0; overflow: hidden; transition: max-height 0.3s ease, padding 0.3s ease; padding: 0 1.5rem;">
                        <div class="faq-answer-content" style="color: var(--color-text-body); font-size: 1rem; line-height: 1.7; padding-top: 0.5rem; padding-bottom: 1.5rem;">
                            TLE stands for Two-Line Element set. It's a data format used to describe the orbital elements of Earth-orbiting objects. 
                            SatTrack uses TLE data from reliable sources like CelesTrak to calculate accurate satellite positions and pass predictions.
                        </div>
                    </div>
                </div>

                <!-- FAQ Item 2 -->
                <div class="faq-item" style="background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(20px); border: 1px solid var(--color-card-border); border-radius: 12px; overflow: hidden; transition: all 0.3s ease;">
                    <button class="faq-question" style="padding: 1.5rem; cursor: pointer; display: flex; justify-content: space-between; align-items: center; background: transparent; border: none; width: 100%; text-align: left; color: var(--color-text-heading); font-size: 1.1rem; font-weight: 500; font-family: var(--font-body); transition: color 0.3s ease;">
                        How do you calculate pass predictions?
                        <span style="font-size: 1.5rem; color: var(--color-accent); transition: transform 0.3s ease; font-weight: 300; line-height: 1;">+</span>
                    </button>
                    <div class="faq-answer" style="max-height: 0; overflow: hidden; transition: max-height 0.3s ease, padding 0.3s ease; padding: 0 1.5rem;">
                        <div class="faq-answer-content" style="color: var(--color-text-body); font-size: 1rem; line-height: 1.7; padding-top: 0.5rem; padding-bottom: 1.5rem;">
                            Pass predictions are calculated using SGP4 (Simplified General Perturbations) propagation algorithms. 
                            We use the satellite's TLE data along with your location coordinates to determine when a satellite will be visible above your horizon. 
                            The calculations consider factors like elevation angle, visibility conditions, and orbital mechanics.
                        </div>
                    </div>
                </div>

                <!-- FAQ Item 3 -->
                <div class="faq-item" style="background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(20px); border: 1px solid var(--color-card-border); border-radius: 12px; overflow: hidden; transition: all 0.3s ease;">
                    <button class="faq-question" style="padding: 1.5rem; cursor: pointer; display: flex; justify-content: space-between; align-items: center; background: transparent; border: none; width: 100%; text-align: left; color: var(--color-text-heading); font-size: 1.1rem; font-weight: 500; font-family: var(--font-body); transition: color 0.3s ease;">
                        How can I reach out to you?
                        <span style="font-size: 1.5rem; color: var(--color-accent); transition: transform 0.3s ease; font-weight: 300; line-height: 1;">+</span>
                    </button>
                    <div class="faq-answer" style="max-height: 0; overflow: hidden; transition: max-height 0.3s ease, padding 0.3s ease; padding: 0 1.5rem;">
                        <div class="faq-answer-content" style="color: var(--color-text-body); font-size: 1rem; line-height: 1.7; padding-top: 0.5rem; padding-bottom: 1.5rem;">
                            You can reach us at <a href="mailto:support@sattrack.com" style="color: var(--color-accent); text-decoration: underline;">support@sattrack.com</a> for any questions, 
                            feedback, or support requests. We're here to help!
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Still Have Questions Section -->
    <section style="position: relative; z-index: 10; padding: 6rem 3rem; min-height: 400px;">
        <canvas id="questionsStarfield" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1;"></canvas>
        <div style="max-width: 900px; margin: 0 auto; position: relative; z-index: 2; text-align: center;">
            <h2 style="font-size: 1.8rem; color: var(--color-text-heading); margin-bottom: 1rem;">Still Have Questions?</h2>
            <p style="color: var(--color-text-body); font-size: 1.05rem; margin-bottom: 1rem;">
                Need more help or have additional questions?
            </p>
            <a href="help.php" class="btn-primary" style="display: inline-block;">Visit Help Center</a>
        </div>
    </section>

    <!-- Footer -->
    <footer style="position: relative; z-index: 10; background: var(--color-background); border-top: 1px solid var(--color-card-border); padding: 4rem 3rem 2rem;">
        <div style="max-width: 1200px; margin: 0 auto;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 3rem; margin-bottom: 3rem;">
                <div>
                    <h1 class="hero-title" style="font-size: 2rem; margin-bottom: 1rem;">
                        <span class="highlight">SatTrack</span>
                    </h1>
                    <p style="color: var(--color-text-subheading); max-width: 300px; line-height: 1.6;">
                        Real-time satellite tracking for observers everywhere.
                    </p>
                </div>
                <div>
                    <h4 style="color: var(--color-text-heading); margin-bottom: 1rem; font-weight: 600;">More Information</h4>
                    <ul style="list-style: none; padding: 0; display: flex; flex-direction: column; gap: 0.75rem;">
                        <li><a href="dashboard.php" style="color: var(--color-text-body); text-decoration: none; transition: color 0.3s;">Dashboard</a></li>
                        <li><a href="about.php" style="color: var(--color-text-body); text-decoration: none; transition: color 0.3s;">About</a></li>
                        <li><a href="help.php" style="color: var(--color-text-body); text-decoration: none; transition: color 0.3s;">Help</a></li>
                        <li><a href="https://github.com/marzafiee" target="_blank" style="color: var(--color-text-body); text-decoration: none; transition: color 0.3s;">GitHub</a></li>
                    </ul>
                </div>
            </div>
            <div style="text-align: center; padding-top: 2rem; border-top: 1px solid var(--color-card-border);">
                <p style="color: var(--color-text-subheading); font-size: 0.95rem;">
                    Made with love by <a href="https://github.com/marzafiee" target="_blank" class="scramble-text" data-text="inez" style="font-style: italic; text-decoration: underline; color: var(--color-text-body); text-decoration-color: var(--color-accent);">inez</a>💜✨
                </p>
            </div>
        </div>
    </footer>

    <!-- full screen starfield bg -->
    <canvas id="starfield"></canvas>

    <!-- my scramble text effect -->
    <script src="assets/js/scrambleText.js"></script>
    
    <script>
        // smooth scroll for demo link
        document.querySelectorAll('a[href="#demo"]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const demoSection = document.getElementById('demo');
                if (demoSection) {
                    demoSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
        
        // Navbar scroll effect - show logo on scroll, add glassmorphism
        const nav = document.getElementById('mainNav');
        const navLogo = document.getElementById('navLogo');
        let lastScroll = 0;
        
        window.addEventListener('scroll', () => {
            const currentScroll = window.pageYOffset;
            
            if (currentScroll > 100) {
                navLogo.style.opacity = '1';
                nav.style.background = 'linear-gradient(180deg, rgba(13, 13, 13, 0.98) 0%, rgba(13, 13, 13, 0.98) 100%)';
                nav.style.backdropFilter = 'blur(20px)';
                nav.style.borderBottom = '1px solid rgba(255, 255, 255, 0.12)';
            } else {
                navLogo.style.opacity = '0';
                nav.style.background = 'linear-gradient(180deg, rgba(13, 13, 13, 0.85) 0%, rgba(13, 13, 13, 0.85) 100%)';
                nav.style.backdropFilter = 'blur(10px)';
                nav.style.borderBottom = '1px solid rgba(255, 255, 255, 0.05)';
            }
            
            lastScroll = currentScroll;
        });
        
        // FAQ toggle functionality
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', () => {
                const faqItem = question.closest('.faq-item');
                const isActive = faqItem.classList.contains('active');
                const plusSign = question.querySelector('span');
                
                // Close all FAQ items
                document.querySelectorAll('.faq-item').forEach(item => {
                    item.classList.remove('active');
                    const sign = item.querySelector('.faq-question span');
                    if (sign) sign.style.transform = 'rotate(0deg)';
                });
                
                // Open clicked item if it wasn't active
                if (!isActive) {
                    faqItem.classList.add('active');
                    if (plusSign) plusSign.style.transform = 'rotate(45deg)';
                }
            });
        });
        
        // Still Have Questions section starfield
        const questionsStarfieldCanvas = document.getElementById('questionsStarfield');
        if (questionsStarfieldCanvas) {
            const questionsStarfieldCtx = questionsStarfieldCanvas.getContext('2d');
            
            function resizeQuestionsStarfield() {
                const section = document.querySelector('section:has(#questionsStarfield)');
                if (section) {
                    questionsStarfieldCanvas.width = section.offsetWidth;
                    questionsStarfieldCanvas.height = section.offsetHeight;
                }
            }
            resizeQuestionsStarfield();
            window.addEventListener('resize', resizeQuestionsStarfield);
            
            const questionsStarCount = 200;
            const questionsStars = [];
            
            for (let i = 0; i < questionsStarCount; i++) {
                questionsStars.push({
                    x: Math.random() * questionsStarfieldCanvas.width,
                    y: Math.random() * questionsStarfieldCanvas.height,
                    radius: Math.random() * 1.2 + 0.3,
                    opacity: Math.random() * 0.25 + 0.2,
                    twinkleSpeed: Math.random() * 0.02 + 0.09
                });
            }
            
            function drawQuestionsStarfield() {
                questionsStarfieldCtx.clearRect(0, 0, questionsStarfieldCanvas.width, questionsStarfieldCanvas.height);
                questionsStarfieldCtx.fillStyle = '#ffffff';
                
                questionsStars.forEach(star => {
                    star.opacity += Math.sin(Date.now() * star.twinkleSpeed) * 0.02;
                    star.opacity = Math.max(0.9, Math.min(0.35, star.opacity));
                    
                    questionsStarfieldCtx.globalAlpha = star.opacity;
                    questionsStarfieldCtx.beginPath();
                    questionsStarfieldCtx.arc(star.x, star.y, star.radius, 0, Math.PI * 2);
                    questionsStarfieldCtx.fill();
                });
                
                questionsStarfieldCtx.globalAlpha = 1;
                requestAnimationFrame(drawQuestionsStarfield);
            }
            drawQuestionsStarfield();
        }
        
        // Demo section starfield
        const demoStarfieldCanvas = document.getElementById('demoStarfield');
        if (demoStarfieldCanvas) {
            const demoStarfieldCtx = demoStarfieldCanvas.getContext('2d');
            
            function resizeDemoStarfield() {
                const section = document.getElementById('demo');
                if (section) {
                    demoStarfieldCanvas.width = section.offsetWidth;
                    demoStarfieldCanvas.height = section.offsetHeight;
                }
            }
            resizeDemoStarfield();
            window.addEventListener('resize', resizeDemoStarfield);
            
            const demoStarCount = 200;
            const demoStars = [];
            
            for (let i = 0; i < demoStarCount; i++) {
                demoStars.push({
                    x: Math.random() * demoStarfieldCanvas.width,
                    y: Math.random() * demoStarfieldCanvas.height,
                    radius: Math.random() * 1.2 + 0.3,
                    opacity: Math.random() * 0.25 + 0.2,
                    twinkleSpeed: Math.random() * 0.02 + 0.09
                });
            }
            
            function drawDemoStarfield() {
                demoStarfieldCtx.clearRect(0, 0, demoStarfieldCanvas.width, demoStarfieldCanvas.height);
                demoStarfieldCtx.fillStyle = '#ffffff';
                
                demoStars.forEach(star => {
                    star.opacity += Math.sin(Date.now() * star.twinkleSpeed) * 0.02;
                    star.opacity = Math.max(0.9, Math.min(0.35, star.opacity));
                    
                    demoStarfieldCtx.globalAlpha = star.opacity;
                    demoStarfieldCtx.beginPath();
                    demoStarfieldCtx.arc(star.x, star.y, star.radius, 0, Math.PI * 2);
                    demoStarfieldCtx.fill();
                });
                
                demoStarfieldCtx.globalAlpha = 1;
                requestAnimationFrame(drawDemoStarfield);
            }
            drawDemoStarfield();
        }
    </script>
    
    <style>
        .btn-play:hover .btn-play-text {
            opacity: 1 !important;
            width: auto !important;
            margin-left: 8px !important;
        }
        
        .btn-play:hover {
            width: auto !important;
            padding: 0 1.5rem !important;
        }
        
        .faq-item:hover {
            border-color: var(--color-accent);
            box-shadow: 0 8px 24px rgba(99, 96, 255, 0.1);
        }
        
        .faq-question:hover {
            color: var(--color-accent);
        }
        
        .faq-item.active .faq-answer {
            max-height: 500px !important;
            padding: 0 1.5rem 1.5rem !important;
        }
        
        .faq-item.active .faq-answer .faq-answer-content {
            opacity: 1;
        }
        
        .faq-item:not(.active) .faq-answer {
            max-height: 0 !important;
            padding: 0 1.5rem !important;
        }
    </style>

    <!-- Three.js -->
    <script src="https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.min.js"></script>
    <script>
        // full screen starfield - separate canvas for background
        const starfieldCanvas = document.getElementById('starfield');
        const starfieldCtx = starfieldCanvas.getContext('2d');
        
        function resizeStarfield() {
            starfieldCanvas.width = window.innerWidth;
            starfieldCanvas.height = window.innerHeight;
        }
        resizeStarfield();
        window.addEventListener('resize', resizeStarfield);

        // generate stars that fill the screen 
        const starCount = 300;
        const stars = [];
        
        for (let i = 0; i < starCount; i++) {
            stars.push({
                x: Math.random() * starfieldCanvas.width,
                y: Math.random() * starfieldCanvas.height,
                radius: Math.random() * 1.2 + 0.3,
                opacity: Math.random() * 0.25 + 0.2,
                twinkleSpeed: Math.random() * 0.02 + 0.09
            });
        }

        function drawStarfield() {
            starfieldCtx.clearRect(0, 0, starfieldCanvas.width, starfieldCanvas.height);
            starfieldCtx.fillStyle = '#ffffff';
            
            stars.forEach(star => {
                star.opacity += Math.sin(Date.now() * star.twinkleSpeed) * 0.02;
                star.opacity = Math.max(0.9, Math.min(0.35, star.opacity));
                
                starfieldCtx.globalAlpha = star.opacity;
                starfieldCtx.beginPath();
                starfieldCtx.arc(star.x, star.y, star.radius, 0, Math.PI * 2);
                starfieldCtx.fill();
            });
            
            starfieldCtx.globalAlpha = 1;
            requestAnimationFrame(drawStarfield);
        }
        drawStarfield();

        // Earth 3D Scene setup
        const container = document.getElementById('earth-container');
        const scene = new THREE.Scene();
        const camera = new THREE.PerspectiveCamera(
            45,
            container.clientWidth / container.clientHeight,
            0.1,
            1000
        );
        const renderer = new THREE.WebGLRenderer({ 
            antialias: true, 
            alpha: true 
        });

        // ensure container is square so the globe isn't clipped since it happened before
        container.style.width = container.style.width || '';
        function fitEarthContainer() {
            // square container that fits within CSS max-width/height
            const w = container.clientWidth;
            container.style.height = w + 'px';
            renderer.setSize(container.clientWidth, container.clientHeight);
            renderer.setPixelRatio(window.devicePixelRatio);
        }

        // create renderer and append canvas
        renderer.setSize(container.clientWidth || 200, container.clientHeight || 200);
        renderer.setPixelRatio(window.devicePixelRatio);
        container.appendChild(renderer.domElement);

        // call once to set proper sizes based on CSS
        fitEarthContainer();

        // create Earth. lol
        const geometry = new THREE.SphereGeometry(2.5, 64, 64);

        // load Earth texture
        const textureLoader = new THREE.TextureLoader();
        const earthTexture = textureLoader.load('https://raw.githubusercontent.com/turban/webgl-earth/master/images/2_no_clouds_4k.jpg');
        const bumpMap = textureLoader.load('https://raw.githubusercontent.com/turban/webgl-earth/master/images/elev_bump_4k.jpg');

        const material = new THREE.MeshPhongMaterial({
            map: earthTexture,
            bumpMap: bumpMap,
            bumpScale: 0.05,
            shininess: 20,
            specular: new THREE.Color(0x666666),
            emissive: new THREE.Color(0x111111),
            emissiveIntensity: 0.2
        });

        const earth = new THREE.Mesh(geometry, material);
        scene.add(earth);

        // add atmospheric glow
        const atmosphereGeometry = new THREE.SphereGeometry(2.65, 64, 64);
        const atmosphereMaterial = new THREE.MeshBasicMaterial({
            color: 0x6360ff,
            transparent: true,
            opacity: 0.2,
            side: THREE.BackSide
        });
        const atmosphere = new THREE.Mesh(atmosphereGeometry, atmosphereMaterial);
        scene.add(atmosphere);

        // Lighting
        const ambientLight = new THREE.AmbientLight(0x555555, 1.5);
        scene.add(ambientLight);

        const directionalLight = new THREE.DirectionalLight(0xffffff, 1.8);
        directionalLight.position.set(5, 3, 5);
        scene.add(directionalLight);

        const pointLight = new THREE.PointLight(0x6699ff, 0.8);
        pointLight.position.set(-5, -3, -5);
        scene.add(pointLight);
        
        // additional fill light for brightness
        const fillLight = new THREE.DirectionalLight(0xffffff, 0.5);
        fillLight.position.set(-3, 2, -3);
        scene.add(fillLight);

        // Camera position
        camera.position.set(0, 0, 8);
        camera.lookAt(0, 0, 0);

        // Mouse interaction
        let mouseX = 0;
        let mouseY = 0;
        let targetX = 0;
        let targetY = 0;

        document.addEventListener('mousemove', (e) => {
            mouseX = (e.clientX / window.innerWidth) * 2 - 1;
            mouseY = -(e.clientY / window.innerHeight) * 2 + 1;
        });

        // Animation loop
        function animate() {
            requestAnimationFrame(animate);
            
            // rotate globe slowly
            earth.rotation.y += 0.002;
            atmosphere.rotation.y += 0.002;
            
            // camera movement based on mouse movements
            targetX = mouseX * 0.3;
            targetY = mouseY * 0.3;
            
            camera.position.x += (targetX - camera.position.x) * 0.05;
            camera.position.y += (targetY - camera.position.y) * 0.05;
            camera.lookAt(scene.position);
            
            renderer.render(scene, camera);
        }

        animate();

        // window resize handler
        window.addEventListener('resize', () => {
            // adjust container to remain square and update renderer
            fitEarthContainer();
            camera.aspect = container.clientWidth / container.clientHeight;
            camera.updateProjectionMatrix();
        });
    </script>
</body>
</html>