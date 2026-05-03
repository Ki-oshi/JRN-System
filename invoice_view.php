<?php
session_start();
require_once 'connection/dbconn.php';
require_once 'includes/auth.php';

requireUser();

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    die('Invalid invoice ID.');
}

$invoice_id = (int)$_GET['id'];

// Fetch full invoice details
$stmt = $conn->prepare("
    SELECT b.id, b.invoice_number, b.client_name, b.total_amount, b.status,
       b.created_at, b.service_name, b.base_fee, b.processing_fee,
       b.other_fees, b.discount,
       DATE_ADD(b.created_at, INTERVAL 30 DAY) AS due_date,
       u.email, u.phone, u.address, u.city, u.state, u.postal_code,
       u.account_number
FROM billings b
LEFT JOIN users u ON u.fullname = b.client_name
                  OR CONCAT(u.first_name,' ',u.last_name) = b.client_name
WHERE b.id = ?
LIMIT 1
");
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$invoice) {
    die('Invoice not found.');
}

// ── Static company info (Philippines) ──────────────────────────────────────
$company_name       = 'JRN Business Solutions Co.';
$company_address    = "Unit 4-7, 2nd Floor, Ziane Commercial Building Corp.\nLot 40-A, Marcos Highway, Pinugay, Baras, Rizal";
$company_email      = 'support@jrnbusiness.com';
$company_phone      = '+63 917 000 0000';
$company_tin        = '123-456-789-000';
$company_bir_permit = 'BIR Permit No. 12AB3456C789';
$company_sec_reg    = 'SEC Reg. No. CS201900000';
$company_bir_date   = 'Date Issued: January 10, 2019';

// ── Static payment channels ────────────────────────────────────────────────
$gcash_number   = '0917-000-0000';
$gcash_name     = 'JRN Business Solutions';
$maya_number    = '0917-000-0000';
$maya_name      = 'JRN Business Solutions';
$bank_name      = 'Bank of the Philippine Islands (BPI)';
$bank_account   = '1234-5678-90';
$bank_account_name = 'JRN Business Solutions Co.';

// ── Fee computation ────────────────────────────────────────────────────────
$base_fee       = (float)($invoice['base_fee']       ?? 0);
$processing_fee = (float)($invoice['processing_fee'] ?? 0);
$other_fees     = (float)($invoice['other_fees']     ?? 0);
$discount       = (float)($invoice['discount']       ?? 0);
$subtotal       = $base_fee + $processing_fee + $other_fees - $discount;

// Philippine VAT: 12%
$vat_rate       = 0.12;
// Determine if VAT-inclusive or we add on top. For service businesses in PH,
// typically total_amount is VAT-inclusive, so we back-compute:
$total_amount   = (float)$invoice['total_amount'];
// If no fee breakdown, fall back to total_amount as subtotal
if ($subtotal <= 0) $subtotal = $total_amount;
$vatable_sales  = round($subtotal / 1.12, 2);
$vat_amount     = round($subtotal - $vatable_sales, 2);

// ── Status display ─────────────────────────────────────────────────────────
$status         = $invoice['status'] ?? 'pending';
$statusLabel    = ucfirst($status);
$statusColorMap = [
    'paid'      => ['bg' => '#f0fdf4', 'color' => '#16a34a', 'border' => '#bbf7d0'],
    'unpaid'    => ['bg' => '#fef9ec', 'color' => '#b45309', 'border' => '#fde68a'],
    'pending'   => ['bg' => '#fef9ec', 'color' => '#b45309', 'border' => '#fde68a'],
    'cancelled' => ['bg' => '#fef2f2', 'color' => '#dc2626', 'border' => '#fecaca'],
];
$sc = $statusColorMap[$status] ?? ['bg' => '#f1f5f9', 'color' => '#374151', 'border' => '#e2e8f0'];

// ── Due date ───────────────────────────────────────────────────────────────
$due_date = !empty($invoice['due_date'])
    ? date('F j, Y', strtotime($invoice['due_date']))
    : date('F j, Y', strtotime(($invoice['created_at'] ?? 'now') . ' +30 days'));

