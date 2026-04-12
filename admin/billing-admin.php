<?php
session_start();
require_once '../connection/dbconn.php';
require_once '../includes/auth.php';
require_once '../includes/activity_logger.php';

requireAdmin();

$message      = '';
$message_type = '';

// ── Quick inline status update ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_update_status'])) {
    $bill_id    = (int)($_POST['bill_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? '';
    $allowed    = ['unpaid', 'pending', 'paid', 'cancelled'];

    if ($bill_id > 0 && in_array($new_status, $allowed, true)) {
        $stmt = $conn->prepare("UPDATE billings SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $bill_id);
        if ($stmt->execute()) {
            logActivity(
                $_SESSION['user_id'],
                'admin',
                'invoice_status_updated',
                "Invoice ID #{$bill_id} status changed to {$new_status}"
            );
            $message      = "Invoice #{$bill_id} status updated to <strong>" . ucfirst($new_status) . "</strong>.";
            $message_type = 'success';
        } else {
            $message      = "Failed to update invoice status.";
            $message_type = 'error';
        }
    }
}

// ── Filters ────────────────────────────────────────────────────────────────
$search     = trim($_GET['search']    ?? '');
$filterStat = trim($_GET['status']    ?? '');
$dateFrom   = trim($_GET['date_from'] ?? '');
$dateTo     = trim($_GET['date_to']   ?? '');

// ── Fetch billing records ──────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];
$types  = '';

if ($search !== '') {
    $like     = '%' . $conn->real_escape_string($search) . '%';
    $where[]  = "(invoice_number LIKE ? OR client_name LIKE ?)";
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}
if ($filterStat !== '' && in_array($filterStat, ['unpaid', 'pending', 'paid', 'cancelled'], true)) {
    $where[]  = "status = ?";
    $params[] = $filterStat;
    $types   .= 's';
}
if ($dateFrom !== '') {
    $where[] = "DATE(created_at) >= ?";
    $params[] = $dateFrom;
    $types .= 's';
}
if ($dateTo   !== '') {
    $where[] = "DATE(created_at) <= ?";
    $params[] = $dateTo;
    $types .= 's';
}

$whereClause = implode(' AND ', $where);
$stmt        = $conn->prepare("SELECT * FROM billings WHERE {$whereClause} ORDER BY created_at DESC");
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$billings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Global aggregates ──────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT status, COUNT(*) as c, COALESCE(SUM(total_amount),0) as s FROM billings GROUP BY status");
$stmt->execute();
$allStats = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $allStats[$r['status']] = $r;
$totalInvoices = array_sum(array_column($allStats, 'c'));

// ── Pending inquiries badge ────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT COUNT(*) as c FROM inquiries WHERE status = 'pending'");
$stmt->execute();
$pending_inquiries = $stmt->get_result()->fetch_assoc()['c'];

// ── Admin info ─────────────────────────────────────────────────────────────
$is_emp = isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'employee';
$tbl    = $is_emp ? 'employees' : 'users';
$s = $conn->prepare("SELECT * FROM {$tbl} WHERE id = ?");
$s->bind_param("i", $_SESSION['user_id']);
$s->execute();
$admin = $s->get_result()->fetch_assoc();
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
    <title>Billing – JRN Admin</title>
    <link rel="stylesheet" href="assets/css/index-admin.css">
    <link rel="stylesheet" href="assets/css/billing-admin.css">
    <link rel="stylesheet" href="assets/css/logout-modal.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
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

        /* ═══════════════════════════════════════════
           SAVE CONFIRMATION MODAL
        ═══════════════════════════════════════════ */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(6px);
            z-index: 2000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.22s ease, visibility 0.22s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-overlay.active .modal-box {
            transform: translateY(0) scale(1);
            opacity: 1;
        }

        .modal-box {
            background: #fff;
            border-radius: var(--r-xl);
            box-shadow: var(--shadow-xl);
            width: 100%;
            max-width: 420px;
            padding: 2rem;
            transform: translateY(20px) scale(0.97);
            opacity: 0;
            transition: transform 0.28s cubic-bezier(0.34, 1.2, 0.64, 1), opacity 0.22s ease;
        }

        .modal-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.1rem;
            font-size: 1.4rem;
        }

        .modal-icon.confirm {
            background: var(--primary-light);
        }

        .modal-icon.success {
            background: var(--green-soft);
        }

        .modal-box h3 {
            font-size: 1.1rem;
            font-weight: 700;
            text-align: center;
            margin: 0 0 0.4rem;
        }

        .modal-box p {
            font-size: 0.86rem;
            color: var(--text-secondary);
            text-align: center;
            margin: 0 0 1.5rem;
            line-height: 1.5;
        }

        .modal-foot {
            display: flex;
            gap: 0.6rem;
        }

        .modal-btn {
            flex: 1;
            height: 42px;
            border-radius: var(--r-sm);
            font-size: 0.88rem;
            font-weight: 700;
            cursor: pointer;
            border: none;
            font-family: 'DM Sans', sans-serif;
            transition: all 0.15s;
        }

        .modal-btn.primary {
            background: var(--primary);
            color: #fff;
        }

        .modal-btn.primary:hover {
            background: var(--primary-mid);
            box-shadow: 0 3px 12px rgba(15, 58, 64, 0.28);
        }

        .modal-btn.outline {
            background: #fff;
            color: var(--text-primary);
            border: 1.5px solid var(--border-color);
        }

        .modal-btn.outline:hover {
            background: var(--bg-secondary);
        }

        .modal-btn.danger {
            background: #fff;
            color: var(--red);
            border: 1.5px solid var(--red-border);
        }

        .modal-btn.danger:hover {
            background: var(--red-soft);
        }

        /* Invoice detail chip */
        .modal-invoice-chip {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--r-md);
            padding: 0.75rem 1rem;
            text-align: center;
            margin-bottom: 1.25rem;
        }

        .mic-inv {
            font-family: 'DM Mono', monospace;
            font-size: 0.88rem;
            color: var(--primary);
            font-weight: 600;
        }

        .mic-from {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        .mic-arrow {
            font-size: 1rem;
            color: var(--accent);
            margin: 0 0.4rem;
        }

        .mic-to {
            font-weight: 700;
            color: var(--text-primary);
        }
    </style>
</head>

<body>

    <!-- ── Sidebar ───────────────────────────────────────────────────────────── -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <img src="../assets/img/logo.jpg" alt="Logo" class="logo-small">
                <h2>JRN Admin</h2>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="index-admin.php" class="nav-item"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7" />
                    <rect x="14" y="3" width="7" height="7" />
                    <rect x="14" y="14" width="7" height="7" />
                    <rect x="3" y="14" width="7" height="7" />
                </svg>Dashboard</a>
            <a href="inquiries-admin.php" class="nav-item"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                </svg>Inquiries<?php if ($pending_inquiries > 0): ?><span class="badge"><?= $pending_inquiries ?></span><?php endif; ?></a>
            <?php if (isAdmin()): ?>
                <a href="billing-admin.php" class="nav-item active"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="6" rx="1" />
                        <rect x="3" y="12" width="18" height="8" rx="1" />
                        <line x1="7" y1="16" x2="11" y2="16" />
                        <line x1="7" y1="19" x2="15" y2="19" />
                    </svg>Billing</a>
            <?php endif; ?>
            <a href="users-admin.php" class="nav-item"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="7" r="4" />
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                </svg>Users</a>
            <?php if (isAdmin()): ?>
                <a href="employees-admin.php" class="nav-item"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                        <circle cx="12" cy="7" r="4" />
                    </svg>Employees</a>
                <a href="activity-logs.php" class="nav-item"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                        <polyline points="14 2 14 8 20 8" />
                        <line x1="16" y1="13" x2="8" y2="13" />
                        <line x1="16" y1="17" x2="8" y2="17" />
                    </svg>Activity Logs</a>
                <a href="services-admin.php" class="nav-item"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="6" width="18" height="12" rx="2" />
                        <path d="M3 10h18" />
                    </svg>Manage Services</a>
                <a href="payroll-reports-admin.php" class="nav-item"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" />
                        <line x1="8" y1="21" x2="16" y2="21" />
                        <line x1="12" y1="17" x2="12" y2="21" />
                    </svg>Payroll Reports</a>
            <?php endif; ?>
            <div style="margin-top:auto;padding-top:1rem;border-top:1px solid var(--border-color);">
                <a href="admin-account.php" class="nav-item"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3" />
                        <path d="M12 1v6m0 6v6m6-11h-6m-6 0H1m18.4-3.6l-4.2 4.2m-8.4 0l-4.2-4.2M18.4 18.4l-4.2-4.2m-8.4 0l-4.2 4.2" />
                    </svg>My Account</a>
            </div>
        </nav>
        <div class="sidebar-footer">
            <a href="#" class="nav-item logout" id="logout-btn"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                    <polyline points="16 17 21 12 16 7" />
                    <line x1="21" y1="12" x2="9" y2="12" />
                </svg>Logout</a>
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

    <!-- ── Main ──────────────────────────────────────────────────────────────── -->
    <main class="main-content">
        <header class="admin-header">
            <div class="admin-header-left">
                <h1>Billing</h1>
                <p class="header-subtitle">Manage invoices and track payments</p>
            </div>
            <div class="admin-header-right">
                <a href="billing-add.php" class="btn btn--primary" style="display:inline-flex;align-items:center;gap:0.4rem;">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="12" y1="5" x2="12" y2="19" />
                        <line x1="5" y1="12" x2="19" y2="12" />
                    </svg>
                    New Invoice
                </a>
                <div class="avatar-circle"><?= strtoupper(substr($admin['first_name'] ?? 'A', 0, 1)) ?></div>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert--<?= $message_type ?>"><?= $message ?></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="alert alert--success"><?= $_SESSION['success'];
                                                unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <!-- ── Stats strip ────────────────────────────────────────────────────── -->
        <div class="stats-strip">
            <div class="stat-card c-total">
                <p class="stat-label">Total Invoices</p>
                <div class="stat-value"><?= $totalInvoices ?></div>
                <div class="stat-sub">All time</div>
            </div>
            <div class="stat-card c-paid">
                <p class="stat-label">Paid</p>
                <div class="stat-value v-paid"><?= $allStats['paid']['c'] ?? 0 ?></div>
                <div class="stat-sub">Invoices settled</div>
            </div>
            <div class="stat-card c-unpaid">
                <p class="stat-label">Unpaid</p>
                <div class="stat-value v-unpaid"><?= $allStats['unpaid']['c'] ?? 0 ?></div>
                <div class="stat-sub">Awaiting payment</div>
            </div>
            <div class="stat-card c-pending">
                <p class="stat-label">Pending</p>
                <div class="stat-value v-pending"><?= $allStats['pending']['c'] ?? 0 ?></div>
                <div class="stat-sub">Under review</div>
            </div>
            <div class="stat-card c-revenue">
                <p class="stat-label">Revenue (Paid)</p>
                <div class="stat-value v-revenue">₱<?= number_format((float)($allStats['paid']['s'] ?? 0), 2) ?></div>
                <div class="stat-sub">Total collected</div>
            </div>
            <div class="stat-card c-unpaid">
                <p class="stat-label">Outstanding</p>
                <div class="stat-value v-outstanding">₱<?= number_format((float)($allStats['unpaid']['s'] ?? 0), 2) ?></div>
                <div class="stat-sub">Unpaid balance</div>
            </div>
        </div>

        <!-- ── Status quick-tabs ─────────────────────────────────────────────── -->
        <div class="status-tabs">
            <?php
            $tabDefs = [
                ''          => ['All', $totalInvoices],
                'unpaid'    => ['Unpaid',    $allStats['unpaid']['c']    ?? 0],
                'pending'   => ['Pending',   $allStats['pending']['c']   ?? 0],
                'paid'      => ['Paid',      $allStats['paid']['c']      ?? 0],
                'cancelled' => ['Cancelled', $allStats['cancelled']['c'] ?? 0],
            ];
            foreach ($tabDefs as $v => [$l, $cnt]):
                $qs  = http_build_query(['search' => $search, 'status' => $v, 'date_from' => $dateFrom, 'date_to' => $dateTo]);
                $act = $filterStat === $v ? 'active' : '';
            ?>
                <a href="?<?= $qs ?>" class="stab <?= $act ?>">
                    <?= $l ?>
                    <span class="stab-count"><?= $cnt ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- ── Main billing card ─────────────────────────────────────────────── -->
        <div class="card">
            <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;">
                <h2 style="display:flex;align-items:center;gap:0.6rem;">
                    <?php if ($filterStat): ?>
                        <span class="bill-badge bb-<?= $filterStat ?>"><?= ucfirst($filterStat) ?></span>
                    <?php endif; ?>
                    Billing Records
                </h2>
                <span style="font-size:0.8rem;color:var(--text-muted);font-weight:500;">
                    <?= count($billings) ?> result<?= count($billings) !== 1 ? 's' : '' ?>
                </span>
            </div>

            <!-- Search + Date filter -->
            <div style="padding:0.85rem 1.25rem;border-bottom:1px solid var(--border-color);">
                <form method="GET" class="filter-row">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($filterStat) ?>">
                    <div class="filter-field">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Invoice # or client name…"
                            value="<?= htmlspecialchars($search) ?>" style="min-width:220px;">
                    </div>
                    <div class="filter-field">
                        <label>From</label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                    </div>
                    <div class="filter-field">
                        <label>To</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                    </div>
                    <div style="display:flex;gap:0.45rem;align-self:flex-end;">
                        <button type="submit" class="btn btn--primary btn--sm" style="height:38px;">
                            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:0.2rem;">
                                <circle cx="11" cy="11" r="8" />
                                <path d="m21 21-4.35-4.35" />
                            </svg>
                            Search
                        </button>
                        <?php if ($search || $dateFrom || $dateTo): ?>
                            <a href="?status=<?= $filterStat ?>" class="btn btn--outline btn--sm" style="height:38px;display:inline-flex;align-items:center;">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <?php if (count($billings) > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Client</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($billings as $row):
                                $status = $row['status'] ?? 'unpaid';
                            ?>
                                <tr>
                                    <td><code class="inv-num"><?= htmlspecialchars($row['invoice_number'] ?? '#' . $row['id']) ?></code></td>
                                    <td>
                                        <span style="font-weight:600;font-size:0.85rem;"><?= htmlspecialchars($row['client_name'] ?? 'N/A') ?></span>
                                    </td>
                                    <td>
                                        <span style="font-weight:700;font-size:0.95rem;color:var(--primary);">
                                            ₱<?= number_format((float)($row['total_amount'] ?? 0), 2) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="bill-badge bb-<?= $status ?>"><?= ucfirst($status) ?></span>
                                    </td>
                                    <td style="white-space:nowrap;font-size:0.82rem;color:var(--text-secondary);">
                                        <?= isset($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : '—' ?>
                                    </td>
                                    <td>
                                        <div class="action-row">
                                            <select class="action-select" id="sel_<?= $row['id'] ?>">
                                                <?php foreach (['unpaid' => 'Unpaid', 'pending' => 'Pending', 'paid' => 'Paid', 'cancelled' => 'Cancelled'] as $v => $l): ?>
                                                    <option value="<?= $v ?>" <?= $status === $v ? 'selected' : '' ?>><?= $l ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="btn-save"
                                                onclick="openSaveModal(<?= $row['id'] ?>,
                                        '<?= htmlspecialchars($row['invoice_number'] ?? '#' . $row['id'], ENT_QUOTES) ?>',
                                        '<?= $status ?>',
                                        '<?= htmlspecialchars($row['client_name'] ?? 'N/A', ENT_QUOTES) ?>')">
                                                <svg width="28" height="24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                    <polyline points="20 6 9 17 4 12" />
                                                </svg>
                                                Save
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <svg width="72" height="72" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 64 64">
                        <rect x="8" y="12" width="48" height="40" rx="4" />
                        <line x1="16" y1="24" x2="48" y2="24" />
                        <line x1="16" y1="34" x2="36" y2="34" />
                        <line x1="16" y1="42" x2="30" y2="42" />
                    </svg>
                    <p>No billing records match your filters.</p>
                    <a href="billing-admin.php" class="btn btn--outline">Clear All Filters</a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- ══════════════════════════════════════════════════
     SAVE STATUS CONFIRMATION MODAL
════════════════════════════════════════════════════ -->
    <div id="saveModal" class="modal-overlay" onclick="if(event.target===this)closeSaveModal()">
        <div class="modal-box">
            <div class="modal-icon confirm">
                <svg width="24" height="24" fill="none" stroke="var(--primary)" stroke-width="2.5">
                    <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z" />
                    <path d="M12 8v4l3 3" />
                </svg>
            </div>
            <h3>Confirm Status Change</h3>
            <p>You're about to update this invoice's payment status.</p>
            <div class="modal-invoice-chip">
                <div class="mic-inv" id="modalInvNum">—</div>
                <div class="mic-from">
                    <span id="modalClientName" style="color:var(--text-secondary);font-size:0.78rem;"></span>
                </div>
                <div style="margin-top:0.5rem;font-size:0.82rem;">
                    <span id="modalFromBadge"></span>
                    <span class="mic-arrow">→</span>
                    <span id="modalToBadge"></span>
                </div>
            </div>

            <div class="modal-foot">
                <button class="modal-btn outline" onclick="closeSaveModal()">Cancel</button>
                <button class="modal-btn primary" id="modalConfirmBtn">Confirm Update</button>
            </div>
        </div>
    </div>

    <!-- Hidden form for status update submit -->
    <form method="POST" id="statusUpdateForm" style="display:none;">
        <input type="hidden" name="bill_id" id="formBillId">
        <input type="hidden" name="new_status" id="formNewStatus">
        <input type="hidden" name="quick_update_status" value="1">
    </form>

    <script>
        let _pendingBillId = null;
        let _pendingStatus = null;

        const badgeHtml = {
            paid: '<span class="bill-badge bb-paid">Paid</span>',
            unpaid: '<span class="bill-badge bb-unpaid">Unpaid</span>',
            pending: '<span class="bill-badge bb-pending">Pending</span>',
            cancelled: '<span class="bill-badge bb-cancelled">Cancelled</span>',
        };

        function openSaveModal(billId, invNum, currentStatus, clientName) {
            const sel = document.getElementById('sel_' + billId);
            const newStatus = sel ? sel.value : currentStatus;

            _pendingBillId = billId;
            _pendingStatus = newStatus;

            document.getElementById('modalInvNum').textContent = invNum;
            document.getElementById('modalClientName').textContent = clientName;
            document.getElementById('modalFromBadge').innerHTML = badgeHtml[currentStatus] || currentStatus;
            document.getElementById('modalToBadge').innerHTML = badgeHtml[newStatus] || newStatus;

            document.getElementById('modalConfirmBtn').onclick = function() {
                document.getElementById('formBillId').value = _pendingBillId;
                document.getElementById('formNewStatus').value = _pendingStatus;
                document.getElementById('statusUpdateForm').submit();
            };

            document.getElementById('saveModal').classList.add('active');
        }

        function closeSaveModal() {
            document.getElementById('saveModal').classList.remove('active');
        }

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeSaveModal();
        });
    </script>
</body>

</html>