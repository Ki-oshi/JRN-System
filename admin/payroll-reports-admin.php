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
        $message      = "Payroll record added successfully.";
        $message_type = "success";
        logActivity(
            $_SESSION['user_id'],
            'admin',
            'payroll_added',
            "Added payroll for employee ID {$employee_id} ({$period_month}/{$period_year})"
        );
    } else {
        $message      = "Error adding payroll record: " . $stmt->error;
        $message_type = "error";
    }
    $stmt->close();
}

// ── Handle Status Toggle ───────────────────────────────────────────────────
if (isset($_POST['toggle_status'])) {
    $payroll_id = (int)$_POST['payroll_id'];
    $new_status = in_array($_POST['new_status'], ['paid', 'pending']) ? $_POST['new_status'] : 'pending';
    $paid_at    = ($new_status === 'paid') ? date('Y-m-d H:i:s') : null;

    $stmt = $conn->prepare("UPDATE payroll SET status = ?, paid_at = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("ssi", $new_status, $paid_at, $payroll_id);
    if ($stmt->execute()) {
        $message      = "Payroll status updated to <strong>" . ucfirst($new_status) . "</strong>.";
        $message_type = "success";
        logActivity(
            $_SESSION['user_id'],
            'admin',
            'payroll_status_updated',
            "Payroll ID {$payroll_id} status changed to {$new_status}"
        );
    } else {
        $message      = "Error updating status: " . $stmt->error;
        $message_type = "error";
    }
    $stmt->close();
}

// ── Filters ────────────────────────────────────────────────────────────────
$filter_month    = (int)($_GET['month']       ?? 0);
$filter_year     = (int)($_GET['year']        ?? date('Y'));
$filter_employee = (int)($_GET['employee_id'] ?? 0);
$filter_status   = $_GET['status'] ?? 'all';

// ── Pending bills badge ────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT COUNT(*) as c FROM billings WHERE status = 'unpaid'");
$stmt->execute();
$pending_bills = $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

// ── Employees dropdown ─────────────────────────────────────────────────────
$emp_result = $conn->query("SELECT id, first_name, last_name FROM employees ORDER BY first_name");
$employees  = $emp_result->fetch_all(MYSQLI_ASSOC);

// ── Build dynamic payroll query ────────────────────────────────────────────
$where  = ["1=1"];
$params = [];
$types  = "";
if ($filter_month > 0) {
    $where[]  = "p.period_month = ?";
    $params[] = $filter_month;
    $types   .= "i";
}
if ($filter_year > 0) {
    $where[]  = "p.period_year = ?";
    $params[] = $filter_year;
    $types   .= "i";
}
if ($filter_employee > 0) {
    $where[]  = "p.employee_id = ?";
    $params[] = $filter_employee;
    $types   .= "i";
}
if ($filter_status !== 'all') {
    $where[]  = "p.status = ?";
    $params[] = $filter_status;
    $types   .= "s";
}
$where_sql = implode(" AND ", $where);

$sql = "SELECT p.*, e.first_name, e.last_name, e.email
        FROM payroll p LEFT JOIN employees e ON p.employee_id = e.id
        WHERE {$where_sql}
        ORDER BY p.period_year DESC, p.period_month DESC, p.id DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $payroll_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
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
    $s2->close();
} else {
    $stats = $conn->query($stats_sql)->fetch_assoc();
}

