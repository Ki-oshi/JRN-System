<?php
session_start();
require_once 'connection/dbconn.php';
require_once __DIR__ . '/vendor/autoload.php';

// Ensure the inquiry summary is available in the session
if (!isset($_SESSION['inquiry_summary'])) {
    $_SESSION['error'] = 'Inquiry summary is not available.';
    header("Location: account_page.php");
    exit();
}

$summary = $_SESSION['inquiry_summary'];

// Clean up the session to avoid reusing the summary
unset($_SESSION['inquiry_summary']);

// Ensure that the required summary data is available
if (!isset($summary['inquiry_number'], $summary['service_name'], $summary['created_at'], $summary['uploaded_files'])) {
    $_SESSION['error'] = 'Invalid inquiry summary data.';
    header("Location: account_page.php");
    exit();
}

// Build tracking URL encoded in the QR
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . $_SERVER['HTTP_HOST'];
$baseUrl .= rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$payload  = $baseUrl . '/track_inquiry.php?ref=' . urlencode($summary['inquiry_number']);

// --- QR generation using goQR / qrserver API ---
$qrDir = __DIR__ . '/uploads/qrcodes/';
if (!is_dir($qrDir)) {
    mkdir($qrDir, 0755, true);
}

$qrFilename  = 'inquiry_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $summary['inquiry_number']) . '.png';
$qrSavePath  = $qrDir . $qrFilename;             // filesystem path
$qrPublicUrl = 'uploads/qrcodes/' . $qrFilename; // browser path

// Build remote API URL (PNG, 150x150)
$apiUrl = 'https://api.qrserver.com/v1/create-qr-code/?'
    . http_build_query([
        'size' => '150x150',
        'data' => $payload,
        'charset-source' => 'UTF-8',
        'charset-target' => 'UTF-8',
    ]);

// Download PNG bytes from API and save locally
$pngData = file_get_contents($apiUrl); // must have allow_url_fopen enabled
if ($pngData !== false) {
    file_put_contents($qrSavePath, $pngData); // Save the file
}

// Save QR path into inquiries.qr_code_path
$update = $conn->prepare("UPDATE inquiries SET qr_code_path = ? WHERE inquiry_number = ?");
$update->bind_param("ss", $qrPublicUrl, $summary['inquiry_number']);
$update->execute();
$update->close();

// Company information (for footer)
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

if (isset($_SESSION['user_id'])) {
    $quickLinks[] = ['text' => 'Account', 'url' => 'account_page.php'];
} else {
    $quickLinks[] = ['text' => 'Login', 'url' => 'login.php'];
    $quickLinks[] = ['text' => 'Sign Up', 'url' => 'signup.php'];
}

// Resource links
$resourceLinks = [
    ['text' => 'Images Use', 'url' => 'https://www.pexels.com/', 'external' => true],
    ['text' => 'FAQ',        'url' => '#faq'],
    ['text' => 'Support',    'url' => '#support'],
    ['text' => 'Contact Us', 'url' => '#contact']
];

