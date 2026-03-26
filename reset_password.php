<?php
session_start();
include 'connection/dbconn.php';

date_default_timezone_set('Asia/Manila');
mysqli_query($conn, "SET time_zone = '+08:00'");

$email = $_GET['email'] ?? '';
$token = $_GET['token'] ?? '';
if (empty($email) || empty($token)) die("Invalid or expired token.");

$stmt = $conn->prepare("SELECT id, fullname FROM users WHERE email=? AND reset_token=? AND reset_expires > NOW()");
$stmt->bind_param("ss", $email, $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) die("Invalid or expired token.");

$user = $result->fetch_assoc();
$userId = $user['id'];
$userName = htmlspecialchars($user['fullname']);

if (!isset($_SESSION['reset_password_log'])) $_SESSION['reset_password_log'] = [];
$_SESSION['reset_password_log'][] = ['user_id' => $userId, 'email' => $email, 'visited_at' => date('Y-m-d H:i:s')];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Reset Password | JRN Business Solutions Co.</title>
    <link rel="icon" type="image/x-icon" href="assets/img/Logo.jpg" />
    <link rel="stylesheet" href="assets/css/global.css" />
    <link rel="stylesheet" href="assets/css/index.css" />
    <link rel="stylesheet" href="assets/css/auth.css" />
    <link rel="stylesheet" href="assets/css/responsive.css" />
    <link rel="stylesheet" href="assets/css/signup-modal.css" />
</head>