// ── Monthly trend ──────────────────────────────────────────────────────────
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
$monthly_stmt->close();

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
    $es->close();
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
$s3->close();

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

        .alert {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .9rem 1.25rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.25rem;
            font-size: .88rem;
            font-weight: 500;
        }

        .alert--success {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .alert--error {
            background: #fff1f2;
            color: #be123c;
            border: 1px solid #fecdd3;
        }

        /* ── Stats grid ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 1.1rem 1.25rem;
            display: flex;
            flex-direction: column;
            gap: .3rem;
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
            margin-bottom: .25rem;
            opacity: .75;
        }

        .stat-card .stat-label {
            font-size: .7rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .05em;
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
            color: var(--primary);
        }

        .stat-card .stat-value.red {
            color: #dc2626;
        }

        .stat-card .stat-sub {
            font-size: .72rem;
            color: var(--text-secondary);
            margin-top: .1rem;
        }

        /* ── Charts ── */
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }

        @media(max-width:820px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }

        .chart-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 14px;
            overflow: hidden;
        }

        .chart-card-header {
            padding: .9rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            background: #fafafa;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            flex-wrap: wrap;
        }

        .chart-card-header h3 {
            font-size: .88rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .chart-card-header .ch-sub {
            font-size: .72rem;
            color: var(--text-secondary);
        }

        .chart-wrap {
            padding: 1rem;
            position: relative;
        }

        .chart-wrap canvas {
            max-height: 260px;
        }

        .donut-legend {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            padding: 0 1rem 1rem;
        }

        .dl-item {
            display: flex;
            align-items: center;
            gap: .35rem;
            font-size: .72rem;
            color: var(--text-secondary);
        }

        .dl-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        /* ── Employee summary ── */
        .emp-summary-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .83rem;
        }

        .emp-summary-table th {
            padding: .65rem .9rem;
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--text-secondary);
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            background: #fafafa;
        }

        .emp-summary-table td {
            padding: .75rem .9rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }

        .emp-summary-table tbody tr:last-child td {
            border-bottom: none;
        }

        .emp-summary-table tbody tr:hover {
            background: #fafafa;
        }

        .emp-bar-wrap {
            display: flex;
            align-items: center;
            gap: .6rem;
        }

        .emp-bar-bg {
            flex: 1;
            height: 6px;
            background: var(--border-color);
            border-radius: 999px;
            overflow: hidden;
        }

        .emp-bar-fill {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #0F3A40, #1C4F50);
        }

        /* ── Filter bar ── */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            align-items: flex-end;
            margin-bottom: 1.25rem;
        }

        .filter-bar .form-group {
            display: flex;
            flex-direction: column;
            gap: .3rem;
        }

        .filter-bar label {
            font-size: .72rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .filter-bar select,
        .filter-bar input[type="number"] {
            padding: .45rem .75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: #fff;
            color: var(--text-primary);
            font-size: .875rem;
            min-width: 130px;
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
            font-size: .71rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            padding: .75rem 1rem;
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }

        .data-table tbody tr {
            transition: background .15s;
        }

        .data-table tbody tr:hover {
            background: #f8fafc;
        }

        .data-table tbody td {
            padding: .85rem 1rem;
            border-bottom: 1px solid var(--border-color);
            font-size: .84rem;
            color: var(--text-primary);
            vertical-align: middle;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* ── Deduction grid in add modal ── */
        .deduction-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .75rem;
        }

        .modal-section-title {
            font-size: .72rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: .06em;
            padding: .75rem 0 .25rem;
            border-top: 1px solid var(--border-color);
            margin-top: .5rem;
        }

        /* ══════════════════════════════════════════
           CONFIRMATION MODAL OVERLAY SYSTEM
        ══════════════════════════════════════════ */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, .55);
            backdrop-filter: blur(6px);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            opacity: 0;
            visibility: hidden;
            transition: opacity .25s ease, visibility .25s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-overlay.active .modal-panel {
            transform: translateY(0) scale(1);
            opacity: 1;
        }

        .modal-panel {
            background: #fff;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            width: 100%;
            max-width: 460px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transform: translateY(24px) scale(.97);
            opacity: 0;
            transition: transform .3s cubic-bezier(.34, 1.2, .64, 1), opacity .25s ease;
        }

        .modal-head {
            padding: 1.5rem 1.75rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
        }

        .modal-head-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 .25rem;
            line-height: 1.3;
        }

        .modal-head-sub {
            font-size: .8rem;
            color: var(--text-secondary);
        }

        .modal-close-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            background: var(--bg-secondary);
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
            transition: all .15s;
            line-height: 1;
        }

        .modal-close-btn:hover {
            background: #fee2e2;
            color: #dc2626;
        }

        .modal-body-inner {
            padding: 1.5rem 1.75rem;
        }

        .modal-foot {
            padding: 1.1rem 1.75rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: .65rem;
            justify-content: flex-end;
            background: var(--bg-secondary);
        }

        .btn-modal-primary {
            height: 40px;
            padding: 0 1.4rem;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            font-size: .85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .15s;
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            font-family: 'DM Sans', sans-serif;
        }

        .btn-modal-primary:hover {
            background: var(--primary-light);
            box-shadow: 0 3px 12px rgba(15, 58, 64, .3);
        }

        .btn-modal-outline {
            height: 40px;
            padding: 0 1.2rem;
            background: #fff;
            color: var(--text-primary);
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: .85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .15s;
            font-family: 'DM Sans', sans-serif;
        }

        .btn-modal-outline:hover {
            border-color: var(--border-strong);
            background: var(--bg-secondary);
        }

        .info-box {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 1rem 1.1rem;
            font-size: .88rem;
            line-height: 1.6;
            color: var(--text-primary);
        }

        .info-box strong {
            color: var(--primary);
        }

        .empty-state {
            padding: 4rem 2rem;
            text-align: center;
        }

        .empty-state p {
            color: var(--text-secondary);
            font-size: .9rem;
            margin: 0 0 1rem;
        }

        @media print {

            .sidebar,
            .admin-header,
            .filter-bar,
            .btn,
            .modal-overlay,
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
                    <rect x="3" y="3" width="7" height="7" />
                    <rect x="14" y="3" width="7" height="7" />
                    <rect x="14" y="14" width="7" height="7" />
                    <rect x="3" y="14" width="7" height="7" />
                </svg>Dashboard
            </a>
            <a href="inquiries-admin.php" class="nav-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
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
                    <?php if ($pending_bills > 0): ?><span class="badge"><?= $pending_bills ?></span><?php endif; ?>
                </a>
            <?php endif; ?>
            <a href="users-admin.php" class="nav-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="7" r="4" />
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                </svg>Users
            </a>
            <?php if (isAdmin()): ?>
                <a href="employees-admin.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                        <circle cx="12" cy="7" r="4" />
                    </svg>Employees
                </a>
                <a href="activity-logs.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                        <polyline points="14 2 14 8 20 8" />
                        <line x1="16" y1="13" x2="8" y2="13" />
                        <line x1="16" y1="17" x2="8" y2="17" />
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
                    </svg>Payroll Reports
                </a>
            <?php endif; ?>
            <div style="margin-top:auto;padding-top:1rem;border-top:1px solid var(--border-color);">
                <a href="admin-account.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3" />
                        <path d="M12 1v6m0 6v6m6-11h-6m-6 0H1m18.4-3.6l-4.2 4.2m-8.4 0l-4.2-4.2M18.4 18.4l-4.2-4.2m-8.4 0l-4.2 4.2" />
                    </svg>My Account
                </a>
            </div>
        </nav>
        <div class="sidebar-footer">
            <a href="#" class="nav-item logout" id="logout-btn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                    <polyline points="16 17 21 12 16 7" />
                    <line x1="21" y1="12" x2="9" y2="12" />
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
                <p class="header-subtitle">Visual analytics and payroll management — <?= $filter_year ?></p>
            </div>
            <div class="admin-header-right">
                <div class="avatar-circle"><?= strtoupper(substr($admin['first_name'] ?? 'A', 0, 1)) ?></div>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert--<?= $message_type ?>"><?= $message ?></div>
        <?php endif; ?>

        <!-- ── KPI Stats ── -->
        <div class="stats-grid">
            <div class="stat-card" style="--sc-accent:#0F3A40;">
                <span class="stat-icon">📋</span>
                <span class="stat-label">Total Records</span>
                <span class="stat-value blue"><?= number_format($stats['total_records'] ?? 0) ?></span>
                <span class="stat-sub"><?= ($stats['count_paid'] ?? 0) ?> paid · <?= ($stats['count_pending'] ?? 0) ?> pending</span>
            </div>
            <div class="stat-card" style="--sc-accent:#7c3aed;">
                <span class="stat-icon">💰</span>
                <span class="stat-label">Total Gross Pay</span>
                <span class="stat-value">₱<?= number_format($stats['total_gross'] ?? 0, 2) ?></span>
                <span class="stat-sub">Before deductions</span>
            </div>
            <div class="stat-card" style="--sc-accent:#0F3A40;">
                <span class="stat-icon">🏦</span>
                <span class="stat-label">Total Net Pay</span>
                <span class="stat-value blue">₱<?= number_format($stats['total_net'] ?? 0, 2) ?></span>
                <span class="stat-sub">Take-home amount</span>
            </div>
            <div class="stat-card" style="--sc-accent:#16a34a;">
                <span class="stat-icon">✅</span>
                <span class="stat-label">Paid Out</span>
                <span class="stat-value green">₱<?= number_format($stats['total_paid'] ?? 0, 2) ?></span>
                <span class="stat-sub"><?= ($stats['count_paid'] ?? 0) ?> records</span>
            </div>
            <div class="stat-card" style="--sc-accent:#d97706;">
                <span class="stat-icon">⏳</span>
                <span class="stat-label">Pending Release</span>
                <span class="stat-value yellow">₱<?= number_format($stats['total_pending'] ?? 0, 2) ?></span>
                <span class="stat-sub"><?= ($stats['count_pending'] ?? 0) ?> records</span>
            </div>
            <div class="stat-card" style="--sc-accent:#dc2626;">
                <span class="stat-icon">📉</span>
                <span class="stat-label">Total Deductions</span>
                <?php $total_ded = ($stats['total_sss'] ?? 0) + ($stats['total_ph'] ?? 0) + ($stats['total_pi'] ?? 0) + ($stats['total_tax'] ?? 0) + ($stats['total_other'] ?? 0); ?>
                <span class="stat-value red">₱<?= number_format($total_ded, 2) ?></span>
                <span class="stat-sub">SSS, PhilHealth, Pag-IBIG, Tax</span>
            </div>
        </div>

        <!-- ── Charts Row 1 ── -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-card-header">
                    <h3>📈 Monthly Payroll Trend</h3>
                    <span class="ch-sub"><?= $filter_year ?> — Net vs Gross</span>
                </div>
                <div class="chart-wrap"><canvas id="chartMonthly"></canvas></div>
            </div>
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
                        <div class="dl-dot" style="background:#16a34a;"></div> Paid (<?= ($stats['count_paid'] ?? 0) ?>)
                    </div>
                    <div class="dl-item">
                        <div class="dl-dot" style="background:#d97706;"></div> Pending (<?= ($stats['count_pending'] ?? 0) ?>)
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Charts Row 2 ── -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-card-header">
                    <h3>🏛️ Deductions Breakdown</h3>
                    <span class="ch-sub">SSS / PhilHealth / Pag-IBIG / Tax / Other</span>
                </div>
                <div class="chart-wrap"><canvas id="chartDeductions"></canvas></div>
            </div>
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

        <!-- ── Employee Summary ── -->
        <?php if (count($emp_summary) > 0):
            $maxNet = max(array_column($emp_summary, 'total_net') ?: [1]); ?>
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
                                <td><strong><?= htmlspecialchars($es['first_name'] . ' ' . $es['last_name']) ?></strong></td>
                                <td><?= $es['records'] ?></td>
                                <td>₱<?= number_format($es['total_gross'], 2) ?></td>
                                <td><strong style="color:var(--primary);">₱<?= number_format($es['total_net'], 2) ?></strong></td>
                                <td><span class="status status--success" style="font-size:.72rem;"><?= $es['paid_count'] ?>/<?= $es['records'] ?></span></td>
                                <td>
                                    <div class="emp-bar-wrap">
                                        <div class="emp-bar-bg">
                                            <div class="emp-bar-fill" style="width:<?= min(100, round(($es['total_net'] / $maxNet) * 100)) ?>%;"></div>
                                        </div>
                                        <span style="font-size:.68rem;color:var(--text-secondary);white-space:nowrap;">
                                            <?= round(($es['total_net'] / max($maxNet, 1)) * 100) ?>%
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- ── Filter Bar ── -->
        <div class="card" style="margin-bottom:1.25rem;">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem;">
                <h2>Filter Records</h2>
                <div style="display:flex;gap:.5rem;">
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
                            <option value="<?= $num ?>" <?= $filter_month == $num ? 'selected' : '' ?>><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Year</label>
                    <input type="number" name="year" value="<?= $filter_year ?>" min="2020" max="2099" style="width:90px;">
                </div>
                <div class="form-group">
                    <label>Employee</label>
                    <select name="employee_id">
                        <option value="0">All Employees</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>" <?= $filter_employee == $emp['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="all" <?= $filter_status === 'all'     ? 'selected' : '' ?>>All</option>
                        <option value="paid" <?= $filter_status === 'paid'    ? 'selected' : '' ?>>Paid</option>
                        <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    </select>
                </div>
                <div class="form-group"><label>&nbsp;</label><button type="submit" class="btn btn--primary">Apply</button></div>
                <div class="form-group"><label>&nbsp;</label><a href="payroll-reports-admin.php" class="btn btn--outline">Clear</a></div>
            </form>
        </div>

        <!-- ── Payroll Table ── -->
        <div class="card">
            <div class="card-header">
                <h2>Payroll Records (<?= count($payroll_records) ?>)</h2>
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
                                    <strong><?= htmlspecialchars($rec['first_name'] . ' ' . $rec['last_name']) ?></strong>
                                    <small style="display:block;color:var(--text-secondary);font-size:.73rem;"><?= htmlspecialchars($rec['email'] ?? '') ?></small>
                                </td>
                                <td><strong><?= $months[$rec['period_month']] ?> <?= $rec['period_year'] ?></strong></td>
                                <td>₱<?= number_format($rec['basic_salary'], 2) ?></td>
                                <td>₱<?= number_format($rec['allowances'] + $rec['overtime_pay'], 2) ?></td>
                                <td><strong>₱<?= number_format($rec['gross_pay'], 2) ?></strong></td>
                                <td style="color:#dc2626;">₱<?= number_format($total_deductions, 2) ?></td>
                                <td><strong style="color:var(--primary);">₱<?= number_format($rec['net_pay'], 2) ?></strong></td>
                                <td><span class="status <?= $statusClass ?>"><?= ucfirst($rec['status']) ?></span></td>
                                <td>
                                    <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
                                        <button class="btn btn--sm btn--outline"
                                            onclick="viewPayroll(<?= htmlspecialchars(json_encode($rec), ENT_QUOTES) ?>)">View</button>

                                        <?php if ($rec['status'] === 'pending'): ?>
                                            <!-- Mark as Paid -->
                                            <button class="btn btn--sm btn--primary"
                                                onclick="openStatusModal('markPaidModal', <?= $rec['id'] ?>, '<?= htmlspecialchars($rec['first_name'] . ' ' . $rec['last_name']) ?>', '<?= $months[$rec['period_month']] ?> <?= $rec['period_year'] ?>')">
                                                Mark Paid
                                            </button>
                                        <?php else: ?>
                                            <!-- Revert to Pending -->
                                            <button class="btn btn--sm btn--outline"
                                                onclick="openStatusModal('revertPendingModal', <?= $rec['id'] ?>, '<?= htmlspecialchars($rec['first_name'] . ' ' . $rec['last_name']) ?>', '<?= $months[$rec['period_month']] ?> <?= $rec['period_year'] ?>')">
                                                Revert Pending
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <svg width="64" height="64" fill="none" stroke="currentColor" stroke-width="1.5" opacity=".25">
                        <rect x="2" y="3" width="20" height="14" rx="2" />
                        <line x1="8" y1="21" x2="16" y2="21" />
                        <line x1="12" y1="17" x2="12" y2="21" />
                    </svg>
                    <p>No payroll records found for the selected filters.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- ══════════════════════════════════════════════════
         ADD PAYROLL MODAL (uses existing .modal / .modal-content CSS)
    ════════════════════════════════════════════════════ -->
    <div id="addPayrollModal" class="modal">
        <div class="modal-content" style="max-width:640px;">
            <div class="modal-header">
                <h2>Add Payroll Record</h2>
                <button class="modal-close" onclick="closeModal('addPayrollModal')">&times;</button>
            </div>
            <form method="POST" class="modal-body">
                <div class="form-group">
                    <label>Employee</label>
                    <select name="employee_id" class="form-control" required>
                        <option value="">-- Select Employee --</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                    <div class="form-group">
                        <label>Month</label>
                        <select name="period_month" class="form-control" required>
                            <?php foreach ($months as $num => $name): ?>
                                <option value="<?= $num ?>" <?= date('n') == $num ? 'selected' : '' ?>><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Year</label>
                        <input type="number" name="period_year" class="form-control" value="<?= date('Y') ?>" min="2020" max="2099" required>
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
                <div style="background:#f3f4f6;border-radius:10px;padding:1rem;margin:.75rem 0;display:grid;grid-template-columns:1fr 1fr 1fr;gap:.5rem;text-align:center;">
                    <div>
                        <div style="font-size:.7rem;color:var(--text-secondary);font-weight:600;text-transform:uppercase;">Gross Pay</div>
                        <div id="previewGross" style="font-size:1.1rem;font-weight:700;">₱0.00</div>
                    </div>
                    <div>
                        <div style="font-size:.7rem;color:var(--text-secondary);font-weight:600;text-transform:uppercase;">Deductions</div>
                        <div id="previewDeductions" style="font-size:1.1rem;font-weight:700;color:#dc2626;">₱0.00</div>
                    </div>
                    <div>
                        <div style="font-size:.7rem;color:var(--text-secondary);font-weight:600;text-transform:uppercase;">Net Pay</div>
                        <div id="previewNet" style="font-size:1.1rem;font-weight:700;color:var(--primary);">₱0.00</div>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
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

    <!-- ── View Payroll Modal ── -->
    <div id="viewPayrollModal" class="modal">
        <div class="modal-content" style="max-width:520px;">
            <div class="modal-header">
                <h2>Payroll Details</h2>
                <button class="modal-close" onclick="closeModal('viewPayrollModal')">&times;</button>
            </div>
            <div id="viewPayrollBody" class="modal-body"></div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════
         MARK AS PAID CONFIRMATION MODAL
    ════════════════════════════════════════════════════ -->
    <div id="markPaidModal" class="modal-overlay">
        <div class="modal-panel">
            <div class="modal-head">
                <div>
                    <h3 class="modal-head-title">Mark as Paid</h3>
                    <p class="modal-head-sub">Confirm payroll payment</p>
                </div>
                <button class="modal-close-btn" onclick="closeOverlayModal('markPaidModal')">&times;</button>
            </div>
            <div class="modal-body-inner">
                <div class="info-box">
                    Mark payroll for <strong id="markPaidName">—</strong>
                    (<span id="markPaidPeriod">—</span>) as <strong style="color:#16a34a;">Paid</strong>?
                    <br><br>
                    This will record today as the payment date.
                </div>
            </div>
            <div class="modal-foot">
                <!-- The form targets this page; payroll_id is injected by JS -->
                <form method="POST" id="markPaidForm">
                    <input type="hidden" name="payroll_id" id="markPaidId" value="">
                    <input type="hidden" name="new_status" value="paid">
                    <button type="submit" name="toggle_status" class="btn-modal-primary">
                        Yes, Mark as Paid
                    </button>
                </form>
                <button class="btn-modal-outline" onclick="closeOverlayModal('markPaidModal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════
         REVERT TO PENDING CONFIRMATION MODAL
    ════════════════════════════════════════════════════ -->
    <div id="revertPendingModal" class="modal-overlay">
        <div class="modal-panel">
            <div class="modal-head">
                <div>
                    <h3 class="modal-head-title">Revert to Pending</h3>
                    <p class="modal-head-sub">Undo paid status</p>
                </div>
                <button class="modal-close-btn" onclick="closeOverlayModal('revertPendingModal')">&times;</button>
            </div>
            <div class="modal-body-inner">
                <div class="info-box">
                    Revert payroll for <strong id="revertName">—</strong>
                    (<span id="revertPeriod">—</span>) back to <strong style="color:#d97706;">Pending</strong>?
                    <br><br>
                    This will clear the recorded payment date.
                </div>
            </div>
            <div class="modal-foot">
                <form method="POST" id="revertPendingForm">
                    <input type="hidden" name="payroll_id" id="revertId" value="">
                    <input type="hidden" name="new_status" value="pending">
                    <button type="submit" name="toggle_status" class="btn-modal-primary">
                        ↩ Yes, Revert to Pending
                    </button>
                </form>
                <button class="btn-modal-outline" onclick="closeOverlayModal('revertPendingModal')">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        const months = <?= json_encode($months) ?>;
        const monthsShort = <?= json_encode(array_values($months_short)) ?>;

        Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";
        Chart.defaults.font.size = 12;

        // ── Monthly Trend ──
        (function() {
            const raw = <?= json_encode($monthly_raw) ?>;
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
                            backgroundColor: 'rgba(15,58,64,.15)',
                            borderColor: 'rgba(15,58,64,.4)',
                            borderWidth: 1,
                            borderRadius: 4,
                            type: 'bar'
                        },
                        {
                            label: 'Net Pay',
                            data: netData,
                            borderColor: '#0F3A40',
                            backgroundColor: 'rgba(15,58,64,.08)',
                            borderWidth: 2.5,
                            tension: .4,
                            fill: true,
                            pointBackgroundColor: '#D9FF00',
                            pointBorderColor: '#0F3A40',
                            pointRadius: 4,
                            type: 'line'
                        }
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
                                color: 'rgba(0,0,0,.05)'
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

        // ── Paid vs Pending Donut ──
        (function() {
            const paid = <?= (int)($stats['count_paid']    ?? 0) ?>;
            const pending = <?= (int)($stats['count_pending'] ?? 0) ?>;
            new Chart(document.getElementById('chartStatus'), {
                type: 'doughnut',
                data: {
                    labels: ['Paid', 'Pending'],
                    datasets: [{
                        data: [paid, pending],
                        backgroundColor: ['#16a34a', '#d97706'],
                        borderColor: ['#fff', '#fff'],
                        borderWidth: 3,
                        hoverOffset: 8
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

        // ── Deductions Bar ──
        (function() {
            const vals = [<?= (float)($stats['total_sss'] ?? 0) ?>, <?= (float)($stats['total_ph'] ?? 0) ?>, <?= (float)($stats['total_pi'] ?? 0) ?>, <?= (float)($stats['total_tax'] ?? 0) ?>, <?= (float)($stats['total_other'] ?? 0) ?>];
            new Chart(document.getElementById('chartDeductions'), {
                type: 'bar',
                data: {
                    labels: ['SSS', 'PhilHealth', 'Pag-IBIG', 'W/H Tax', 'Other'],
                    datasets: [{
                        label: 'Amount (₱)',
                        data: vals,
                        backgroundColor: ['#0F3A40', '#1C4F50', '#2d6b6e', '#d97706', '#9ca3af'],
                        borderRadius: 6,
                        borderSkipped: false
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
                                color: 'rgba(0,0,0,.05)'
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

        // ── Earnings Donut ──
        (function() {
            const basic = <?= (float)($stats['total_basic']      ?? 0) ?>;
            const allow = <?= (float)($stats['total_allowances'] ?? 0) ?>;
            const ot = <?= (float)($stats['total_ot']         ?? 0) ?>;
            new Chart(document.getElementById('chartEarnings'), {
                type: 'doughnut',
                data: {
                    labels: ['Basic Salary', 'Allowances', 'Overtime'],
                    datasets: [{
                        data: [basic, allow, ot],
                        backgroundColor: ['#0F3A40', '#1C4F50', '#D9FF00'],
                        borderColor: ['#fff', '#fff', '#fff'],
                        borderWidth: 3,
                        hoverOffset: 8
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

        // ── Live payroll calculator ──
        function calcPayroll() {
            const g = id => parseFloat(document.querySelector(`[name="${id}"]`)?.value || 0);
            const gross = g('basic_salary') + g('allowances') + g('overtime_pay');
            const ded = g('sss_deduction') + g('philhealth_deduction') + g('pagibig_deduction') + g('tax_deduction') + g('other_deductions');
            const net = gross - ded;
            const fmt = v => '₱' + v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            document.getElementById('previewGross').textContent = fmt(gross);
            document.getElementById('previewDeductions').textContent = fmt(ded);
            document.getElementById('previewNet').textContent = fmt(net);
        }

        // ── View payroll detail ──
        function viewPayroll(rec) {
            const fmt = v => '₱' + parseFloat(v).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            const ded = parseFloat(rec.sss_deduction) + parseFloat(rec.philhealth_deduction) +
                parseFloat(rec.pagibig_deduction) + parseFloat(rec.tax_deduction) + parseFloat(rec.other_deductions);
            const row = (l, v, s = '') => `<div class="detail-row"><strong>${l}:</strong><span style="${s}">${v}</span></div>`;
            document.getElementById('viewPayrollBody').innerHTML = `
                <div class="inquiry-detail">
                    ${row('Employee', (rec.first_name||'')+' '+(rec.last_name||''))}
                    ${row('Email', rec.email||'N/A')}
                    ${row('Period', months[rec.period_month]+' '+rec.period_year)}
                    ${row('Status', `<span class="status ${rec.status==='paid'?'status--success':'status--warning'}">${rec.status.charAt(0).toUpperCase()+rec.status.slice(1)}</span>`)}
                    ${row('Basic Salary', fmt(rec.basic_salary))}
                    ${row('Allowances', fmt(rec.allowances))}
                    ${row('Overtime Pay', fmt(rec.overtime_pay))}
                    ${row('Gross Pay', fmt(rec.gross_pay), 'font-weight:700;')}
                    <div class="detail-row full" style="border-top:1px dashed var(--border-color);padding-top:.5rem;margin-top:.25rem;">
                        <strong style="color:#dc2626;">Deductions</strong>
                    </div>
                    ${row('SSS', fmt(rec.sss_deduction))}
                    ${row('PhilHealth', fmt(rec.philhealth_deduction))}
                    ${row('Pag-IBIG', fmt(rec.pagibig_deduction))}
                    ${row('Withholding Tax', fmt(rec.tax_deduction))}
                    ${row('Other Deductions', fmt(rec.other_deductions))}
                    ${row('Total Deductions', fmt(ded), 'color:#dc2626;font-weight:700;')}
                    ${row('Net Pay', fmt(rec.net_pay), 'color:var(--primary);font-weight:700;font-size:1.1rem;')}
                    ${rec.paid_at ? row('Paid At', new Date(rec.paid_at).toLocaleString()) : ''}
                    ${rec.notes ? `<div class="detail-row full"><strong>Notes:</strong><p style="margin-top:.3rem;">${rec.notes}</p></div>` : ''}
                </div>`;
            document.getElementById('viewPayrollModal').classList.add('active');
        }

        // ── Status modal helpers ──────────────────────────────────────────────────
        // Opens the correct confirmation modal and injects the payroll ID + label
        function openStatusModal(modalId, payrollId, employeeName, period) {
            if (modalId === 'markPaidModal') {
                document.getElementById('markPaidId').value = payrollId;
                document.getElementById('markPaidName').textContent = employeeName;
                document.getElementById('markPaidPeriod').textContent = period;
            } else {
                document.getElementById('revertId').value = payrollId;
                document.getElementById('revertName').textContent = employeeName;
                document.getElementById('revertPeriod').textContent = period;
            }
            document.getElementById(modalId).classList.add('active');
        }

        function closeOverlayModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        // Close overlay modals when clicking the backdrop
        document.querySelectorAll('.modal-overlay').forEach(el => {
            el.addEventListener('click', function(e) {
                if (e.target === this) this.classList.remove('active');
            });
        });

        // ── Legacy modal helpers (for add/view which use .modal class) ──
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }
        window.onclick = e => {
            if (e.target.classList.contains('modal')) e.target.classList.remove('active');
        };

        // Close any overlay modal on Escape
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(el => el.classList.remove('active'));
            }
        });
    </script>
</body>

</html>