<?php require_once 'includes/db_config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help - SatTrack</title>
    <link href="https://fonts.googleapis.com/css2?family=EB+Garamond:wght@400;500;600;700&family=Inconsolata:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        /* FAQ Styles */
        .faq-container {
            max-width: 900px;
            margin: 120px auto 60px;
            padding: 0 2rem;
            position: relative;
            z-index: 10;
        }

        .faq-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .faq-subtitle {
            font-family: var(--font-subheading);
            font-size: 0.9rem;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--color-text-subheading);
            margin-bottom: 0.5rem;
        }

        .faq-title {
            font-size: 3rem;
            font-weight: 600;
            color: var(--color-text-heading);
            margin-bottom: 1rem;
        }

        .faq-description {
            font-size: 1.1rem;
            color: var(--color-text-body);
            max-width: 600px;
            margin: 0 auto 3rem;
        }

        .faq-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .faq-item {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border: 1px solid var(--color-card-border);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .faq-item:hover {
            border-color: var(--color-accent);
            box-shadow: 0 8px 24px rgba(99, 96, 255, 0.1);
        }

        .faq-question {
            padding: 1.5rem;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: transparent;
            border: none;
            width: 100%;
            text-align: left;
            color: var(--color-text-heading);
            font-size: 1.1rem;
            font-weight: 500;
            font-family: var(--font-body);
            transition: color 0.3s ease;
        }

        .faq-question:hover {
            color: var(--color-accent);
        }

        .faq-question::after {
            content: '+';
            font-size: 1.5rem;
            color: var(--color-accent);
            transition: transform 0.3s ease;
            font-weight: 300;
            line-height: 1;
        }

        .faq-item.active .faq-question::after {
            transform: rotate(45deg);
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, padding 0.3s ease;
            padding: 0 1.5rem;
        }

        .faq-item.active .faq-answer {
            max-height: 500px;
            padding: 0 1.5rem 1.5rem;
        }

        .faq-answer-content {
            color: var(--color-text-body);
            font-size: 1rem;
            line-height: 1.7;
            padding-top: 0.5rem;
        }

        .contact-section {
            margin-top: 4rem;
            padding-top: 3rem;
            border-top: 1px solid var(--color-card-border);
            text-align: center;
        }

        .contact-section h2 {
            font-size: 1.8rem;
            color: var(--color-text-heading);
            margin-bottom: 1rem;
        }

        .contact-section p {
            color: var(--color-text-body);
            font-size: 1.05rem;
            margin-bottom: 1rem;
        }

        .contact-section a {
            color: var(--color-accent);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .contact-section a:hover {
            color: var(--color-text-heading);
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .faq-container {
                margin-top: 100px;
                padding: 0 1rem;
            }

            .faq-title {
                font-size: 2rem;
            }

            .faq-question {
                font-size: 1rem;
                padding: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <!-- nav bar -->
    <nav>
        <a href="index.php" class="logo">SatTrack</a>
        <ul class="nav-links">
            <li><a href="dashboard.php">Demo</a></li>
            <li><a href="passes.php">Tracker</a></li>
            <li><a href="about.php">About</a></li>
            <li><a href="help.php" class="active">Help</a></li>
            <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
                <li><button class="btn-search" onclick="window.location.href='dashboard.php'">Search</button></li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- full screen starfield bg -->
    <canvas id="starfield"></canvas>

    <!-- FAQ Container -->
    <div class="faq-container">
        <div class="faq-header">
            <p class="faq-subtitle">Support & Guidance</p>
            <h1 class="faq-title">Frequently Asked Questions</h1>
            <p class="faq-description">
                Everything you need to know about SatTrack. Whether you're new to satellite tracking or a seasoned observer, find answers to common questions below.
            </p>
        </div>

        <div class="faq-list">
            <!-- FAQ Item 1 -->
            <div class="faq-item">
                <button class="faq-question">How do I get started with SatTrack?</button>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        Getting started is easy! First, create a free account by clicking "Get Started" on the homepage. Once logged in, set your location in your profile settings (this helps us calculate accurate pass predictions). Then, browse the satellite catalog on the Dashboard and add satellites to your watchlist. Pass predictions will automatically appear in the "My Passes" section.
                    </div>
                </div>
            </div>

            <!-- FAQ Item 2 -->
            <div class="faq-item">
                <button class="faq-question">What is a satellite pass and how do I read the predictions?</button>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        A satellite pass is when a satellite becomes visible from your location. Each prediction shows the start time, end time, maximum elevation (how high it appears in the sky), and whether it will be visible to the naked eye. Higher elevation means the satellite will appear brighter and easier to spot. The "visible" indicator tells you if conditions are good for observation.
                    </div>
                </div>
        </div>

            <!-- FAQ Item 3 -->
            <div class="faq-item">
                <button class="faq-question">How accurate are the pass predictions?</button>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        Our predictions use real-time TLE (Two-Line Element) data updated daily from reliable sources like CelesTrak. Predictions are typically accurate to within a few seconds for the next 7-10 days. Accuracy may decrease for longer-term predictions due to orbital changes. We recommend checking predictions a few hours before a pass for the most accurate timing.
                    </div>
                </div>
            </div>

            <!-- FAQ Item 4 -->
            <div class="faq-item">
                <button class="faq-question">Can I track the International Space Station (ISS)?</button>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        Absolutely! The ISS is one of the most popular satellites to track. Simply search for "International Space Station" or "ISS" in the satellite catalog on your Dashboard and add it to your watchlist. The ISS is often visible to the naked eye and appears as a bright, fast-moving point of light crossing the sky. It's perfect for beginners!
                    </div>
                </div>
            </div>

            <!-- FAQ Item 5 -->
            <div class="faq-item">
                <button class="faq-question">How do I change my location for pass predictions?</button>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        You can update your location in two ways: 1) Go to your Profile page and update your default latitude and longitude coordinates, or 2) On the Dashboard, click the "Change" button next to your location display. Enter your coordinates (you can find them using Google Maps or any GPS app). Once updated, all pass predictions will automatically recalculate for your new location.
                    </div>
                </div>
            </div>

            <!-- FAQ Item 6 -->
            <div class="faq-item">
                <button class="faq-question">What satellites can I track on SatTrack?</button>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        SatTrack includes thousands of satellites including the International Space Station (ISS), Starlink satellites, weather satellites, communication satellites, scientific missions, and more. You can browse the full catalog on your Dashboard and filter by satellite type. Popular choices for beginners include the ISS, Hubble Space Telescope, and bright weather satellites.
                    </div>
                </div>
            </div>

            <!-- FAQ Item 7 -->
            <div class="faq-item">
                <button class="faq-question">What does "visible" mean in pass predictions?</button>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        A pass is marked as "visible" when the satellite will be bright enough and high enough in the sky to be seen with the naked eye under good conditions. This depends on factors like the satellite's altitude, the angle of sunlight reflecting off it, and your local sky conditions. Even if a pass isn't marked as visible, you might still be able to spot it with binoculars or a telescope.
                    </div>
                </div>
            </div>
            </div>

        <div class="contact-section">
            <h2>Still Have Questions?</h2>
            <p>
                Can't find what you're looking for? Need technical support?
            </p>
            <p>
                Reach out to us at: <a href="mailto:support@sattrack.com">support@sattrack.com</a>
            </p>
        </div>
    </div>

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

        // FAQ Toggle Functionality
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', () => {
                const faqItem = question.parentElement;
                const isActive = faqItem.classList.contains('active');
                
                // Close all FAQ items
                document.querySelectorAll('.faq-item').forEach(item => {
                    item.classList.remove('active');
                });
                
                // Open clicked item if it wasn't active
                if (!isActive) {
                    faqItem.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>
