<?php
session_start();
require_once '../connection/dbconn.php';
require_once '../includes/auth.php';
require_once '../includes/activity_logger.php';

// Only admins can manage employees
requireAdminOnly();

// Handle employee actions
$message = '';
$message_type = '';

$actorId   = $_SESSION['user_id'] ?? null;
$actorType = 'admin'; // this page is admin-only

// Archive employee
if (isset($_GET['archive']) && is_numeric($_GET['archive'])) {
    $id = (int)$_GET['archive'];

    // Fetch for logging
    $u = $conn->prepare("SELECT email, first_name, last_name, status FROM employees WHERE id = ?");
    $u->bind_param("i", $id);
    $u->execute();
    $empInfo = $u->get_result()->fetch_assoc();
    $u->close();

    $stmt = $conn->prepare("UPDATE employees SET status = 'archived' WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "Employee archived successfully";
        $message_type = "success";

        if ($empInfo && $actorId) {
            $fullName = trim(($empInfo['first_name'] ?? '') . ' ' . ($empInfo['last_name'] ?? ''));
            $email    = $empInfo['email'] ?? 'unknown';
            $prev     = $empInfo['status'] ?? 'unknown';

            logActivity(
                $actorId,
                $actorType,
                'employee_archived',
                "Employee {$fullName} ({$email}) archived (previous status: '{$prev}')"
            );
        }
    } else {
        $message = "Error archiving employee";
        $message_type = "error";
    }
}

// Restore archived employee
if (isset($_GET['restore']) && is_numeric($_GET['restore'])) {
    $id = (int)$_GET['restore'];

    $u = $conn->prepare("SELECT email, first_name, last_name, status FROM employees WHERE id = ?");
    $u->bind_param("i", $id);
    $u->execute();
    $empInfo = $u->get_result()->fetch_assoc();
    $u->close();

    $stmt = $conn->prepare("UPDATE employees SET status = 'active' WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "Employee restored successfully";
        $message_type = "success";

        if ($empInfo && $actorId) {
            $fullName = trim(($empInfo['first_name'] ?? '') . ' ' . ($empInfo['last_name'] ?? ''));
            $email    = $empInfo['email'] ?? 'unknown';
            $prev     = $empInfo['status'] ?? 'unknown';

            logActivity(
                $actorId,
                $actorType,
                'employee_restored',
                "Employee {$fullName} ({$email}) restored to active (previous status: '{$prev}')"
            );
        }
    } else {
        $message = "Error restoring employee";
        $message_type = "error";
    }
}

// Toggle employee status (active/inactive only)
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];

    $u = $conn->prepare("SELECT email, first_name, last_name, status FROM employees WHERE id = ? AND status != 'archived'");
    $u->bind_param("i", $id);
    $u->execute();
    $empInfo = $u->get_result()->fetch_assoc();
    $u->close();

    $stmt = $conn->prepare("UPDATE employees SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ? AND status != 'archived'");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "Employee status updated";
        $message_type = "success";

        if ($empInfo && $actorId) {
            $oldStatus = $empInfo['status'] ?? 'unknown';
            $newStatus = ($oldStatus === 'active') ? 'inactive' : 'active';
            $fullName  = trim(($empInfo['first_name'] ?? '') . ' ' . ($empInfo['last_name'] ?? ''));
            $email     = $empInfo['email'] ?? 'unknown';

            logActivity(
                $actorId,
                $actorType,
                'employee_status_toggled',
                "Employee {$fullName} ({$email}) status changed from '{$oldStatus}' to '{$newStatus}'"
            );
        }
    } else {
        $message = "Error updating employee status";
        $message_type = "error";
    }
}

// Filter: Show active, all, or archived
$filter = $_GET['filter'] ?? 'active';
$allowed_filters = ['active', 'all', 'archived'];
if (!in_array($filter, $allowed_filters)) {
    $filter = 'active';
}

// Role filter (only applies to 'all' view)
$role_filter = $_GET['role'] ?? '';
$allowed_roles = ['', 'admin', 'staff'];
if (!in_array($role_filter, $allowed_roles)) {
    $role_filter = '';
}

