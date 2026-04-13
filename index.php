<?php
session_start();
require_once 'includes/auth.php';

// Define services
$services = [
    [
        'icon' => 'FileText.svg',
        'title' => 'Business Registration',
        'description' => 'Essential legal registration services to establish your business in the Philippines.',
        'items' => [
            ['name' => 'DTI Registration', 'detail' => 'Sole proprietorship business name registration'],
            ['name' => 'SEC Registration', 'detail' => 'Corporation and partnership registration'],
            ['name' => "Mayor's Permit", 'detail' => 'Local government business permit'],
            ['name' => 'BIR Registration', 'detail' => 'Tax identification and compliance setup']
        ]
    ],
    [
        'icon' => 'BriefcaseBusiness.svg',
        'title' => 'Business Processing',
        'description' => 'Manage changes, renewals, and compliance for existing business registrations.',
        'items' => [
            ['name' => 'Business Closure', 'detail' => 'Proper deregistration and shutdown procedures'],
            ['name' => 'Permit Renewal', 'detail' => 'Annual renewal of business permits'],
            ['name' => 'Registration Amendment', 'detail' => 'Update business information and documents'],
            ['name' => 'BIR Open Cases', 'detail' => 'Resolve unfiled returns and tax issues']
        ]
    ],
    [
        'icon' => 'FileChartColumn.svg',
        'title' => 'Accounting & Tax',
        'description' => 'Professional accounting, bookkeeping, and tax compliance services.',
        'items' => [
            ['name' => 'Bookkeeping', 'detail' => 'Accurate financial record maintenance'],
            ['name' => 'Retainership', 'detail' => 'Ongoing monthly accounting support'],
            ['name' => 'BIR Tax Filing', 'detail' => 'Timely filing of all tax returns'],
            ['name' => 'Annual Income Tax', 'detail' => 'Year-end ITR preparation and filing']
        ]
    ],
    [
        'icon' => 'Users.svg',
        'title' => 'Advisory & Management',
        'description' => 'Expert consultation and payroll management for business optimization.',
        'items' => [
            ['name' => 'Business Consultation', 'detail' => 'Strategic guidance for growth'],
            ['name' => 'Tax Advisory', 'detail' => 'Tax planning and optimization strategies'],
            ['name' => 'Payroll Management', 'detail' => 'Complete employee compensation processing']
        ]
    ]
];

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
    ['name' => 'Facebook', 'icon' => 'facebook.svg', 'url' => 'https://www.facebook.com/JRNBaras'],
    ['name' => 'Twitter', 'icon' => 'twitter.svg', 'url' => '#'],
    ['name' => 'Instagram', 'icon' => 'instagram.svg', 'url' => '#']
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($companyInfo['name']); ?> - Business Solutions Philippines</title>
    <meta name="description" content="Trusted accounting and business solutions in the Philippines. DTI, SEC, BIR registration, tax filing, bookkeeping, payroll management, and business consultation services.">
    <meta name="keywords" content="accounting services philippines, business registration, DTI registration, SEC registration, BIR filing, bookkeeping, payroll, tax advisory">
    <script src="https://cdn.jsdelivr.net/npm/lucide/dist/lucide.min.js"></script>
    <link rel="icon" type="image/x-icon" href="assets/img/Logo.jpg">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/logout-modal.css">
    <link rel="stylesheet" href="assets/css/chat-bubble.css">
</head>

<body>
    <!-- Navbar -->
    <header class="navbar">
        <div class="logo-container">
            <img src="assets/img/logo.jpg" alt="<?php echo htmlspecialchars($companyInfo['name']); ?> Logo" class="logo-img">
            <span class="logo-text"><?php echo htmlspecialchars($companyInfo['name']); ?></span>
        </div>

        <div class="hamburger" onclick="toggleMenu()" aria-label="Toggle menu">
            <span></span>
            <span></span>
            <span></span>
        </div>

        <nav>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="service-display.php">Services</a></li>
                <li><a href="#about">About Us</a></li>
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
    <section class="hero">
        <div class="hero-content">
            <h1>Complete Business &amp; Accounting Solutions</h1>
            <p>From business registration to tax compliance and payroll—we handle the paperwork so you can focus on growing your business.</p>
            <a href="services.php" class="btn">Inquire Now!</a>
        </div>
    </section>

    <!-- Services -->
    <section id="services" class="services">
        <h2>Comprehensive Services for Your Business</h2>
        <p class="services-intro">We offer <?php echo htmlspecialchars($companyInfo['services_count']); ?> professional services across registration, compliance, accounting, and advisory.</p>

        <div class="services-grid">
            <?php foreach ($services as $service): ?>
                <div class="service-card">
                    <div class="service-card-header">
                        <img src="assets/img/icons/<?php echo htmlspecialchars($service['icon']); ?>"
                            alt="<?php echo htmlspecialchars($service['title']); ?>"
                            class="service-icon">
                        <h3><?php echo htmlspecialchars($service['title']); ?></h3>
                    </div>
                    <p class="service-description"><?php echo htmlspecialchars($service['description']); ?></p>
                    <ul class="service-list">
                        <?php foreach ($service['items'] as $item): ?>
                            <li>
                                <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                <span class="service-detail"><?php echo htmlspecialchars($item['detail']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- About -->
    <section id="about" class="about">
        <div class="about-content">
            <h2>About <?php echo htmlspecialchars($companyInfo['name']); ?></h2>
            <p><?php echo htmlspecialchars($companyInfo['description']); ?></p>

            <div class="about-highlights">
                <div class="highlight-item">
                    <h4>Our Mission</h4>
                    <p>To empower Filipino businesses with reliable compliance and accounting solutions that enable sustainable growth.</p>
                </div>
                <div class="highlight-item">
                    <h4>Our Expertise</h4>
                    <p>Specializing in business registration, tax compliance, bookkeeping, payroll management, and strategic business advisory across all industries.</p>
                </div>
                <div class="highlight-item">
                    <h4>Why Choose Us</h4>
                    <p>Digital e-Process system, QR code tracking, experienced professionals, guaranteed accuracy, and comprehensive support for all your business needs.</p>
                </div>
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

    <!-- Logout Modal -->
    <?php if (isset($_SESSION['user_id'])): ?>
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
    <?php endif; ?>

    <!-- Chat Bubble -->
    <div id="chat-bubble-container">
        <div id="chat-bubble">
            <svg id="chat-icon" xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            <svg id="close-icon" xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </div>
        <div id="chat-popup">
            <div id="chat-header">
                <div class="chat-header-content">
                    <div class="chat-avatar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                    <div class="chat-header-text">
                        <h4>JRN Business Solutions</h4>
                        <span class="chat-status">
                            <span class="status-dot"></span>
                            Online
                        </span>
                    </div>
                </div>
            </div>
            <div id="chat-messages"></div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/chat-bubble.js"></script>
    <script>
        lucide.createIcons();
    </script>
    <script>
        function toggleMenu() {
            document.querySelector('.nav-links').classList.toggle('active');
            document.querySelector('.hamburger').classList.toggle('active');
        }
    </script>
    <?php if (isset($_SESSION['user_id'])): ?>
        <script src="assets/js/logout-modal.js"></script>
    <?php endif; ?>
</body>

</html>
