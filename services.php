<?php
session_start();
require_once 'includes/auth.php';

$conn = new mysqli('localhost', 'root', '', 'jrndb'); // Update DB credentials if needed
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$q = $conn->query("SELECT * FROM services WHERE is_active = 1 ORDER BY display_order ASC, id DESC");
$services = [];
while ($row = $q->fetch_assoc()) {
    $services[] = $row;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Our Services - JRN Business Solutions Co.</title>
    <link rel="icon" type="image/x-icon" href="assets/img/Logo.jpg" />
    <link rel="stylesheet" href="assets/css/global.css" />
    <link rel="stylesheet" href="assets/css/index.css" />
    <link rel="stylesheet" href="assets/css/services.css" />
    <link rel="stylesheet" href="assets/css/responsive.css" />
    <link rel="stylesheet" href="assets/css/logout-modal.css" />
</head>

<body>
    <!-- Navbar -->
    <header class="navbar">
        <div class="logo-container">
            <img src="assets/img/logo.jpg" alt="JRN Logo" class="logo-img" />
            <span class="logo-text">JRN Business Solutions Co.</span>
        </div>

        <div class="hamburger" onclick="toggleMenu()">
            <span></span>
            <span></span>
            <span></span>
        </div>

        <nav>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="services.php" class="active">Services</a></li>
                <li><a href="index.php#about">About Us</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="account_page.php">Account</a></li>
                    <li><a href="#" id="logout-btn">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <!-- Hero -->
    <section class="services-page-hero">
        <div class="hero-content">
            <h1>Our Services</h1>
            <p>
                From registration to compliance, we provide comprehensive services to
                help your business succeed.
            </p>
        </div>
    </section>

    <!-- Services Section -->
    <section class="services-section">
        <div class="services-container">

            <!-- Example grouping by category: -->
            <?php
            // Group services by category 
            $grouped = [];
            foreach ($services as $service) {
                $category = $service['category'] ?: 'Uncategorized';
                $grouped[$category][] = $service;
            }

            foreach ($grouped as $categoryName => $servicesInCategory): ?>
                <div class="service-category">
                    <div class="category-header">
                        <div class="category-icon">
                            <!-- You can place SVG or icon here; for demo, we use a generic icon -->
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="32" height="32" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10" />
                                <path d="M14.31 8l5.74 9.94" />
                                <path d="M9.69 8h11.48" />
                                <path d="M7.38 12l5.74-9.94" />
                                <path d="M9.69 16L3.95 6.06" />
                                <path d="M14.31 16H2.83" />
                                <path d="M16.62 12l-5.74 9.94" />
                            </svg>
                        </div>
                        <h2><?= htmlspecialchars($categoryName) ?></h2>
                    </div>

                    <div class="services-grid">
                        <?php foreach ($servicesInCategory as $s): ?>
                            <a href="service-display.php?slug=<?= urlencode($s['slug']) ?>" class="service-card">
                                <div class="service-card-header">
                                    <h3><?= htmlspecialchars($s['name']) ?></h3>
                                    <?php if ($s['category']): ?>
                                        <span class="service-badge"><?= htmlspecialchars($s['category']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <p><?= htmlspecialchars($s['short_description']) ?></p>
                                <span class="service-arrow">→</span>
                            </a>
                        <?php endforeach; ?>

                        <!-- Coming Soon card always at the end -->
                        <div class="service-card">
                            <div class="service-card-header">
                                <h3>New Services Coming Soon</h3>
                            </div>
                            <p>
                                <span style="font-weight:500;">Look out for upcoming services designed to help your business succeed.</span>
                            </p>
                            <span class="service-arrow">→</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>
    </section>

    <!-- Logout Modal -->
    <div class="logout-modal-overlay" id="logout-modal-overlay">
        <div class="logout-modal">
            <h2>Confirm Logout</h2>
            <p>Are you sure you want to log out?</p>
            <div class="logout-modal-buttons">
                <button class="logout-btn-confirm" id="logout-confirm">Yes</button>
                <button class="logout-btn-cancel" id="logout-cancel">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-top">
            <div class="footer-logo-desc">
                <div class="footer-logo-name">
                    <img src="assets/img/Logo.jpg" alt="JRN Logo" class="footer-logo" />
                    <h3>JRN Business Solutions Co.</h3>
                </div>
                <p>
                    Providing end-to-end business solutions including legal documents
                    processing, tax compliance, payroll, and accounting services to help
                    your business grow.
                </p>
                <div class="footer-socials">
                    <a href="#" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                        <img src="assets/img/icons/facebook.svg" alt="Facebook" height="24" />
                    </a>
                    <a href="#" target="_blank" rel="noopener noreferrer" aria-label="Twitter">
                        <img src="assets/img/icons/twitter.svg" alt="Twitter" height="24" />
                    </a>
                    <a href="#" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                        <img src="assets/img/icons/instagram.svg" alt="Instagram" height="24" />
                    </a>
                </div>
            </div>
            <div class="footer-links">
                <div class="footer-column">
                    <h4>Quick Access</h4>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="services.php">Services</a></li>
                        <li><a href="index.php#about">About Us</a></li>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <li><a href="account_page.php">Account</a></li>
                        <?php else: ?>
                            <li><a href="login.php">Login</a></li>
                            <li><a href="signup.php">Sign Up</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="footer-column">
                    <h4>Resources</h4>
                    <ul>
                        <li><a href="https://www.pexels.com/" target="_blank" rel="noopener noreferrer">Images Use</a></li>
                        <li><a href="#faq">FAQ</a></li>
                        <li><a href="#support">Support</a></li>
                        <li><a href="#contact">Contact Us</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>
                &copy; <?= date("Y") ?> JRN Business Solutions Co. All Rights Reserved. |
                <a href="privacy.php">Privacy Policy</a> |
                <a href="terms.php">Terms of Service</a>
            </p>
        </div>
    </footer>

    <script>
        function toggleMenu() {
            document.querySelector('.nav-links').classList.toggle('active');
            document.querySelector('.hamburger').classList.toggle('active');
        }
    </script>
    <script src="assets/js/logout-modal.js"></script>
</body>

</html>