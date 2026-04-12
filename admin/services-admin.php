<?php
session_start();
require_once '../connection/dbconn.php';
require_once '../includes/auth.php';

requireAdmin();

// Fetch unique categories for filter dropdown
$category_stmt = $conn->prepare("SELECT DISTINCT category FROM services ORDER BY category ASC");
$category_stmt->execute();
$categories = $category_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Selected category from filter
$category_filter = $_GET['category'] ?? '';

if ($category_filter && $category_filter !== 'all') {
    $stmt = $conn->prepare("SELECT * FROM services WHERE category = ? ORDER BY display_order ASC, name ASC");
    $stmt->bind_param("s", $category_filter);
} else {
    $stmt = $conn->prepare("SELECT * FROM services ORDER BY display_order ASC, name ASC");
}
$stmt->execute();
$services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle search
$search_query = trim($_GET['search'] ?? '');

// Paging
$per_page = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// Build WHERE clause
$where = [];
$params = [];
$types = '';

if ($category_filter && $category_filter !== 'all') {
    $where[] = 'category = ?';
    $params[] = $category_filter;
    $types .= 's';
}
if ($search_query) {
    $where[] = '(name LIKE ? OR slug LIKE ?)';
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $types .= 'ss';
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Get total count for paging
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM services $where_sql");
if ($params) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_count = $count_stmt->get_result()->fetch_row()[0] ?? 0;

// Fetch page of results
$sql = "SELECT * FROM services $where_sql ORDER BY display_order ASC, name ASC LIMIT $per_page OFFSET $offset";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Compute page count
$page_count = max(1, ceil($total_count / $per_page));

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
    <title>Manage Services - JRN Admin</title>
    <link rel="stylesheet" href="assets/css/index-admin.css">
    <link rel="stylesheet" href="assets/css/services-admin.css">
    <link rel="stylesheet" href="assets/css/service-status-modal.css">
    <link rel="stylesheet" href="assets/css/service-delete-modal.css">
    <link rel="stylesheet" href="assets/css/logout-modal.css">
    <style>
        .service-schedule-info {
            margin-top: 4px;
            font-size: 0.75rem;
            color: #777;
        }
    </style>
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
                <a href="services-admin.php" class="nav-item active">
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
                <h1>Manage Services</h1>
                <p class="header-subtitle">Add, edit, and enable/disable business services.</p>
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

        <section class="card">
            <div class="card-header">
                <h2>All Services</h2>
                <div class="admin-header-right">
                    <a href="services-add.php" class="btn btn--primary">+ Add New Service</a>
                </div>
            </div>
            <?php if (count($services) > 0): ?>
                <form method="GET" class="filter-search-bar">
                    <div style="display:flex;align-items:center;gap:0.7rem;">
                        <label for="search">Search:</label>
                        <input type="text" name="search" id="search" class="form-control"
                            placeholder="Search a Service" value="<?= htmlspecialchars($search_query ?? '') ?>">
                        <button class="btn btn--sm btn--primary" type="submit">Search</button>
                    </div>
                    <div style="display:flex;align-items:center;gap:0.7rem;">
                        <label for="category">Category:</label>
                        <select id="category" name="category" class="form-control" onchange="this.form.submit();">
                            <option value="all" <?= (!$category_filter || $category_filter === 'all') ? ' selected' : '' ?>>All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['category']) ?>" <?= ($cat['category'] === $category_filter) ? ' selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Service Name</th>
                            <th>Category</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $srv): ?>
                            <tr>
                                <td>
                                    <?php if ($srv['is_active']): ?>
                                        <span class="status status--success">Active</span>
                                    <?php else: ?>
                                        <span class="status status--error">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($srv['name']); ?></strong>
                                    <div style="font-size:0.8rem;color:#888;"><?php echo htmlspecialchars($srv['slug']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($srv['category']); ?></td>
                                <td>
                                    <div class="action-btn-group">
                                        <a href="services-edit.php?id=<?= $srv['id'] ?>" class="btn btn--sm btn--primary">Edit</a>

                                        <?php if ($srv['is_active']): ?>
                                            <a href="javascript:void(0);" class="btn btn--sm btn--danger"
                                                onclick="openStatusModal(<?= $srv['id'] ?>, 'deactivate', '<?= htmlspecialchars($srv['name']) ?>')">
                                                Deactivate
                                            </a>
                                        <?php else: ?>
                                            <a href="javascript:void(0);" class="btn btn--sm btn--success"
                                                onclick="openStatusModal(<?= $srv['id'] ?>, 'activate', '<?= htmlspecialchars($srv['name']) ?>')">
                                                Activate
                                            </a>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($srv['scheduled_action']) && !empty($srv['scheduled_effective_at'])): ?>
                                        <?php
                                        $dt = new DateTime($srv['scheduled_effective_at']);
                                        $when = $dt->format('F j, Y g:i A'); // [web:94][web:97]
                                        $label = ($srv['scheduled_action'] === 'deactivate') ? 'Deactivation' : 'Activation';
                                        ?>
                                        <div class="service-schedule-info">
                                            <small>
                                                <?= $label ?> scheduled on <strong><?= htmlspecialchars($when) ?></strong>
                                            </small>
                                        </div>
                                        <div class="action-btn-group" style="margin-top: 4px;">
                                            <a href="javascript:void(0);" class="btn btn--sm btn--outline"
                                                onclick="openCancelScheduleModal(<?= $srv['id'] ?>, '<?= htmlspecialchars($srv['name']) ?>')">
                                                Cancel schedule
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($page_count > 1): ?>
                    <nav class="pagination-bar">
                        <?php for ($p = 1; $p <= $page_count; $p++): ?>
                            <a href="?category=<?= urlencode($category_filter) ?>&search=<?= urlencode($search_query) ?>&page=<?= $p ?>"
                                class="btn btn--sm<?= $page == $p ? ' btn--primary active' : '' ?>">
                                <?= $p ?>
                            </a>
                        <?php endfor; ?>
                    </nav>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <p>No services added yet.</p>
                    <a href="services-add.php" class="btn btn--primary">+ Add New Service</a>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- Service Status (Activate/Deactivate) Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content status-modal">
            <div class="modal-header">
                <h2 id="statusModalTitle">Are you sure?</h2>
            </div>
            <div class="modal-body">
                <p id="statusModalMessage">
                    This will update the visibility status of the service.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn--primary" id="confirmStatusBtn">Yes, continue</button>
                <button class="btn btn--outline" type="button" onclick="closeStatusModal()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Service Delete Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content delete-modal">
            <div class="modal-header">
                <h2>Delete Service?</h2>
            </div>
            <div class="modal-body">
                <p id="deleteModalMessage">
                    This action cannot be undone.<br>
                    Are you sure you want to delete this service?
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn--primary" id="confirmDeleteBtn">Yes, delete</button>
                <button class="btn btn--outline" type="button" onclick="closeDeleteModal()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Service Schedule Cancel Modal -->
    <div id="cancelScheduleModal" class="modal">
        <div class="modal-content status-modal">
            <div class="modal-header">
                <h2>Cancel Scheduled Change?</h2>
            </div>
            <div class="modal-body">
                <p id="cancelScheduleModalMessage">
                    Are you sure you want to cancel the scheduled activation/deactivation for this service?
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn--primary" id="confirmCancelScheduleBtn">Yes, cancel it</button>
                <button class="btn btn--outline" type="button" onclick="closeCancelScheduleModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        let statusActionId = null,
            statusActionType = '',
            statusActionName = '',
            deleteActionId = null,
            deleteActionName = '',
            cancelScheduleId = null,
            cancelScheduleName = '';

        function openStatusModal(id, action, name) {
            statusActionId = id;
            statusActionType = action;
            statusActionName = name;
            document.getElementById('statusModalTitle').innerText =
                (action === 'deactivate' ? 'Deactivate Service?' : 'Activate Service?');
            document.getElementById('statusModalMessage').innerHTML =
                (action === 'deactivate' ?
                    `Are you sure you want to <b>deactivate</b> "<b>${name}</b>"? An email will be sent to all users and this service will be deactivated in <b>3 days</b>.` :
                    `Are you sure you want to <b>activate</b> "<b>${name}</b>"? An email will be sent to all users and this service will be activated in <b>3 days</b>.`);
            document.getElementById('statusModal').style.display = 'flex';
        }

        function closeStatusModal() {
            document.getElementById('statusModal').style.display = 'none';
        }
        document.getElementById('confirmStatusBtn').onclick = function() {
            if (statusActionId && statusActionType) {
                window.location.href = `services-toggle.php?id=${statusActionId}&action=${statusActionType}`;
            }
        };

        function openDeleteModal(id, name) {
            deleteActionId = id;
            deleteActionName = name;
            document.getElementById('deleteModalMessage').innerHTML =
                `This action cannot be undone.<br>Are you sure you want to delete <b>${name}</b>?`;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        document.getElementById('confirmDeleteBtn').onclick = function() {
            if (deleteActionId) {
                window.location.href = `services-delete.php?id=${deleteActionId}`;
            }
        };

        function openCancelScheduleModal(id, name) {
            cancelScheduleId = id;
            cancelScheduleName = name;
            document.getElementById('cancelScheduleModalMessage').innerHTML =
                `Are you sure you want to cancel the scheduled status change for <b>${name}</b>?`;
            document.getElementById('cancelScheduleModal').style.display = 'flex';
        }

        function closeCancelScheduleModal() {
            document.getElementById('cancelScheduleModal').style.display = 'none';
        }
        document.getElementById('confirmCancelScheduleBtn').onclick = function() {
            if (cancelScheduleId) {
                window.location.href = `services-toggle.php?id=${cancelScheduleId}&action=cancel_schedule`;
            }
        };

        window.onclick = function(event) {
            if (event.target === document.getElementById('statusModal')) closeStatusModal();
            if (event.target === document.getElementById('deleteModal')) closeDeleteModal();
            if (event.target === document.getElementById('cancelScheduleModal')) closeCancelScheduleModal();
        };
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeStatusModal();
                closeDeleteModal();
                closeCancelScheduleModal();
            }
        });
    </script>

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