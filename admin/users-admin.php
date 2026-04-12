<?php
session_start();
require_once '../connection/dbconn.php';
require_once '../includes/auth.php';
require_once '../includes/activity_logger.php';

// Allow both admins and employees (helper enforces access)
requireAdmin();

$message = '';
$message_type = '';

// Handle actions via POST (PRG pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentFilter = $_POST['filter'] ?? 'all';

    // Toggle active/inactive
    if (isset($_POST['toggle']) && is_numeric($_POST['toggle'])) {
        $id = (int)$_POST['toggle'];

        // Fetch user info for logging
        $u = $conn->prepare("SELECT email, first_name, last_name, status FROM users WHERE id = ? AND role = 'user'");
        $u->bind_param("i", $id);
        $u->execute();
        $userInfo = $u->get_result()->fetch_assoc();
        $u->close();

        $stmt = $conn->prepare("UPDATE users SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ? AND role = 'user'");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $_SESSION['flash_message'] = "User status updated successfully";
            $_SESSION['flash_type']    = "success";

            if ($userInfo) {
                $oldStatus = $userInfo['status'];
                $newStatus = ($oldStatus === 'active') ? 'inactive' : 'active';

                $actorType = (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'employee') ? 'employee' : 'admin';
                $email     = $userInfo['email'] ?? 'unknown';

                logActivity(
                    $_SESSION['user_id'],
                    $actorType,
                    'user_status_toggled',
                    "User {$email} status changed from '{$oldStatus}' to '{$newStatus}'"
                );
            }
        } else {
            $_SESSION['flash_message'] = "Error updating user status";
            $_SESSION['flash_type']    = "error";
        }
        header('Location: users-admin.php?filter=' . urlencode($currentFilter));
        exit;
    }

    // Suspend user
    if (isset($_POST['suspend']) && is_numeric($_POST['suspend'])) {
        $id = (int)$_POST['suspend'];

        $u = $conn->prepare("SELECT email, first_name, last_name, status FROM users WHERE id = ? AND role = 'user'");
        $u->bind_param("i", $id);
        $u->execute();
        $userInfo = $u->get_result()->fetch_assoc();
        $u->close();

        $stmt = $conn->prepare("UPDATE users SET status = 'suspended' WHERE id = ? AND role = 'user'");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $_SESSION['flash_message'] = "User suspended successfully";
            $_SESSION['flash_type']    = "success";

            if ($userInfo) {
                $actorType = (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'employee') ? 'employee' : 'admin';
                $email     = $userInfo['email'] ?? 'unknown';

                logActivity(
                    $_SESSION['user_id'],
                    $actorType,
                    'user_suspended',
                    "User {$email} suspended (previous status: '{$userInfo['status']}')"
                );
            }
        } else {
            $_SESSION['flash_message'] = "Error suspending user";
            $_SESSION['flash_type']    = "error";
        }
        header('Location: users-admin.php?filter=' . urlencode($currentFilter));
        exit;
    }

    // Activate user
    if (isset($_POST['activate']) && is_numeric($_POST['activate'])) {
        $id = (int)$_POST['activate'];

        $u = $conn->prepare("SELECT email, first_name, last_name, status FROM users WHERE id = ? AND role = 'user'");
        $u->bind_param("i", $id);
        $u->execute();
        $userInfo = $u->get_result()->fetch_assoc();
        $u->close();

        $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role = 'user'");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $_SESSION['flash_message'] = "User activated successfully";
            $_SESSION['flash_type']    = "success";

            if ($userInfo) {
                $actorType = (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'employee') ? 'employee' : 'admin';
                $email     = $userInfo['email'] ?? 'unknown';

                logActivity(
                    $_SESSION['user_id'],
                    $actorType,
                    'user_activated',
                    "User {$email} activated (previous status: '{$userInfo['status']}')"
                );
            }
        } else {
            $_SESSION['flash_message'] = "Error activating user";
            $_SESSION['flash_type']    = "error";
        }
        header('Location: users-admin.php?filter=' . urlencode($currentFilter));
        exit;
    }
}

