<?php
session_start();
require_once '../connection/dbconn.php';
require_once '../includes/auth.php';
require_once '../includes/activity_logger.php';

requireAdmin();

// Extra check: Only allow actual admins, not regular employees
if (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'employee') {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header("Location: index-admin.php");
        exit;
    }
}

// Get filters
$filter_action = $_GET['action'] ?? '';
$filter_user_type = $_GET['user_type'] ?? '';
$filter_date = $_GET['date'] ?? '';

// Build filters array
$filters = ['limit' => 200];
if ($filter_action) $filters['action'] = $filter_action;
if ($filter_user_type) $filters['user_type'] = $filter_user_type;
if ($filter_date) {
    $filters['date_from'] = $filter_date . ' 00:00:00';
    $filters['date_to'] = $filter_date . ' 23:59:59';
}

// Get logs
$logger = new ActivityLogger($conn);
$logs = $logger->getLogs($filters);

// Get unique actions for filter dropdown
$stmt = $conn->prepare("SELECT DISTINCT action FROM activity_logs ORDER BY action");
$stmt->execute();
$available_actions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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

// Get pending inquiries count
$stmt = $conn->prepare("SELECT COUNT(*) as pending_count FROM inquiries WHERE status = 'pending'");
$stmt->execute();
$pending_inquiries = $stmt->get_result()->fetch_assoc()['pending_count'];

// Pending bills badge
$stmt = $conn->prepare("SELECT COUNT(*) as pending_bills FROM billings WHERE status = 'unpaid'");
$stmt->execute();
$pending_bills = $stmt->get_result()->fetch_assoc()['pending_bills'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - JRN Admin</title>
    <link rel="stylesheet" href="assets/css/index-admin.css">
    <link rel="stylesheet" href="assets/css/activity-logs.css">
    <link rel="stylesheet" href="assets/css/logout-modal.css">
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
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                </svg>
                Users
            </a>

            <?php if (isAdmin()): ?>
                <a href="employees-admin.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    Employees
                </a>
                <a href="activity-logs.php" class="nav-item active">
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
                <h1>Activity Logs</h1>
                <p class="header-subtitle">Monitor system activities and user actions</p>
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

        <!-- Filters -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-body">
                <form method="GET" class="filters-form">
                    <div class="filter-group">
                        <label for="action">Action Type</label>
                        <select name="action" id="action" class="form-control">
                            <option value="">All Actions</option>
                            <?php foreach ($available_actions as $act): ?>
                                <option value="<?php echo htmlspecialchars($act['action']); ?>" <?php echo $filter_action === $act['action'] ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $act['action'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="user_type">User Type</label>
                        <select name="user_type" id="user_type" class="form-control">
                            <option value="">All Types</option>
                            <option value="admin" <?php echo $filter_user_type === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="employee" <?php echo $filter_user_type === 'employee' ? 'selected' : ''; ?>>Employee</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="date">Date</label>
                        <input type="date" name="date" id="date" class="form-control" value="<?php echo htmlspecialchars($filter_date); ?>">
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn--primary">Apply Filters</button>
                        <a href="activity-logs.php" class="btn btn--outline">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="card">
            <div class="card-header">
                <h2>Recent Activity (<?php echo count($logs); ?>)</h2>
            </div>
            <?php if (count($logs) > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td style="white-space: nowrap;">
                                        <?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?>
                                    </td>
                                    <td>
                                        <span class="status <?php
                                                            echo $log['user_type'] === 'admin' ? 'status--info' : ($log['user_type'] === 'employee' ? 'status--warning' : 'status--success');
                                                            ?>">
                                            <?php echo ucfirst($log['user_type']); ?>
                                        </span>
                                        <small style="display: block; color: var(--text-secondary); margin-top: 0.25rem;">
                                            <?php
                                            if ($log['first_name'] && $log['last_name']) {
                                                echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']);
                                            } elseif ($log['user_email']) {
                                                echo htmlspecialchars($log['user_email']);
                                            } else {
                                                echo 'ID: ' . ($log['user_id'] ?? 'N/A');
                                            }
                                            ?>
                                        </small>
                                    </td>
                                    <td>
                                        <strong><?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['description']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <svg width="64" height="64" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                    </svg>
                    <p>No activity logs found</p>
                </div>
            <?php endif; ?>
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