// Social links
$socialLinks = [
    ['name' => 'Facebook',  'icon' => 'facebook.svg',  'url' => '#'],
    ['name' => 'Twitter',   'icon' => 'twitter.svg',   'url' => '#'],
    ['name' => 'Instagram', 'icon' => 'instagram.svg', 'url' => '#'],
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Inquiry Submission Summary | JRN Business Solutions Co.</title>
    <link rel="icon" type="image/x-icon" href="assets/img/Logo.jpg" />
    <link rel="stylesheet" href="assets/css/global.css" />
    <link rel="stylesheet" href="assets/css/index.css" />
    <link rel="stylesheet" href="assets/css/account-page.css" />
    <link rel="stylesheet" href="assets/css/inquiry-summary.css" />
    <script src="https://kit.fontawesome.com/a2e0e6d6f3.js" crossorigin="anonymous"></script>
</head>

<body>
    <header class="navbar">
        <div class="logo-container">
            <img src="assets/img/Logo.jpg" alt="JRN Logo" class="logo-img" />
            <span class="logo-text">JRN Business Solutions Co.</span>
        </div>
        <div class="hamburger" onclick="toggleMenu()"><span></span><span></span><span></span></div>
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

    <main>
        <section class="inquiry-summary-hero"></section>
        <div class="inquiry-summary-landscape">
            <div class="summary-details-col">
                <h2>
                    <span class="docs-icon-bg">
                        <i class="fas fa-file-alt"></i>
                    </span> Inquiry Submitted!
                </h2>
                <div class="summary-row">
                    <div>
                        <span class="summary-label">Reference #</span>
                        <span class="summary-value">
                            <?php echo htmlspecialchars($summary['inquiry_number']); ?>
                        </span>
                    </div>
                    <div>
                        <span class="summary-label">Service</span>
                        <span class="summary-value">
                            <?php echo htmlspecialchars($summary['service_name']); ?>
                        </span>
                    </div>
                    <div>
                        <span class="summary-label">Submitted</span>
                        <span class="summary-value">
                            <?php echo htmlspecialchars($summary['created_at']); ?>
                        </span>
                    </div>
                    <?php if (!empty($summary['additional_notes'])): ?>
                        <div>
                            <span class="summary-label">Notes</span>
                            <span class="summary-value">
                                <?php echo nl2br(htmlspecialchars($summary['additional_notes'])); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="summary-section">
                    <span class="summary-label">Uploaded Documents:</span>
                    <ul class="summary-files">
                        <?php foreach ($summary['uploaded_files'] as $file): ?>
                            <li>
                                <i class="fas fa-paperclip"></i>
                                <?php echo htmlspecialchars($file['original_name']); ?>
                                <span class="file-meta">
                                    (<?php echo strtoupper($file['file_type']); ?>,
                                    <?php echo round($file['file_size'] / 1024); ?> KB)
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <button class="summary-btn" onclick="window.location.href='account_page.php#services'">
                    <i class="fas fa-clipboard-list"></i> View Full Service Details
                </button>
            </div>

            <div class="summary-qr-col">
                <h3>
                    <span class="qr-icon-bg"><i class="fas fa-qrcode"></i></span>
                    Inquiry QR Code
                </h3>

                <!-- Display QR directly from PNG file -->
                <img class="qr-img" id="qrcode-img"
                    src="<?php echo htmlspecialchars($qrPublicUrl); ?>"
                    alt="Inquiry QR Code" />

                <button type="button" class="summary-btn download-btn"
                    id="downloadQRBtn"
                    data-download-url="<?php echo htmlspecialchars($qrPublicUrl); ?>">
                    <i class="fas fa-download"></i> Download QR Code
                </button>

                <div class="qr-reminder">
                    <span>
                        Scan this QR code anytime to view the full details and current status
                        of your inquiry (<?php echo htmlspecialchars($summary['inquiry_number']); ?>),
                        or present it to our staff for assistance.
                    </span>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="footer-top">
            <div class="footer-logo-desc">
                <div class="footer-logo-name">
                    <img src="assets/img/logo.jpg" alt="JRN Logo" class="footer-logo">
                    <h3><?php echo htmlspecialchars($companyInfo['name']); ?></h3>
                </div>
                <p>Providing end-to-end business solutions including legal documents processing, tax compliance, payroll, and accounting services to help your business grow.</p>
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
                                    <?php echo !empty($link['external']) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                    <?php echo htmlspecialchars($link['text']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <p>
                &copy; <?php echo date("Y"); ?> <?php echo htmlspecialchars($companyInfo['name']); ?>.
                All Rights Reserved. |
                <a href="privacy.php">Privacy Policy</a> |
                <a href="terms.php">Terms of Service</a>
            </p>
        </div>
    </footer>

    <script>
        document.getElementById("downloadQRBtn").addEventListener("click", function() {
            const url = this.dataset.downloadUrl; // real PNG in /uploads/qrcodes/
            const link = document.createElement('a');
            link.href = url;
            link.download = 'inquiry-qr-code.png';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    </script>
</body>

</html>