// Read flash message (after redirect)
if (isset($_SESSION['flash_message'])) {
    $message      = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// Filter: Show all or by status
$filter          = $_GET['filter'] ?? 'all';
$allowed_filters = ['all', 'active', 'inactive', 'suspended'];
if (!in_array($filter, $allowed_filters, true)) {
    $filter = 'all';
}

// Fetch users based on filter (only regular users, not admins)
if ($filter === 'all') {
    $stmt = $conn->prepare("SELECT * FROM users WHERE role = 'user' ORDER BY created_at DESC");
} else {
    $stmt = $conn->prepare("SELECT * FROM users WHERE role = 'user' AND status = ? ORDER BY created_at DESC");
    $stmt->bind_param("s", $filter);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Count by status
$stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM users WHERE role = 'user' GROUP BY status");
$stmt->execute();
$status_counts = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $status_counts[$row['status']] = (int)$row['count'];
}
$total_users = array_sum($status_counts);

// Get admin info (either from employees or users)
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
$row = $stmt->get_result()->fetch_assoc();
$pending_inquiries = $row['pending_count'] ?? 0;

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
    <title>User Management - JRN Admin</title>
    <link rel="stylesheet" href="assets/css/index-admin.css">
    <link rel="stylesheet" href="assets/css/users-admin.css">
    <link rel="stylesheet" href="assets/css/logout-modal.css">


    <style>
        /* ══════════════════════════════════════════
           FORCE LIGHT MODE — no dark mode anywhere
        ══════════════════════════════════════════ */
        :root {
            color-scheme: light only !important;
            --primary: #0F3A40;
            --primary-mid: #1a5560;
            --primary-light: #e8f4f5;
            --accent: #2fb8c4;
            --accent-soft: #e0f7fa;
            --gold: #f59e0b;
            --gold-soft: #fef3c7;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --bg-page: #f8fafc;
            --bg-card: #ffffff;
            --bg-secondary: #f1f5f9;
            --border-color: #e2e8f0;
            --border-strong: #cbd5e1;
            --green: #16a34a;
            --green-soft: #dcfce7;
            --green-border: #bbf7d0;
            --red: #dc2626;
            --red-soft: #fee2e2;
            --red-border: #fecaca;
            --amber: #d97706;
            --amber-soft: #fef9c3;
            --amber-border: #fde68a;
            --slate: #64748b;
            --slate-soft: #f1f5f9;
            --slate-border: #e2e8f0;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 12px 40px rgba(0, 0, 0, 0.10);
            --shadow-xl: 0 24px 64px rgba(0, 0, 0, 0.13);
            --r-sm: 8px;
            --r-md: 12px;
            --r-lg: 16px;
            --r-xl: 20px;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            background: var(--bg-page) !important;
            color: var(--text-primary) !important;
            font-family: 'DM Sans', -apple-system, sans-serif !important;
        }

        /* ── Stats strip ── */
        .stats-strip {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 0.85rem;
            margin-bottom: 1.25rem;
        }

        @media (max-width: 1100px) {
            .stats-strip {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 600px) {
            .stats-strip {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .stat-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: var(--r-lg);
            padding: 1.10rem 0.41rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: box-shadow 0.2s, transform 0.2s;
        }

        .stat-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            border-radius: var(--r-lg) var(--r-lg) 0 0;
        }

        .stat-card.c-total::before {
            background: linear-gradient(90deg, var(--primary), var(--accent));
        }

        .stat-card.c-paid::before {
            background: var(--green);
        }

        .stat-card.c-unpaid::before {
            background: var(--red);
        }

        .stat-card.c-pending::before {
            background: var(--amber);
        }

        .stat-card.c-cancelled::before {
            background: var(--slate);
        }

        .stat-card.c-revenue::before {
            background: linear-gradient(90deg, var(--gold), #f97316);
        }

        .stat-label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.09em;
            color: var(--text-muted);
            margin: 0 0 0.5rem;
        }

        .stat-value {
            font-size: 1.65rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1;
        }

        .stat-value.v-paid {
            color: var(--green);
        }

        .stat-value.v-unpaid {
            color: var(--red);
        }

        .stat-value.v-pending {
            color: var(--amber);
        }

        .stat-value.v-revenue {
            color: var(--primary);
            font-size: 1.2rem;
        }

        .stat-value.v-outstanding {
            color: var(--red);
            font-size: 1.2rem;
        }

        .stat-sub {
            font-size: 0.62rem;
            color: var(--text-muted);
            margin-top: 0.35rem;
        }


        /* ── Filter bar ── */
        .filter-zone {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: var(--r-lg);
            padding: 1rem 1.25rem;
            margin-bottom: 1.1rem;
            box-shadow: var(--shadow-sm);
        }

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            align-items: flex-end;
        }

        .filter-field {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }

        .filter-field label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
        }

        .filter-field input,
        .filter-field select {
            height: 38px;
            padding: 0 0.85rem;
            border: 1.5px solid var(--border-color);
            border-radius: var(--r-sm);
            font-size: 0.84rem;
            background: #fff;
            color: var(--text-primary);
            font-family: 'DM Sans', sans-serif;
            transition: border-color 0.18s, box-shadow 0.18s;
        }

        .filter-field input:focus,
        .filter-field select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(47, 184, 196, 0.12);
        }

        .filter-field input[type="text"] {
            min-width: 220px;
        }

        /* ── Status tabs ── */
        .status-tabs {
            display: flex;
            gap: 0.45rem;
            flex-wrap: wrap;
            margin-bottom: 1.1rem;
        }

        .stab {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            height: 34px;
            padding: 0 1rem;
            border-radius: 999px;
            border: 1.5px solid var(--border-color);
            background: #fff;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            color: var(--text-secondary);
            transition: all 0.16s;
            box-shadow: var(--shadow-sm);
        }

        .stab:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-light);
        }

        .stab.active {
            background: var(--primary);
            color: #fff;
            border-color: transparent;
            box-shadow: 0 2px 10px rgba(15, 58, 64, 0.22);
        }

        .stab .stab-count {
            font-size: 0.68rem;
            background: rgba(255, 255, 255, 0.22);
            border-radius: 999px;
            padding: 0.05rem 0.45rem;
        }

        .stab:not(.active) .stab-count {
            background: var(--bg-secondary);
            color: var(--text-muted);
        }

        /* ── Alert ── */
        .alert {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.9rem 1.25rem;
            border-radius: var(--r-md);
            margin-bottom: 1.25rem;
            font-size: 0.88rem;
            font-weight: 500;
        }

        .alert--success {
            background: var(--green-soft);
            color: var(--green);
            border: 1px solid var(--green-border);
        }

        .alert--error {
            background: var(--red-soft);
            color: var(--red);
            border: 1px solid var(--red-border);
        }

        /* ── Table ── */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .data-table thead th {
            background: var(--bg-secondary);
            color: var(--text-secondary);
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 0.75rem 1rem;
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }

        .data-table tbody tr {
            transition: background 0.14s;
        }

        .data-table tbody tr:hover {
            background: #f8fafc;
        }

        .data-table tbody td {
            padding: 0.9rem 1rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.84rem;
            vertical-align: middle;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        code.inv-num {
            font-family: 'DM Mono', monospace;
            font-size: 0.76rem;
            font-weight: 500;
            color: var(--primary);
            background: var(--primary-light);
            padding: 0.2rem 0.55rem;
            border-radius: 6px;
        }

        /* ── Status badges ── */
        .bill-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 0.28rem 0.7rem;
            border-radius: 999px;
        }

        .bill-badge::before {
            content: '';
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: currentColor;
            flex-shrink: 0;
        }

        .bb-paid {
            background: var(--green-soft);
            color: var(--green);
            border: 1px solid var(--green-border);
        }

        .bb-unpaid {
            background: var(--red-soft);
            color: var(--red);
            border: 1px solid var(--red-border);
        }

        .bb-pending {
            background: var(--amber-soft);
            color: var(--amber);
            border: 1px solid var(--amber-border);
        }

        .bb-cancelled {
            background: var(--slate-soft);
            color: var(--slate);
            border: 1px solid var(--slate-border);
        }

        /* ── Inline action row ── */
        .action-row {
            display: flex;
            gap: 0.4rem;
            align-items: center;
        }

        .action-select {
            height: 33px;
            padding: 0 0.6rem;
            border: 1.5px solid var(--border-color);
            border-radius: var(--r-sm);
            font-size: 0.78rem;
            background: #fff;
            color: var(--text-primary);
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            transition: border-color 0.16s;
        }

        .action-select:focus {
            outline: none;
            border-color: var(--accent);
        }

        .btn-save {
            height: 33px;
            padding: 0 0.85rem;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: var(--r-sm);
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            transition: background 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .btn-save:hover {
            background: var(--primary-mid);
        }

        .btn-edit-link {
            height: 33px;
            padding: 0 0.85rem;
            border: 1.5px solid var(--border-color);
            border-radius: var(--r-sm);
            font-size: 0.78rem;
            font-weight: 600;
            background: #fff;
            color: var(--text-primary);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.15s;
        }

        .btn-edit-link:hover {
            border-color: var(--border-strong);
            background: var(--bg-secondary);
        }

        /* ── Empty state ── */
        .empty-state {
            padding: 4rem 2rem;
            text-align: center;
        }

        .empty-state svg {
            opacity: 0.18;
            margin-bottom: 1rem;
        }

        .empty-state p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin: 0 0 1rem;
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
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                Inquiries
                <?php if ($pending_inquiries > 0): ?>
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

            <a href="users-admin.php" class="nav-item active">
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
                <a href="payroll-reports-admin.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" />
                        <line x1="8" y1="21" x2="16" y2="21" />
                        <line x1="12" y1="17" x2="12" y2="21" />
                        <path d="M6 8h.01M10 8h4M6 12h12" />
                    </svg>
                    Payroll Reports
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
                <h1>User Management</h1>
                <p class="header-subtitle">Manage registered users and their access</p>
            </div>
            <div class="admin-header-right">
                <div class="avatar-circle">
                    <?php echo strtoupper(substr($admin['first_name'] ?? 'A', 0, 1)); ?>
                </div>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert--<?php echo $message_type; ?>" style="margin-bottom: 1.5rem;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                All Users
                <span class="count"><?php echo $total_users; ?></span>
            </a>
            <a href="?filter=active" class="filter-tab <?php echo $filter === 'active' ? 'active' : ''; ?>">
                Active
                <span class="count"><?php echo $status_counts['active'] ?? 0; ?></span>
            </a>
            <a href="?filter=inactive" class="filter-tab <?php echo $filter === 'inactive' ? 'active' : ''; ?>">
                Inactive
                <span class="count"><?php echo $status_counts['inactive'] ?? 0; ?></span>
            </a>
            <a href="?filter=suspended" class="filter-tab <?php echo $filter === 'suspended' ? 'active' : ''; ?>">
                Suspended
                <span class="count"><?php echo $status_counts['suspended'] ?? 0; ?></span>
            </a>
        </div>

        <div class="card">
            <div class="card-header">
                <h2><?php echo ucfirst($filter); ?> Users (<?php echo count($users); ?>)</h2>
            </div>

            <?php if (count($users) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Account #</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th>Status</th>
                            <th>Role</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <code style="font-size: 0.75rem; color: var(--primary); font-weight: 600;">
                                        <?php echo htmlspecialchars($user['account_number'] ?? 'N/A'); ?>
                                    </code>
                                </td>
                                <td>
                                    <div class="user-cell">
                                        <div class="avatar-sm">
                                            <?php
                                            $name = $user['fullname'] ?? $user['first_name'] . ' ' . $user['last_name'] ?? 'U';
                                            echo strtoupper(substr($name, 0, 1));
                                            ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($user['fullname'] ?? ($user['first_name'] . ' ' . $user['last_name'])); ?></strong>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($user['address'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php
                                    $statusClass = match ($user['status']) {
                                        'active'    => 'status--success',
                                        'inactive'  => 'status--warning',
                                        'suspended' => 'status--error',
                                        default     => 'status--info',
                                    };
                                    ?>
                                    <span class="status <?php echo $statusClass; ?>"><?php echo ucfirst($user['status']); ?></span>
                                </td>
                                <td><?php echo ucfirst($user['role']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                        <!-- View button opens modal -->
                                        <button
                                            type="button"
                                            class="btn btn--sm btn--outline user-view-btn"
                                            data-user='<?php echo json_encode([
                                                            "id" => $user["id"],
                                                            "account_number" => $user["account_number"],
                                                            "fullname" => $user["fullname"],
                                                            "email" => $user["email"],
                                                            "phone" => $user["phone"],
                                                            "address" => $user["address"],
                                                            "city" => $user["city"],
                                                            "state" => $user["state"],
                                                            "postal_code" => $user["postal_code"],
                                                            "status" => $user["status"],
                                                            "role" => $user["role"],
                                                            "created_at" => $user["created_at"],
                                                            "username" => $user["username"]
                                                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>'>
                                            View
                                        </button>

                                        <!-- Action buttons (Activate/Deactivate/Suspend) -->
                                        <?php if ($user['status'] === 'suspended'): ?>
                                            <button type="button" class="btn btn--sm btn--primary admin-action-btn" data-action-name="activate" data-action-value="<?php echo $user['id']; ?>" data-label="Activate User" data-message="Are you sure you want to activate this user?">Activate</button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn--sm btn--outline admin-action-btn" data-action-name="toggle" data-action-value="<?php echo $user['id']; ?>" data-label="Activate User" data-message="Are you sure you want to <?php echo $user['status'] === 'active' ? 'deactivate' : 'activate'; ?> this user?"><?php echo $user['status'] === 'active' ? 'Deactivate' : 'Activate'; ?></button>

                                            <button type="button" class="btn btn--sm admin-action-btn" style="color: var(--danger);" data-action-name="suspend" data-action-value="<?php echo $user['id']; ?>" data-label="Suspend User" data-message="Suspend this user? They will not be able to log in.">Suspend</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <svg width="64" height="64" fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    </svg>
                    <p>No <?php echo $filter === 'all' ? '' : $filter; ?> users found</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Admin Action Confirm Modal -->
    <div class="modal-overlay" id="adminActionOverlay" style="display:none;">
        <div class="modal-card admin-confirm-modal">
            <button type="button" class="modal-close" id="adminActionClose">&times;</button>
            <h2 id="adminActionTitle">Confirm Action</h2>
            <p id="adminActionMessage" style="margin: 0.5rem 0 1.25rem; font-size: 0.95rem;"></p>
            <div class="admin-confirm-actions">
                <button type="button" class="btn btn--sm btn--outline" id="adminActionCancel">Cancel</button>
                <button type="button" class="btn btn--sm btn--primary" id="adminActionConfirm">Confirm</button>
            </div>
        </div>
    </div>

    <!-- User View Modal -->
    <div class="modal-overlay" id="userViewOverlay" style="display:none;">
        <div class="modal-card" id="userViewModal">
            <button type="button" class="modal-close" id="userViewClose">&times;</button>
            <h2>User Details</h2>
            <div class="modal-body">
                <div class="modal-grid">
                    <div class="field-row">
                        <span class="label">Account #</span>
                        <span class="value" id="uvAccountNumber"></span>
                    </div>
                    <div class="field-row">
                        <span class="label">Name</span>
                        <span class="value" id="uvName"></span>
                    </div>
                    <div class="field-row">
                        <span class="label">Username</span>
                        <span class="value" id="uvUsername"></span>
                    </div>
                    <div class="field-row">
                        <span class="label">Email</span>
                        <span class="value" id="uvEmail"></span>
                    </div>
                    <div class="field-row">
                        <span class="label">Phone</span>
                        <span class="value" id="uvPhone"></span>
                    </div>
                    <div class="field-row">
                        <span class="label">Address</span>
                        <span class="value" id="uvAddress"></span>
                    </div>
                    <div class="field-row">
                        <span class="label">Status</span>
                        <span class="value" id="uvStatus"></span>
                    </div>
                    <div class="field-row">
                        <span class="label">Registered</span>
                        <span class="value" id="uvRegistered"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden form for admin actions (POST) -->
    <form id="adminActionForm" method="POST" style="display:none;">
        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
        <input type="hidden" name="toggle" id="adminToggleInput">
        <input type="hidden" name="suspend" id="adminSuspendInput">
        <input type="hidden" name="activate" id="adminActivateInput">
    </form>

    <!-- Theme toggle -->
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

    <!-- User view modal JS -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const overlay = document.getElementById('userViewOverlay');
            const closeBtn = document.getElementById('userViewClose');

            function openUserModal(user) {
                const fullName =
                    (user.first_name && user.last_name) ?
                    user.first_name + ' ' + user.last_name :
                    (user.fullname || user.username || 'Unknown');

                document.getElementById('uvAccountNumber').textContent = user.account_number || 'N/A';
                document.getElementById('uvName').textContent = fullName;
                document.getElementById('uvUsername').textContent = user.username ? '@' + user.username : '—';
                document.getElementById('uvEmail').textContent = user.email || '—';
                document.getElementById('uvPhone').textContent = user.phone || 'N/A';

                const addressParts = [user.address, user.city, user.state, user.postal_code]
                    .filter(Boolean)
                    .join(', ');
                document.getElementById('uvAddress').textContent = addressParts || '—';

                document.getElementById('uvStatus').textContent =
                    user.status ? user.status.charAt(0).toUpperCase() + user.status.slice(1) : '—';

                document.getElementById('uvRegistered').textContent =
                    user.created_at ? new Date(user.created_at).toLocaleString() : '—';

                overlay.style.display = 'flex';
            }

            function closeUserModal() {
                overlay.style.display = 'none';
            }

            document.querySelectorAll('.user-view-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const data = btn.getAttribute('data-user');
                    if (!data) return;
                    const user = JSON.parse(data);
                    openUserModal(user);
                });
            });

            closeBtn.addEventListener('click', closeUserModal);
            overlay.addEventListener('click', e => {
                if (e.target === overlay) closeUserModal();
            });
            document.addEventListener('keydown', e => {
                if (e.key === 'Escape' && overlay.style.display === 'flex') {
                    closeUserModal();
                }
            });
        });
    </script>

    <!-- Admin action confirm modal JS -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const actionOverlay = document.getElementById('adminActionOverlay');
            const actionCloseBtn = document.getElementById('adminActionClose');
            const actionCancel = document.getElementById('adminActionCancel');
            const actionConfirm = document.getElementById('adminActionConfirm');
            const actionTitleEl = document.getElementById('adminActionTitle');
            const actionMsgEl = document.getElementById('adminActionMessage');

            const actionForm = document.getElementById('adminActionForm');
            const toggleInput = document.getElementById('adminToggleInput');
            const suspendInput = document.getElementById('adminSuspendInput');
            const activateInput = document.getElementById('adminActivateInput');

            let pendingField = null;
            let pendingValue = null;

            function openAdminActionModal(label, message, field, value) {
                actionTitleEl.textContent = label || 'Confirm Action';
                actionMsgEl.textContent = message || 'Are you sure you want to continue?';
                pendingField = field;
                pendingValue = value;
                actionOverlay.style.display = 'flex';
            }

            function closeAdminActionModal() {
                actionOverlay.style.display = 'none';
                pendingField = null;
                pendingValue = null;
            }

            actionConfirm.addEventListener('click', function() {
                if (!pendingField || !pendingValue) {
                    closeAdminActionModal();
                    return;
                }

                // Clear all
                toggleInput.value = '';
                suspendInput.value = '';
                activateInput.value = '';

                if (pendingField === 'toggle') toggleInput.value = pendingValue;
                if (pendingField === 'suspend') suspendInput.value = pendingValue;
                if (pendingField === 'activate') activateInput.value = pendingValue;

                actionForm.submit();
            });

            actionCloseBtn.addEventListener('click', closeAdminActionModal);
            actionCancel.addEventListener('click', closeAdminActionModal);

            actionOverlay.addEventListener('click', function(e) {
                if (e.target === actionOverlay) {
                    closeAdminActionModal();
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && actionOverlay.style.display === 'flex') {
                    closeAdminActionModal();
                }
            });

            // Attach to Activate/Deactivate/Suspend buttons
            document.querySelectorAll('.admin-action-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const label = this.dataset.label;
                    const message = this.dataset.message;
                    const field = this.dataset.actionName;
                    const value = this.dataset.actionValue;
                    openAdminActionModal(label, message, field, value);
                });
            });
        });
    </script>
</body>

</html>