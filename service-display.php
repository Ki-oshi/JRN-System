<?php
session_start();
require_once 'includes/auth.php';

require_once 'connection/dbconn.php';

$slug = isset($_GET['slug']) ? $_GET['slug'] : null;
$service = null;
if ($slug) {
    $stmt = $conn->prepare("SELECT * FROM services WHERE slug = ? AND is_active = 1");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    $service = $result->fetch_assoc();
    $stmt->close();
}
$conn->close();

if (!$service) {
    header("Location: services.php");
    exit;
}

// Company information
$companyInfo = [
    'name' => 'JRN Business Solutions Co.',
    'tagline' => 'Your Partner in Business Compliance and Growth',
    'description' => 'We are a dedicated accounting and business solutions firm with expertise in serving startups, SMEs, and established enterprises. Our comprehensive services ensure your business stays compliant while you focus on growth.',
    'established' => '2020',
    'services_count' => '15+',
    'clients_served' => '500+'
];

// Quick Links - conditional based on login status
$quickLinks = [
    ['text' => 'About Us', 'url' => '#about'],
    ['text' => 'Services', 'url' => 'services.php']
];

// Add conditional links based on session
if (isset($_SESSION['user_id'])) {
    $quickLinks[] = ['text' => 'Account', 'url' => 'account_page.php'];
} else {
    $quickLinks[] = ['text' => 'Login', 'url' => 'login.php'];
    $quickLinks[] = ['text' => 'Sign Up', 'url' => 'signup.php'];
}

// Resource links
$resourceLinks = [
    ['text' => 'Images Use', 'url' => 'https://www.pexels.com/', 'external' => true],
    ['text' => 'FAQ', 'url' => '#faq'],
    ['text' => 'Support', 'url' => '#support'],
    ['text' => 'Contact Us', 'url' => '#contact']
];

// Social links
$socialLinks = [
    ['name' => 'Facebook', 'icon' => 'facebook.svg', 'url' => '#'],
    ['name' => 'Twitter', 'icon' => 'twitter.svg', 'url' => '#'],
    ['name' => 'Instagram', 'icon' => 'instagram.svg', 'url' => '#']
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($service['name']) ?> - JRN Business Solutions Co.</title>
    <link rel="icon" type="image/x-icon" href="assets/img/Logo.jpg">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/services.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/logout-modal.css">

    <style>
        .service-deprecation-note {
            margin-top: 15px;
            padding: 10px 12px;
            border-left: 4px solid #e53935;
            background-color: #ffebee;
            color: #b71c1c;
            font-size: 0.9rem;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <header class="navbar">
        <div class="logo-container">
            <img src="assets/img/Logo.jpg" alt="JRN Logo" class="logo-img">
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
                <li><a href="services.php">Services</a></li>
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
    <section
        class="service-hero <?= htmlspecialchars($service['slug']) ?>"
        style="<?= $service['image']
                    ? "background-image: linear-gradient(rgba(15,58,64,0.85), rgba(15,58,64,0.85)), url('" . htmlspecialchars($service['image']) . "'); background-size: cover; background-position: center;"
                    : "" ?>">
        <div class="hero-content">
            <h1><?= htmlspecialchars($service['name']) ?></h1>
            <p><?= htmlspecialchars($service['short_description']) ?></p>
        </div>
    </section>


    <!-- Service Details -->
    <section class="service-details">
        <div class="container">
            <div class="service-long-description">
                <?= $service['long_description'] ?>
            </div>
            <?php
            $note = 'Note: This service may be discontinued in the future. We will make sure to send a notice before we completely deactivate this service.';

            if (!empty($service['scheduled_action']) && !empty($service['scheduled_effective_at'])) {
                $effective = new DateTime($service['scheduled_effective_at']);
                $dateStr   = $effective->format('F j, Y g:i A'); // e.g. March 18, 2026 4:25 PM [web:94][web:99]

                if ($service['scheduled_action'] === 'deactivate') {
                    $note = "Note: This service is scheduled to be deactivated on {$dateStr}. You may still submit inquiries until that date.";
                } elseif ($service['scheduled_action'] === 'activate') {
                    $note = "Note: This service is scheduled to be activated on {$dateStr}. You may inquire in advance, but processing will start after activation.";
                }
            }
            ?>
            <p class="service-deprecation-note">
                <?= htmlspecialchars($note) ?>
            </p>

            <div style="margin-top: 30px;">
                <a href="services.php" class="btn back-btn">Back to Services</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="inquire.php?service=<?= urlencode($service['slug']) ?>" class="btn back-btn" style="background: var(--accent); margin-left: 10px;">Inquire Now</a>
                <?php else: ?>
                    <a href="login.php" class="btn back-btn" style="background: var(--accent); margin-left: 10px;">Login to Inquire</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-top">
            <div class="footer-logo-desc">
                <div class="footer-logo-name">
                    <img src="assets/img/logo.jpg" alt="JRN Logo" class="footer-logo">
                    <h3><?php echo htmlspecialchars($companyInfo['name']); ?></h3>
                </div>
                <p>Providing end-to-end business solutions including legal documents processing, tax compliance, payroll, and accounting services to help your business grow.</p>

                <!-- Socials -->
                <div class="footer-socials">
                    <?php foreach ($socialLinks as $social): ?>
                        <a href="<?php echo htmlspecialchars($social['url']); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="<?php echo htmlspecialchars($social['name']); ?>">
                            <img src="assets/img/icons/<?php echo htmlspecialchars($social['icon']); ?>"
                                alt="<?php echo htmlspecialchars($social['name']); ?>"
                                height="24">
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Useful Links -->
            <div class="footer-links">
                <div class="footer-column">
                    <h4>Quick Access</h4>
                    <ul>
                        <?php foreach ($quickLinks as $link): ?>
                            <li>
                                <a href="<?php echo htmlspecialchars($link['url']); ?>">
                                    <?php echo htmlspecialchars($link['text']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="footer-column">
                    <h4>Resources</h4>
                    <ul>
                        <?php foreach ($resourceLinks as $link): ?>
                            <li>
                                <a href="<?php echo htmlspecialchars($link['url']); ?>"
                                    <?php echo isset($link['external']) && $link['external'] ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                    <?php echo htmlspecialchars($link['text']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Bottom -->
        <div class="footer-bottom">
            <p>
                &copy; <?php echo date("Y"); ?> <?php echo htmlspecialchars($companyInfo['name']); ?>. All Rights Reserved. |
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