<body>
    <header class="navbar">
        <div class="logo-container">
            <img src="assets/img/logo.jpg" alt="JRN Logo" class="logo-img" />
            <span class="logo-text">JRN Business Solutions Co.</span>
        </div>
        <div class="hamburger" onclick="toggleMenu()"><span></span><span></span><span></span></div>
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
                    <h3>Reset Your Password</h3>
                    <p class="form-subtitle">Hi <strong style="color: var(--primary);"><?php echo $userName; ?></strong>, create a strong new password below</p>

                    <form id="reset-form">
                        <div class="form-group">
                            <label for="password">New Password</label>
                            <div class="input-with-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg>
                                <input type="password" id="password" name="password" placeholder="Enter new password" required />
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <div class="input-with-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg>
                                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required />
                            </div>
                        </div>

                        <input type="hidden" name="user_id" value="<?php echo $userId; ?>" />

                        <button type="submit" class="btn-primary">
                            Update Password
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 12h14"></path>
                                <path d="m12 5 7 7-7 7"></path>
                            </svg>
                        </button>
                    </form>

                    <div class="form-divider"><span>Remember your password?</span></div>
                    <p class="signup-prompt"><a href="login.php">Back to Login</a></p>
                </div>
            </div>
        </div>
    </section>

    <!-- Modal -->
    <div id="reset-modal" class="signup-modal" style="display:none;">
        <div class="signup-modal-content">
            <div class="loader" id="reset-loader"></div>
            <p id="reset-message-modal"></p>
        </div>
    </div>

    <footer class="footer">
        <div class="footer-top">
            <div class="footer-logo-desc">
                <div class="footer-logo-name">
                    <img src="assets/img/logo.jpg" alt="JRN Logo" class="footer-logo" />
                    <h3>JRN Business Solutions Co.</h3>
                </div>
                <p>Providing end-to-end business solutions including legal documents processing, tax compliance, payroll, and accounting services to help your business grow.</p>
                <div class="footer-socials">
                    <a href="#" target="_blank" aria-label="Facebook"><img src="assets/img/icons/facebook.svg" alt="Facebook" height="24" /></a>
                    <a href="#" target="_blank" aria-label="Twitter"><img src="assets/img/icons/twitter.svg" alt="Twitter" height="24" /></a>
                    <a href="#" target="_blank" aria-label="Instagram"><img src="assets/img/icons/instagram.svg" alt="Instagram" height="24" /></a>
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
            <p>&copy; <?php echo date('Y'); ?> JRN Business Solutions Co. All Rights Reserved. |
                <a href='#'>Privacy Policy</a> |
                <a href='#'>Terms of Service</a>
            </p>
        </div>
    </footer>

    <script>
        function toggleMenu() {
            document.querySelector('.nav-links').classList.toggle('active');
            document.querySelector('.hamburger').classList.toggle('active');
        }

        // Password indicators (strength + match)
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('reset-form');
            if (!form) return;

            const password = form.querySelector('#password');
            const confirmPassword = form.querySelector('#confirm_password');

            // Container below confirm password
            const container = document.createElement('div');
            container.className = 'password-validation-wrapper';
            const confirmGroup = confirmPassword.closest('.form-group');
            confirmGroup.parentNode.insertBefore(container, confirmGroup.nextSibling);

            container.innerHTML = `
                <div class="password-strength-section">
                    <div class="strength-header">
                        <span class="strength-label">Password Strength:</span>
                        <span class="strength-text"></span>
                    </div>
                    <div class="strength-bar">
                        <div class="strength-bar-segment" data-level="1"></div>
                        <div class="strength-bar-segment" data-level="2"></div>
                        <div class="strength-bar-segment" data-level="3"></div>
                        <div class="strength-bar-segment" data-level="4"></div>
                    </div>
                </div>
                <div class="strength-requirements">
                    <div class="requirement-item" data-req="length">
                        <svg class="req-icon-circle" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">ircle cx="12" cy="12"2" r="10"/></svg>
                        <svg class="req-icon-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>At least 8 characters</span>
                    </div>
                    <div class="requirement-item" data-req="case">
                        <svg class="req-icon-circle" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">ircle cx="12" cy="12"2" r="10"/></svg>
                        <svg class="req-icon-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Upper & lowercase letters</span>
                    </div>
                    <div class="requirement-item" data-req="number">
                        <svg class="req-icon-circle" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">ircle cx="12" cy="12"2" r="10"/></svg>
                        <svg class="req-icon-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>At least one number</span>
                    </div>
                    <div class="requirement-item" data-req="special">
                        <svg class="req-icon-circle" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">ircle cx="12" cy="12"2" r="10"/></svg>
                        <svg class="req-icon-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Special character (!@#$%^&*)</span>
                    </div>
                </div>
                <div class="password-match-section">
                    <div class="match-indicator"></div>
                </div>
            `;

            const segments = container.querySelectorAll('.strength-bar-segment');
            const strengthText = container.querySelector('.strength-text');
            const reqList = container.querySelector('.strength-requirements');
            const matchIndicator = container.querySelector('.match-indicator');

            function requirements(value) {
                return {
                    length: value.length >= 8,
                    case: /[a-z]/.test(value) && /[A-Z]/.test(value),
                    number: /\d/.test(value),
                    special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(value)
                };
            }

            function score(reqs) {
                return Object.values(reqs).filter(Boolean).length;
            }

            function level(scoreVal) {
                const map = {
                    0: {
                        text: '',
                        color: ''
                    },
                    1: {
                        text: 'Weak',
                        color: '#ef4444'
                    },
                    2: {
                        text: 'Fair',
                        color: '#f97316'
                    },
                    3: {
                        text: 'Good',
                        color: '#3b82f6'
                    },
                    4: {
                        text: 'Strong',
                        color: '#10b981'
                    }
                };
                return map[scoreVal];
            }

            function updateStrength() {
                const val = password.value;
                if (!val) {
                    segments.forEach(s => s.classList.remove('active'));
                    strengthText.textContent = '';
                    reqList.style.display = 'none';
                    return;
                }
                const reqs = requirements(val);
                const s = score(reqs);
                const lv = level(s);

                segments.forEach((seg, i) => {
                    if (i < s) {
                        seg.classList.add('active');
                        seg.style.background = lv.color;
                    } else seg.classList.remove('active');
                });

                strengthText.textContent = lv.text;
                strengthText.style.color = lv.color;

                reqList.style.display = 'grid';
                Object.entries(reqs).forEach(([k, met]) => {
                    const item = reqList.querySelector(`[data-req="${k}"]`);
                    item.classList.toggle('met', met);
                });
            }

            function updateMatch() {
                const a = password.value;
                const b = confirmPassword.value;

                if (!b) {
                    matchIndicator.className = 'match-indicator';
                    matchIndicator.innerHTML = '';
                    return;
                }

                if (a === b && a.length) {
                    matchIndicator.className = 'match-indicator match-success';
                    matchIndicator.textContent = 'Passwords match';
                } else {
                    matchIndicator.className = 'match-indicator match-error';
                    matchIndicator.textContent = 'Passwords do not match';
                }
            }

            password.addEventListener('input', () => {
                updateStrength();
                updateMatch();
            });
            confirmPassword.addEventListener('input', updateMatch);

            // Submit
            const modal = document.getElementById('reset-modal');
            const loader = document.getElementById('reset-loader');
            const resetMessageModal = document.getElementById('reset-message-modal');

            form.addEventListener('submit', (e) => {
                const s = score(requirements(password.value));
                const match = password.value === confirmPassword.value;

                if (s < 2 || !match) {
                    e.preventDefault();
                    if (s < 2) {
                        strengthText.textContent = 'Password is too weak';
                        strengthText.style.color = '#ef4444';
                    }
                    if (!match) {
                        matchIndicator.className = 'match-indicator match-error';
                        matchIndicator.textContent = 'Passwords must match';
                    }
                    return;
                }

                e.preventDefault();
                const formData = new FormData(form);

                modal.style.display = 'flex';
                loader.style.display = 'block';
                resetMessageModal.innerText = '';

                fetch('process_reset_password.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        loader.style.display = 'none';
                        resetMessageModal.innerText = data.message;
                        resetMessageModal.style.color = data.success ? 'green' : 'red';

                        if (data.success) {
                            fetch('session_log_reset.php', {
                                method: 'POST',
                                body: formData
                            });
                            form.reset();
                            setTimeout(() => location.href = 'login.php', 3000);
                        } else {
                            setTimeout(() => modal.style.display = 'none', 3000);
                        }
                    })
                    .catch(err => {
                        loader.style.display = 'none';
                        resetMessageModal.innerText = 'An error occurred.';
                        resetMessageModal.style.color = 'red';
                        setTimeout(() => modal.style.display = 'none', 3000);
                        console.error(err);
                    });
            });
        });
    </script>
</body>

</html>