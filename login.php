<?php
session_start();
include 'connection/dbconn.php';
require_once 'includes/auth.php';

// Redirect if already logged in
blockIfLoggedIn();

// Handle email verification redirect
if (isset($_GET['verified'])) {
    $status = $_GET['verified'];
    if ($status == 1) {
        $verificationMessage = "Email verified successfully! You can now log in.";
        $messageType = "success";
    } elseif ($status == 0) {
        $verificationMessage = "Invalid or expired verification link.";
        $messageType = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | JRN Business Solutions Co.</title>
    <meta name="description" content="Login to your JRN Business Solutions account to track applications and manage documents.">
    <link rel="icon" type="image/x-icon" href="assets/img/logo.jpg">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
</head>

<body>
    <!-- Navbar -->
    <header class="navbar">
        <div class="logo-container">
            <img src="assets/img/logo.jpg" alt="JRN Logo" class="logo-img">
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
                <li><a href="signup.php">Sign Up</a></li>
            </ul>
        </nav>
    </header>

    <!-- Login Section -->
    <section class="form-section">
        <div class="form-container">
            <div class="form-card">
                <!-- Left side - Branding -->
                <div class="form-brand">
                    <div class="brand-content">
                        <img src="assets/img/logo.jpg" alt="JRN Logo" class="brand-logo">
                        <h2>JRN Business Solutions Co.</h2>
                        <p>Your trusted partner in business compliance and growth</p>

                        <div class="brand-features">
                            <div class="feature-item">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                                <span>Track applications in real-time</span>
                            </div>
                            <div class="feature-item">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                                <span>Manage documents securely</span>
                            </div>
                            <div class="feature-item">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                                <span>Get instant status updates</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right side - Login Form -->
                <div class="form-box">
                    <h3>Sign In</h3>
                    <p class="form-subtitle">Enter your credentials to access your account</p>

                    <?php if (!empty($verificationMessage)): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <?php if ($messageType == 'success'): ?>
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                <?php else: ?>
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="8" x2="12" y2="12"></line>
                                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                <?php endif; ?>
                            </svg>
                            <?= htmlspecialchars($verificationMessage) ?>
                        </div>
                    <?php endif; ?>

                    <form id="login-form" action="process_user_login.php" method="post">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <div class="input-with-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                    <polyline points="22,6 12,13 2,6"></polyline>
                                </svg>
                                <input type="email" id="email" name="email" placeholder="your@email.com" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="input-with-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg>
                                <input type="password" id="password" name="password" placeholder="Enter your password" required>
                            </div>
                        </div>

                        <div class="form-footer">
                            <a href="forgot_password.php" class="forgot-link">Forgot password?</a>
                        </div>

                        <button type="submit" class="btn-primary">
                            Sign In
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </button>
                    </form>

                    <div id="login-error" class="error-message"></div>

                    <div class="form-divider">
                        <span>New to JRN?</span>
                    </div>

                    <p class="signup-prompt">
                        Don't have an account? <a href="signup.php">Create one now</a>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="footer-top">
            <div class="footer-logo-desc">
                <div class="footer-logo-name">
                    <img src="assets/img/logo.jpg" alt="JRN Logo" class="footer-logo">
                    <h3>JRN Business Solutions Co.</h3>
                </div>
                <p>Providing end-to-end business solutions including legal documents processing, tax compliance, payroll, and accounting services to help your business grow.</p>

                <div class="footer-socials">
                    <a href="#"><img src="assets/img/icons/facebook.svg" alt="Facebook" height="24"></a>
                    <a href="#"><img src="assets/img/icons/twitter.svg" alt="Twitter" height="24"></a>
                    <a href="#"><img src="assets/img/icons/instagram.svg" alt="Instagram" height="24"></a>
                </div>
            </div>

            <div class="footer-links">
                <div class="footer-column">
                    <h4>Quick Access</h4>
                    <ul>
                        <li><a href="index.php#about">About Us</a></li>
                        <li><a href="services.php">Services</a></li>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="account_page.php">Account</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h4>Resources</h4>
                    <ul>
                        <li><a href="#">Blog</a></li>
                        <li><a href="#">FAQ</a></li>
                        <li><a href="#">Support</a></li>
                        <li><a href="#">Contact Us</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <p>
                &copy; <?php echo date("Y"); ?> JRN Business Solutions Co. All Rights Reserved. |
                <a href="#">Privacy Policy</a> |
                <a href="#">Terms of Service</a>
            </p>
        </div>
    </footer>

    <script src="assets/js/login.js"></script>
    <script>
        function toggleMenu() {
            document.querySelector('.nav-links').classList.toggle('active');
            document.querySelector('.hamburger').classList.toggle('active');
        }
    </script>
</body>

</html>