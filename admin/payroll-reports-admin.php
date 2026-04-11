<?php
session_start();
require_once '../connection/dbconn.php';
require_once '../includes/auth.php';
require_once '../includes/activity_logger.php';

requireAdmin();

$message      = '';
$message_type = '';

// ── Handle Add Payroll ─────────────────────────────────────────────────────
if (isset($_POST['add_payroll'])) {
    $employee_id          = (int)$_POST['employee_id'];
    $period_month         = (int)$_POST['period_month'];
    $period_year          = (int)$_POST['period_year'];
    $basic_salary         = (float)$_POST['basic_salary'];
    $allowances           = (float)($_POST['allowances'] ?? 0);
    $overtime_pay         = (float)($_POST['overtime_pay'] ?? 0);
    $sss_deduction        = (float)($_POST['sss_deduction'] ?? 0);
    $philhealth_deduction = (float)($_POST['philhealth_deduction'] ?? 0);
    $pagibig_deduction    = (float)($_POST['pagibig_deduction'] ?? 0);
    $tax_deduction        = (float)($_POST['tax_deduction'] ?? 0);
    $other_deductions     = (float)($_POST['other_deductions'] ?? 0);
    $notes                = trim($_POST['notes'] ?? '');
    $status               = $_POST['status'] ?? 'pending';

    $gross_pay = $basic_salary + $allowances + $overtime_pay;
    $net_pay   = $gross_pay - $sss_deduction - $philhealth_deduction
        - $pagibig_deduction - $tax_deduction - $other_deductions;
    $paid_at   = ($status === 'paid') ? date('Y-m-d H:i:s') : null;

    $stmt = $conn->prepare("
        INSERT INTO payroll
            (employee_id, period_month, period_year, basic_salary, allowances, overtime_pay,
             sss_deduction, philhealth_deduction, pagibig_deduction, tax_deduction, other_deductions,
             gross_pay, net_pay, status, notes, paid_at, created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->bind_param(
        "iiiddddddddddsssi",
        $employee_id,
        $period_month,
        $period_year,
        $basic_salary,
        $allowances,
        $overtime_pay,
        $sss_deduction,
        $philhealth_deduction,
        $pagibig_deduction,
        $tax_deduction,
        $other_deductions,
        $gross_pay,
        $net_pay,
        $status,
        $notes,
        $paid_at,
        $_SESSION['user_id']
    );
    if ($stmt->execute()) {
        $message = "Payroll record added successfully.";
        $message_type = "success";
        logActivity($_SESSION['user_id'], 'admin', 'payroll_added', "Added payroll for employee ID {$employee_id} ({$period_month}/{$period_year})");
    } else {
        $message = "Error adding payroll record.";
        $message_type = "error";
    }
}

// ── Handle Status Toggle ───────────────────────────────────────────────────
if (isset($_POST['toggle_status'])) {
    $payroll_id = (int)$_POST['payroll_id'];
    $new_status = $_POST['new_status'];
    $paid_at    = ($new_status === 'paid') ? date('Y-m-d H:i:s') : null;
    $stmt = $conn->prepare("UPDATE payroll SET status=?, paid_at=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param("ssi", $new_status, $paid_at, $payroll_id);
    if ($stmt->execute()) {
        $message = "Payroll status updated.";
        $message_type = "success";
    }
}

// ── Filters ────────────────────────────────────────────────────────────────
$filter_month    = (int)($_GET['month'] ?? 0);
$filter_year     = (int)($_GET['year']  ?? date('Y'));
$filter_employee = (int)($_GET['employee_id'] ?? 0);
$filter_status   = $_GET['status'] ?? 'all';

// ── Pending bills badge ────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT COUNT(*) as c FROM billings WHERE status='unpaid'");
$stmt->execute();
$pending_bills = $stmt->get_result()->fetch_assoc()['c'];

// ── Employees dropdown ─────────────────────────────────────────────────────
$emp_result = $conn->query("SELECT id, first_name, last_name FROM employees ORDER BY first_name");
$employees  = $emp_result->fetch_all(MYSQLI_ASSOC);

// ── Build dynamic payroll query ────────────────────────────────────────────
$where = ["1=1"];
$params = [];
$types = "";
if ($filter_month > 0) {
    $where[] = "p.period_month = ?";
    $params[] = $filter_month;
    $types .= "i";
}
if ($filter_year > 0) {
    $where[] = "p.period_year = ?";
    $params[] = $filter_year;
    $types .= "i";
}
if ($filter_employee > 0) {
    $where[] = "p.employee_id = ?";
    $params[] = $filter_employee;
    $types .= "i";
}
if ($filter_status !== 'all') {
    $where[] = "p.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}
$where_sql = implode(" AND ", $where);

$sql = "SELECT p.*, e.first_name, e.last_name, e.email
        FROM payroll p LEFT JOIN employees e ON p.employee_id = e.id
        WHERE {$where_sql} ORDER BY p.period_year DESC, p.period_month DESC, p.id DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $payroll_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $payroll_records = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

// ── Summary stats ──────────────────────────────────────────────────────────
$stats_sql = "SELECT COUNT(*) as total_records, SUM(gross_pay) as total_gross, SUM(net_pay) as total_net,
    SUM(CASE WHEN status='paid' THEN net_pay ELSE 0 END) as total_paid,
    SUM(CASE WHEN status='pending' THEN net_pay ELSE 0 END) as total_pending,
    COUNT(CASE WHEN status='paid' THEN 1 END) as count_paid,
    COUNT(CASE WHEN status='pending' THEN 1 END) as count_pending,
    SUM(sss_deduction) as total_sss, SUM(philhealth_deduction) as total_ph,
    SUM(pagibig_deduction) as total_pi, SUM(tax_deduction) as total_tax,
    SUM(other_deductions) as total_other, SUM(basic_salary) as total_basic,
    SUM(allowances) as total_allowances, SUM(overtime_pay) as total_ot
    FROM payroll p WHERE {$where_sql}";

if (!empty($params)) {
    $s2 = $conn->prepare($stats_sql);
    $s2->bind_param($types, ...$params);
    $s2->execute();
    $stats = $s2->get_result()->fetch_assoc();
} else {
    $stats = $conn->query($stats_sql)->fetch_assoc();
}

// ── Monthly trend (all records for selected year, unfiltered by month) ─────
$monthly_sql = "SELECT p.period_month, SUM(net_pay) as net, SUM(gross_pay) as gross
    FROM payroll p WHERE p.period_year = ?
    " . ($filter_employee > 0 ? "AND p.employee_id = ?" : "") . "
    GROUP BY p.period_month ORDER BY p.period_month";

$monthly_stmt = $conn->prepare($monthly_sql);
if ($filter_employee > 0) {
    $monthly_stmt->bind_param("ii", $filter_year, $filter_employee);
} else {
    $monthly_stmt->bind_param("i", $filter_year);
}
$monthly_stmt->execute();
$monthly_raw = $monthly_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Per-employee summary ───────────────────────────────────────────────────
$emp_sql = "SELECT e.first_name, e.last_name, SUM(p.net_pay) as total_net, SUM(p.gross_pay) as total_gross,
    COUNT(*) as records, SUM(CASE WHEN p.status='paid' THEN 1 ELSE 0 END) as paid_count
    FROM payroll p LEFT JOIN employees e ON p.employee_id = e.id
    WHERE {$where_sql} GROUP BY p.employee_id ORDER BY total_net DESC LIMIT 10";

if (!empty($params)) {
    $es = $conn->prepare($emp_sql);
    $es->bind_param($types, ...$params);
    $es->execute();
    $emp_summary = $es->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $emp_summary = $conn->query($emp_sql)->fetch_all(MYSQLI_ASSOC);
}

// ── Admin info ─────────────────────────────────────────────────────────────
$is_from_employees = isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'employee';
$tbl = $is_from_employees ? 'employees' : 'users';
$s3  = $conn->prepare("SELECT * FROM {$tbl} WHERE id = ?");
$s3->bind_param("i", $_SESSION['user_id']);
$s3->execute();
$admin = $s3->get_result()->fetch_assoc();

$months = [
    1 => 'January',
    2 => 'February',
    3 => 'March',
    4 => 'April',
    5 => 'May',
    6 => 'June',
    7 => 'July',
    8 => 'August',
    9 => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December'
];
$months_short = [
    1 => 'Jan',
    2 => 'Feb',
    3 => 'Mar',
    4 => 'Apr',
    5 => 'May',
    6 => 'Jun',
    7 => 'Jul',
    8 => 'Aug',
    9 => 'Sep',
    10 => 'Oct',
    11 => 'Nov',
    12 => 'Dec'
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Reports - JRN Admin</title>
    <link rel="stylesheet" href="assets/css/index-admin.css">
    <link rel="stylesheet" href="assets/css/inquiries-admin.css">
    <link rel="stylesheet" href="assets/css/logout-modal.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    <style>
        /* ── Payroll Analytics Styles ───────────────────────────────────────── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--card-bg, #fff);
            border: 1px solid var(--border-color, #e5e7eb);
            border-radius: 14px;
            padding: 1.1rem 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--sc-accent, #0F3A40);
            border-radius: 14px 14px 0 0;
        }

        .stat-card .stat-icon {
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
            opacity: 0.75;
        }

        .stat-card .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stat-card .stat-value {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text-primary);
        }

        .stat-card .stat-value.green {
            color: #16a34a;
        }

        .stat-card .stat-value.yellow {
            color: #d97706;
        }

        .stat-card .stat-value.blue {
            color: var(--primary, #0F3A40);
        }

        .stat-card .stat-value.red {
            color: #dc2626;
        }

        .stat-card .stat-sub {
            font-size: 0.72rem;
            color: var(--text-secondary);
            margin-top: 0.1rem;
        }

        /* ── Charts grid ── */
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }

        .charts-grid.full {
            grid-template-columns: 1fr;
        }

        @media (max-width: 820px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }

        .chart-card {
            background: var(--card-bg, #fff);
            border: 1px solid var(--border-color, #e5e7eb);
            border-radius: 14px;
            overflow: hidden;
        }

        .chart-card-header {
            padding: 0.9rem 1.25rem;
            border-bottom: 1px solid var(--border-color, #f0f0f0);
            background: var(--gray-50, #fafafa);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .chart-card-header h3 {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chart-card-header .ch-sub {
            font-size: 0.72rem;
            color: var(--text-secondary);
        }

        .chart-wrap {
            padding: 1rem;
            position: relative;
        }

        .chart-wrap canvas {
            max-height: 260px;
        }

        /* ── Employee summary table ── */
        .emp-summary-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.83rem;
        }

        .emp-summary-table th {
            padding: 0.65rem 0.9rem;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--text-secondary);
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            background: var(--gray-50, #fafafa);
        }

        .emp-summary-table td {
            padding: 0.75rem 0.9rem;
            border-bottom: 1px solid var(--border-color, #f3f4f6);
            color: var(--text-primary);
            vertical-align: middle;
        }

        .emp-summary-table tbody tr:last-child td {
            border-bottom: none;
        }

        .emp-summary-table tbody tr:hover {
            background: var(--gray-50, #fafafa);
        }

        .emp-bar-wrap {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .emp-bar-bg {
            flex: 1;
            height: 6px;
            background: var(--border-color, #e5e7eb);
            border-radius: 999px;
            overflow: hidden;
        }

        .emp-bar-fill {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #0F3A40, #1C4F50);
        }

        /* ── Deductions donut legend ── */
        .donut-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            padding: 0 1rem 1rem;
        }

        .dl-item {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.72rem;
            color: var(--text-secondary);
        }

        .dl-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        /* ── Filter bar ── */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: flex-end;
            margin-bottom: 1.25rem;
        }

        .filter-bar .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }

        .filter-bar label {
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .filter-bar select,
        .filter-bar input[type="number"] {
            padding: 0.45rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--card-bg);
            color: var(--text-primary);
            font-size: 0.875rem;
            min-width: 130px;
        }

        /* ── Deduction grid in modal ── */
        .deduction-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        .modal-section-title {
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 0.75rem 0 0.25rem;
            border-top: 1px solid var(--border-color);
            margin-top: 0.5rem;
        }

        /* ── Print optimisation ── */
        @media print {

            .sidebar,
            .admin-header,
            .filter-bar,
            .btn,
            .modal,
            #addPayrollModal,
            #viewPayrollModal {
                display: none !important;
            }

            .main-content {
                margin: 0 !important;
            }

            .chart-card {
                break-inside: avoid;
            }
        }
    </style>
</head>

<body>

    <!-- ── Sidebar ─────────────────────────────────────────────────────────────── -->
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
                </svg>Dashboard
            </a>
            <a href="inquiries-admin.php" class="nav-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>Inquiries
            </a>
            <?php if (isAdmin()): ?>
                <a href="billing-admin.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="6" rx="1" />
                        <rect x="3" y="12" width="18" height="8" rx="1" />
                        <line x1="7" y1="16" x2="11" y2="16" />
                        <line x1="7" y1="19" x2="15" y2="19" />
                    </svg>Billing
                    <?php if ($pending_bills > 0): ?><span class="badge"><?php echo $pending_bills; ?></span><?php endif; ?>
                </a>
            <?php endif; ?>
            <a href="users-admin.php" class="nav-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                </svg>Users
            </a>
            <?php if (isAdmin()): ?>
                <a href="employees-admin.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>Employees
                </a>
                <a href="activity-logs.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>Activity Logs
                </a>
                <a href="services-admin.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="6" width="18" height="12" rx="2" />
                        <path d="M3 10h18" />
                    </svg>Manage Services
                </a>
                <a href="payroll-reports-admin.php" class="nav-item active">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" />
                        <line x1="8" y1="21" x2="16" y2="21" />
                        <line x1="12" y1="17" x2="12" y2="21" />
                        <path d="M6 8h.01M10 8h4M6 12h12" />
                    </svg>Payroll Reports
                </a>
            <?php endif; ?>
            <div style="margin-top:auto;padding-top:1rem;border-top:1px solid var(--border-color);">
                <a href="admin-account.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M12 1v6m0 6v6m6-11h-6m-6 0H1m18.4-3.6l-4.2 4.2m-8.4 0l-4.2-4.2M18.4 18.4l-4.2-4.2m-8.4 0l-4.2 4.2"></path>
                    </svg>My Account
                </a>
            </div>
        </nav>
        <div class="sidebar-footer">
            <a href="#" class="nav-item logout" id="logout-btn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>Logout
            </a>
        </div>
    </aside>

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

    <!-- ── Main Content ──────────────────────────────────────────────────────── -->
    <main class="main-content">
        <header class="admin-header">
            <div class="admin-header-left">
                <h1>Payroll Reports</h1>
                <p class="header-subtitle">Visual analytics and payroll management — <?php echo $filter_year; ?></p>
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
            <div class="alert alert--<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <!-- ── KPI Stats ──────────────────────────────────────────────────────── -->
        <div class="stats-grid">
            <div class="stat-card" style="--sc-accent:#0F3A40;">
                <span class="stat-icon">📋</span>
                <span class="stat-label">Total Records</span>
                <span class="stat-value blue"><?php echo number_format($stats['total_records'] ?? 0); ?></span>
                <span class="stat-sub"><?php echo ($stats['count_paid'] ?? 0); ?> paid · <?php echo ($stats['count_pending'] ?? 0); ?> pending</span>
            </div>
            <div class="stat-card" style="--sc-accent:#7c3aed;">
                <span class="stat-icon">💰</span>
                <span class="stat-label">Total Gross Pay</span>
                <span class="stat-value">₱<?php echo number_format($stats['total_gross'] ?? 0, 2); ?></span>
                <span class="stat-sub">Before deductions</span>
            </div>
            <div class="stat-card" style="--sc-accent:#0F3A40;">
                <span class="stat-icon">🏦</span>
                <span class="stat-label">Total Net Pay</span>
                <span class="stat-value blue">₱<?php echo number_format($stats['total_net'] ?? 0, 2); ?></span>
                <span class="stat-sub">Take-home amount</span>
            </div>
            <div class="stat-card" style="--sc-accent:#16a34a;">
                <span class="stat-icon">✅</span>
                <span class="stat-label">Paid Out</span>
                <span class="stat-value green">₱<?php echo number_format($stats['total_paid'] ?? 0, 2); ?></span>
                <span class="stat-sub"><?php echo ($stats['count_paid'] ?? 0); ?> records</span>
            </div>
            <div class="stat-card" style="--sc-accent:#d97706;">
                <span class="stat-icon">⏳</span>
                <span class="stat-label">Pending Release</span>
                <span class="stat-value yellow">₱<?php echo number_format($stats['total_pending'] ?? 0, 2); ?></span>
                <span class="stat-sub"><?php echo ($stats['count_pending'] ?? 0); ?> records</span>
            </div>
            <div class="stat-card" style="--sc-accent:#dc2626;">
                <span class="stat-icon">📉</span>
                <span class="stat-label">Total Deductions</span>
                <?php $total_ded = ($stats['total_sss'] ?? 0) + ($stats['total_ph'] ?? 0) + ($stats['total_pi'] ?? 0) + ($stats['total_tax'] ?? 0) + ($stats['total_other'] ?? 0); ?>
                <span class="stat-value red">₱<?php echo number_format($total_ded, 2); ?></span>
                <span class="stat-sub">SSS, PhilHealth, Pag-IBIG, Tax</span>
            </div>
        </div>

        <!-- ── Charts Row 1: Monthly Trend + Paid vs Pending ─────────────────── -->
        <div class="charts-grid">
            <!-- Monthly Net Pay Trend -->
            <div class="chart-card">
                <div class="chart-card-header">
                    <h3>📈 Monthly Payroll Trend</h3>
                    <span class="ch-sub"><?php echo $filter_year; ?> — Net vs Gross</span>
                </div>
                <div class="chart-wrap">
                    <canvas id="chartMonthly"></canvas>
                </div>
            </div>

            <!-- Paid vs Pending Donut -->
            <div class="chart-card">
                <div class="chart-card-header">
                    <h3>🍩 Payment Status</h3>
                    <span class="ch-sub">Paid vs Pending (by record count)</span>
                </div>
                <div class="chart-wrap" style="display:flex;align-items:center;justify-content:center;">
                    <canvas id="chartStatus" style="max-height:240px;max-width:240px;"></canvas>
                </div>
                <div class="donut-legend">
                    <div class="dl-item">
                        <div class="dl-dot" style="background:#16a34a;"></div> Paid (<?php echo ($stats['count_paid'] ?? 0); ?>)
                    </div>
                    <div class="dl-item">
                        <div class="dl-dot" style="background:#d97706;"></div> Pending (<?php echo ($stats['count_pending'] ?? 0); ?>)
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Charts Row 2: Deductions Breakdown + Earnings Composition ──────── -->
        <div class="charts-grid">
            <!-- Deductions breakdown -->
            <div class="chart-card">
                <div class="chart-card-header">
                    <h3>🏛️ Deductions Breakdown</h3>
                    <span class="ch-sub">SSS / PhilHealth / Pag-IBIG / Tax / Other</span>
                </div>
                <div class="chart-wrap">
                    <canvas id="chartDeductions"></canvas>
                </div>
            </div>

            <!-- Earnings composition bar -->
            <div class="chart-card">
                <div class="chart-card-header">
                    <h3>📊 Earnings Composition</h3>
                    <span class="ch-sub">Basic Salary vs Allowances vs Overtime</span>
                </div>
                <div class="chart-wrap" style="display:flex;align-items:center;justify-content:center;">
                    <canvas id="chartEarnings" style="max-height:240px;max-width:240px;"></canvas>
                </div>
                <div class="donut-legend">
                    <div class="dl-item">
                        <div class="dl-dot" style="background:#0F3A40;"></div> Basic Salary
                    </div>
                    <div class="dl-item">
                        <div class="dl-dot" style="background:#1C4F50;"></div> Allowances
                    </div>
                    <div class="dl-item">
                        <div class="dl-dot" style="background:#D9FF00;border:1px solid #ccc;"></div> Overtime Pay
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Employee Summary ───────────────────────────────────────────────── -->
        <?php if (count($emp_summary) > 0):
            $maxNet = max(array_column($emp_summary, 'total_net') ?: [1]);
        ?>
            <div class="card" style="margin-bottom:1.25rem;">
                <div class="card-header">
                    <h2>👥 Employee Payroll Summary</h2>
                </div>
                <table class="emp-summary-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Records</th>
                            <th>Gross Pay</th>
                            <th>Net Pay</th>
                            <th>Paid</th>
                            <th style="width:160px;">Net Pay Distribution</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emp_summary as $es): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($es['first_name'] . ' ' . $es['last_name']); ?></strong></td>
                                <td><?php echo $es['records']; ?></td>
                                <td>₱<?php echo number_format($es['total_gross'], 2); ?></td>
                                <td><strong style="color:var(--primary);">₱<?php echo number_format($es['total_net'], 2); ?></strong></td>
                                <td><span class="status status--success" style="font-size:0.72rem;"><?php echo $es['paid_count']; ?>/<?php echo $es['records']; ?></span></td>
                                <td>
                                    <div class="emp-bar-wrap">
                                        <div class="emp-bar-bg">
                                            <div class="emp-bar-fill" style="width:<?php echo min(100, round(($es['total_net'] / $maxNet) * 100)); ?>%;"></div>
                                        </div>
                                        <span style="font-size:0.68rem;color:var(--text-secondary);white-space:nowrap;">
                                            <?php echo round(($es['total_net'] / max($maxNet, 1)) * 100); ?>%
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- ── Filter Bar ─────────────────────────────────────────────────────── -->
        <div class="card" style="margin-bottom:1.25rem;">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.75rem;">
                <h2>Filter Records</h2>
                <div style="display:flex;gap:0.5rem;">
                    <button class="btn btn--primary btn--sm" onclick="openModal('addPayrollModal')">+ Add Payroll</button>
                    <button class="btn btn--outline btn--sm" onclick="window.print()">🖨 Print / Export</button>
                </div>
            </div>
            <form method="GET" class="filter-bar" style="padding:0 1.25rem 1.25rem;">
                <div class="form-group">
                    <label>Month</label>
                    <select name="month">
                        <option value="0">All Months</option>
                        <?php foreach ($months as $num => $name): ?>
                            <option value="<?php echo $num; ?>" <?php echo $filter_month == $num ? 'selected' : ''; ?>><?php echo $name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Year</label>
                    <input type="number" name="year" value="<?php echo $filter_year; ?>" min="2020" max="2099" style="width:90px;">
                </div>
                <div class="form-group">
                    <label>Employee</label>
                    <select name="employee_id">
                        <option value="0">All Employees</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>" <?php echo $filter_employee == $emp['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="all" <?php echo $filter_status === 'all'     ? 'selected' : ''; ?>>All</option>
                        <option value="paid" <?php echo $filter_status === 'paid'    ? 'selected' : ''; ?>>Paid</option>
                        <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>
                <div class="form-group"><label>&nbsp;</label><button type="submit" class="btn btn--primary">Apply</button></div>
                <div class="form-group"><label>&nbsp;</label><a href="payroll-reports-admin.php" class="btn btn--outline">Clear</a></div>
            </form>
        </div>

        <!-- ── Payroll Table ──────────────────────────────────────────────────── -->
        <div class="card">
            <div class="card-header">
                <h2>Payroll Records (<?php echo count($payroll_records); ?>)</h2>
            </div>
            <?php if (count($payroll_records) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Period</th>
                            <th>Basic Salary</th>
                            <th>Allowances</th>
                            <th>Gross Pay</th>
                            <th>Deductions</th>
                            <th>Net Pay</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payroll_records as $rec):
                            $total_deductions = $rec['sss_deduction'] + $rec['philhealth_deduction']
                                + $rec['pagibig_deduction'] + $rec['tax_deduction'] + $rec['other_deductions'];
                            $statusClass = $rec['status'] === 'paid' ? 'status--success' : 'status--warning';
                        ?>
                            <tr>
                                <td>
                                    <div class="contact-cell">
                                        <strong><?php echo htmlspecialchars($rec['first_name'] . ' ' . $rec['last_name']); ?></strong>
                                        <small><?php echo htmlspecialchars($rec['email'] ?? ''); ?></small>
                                    </div>
                                </td>
                                <td><strong><?php echo $months[$rec['period_month']]; ?> <?php echo $rec['period_year']; ?></strong></td>
                                <td>₱<?php echo number_format($rec['basic_salary'], 2); ?></td>
                                <td>₱<?php echo number_format($rec['allowances'] + $rec['overtime_pay'], 2); ?></td>
                                <td><strong>₱<?php echo number_format($rec['gross_pay'], 2); ?></strong></td>
                                <td style="color:var(--danger);">₱<?php echo number_format($total_deductions, 2); ?></td>
                                <td><strong style="color:var(--primary);">₱<?php echo number_format($rec['net_pay'], 2); ?></strong></td>
                                <td><span class="status <?php echo $statusClass; ?>"><?php echo ucfirst($rec['status']); ?></span></td>
                                <td>
                                    <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
                                        <button class="btn btn--sm btn--outline" onclick="viewPayroll(<?php echo htmlspecialchars(json_encode($rec)); ?>)">View</button>
                                        <?php if ($rec['status'] === 'pending'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="payroll_id" value="<?php echo $rec['id']; ?>">
                                                <input type="hidden" name="new_status" value="paid">
                                                <button type="submit" name="toggle_status" class="btn btn--sm btn--primary"
                                                    onclick="return confirm('Mark as Paid?')">Mark Paid</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="payroll_id" value="<?php echo $rec['id']; ?>">
                                                <input type="hidden" name="new_status" value="pending">
                                                <button type="submit" name="toggle_status" class="btn btn--sm btn--outline"
                                                    onclick="return confirm('Revert to Pending?')"
                                                    style="color:var(--danger);border-color:var(--danger);">Revert</button>
                                            </form>
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
                        <rect x="2" y="3" width="20" height="14" rx="2" />
                        <line x1="8" y1="21" x2="16" y2="21" />
                        <line x1="12" y1="17" x2="12" y2="21" />
                    </svg>
                    <p>No payroll records found for the selected filters.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- ── Add Payroll Modal ──────────────────────────────────────────────────── -->
    <div id="addPayrollModal" class="modal">
        <div class="modal-content" style="max-width:640px;">
            <div class="modal-header">
                <h2>Add Payroll Record</h2><button class="modal-close" onclick="closeModal('addPayrollModal')">&times;</button>
            </div>
            <form method="POST" class="modal-body">
                <div class="form-group">
                    <label>Employee</label>
                    <select name="employee_id" class="form-control" required>
                        <option value="">-- Select Employee --</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                    <div class="form-group">
                        <label>Month</label>
                        <select name="period_month" class="form-control" required>
                            <?php foreach ($months as $num => $name): ?>
                                <option value="<?php echo $num; ?>" <?php echo date('n') == $num ? 'selected' : ''; ?>><?php echo $name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Year</label>
                        <input type="number" name="period_year" class="form-control" value="<?php echo date('Y'); ?>" min="2020" max="2099" required>
                    </div>
                </div>
                <p class="modal-section-title">Earnings</p>
                <div class="deduction-grid">
                    <div class="form-group"><label>Basic Salary (₱)</label><input type="number" name="basic_salary" class="form-control" step="0.01" min="0" value="0.00" required oninput="calcPayroll()"></div>
                    <div class="form-group"><label>Allowances (₱)</label><input type="number" name="allowances" class="form-control" step="0.01" min="0" value="0.00" oninput="calcPayroll()"></div>
                    <div class="form-group"><label>Overtime Pay (₱)</label><input type="number" name="overtime_pay" class="form-control" step="0.01" min="0" value="0.00" oninput="calcPayroll()"></div>
                </div>
                <p class="modal-section-title">Deductions</p>
                <div class="deduction-grid">
                    <div class="form-group"><label>SSS (₱)</label><input type="number" name="sss_deduction" class="form-control" step="0.01" min="0" value="0.00" oninput="calcPayroll()"></div>
                    <div class="form-group"><label>PhilHealth (₱)</label><input type="number" name="philhealth_deduction" class="form-control" step="0.01" min="0" value="0.00" oninput="calcPayroll()"></div>
                    <div class="form-group"><label>Pag-IBIG (₱)</label><input type="number" name="pagibig_deduction" class="form-control" step="0.01" min="0" value="0.00" oninput="calcPayroll()"></div>
                    <div class="form-group"><label>Withholding Tax (₱)</label><input type="number" name="tax_deduction" class="form-control" step="0.01" min="0" value="0.00" oninput="calcPayroll()"></div>
                    <div class="form-group"><label>Other Deductions (₱)</label><input type="number" name="other_deductions" class="form-control" step="0.01" min="0" value="0.00" oninput="calcPayroll()"></div>
                </div>
                <!-- Live preview -->
                <div style="background:var(--gray-100,#f3f4f6);border-radius:10px;padding:1rem;margin:0.75rem 0;display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.5rem;text-align:center;">
                    <div>
                        <div style="font-size:0.7rem;color:var(--text-secondary);font-weight:600;text-transform:uppercase;">Gross Pay</div>
                        <div id="previewGross" style="font-size:1.1rem;font-weight:700;">₱0.00</div>
                    </div>
                    <div>
                        <div style="font-size:0.7rem;color:var(--text-secondary);font-weight:600;text-transform:uppercase;">Deductions</div>
                        <div id="previewDeductions" style="font-size:1.1rem;font-weight:700;color:var(--danger);">₱0.00</div>
                    </div>
                    <div>
                        <div style="font-size:0.7rem;color:var(--text-secondary);font-weight:600;text-transform:uppercase;">Net Pay</div>
                        <div id="previewNet" style="font-size:1.1rem;font-weight:700;color:var(--primary);">₱0.00</div>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                        </select>
                    </div>
                </div>
                <div class="form-group"><label>Notes</label><textarea name="notes" class="form-control" rows="2" placeholder="Optional notes..."></textarea></div>
                <div class="modal-footer">
                    <button type="submit" name="add_payroll" class="btn btn--primary">Save Payroll Record</button>
                    <button type="button" class="btn btn--outline" onclick="closeModal('addPayrollModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── View Payroll Modal ────────────────────────────────────────────────── -->
    <div id="viewPayrollModal" class="modal">
        <div class="modal-content" style="max-width:520px;">
            <div class="modal-header">
                <h2>Payroll Details</h2><button class="modal-close" onclick="closeModal('viewPayrollModal')">&times;</button>
            </div>
            <div id="viewPayrollBody" class="modal-body"></div>
        </div>
    </div>

    <script>
        // ── Theme ──────────────────────────────────────────────────────────────────
        const themeToggle = document.getElementById('themeToggle');
        const htmlElement = document.documentElement;
        htmlElement.setAttribute('data-theme', localStorage.getItem('theme') || 'light');
        themeToggle.addEventListener('click', () => {
            const t = htmlElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            htmlElement.setAttribute('data-theme', t);
            localStorage.setItem('theme', t);
        });

        const months = <?php echo json_encode($months); ?>;
        const monthsShort = <?php echo json_encode(array_values($months_short)); ?>;

        // ── Chart.js defaults ──────────────────────────────────────────────────────
        Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";
        Chart.defaults.font.size = 12;

        // ── 1. Monthly Trend ──────────────────────────────────────────────────────
        (function() {
            const raw = <?php echo json_encode($monthly_raw); ?>;
            const labels = monthsShort;
            const netData = new Array(12).fill(0);
            const grossData = new Array(12).fill(0);
            raw.forEach(r => {
                netData[r.period_month - 1] = parseFloat(r.net) || 0;
                grossData[r.period_month - 1] = parseFloat(r.gross) || 0;
            });

            new Chart(document.getElementById('chartMonthly'), {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                            label: 'Gross Pay',
                            data: grossData,
                            backgroundColor: 'rgba(15,58,64,0.15)',
                            borderColor: 'rgba(15,58,64,0.4)',
                            borderWidth: 1,
                            borderRadius: 4,
                            type: 'bar',
                        },
                        {
                            label: 'Net Pay',
                            data: netData,
                            borderColor: '#0F3A40',
                            backgroundColor: 'rgba(15,58,64,0.08)',
                            borderWidth: 2.5,
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#D9FF00',
                            pointBorderColor: '#0F3A40',
                            pointRadius: 4,
                            type: 'line',
                        },
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 16
                            }
                        }
                    },
                    scales: {
                        y: {
                            ticks: {
                                callback: v => '₱' + (v / 1000).toFixed(0) + 'K'
                            },
                            grid: {
                                color: 'rgba(0,0,0,0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        })();

        // ── 2. Paid vs Pending Donut ───────────────────────────────────────────────
        (function() {
            const paid = <?php echo (int)($stats['count_paid']    ?? 0); ?>;
            const pending = <?php echo (int)($stats['count_pending'] ?? 0); ?>;
            new Chart(document.getElementById('chartStatus'), {
                type: 'doughnut',
                data: {
                    labels: ['Paid', 'Pending'],
                    datasets: [{
                        data: [paid, pending],
                        backgroundColor: ['#16a34a', '#d97706'],
                        borderColor: ['#fff', '#fff'],
                        borderWidth: 3,
                        hoverOffset: 8,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    cutout: '68%',
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: c => ` ${c.label}: ${c.raw} records`
                            }
                        }
                    }
                }
            });
        })();

        // ── 3. Deductions Bar Chart ────────────────────────────────────────────────
        (function() {
            const vals = [
                <?php echo (float)($stats['total_sss']   ?? 0); ?>,
                <?php echo (float)($stats['total_ph']    ?? 0); ?>,
                <?php echo (float)($stats['total_pi']    ?? 0); ?>,
                <?php echo (float)($stats['total_tax']   ?? 0); ?>,
                <?php echo (float)($stats['total_other'] ?? 0); ?>,
            ];
            new Chart(document.getElementById('chartDeductions'), {
                type: 'bar',
                data: {
                    labels: ['SSS', 'PhilHealth', 'Pag-IBIG', 'W/H Tax', 'Other'],
                    datasets: [{
                        label: 'Amount (₱)',
                        data: vals,
                        backgroundColor: ['#0F3A40', '#1C4F50', '#2d6b6e', '#d97706', '#9ca3af'],
                        borderRadius: 6,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                callback: v => '₱' + (v / 1000).toFixed(0) + 'K'
                            },
                            grid: {
                                color: 'rgba(0,0,0,0.05)'
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        })();

        // ── 4. Earnings Donut ─────────────────────────────────────────────────────
        (function() {
            const basic = <?php echo (float)($stats['total_basic']        ?? 0); ?>;
            const allow = <?php echo (float)($stats['total_allowances']   ?? 0); ?>;
            const ot = <?php echo (float)($stats['total_ot']           ?? 0); ?>;
            new Chart(document.getElementById('chartEarnings'), {
                type: 'doughnut',
                data: {
                    labels: ['Basic Salary', 'Allowances', 'Overtime'],
                    datasets: [{
                        data: [basic, allow, ot],
                        backgroundColor: ['#0F3A40', '#1C4F50', '#D9FF00'],
                        borderColor: ['#fff', '#fff', '#fff'],
                        borderWidth: 3,
                        hoverOffset: 8,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: c => ` ${c.label}: ₱${c.raw.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',')}`
                            }
                        }
                    }
                }
            });
        })();

        // ── Payroll modal helpers ──────────────────────────────────────────────────
        function calcPayroll() {
            const g = (id) => parseFloat(document.querySelector(`[name="${id}"]`)?.value || 0);
            const gross = g('basic_salary') + g('allowances') + g('overtime_pay');
            const ded = g('sss_deduction') + g('philhealth_deduction') + g('pagibig_deduction') + g('tax_deduction') + g('other_deductions');
            const net = gross - ded;
            const fmt = v => '₱' + v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            document.getElementById('previewGross').textContent = fmt(gross);
            document.getElementById('previewDeductions').textContent = fmt(ded);
            document.getElementById('previewNet').textContent = fmt(net);
        }

        function viewPayroll(rec) {
            const fmt = v => '₱' + parseFloat(v).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            const ded = parseFloat(rec.sss_deduction) + parseFloat(rec.philhealth_deduction) +
                parseFloat(rec.pagibig_deduction) + parseFloat(rec.tax_deduction) + parseFloat(rec.other_deductions);
            const row = (l, v, s = '') => `<div class="detail-row"><strong>${l}:</strong><span style="${s}">${v}</span></div>`;

            document.getElementById('viewPayrollBody').innerHTML = `
        <div class="inquiry-detail">
            ${row('Employee', (rec.first_name||'') + ' ' + (rec.last_name||''))}
            ${row('Email', rec.email || 'N/A')}
            ${row('Period', months[rec.period_month] + ' ' + rec.period_year)}
            ${row('Status', `<span class="status ${rec.status==='paid'?'status--success':'status--warning'}">${rec.status.charAt(0).toUpperCase()+rec.status.slice(1)}</span>`)}
            ${row('Basic Salary', fmt(rec.basic_salary))}
            ${row('Allowances', fmt(rec.allowances))}
            ${row('Overtime Pay', fmt(rec.overtime_pay))}
            ${row('Gross Pay', fmt(rec.gross_pay), 'font-weight:700;')}
            <div class="detail-row full" style="border-top:1px dashed var(--border-color);padding-top:0.5rem;margin-top:0.25rem;">
                <strong style="color:var(--danger);">Deductions</strong>
            </div>
            ${row('SSS', fmt(rec.sss_deduction))}
            ${row('PhilHealth', fmt(rec.philhealth_deduction))}
            ${row('Pag-IBIG', fmt(rec.pagibig_deduction))}
            ${row('Withholding Tax', fmt(rec.tax_deduction))}
            ${row('Other Deductions', fmt(rec.other_deductions))}
            ${row('Total Deductions', fmt(ded), 'color:var(--danger);font-weight:700;')}
            ${row('Net Pay', fmt(rec.net_pay), 'color:var(--primary);font-weight:700;font-size:1.1rem;')}
            ${rec.paid_at ? row('Paid At', new Date(rec.paid_at).toLocaleString()) : ''}
            ${rec.notes ? `<div class="detail-row full"><strong>Notes:</strong><p style="margin-top:0.3rem;">${rec.notes}</p></div>` : ''}
        </div>`;
            document.getElementById('viewPayrollModal').classList.add('active');
        }

        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }
        window.onclick = e => {
            if (e.target.classList.contains('modal')) e.target.classList.remove('active');
        };
    </script>
</body>

</html>