$issued_date = !empty($invoice['created_at'])
    ? date('F j, Y', strtotime($invoice['created_at']))
    : date('F j, Y');

// ── Client address block ───────────────────────────────────────────────────
$client_address_parts = array_filter([
    $invoice['address']     ?? null,
    $invoice['city']        ?? null,
    $invoice['state']       ?? null,
    $invoice['postal_code'] ?? null,
    'Philippines'
]);
$client_address = implode(', ', $client_address_parts) ?: 'Philippines';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= htmlspecialchars($invoice['invoice_number']); ?> — <?= htmlspecialchars($company_name); ?></title>
    <link rel="icon" type="image/x-icon" href="assets/img/Logo.jpg" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;600;700&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        :root {
            --ink: #0d2b2e;
            --ink-mid: #2c4a4e;
            --ink-light: #5f7c80;
            --ink-faint: #97b0b3;
            --teal: #0f3a40;
            --teal-mid: #155e68;
            --teal-light: #d1eaed;
            --gold: #c8973a;
            --gold-light: #fdf3e3;
            --bg: #f2f5f7;
            --white: #ffffff;
            --border: rgba(15, 58, 64, 0.10);
            --radius: 12px;
            --shadow: 0 8px 32px rgba(15, 58, 64, 0.10), 0 2px 8px rgba(15, 58, 64, 0.06);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--ink);
            min-height: 100vh;
        }

        /* ── Top action bar ───────────────────────────────────────── */
        .action-bar {
            background: var(--teal);
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.18);
        }

        .action-bar-brand {
            font-family: 'Lora', serif;
            font-size: 0.95rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.75);
            letter-spacing: 0.01em;
        }

        .action-bar-right {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .ab-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            font-family: 'DM Sans', sans-serif;
            transition: all .18s;
            text-decoration: none;
        }

        .ab-btn-ghost {
            background: rgba(255, 255, 255, 0.10);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.20);
        }

        .ab-btn-ghost:hover {
            background: rgba(255, 255, 255, 0.18);
        }

        .ab-btn-white {
            background: var(--white);
            color: var(--teal);
        }

        .ab-btn-white:hover {
            background: #e8f4f5;
        }

        .ab-btn-gold {
            background: var(--gold);
            color: #fff;
        }

        .ab-btn-gold:hover {
            background: #b5862f;
        }

        /* ── Page wrapper ─────────────────────────────────────────── */
        .invoice-page {
            max-width: 860px;
            margin: 32px auto 48px;
            padding: 0 16px;
        }

        /* ── Invoice card ─────────────────────────────────────────── */
        .invoice-card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            overflow: hidden;
        }

        /* ── Header band ──────────────────────────────────────────── */
        .inv-header {
            background: var(--teal);
            padding: 28px 32px 24px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            position: relative;
            overflow: hidden;
        }

        .inv-header::before {
            content: '';
            position: absolute;
            inset: 0;
            background: repeating-linear-gradient(-45deg,
                    transparent,
                    transparent 18px,
                    rgba(255, 255, 255, 0.025) 18px,
                    rgba(255, 255, 255, 0.025) 36px);
            pointer-events: none;
        }

        .inv-brand-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }

        .inv-brand-logo img {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.25);
        }

        .inv-brand-name {
            font-family: 'Lora', serif;
            font-size: 1.15rem;
            font-weight: 700;
            color: #fff;
            line-height: 1.2;
        }

        .inv-brand-tagline {
            font-size: 0.72rem;
            color: rgba(255, 255, 255, 0.55);
            font-weight: 400;
            letter-spacing: 0.05em;
        }

        .inv-brand-details {
            font-size: 0.78rem;
            color: rgba(255, 255, 255, 0.65);
            line-height: 1.8;
        }

        .inv-brand-details strong {
            color: rgba(255, 255, 255, 0.85);
            font-weight: 500;
        }

        .inv-header-right {
            text-align: right;
            flex-shrink: 0;
        }

        .inv-label {
            font-family: 'Lora', serif;
            font-size: 2rem;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.92);
            letter-spacing: -0.02em;
            line-height: 1;
            margin-bottom: 8px;
        }

        .inv-number {
            font-size: 0.82rem;
            color: rgba(255, 255, 255, 0.65);
            margin-bottom: 10px;
            font-family: 'Courier New', monospace;
            letter-spacing: 0.04em;
        }

        .inv-number strong {
            color: var(--gold);
            font-size: 0.9rem;
        }

        .inv-status-badge {
            display: inline-block;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            background: <?= $sc['bg'] ?>;
            color: <?= $sc['color'] ?>;
            border: 1px solid <?= $sc['border'] ?>;
        }

        /* ── Gold accent strip ────────────────────────────────────── */
        .inv-accent-strip {
            background: linear-gradient(90deg, var(--gold) 0%, #e0b55c 100%);
            height: 3px;
        }

        /* ── Body padding ─────────────────────────────────────────── */
        .inv-body {
            padding: 28px 32px;
        }

        /* ── Two-col meta row ─────────────────────────────────────── */
        .inv-meta-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
            padding-bottom: 22px;
            border-bottom: 1px solid var(--border);
        }

        .inv-meta-block h4 {
            font-size: 0.68rem;
            font-weight: 700;
            color: var(--ink-faint);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 8px;
        }

        .inv-meta-block p {
            font-size: 0.88rem;
            color: var(--ink-light);
            line-height: 1.65;
        }

        .inv-meta-block strong {
            font-size: 1rem;
            color: var(--ink);
            font-weight: 600;
            display: block;
            margin-bottom: 2px;
        }

        .inv-meta-block .mono {
            font-family: 'Courier New', monospace;
            font-size: 0.82rem;
            color: var(--teal-mid);
            font-weight: 600;
        }

        /* ── Dates row ────────────────────────────────────────────── */
        .inv-dates-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 24px;
        }

        .inv-date-cell {
            padding: 12px 16px;
            border-right: 1px solid var(--border);
        }

        .inv-date-cell:last-child {
            border-right: none;
        }

        .inv-date-cell span {
            font-size: 0.68rem;
            font-weight: 700;
            color: var(--ink-faint);
            text-transform: uppercase;
            letter-spacing: 0.07em;
            display: block;
            margin-bottom: 3px;
        }

        .inv-date-cell strong {
            font-size: 0.88rem;
            color: var(--ink);
            font-weight: 600;
        }

        .due-highlight strong {
            color: var(--gold);
        }

        /* ── Service table ────────────────────────────────────────── */
        .inv-section-label {
            font-size: 0.68rem;
            font-weight: 700;
            color: var(--ink-faint);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 10px;
        }

        .inv-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.87rem;
            margin-bottom: 0;
        }

        .inv-table thead tr {
            background: var(--teal);
            color: rgba(255, 255, 255, 0.85);
        }

        .inv-table thead th {
            padding: 10px 14px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            text-align: left;
        }

        .inv-table thead th:last-child {
            text-align: right;
        }

        .inv-table tbody tr {
            border-bottom: 1px solid var(--border);
        }

        .inv-table tbody tr:last-child {
            border-bottom: none;
        }

        .inv-table tbody tr:hover {
            background: #f9fafb;
        }

        .inv-table td {
            padding: 12px 14px;
            color: var(--ink-mid);
            vertical-align: top;
        }

        .inv-table td:last-child {
            text-align: right;
            font-weight: 600;
            color: var(--ink);
        }

        .inv-table .item-name {
            font-weight: 600;
            color: var(--ink);
            font-size: 0.88rem;
        }

        .inv-table .item-desc {
            font-size: 0.78rem;
            color: var(--ink-faint);
            margin-top: 2px;
        }

        .inv-table .discount-row td {
            color: #16a34a;
            font-style: italic;
        }

        .inv-table .discount-row td:last-child {
            color: #16a34a;
        }

        /* ── Totals block ─────────────────────────────────────────── */
        .inv-totals-wrap {
            display: flex;
            justify-content: flex-end;
            margin-top: 0;
            border-top: 1px solid var(--border);
        }

        .inv-totals {
            min-width: 300px;
            padding: 18px 0 0;
        }

        .inv-totals-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 0;
            font-size: 0.86rem;
            color: var(--ink-light);
        }

        .inv-totals-row.vat-row {
            color: var(--ink-mid);
            font-style: italic;
        }

        .inv-totals-row.total-row {
            border-top: 2px solid var(--teal);
            margin-top: 8px;
            padding-top: 12px;
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--teal);
            font-family: 'Lora', serif;
        }

        .inv-totals-row span:last-child {
            font-weight: 600;
            color: var(--ink);
        }

        .inv-totals-row.total-row span:last-child {
            color: var(--teal);
            font-weight: 700;
            font-size: 1.2rem;
        }

        .inv-totals-row.discount-row span {
            color: #16a34a;
        }

        /* ── In-words ─────────────────────────────────────────────── */
        .inv-words {
            margin-top: 14px;
            padding: 10px 14px;
            background: var(--gold-light);
            border: 1px solid rgba(200, 151, 58, 0.25);
            border-radius: 8px;
            font-size: 0.8rem;
            color: var(--ink-mid);
            line-height: 1.5;
        }

        .inv-words strong {
            color: var(--gold);
            font-weight: 600;
        }

        /* ── Divider ──────────────────────────────────────────────── */
        .inv-divider {
            border: none;
            border-top: 1px solid var(--border);
            margin: 24px 0;
        }

        /* ── Two-col lower ────────────────────────────────────────── */
        .inv-lower {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .inv-panel {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 16px 18px;
        }

        .inv-panel h4 {
            font-size: 0.68rem;
            font-weight: 700;
            color: var(--ink-faint);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
        }

        /* Payment channels */
        .payment-channel {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 12px;
        }

        .payment-channel:last-child {
            margin-bottom: 0;
        }

        .payment-channel-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        .pci-gcash {
            background: #0068ff;
            color: #fff;
        }

        .pci-maya {
            background: #00a550;
            color: #fff;
        }

        .pci-bank {
            background: #c8260c;
            color: #fff;
        }

        .payment-channel-info {
            font-size: 0.8rem;
            color: var(--ink-mid);
            line-height: 1.5;
        }

        .payment-channel-info strong {
            color: var(--ink);
            font-size: 0.83rem;
            display: block;
        }

        .payment-channel-info .acct {
            font-family: 'Courier New', monospace;
            color: var(--teal-mid);
            font-weight: 700;
            font-size: 0.85rem;
        }

        /* Terms */
        .inv-terms-list {
            list-style: none;
        }

        .inv-terms-list li {
            font-size: 0.78rem;
            color: var(--ink-light);
            padding: 4px 0;
            padding-left: 14px;
            position: relative;
            line-height: 1.5;
        }

        .inv-terms-list li::before {
            content: '–';
            position: absolute;
            left: 0;
            color: var(--ink-faint);
        }

        /* ── BIR compliance footer ────────────────────────────────── */
        .inv-compliance {
            margin-top: 24px;
            padding: 14px 18px;
            background: #f8f9fa;
            border: 1px dashed rgba(15, 58, 64, 0.15);
            border-radius: 10px;
            font-size: 0.75rem;
            color: var(--ink-faint);
            line-height: 1.7;
            text-align: center;
        }

        .inv-compliance strong {
            color: var(--ink-light);
        }

        /* ── Signature row ────────────────────────────────────────── */
        .inv-signature-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 24px;
        }

        .inv-sig-block {
            text-align: center;
        }

        .inv-sig-line {
            border-top: 1.5px solid var(--ink-mid);
            margin: 36px 16px 6px;
        }

        .inv-sig-label {
            font-size: 0.75rem;
            color: var(--ink-light);
            font-weight: 500;
        }

        .inv-sig-sublabel {
            font-size: 0.7rem;
            color: var(--ink-faint);
        }

        /* ── Paid watermark ───────────────────────────────────────── */
        .inv-watermark-wrap {
            position: relative;
        }

        <?php if ($status === 'paid'): ?>.inv-watermark-wrap::after {
            content: 'PAID';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-family: 'Lora', serif;
            font-size: 6rem;
            font-weight: 700;
            color: rgba(22, 163, 74, 0.07);
            pointer-events: none;
            white-space: nowrap;
            letter-spacing: 0.1em;
            user-select: none;
        }

        <?php endif; ?>

        /* ── Notice bar (unpaid) ──────────────────────────────────── */
        .inv-due-notice {
            background: #fef9ec;
            border: 1px solid #fde68a;
            border-radius: 10px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
            font-size: 0.85rem;
            color: #92400e;
        }

        .inv-due-notice i {
            color: var(--gold);
            font-size: 1rem;
            flex-shrink: 0;
        }

        .inv-due-notice strong {
            font-weight: 700;
        }

        /* ── Print ────────────────────────────────────────────────── */
        @media print {
            body {
                background: #fff;
            }

            .action-bar,
            .inv-due-notice {
                display: none !important;
            }

            .invoice-page {
                margin: 0;
                padding: 0;
                max-width: 100%;
            }

            .invoice-card {
                box-shadow: none;
                border: none;
                border-radius: 0;
            }

            .inv-header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .inv-table thead tr {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .payment-channel-icon {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        @media (max-width: 640px) {
            .inv-header {
                flex-direction: column;
            }

            .inv-header-right {
                text-align: left;
            }

            .inv-meta-row,
            .inv-lower,
            .inv-signature-row {
                grid-template-columns: 1fr;
            }

            .inv-dates-row {
                grid-template-columns: 1fr;
            }

            .inv-date-cell {
                border-right: none;
                border-bottom: 1px solid var(--border);
            }

            .inv-date-cell:last-child {
                border-bottom: none;
            }

            .inv-body {
                padding: 20px 16px;
            }

            .inv-header {
                padding: 20px 16px;
            }

            .action-bar {
                padding: 10px 14px;
            }

            .ab-btn span {
                display: none;
            }
        }
    </style>
</head>

<body>

    <!-- ── Action bar ─────────────────────────────────────────────────────────── -->
    <div class="action-bar">
        <span class="action-bar-brand"><?= htmlspecialchars($company_name); ?></span>
        <div class="action-bar-right">
            <a href="account_page.php#billing" class="ab-btn ab-btn-ghost">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Account</span>
            </a>
            <button class="ab-btn ab-btn-white" onclick="window.print()">
                <i class="fas fa-print"></i>
                <span>Print / Save PDF</span>
            </button>
            <?php if (in_array($status, ['unpaid', 'pending'])): ?>
                <a href="create_checkout.php?invoice_id=<?= (int)$invoice['id']; ?>" class="ab-btn ab-btn-gold">
                    <i class="fas fa-credit-card"></i>
                    <span>Pay Now</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Page ───────────────────────────────────────────────────────────────── -->
    <div class="invoice-page">

        <?php if (in_array($status, ['unpaid', 'pending'])): ?>
            <div class="inv-due-notice">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    This invoice is <strong>due on <?= $due_date; ?></strong>. Please settle your balance at your earliest convenience to avoid any service delays. For questions, email us at <strong><?= htmlspecialchars($company_email); ?></strong>.
                </div>
            </div>
        <?php endif; ?>

        <div class="invoice-card">

            <!-- Header band -->
            <div class="inv-header">
                <div class="inv-brand">
                    <div class="inv-brand-logo">
                        <img src="assets/img/logo.jpg" alt="JRN Logo" onerror="this.style.display='none'">
                        <div>
                            <div class="inv-brand-name"><?= htmlspecialchars($company_name); ?></div>
                            <div class="inv-brand-tagline">Professional Business Solutions</div>
                        </div>
                    </div>
                    <div class="inv-brand-details">
                        <strong>Registered Address</strong>
                        <?= nl2br(htmlspecialchars($company_address)); ?><br>
                        <strong>Contact</strong>
                        <?= htmlspecialchars($company_email); ?> &nbsp;|&nbsp; <?= htmlspecialchars($company_phone); ?><br>
                        <strong>TIN</strong> <?= htmlspecialchars($company_tin); ?><br>
                        <?= htmlspecialchars($company_bir_permit); ?> &nbsp;|&nbsp; <?= htmlspecialchars($company_sec_reg); ?>
                    </div>
                </div>
                <div class="inv-header-right">
                    <div class="inv-label">INVOICE</div>
                    <div class="inv-number">Ref. No. <strong><?= htmlspecialchars($invoice['invoice_number']); ?></strong></div>
                    <div class="inv-status-badge"><?= htmlspecialchars($statusLabel); ?></div>
                </div>
            </div>
            <div class="inv-accent-strip"></div>

            <!-- Body -->
            <div class="inv-body inv-watermark-wrap">

                <!-- Bill To + Service -->
                <div class="inv-meta-row">
                    <div class="inv-meta-block">
                        <h4>Bill To</h4>
                        <strong><?= htmlspecialchars($invoice['client_name']); ?></strong>
                        <p>
                            <?= htmlspecialchars($client_address); ?><br>
                            <?php if (!empty($invoice['email'])): ?>
                                <?= htmlspecialchars($invoice['email']); ?><br>
                            <?php endif; ?>
                            <?php if (!empty($invoice['phone'])): ?>
                                <?= htmlspecialchars($invoice['phone']); ?>
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($invoice['account_number'])): ?>
                            <p style="margin-top:6px;">
                                Account No. <span class="mono"><?= htmlspecialchars($invoice['account_number']); ?></span>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="inv-meta-block" style="text-align:right;">
                        <h4>Invoice Details</h4>
                        <p>
                            Invoice No. <span class="mono"><?= htmlspecialchars($invoice['invoice_number']); ?></span><br>
                            TIN (Client): &nbsp;<span style="color:#97b0b3;font-style:italic;">Not on file</span><br>
                            Nature of Service: <strong style="display:inline;font-size:0.88rem;"><?= htmlspecialchars($invoice['service_name'] ?? 'Business Services'); ?></strong>
                        </p>
                    </div>
                </div>

                <!-- Dates -->
                <div class="inv-dates-row">
                    <div class="inv-date-cell">
                        <span>Date Issued</span>
                        <strong><?= $issued_date; ?></strong>
                    </div>
                    <div class="inv-date-cell">
                        <span>Payment Terms</span>
                        <strong>Net 30 Days</strong>
                    </div>
                    <div class="inv-date-cell due-highlight">
                        <span>Due Date</span>
                        <strong><?= $due_date; ?></strong>
                    </div>
                </div>

                <!-- Service table -->
                <div class="inv-section-label">Services Rendered</div>
                <table class="inv-table">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Description</th>
                            <th style="width:140px;text-align:right;">Amount (₱)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($base_fee > 0): ?>
                            <tr>
                                <td style="color:var(--ink-faint);">1</td>
                                <td>
                                    <div class="item-name"><?= htmlspecialchars($invoice['service_name'] ?? 'Professional Service Fee'); ?></div>
                                    <div class="item-desc">Base service fee — professional processing and consultation</div>
                                </td>
                                <td>₱<?= number_format($base_fee, 2); ?></td>
                            </tr>
                        <?php endif; ?>

                        <?php if ($processing_fee > 0): ?>
                            <tr>
                                <td style="color:var(--ink-faint);"><?= $base_fee > 0 ? 2 : 1; ?></td>
                                <td>
                                    <div class="item-name">Processing &amp; Administrative Fee</div>
                                    <div class="item-desc">Document handling, filing, and administrative charges</div>
                                </td>
                                <td>₱<?= number_format($processing_fee, 2); ?></td>
                            </tr>
                        <?php endif; ?>

                        <?php if ($other_fees > 0): ?>
                            <tr>
                                <td style="color:var(--ink-faint);">—</td>
                                <td>
                                    <div class="item-name">Other Charges</div>
                                    <div class="item-desc">Miscellaneous fees applicable to this service</div>
                                </td>
                                <td>₱<?= number_format($other_fees, 2); ?></td>
                            </tr>
                        <?php endif; ?>

                        <?php if ($base_fee <= 0 && $processing_fee <= 0 && $other_fees <= 0): ?>
                            <tr>
                                <td style="color:var(--ink-faint);">1</td>
                                <td>
                                    <div class="item-name"><?= htmlspecialchars($invoice['service_name'] ?? 'Professional Business Service'); ?></div>
                                    <div class="item-desc">Complete professional service package as agreed</div>
                                </td>
                                <td>₱<?= number_format($total_amount, 2); ?></td>
                            </tr>
                        <?php endif; ?>

                        <?php if ($discount > 0): ?>
                            <tr class="discount-row">
                                <td>—</td>
                                <td>
                                    <div class="item-name">Discount Applied</div>
                                    <div class="item-desc">Special discount granted for this transaction</div>
                                </td>
                                <td>−₱<?= number_format($discount, 2); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Totals -->
                <div class="inv-totals-wrap">
                    <div class="inv-totals">
                        <?php if ($base_fee > 0 || $processing_fee > 0): ?>
                            <div class="inv-totals-row">
                                <span>Subtotal (VAT-Inclusive)</span>
                                <span>₱<?= number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="inv-totals-row vat-row">
                                <span>VATable Sales (Excl. 12% VAT)</span>
                                <span>₱<?= number_format($vatable_sales, 2); ?></span>
                            </div>
                            <div class="inv-totals-row vat-row">
                                <span>Output VAT (12%)</span>
                                <span>₱<?= number_format($vat_amount, 2); ?></span>
                            </div>
                            <div class="inv-totals-row vat-row" style="font-size:0.75rem;">
                                <span style="color:var(--ink-faint);">Zero-Rated Sales</span>
                                <span style="color:var(--ink-faint);">₱0.00</span>
                            </div>
                            <div class="inv-totals-row vat-row" style="font-size:0.75rem;">
                                <span style="color:var(--ink-faint);">VAT-Exempt Sales</span>
                                <span style="color:var(--ink-faint);">₱0.00</span>
                            </div>
                        <?php endif; ?>
                        <?php if ($discount > 0): ?>
                            <div class="inv-totals-row discount-row">
                                <span>Discount</span>
                                <span>−₱<?= number_format($discount, 2); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="inv-totals-row total-row">
                            <span>TOTAL DUE</span>
                            <span>₱<?= number_format($total_amount, 2); ?></span>
                        </div>

                        <!-- Amount in words -->
                        <div class="inv-words">
                            <strong>Amount in Words:</strong><br>
                            <em id="amountWords">— Loading —</em> Pesos
                            <?php if (fmod($total_amount, 1) > 0): ?>
                                and <?= number_format(($total_amount - floor($total_amount)) * 100); ?>/100 Centavos
                            <?php else: ?>
                                Only
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <hr class="inv-divider">

                <!-- Payment & Terms -->
                <div class="inv-lower">
                    <div class="inv-panel">
                        <h4><i class="fas fa-money-bill-wave" style="margin-right:5px;color:var(--teal-mid);"></i> Payment Channels</h4>

                        <div class="payment-channel">
                            <div class="payment-channel-icon pci-gcash">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <div class="payment-channel-info">
                                <strong>GCash</strong>
                                <span class="acct"><?= htmlspecialchars($gcash_number); ?></span>
                                <?= htmlspecialchars($gcash_name); ?>
                            </div>
                        </div>

                        <div class="payment-channel">
                            <div class="payment-channel-icon pci-maya">
                                <i class="fas fa-wallet"></i>
                            </div>
                            <div class="payment-channel-info">
                                <strong>Maya (PayMaya)</strong>
                                <span class="acct"><?= htmlspecialchars($maya_number); ?></span>
                                <?= htmlspecialchars($maya_name); ?>
                            </div>
                        </div>

                        <div class="payment-channel">
                            <div class="payment-channel-icon pci-bank">
                                <i class="fas fa-university"></i>
                            </div>
                            <div class="payment-channel-info">
                                <strong><?= htmlspecialchars($bank_name); ?></strong>
                                <span class="acct">Acct. No. <?= htmlspecialchars($bank_account); ?></span>
                                <?= htmlspecialchars($bank_account_name); ?>
                            </div>
                        </div>

                        <p style="font-size:0.72rem;color:var(--ink-faint);margin-top:10px;line-height:1.5;">
                            <i class="fas fa-info-circle" style="margin-right:3px;"></i>
                            After payment, send proof of payment to <strong style="color:var(--ink-light);"><?= htmlspecialchars($company_email); ?></strong> with your Invoice No. as the subject.
                        </p>
                    </div>

                    <div class="inv-panel">
                        <h4><i class="fas fa-file-contract" style="margin-right:5px;color:var(--teal-mid);"></i> Terms &amp; Conditions</h4>
                        <ul class="inv-terms-list">
                            <li>Payment is due within <strong>30 days</strong> from the date of issuance.</li>
                            <li>This invoice is <strong>VAT-inclusive</strong> at the rate of 12% as required by Philippine law (NIRC, as amended by TRAIN Law).</li>
                            <li>Withholding Tax (EWT) may apply depending on the client's tax classification. BIR Form 2307 must be issued if applicable.</li>
                            <li>Services not paid within the due date may be subject to a <strong>2% monthly penalty</strong>.</li>
                            <li>This document serves as a provisional invoice. An Official Receipt (OR) will be issued upon full payment.</li>
                            <li>For disputes, contact us within <strong>5 business days</strong> of invoice issuance.</li>
                            <li>All amounts are in <strong>Philippine Peso (₱ / PHP)</strong>.</li>
                        </ul>
                    </div>
                </div>

                <!-- Signature row -->
                <div class="inv-signature-row">
                    <div class="inv-sig-block">
                        <div class="inv-sig-line"></div>
                        <div class="inv-sig-label">Authorized Signature</div>
                        <div class="inv-sig-sublabel"><?= htmlspecialchars($company_name); ?></div>
                    </div>
                    <div class="inv-sig-block">
                        <div class="inv-sig-line"></div>
                        <div class="inv-sig-label">Received By / Client Signature</div>
                        <div class="inv-sig-sublabel"><?= htmlspecialchars($invoice['client_name']); ?></div>
                    </div>
                </div>

                <!-- BIR compliance -->
                <div class="inv-compliance">
                    <strong>BIR Compliance Notice</strong><br>
                    <?= htmlspecialchars($company_bir_permit); ?> &nbsp;|&nbsp; <?= htmlspecialchars($company_sec_reg); ?> &nbsp;|&nbsp; <?= htmlspecialchars($company_bir_date); ?><br>
                    This invoice is generated in accordance with the <strong>National Internal Revenue Code (NIRC)</strong> of the Philippines, as amended by <strong>Republic Act No. 10963 (TRAIN Law)</strong>.<br>
                    THIS DOCUMENT IS NOT VALID FOR CLAIMING INPUT TAX. An Official Receipt will be issued upon full settlement of this invoice.
                </div>

            </div><!-- /inv-body -->
        </div><!-- /invoice-card -->
    </div><!-- /invoice-page -->

    <script>
        // Amount to words (Philippine Peso)
        function numberToWords(n) {
            const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
                'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
                'Seventeen', 'Eighteen', 'Nineteen'
            ];
            const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
            if (n === 0) return 'Zero';
            if (n < 0) return 'Negative ' + numberToWords(-n);
            let words = '';
            if (Math.floor(n / 1000000) > 0) {
                words += numberToWords(Math.floor(n / 1000000)) + ' Million ';
                n %= 1000000;
            }
            if (Math.floor(n / 1000) > 0) {
                words += numberToWords(Math.floor(n / 1000)) + ' Thousand ';
                n %= 1000;
            }
            if (Math.floor(n / 100) > 0) {
                words += numberToWords(Math.floor(n / 100)) + ' Hundred ';
                n %= 100;
            }
            if (n > 0) {
                if (n < 20) {
                    words += ones[n] + ' ';
                } else {
                    words += tens[Math.floor(n / 10)] + ' ';
                    if (n % 10 > 0) words += ones[n % 10] + ' ';
                }
            }
            return words.trim();
        }

        document.addEventListener('DOMContentLoaded', () => {
            const total = <?= json_encode((float)$total_amount); ?>;
            const whole = Math.floor(total);
            const el = document.getElementById('amountWords');
            if (el) el.textContent = numberToWords(whole);
        });
    </script>
</body>

</html>