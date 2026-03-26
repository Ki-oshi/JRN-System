<?php
session_start();
require_once '../connection/dbconn.php';
require_once '../includes/auth.php';
require_once '../includes/activity_logger.php';

requireAdmin();

$message = '';
$message_type = '';
$showSuccessModal = false;
$successText = '';

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    header('Location: billing-admin.php');
    exit;
}

$invoiceId = (int)$_GET['id'];

// Get pending inquiries count (for sidebar badge)
$stmt = $conn->prepare("SELECT COUNT(*) as pending_count FROM inquiries WHERE status = 'pending'");
$stmt->execute();
$pending_inquiries = $stmt->get_result()->fetch_assoc()['pending_count'];

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

if (!$admin) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

// Fetch invoice (fresh from DB)
$stmt = $conn->prepare("SELECT * FROM billings WHERE id = ?");
$stmt->bind_param("i", $invoiceId);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();

if (!$invoice) {
    header('Location: billing-admin.php');
    exit;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {

    // Re-fetch current status from DB to be 100% sure
    $stmt = $conn->prepare("SELECT status FROM billings WHERE id = ?");
    $stmt->bind_param("i", $invoiceId);
    $stmt->execute();
    $currentRow = $stmt->get_result()->fetch_assoc();

    if (!$currentRow) {
        $message = 'Invoice not found.';
        $message_type = 'error';
    } elseif ($currentRow['status'] === 'paid') {
        // Already paid in DB: do not allow any more changes
        $message = 'This invoice has already been marked as Paid and can no longer be updated.';
        $message_type = 'error';
        $invoice['status'] = 'paid'; // sync local copy
    } else {
        $newStatus = $_POST['status'] ?? $invoice['status'];

        $allowed_status = ['unpaid', 'pending', 'paid', 'cancelled'];
        if (!in_array($newStatus, $allowed_status, true)) {
            $message = 'Invalid status selected.';
            $message_type = 'error';
        } else {
            $stmt = $conn->prepare("UPDATE billings SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $newStatus, $invoiceId);
            if ($stmt->execute()) {
                // Keep old status for logging
                $oldStatus         = $invoice['status'];
                // Update local invoice array
                $invoice['status'] = $newStatus;

                // Show success modal
                $successText      = "Invoice #{$invoice['invoice_number']} status was updated to " . ucfirst($newStatus) . ".";
                $showSuccessModal = true;
                $message          = '';
                $message_type     = '';

                // Log the change
                $user_type = (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'employee')
                    ? ((isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 'admin' : 'employee')
                    : 'admin';

                $log_description = "Invoice {$invoice['invoice_number']} status changed"
                    . " from '" . ucfirst($oldStatus) . "' to '" . ucfirst($newStatus) . "'";

                logActivity(
                    $_SESSION['user_id'],
                    $user_type,
                    'invoice_status_updated',
                    $log_description
                );
            } else {
                $message = 'Error updating invoice status. Please try again.';
                $message_type = 'error';
            }
        }
    }
}

$isPaid = ($invoice['status'] === 'paid');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Invoice - JRN Admin</title>
    <link rel="stylesheet" href="assets/css/index-admin.css">
    <link rel="stylesheet" href="assets/css/billing-admin.css">
    <link rel="stylesheet" href="assets/css/billing-add.css">
    <link rel="stylesheet" href="assets/css/billing-edit.css">
    <link rel="stylesheet" href="assets/css/logout-modal.css">
</head>

<body>
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
                <h1>Edit Invoice</h1>
                <p class="header-subtitle">Update the status of an existing invoice</p>
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

        <?php if ($message && $message_type === 'error'): ?>
            <div id="notification" class="alert alert--<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($showSuccessModal): ?>
            <div id="statusModal" class="modal active">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Status Updated</h2>
                        <button type="button" class="modal-close" id="statusModalClose">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p><?php echo htmlspecialchars($successText); ?></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn--primary" id="statusModalOk">
                            OK
                        </button>
                        <a href="billing-admin.php" class="btn btn--outline">Back to Billing</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card edit-invoice-card">
            <div class="card-header">
                <h2>Invoice #<?php echo htmlspecialchars($invoice['invoice_number']); ?></h2>
            </div>
            <div class="card-body">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Client</label>
                        <input type="text" class="form-control" disabled
                            value="<?php echo htmlspecialchars($invoice['client_name']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Total Amount</label>
                        <input type="text" class="form-control" disabled
                            value="₱<?php echo number_format((float)$invoice['total_amount'], 2); ?>">
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Created At</label>
                        <input type="text" class="form-control" disabled
                            value="<?php echo htmlspecialchars($invoice['created_at']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Last Updated</label>
                        <input type="text" class="form-control" disabled
                            value="<?php echo htmlspecialchars($invoice['updated_at'] ?? ''); ?>">
                    </div>
                </div>

                <form method="POST" class="invoice-form">
                    <div class="form-group">
                        <label>Status <span class="required">*</span></label>
                        <select name="status" class="form-control" <?php echo $isPaid ? 'disabled' : ''; ?> required>
                            <?php
                            $currentStatus = $invoice['status'];
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
                        <?php if ($isPaid): ?>
                            <small class="form-hint">Paid invoices are locked and cannot be edited.</small>
                        <?php endif; ?>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="update_status" class="btn btn--primary" <?php echo $isPaid ? 'disabled' : ''; ?>>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 21H5a 2 2 0 0 1-2-2V5a 2 2 0 0 1 2-2h11l5 5v11a 2 2 0 0 1-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                            </svg>
                            Update Status
                        </button>
                        <a href="billing-admin.php" class="btn btn--outline">Back to Billing</a>
                    </div>
                </form>
            </div>
        </div>
    </main>

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

        // Auto-hide error notification
        const notif = document.getElementById('notification');
        if (notif) {
            setTimeout(() => {
                notif.classList.add('hide');
                setTimeout(() => notif.remove(), 300);
            }, 3000);
        }

        // Success modal
        const statusModal = document.getElementById('statusModal');
        if (statusModal) {
            const closeBtn = document.getElementById('statusModalClose');
            const okBtn = document.getElementById('statusModalOk');

            function hideStatusModal() {
                statusModal.classList.remove('active');
            }

            closeBtn.addEventListener('click', hideStatusModal);
            okBtn.addEventListener('click', hideStatusModal);

            statusModal.addEventListener('click', (e) => {
                if (e.target === statusModal) hideStatusModal();
            });
        }
    </script>
</body>

</html>