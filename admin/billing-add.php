<?php
session_start();
require_once '../connection/dbconn.php';
require_once '../includes/auth.php';
require_once '../includes/activity_logger.php';


requireAdmin();

$message = '';
$message_type = '';

// Get pending inquiries count (for sidebar badge)
$stmt = $conn->prepare("SELECT COUNT(*) as pending_count FROM inquiries WHERE status = 'pending'");
$stmt->execute();
$pending_inquiries = $stmt->get_result()->fetch_assoc()['pending_count'];

// Get admin info (supports users/employees like other pages)
$is_from_employees = isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'employee';
if ($is_from_employees) {
    $stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
} else {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
}
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

if (!$admin) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

$clients = [];
$stmt = $conn->prepare("
    SELECT id, fullname, first_name, last_name
    FROM users
    ORDER BY fullname ASC, first_name ASC, last_name ASC
");
$stmt->execute();
$clients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pendingInquiries = [];
$stmt = $conn->prepare("
    SELECT i.id,
           i.inquiry_number,
           i.service_name,
           i.price,
           i.created_at,
           u.id AS user_id,
           u.fullname,
           u.first_name,
           u.last_name
    FROM inquiries i
    JOIN users u ON u.id = i.user_id
    WHERE i.status = 'pending'
    ORDER BY i.created_at DESC
");
$stmt->execute();
$pendingInquiries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$clientsJson          = json_encode($clients);
$pendingInquiriesJson = json_encode($pendingInquiries);


// Helper: generate simple invoice number (INV-YYYYMMDD-XXXX)
function generateInvoiceNumber(mysqli $conn): string
{
    $datePart = date('Ymd');
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM billings WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['c'] + 1;
    $seq = str_pad((string)$count, 4, '0', STR_PAD_LEFT);
    return "INV-{$datePart}-{$seq}";
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_invoice'])) {
    $client_name  = trim($_POST['client_name'] ?? '');
    $total_amount = trim($_POST['total_amount'] ?? '');
    $status       = $_POST['status'] ?? 'unpaid';
    $reference    = trim($_POST['reference'] ?? ''); // inquiry_number
    $invoice_date = trim($_POST['invoice_date'] ?? '');
    $due_date     = trim($_POST['due_date'] ?? '');
    $note         = trim($_POST['note'] ?? '');

    $errors = [];

    if ($client_name === '') {
        $errors[] = 'Client name is required.';
    }

    if ($reference === '') {
        $errors[] = 'Inquiry reference is required.';
    }

    // Validate amount
    if ($total_amount === '') {
        $errors[] = 'Total amount is required.';
    } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $total_amount)) {
        $errors[] = 'Total amount must be a valid number with up to 2 decimal places.';
    } else {
        $total_amount = (float)$total_amount;
        if ($total_amount < 0) {
            $errors[] = 'Total amount cannot be negative.';
        }
    }

    // Validate status
    $allowed_status = ['unpaid', 'pending', 'paid', 'cancelled'];
    if (!in_array($status, $allowed_status, true)) {
        $status = 'unpaid';
    }

    // Optional extra server-side guard: do not allow duplicate invoice for same inquiry
    if ($reference !== '') {
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS c
            FROM billings
            WHERE invoice_number = ?
              AND status IN ('paid','cancelled')
        ");
        $stmt->bind_param("s", $reference);
        $stmt->execute();
        $dupCount = (int)$stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();
        if ($dupCount > 0) {
            $errors[] = 'This inquiry already has a final invoice.';
        }
    }

    if (empty($errors)) {
        $invoice_number = generateInvoiceNumber($conn);
        $stmt = $conn->prepare("
            INSERT INTO billings (invoice_number, client_name, total_amount, status, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("ssds", $invoice_number, $client_name, $total_amount, $status);

        if ($stmt->execute()) {
            // LOG HERE: invoice successfully created
            logActivity(
                $_SESSION['user_id'],
                'admin',
                'invoice_created',
                "Invoice {$invoice_number} created for inquiry {$reference} (client: {$client_name}, amount: {$total_amount}, status: {$status})"
            );

            $_SESSION['success'] =
                "Invoice created for Inquiry #{$reference}. Invoice #: <strong>{$invoice_number}</strong>";
            header("Location: billing-admin.php");
            exit;
        } else {
            $message = 'Error creating invoice. Please try again.';
            $message_type = 'error';
        }
    } else {
        $message = implode('<br>', $errors);
        $message_type = 'error';
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Invoice - JRN Admin</title>
    <link rel="stylesheet" href="assets/css/index-admin.css">
    <link rel="stylesheet" href="assets/css/billing-admin.css">
    <link rel="stylesheet" href="assets/css/billing-add.css">
    <link rel="stylesheet" href="assets/css/logout-modal.css">
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
            <a href="billing-admin.php" class="nav-item active">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="6" rx="1" />
                    <rect x="3" y="12" width="18" height="8" rx="1" />
                    <line x1="7" y1="16" x2="11" y2="16" />
                    <line x1="7" y1="19" x2="15" y2="19" />
                </svg>
                Billing
            </a>
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
                <a href="services-admin.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="6" width="18" height="12" rx="2" />
                        <path d="M3 10h18" />
                    </svg>
                    Manage Services
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
                <a href="billing-admin.php" class="back-link">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7" />
                    </svg>
                    Back to Billing
                </a>
                <h1>Create Invoice</h1>
                <p class="header-subtitle">Add a new invoice to the billing records</p>
            </div>
            <div class="admin-header-right">
                <!-- theme toggle + avatar (unchanged) -->
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert--<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="card add-invoice-card">
            <div class="card-header">
                <h2>Invoice Details</h2>
            </div>
            <div class="card-body">
                <form method="POST" class="invoice-form">
                    <div class="form-grid-2">
                        <!-- Client select -->
                        <div class="form-group">
                            <label>Client Name <span class="required">*</span></label>
                            <select id="clientSelect" name="client_name" class="form-control form-control--lg" required>
                                <option value="" disabled <?php echo empty($_POST['client_name']) ? 'selected' : ''; ?>>
                                    Select a client
                                </option>
                                <?php
                                foreach ($clients as $c):
                                    $fullName = $c['fullname'] ?: trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
                                    if ($fullName === '') continue;
                                    $selected = (isset($_POST['client_name']) && $_POST['client_name'] === $fullName) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo htmlspecialchars($fullName); ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($fullName); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-hint">
                                You can always select any client; only their pending inquiries without a final invoice will appear below.
                            </small>
                        </div>

                        <!-- Inquiry reference select -->
                        <div class="form-group">
                            <label>Inquiry Reference <span class="required">*</span></label>
                            <select id="referenceSelect" name="reference" class="form-control" required>
                                <option value="" disabled selected>
                                    Select a client first
                                </option>
                            </select>
                            <small class="form-hint">
                                Only pending inquiries for the selected client that do not yet have a paid/cancelled invoice are shown.
                            </small>
                        </div>
                    </div>

                    <div class="form-grid-3">
                        <div class="form-group">
                            <label>Invoice Date</label>
                            <input type="date" name="invoice_date" class="form-control"
                                value="<?php echo htmlspecialchars($_POST['invoice_date'] ?? date('Y-m-d')); ?>">
                        </div>
                        <div class="form-group">
                            <label>Due Date</label>
                            <input type="date" name="due_date" class="form-control"
                                value="<?php echo htmlspecialchars($_POST['due_date'] ?? ''); ?>">
                            <small class="form-hint">Leave blank if not applicable.</small>
                        </div>
                        <div class="form-group">
                            <label>Status <span class="required">*</span></label>
                            <select name="status" class="form-control" required>
                                <?php
                                $currentStatus = $_POST['status'] ?? 'unpaid';
                                $options = [
                                    'unpaid'   => 'Unpaid',
                                    'pending'  => 'Pending',
                                    'paid'     => 'Paid',
                                    'cancelled' => 'Cancelled',
                                ];
                                foreach ($options as $value => $label) {
                                    $sel = $currentStatus === $value ? 'selected' : '';
                                    echo "<option value=\"{$value}\" {$sel}>{$label}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Total Amount (₱) <span class="required">*</span></label>
                            <input type="number" name="total_amount" class="form-control form-control--lg"
                                required step="0.01" min="0"
                                placeholder="0.00"
                                value="<?php echo htmlspecialchars($_POST['total_amount'] ?? '0.00'); ?>">
                            <small class="form-hint">
                                Grand total including taxes and fees, with 2 decimals (e.g., 1500.00).
                            </small>
                        </div>
                        <div class="form-group">
                            <label>Invoice Note</label>
                            <textarea name="note" class="form-control form-control--textarea"
                                placeholder="Optional note that will appear on the invoice (e.g., payment instructions, bank details)."><?php
                                                                                                                                        echo htmlspecialchars($_POST['note'] ?? '');
                                                                                                                                        ?></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="create_invoice" class="btn btn--primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                <polyline points="7 3 7 8 15 8"></polyline>
                            </svg>
                            Create Invoice
                        </button>
                        <a href="billing-admin.php" class="btn btn--outline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        const currentTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', currentTheme);

        const pendingInquiries = <?php echo $pendingInquiriesJson; ?>;
        const clientSelect = document.getElementById('clientSelect');
        const referenceSelect = document.getElementById('referenceSelect');

        function rebuildReferenceOptions(selectedClient) {
            referenceSelect.innerHTML = '';
            const placeholder = document.createElement('option');
            placeholder.disabled = true;
            placeholder.selected = true;
            placeholder.textContent = selectedClient ? 'Select inquiry reference' : 'Select a client first';
            referenceSelect.appendChild(placeholder);

            if (!selectedClient) return;

            const matches = pendingInquiries.filter(inq => {
                const fullName = inq.fullname || ((inq.first_name || '') + ' ' + (inq.last_name || '')).trim();
                return fullName === selectedClient;
            });

            matches.forEach(inq => {
                const opt = document.createElement('option');
                const refValue = inq.inquiry_number || inq.id;
                opt.value = refValue;
                opt.textContent = refValue + ' — ' + inq.service_name;
                referenceSelect.appendChild(opt);
            });

            <?php if (!empty($_POST['reference'])): ?>
                const prevRef = <?php echo json_encode($_POST['reference']); ?>;
                for (const option of referenceSelect.options) {
                    if (option.value === prevRef) {
                        option.selected = true;
                        break;
                    }
                }
            <?php endif; ?>
        }

        clientSelect.addEventListener('change', function() {
            rebuildReferenceOptions(this.value);
        });

        <?php if (!empty($_POST['client_name'])): ?>
            rebuildReferenceOptions(<?php echo json_encode($_POST['client_name']); ?>);
        <?php endif; ?>
    </script>
</body>

</html>