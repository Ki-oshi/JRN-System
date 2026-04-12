<?php
session_start();
require_once '../connection/dbconn.php';
require_once '../includes/auth.php';
require_once '../includes/activity_logger.php';

requireAdmin();

$message      = '';
$message_type = '';

$proc_labels = [
    'standard' => ['label' => 'Standard',  'cls' => 'proc-standard'],
    'priority' => ['label' => 'Priority',  'cls' => 'proc-priority'],
    'express'  => ['label' => 'Express',   'cls' => 'proc-express'],
    'rush'     => ['label' => 'Rush',      'cls' => 'proc-rush'],
    'same_day' => ['label' => 'Same-Day',  'cls' => 'proc-same_day'],
];

// ── Handle status update ───────────────────────────────────────────────────
if (isset($_POST['update_status'])) {
    $inquiry_id       = (int)$_POST['inquiry_id'];
    $status           = $_POST['status'];
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');

    $inq = $conn->prepare("
        SELECT i.inquiry_number, i.service_name, i.status as old_status,
               u.first_name, u.last_name, u.email
        FROM inquiries i LEFT JOIN users u ON i.user_id = u.id
        WHERE i.id = ?
    ");
    $inq->bind_param("i", $inquiry_id);
    $inq->execute();
    $inq_data = $inq->get_result()->fetch_assoc();

    if (!$inq_data) {
        $message = "Inquiry not found.";
        $message_type = "error";
    } elseif ($inq_data['old_status'] === 'rejected') {
        $message = "Rejected inquiries are final and cannot be modified.";
        $message_type = "error";
    } elseif ($status === 'rejected' && empty($rejection_reason)) {
        $message = "A rejection reason is required.";
        $message_type = "error";
    } else {
        if ($status === 'rejected') {
            $s = $conn->prepare("UPDATE inquiries SET status=?, rejection_reason=?, updated_at=NOW() WHERE id=? AND status!='rejected'");
            $s->bind_param("ssi", $status, $rejection_reason, $inquiry_id);
        } else {
            $s = $conn->prepare("UPDATE inquiries SET status=?, rejection_reason=NULL, updated_at=NOW() WHERE id=? AND status!='rejected'");
            $s->bind_param("si", $status, $inquiry_id);
        }
        if ($s->execute() && $s->affected_rows > 0) {
            $message = "Inquiry status updated successfully.";
            $message_type = "success";
            $utype   = (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'employee') ? 'employee' : 'admin';
            $uinfo   = $inq_data['first_name'] ? "{$inq_data['first_name']} {$inq_data['last_name']}" : $inq_data['email'];
            $log     = "Updated inquiry #{$inq_data['inquiry_number']} for {$uinfo} — "
                . "Status: '" . ucfirst($inq_data['old_status']) . "' → '" . ucfirst($status) . "'";
            if ($status === 'rejected' && $rejection_reason) $log .= " — Reason: " . substr($rejection_reason, 0, 100);
            logActivity($_SESSION['user_id'], $utype, 'inquiry_status_updated', $log);
        } elseif ($s->affected_rows === 0) {
            $message = "Cannot modify a rejected inquiry.";
            $message_type = "error";
        } else {
            $message = "Error updating inquiry status.";
            $message_type = "error";
        }
    }
}

// ── Filters ────────────────────────────────────────────────────────────────
$filter      = $_GET['filter']   ?? 'all';
$proc_filter = $_GET['proc']     ?? '';
$search      = trim($_GET['search'] ?? '');

$allowed_filters = ['all', 'pending', 'in_review', 'completed', 'rejected'];
if (!in_array($filter, $allowed_filters)) $filter = 'all';

$allowed_procs = ['standard', 'priority', 'express', 'rush', 'same_day'];
if (!in_array($proc_filter, $allowed_procs)) $proc_filter = '';

// ── Pending bills badge ────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT COUNT(*) as c FROM billings WHERE status='unpaid'");
$stmt->execute();
$pending_bills = $stmt->get_result()->fetch_assoc()['c'];

// ── Build query ────────────────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];
$types  = '';

if ($filter !== 'all') {
    $where[]  = "i.status = ?";
    $params[] = $filter;
    $types   .= 's';
}
if ($proc_filter !== '') {
    $where[]  = "i.processing_type = ?";
    $params[] = $proc_filter;
    $types   .= 's';
}
if ($search !== '') {
    $like     = '%' . $conn->real_escape_string($search) . '%';
    $where[]  = "(i.inquiry_number LIKE ? OR i.service_name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $params   = array_merge($params, [$like, $like, $like, $like, $like]);
    $types   .= 'sssss';
}

$whereSQL = implode(' AND ', $where);
$stmt     = $conn->prepare("
    SELECT i.*,
           u.first_name, u.last_name, u.email, u.phone, u.account_number,
           COUNT(d.id) as document_count
    FROM inquiries i
    LEFT JOIN users u ON i.user_id = u.id
    LEFT JOIN inquiry_documents d ON i.id = d.inquiry_id
    WHERE {$whereSQL}
    GROUP BY i.id
    ORDER BY i.created_at DESC
");
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$inquiries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Status counts ──────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT status, COUNT(*) as c FROM inquiries GROUP BY status");
$stmt->execute();
$status_counts = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $status_counts[$r['status']] = $r['c'];
$total_inquiries = array_sum($status_counts);

// ── Processing type counts ─────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT processing_type, COUNT(*) as c FROM inquiries GROUP BY processing_type");
$stmt->execute();
$proc_counts = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $proc_counts[$r['processing_type']] = $r['c'];

// ── Admin info ─────────────────────────────────────────────────────────────
$is_emp = isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'employee';
$tbl    = $is_emp ? 'employees' : 'users';
$s      = $conn->prepare("SELECT * FROM {$tbl} WHERE id = ?");
$s->bind_param("i", $_SESSION['user_id']);
$s->execute();
$admin = $s->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inquiries – JRN Admin</title>
    <link rel="stylesheet" href="assets/css/index-admin.css">
    <link rel="stylesheet" href="assets/css/inquiries-admin.css">
    <link rel="stylesheet" href="assets/css/logout-modal.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
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

        /* ═══════════════════════════════════════════
           MODAL SYSTEM — Completely Redesigned
        ═══════════════════════════════════════════ */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s ease, visibility 0.25s ease;
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
            max-height: 92vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transform: translateY(24px) scale(0.97);
            opacity: 0;
            transition: transform 0.3s cubic-bezier(0.34, 1.2, 0.64, 1), opacity 0.25s ease;
        }

        .modal-panel--view {
            max-width: 800px;
        }

        .modal-panel--update {
            max-width: 540px;
        }

        /* Modal header */
        .modal-head {
            padding: 1.5rem 1.75rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            flex-shrink: 0;
        }

        .modal-head-info {}

        .modal-head-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 0.25rem;
            line-height: 1.3;
        }

        .modal-head-sub {
            font-size: 0.8rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
            transition: all 0.15s;
            line-height: 1;
        }

        .modal-close-btn:hover {
            background: #fee2e2;
            color: #dc2626;
        }

        /* Modal tabs */
        .modal-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            padding: 0 1.75rem;
            flex-shrink: 0;
        }

        .modal-tab {
            padding: 0.9rem 1.1rem 0.85rem;
            font-size: 0.84rem;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 2.5px solid transparent;
            margin-bottom: -1px;
            color: var(--text-secondary);
            transition: color 0.15s, border-color 0.15s;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .modal-tab:hover {
            color: var(--text-primary);
        }

        .modal-tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .tab-count {
            font-size: 0.68rem;
            background: var(--bg-secondary);
            color: var(--text-secondary);
            border-radius: 999px;
            padding: 0.1rem 0.5rem;
            font-weight: 700;
        }

        .modal-tab.active .tab-count {
            background: var(--primary-xlight);
            color: var(--primary);
        }

        /* Modal body */
        .modal-body {
            padding: 1.5rem 1.75rem;
            overflow-y: auto;
            flex: 1;
        }

        .modal-panel-content {
            display: none;
        }

        .modal-panel-content.active {
            display: block;
        }

        /* Modal footer */
        .modal-foot {
            padding: 1.1rem 1.75rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 0.65rem;
            justify-content: flex-end;
            flex-shrink: 0;
            background: var(--bg-secondary);
        }

        .btn-modal-primary {
            height: 40px;
            padding: 0 1.4rem;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-family: 'DM Sans', sans-serif;
        }

        .btn-modal-primary:hover {
            background: var(--primary-light);
            box-shadow: 0 3px 12px rgba(15, 58, 64, 0.3);
        }

        .btn-modal-primary:disabled {
            opacity: 0.45;
            cursor: not-allowed;
            box-shadow: none;
        }

        .btn-modal-outline {
            height: 40px;
            padding: 0 1.2rem;
            background: #fff;
            color: var(--text-primary);
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
            font-family: 'DM Sans', sans-serif;
        }

        .btn-modal-outline:hover {
            border-color: var(--border-strong);
            background: var(--bg-secondary);
        }

        /* ── Detail grid in view modal ── */
        .detail-section-title {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-muted);
            margin: 0 0 0.85rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem 1.5rem;
            margin-bottom: 1.5rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .detail-item.full {
            grid-column: 1 / -1;
        }

        .di-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--text-muted);
        }

        .di-value {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-primary);
            line-height: 1.4;
        }

        .price-main {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
        }

        .price-was {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-decoration: line-through;
            margin-top: 0.25rem;
        }

        .client-avatar {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .client-card {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
        }

        .client-card-info {}

        .client-card-name {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .client-card-meta {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 0.25rem;
        }

        .client-card-meta span {
            font-size: 0.78rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        /* Rejection box */
        .rejection-card {
            display: flex;
            gap: 0.85rem;
            padding: 1rem 1.1rem;
            background: #fff1f2;
            border: 1px solid #fecdd3;
            border-radius: var(--radius-md);
            margin-top: 0.5rem;
        }

        .rejection-icon {
            width: 32px;
            height: 32px;
            background: #fee2e2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: #dc2626;
            font-size: 0.9rem;
        }

        .rejection-card p {
            margin: 0;
            font-size: 0.88rem;
            color: #9f1239;
            font-weight: 500;
        }

        .rejection-card small {
            display: block;
            margin-top: 0.35rem;
            font-size: 0.72rem;
            color: #be123c;
            opacity: 0.8;
        }

        /* Notes card */
        .notes-card {
            padding: 0.9rem 1rem;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            color: var(--text-primary);
            white-space: pre-wrap;
            line-height: 1.6;
        }

        /* Document cards */
        .doc-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.85rem 1rem;
            background: #fff;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            margin-bottom: 0.6rem;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .doc-card:hover {
            border-color: var(--accent);
            box-shadow: 0 2px 12px rgba(47, 184, 196, 0.1);
        }

        .doc-card-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .doc-icon {
            width: 38px;
            height: 38px;
            background: var(--primary-xlight);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            flex-shrink: 0;
        }

        .doc-name {
            font-family: 'DM Mono', monospace;
            font-size: 0.8rem;
            color: var(--primary);
            font-weight: 500;
        }

        .doc-meta {
            font-size: 0.72rem;
            color: var(--text-secondary);
            margin-top: 0.15rem;
        }

        .doc-download {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            height: 32px;
            padding: 0 0.85rem;
            font-size: 0.77rem;
            font-weight: 600;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-sm);
            color: var(--primary);
            background: var(--primary-xlight);
            text-decoration: none;
            transition: all 0.15s;
        }

        .doc-download:hover {
            background: var(--primary);
            color: #fff;
            border-color: transparent;
        }

        .empty-docs {
            text-align: center;
            padding: 2.5rem 1rem;
            color: var(--text-muted);
        }

        .empty-docs svg {
            opacity: 0.35;
            margin-bottom: 0.75rem;
        }

        .empty-docs p {
            font-size: 0.88rem;
            margin: 0;
        }

        /* ── Update status modal ── */
        .status-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.65rem;
            margin-top: 0.75rem;
        }

        .status-card {
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 0.9rem 1rem;
            cursor: pointer;
            transition: all 0.18s;
            position: relative;
            background: #fff;
        }

        .status-card:hover {
            border-color: var(--accent);
            box-shadow: 0 2px 12px rgba(47, 184, 196, 0.1);
        }

        .status-card.selected {
            border-color: var(--primary);
            background: var(--primary-xlight);
            box-shadow: 0 0 0 1px var(--primary);
        }

        .status-card input[type="radio"] {
            display: none;
        }

        .status-card-check {
            position: absolute;
            top: 0.6rem;
            right: 0.6rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 2px solid var(--border-color);
            background: #fff;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .status-card.selected .status-card-check {
            background: var(--primary);
            border-color: var(--primary);
        }

        .status-card.selected .status-card-check::after {
            content: '';
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: #fff;
        }

        .sc-icon {
            font-size: 1.3rem;
            margin-bottom: 0.35rem;
        }

        .sc-label {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-primary);
            display: block;
        }

        .sc-desc {
            font-size: 0.73rem;
            color: var(--text-secondary);
            margin-top: 0.15rem;
            display: block;
        }

        .status-card[data-value="rejected"] .sc-label {
            color: #be123c;
        }

        /* Rejection reason textarea */
        .rejection-group {
            margin-top: 1.1rem;
            padding: 1rem;
            background: #fff1f2;
            border: 1px solid #fecdd3;
            border-radius: var(--radius-md);
        }

        .rejection-group label {
            font-size: 0.82rem;
            font-weight: 700;
            color: #9f1239;
            display: flex;
            align-items: center;
            gap: 0.35rem;
            margin-bottom: 0.6rem;
        }

        .rejection-group textarea {
            width: 100%;
            border: 1.5px solid #fecdd3;
            border-radius: var(--radius-sm);
            padding: 0.75rem;
            font-size: 0.85rem;
            resize: vertical;
            min-height: 100px;
            background: #fff;
            color: var(--text-primary);
            font-family: 'DM Sans', sans-serif;
            transition: border-color 0.15s;
        }

        .rejection-group textarea:focus {
            outline: none;
            border-color: #f87171;
            box-shadow: 0 0 0 3px rgba(248, 113, 113, 0.15);
        }

        .rejection-warning {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.73rem;
            color: #9f1239;
            margin-top: 0.5rem;
        }

        /* Current status display */
        .current-status-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.85rem 1rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            border: 1px solid var(--border-color);
        }

        .csb-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.07em;
        }

        /* Empty state */
        .empty-state {
            padding: 4rem 2rem;
            text-align: center;
        }

        .empty-state svg {
            opacity: 0.2;
            margin-bottom: 1rem;
        }

        .empty-state p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin: 0 0 1rem;
        }

        /* Filter card wrapper */
        .filter-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1rem 1.25rem;
            margin-bottom: 1.1rem;
            box-shadow: var(--shadow-sm);
        }

        /* Divider */
        .modal-divider {
            height: 1px;
            background: var(--border-color);
            margin: 1.25rem 0;
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
            <a href="index-admin.php" class="nav-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                Dashboard
            </a>
            <a href="inquiries-admin.php" class="nav-item active">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                Inquiries
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
                    <?php if ($pending_bills > 0): ?>
                        <span class="badge"><?= $pending_bills ?></span>
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
                    </svg>
                    Payroll Reports
                </a>
            <?php endif; ?>
            <div style="margin-top:auto;padding-top:1rem;border-top:1px solid var(--border-color);">
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
                <h1>Service Inquiries</h1>
                <p class="header-subtitle">Manage customer service requests and applications</p>
            </div>
            <div class="admin-header-right">
                <div class="avatar-circle"><?= strtoupper(substr($admin['first_name'] ?? 'A', 0, 1)) ?></div>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert--<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <!-- ── Status filter tabs ────────────────────────────────────────────── -->
        <div class="filter-tabs">
            <?php
            $tabData = ['all' => 'All', 'pending' => 'Pending', 'in_review' => 'In Review', 'completed' => 'Completed', 'rejected' => 'Rejected'];
            foreach ($tabData as $k => $l):
                $cnt = $k === 'all' ? $total_inquiries : ($status_counts[$k] ?? 0);
                $qs  = http_build_query(['filter' => $k, 'proc' => $proc_filter, 'search' => $search]);
            ?>
                <a href="?<?= $qs ?>" class="filter-tab <?= $filter === $k ? 'active' : '' ?>">
                    <?= $l ?> <span class="count"><?= $cnt ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- ── Filter card ──────────────────────────────────────────────────── -->
        <div class="filter-card">
            <div class="proc-filter-bar">
                <span class="pf-label">Processing:</span>
                <?php $allProcQs = http_build_query(['filter' => $filter, 'proc' => '', 'search' => $search]); ?>
                <a href="?<?= $allProcQs ?>" class="proc-btn <?= $proc_filter === '' ? 'active' : '' ?>">
                    All <span class="count">(<?= $total_inquiries ?>)</span>
                </a>
                <?php foreach ($proc_labels as $pk => $pm):
                    $cnt    = $proc_counts[$pk] ?? 0;
                    $procQs = http_build_query(['filter' => $filter, 'proc' => $pk, 'search' => $search]);
                ?>
                    <a href="?<?= $procQs ?>" class="proc-btn <?= $proc_filter === $pk ? 'active' : '' ?>">
                        <span class="proc-pill <?= $pm['cls'] ?>" style="padding:0.12rem 0.45rem;font-size:0.62rem;"><?= $pm['label'] ?></span>
                        <span class="count">(<?= $cnt ?>)</span>
                    </a>
                <?php endforeach; ?>
            </div>

            <form method="GET" class="search-bar">
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                <input type="hidden" name="proc" value="<?= htmlspecialchars($proc_filter) ?>">
                <input type="text" name="search"
                    placeholder="Search by inquiry #, service, or client name…"
                    value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn--primary btn--sm">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:0.2rem;">
                        <circle cx="11" cy="11" r="8" />
                        <path d="m21 21-4.35-4.35" />
                    </svg>
                    Search
                </button>
                <?php if ($search !== ''): ?>
                    <a href="?filter=<?= $filter ?>&proc=<?= $proc_filter ?>" class="btn btn--outline btn--sm">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- ── Inquiries table ───────────────────────────────────────────────── -->
        <div class="card">
            <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
                <h2 style="display:flex;align-items:center;gap:0.65rem;">
                    <?= ucfirst(str_replace('_', ' ', $filter)) ?> Inquiries
                    <?php if ($proc_filter): ?>
                        <span class="proc-pill <?= $proc_labels[$proc_filter]['cls'] ?>"><?= $proc_labels[$proc_filter]['label'] ?></span>
                    <?php endif; ?>
                </h2>
                <span style="font-size:0.8rem;color:var(--text-muted);font-weight:500;">
                    <?= count($inquiries) ?> result<?= count($inquiries) !== 1 ? 's' : '' ?>
                </span>
            </div>

            <?php if (count($inquiries) > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Inquiry #</th>
                                <th>Account #</th>
                                <th>Client</th>
                                <th>Service</th>
                                <th>Processing</th>
                                <th>Fee</th>
                                <th>Docs</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inquiries as $inquiry):
                                $pt     = $inquiry['processing_type'] ?? 'standard';
                                $ptMeta = $proc_labels[$pt] ?? ['label' => ucfirst($pt), 'cls' => 'proc-standard'];
                                $statusCls = match ($inquiry['status']) {
                                    'pending'   => 'status-pending',
                                    'in_review' => 'status-in_review',
                                    'completed' => 'status-completed',
                                    'rejected'  => 'status-rejected',
                                    default     => 'status-pending'
                                };
                            ?>
                                <tr>
                                    <td>
                                        <code class="inq-num"><?= htmlspecialchars($inquiry['inquiry_number'] ?? 'N/A') ?></code>
                                    </td>
                                    <td>
                                        <code class="acc-num"><?= htmlspecialchars($inquiry['account_number'] ?? '—') ?></code>
                                    </td>
                                    <td>
                                        <div class="client-cell">
                                            <strong><?= (!empty($inquiry['first_name']) && !empty($inquiry['last_name'])) ? htmlspecialchars($inquiry['first_name'] . ' ' . $inquiry['last_name']) : 'Unknown' ?></strong>
                                            <small><?= htmlspecialchars($inquiry['email'] ?? '—') ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <strong style="font-size:0.85rem;"><?= htmlspecialchars($inquiry['service_name']) ?></strong>
                                        <?php if (!empty($inquiry['additional_notes'])): ?>
                                            <p class="message-preview" style="font-size:0.74rem;color:var(--text-secondary);margin:0.1rem 0 0;"><?= htmlspecialchars(substr($inquiry['additional_notes'], 0, 40)) ?>…</p>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="proc-pill <?= $ptMeta['cls'] ?>"><?= $ptMeta['label'] ?></span>
                                    </td>
                                    <td>
                                        <div class="fee-cell">
                                            <strong>₱<?= number_format($inquiry['price'] ?? 0, 2) ?></strong>
                                            <?php if (!empty($inquiry['base_price']) && $inquiry['base_price'] != $inquiry['price']): ?>
                                                <small>₱<?= number_format($inquiry['base_price'], 2) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($inquiry['document_count'] > 0): ?>
                                            <span class="doc-badge">
                                                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                    <polyline points="14 2 14 8 20 8" />
                                                </svg>
                                                <?= $inquiry['document_count'] ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted);font-size:0.78rem;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= $statusCls ?>">
                                            <?= ucfirst(str_replace('_', ' ', $inquiry['status'])) ?>
                                        </span>
                                    </td>
                                    <td style="white-space:nowrap;font-size:0.82rem;color:var(--text-secondary);">
                                        <?= date('M d, Y', strtotime($inquiry['created_at'])) ?>
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:0.35rem;align-items:center;">
                                            <button class="btn-view" onclick="viewInquiry(<?= $inquiry['id'] ?>)" title="View details">
                                                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                    <circle cx="12" cy="12" r="3" />
                                                </svg>
                                                View
                                            </button>
                                            <?php if ($inquiry['status'] !== 'rejected'): ?>
                                                <button class="btn-update" onclick="openUpdateModal(<?= $inquiry['id'] ?>, '<?= $inquiry['status'] ?>')" title="Update status">
                                                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <polyline points="20 6 9 17 4 12" />
                                                    </svg>
                                                    Update
                                                </button>
                                            <?php else: ?>
                                                <span class="btn-closed" title="Permanently closed">Closed</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <svg width="72" height="72" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                    </svg>
                    <p>No inquiries match the current filters.</p>
                    <a href="inquiries-admin.php" class="btn btn--outline">Clear All Filters</a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- ═══════════════════════════════════════════════════
     VIEW INQUIRY MODAL
