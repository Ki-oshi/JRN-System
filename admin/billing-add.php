<?php
session_start();
require_once '../connection/dbconn.php';
require_once '../includes/auth.php';
require_once '../includes/activity_logger.php';

requireAdmin();

$message      = '';
$message_type = '';

// ── Sidebar badges ─────────────────────────────────────────────────────────
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

// ── Clients list ───────────────────────────────────────────────────────────
$stmt    = $conn->prepare("SELECT id, fullname, first_name, last_name FROM users ORDER BY fullname ASC, first_name ASC");
$stmt->execute();
$clients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Pending inquiries without a final invoice ──────────────────────────────
$stmt = $conn->prepare("
    SELECT i.id, i.inquiry_number, i.service_name, i.price, i.created_at,
           u.id AS user_id, u.fullname, u.first_name, u.last_name
    FROM inquiries i
    JOIN users u ON u.id = i.user_id
    WHERE i.status = 'pending'
      AND NOT EXISTS (
          SELECT 1 FROM billings b
          WHERE b.invoice_number COLLATE utf8mb4_unicode_ci
                LIKE CONCAT('%', i.inquiry_number COLLATE utf8mb4_unicode_ci, '%')
            AND b.status IN ('paid','cancelled')
      )
    ORDER BY i.created_at DESC
");
$stmt->execute();
$pendingInquiries     = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pendingInquiriesJson = json_encode($pendingInquiries);
$clientsJson          = json_encode($clients);
$stmt->close();

// ── Invoice number generator ───────────────────────────────────────────────
function generateInvoiceNumber(mysqli $conn): string
{
    $datePart = date('Ymd');
    $stmt     = $conn->prepare("SELECT COUNT(*) AS c FROM billings WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['c'] + 1;
    return "INV-{$datePart}-" . str_pad((string)$count, 4, '0', STR_PAD_LEFT);
}

// ── Form submit ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_invoice'])) {
    $client_name  = trim($_POST['client_name']  ?? '');
    $total_amount = trim($_POST['total_amount'] ?? '');
    $status       = $_POST['status']             ?? 'unpaid';
    $reference    = trim($_POST['reference']    ?? '');
    $invoice_date = trim($_POST['invoice_date'] ?? '');
    $due_date     = trim($_POST['due_date']     ?? '');
    $note         = trim($_POST['note']         ?? '');

    $errors = [];
    if ($client_name === '') $errors[] = 'Client name is required.';
    if ($reference   === '') $errors[] = 'Inquiry reference is required.';
    if ($total_amount === '') {
        $errors[] = 'Total amount is required.';
    } elseif (!is_numeric($total_amount) || (float)$total_amount < 0) {
        $errors[] = 'Total amount must be a valid non-negative number.';
    } else {
        $total_amount = round((float)$total_amount, 2);
    }
    $allowed_status = ['unpaid', 'pending', 'paid', 'cancelled'];
    if (!in_array($status, $allowed_status, true)) $status = 'unpaid';

    if ($reference !== '') {
        $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM billings WHERE invoice_number LIKE ? AND status IN ('paid','cancelled')");
        $like = '%' . $conn->real_escape_string($reference) . '%';
        $stmt->bind_param("s", $like);
        $stmt->execute();
        if ((int)$stmt->get_result()->fetch_assoc()['c'] > 0) {
            $errors[] = 'This inquiry already has a finalised (paid/cancelled) invoice.';
        }
        $stmt->close();
    }

    if (empty($errors)) {
        $invoice_number  = generateInvoiceNumber($conn);
        $service_name    = '';
        $base_fee        = $total_amount;
        $processing_fee  = 0.00;
        $other_fees      = 0.00;
        $discount        = 0.00;

        // Pull service name & original price from the inquiry
        if ($reference !== '') {
            $stmt = $conn->prepare(
                "SELECT service_name, price FROM inquiries WHERE inquiry_number = ? LIMIT 1"
            );
            $stmt->bind_param("s", $reference);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                $service_name = $row['service_name'];
                $base_fee     = round((float)$row['price'], 2);
            }
        }

        $stmt = $conn->prepare("
            INSERT INTO billings
                (invoice_number, client_name, total_amount, status,
                 service_name, base_fee, processing_fee, other_fees, discount,
                 created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param(
            "ssdssdddd",
            $invoice_number,
            $client_name,
            $total_amount,
            $status,
            $service_name,
            $base_fee,
            $processing_fee,
            $other_fees,
            $discount
        );

        if ($stmt->execute()) {
            logActivity(
                $_SESSION['user_id'],
                'admin',
                'invoice_created',
                "Invoice {$invoice_number} created for inquiry {$reference} "
                    . "(client: {$client_name}, amount: {$total_amount}, status: {$status})"
            );
            $_SESSION['success'] = "Invoice <strong>{$invoice_number}</strong> created successfully for {$client_name}.";
            header("Location: billing-admin.php");
            exit;
        } else {
            $message      = 'Error creating invoice: ' . $stmt->error;
            $message_type = 'error';
        }
        $stmt->close();
    } else {
        $message      = implode('<br>', $errors);
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Invoice – JRN Admin</title>
    <link rel="stylesheet" href="assets/css/index-admin.css">
    <link rel="stylesheet" href="assets/css/billing-admin.css">
    <link rel="stylesheet" href="assets/css/billing-add.css">
    <link rel="stylesheet" href="assets/css/logout-modal.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        /* ══════════════════════════════════════════
           FORCE LIGHT MODE
        ══════════════════════════════════════════ */
        :root {
            color-scheme: light only !important;
            --primary: #0F3A40;
            --primary-mid: #1a5560;
            --primary-light: #e8f4f5;
            --accent: #2fb8c4;
            --accent-soft: #e0f7fa;
            --gold: #f59e0b;
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

        /* ── Back link ── */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            color: var(--text-secondary);
            font-size: 0.82rem;
            font-weight: 500;
            text-decoration: none;
            margin-bottom: 0.4rem;
            transition: color 0.15s;
        }

        .back-link:hover {
            color: var(--primary);
        }

        /* ── Alert ── */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.9rem 1.25rem;
            border-radius: var(--r-md);
            margin-bottom: 1.25rem;
            font-size: 0.875rem;
            font-weight: 500;
            line-height: 1.5;
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

        /* ── Two-column layout ── */
        .create-layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 1.25rem;
            align-items: start;
        }

        @media (max-width: 960px) {
            .create-layout {
                grid-template-columns: 1fr;
            }
        }

        /* ── Form card ── */
        .form-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: var(--r-xl);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .form-card-head {
            padding: 1.35rem 1.75rem 1.1rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* ── Form Card Header Icon ── */
        .form-card-head-icon svg {
            width: 20px;
            height: 20px;
            stroke-width: 2;
            fill: var(--primary);
        }

        .form-card-head h2 {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
        }

        .form-card-head p {
            margin: 0.15rem 0 0;
            font-size: 0.78rem;
            color: var(--text-secondary);
        }

        .form-card-body {
            padding: 1.5rem 1.75rem;
        }

        /* ── Section titles ── */
        .section-label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-muted);
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
            margin: 0 0 1rem;
        }

        /* ── Form fields ── */
        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.1rem;
        }

        .form-grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1.1rem;
        }

        @media (max-width: 700px) {

            .form-grid-2,
            .form-grid-3 {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            margin-bottom: 1.1rem;
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        .form-group label {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .required {
            color: #ef4444;
        }

        .form-control {
            height: 42px;
            padding: 0 1rem;
            border: 1.5px solid var(--border-color);
            border-radius: var(--r-sm);
            font-size: 0.88rem;
            background: #fff;
            color: var(--text-primary);
            font-family: 'DM Sans', sans-serif;
            transition: border-color 0.18s, box-shadow 0.18s;
            width: 100%;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(47, 184, 196, 0.12);
        }

        .form-control::placeholder {
            color: var(--text-muted);
        }

        textarea.form-control {
            height: auto;
            padding: 0.75rem 1rem;
            resize: vertical;
            min-height: 96px;
        }

        select.form-control {
            cursor: pointer;
        }

        .form-hint {
            font-size: 0.74rem;
            color: var(--text-muted);
            line-height: 1.4;
        }

        .form-control:disabled {
            background: var(--bg-secondary);
            cursor: not-allowed;
            color: var(--text-secondary);
        }

        /* ── Inquiry preview card ── */
        .inquiry-preview {
            border: 1.5px solid var(--border-color);
            border-radius: var(--r-md);
            padding: 1rem 1.1rem;
            margin-top: 0.5rem;
            display: none;
            background: var(--primary-light);
            animation: fadeIn 0.2s ease;
        }

        .inquiry-preview.visible {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .ip-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.35rem;
        }

        .ip-row:last-child {
            margin-bottom: 0;
        }

        .ip-label {
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--text-secondary);
        }

        .ip-value {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .ip-amount {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--primary);
        }

        .ip-divider {
            height: 1px;
            background: var(--border-color);
            margin: 0.6rem 0;
        }

        /* Autofill badge */
        .autofill-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.68rem;
            font-weight: 700;
            background: var(--green-soft);
            color: var(--green);
            border: 1px solid var(--green-border);
            padding: 0.15rem 0.5rem;
            border-radius: 999px;
            margin-left: 0.5rem;
            animation: popIn 0.25s cubic-bezier(0.34, 1.2, 0.64, 1);
        }

        @keyframes popIn {
            from {
                transform: scale(0.7);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        /* ── Side panel ── */
        .side-panel {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        /* Status selector cards */
        .status-selector {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .status-opt {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--r-md);
            cursor: pointer;
            transition: all 0.16s;
            background: #fff;
            position: relative;
        }

        .status-opt:hover {
            border-color: var(--accent);
            background: var(--accent-soft);
        }

        .status-opt.selected {
            border-color: var(--primary);
            background: var(--primary-light);
            box-shadow: 0 0 0 1px var(--primary);
        }

        .status-opt input[type="radio"] {
            display: none;
        }

        .status-opt-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .dot-unpaid {
            background: #dc2626;
        }

        .dot-pending {
            background: #d97706;
        }

        .dot-paid {
            background: #16a34a;
        }

        .dot-cancelled {
            background: #94a3b8;
        }

        .status-opt-check {
            position: absolute;
            right: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
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

        .status-opt.selected .status-opt-check {
            background: var(--primary);
            border-color: var(--primary);
        }

        .status-opt.selected .status-opt-check::after {
            content: '';
            width: 5px;
            height: 5px;
            background: #fff;
            border-radius: 50%;
        }

        .status-opt-label {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .status-opt-desc {
            font-size: 0.73rem;
            color: var(--text-secondary);
            margin-top: 0.1rem;
        }

        /* Invoice preview panel */
        .invoice-panel {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: var(--r-xl);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .invoice-panel-head {
            background: linear-gradient(135deg, var(--primary), var(--primary-mid));
            padding: 1.25rem 1.5rem;
            color: #fff;
        }

        .ip-head-label {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            opacity: 0.7;
        }

        .ip-head-num {
            font-family: 'DM Mono', monospace;
            font-size: 1.1rem;
            font-weight: 600;
            margin-top: 0.25rem;
        }

        .invoice-panel-body {
            padding: 1.25rem 1.5rem;
        }

        .ipb-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.55rem 0;
            border-bottom: 1px dashed var(--border-color);
        }

        .ipb-row:last-child {
            border-bottom: none;
        }

        .ipb-key {
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-weight: 600;
        }

        .ipb-val {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .ipb-val.amount {
            font-size: 1.2rem;
            color: var(--primary);
        }

        /* Status select hidden input sync */
        #statusHidden {
            display: none;
        }

        /* ── Form actions ── */
        .form-actions-bar {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            padding: 1.25rem 1.75rem;
            border-top: 1px solid var(--border-color);
            background: var(--bg-secondary);
        }

        .btn-create {
            height: 44px;
            padding: 0 1.75rem;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: var(--r-md);
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            transition: all 0.15s;
            box-shadow: 0 2px 10px rgba(15, 58, 64, 0.2);
        }

        .btn-create:hover {
            background: var(--primary-mid);
            box-shadow: 0 4px 16px rgba(15, 58, 64, 0.3);
        }

        .btn-cancel-link {
            height: 44px;
            padding: 0 1.25rem;
            border: 1.5px solid var(--border-color);
            border-radius: var(--r-md);
            font-size: 0.88rem;
            font-weight: 600;
            background: #fff;
            color: var(--text-primary);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.15s;
        }

        .btn-cancel-link:hover {
            border-color: var(--border-strong);
            background: var(--bg-secondary);
        }

        /* ═══════════════════════════════════════════
           CONFIRM / SUCCESS MODAL
        ═══════════════════════════════════════════ */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(7px);
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
            max-width: 440px;
            overflow: hidden;
            transform: translateY(22px) scale(0.96);
            opacity: 0;
            transition: transform 0.28s cubic-bezier(0.34, 1.2, 0.64, 1), opacity 0.22s ease;
        }

        .modal-header-band {
            background: linear-gradient(135deg, var(--primary), var(--primary-mid));
            padding: 1.75rem 1.75rem 1.25rem;
            text-align: center;
            color: #fff;
        }

        .modal-icon-circle {
            width: 56px;
            height: 56px;
            background: rgba(255, 255, 255, 0.18);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.85rem;
            font-size: 1.5rem;
        }

        .modal-header-band h3 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 700;
        }

        .modal-header-band p {
            margin: 0.3rem 0 0;
            font-size: 0.82rem;
            opacity: 0.8;
        }

        .modal-body-zone {
            padding: 1.5rem 1.75rem;
        }

        /* Invoice summary in modal */
        .modal-summary {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--r-md);
            padding: 1rem 1.1rem;
            margin-bottom: 1.25rem;
        }

        .ms-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
            font-size: 0.84rem;
        }

        .ms-row:last-child {
            margin-bottom: 0;
        }

        .ms-key {
            color: var(--text-secondary);
            font-weight: 500;
        }

        .ms-val {
            font-weight: 700;
            color: var(--text-primary);
        }

        .ms-val.amount {
            font-size: 1.1rem;
            color: var(--primary);
        }

        .ms-divider {
            height: 1px;
            background: var(--border-color);
            margin: 0.6rem 0;
        }

        .modal-foot-zone {
            display: flex;
            gap: 0.6rem;
            padding: 1rem 1.75rem 1.5rem;
        }

        .modal-btn {
            flex: 1;
            height: 44px;
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

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 0.22rem 0.65rem;
            border-radius: 999px;
        }

        .sp-unpaid {
            background: var(--red-soft);
            color: var(--red);
            border: 1px solid var(--red-border);
        }

        .sp-pending {
            background: var(--amber-soft);
            color: var(--amber);
            border: 1px solid var(--amber-border);
        }

        .sp-paid {
            background: var(--green-soft);
            color: var(--green);
            border: 1px solid var(--green-border);
        }

        .sp-cancelled {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
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
                <a href="billing-admin.php" class="back-link">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7" />
                    </svg>
                    Back to Billing
                </a>
                <h1>Create Invoice</h1>
                <p class="header-subtitle">Generate a new invoice for a pending client inquiry</p>
            </div>
            <div class="admin-header-right">
                <div class="avatar-circle"><?= strtoupper(substr($admin['first_name'] ?? 'A', 0, 1)) ?></div>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert--<?= $message_type ?>"><?= $message ?></div>
        <?php endif; ?>

        <div class="create-layout">

            <!-- ── LEFT: Main form ───────────────────────────────────────────── -->
            <div>
                <div class="form-card">
                    <div class="form-card-head">
                        <div class="form-card-head-icon">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                <polyline points="14 2 14 8 20 8" />
                            </svg>
                        </div>
                        <div>
                            <h2>Invoice Details</h2>
                            <p>Fill in the fields below to generate a new invoice</p>
                        </div>
                    </div>

                    <div class="form-card-body">
                        <form method="POST" id="invoiceForm">

                            <!-- Hidden field to trigger PHP processing -->
                            <input type="hidden" name="create_invoice" value="1">

                            <!-- Section: Client & Reference -->
                            <p class="section-label">Client &amp; Service Reference</p>
                            <div class="form-grid-2" style="margin-bottom:1.1rem;">
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>Client Name <span class="required">*</span></label>
                                    <select id="clientSelect" name="client_name" class="form-control" required>
                                        <option value="" disabled <?= empty($_POST['client_name']) ? 'selected' : '' ?>>Select a client…</option>
                                        <?php foreach ($clients as $c):
                                            $fn = $c['fullname'] ?: trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
                                            if ($fn === '') continue;
                                            $sel = (isset($_POST['client_name']) && $_POST['client_name'] === $fn) ? 'selected' : '';
                                        ?>
                                            <option value="<?= htmlspecialchars($fn) ?>" <?= $sel ?>><?= htmlspecialchars($fn) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="form-hint">Clients with pending inquiries will populate references.</span>
                                </div>

                                <div class="form-group" style="margin-bottom:0;">
                                    <label>
                                        Inquiry Reference <span class="required">*</span>
                                        <span id="refAutoFillBadge" class="autofill-badge" style="display:none;">✓ Amount filled</span>
                                    </label>
                                    <select id="referenceSelect" name="reference" class="form-control" required>
                                        <option value="" disabled selected>Select client first…</option>
                                    </select>
                                    <span class="form-hint">Only unfinalised pending inquiries shown.</span>
                                </div>
                            </div>

                            <!-- Inquiry preview -->
                            <div id="inquiryPreview" class="inquiry-preview">
                                <div class="ip-row">
                                    <span class="ip-label">Service</span>
                                    <span class="ip-value" id="prevService">—</span>
                                </div>
                                <div class="ip-divider"></div>
                                <div class="ip-row">
                                    <span class="ip-label">Submitted</span>
                                    <span class="ip-value" id="prevDate">—</span>
                                </div>
                                <div class="ip-row">
                                    <span class="ip-label">Inquiry Amount</span>
                                    <span class="ip-amount" id="prevAmount">₱0.00</span>
                                </div>
                            </div>

                            <!-- Section: Dates -->
                            <p class="section-label" style="margin-top:1.5rem;">Dates</p>
                            <div class="form-grid-2" style="margin-bottom:1.1rem;">
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>Invoice Date</label>
                                    <input type="date" name="invoice_date" class="form-control"
                                        value="<?= htmlspecialchars($_POST['invoice_date'] ?? date('Y-m-d')) ?>">
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>Due Date</label>
                                    <input type="date" name="due_date" class="form-control"
                                        value="<?= htmlspecialchars($_POST['due_date'] ?? '') ?>">
                                    <span class="form-hint">Leave blank if not applicable.</span>
                                </div>
                            </div>

                            <!-- Section: Amount -->
                            <p class="section-label" style="margin-top:1.5rem;">Amount &amp; Notes</p>
                            <div class="form-grid-2" style="margin-bottom:1.1rem;">
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>Total Amount (₱) <span class="required">*</span></label>
                                    <input type="number" id="totalAmount" name="total_amount" class="form-control"
                                        required step="0.01" min="0" placeholder="0.00"
                                        value="<?= htmlspecialchars($_POST['total_amount'] ?? '') ?>">
                                    <span class="form-hint">Auto-filled from inquiry — you can override.</span>
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label>Invoice Note</label>
                                    <textarea name="note" class="form-control"
                                        placeholder="Optional — payment instructions, bank details…"><?= htmlspecialchars($_POST['note'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <!-- Hidden status synced from the right panel -->
                            <input type="hidden" name="status" id="statusHidden" value="<?= htmlspecialchars($_POST['status'] ?? 'unpaid') ?>">
                        </form>
                    </div>

                    <!-- Actions bar -->
                    <div class="form-actions-bar">
                        <button type="button" class="btn-create" onclick="openConfirmModal()">
                            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                                <polyline points="17 21 17 13 7 13 7 21" />
                                <polyline points="7 3 7 8 15 8" />
                            </svg>
                            Create Invoice
                        </button>
                        <a href="billing-admin.php" class="btn-cancel-link">Cancel</a>
                    </div>
                </div>
            </div>

            <!-- ── RIGHT: Side panel ─────────────────────────────────────────── -->
            <div class="side-panel">

                <!-- Status selector -->
                <div class="invoice-panel">
                    <div class="form-card-head" style="border-bottom:1px solid var(--border-color);padding:1.1rem 1.25rem;">
                        <div class="form-card-head-icon" style="width:32px;height:32px;">
                            <svg width="30" height="30" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="10" cy="10" r="10" />
                                <polyline points="20 6 9 17 4 12" />
                            </svg>
                        </div>
                        <div>
                            <h2 style="font-size:0.9rem;margin:0;">Invoice Status</h2>
                            <p style="font-size:0.74rem;color:var(--text-secondary);margin:0.1rem 0 0;">Select the initial status</p>
                        </div>
                    </div>
                    <div style="padding:1rem 1.25rem;">
                        <div class="status-selector">
                            <?php
                            $statOpts = [
                                'unpaid'    => ['Unpaid',    'Awaiting client payment',   'dot-unpaid'],
                                'pending'   => ['Pending',   'Under review / processing', 'dot-pending'],
                                'paid'      => ['Paid',      'Payment already received',  'dot-paid'],
                                'cancelled' => ['Cancelled', 'Invoice voided',             'dot-cancelled'],
                            ];
                            $curStat = $_POST['status'] ?? 'unpaid';
                            foreach ($statOpts as $v => [$l, $d, $dotCls]):
                                $sel = $curStat === $v ? 'selected' : '';
                            ?>
                                <div class="status-opt <?= $sel ?>" data-value="<?= $v ?>" onclick="selectStatus('<?= $v ?>')">
                                    <span class="status-opt-dot <?= $dotCls ?>"></span>
                                    <div>
                                        <div class="status-opt-label"><?= $l ?></div>
                                        <div class="status-opt-desc"><?= $d ?></div>
                                    </div>
                                    <div class="status-opt-check"></div>
                                    <input type="radio" name="_status_visual" value="<?= $v ?>" <?= $sel ? 'checked' : '' ?>>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Live invoice preview panel -->
                <div class="invoice-panel">
                    <div class="invoice-panel-head">
                        <div class="ip-head-label">Invoice Preview</div>
                        <div class="ip-head-num" id="previewInvNum">INV-YYYYMMDD-####</div>
                    </div>
                    <div class="invoice-panel-body">
                        <div class="ipb-row">
                            <span class="ipb-key">Client</span>
                            <span class="ipb-val" id="previewClient">—</span>
                        </div>
                        <div class="ipb-row">
                            <span class="ipb-key">Service</span>
                            <span class="ipb-val" id="previewService" style="max-width:160px;text-align:right;font-size:0.8rem;">—</span>
                        </div>
                        <div class="ipb-row">
                            <span class="ipb-key">Status</span>
                            <span id="previewStatus" class="status-pill sp-unpaid">Unpaid</span>
                        </div>
                        <div class="ipb-row">
                            <span class="ipb-key">Date</span>
                            <span class="ipb-val" id="previewDate"><?= date('M d, Y') ?></span>
                        </div>
                        <div class="ipb-row">
                            <span class="ipb-key">Total</span>
                            <span class="ipb-val amount" id="previewAmount">₱0.00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- ══════════════════════════════════════════════════
         CONFIRM INVOICE CREATION MODAL
    ════════════════════════════════════════════════════ -->
    <div id="confirmModal" class="modal-overlay" onclick="if(event.target===this)closeConfirmModal()">
        <div class="modal-box">
            <div class="modal-header-band">
                <div class="modal-icon-circle">
                    <svg width="26" height="26" fill="none" stroke="#fff" stroke-width="2.5">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                        <polyline points="14 2 14 8 20 8" />
                        <line x1="16" y1="13" x2="8" y2="13" />
                        <line x1="16" y1="17" x2="8" y2="17" />
                        <polyline points="10 9 9 9 8 9" />
                    </svg>
                </div>
                <h3>Confirm Invoice Creation</h3>
                <p>Review the details below before saving</p>
            </div>

            <div class="modal-body-zone">
                <div class="modal-summary">
                    <div class="ms-row">
                        <span class="ms-key">Client</span>
                        <span class="ms-val" id="mClient">—</span>
                    </div>
                    <div class="ms-row">
                        <span class="ms-key">Service Reference</span>
                        <span class="ms-val" id="mRef" style="font-family:'DM Mono',monospace;font-size:0.82rem;">—</span>
                    </div>
                    <div class="ms-divider"></div>
                    <div class="ms-row">
                        <span class="ms-key">Invoice Status</span>
                        <span id="mStatus"></span>
                    </div>
                    <div class="ms-row">
                        <span class="ms-key">Invoice Date</span>
                        <span class="ms-val" id="mDate">—</span>
                    </div>
                    <div class="ms-divider"></div>
                    <div class="ms-row">
                        <span class="ms-key">Total Amount</span>
                        <span class="ms-val amount" id="mAmount">₱0.00</span>
                    </div>
                </div>
                <p style="font-size:0.78rem;color:var(--text-muted);text-align:center;margin:0;">
                    An invoice number will be automatically assigned upon creation.
                </p>
            </div>

            <div class="modal-foot-zone">
                <button class="modal-btn outline" onclick="closeConfirmModal()">Go Back</button>
                <button class="modal-btn primary" id="modalSubmitBtn">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:0.3rem;">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    Create Invoice
                </button>
            </div>
        </div>
    </div>

    <script>
        const pendingInquiries = <?= $pendingInquiriesJson ?>;

        const clientSel = document.getElementById('clientSelect');
        const referenceSel = document.getElementById('referenceSelect');
        const totalAmt = document.getElementById('totalAmount');
        const preview = document.getElementById('inquiryPreview');
        const badge = document.getElementById('refAutoFillBadge');
        const statusHidden = document.getElementById('statusHidden');

        const statusPillHtml = {
            unpaid: '<span class="status-pill sp-unpaid">Unpaid</span>',
            pending: '<span class="status-pill sp-pending">Pending</span>',
            paid: '<span class="status-pill sp-paid">Paid</span>',
            cancelled: '<span class="status-pill sp-cancelled">Cancelled</span>',
        };

        function fmt(v) {
            return '₱' + parseFloat(v || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        // ── Status selector ───────────────────────────────────────────────────
        function selectStatus(val) {
            document.querySelectorAll('.status-opt').forEach(el => {
                el.classList.toggle('selected', el.dataset.value === val);
            });
            statusHidden.value = val;
            updatePreviewStatus(val);
        }

        function updatePreviewStatus(val) {
            const el = document.getElementById('previewStatus');
            if (!el) return;
            el.className = 'status-pill sp-' + val;
            el.textContent = val.charAt(0).toUpperCase() + val.slice(1);
        }

        // ── Reference rebuild ─────────────────────────────────────────────────
        function rebuildReferenceOptions(selectedClient) {
            referenceSel.innerHTML = '';
            preview.classList.remove('visible');
            badge.style.display = 'none';

            const ph = document.createElement('option');
            ph.disabled = true;
            ph.selected = true;
            ph.value = '';
            ph.textContent = selectedClient ? 'Select an inquiry reference…' : 'Select a client first…';
            referenceSel.appendChild(ph);

            if (!selectedClient) return;

            const matches = pendingInquiries.filter(inq => {
                const fn = inq.fullname || ((inq.first_name || '') + ' ' + (inq.last_name || '')).trim();
                return fn === selectedClient;
            });

            if (matches.length === 0) {
                const opt = document.createElement('option');
                opt.disabled = true;
                opt.textContent = 'No eligible pending inquiries';
                referenceSel.appendChild(opt);
                return;
            }

            matches.forEach(inq => {
                const opt = document.createElement('option');
                opt.value = inq.inquiry_number || inq.id;
                opt.textContent = (inq.inquiry_number || inq.id) + ' — ' + inq.service_name;
                opt.dataset.price = inq.price || '0';
                opt.dataset.service = inq.service_name || '';
                opt.dataset.date = inq.created_at || '';
                referenceSel.appendChild(opt);
            });

            <?php if (!empty($_POST['reference'])): ?>
                const prevRef = <?= json_encode($_POST['reference']) ?>;
                for (const opt of referenceSel.options) {
                    if (opt.value === prevRef) {
                        opt.selected = true;
                        showInquiryPreview(opt);
                        break;
                    }
                }
            <?php endif; ?>
        }

        function showInquiryPreview(opt) {
            if (!opt || !opt.value || opt.disabled) {
                preview.classList.remove('visible');
                badge.style.display = 'none';
                updateSidePreview(null, null);
                return;
            }
            document.getElementById('prevService').textContent = opt.dataset.service || '—';
            document.getElementById('prevDate').textContent = opt.dataset.date ?
                new Date(opt.dataset.date).toLocaleDateString('en-PH', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                }) :
                '—';
            document.getElementById('prevAmount').textContent = fmt(opt.dataset.price);
            preview.classList.add('visible');

            // Auto-fill amount
            totalAmt.value = parseFloat(opt.dataset.price || 0).toFixed(2);
            badge.style.display = 'inline-flex';
            setTimeout(() => {
                badge.style.display = 'none';
            }, 3000);

            updateSidePreview(opt.dataset.service, opt.dataset.price);
        }

        function updateSidePreview(service, amount) {
            document.getElementById('previewService').textContent = service || '—';
            document.getElementById('previewAmount').textContent = fmt(amount || 0);
        }

        clientSel.addEventListener('change', function() {
            rebuildReferenceOptions(this.value);
            document.getElementById('previewClient').textContent = this.value || '—';
        });

        referenceSel.addEventListener('change', function() {
            showInquiryPreview(this.options[this.selectedIndex]);
        });

        totalAmt.addEventListener('input', function() {
            document.getElementById('previewAmount').textContent = fmt(this.value);
        });

        // Date preview sync
        document.querySelector('[name="invoice_date"]').addEventListener('change', function() {
            document.getElementById('previewDate').textContent = this.value ?
                new Date(this.value + 'T00:00:00').toLocaleDateString('en-PH', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                }) :
                '—';
        });

        // Restore POST state after validation error
        <?php if (!empty($_POST['client_name'])): ?>
            rebuildReferenceOptions(<?= json_encode($_POST['client_name']) ?>);
            document.getElementById('previewClient').textContent = <?= json_encode($_POST['client_name']) ?>;
        <?php endif; ?>

        // ── Confirm modal ─────────────────────────────────────────────────────
        function openConfirmModal() {
            const client = clientSel.value;
            const ref = referenceSel.value;
            const amount = parseFloat(totalAmt.value || 0).toFixed(2);
            const status = statusHidden.value;
            const dateVal = document.querySelector('[name="invoice_date"]').value;

            // Client-side validation
            if (!client) {
                clientSel.style.borderColor = '#ef4444';
                clientSel.focus();
                return;
            }
            if (!ref || referenceSel.selectedOptions[0]?.disabled) {
                referenceSel.style.borderColor = '#ef4444';
                referenceSel.focus();
                return;
            }
            if (isNaN(parseFloat(totalAmt.value)) || parseFloat(totalAmt.value) < 0) {
                totalAmt.style.borderColor = '#ef4444';
                totalAmt.focus();
                return;
            }

            // Reset borders
            [clientSel, referenceSel, totalAmt].forEach(el => el.style.borderColor = '');

            // Populate modal summary
            document.getElementById('mClient').textContent = client;
            document.getElementById('mRef').textContent = ref;
            document.getElementById('mAmount').textContent = fmt(amount);
            document.getElementById('mDate').textContent = dateVal ?
                new Date(dateVal + 'T00:00:00').toLocaleDateString('en-PH', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                }) :
                'Today';
            document.getElementById('mStatus').innerHTML = statusPillHtml[status] || '<span>' + status + '</span>';

            // Wire submit button
            const submitBtn = document.getElementById('modalSubmitBtn');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:0.3rem;"><polyline points="20 6 9 17 4 12"/></svg> Create Invoice';
            submitBtn.onclick = function() {
                this.disabled = true;
                this.innerHTML = 'Saving…';
                document.getElementById('invoiceForm').submit();
            };

            document.getElementById('confirmModal').classList.add('active');
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').classList.remove('active');
        }

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeConfirmModal();
        });

        // Spinner keyframe
        const style = document.createElement('style');
        style.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
        document.head.appendChild(style);
    </script>
</body>

</html>