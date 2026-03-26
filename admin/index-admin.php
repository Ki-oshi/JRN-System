<?php
session_start();
require_once '../connection/dbconn.php';
require_once '../includes/auth.php';

requireAdmin();

// Initialize stats
$stats = [
    'total_users' => 0,
    'total_inquiries' => 0,
    'pending_inquiries' => 0,
    'in_progress_inquiries' => 0
];

// Fetch stats
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
$stmt->execute();
$stats['total_users'] = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM inquiries");
$stmt->execute();
$stats['total_inquiries'] = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM inquiries WHERE status = 'pending'");
$stmt->execute();
$stats['pending_inquiries'] = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM inquiries WHERE status = 'in_review'");
$stmt->execute();
$stats['in_progress_inquiries'] = $stmt->get_result()->fetch_assoc()['total'];

// Pending bills badge
$stmt = $conn->prepare("SELECT COUNT(*) as pending_bills FROM billings WHERE status = 'unpaid'");
$stmt->execute();
$pending_bills = $stmt->get_result()->fetch_assoc()['pending_bills'];

// Recent inquiries
$stmt = $conn->prepare("
    SELECT i.*, u.first_name, u.last_name, u.email 
    FROM inquiries i
    JOIN users u ON i.user_id = u.id
    ORDER BY i.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recent_inquiries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Service breakdown
$stmt = $conn->prepare("
    SELECT service_name, COUNT(*) as count 
    FROM inquiries 
    GROUP BY service_name 
    ORDER BY count DESC
");
$stmt->execute();
$service_breakdown = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get admin/employee info based on account type
$admin = null;
if (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'employee') {
    // Fetch from employees table
    $stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
} else {
    // Fetch from users table (regular admin)
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
}

// Fallback if no admin data found
if (!$admin) {
    $admin = [
        'first_name' => 'Admin',
        'last_name' => ''
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - JRN Business Solutions</title>
    <link rel="stylesheet" href="assets/css/index-admin.css">
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
            <a href="index-admin.php" class="nav-item active">
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
                <?php if ($stats['pending_inquiries'] > 0): ?>
                    <span class="badge"><?php echo $stats['pending_inquiries']; ?></span>
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
                <h1>Dashboard</h1>
                <p class="header-subtitle">Welcome back, <?php echo htmlspecialchars($admin['first_name'] ?? 'Admin'); ?></p>
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

        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                    </svg>
                </div>
                <div class="stat-content">
                    <p class="stat-label">Total Users</p>
                    <h3><?php echo $stats['total_users']; ?></h3>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                </div>
                <div class="stat-content">
                    <p class="stat-label">Total Inquiries</p>
                    <h3><?php echo $stats['total_inquiries']; ?></h3>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon yellow">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                </div>
                <div class="stat-content">
                    <p class="stat-label">Pending</p>
                    <h3><?php echo $stats['pending_inquiries']; ?></h3>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                    </svg>
                </div>
                <div class="stat-content">
                    <p class="stat-label">In Review</p>
                    <h3><?php echo $stats['in_progress_inquiries']; ?></h3>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-header">
                <h2>Recent Inquiries</h2>
                <a href="inquiries-admin.php" class="btn btn--sm btn--outline">View All</a>
            </div>
            <?php if (count($recent_inquiries) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_inquiries as $inq): ?>
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <div class="avatar-sm"><?php echo strtoupper(substr($inq['first_name'] ?? 'U', 0, 1)); ?></div>
                                        <div>
                                            <strong><?php echo htmlspecialchars(($inq['first_name'] ?? '') . ' ' . ($inq['last_name'] ?? '')); ?></strong>
                                            <small><?php echo htmlspecialchars($inq['email']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($inq['service_name']); ?></td>
                                <td>
                                    <?php
                                    $class = match ($inq['status']) {
                                        'pending' => 'status--warning',
                                        'in_review' => 'status--info',
                                        'completed' => 'status--success',
                                        'rejected' => 'status--error',
                                        default => 'status--info'
                                    };
                                    ?>
                                    <span class="status <?php echo $class; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $inq['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($inq['created_at'])); ?></td>
                                <td><a href="inquiries-admin.php" class="btn btn--sm btn--outline">View</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <svg width="64" height="64" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                    <p>No inquiries yet</p>
                </div>
            <?php endif; ?>
        </section>

        <section class="card">
            <div class="card-header">
                <h2>Service Breakdown</h2>
            </div>
            <div class="service-list">
                <?php if (count($service_breakdown) > 0 && $stats['total_inquiries'] > 0): ?>
                    <?php foreach ($service_breakdown as $s): ?>
                        <div class="service-item">
                            <div class="service-info">
                                <span class="service-name"><?php echo htmlspecialchars($s['service_name']); ?></span>
                                <span class="service-count"><?php echo $s['count']; ?> inquiries</span>
                            </div>
                            <div class="service-bar">
                                <div class="service-bar-fill" style="width: <?php echo ($s['count'] / $stats['total_inquiries']) * 100; ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No services data yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
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