════════════════════════════════════════════════════ -->
    <div id="viewModal" class="modal-overlay" onclick="handleOverlayClick(event,'viewModal')">
        <div class="modal-panel modal-panel--view">

            <!-- Head -->
            <div class="modal-head">
                <div class="modal-head-info">
                    <h2 class="modal-head-title" id="viewModalTitle">Inquiry Details</h2>
                    <div class="modal-head-sub" id="viewModalSub">
                        <!-- filled by JS -->
                    </div>
                </div>
                <button class="modal-close-btn" onclick="closeModal('viewModal')" aria-label="Close">&times;</button>
            </div>

            <!-- Tabs -->
            <div class="modal-tabs">
                <div class="modal-tab active" onclick="switchTab('details')" id="tab-btn-details">
                    <svg width="24" height="28" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                        <polyline points="14 2 14 8 20 8" />
                    </svg>
                    Details
                </div>
                <div class="modal-tab" onclick="switchTab('documents')" id="tab-btn-documents">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48" />
                    </svg>
                    Documents
                    <span class="tab-count" id="docCountBadge">0</span>
                </div>
            </div>

            <!-- Body -->
            <div class="modal-body">
                <div id="tab-details" class="modal-panel-content active">
                    <div id="detailsBody"></div>
                </div>
                <div id="tab-documents" class="modal-panel-content">
                    <div id="documentsBody"></div>
                </div>
            </div>

            <!-- Footer -->
            <div class="modal-foot">
                <button id="viewModalUpdateBtn" class="btn-modal-primary" style="display:none;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    Update Status
                </button>
                <button class="btn-modal-outline" onclick="closeModal('viewModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════
     UPDATE STATUS MODAL
