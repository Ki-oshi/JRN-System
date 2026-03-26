<?php
session_start();
require_once 'connection/dbconn.php';
require_once 'includes/auth.php';

requireUser();

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    die('Invalid invoice ID.');
}

$invoice_id = (int)$_GET['id'];

// Fetch invoice by ID
$stmt = $conn->prepare("
    SELECT id, invoice_number, client_name, total_amount, status, created_at
    FROM billings
    WHERE id = ?
");
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$invoice) {
    die('Invoice not found.');
}

// Basic company info (adjust as needed)
$company_name    = 'JRN Business Solutions Co.';
$company_address = "Unit 4-7 2nd floor Ziane Commercial Building Corporation Lot 40-A\nMarcos Highway Pinugay Baras Rizal, Philippines";
$company_email   = 'support@jrnbusiness.com';
$company_phone   = '+63 900 000 0000';

// Status label class
$statusClass = 'status-pill ';
if ($invoice['status'] === 'paid') {
    $statusClass .= 'status-paid';
} elseif (in_array($invoice['status'], ['unpaid', 'pending'])) {
    $statusClass .= 'status-unpaid';
} elseif ($invoice['status'] === 'cancelled') {
    $statusClass .= 'status-cancelled';
} else {
    $statusClass .= 'status-other';
}
$statusLabel = ucfirst($invoice['status']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Invoice <?= htmlspecialchars($invoice['invoice_number']); ?> | <?= htmlspecialchars($company_name); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/account-page.css">
    <style>
        body {
            background: #f4f7f9;
        }

        .invoice-page {
            max-width: 900px;
            margin: 30px auto 40px;
            padding: 0 16px;
        }

        .invoice-card {
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 10px 24px rgba(15, 58, 64, 0.12);
            border: 1px solid rgba(15, 58, 64, 0.08);
            padding: 22px 22px 18px;
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
            border-bottom: 1px solid rgba(15, 58, 64, 0.08);
            padding-bottom: 14px;
            margin-bottom: 16px;
        }

        .invoice-brand h1 {
            margin: 0 0 4px;
            font-size: 1.3rem;
            color: var(--primary-dark);
        }

        .invoice-brand p {
            margin: 0;
            font-size: 0.88rem;
            white-space: pre-line;
            color: #5f6c72;
        }

        .invoice-meta {
            text-align: right;
            font-size: 0.9rem;
        }

        .invoice-meta h2 {
            margin: 0 0 4px;
            font-size: 1.15rem;
            color: var(--primary-dark);
        }

        .invoice-meta span {
            display: block;
            color: #5f6c72;
        }

        .invoice-section {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 18px;
            font-size: 0.9rem;
        }

        .invoice-section h3 {
            margin: 0 0 6px;
            font-size: 0.94rem;
            color: var(--primary-dark);
        }

        .invoice-section p {
            margin: 2px 0;
            color: #4f5d62;
        }

        .invoice-summary {
            margin-top: 10px;
            border-top: 1px dashed rgba(15, 58, 64, 0.2);
            padding-top: 12px;
            display: flex;
            justify-content: flex-end;
        }

        .invoice-summary-table {
            font-size: 0.9rem;
        }

        .invoice-summary-table td {
            padding: 4px 0 4px 18px;
        }

        .invoice-summary-table td.label {
            color: #6a767b;
        }

        .invoice-summary-table td.value {
            font-weight: 700;
            color: var(--primary-dark);
        }

        .invoice-actions {
            margin-top: 18px;
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

        .btn-outline {
            padding: 9px 14px;
            border-radius: 10px;
            border: 1px solid rgba(15, 58, 64, 0.18);
            background: #ffffff;
            color: var(--primary-dark);
            font-weight: 600;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .btn-outline:hover {
            background: #f3f6f6;
        }

        @media print {
            body {
                background: #ffffff;
            }

            .invoice-actions,
            .site-header,
            .navbar,
            footer {
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
            }
        }
    </style>
</head>

<body>
    <div class="invoice-page">
        <div class="invoice-card">
            <div class="invoice-header">
                <div class="invoice-brand">
                    <h1><?= htmlspecialchars($company_name); ?></h1>
                    <p><?= htmlspecialchars($company_address); ?></p>
                    <p>Email: <?= htmlspecialchars($company_email); ?> | Phone: <?= htmlspecialchars($company_phone); ?></p>
                </div>
                <div class="invoice-meta">
                    <h2>Invoice</h2>
                    <span><strong>#<?= htmlspecialchars($invoice['invoice_number']); ?></strong></span>
                    <span>Issued on: <?= $invoice['created_at'] ? date('M j, Y', strtotime($invoice['created_at'])) : '—'; ?></span>
                    <span>Status:
                        <span class="<?= $statusClass; ?>">
                            <?= htmlspecialchars($statusLabel); ?>
                        </span>
                    </span>
                </div>
            </div>

            <div class="invoice-section">
                <div>
                    <h3>Bill To</h3>
                    <p><?= htmlspecialchars($invoice['client_name']); ?></p>
                </div>
                <div>
                    <h3>Invoice Details</h3>
                    <p><strong>Invoice #:</strong> <?= htmlspecialchars($invoice['invoice_number']); ?></p>
                    <p><strong>Status:</strong> <?= htmlspecialchars($statusLabel); ?></p>
                </div>
            </div>

            <div class="invoice-summary">
                <table class="invoice-summary-table">
                    <tr>
                        <td class="label">Subtotal</td>
                        <td class="value">₱<?= number_format((float)$invoice['total_amount'], 2); ?></td>
                    </tr>
                    <tr>
                        <td class="label">Total</td>
                        <td class="value">₱<?= number_format((float)$invoice['total_amount'], 2); ?></td>
                    </tr>
                </table>
            </div>

            <div class="invoice-actions">
                <button type="button" class="btn-outline" onclick="window.location.href='account_page.php#billing';">
                    Back to Account
                </button>
                <div style="display:flex;gap:8px;">
                    <?php if (in_array($invoice['status'], ['unpaid', 'pending'])): ?>
                        <button type="button" class="btn-primary"
                            onclick="window.location.href='create_checkout.php?invoice_id=<?= (int)$invoice['id']; ?>';">
                            Pay Now
                        </button>
                    <?php endif; ?>
                    <button type="button" class="btn-light" onclick="window.print();">
                        Print
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>

</html>