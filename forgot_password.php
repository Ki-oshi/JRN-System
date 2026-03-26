<?php
session_start();

// Initialize session log array if not already set
if (!isset($_SESSION['forgot_password_log'])) {
    $_SESSION['forgot_password_log'] = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | JRN Business Solutions Co.</title>
    <meta name="description" content="Reset your JRN Business Solutions account password securely.">
    <link rel="icon" type="image/x-icon" href="assets/img/Logo.jpg">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/signup-modal.css">
</head>

<body>
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
                <li><a href="login.php">Login</a></li>
            </ul>
        </nav>
    </header>

    <section class="form-section">
        <div class="form-container">
            <div class="form-card form-card-single">
                <div class="form-box">
                    <h3>Reset Password</h3>
                    <p class="form-subtitle">Enter your registered email address and we'll send you a password reset link</p>

                    <form id="forgot-form">
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

                        <button type="submit" class="btn-primary">
                            Send Reset Link
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="22" y1="2" x2="11" y2="13"></line>
                                <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                            </svg>
                        </button>
                    </form>

                    <div id="forgot-message" class="error-message"></div>

                    <div class="form-divider">
                        <span>Remember your password?</span>
                    </div>

                    <p class="signup-prompt">
                        <a href="login.php">Back to Login</a>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Modal -->
    <div id="reset-modal" class="signup-modal">
        <div class="signup-modal-content">
            <div class="loader" id="reset-loader"></div>
            <div class="modal-info">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--primary); margin-bottom: 10px;">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
                <p style="margin-bottom:10px; font-size:0.95rem; color:#555;">
                    You will receive a one-time link to reset your password.
                </p>
            </div>
            <p id="reset-message"></p>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-top">
            <div class="footer-logo-desc">
                <div class="footer-logo-name">
                    <img src="assets/img/logo.jpg" alt="JRN Logo" class="footer-logo">
                    <h3>JRN Business Solutions Co.</h3>
                </div>
                <p>Providing end-to-end business solutions including legal documents processing, tax compliance, payroll, and accounting services to help your business grow.</p>

                <div class="footer-socials">
                    <a href="#" target="_blank" aria-label="Facebook">
                        <img src="assets/img/icons/facebook.svg" alt="Facebook" height="24">
                    </a>
                    <a href="#" target="_blank" aria-label="Twitter">
                        <img src="assets/img/icons/twitter.svg" alt="Twitter" height="24">
                    </a>
                    <a href="#" target="_blank" aria-label="Instagram">
                        <img src="assets/img/icons/instagram.svg" alt="Instagram" height="24">
                    </a>
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

    <script>
        function toggleMenu() {
            document.querySelector('.nav-links').classList.toggle('active');
            document.querySelector('.hamburger').classList.toggle('active');
        }

        const forgotForm = document.getElementById('forgot-form');
        const modal = document.getElementById('reset-modal');
        const loader = document.getElementById('reset-loader');
        const resetMessage = document.getElementById('reset-message');
        const forgotMessage = document.getElementById('forgot-message');

        forgotForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(forgotForm);

            // Log the email in session via AJAX (optional)
            fetch('log_forgot_session.php', {
                method: 'POST',
                body: formData
            });

            modal.style.display = 'flex';
            loader.style.display = 'block';
            resetMessage.innerText = '';
            forgotMessage.classList.remove('show');

            fetch('process_forgot_password.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    loader.style.display = 'none';
                    resetMessage.innerText = data.message;
                    resetMessage.style.color = data.success ? 'green' : 'red';
                    setTimeout(() => {
                        modal.style.display = 'none';
                        if (!data.success) {
                            forgotMessage.innerText = data.message;
                            forgotMessage.style.color = 'red';
                            forgotMessage.classList.add('show');
                        }
                    }, 3000);
                    if (data.success) {
                        forgotForm.reset();
                    }
                })
                .catch(err => {
                    loader.style.display = 'none';
                    resetMessage.innerText = 'An error occurred. Please try again.';
                    resetMessage.style.color = 'red';
                    setTimeout(() => {
                        modal.style.display = 'none';
                    }, 3000);
                    console.error(err);
                });
        });
    </script>
</body>

</html>