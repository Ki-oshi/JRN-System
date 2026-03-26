<?php
session_start();
require_once '../connection/dbconn.php';
require_once '../includes/auth.php';

requireAdmin();

$message = '';
$message_type = '';

// Determine which table to use based on account_type session variable
$is_from_employees = isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'employee';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Get current admin info
    if ($is_from_employees) {
        $stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    }
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();

    if (!$admin) {
        $message = "Account not found";
        $message_type = "error";
    } elseif (!password_verify($current_password, $admin['password'])) {
        $message = "Current password is incorrect";
        $message_type = "error";
    } elseif (strlen($new_password) < 6) {
        $message = "New password must be at least 6 characters";
        $message_type = "error";
    } elseif ($new_password !== $confirm_password) {
        $message = "New passwords do not match";
        $message_type = "error";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        if ($is_from_employees) {
            $stmt = $conn->prepare("UPDATE employees SET password = ? WHERE id = ?");
        } else {
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        }
        $stmt->bind_param("si", $hashed_password, $_SESSION['user_id']);

        if ($stmt->execute()) {
            $message = "Password changed successfully";
            $message_type = "success";
        } else {
            $message = "Error changing password";
            $message_type = "error";
        }
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (empty($first_name) || empty($last_name) || empty($email)) {
        $message = "All fields are required";
        $message_type = "error";
    } else {
        if ($is_from_employees) {
            $stmt = $conn->prepare("UPDATE employees SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
        } else {
            $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
        }
        $stmt->bind_param("sssi", $first_name, $last_name, $email, $_SESSION['user_id']);

        if ($stmt->execute()) {
            $message = "Profile updated successfully";
            $message_type = "success";
        } else {
            $message = "Error updating profile";
            $message_type = "error";
        }
    }
}

// Get admin info
$is_from_employees = isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'employee';
if ($is_from_employees) {
    $stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
} else {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
}
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

// Pending inquiries badge
$stmt = $conn->prepare("SELECT COUNT(*) as pending_count FROM inquiries WHERE status = 'pending'");
$stmt->execute();
$pending_inquiries = $stmt->get_result()->fetch_assoc()['pending_count'];

// Pending bills badge
$stmt = $conn->prepare("SELECT COUNT(*) as pending_bills FROM billings WHERE status = 'unpaid'");
$stmt->execute();
$pending_bills = $stmt->get_result()->fetch_assoc()['pending_bills'];

// If admin not found, logout
if (!$admin) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - JRN Admin</title>
    <link rel="stylesheet" href="assets/css/index-admin.css">
    <link rel="stylesheet" href="assets/css/logout-modal.css">
    <style>
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            color: var(--text-primary);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            outline: 2px solid var(--primary);
            border-color: var(--primary);
        }

        .form-control:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            background: var(--gray-100);
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <img src="../assets/img/logo.jpg" alt="Logo" class="logo-small">
                <h2>JRN Admin</h2>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="index-admin.php" class="nav-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                Dashboard
            </a>

            <a href="inquiries-admin.php" class="nav-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" overflow="visible">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                Inquiries
                <?php if (isset($pending_inquiries) && $pending_inquiries > 0): ?>
                    <span class="badge"><?php echo $pending_inquiries; ?></span>
                <?php endif; ?>
            </a>

            <?php if (isAdmin()): ?>
                <a href="billing-admin.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="6" rx="1" />
                        <rect x="3" y="12" width="18" height="8" rx="1" />
                        <line x1="7" y1="16" x2="11" y2="16" />
                        <line x1="7" y1="19" x2="15" y2="19" />
                    </svg>
                    Billing
                    <?php if (isset($pending_bills) && $pending_bills > 0): ?>
                        <span class="badge"><?php echo $pending_bills; ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

            <a href="users-admin.php" class="nav-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" overflow="visible">
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                </svg>
                Users
            </a>

            <?php if (isAdmin()): ?>
                <a href="employees-admin.php" class="nav-item active">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    Employees
                </a>
                <a href="activity-logs.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    Activity Logs
                </a>
                <a href="services-admin.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="6" width="18" height="12" rx="2" />
                        <path d="M3 10h18" />
                    </svg>
                    Manage Services
                </a>
            <?php endif; ?>

            <div style="margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                <a href="admin-account.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M12 1v6m0 6v6m6-11h-6m-6 0H1m18.4-3.6l-4.2 4.2m-8.4 0l-4.2-4.2M18.4 18.4l-4.2-4.2m-8.4 0l-4.2 4.2"></path>
                    </svg>
                    My Account
                </a>
            </div>
        </nav>
        <div class="sidebar-footer">
            <a href="#" class="nav-item logout" id="logout-btn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                Logout
            </a>
        </div>
    </aside>

    <main class="main-content">
        <header class="admin-header">
            <div class="admin-header-left">
                <h1>My Account</h1>
                <p class="header-subtitle">Manage your profile and security settings</p>
            </div>
            <div class="admin-header-right">
                <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">
                    <svg class="moon-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                    </svg>
                    <svg class="sun-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="5"></circle>
                        <line x1="12" y1="1" x2="12" y2="3"></line>
                        <line x1="12" y1="21" x2="12" y2="23"></line>
                        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                        <line x1="1" y1="12" x2="3" y2="12"></line>
                        <line x1="21" y1="12" x2="23" y2="12"></line>
                        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                    </svg>
                </button>
                <div class="avatar-circle"><?php echo strtoupper(substr($admin['first_name'] ?? 'A', 0, 1)); ?></div>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert--<?php echo $message_type; ?>" style="margin-bottom: 1.5rem; padding: 1rem; border-radius: 8px; font-weight: 500; background: <?php echo $message_type === 'success' ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)'; ?>; color: <?php echo $message_type === 'success' ? 'var(--success)' : 'var(--danger)'; ?>;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Profile Information -->
        <form method="POST" class="card">
            <div class="card-header">
                <h2>Profile Information</h2>
            </div>
            <div style="padding: 1.5rem;">
                <div class="form-grid">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" class="form-control" required value="<?php echo htmlspecialchars($admin['first_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" class="form-control" required value="<?php echo htmlspecialchars($admin['last_name'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Role</label>
                    <input type="text" class="form-control" disabled value="<?php echo isset($admin['role']) ? ucfirst($admin['role']) : 'Administrator'; ?>">
                </div>

                <div style="margin-top: 1.5rem;">
                    <button type="submit" name="update_profile" class="btn btn--primary">Update Profile</button>
                </div>
            </div>
        </form>

        <!-- Change Password -->
        <form method="POST" class="card">
            <div class="card-header">
                <h2>Change Password</h2>
            </div>
            <div style="padding: 1.5rem;">
                <!-- Security Reminder -->
                <div style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(99, 102, 241, 0.1) 100%); border: 1px solid rgba(59, 130, 246, 0.3); padding: 1rem 1.25rem; border-radius: 10px; margin-bottom: 1.5rem;">
                    <div style="display: flex; gap: 0.75rem; align-items: center;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                            <path d="M9 12l2 2 4-4"></path>
                        </svg>
                        <p style="color: var(--text-primary); font-size: 0.875rem; margin: 0; font-weight: 500;">
                            <strong style="color: #3b82f6;">Important:</strong> Change your password after first login for security.
                        </p>
                    </div>
                </div>

                <div class="form-group">
                    <label>Current Password *</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>New Password * (min 6 characters)</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password *</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                </div>

                <div style="margin-top: 1.5rem;">
                    <button type="submit" name="change_password" class="btn btn--primary">Change Password</button>
                </div>
            </div>
        </form>


        <!-- Account Information -->
        <div class="card">
            <div class="card-header">
                <h2>Account Information</h2>
            </div>
            <div style="padding: 1.5rem;">
                <div style="display: grid; gap: 1rem;">
                    <?php if (isset($admin['account_number']) && !empty($admin['account_number'])): ?>
                        <div>
                            <strong style="font-size: 0.875rem; color: var(--text-secondary);">Account Number</strong>
                            <p style="margin-top: 0.25rem; color: var(--text-primary); font-family: monospace; font-weight: 600; font-size: 1.125rem; color: var(--primary);">
                                <?php echo htmlspecialchars($admin['account_number']); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                    <div>
                        <strong style="font-size: 0.875rem; color: var(--text-secondary);">Account Type</strong>
                        <p style="margin-top: 0.25rem; color: var(--text-primary);">
                            <?php echo $is_from_employees ? 'Employee Account' : 'Administrator Account'; ?>
                        </p>
                    </div>
                    <div>
                        <strong style="font-size: 0.875rem; color: var(--text-secondary);">Account Created</strong>
                        <p style="margin-top: 0.25rem; color: var(--text-primary);">
                            <?php echo isset($admin['created_at']) && $admin['created_at'] ? date('F d, Y', strtotime($admin['created_at'])) : 'N/A'; ?>
                        </p>
                    </div>
                    <div>
                        <strong style="font-size: 0.875rem; color: var(--text-secondary);">Last Updated</strong>
                        <p style="margin-top: 0.25rem; color: var(--text-primary);">
                            <?php echo isset($admin['updated_at']) && $admin['updated_at'] ? date('F d, Y', strtotime($admin['updated_at'])) : (isset($admin['created_at']) && $admin['created_at'] ? date('F d, Y', strtotime($admin['created_at'])) : 'N/A'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>

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

    <script src="assets/js/logout-modal.js"></script>

    <script>
        const themeToggle = document.getElementById('themeToggle');
        const htmlElement = document.documentElement;
        const currentTheme = localStorage.getItem('theme') || 'light';
        htmlElement.setAttribute('data-theme', currentTheme);
        themeToggle.addEventListener('click', () => {
            const theme = htmlElement.getAttribute('data-theme');
            const newTheme = theme === 'light' ? 'dark' : 'light';
            htmlElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        });
    </script>
</body>

</html>