// Fetch employees based on filter
if ($filter === 'active') {
    $stmt = $conn->prepare("SELECT * FROM employees WHERE status = 'active' ORDER BY created_at DESC");
} elseif ($filter === 'archived') {
    $stmt = $conn->prepare("SELECT * FROM employees WHERE status = 'archived' ORDER BY created_at DESC");
} else {
    // All employees (not archived), with optional role filter
    if ($role_filter === 'admin') {
        $stmt = $conn->prepare("SELECT * FROM employees WHERE status != 'archived' AND role = 'admin' ORDER BY created_at DESC");
    } elseif ($role_filter === 'staff') {
        $stmt = $conn->prepare("SELECT * FROM employees WHERE status != 'archived' AND role = 'staff' ORDER BY created_at DESC");
    } else {
        $stmt = $conn->prepare("SELECT * FROM employees WHERE status != 'archived' ORDER BY created_at DESC");
    }
}
$stmt->execute();
$employees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Count by status
$stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM employees GROUP BY status");
$stmt->execute();
$status_counts = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $status_counts[$row['status']] = $row['count'];
}

// Count non-archived employees
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM employees WHERE status != 'archived'");
$stmt->execute();
$all_count = $stmt->get_result()->fetch_assoc()['count'];

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

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management - JRN Admin</title>
    <link rel="stylesheet" href="assets/css/index-admin.css">
    <link rel="stylesheet" href="assets/css/employees-admin.css">
    <link rel="stylesheet" href="assets/css/logout-modal.css">

    <style>
        :root {
            color-scheme: light only !important;
            --primary: #0F3A40;
            --primary-light: #1a5560;
            --primary-xlight: #e8f4f5;
            --accent: #2fb8c4;
            --accent-soft: #e0f7fa;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --bg-page: #f8fafc;
            --bg-card: #ffffff;
            --bg-secondary: #f1f5f9;
            --border-color: #e2e8f0;
            --border-strong: #cbd5e1;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.08), 0 2px 6px rgba(0, 0, 0, 0.04);
            --shadow-lg: 0 12px 40px rgba(0, 0, 0, 0.12), 0 4px 14px rgba(0, 0, 0, 0.06);
            --shadow-xl: 0 24px 64px rgba(0, 0, 0, 0.14), 0 8px 24px rgba(0, 0, 0, 0.08);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
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

        /* ── Processing type pills ── */
        .proc-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            padding: 0.25rem 0.7rem;
            border-radius: 999px;
            white-space: nowrap;
            font-family: 'DM Sans', sans-serif;
        }

        .proc-standard {
            background: #dbeafe;
            color: #1e40af;
        }

        .proc-priority {
            background: #ede9fe;
            color: #5b21b6;
        }

        .proc-express {
            background: #fef3c7;
            color: #92400e;
        }

        .proc-rush {
            background: #fee2e2;
            color: #991b1b;
        }

        .proc-same_day {
            background: #fce7f3;
            color: #9d174d;
        }

        /* ── Secondary filter bar ── */
        .proc-filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            align-items: center;
            padding-bottom: 0.85rem;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 0.85rem;
        }

        .proc-filter-bar .pf-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            margin-right: 0.25rem;
        }

        .proc-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            height: 32px;
            padding: 0 0.9rem;
            border: 1.5px solid var(--border-color);
            border-radius: 999px;
            background: #fff;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.18s ease;
            box-shadow: var(--shadow-sm);
        }

        .proc-btn:hover {
            border-color: var(--primary);
            background: var(--primary-xlight);
            color: var(--primary);
        }

        .proc-btn.active {
            background: var(--primary);
            color: #fff;
            border-color: transparent;
            box-shadow: 0 2px 8px rgba(15, 58, 64, 0.25);
        }

        .proc-btn .count {
            font-size: 0.68rem;
            opacity: 0.75;
        }

        /* ── Search bar ── */
        .search-bar {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-bar input {
            height: 38px;
            padding: 0 1rem;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            min-width: 260px;
            background: #fff;
            color: var(--text-primary);
            transition: border-color 0.18s;
            font-family: 'DM Sans', sans-serif;
        }

        .search-bar input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(47, 184, 196, 0.12);
        }

        .search-bar input::placeholder {
            color: var(--text-muted);
        }

        .search-bar button {
            height: 38px;
            padding: 0 1.1rem;
            font-size: 0.83rem;
            white-space: nowrap;
        }

        /* ── Alert banners ── */
        .alert {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.9rem 1.25rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.25rem;
            font-size: 0.88rem;
            font-weight: 500;
        }

        .alert::before {
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .alert--success {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .alert--success::before {
            content: '✓';
        }

        .alert--error {
            background: #fff1f2;
            color: #be123c;
            border: 1px solid #fecdd3;
        }

        .alert--error::before {
            content: '✕';
        }

        /* ── Status badges ── */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            padding: 0.3rem 0.75rem;
            border-radius: 999px;
        }

        .status-badge::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
            flex-shrink: 0;
        }

        .status-pending {
            background: #fef9ee;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .status-in_review {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        .status-completed {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .status-rejected {
            background: #fff1f2;
            color: #be123c;
            border: 1px solid #fecdd3;
        }

        /* ── Table enhancements ── */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .data-table thead th {
            background: var(--bg-secondary);
            color: var(--text-secondary);
            font-size: 0.71rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            padding: 0.75rem 1rem;
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }

        .data-table thead th:first-child {
            border-radius: var(--radius-sm) 0 0 0;
        }

        .data-table thead th:last-child {
            border-radius: 0 var(--radius-sm) 0 0;
        }

        .data-table tbody tr {
            transition: background 0.15s;
        }

        .data-table tbody tr:hover {
            background: #f8fafc;
        }

        .data-table tbody td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.84rem;
            color: var(--text-primary);
            vertical-align: middle;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        code.inq-num {
            font-family: 'DM Mono', monospace;
            font-size: 0.75rem;
            color: var(--primary);
            font-weight: 500;
            background: var(--primary-xlight);
            padding: 0.2rem 0.5rem;
            border-radius: 5px;
        }

        code.acc-num {
            font-family: 'DM Mono', monospace;
            font-size: 0.72rem;
            color: var(--text-secondary);
        }

        .client-cell strong {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .client-cell small {
            display: block;
            color: var(--text-secondary);
            font-size: 0.73rem;
            margin-top: 0.1rem;
        }

        .fee-cell strong {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--primary);
        }

        .fee-cell small {
            display: block;
            color: var(--text-muted);
            text-decoration: line-through;
            font-size: 0.73rem;
            margin-top: 0.1rem;
        }

        .doc-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--primary);
            background: var(--primary-xlight);
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
        }

        .btn-view {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            height: 32px;
            padding: 0 0.85rem;
            font-size: 0.78rem;
            font-weight: 600;
            border-radius: var(--radius-sm);
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s;
            border: 1.5px solid var(--border-color);
            background: #fff;
            color: var(--text-primary);
        }

        .btn-view:hover {
            background: var(--bg-secondary);
            border-color: var(--border-strong);
        }

        .btn-update {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            height: 32px;
            padding: 0 0.85rem;
            font-size: 0.78rem;
            font-weight: 600;
            border-radius: var(--radius-sm);
            cursor: pointer;
            border: none;
            background: var(--primary);
            color: #fff;
            transition: all 0.15s;
        }

        .btn-update:hover {
            background: var(--primary-light);
            box-shadow: 0 2px 8px rgba(15, 58, 64, 0.25);
        }

        .btn-closed {
            display: inline-flex;
            align-items: center;
            height: 32px;
            padding: 0 0.85rem;
            font-size: 0.78rem;
            font-weight: 600;
            border-radius: var(--radius-sm);
            background: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
            border: 1.5px solid #e2e8f0;
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

    <main class="main-content">
        <header class="admin-header">
            <div class="admin-header-left">
                <h1>Employee Management</h1>
                <p class="header-subtitle">Manage staff access and permissions</p>
            </div>
            <div class="admin-header-right">
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
            <div class="alert alert--<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <a href="?filter=active" class="filter-tab <?php echo $filter === 'active' ? 'active' : ''; ?>">
                Active
                <span class="count"><?php echo $status_counts['active'] ?? 0; ?></span>
            </a>
            <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                Employees
                <span class="count"><?php echo $all_count; ?></span>
            </a>
            <a href="?filter=archived" class="filter-tab <?php echo $filter === 'archived' ? 'active' : ''; ?>">
                Archived
                <span class="count"><?php echo $status_counts['archived'] ?? 0; ?></span>
            </a>
        </div>

        <!-- Role Filter (only shows for "All Employees" tab) -->
        <?php if ($filter === 'all'): ?>
            <div class="card" style="margin-bottom: 1rem;">
                <div class="card-body" style="padding: 1rem;">
                    <form method="GET" style="display: flex; align-items: center; gap: 1rem;">
                        <input type="hidden" name="filter" value="all">
                        <label for="role" style="font-weight: 500; font-size: 0.875rem; margin: 0;">Filter By:</label>
                        <select name="role" id="role" class="form-control" style="width: 200px;" onchange="this.form.submit()">
                            <option value="">All</option>
                            <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admins</option>
                            <option value="staff" <?php echo $role_filter === 'staff' ? 'selected' : ''; ?>>Staff</option>
                        </select>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h2>
                    <?php
                    if ($filter === 'all' && $role_filter === 'admin') {
                        echo 'Admins';
                    } elseif ($filter === 'all' && $role_filter === 'staff') {
                        echo 'Staffs';
                    } else {
                        echo ucfirst($filter) . ' Employees';
                    }
                    ?>
                    (<?php echo count($employees); ?>)
                </h2>
                <a href="employee-add.php" class="btn btn--primary">+ Add Employee</a>
            </div>
            <?php if (count($employees) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <div class="avatar-sm"><?php echo strtoupper(substr($emp['first_name'], 0, 1)); ?></div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></strong>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($emp['email']); ?></td>
                                <td>
                                    <span class="status <?php echo $emp['role'] === 'admin' ? 'status--info' : 'status--warning'; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $emp['role'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = match ($emp['status']) {
                                        'active' => 'status--success',
                                        'inactive' => 'status--warning',
                                        'archived' => 'status--error',
                                        default => 'status--info'
                                    };
                                    ?>
                                    <span class="status <?php echo $statusClass; ?>">
                                        <?php echo ucfirst($emp['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($emp['created_at'])); ?></td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                        <?php if ($emp['status'] !== 'archived'): ?>
                                            <a href="employee-edit.php?id=<?php echo $emp['id']; ?>" class="btn btn--sm btn--outline">Edit</a>
                                            <button class="btn btn--sm btn--outline" onclick="showToggleModal(<?php echo $emp['id']; ?>, '<?php echo $emp['status']; ?>', '<?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>')">
                                                <?php echo $emp['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                            <button class="btn btn--sm" style="color: var(--warning);" onclick="showArchiveModal(<?php echo $emp['id']; ?>, '<?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>')">Archive</button>
                                        <?php else: ?>
                                            <button class="btn btn--sm btn--primary" onclick="showRestoreModal(<?php echo $emp['id']; ?>, '<?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>')">Restore</button>
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
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <p>No <?php echo $filter === 'archived' ? 'archived' : ''; ?> employees found</p>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- Toggle Status Modal -->
    <div class="modal-overlay" id="toggleModal">
        <div class="modal">
            <div class="modal-header">
                <h2>Confirm Status Change</h2>
            </div>
            <div class="modal-body">
                <p id="toggleMessage"></p>
            </div>
            <div class="modal-actions">
                <button class="btn btn--outline" onclick="closeModal('toggleModal')">Cancel</button>
                <button class="btn btn--primary" id="toggleConfirm">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Archive Modal -->
    <div class="modal-overlay" id="archiveModal">
        <div class="modal">
            <div class="modal-header">
                <h2>Archive Employee</h2>
            </div>
            <div class="modal-body">
                <p id="archiveMessage"></p>
                <p style="color: var(--text-secondary); font-size: 0.875rem; margin-top: 0.5rem;">
                    Archived employees can be restored later from the Archived tab.
                </p>
            </div>
            <div class="modal-actions">
                <button class="btn btn--outline" onclick="closeModal('archiveModal')">Cancel</button>
                <button class="btn" style="background: var(--warning); color: white;" id="archiveConfirm">Archive</button>
            </div>
        </div>
    </div>

    <!-- Restore Modal -->
    <div class="modal-overlay" id="restoreModal">
        <div class="modal">
            <div class="modal-header">
                <h2>Restore Employee</h2>
            </div>
            <div class="modal-body">
                <p id="restoreMessage"></p>
            </div>
            <div class="modal-actions">
                <button class="btn btn--outline" onclick="closeModal('restoreModal')">Cancel</button>
                <button class="btn btn--primary" id="restoreConfirm">Restore</button>
            </div>
        </div>
    </div>

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

        // Modal functions
        function showToggleModal(id, currentStatus, name) {
            const action = currentStatus === 'active' ? 'deactivate' : 'activate';
            document.getElementById('toggleMessage').textContent =
                `Are you sure you want to ${action} ${name}?`;
            document.getElementById('toggleConfirm').onclick = () => {
                window.location.href = `?toggle=${id}&filter=<?php echo $filter; ?>`;
            };
            document.getElementById('toggleModal').style.display = 'flex';
        }

        function showArchiveModal(id, name) {
            document.getElementById('archiveMessage').textContent =
                `Archive ${name}? They will be moved to the archived list and can be restored later.`;
            document.getElementById('archiveConfirm').onclick = () => {
                window.location.href = `?archive=${id}&filter=<?php echo $filter; ?>`;
            };
            document.getElementById('archiveModal').style.display = 'flex';
        }

        function showRestoreModal(id, name) {
            document.getElementById('restoreMessage').textContent =
                `Restore ${name} to active status?`;
            document.getElementById('restoreConfirm').onclick = () => {
                window.location.href = `?restore=${id}&filter=<?php echo $filter; ?>`;
            };
            document.getElementById('restoreModal').style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal on outside click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.style.display = 'none';
                }
            });
        });

        // Close modal on ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay').forEach(overlay => {
                    overlay.style.display = 'none';
                });
            }
        });
    </script>
</body>

</html>