════════════════════════════════════════════════════ -->
    <div id="updateModal" class="modal-overlay" onclick="handleOverlayClick(event,'updateModal')">
        <div class="modal-panel modal-panel--update">

            <!-- Head -->
            <div class="modal-head">
                <div class="modal-head-info">
                    <h2 class="modal-head-title">Update Inquiry Status</h2>
                    <p style="margin:0.3rem 0 0;font-size:0.82rem;color:var(--text-secondary);">
                        Select the new status for this inquiry
                    </p>
                </div>
                <button class="modal-close-btn" onclick="closeModal('updateModal')" aria-label="Close">&times;</button>
            </div>

            <!-- Body -->
            <div class="modal-body">
                <form method="POST" id="updateStatusForm">
                    <input type="hidden" name="inquiry_id" id="updateInquiryId">

                    <!-- Current status display -->
                    <div class="current-status-bar">
                        <span class="csb-label">Current Status</span>
                        <span id="currentStatusBadge"></span>
                    </div>

                    <!-- Visual status grid -->
                    <p style="font-size:0.78rem;font-weight:600;text-transform:uppercase;letter-spacing:0.07em;color:var(--text-muted);margin:0 0 0.5rem;">Select New Status</p>
                    <div class="status-grid" id="statusOptionGrid">
                        <?php
                        $statOpts = [
                            'pending'   => ['label' => 'Pending',   'icon' => '⏳', 'desc' => 'Awaiting admin review'],
                            'in_review' => ['label' => 'In Review', 'icon' => '🔍', 'desc' => 'Being actively processed'],
                            'completed' => ['label' => 'Completed', 'icon' => '✅', 'desc' => 'Service fulfilled successfully'],
                            'rejected'  => ['label' => 'Rejected',  'icon' => '🚫', 'desc' => 'Cannot proceed — requires reason'],
                        ];
                        foreach ($statOpts as $v => $d):
                        ?>
                            <div class="status-card" data-value="<?= $v ?>" onclick="selectStatus('<?= $v ?>')">
                                <div class="status-card-check"></div>
                                <input type="radio" name="status" value="<?= $v ?>" id="so_<?= $v ?>">
                                <div class="sc-icon"><?= $d['icon'] ?></div>
                                <span class="sc-label"><?= $d['label'] ?></span>
                                <span class="sc-desc"><?= $d['desc'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Rejection reason -->
                    <div class="rejection-group" id="rejectionGroup" style="display:none;">
                        <label>
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10" />
                                <line x1="12" y1="8" x2="12" y2="12" />
                                <line x1="12" y1="16" x2="12.01" y2="16" />
                            </svg>
                            Rejection Reason <span style="color:#dc2626;">*</span>
                        </label>
                        <textarea name="rejection_reason" id="rejectionReason"
                            placeholder="Provide a clear reason — this will be visible to the client…"></textarea>
                        <div class="rejection-warning">
                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                                <line x1="12" y1="9" x2="12" y2="13" />
                                <line x1="12" y1="17" x2="12.01" y2="17" />
                            </svg>
                            This rejection is permanent and cannot be undone.
                        </div>
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <div class="modal-foot">
                <button type="submit" form="updateStatusForm" name="update_status" class="btn-modal-primary" id="updateSubmitBtn" disabled>
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    Save Status
                </button>
                <button type="button" class="btn-modal-outline" onclick="closeModal('updateModal')">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        const inquiries = <?= json_encode($inquiries) ?>;

        const procMeta = {
            standard: {
                label: 'Standard',
                cls: 'proc-standard'
            },
            priority: {
                label: 'Priority',
                cls: 'proc-priority'
            },
            express: {
                label: 'Express',
                cls: 'proc-express'
            },
            rush: {
                label: 'Rush',
                cls: 'proc-rush'
            },
            same_day: {
                label: 'Same-Day',
                cls: 'proc-same_day'
            },
        };

        const statusMeta = {
            pending: {
                label: 'Pending',
                cls: 'status-pending'
            },
            in_review: {
                label: 'In Review',
                cls: 'status-in_review'
            },
            completed: {
                label: 'Completed',
                cls: 'status-completed'
            },
            rejected: {
                label: 'Rejected',
                cls: 'status-rejected'
            },
        };

        function fmtPrice(v) {
            return '₱' + parseFloat(v || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        function fmtDate(d) {
            if (!d) return '—';
            return new Date(d).toLocaleString('en-PH', {
                dateStyle: 'medium',
                timeStyle: 'short'
            });
        }

        function generateMaskedName() {
            const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
            let r = "JRN".split("");
            while (r.length < 14) r.push(chars[Math.floor(Math.random() * chars.length)]);
            for (let i = r.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [r[i], r[j]] = [r[j], r[i]];
            }
            return r.join("");
        }

        // ─── View Modal ──────────────────────────────────────────────────────────────
        function viewInquiry(id) {
            const inq = inquiries.find(i => i.id == id);
            if (!inq) return;

            const pt = inq.processing_type || 'standard';
            const ptm = procMeta[pt] || procMeta.standard;
            const sm = statusMeta[inq.status] || statusMeta.pending;
            const initials = inq.first_name ? (inq.first_name[0] + (inq.last_name?.[0] || '')).toUpperCase() : '?';
            const fullName = inq.first_name ? `${inq.first_name} ${inq.last_name}` : 'Unknown Client';

            // Header
            document.getElementById('viewModalTitle').textContent = `Inquiry #${inq.inquiry_number || id}`;
            document.getElementById('viewModalSub').innerHTML = `
        <span class="status-badge ${sm.cls}">${sm.label}</span>
        <span class="proc-pill ${ptm.cls}" style="font-size:0.65rem;">${ptm.label}</span>
    `;

            // Details tab
            document.getElementById('detailsBody').innerHTML = `
        <!-- Client card -->
        <div class="client-card">
            <div class="client-avatar">${initials}</div>
            <div class="client-card-info">
                <div class="client-card-name">${fullName}</div>
                <div class="client-card-meta">
                    ${inq.email ? `<span><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>${inq.email}</span>` : ''}
                    ${inq.phone ? `<span><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07C9.36 17.5 7.5 15.64 6.15 13.36a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 5.05 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L9.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 23 16.92z"/></svg>${inq.phone}</span>` : ''}
                    ${inq.account_number ? `<span>Acct: <code style="font-family:'DM Mono',monospace;">${inq.account_number}</code></span>` : ''}
                </div>
            </div>
        </div>

        <!-- Service details -->
        <p class="detail-section-title">Service Information</p>
        <div class="detail-grid">
            <div class="detail-item full">
                <span class="di-label">Service Name</span>
                <span class="di-value" style="font-size:1rem;font-weight:700;">${inq.service_name}</span>
            </div>
            <div class="detail-item">
                <span class="di-label">Processing Type</span>
                <span class="di-value"><span class="proc-pill ${ptm.cls}">${ptm.label} Processing</span></span>
            </div>
            <div class="detail-item">
                <span class="di-label">Service Fee</span>
                <div class="di-value">
                    <div class="price-main">${fmtPrice(inq.price)}</div>
                    ${inq.base_price && inq.base_price != inq.price ? `<div class="price-was">Base: ${fmtPrice(inq.base_price)}</div>` : ''}
                </div>
            </div>
            <div class="detail-item">
                <span class="di-label">Submitted On</span>
                <span class="di-value">${fmtDate(inq.created_at)}</span>
            </div>
            <div class="detail-item">
                <span class="di-label">Last Updated</span>
                <span class="di-value">${fmtDate(inq.updated_at)}</span>
            </div>
        </div>

        ${inq.additional_notes ? `
        <p class="detail-section-title">Additional Notes</p>
        <div class="notes-card">${inq.additional_notes}</div>
        ` : ''}

        ${inq.status === 'rejected' && inq.rejection_reason ? `
        <div class="modal-divider"></div>
        <p class="detail-section-title" style="color:#be123c;">Rejection Details</p>
        <div class="rejection-card">
            <div class="rejection-icon">✕</div>
            <div>
                <p>${inq.rejection_reason}</p>
                <small>This inquiry has been permanently rejected and cannot be modified.</small>
            </div>
        </div>
        ` : ''}
    `;

            // Documents tab — fetch
            document.getElementById('documentsBody').innerHTML = `<div style="text-align:center;padding:2rem;color:var(--text-muted);font-size:0.85rem;">Loading documents…</div>`;

            fetch(`get_inquiry_documents.php?inquiry_id=${id}`)
                .then(r => r.ok ? r.json() : [])
                .then(docs => {
                    document.getElementById('docCountBadge').textContent = docs.length;

                    if (docs.length === 0) {
                        document.getElementById('documentsBody').innerHTML = `
                    <div class="empty-docs">
                        <svg width="52" height="52" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                        </svg>
                        <p>No documents were attached to this inquiry.</p>
                    </div>`;
                    } else {
                        document.getElementById('documentsBody').innerHTML = `
                    <p style="font-size:0.78rem;color:var(--text-muted);margin:0 0 0.85rem;font-weight:500;">
                        ${docs.length} document${docs.length !== 1 ? 's' : ''} attached
                    </p>
                    ${docs.map(doc => `
                    <div class="doc-card">
                        <div class="doc-card-left">
                            <div class="doc-icon">
                                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            </div>
                            <div>
                                <div class="doc-name">${generateMaskedName()}</div>
                                <div class="doc-meta">${doc.id_type ? doc.id_type + ' &bull; ' : ''}${(doc.file_size / 1024).toFixed(2)} KB</div>
                            </div>
                        </div>
                        <a href="../download_document.php?id=${doc.id}" target="_blank" class="doc-download">
                            <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            Download
                        </a>
                    </div>`).join('')}
                `;
                    }
                })
                .catch(() => {
                    document.getElementById('documentsBody').innerHTML =
                        '<p style="color:#dc2626;font-size:0.85rem;padding:0.5rem 0;">Could not load documents.</p>';
                });

            // Update button in footer
            const updBtn = document.getElementById('viewModalUpdateBtn');
            if (inq.status !== 'rejected') {
                updBtn.style.display = 'inline-flex';
                updBtn.onclick = () => {
                    closeModal('viewModal');
                    openUpdateModal(id, inq.status);
                };
            } else {
                updBtn.style.display = 'none';
            }

            switchTab('details');
            document.getElementById('viewModal').classList.add('active');
        }

        // ─── Tab switching ───────────────────────────────────────────────────────────
        function switchTab(name) {
            ['details', 'documents'].forEach(t => {
                document.getElementById(`tab-${t}`).classList.toggle('active', t === name);
                document.getElementById(`tab-btn-${t}`).classList.toggle('active', t === name);
            });
        }

        // ─── Update Status Modal ─────────────────────────────────────────────────────
        function openUpdateModal(id, currentStatus) {
            document.getElementById('updateInquiryId').value = id;

            // Show current status badge
            const sm = statusMeta[currentStatus] || statusMeta.pending;
            document.getElementById('currentStatusBadge').innerHTML =
                `<span class="status-badge ${sm.cls}">${sm.label}</span>`;

            // Reset
            document.querySelectorAll('.status-card').forEach(el => el.classList.remove('selected'));
            document.getElementById('rejectionGroup').style.display = 'none';
            document.getElementById('rejectionReason').value = '';
            document.getElementById('updateSubmitBtn').disabled = true;

            // Pre-select current
            const cur = document.querySelector(`.status-card[data-value="${currentStatus}"]`);
            if (cur) {
                cur.classList.add('selected');
                document.getElementById(`so_${currentStatus}`).checked = true;
                document.getElementById('updateSubmitBtn').disabled = false;
            }

            document.getElementById('updateModal').classList.add('active');
        }

        function selectStatus(val) {
            document.querySelectorAll('.status-card').forEach(el => {
                el.classList.toggle('selected', el.dataset.value === val);
            });
            document.getElementById(`so_${val}`).checked = true;
            const isRejected = val === 'rejected';
            document.getElementById('rejectionGroup').style.display = isRejected ? 'block' : 'none';
            if (!isRejected) document.getElementById('rejectionReason').value = '';
            document.getElementById('updateSubmitBtn').disabled = false;
        }

        // ─── Modal utilities ─────────────────────────────────────────────────────────
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function handleOverlayClick(e, id) {
            if (e.target.id === id) closeModal(id);
        }
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeModal('viewModal');
                closeModal('updateModal');
            }
        });
    </script>
</body>

</html>