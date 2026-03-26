<?php
session_start();
include 'connection/dbconn.php';
require_once 'includes/auth.php';

requireUser();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user = null;

// Check which table to query based on account_type
if (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'employee') {
    header("Location: admin/index-admin.php");
    exit;
} else {
    $stmt = $conn->prepare("
        SELECT fullname, first_name, last_name, username, email, phone, address, city, state, postal_code, 
               created_at, account_number, role, status
        FROM users WHERE id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
}

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$display_name = (!empty($user['first_name']) && !empty($user['last_name']))
    ? $user['first_name'] . ' ' . $user['last_name']
    : (!empty($user['fullname']) ? $user['fullname'] : ($user['username'] ?? 'User'));

$first_initial = !empty($user['first_name'])
    ? strtoupper(substr($user['first_name'], 0, 1))
    : strtoupper(substr($user['fullname'] ?? $user['username'] ?? 'U', 0, 1));

$status_display = [
    'active' => 'Active',
    'inactive' => 'Inactive',
    'suspended' => 'Suspended'
];
$current_status = $status_display[$user['status'] ?? 'active'] ?? 'Unknown';

$status_class = [
    'active' => 'status-active',
    'inactive' => 'status-inactive',
    'suspended' => 'status-suspended'
];
$current_status_class = $status_class[$user['status'] ?? 'active'] ?? 'status-inactive';

$company_name = 'JRN Business Solutions Co.';

$quickLinks = [
    ['text' => 'About Us', 'url' => 'index.php#about'],
    ['text' => 'Services', 'url' => 'services.php'],
];
if (isset($_SESSION['user_id'])) {
    $quickLinks[] = ['text' => 'Account', 'url' => 'account_page.php'];
} else {
    $quickLinks[] = ['text' => 'Login', 'url' => 'login.php'];
    $quickLinks[] = ['text' => 'Sign Up', 'url' => 'signup.php'];
}

$resourceLinks = [
    ['text' => 'Blog', 'url' => '#'],
    ['text' => 'FAQ', 'url' => '#'],
    ['text' => 'Support', 'url' => '#'],
    ['text' => 'Contact Us', 'url' => '#']
];
$socialLinks = [
    ['name' => 'Facebook', 'icon' => 'facebook.svg', 'url' => '#'],
    ['name' => 'Twitter', 'icon' => 'twitter.svg', 'url' => '#'],
    ['name' => 'Instagram', 'icon' => 'instagram.svg', 'url' => '#']
];

// Fetch user availed services from inquiries
$user_services = [];
$stmt = $conn->prepare("
    SELECT i.id,
           i.inquiry_number,
           i.service_name,
           i.additional_notes,
           i.status,
           i.created_at,
           i.qr_code_path,              -- add this
           COUNT(d.id) AS document_count
    FROM inquiries i
    LEFT JOIN inquiry_documents d ON i.id = d.inquiry_id
    WHERE i.user_id = ?
      AND i.status IN ('pending','in_review','completed')
    GROUP BY i.id
    ORDER BY i.created_at DESC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $user_services[] = $row;
}
$stmt->close();


// Fetch user invoices
$user_invoices = [];

// Fallback: match by client_name = display_name
$stmt = $conn->prepare("
    SELECT b.id,
           b.invoice_number,
           b.total_amount,
           b.status,
           b.created_at
    FROM billings b
    WHERE b.client_name = ?
    ORDER BY b.created_at DESC
");
$stmt->bind_param("s", $display_name);

$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $user_invoices[] = $row;
}
$stmt->close();



?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Account | JRN Business Solutions Co.</title>
    <link rel="icon" type="image/x-icon" href="assets/img/Logo.jpg" />
    <link rel="stylesheet" href="assets/css/global.css" />
    <link rel="stylesheet" href="assets/css/index.css" />
    <link rel="stylesheet" href="assets/css/responsive.css" />
    <link rel="stylesheet" href="assets/css/logout-modal.css" />
    <link rel="stylesheet" href="assets/css/discard-modal.css" />
    <link rel="stylesheet" href="assets/css/account-page.css" />
    <link rel="stylesheet" href="assets/css/password-change-modal.css">
</head>

<body>
    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success" id="alertSuccess">
            <div class="alert-content">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $_SESSION['success'];
                        unset($_SESSION['success']); ?></span>
            </div>
            <button class="alert-close" onclick="closeAlert('alertSuccess')">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error" id="alertError">
            <div class="alert-content">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $_SESSION['error'];
                        unset($_SESSION['error']); ?></span>
            </div>
            <button class="alert-close" onclick="closeAlert('alertError')">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>

    <!-- Navbar -->
    <header class="navbar">
        <div class="logo-container">
            <img src="assets/img/logo.jpg" alt="JRN Logo" class="logo-img" />
            <span class="logo-text"><?php echo htmlspecialchars($company_name); ?></span>
        </div>
        <div class="hamburger" onclick="toggleMenu()"><span></span><span></span><span></span></div>
        <nav>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="services.php">Services</a></li>
                <li><a href="index.php#about">About Us</a></li>
                <li><a class="active" href="account_page.php">Account</a></li>
                <li><a href="#" id="logout-btn">Logout</a></li>
            </ul>
        </nav>
    </header>

    <!-- Page Hero -->
    <section class="account-hero">
        <div class="account-hero-inner">
            <div class="hero-id">
                <div class="avatar-circle">
                    <span><?php echo $first_initial; ?></span>
                </div>
                <div>
                    <h1><?php echo htmlspecialchars($display_name); ?></h1>
                    <p>Account No. <strong><?php echo htmlspecialchars($user['account_number'] ?? 'N/A'); ?></strong></p>
                </div>
            </div>
            <div class="hero-meta">
                <div class="meta-item">
                    <span>Member since</span>
                    <strong><?php echo !empty($user['created_at']) ? date("F j, Y", strtotime($user['created_at'])) : '—'; ?></strong>
                </div>
                <div class="meta-item">
                    <span>Status</span>
                    <strong class="status-pill <?php echo $current_status_class; ?>"><?php echo $current_status; ?></strong>
                </div>
            </div>
        </div>
    </section>
    <div class="account-container">
        <!-- Sidebar -->
        <aside class="account-sidebar">
            <div class="sidebar-header">
                <img src="assets/img/icons/ProfileIcon.svg" alt="Profile" class="sidebar-avatar-img" width="84" height="84">
                <h3><?php echo htmlspecialchars($display_name); ?></h3>
                <p class="sidebar-email"><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></p>
            </div>

            <ul class="sidebar-menu">
                <li><a class="active" href="#profile"><i class="fas fa-user-circle"></i> Profile Info</a></li>
                <li><a href="#services"><i class="fas fa-briefcase"></i> My Services</a></li>
                <li><a href="#billing"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
                <li><a href="#settings"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>

            <ul class="sidebar-menu sidebar-footer">
                <li><a href="terms.php"><i class="fas fa-file-contract"></i> Terms of Service</a></li>
                <li><a href="privacy.php"><i class="fas fa-shield-alt"></i> Privacy Policy</a></li>
                <li><a href="#" class="report-link"><i class="fas fa-flag"></i> Report an Issue</a></li>
            </ul>
        </aside>

        <main class="account-main">
            <!-- Profile Info -->
            <section class="dashboard-card active" id="profile">
                <div class="card-head">
                    <h3><i class="fas fa-user"></i> Profile Information</h3>
                    <button type="button" class="btn-light" id="editProfileBtn">
                        <i class="fas fa-pen"></i> Edit
                    </button>
                </div>

                <div class="card-content">
                    <form id="profileForm" method="POST" action="update_profile.php">
                        <!-- Display Mode -->
                        <div id="profileDisplay" class="profile-grid">
                            <div class="field"><span>Full Name</span><strong><?php echo htmlspecialchars($display_name); ?></strong></div>
                            <div class="field"><span>Username</span><strong><?php echo isset($user['username']) && $user['username'] ? htmlspecialchars($user['username']) : 'N/A'; ?></strong></div>
                            <div class="field"><span>Email</span><strong><?php echo isset($user['email']) && $user['email'] ? htmlspecialchars($user['email']) : 'N/A'; ?></strong></div>
                            <div class="field"><span>Phone</span><strong><?php echo isset($user['phone']) && $user['phone'] ? htmlspecialchars($user['phone']) : 'N/A'; ?></strong></div>
                            <div class="field full"><span>Address</span><strong><?php echo isset($user['address']) && $user['address'] ? htmlspecialchars($user['address']) : 'N/A'; ?></strong></div>
                            <div class="field"><span>City</span><strong><?php echo isset($user['city']) && $user['city'] ? htmlspecialchars($user['city']) : 'N/A'; ?></strong></div>
                            <div class="field"><span>State</span><strong><?php echo isset($user['state']) && $user['state'] ? htmlspecialchars($user['state']) : 'N/A'; ?></strong></div>
                            <div class="field"><span>Postal Code</span><strong><?php echo isset($user['postal_code']) && $user['postal_code'] ? htmlspecialchars($user['postal_code']) : 'N/A'; ?></strong></div>
                        </div>

                        <!-- Edit Mode -->
                        <div id="profileEdit" class="profile-edit">
                            <div class="input-grid-2">
                                <div class="input-row">
                                    <label for="first_name">First Name</label>
                                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required />
                                </div>

                                <div class="input-row">
                                    <label for="last_name">Last Name</label>
                                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required />
                                </div>
                            </div>

                            <div class="input-row">
                                <label for="username">Username</label>
                                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required />
                            </div>

                            <div class="input-row">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required />
                            </div>

                            <div class="input-row">
                                <label for="phone">Phone</label>
                                <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" />
                            </div>

                            <div class="input-row">
                                <label for="address">Address</label>
                                <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" />
                            </div>

                            <div class="input-grid-3">
                                <div class="input-row">
                                    <label for="city">City</label>
                                    <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>" />
                                </div>
                                <div class="input-row">
                                    <label for="state">State</label>
                                    <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($user['state'] ?? ''); ?>" />
                                </div>
                                <div class="input-row">
                                    <label for="postal_code">Postal Code</label>
                                    <input type="text" id="postal_code" name="postal_code" value="<?php echo htmlspecialchars($user['postal_code'] ?? ''); ?>" />
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn-primary">Save Changes</button>
                                <button type="button" class="btn-light" id="cancelEditBtn">Cancel</button>
                            </div>
                        </div>
                    </form>
                </div>
            </section>

            <!-- My Services -->
            <section class="dashboard-card" id="services">
                <div class="card-head">
                    <h3><i class="fas fa-handshake"></i> My Services</h3>
                    <a href="services.php" class="btn-light"><i class="fas fa-plus"></i> Inquire Now!</a>
                </div>
                <div class="card-content">
                    <?php if (count($user_services) > 0): ?>
                        <div class="service-grid">
                            <?php foreach ($user_services as $srv): ?>
                                <div class="service-card"
                                    data-inquiry-id="<?php echo (int)$srv['id']; ?>"
                                    data-inquiry-number="<?php echo htmlspecialchars($srv['inquiry_number']); ?>"
                                    data-service-name="<?php echo htmlspecialchars($srv['service_name']); ?>"
                                    data-status="<?php echo htmlspecialchars($srv['status']); ?>"
                                    data-created-at="<?php echo htmlspecialchars($srv['created_at']); ?>"
                                    data-notes="<?php echo htmlspecialchars($srv['additional_notes'] ?? ''); ?>"
                                    data-qr="<?php echo htmlspecialchars($srv['qr_code_path'] ?? ''); ?>">
                                    <div class="service-icon"><i class="fas fa-clipboard-check"></i></div>
                                    <div class="service-body">
                                        <h4><?php echo htmlspecialchars($srv['service_name']); ?></h4>
                                        <?php if (!empty($srv['additional_notes'])): ?>
                                            <p><?php echo nl2br(htmlspecialchars($srv['additional_notes'])); ?></p>
                                        <?php endif; ?>
                                        <p class="service-detail"><strong>Status:</strong>
                                            <?php
                                            if ($srv['status'] === 'pending') echo 'Pending';
                                            elseif ($srv['status'] === 'in_review') echo 'In Review';
                                            elseif ($srv['status'] === 'completed') echo 'Completed';
                                            elseif ($srv['status'] === 'rejected') echo 'Rejected';
                                            else echo htmlspecialchars(ucfirst($srv['status']));
                                            ?>
                                        </p>
                                        <p class="service-detail"><strong>Reference #:</strong> <?php echo htmlspecialchars($srv['inquiry_number']); ?></p>
                                        <p class="service-detail"><strong>Requested:</strong> <?php echo date('F j, Y', strtotime($srv['created_at'])); ?></p>
                                    </div>
                                    <button type="button"
                                        class="btn-light btn-xs view-inquiry-btn"
                                        data-inquiry-id="<?php echo (int)$srv['id']; ?>">
                                        View Details
                                    </button>

                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-invoice-dollar"></i>
                            <p>No availed services yet</p>
                            <span>Once you avail a service, it will appear here!</span>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Billing -->
            <section class="dashboard-card" id="billing">
                <div class="card-head">
                    <h3><i class="fas fa-wallet"></i> Billing & Payments</h3>
                    <?php if (count($user_invoices) > 0): ?>
                        <span class="billing-summary-pill">
                            <?php
                            $unpaidCount = 0;
                            $unpaidTotal = 0.0;
                            foreach ($user_invoices as $inv) {
                                if (in_array($inv['status'], ['unpaid', 'pending'])) {
                                    $unpaidCount++;
                                    $unpaidTotal += (float)$inv['total_amount'];
                                }
                            }
                            ?>
                            <?php if ($unpaidCount > 0): ?>
                                You have <strong><?php echo $unpaidCount; ?></strong> unpaid invoice(s)
                                totalling <strong>₱<?php echo number_format($unpaidTotal, 2); ?></strong>.
                            <?php else: ?>
                                All invoices are settled. Thank you!
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="card-content">
                    <?php if (count($user_invoices) > 0): ?>
                        <div class="billing-table-wrapper">
                            <table class="billing-table">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Issued On</th>
                                        <th>Total (₱)</th>
                                        <th>Status</th>
                                        <th style="text-align:center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($user_invoices as $inv): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($inv['invoice_number']); ?></td>
                                            <td><?php echo $inv['created_at'] ? date('M j, Y', strtotime($inv['created_at'])) : '—'; ?></td>
                                            <td><?php echo number_format((float)$inv['total_amount'], 2); ?></td>
                                            <td>
                                                <?php
                                                $statusLabel = ucfirst($inv['status']);
                                                $statusClass = 'status-pill ';
                                                if ($inv['status'] === 'paid') {
                                                    $statusClass .= 'status-paid';
                                                } elseif (in_array($inv['status'], ['unpaid', 'pending'])) {
                                                    $statusClass .= 'status-unpaid';
                                                } elseif ($inv['status'] === 'cancelled') {
                                                    $statusClass .= 'status-cancelled';
                                                } else {
                                                    $statusClass .= 'status-other';
                                                }
                                                ?>
                                                <span class="<?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
                                            </td>
                                            <td style="text-align:center;">
                                                <?php if (in_array($inv['status'], ['unpaid', 'pending'])): ?>
                                                    <button type="button"
                                                        class="btn-primary btn-xs billing-pay-btn"
                                                        data-invoice-id="<?php echo (int)$inv['id']; ?>"
                                                        data-invoice-number="<?php echo htmlspecialchars($inv['invoice_number']); ?>"
                                                        data-invoice-amount="<?php echo number_format((float)$inv['total_amount'], 2); ?>"
                                                        data-invoice-status="<?php echo htmlspecialchars($inv['status']); ?>">
                                                        Pay
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button"
                                                        class="btn-light btn-xs billing-view-btn"
                                                        data-invoice-id="<?php echo (int)$inv['id']; ?>"
                                                        data-invoice-number="<?php echo htmlspecialchars($inv['invoice_number']); ?>"
                                                        data-invoice-amount="<?php echo number_format((float)$inv['total_amount'], 2); ?>"
                                                        data-invoice-status="<?php echo htmlspecialchars($inv['status']); ?>">
                                                        View
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-invoice-dollar"></i>
                            <p>No invoices yet</p>
                            <span>Once your inquiries are billed, invoices will appear here.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <div class="modal-overlay" id="inquiryModal" style="display:none;">
                <div class="modal-content inquiry-modal">
                    <span class="close-modal" id="closeInquiryModal">&times;</span>
                    <h2><i class="fas fa-clipboard-list"></i> Inquiry Details</h2>
                    <div id="inquiryModalBody"></div>
                    <div class="inquiry-modal-actions">
                    </div>
                </div>
            </div>

            <!-- Settings -->
            <section class="dashboard-card" id="settings">
                <div class="card-head">
                    <h3><i class="fas fa-sliders-h"></i> Account Settings</h3>
                </div>
                <div class="card-content settings-list">
                    <div class="setting-row">
                        <div>
                            <h4><i class="fas fa-lock"></i> Change Password</h4>
                            <p>Keep your account safe by updating your password regularly.</p>
                        </div>
                        <button type="button" class="btn-primary" id="openPasswordModal" style="min-width:110px;"><i class="fas fa-key"></i> Change</button>
                    </div>
                    <div class="settings-hint">
                        <i class="fas fa-info-circle"></i> For further security assistance, please contact <a href="support.php">Support</a>.
                    </div>
                </div>
            </section>


            <!-- Billing Modal -->
            <div class="modal-overlay" id="billingModalOverlay" style="display:none;">
                <div class="modal-content billing-modal">
                    <span class="close-modal" id="closeBillingModal">&times;</span>
                    <h2 id="billingModalTitle"><i class="fas fa-file-invoice-dollar"></i> Invoice</h2>

                    <div class="billing-modal-body">
                        <p><strong>Invoice #:</strong> <span id="bmInvoiceNumber"></span></p>
                        <p><strong>Status:</strong> <span id="bmInvoiceStatus"></span></p>
                        <p><strong>Total Amount:</strong> ₱<span id="bmInvoiceAmount"></span></p>
                    </div>

                    <div class="billing-modal-actions" id="billingModalActions">
                    </div>
                </div>
            </div>

            <!-- Password Change Modal -->
            <div class="modal-overlay" id="passwordModalOverlay" style="display: none;">
                <div class="modal-content">
                    <span class="close-modal" id="closePasswordModal">&times;</span>
                    <h2><i class="fas fa-key"></i> Change Password</h2>
                    <form id="changePasswordForm" action="update_password.php" method="POST">
                        <div class="input-row">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required autocomplete="current-password" />
                        </div>
                        <div class="input-row">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required autocomplete="new-password" />
                        </div>
                        <div class="input-row">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password" />
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-primary">Update Password</button>
                            <button type="button" class="btn-light" id="cancelPasswordModal">Cancel</button>
                        </div>
                    </form>
                    <div id="passwordModalMsg" style="margin-top:10px;"></div>
                </div>
            </div>
        </main>
    </div>


    <footer class="footer">
        <div class="footer-top">
            <div class="footer-logo-desc">
                <div class="footer-logo-name">
                    <img src="assets/img/logo.jpg" alt="JRN Logo" class="footer-logo">
                    <h3><?php echo htmlspecialchars($company_name); ?></h3>
                </div>
                <p>Providing end-to-end business solutions including legal documents processing, tax compliance, payroll, and accounting services to help your business grow.</p>
                <div class="footer-socials">
                    <?php foreach ($socialLinks as $social): ?>
                        <a href="<?php echo htmlspecialchars($social['url']); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="<?php echo htmlspecialchars($social['name']); ?>">
                            <img src="assets/img/icons/<?php echo htmlspecialchars($social['icon']); ?>"
                                alt="<?php echo htmlspecialchars($social['name']); ?>"
                                height="24">
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="footer-links">
                <div class="footer-column">
                    <h4>Quick Access</h4>
                    <ul>
                        <?php foreach ($quickLinks as $link): ?>
                            <li>
                                <a href="<?php echo htmlspecialchars($link['url']); ?>">
                                    <?php echo htmlspecialchars($link['text']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="footer-column">
                    <h4>Resources</h4>
                    <ul>
                        <?php foreach ($resourceLinks as $link): ?>
                            <li>
                                <a href="<?php echo htmlspecialchars($link['url']); ?>">
                                    <?php echo htmlspecialchars($link['text']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>
                &copy; <?php echo date("Y"); ?> <?php echo htmlspecialchars($company_name); ?>. All Rights Reserved. |
                <a href="privacy.php">Privacy Policy</a> |
                <a href="terms.php">Terms of Service</a>
            </p>
        </div>
    </footer>

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

    <script src="https://kit.fontawesome.com/a2e0e6d6f3.js" crossorigin="anonymous"></script>
    <script src="assets/js/logout-modal.js"></script>
    <script src="assets/js/discard-modal.js"></script>

    <!-- Inquiry Details + QR modal -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const services = <?php echo json_encode($user_services); ?>;

            const inquiryModal = document.getElementById('inquiryModal');
            const inquiryModalBody = document.getElementById('inquiryModalBody');
            const closeInquiryModal = document.getElementById('closeInquiryModal');

            document.querySelectorAll('.view-inquiry-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.dataset.inquiryId;
                    const srv = services.find(s => s.id == id);
                    if (!srv) return;

                    fetch(`get_my_inquiry_documents.php?inquiry_id=${id}`)
                        .then(r => r.json())
                        .then(docs => {
                            let docsHtml = 'No attached documents.';
                            if (docs.length) {
                                docsHtml = docs.map(doc => `
                            <div class="doc-row">
                              <div>
                                <strong>${doc.file_name}</strong>
                                <small>${(doc.file_size / 1024).toFixed(2)} KB</small>
                              </div>
                              <a href="download_document.php?id=${doc.id}"
                                 class="btn-light btn-xs" target="_blank">
                                 Download
                              </a>
                            </div>
                        `).join('');
                            }
                            let qrHtml = '';
                            if (srv.qr_code_path) {
                                qrHtml = `
        <div class="inquiry-detail-row" style="margin-top:10px;">
            <span>
            <strong>Inquiry QR Code:</strong>
                   <div class="inquiry-qr-block" style="text-align:center;">
    <img src="${srv.qr_code_path}"
         alt="Inquiry QR Code"
         style="max-width:140px;height:auto;border-radius:10px;
                border:1px solid rgba(15,58,64,0.12);
                padding:6px;background:#f8fbfb;
                display:block;margin:0 auto 6px;">

    <a href="${srv.qr_code_path}"
       download="inquiry-${srv.inquiry_number}-qr.png"
       class="btn-light btn-xs"
       style="display:inline-block;margin:0 auto;">
        Download QR Code
    </a>
</div>
            </span>
        </div>
    `;
                            }


                            inquiryModalBody.innerHTML = `
                        <div class="inquiry-detail-row">
                          <strong>Service:</strong>
                          <span>${srv.service_name}</span>
                        </div>
                        <div class="inquiry-detail-row">
                          <strong>Reference #:</strong>
                          <span>${srv.inquiry_number}</span>
                        </div>
                        <div class="inquiry-detail-row">
                          <strong>Status:</strong>
                          <span>${
                              srv.status
                                .replace('_', ' ')
                                .replace(/\\b\\w/g, c => c.toUpperCase())
                          }</span>
                        </div>
                        <div class="inquiry-detail-row">
                          <strong>Requested:</strong>
                          <span>${new Date(srv.created_at).toLocaleString()}</span>
                        </div>
                        <div class="inquiry-detail-row">
                          <span>
                            <strong>Additional Notes:</strong>
                            <div class="inquiry-notes">
                              ${srv.additional_notes || '—'}
                            </div>
                          </span>
                        </div>
                        ${qrHtml}
                        <div>
                          <div class="inquiry-docs-title">Attached Documents</div>
                          ${docsHtml}
                        </div>
                    `;

                            inquiryModal.style.display = 'flex';
                        })
                        .catch(err => console.error('Error loading docs:', err));
                });
            });

            if (closeInquiryModal) {
                closeInquiryModal.onclick = () => {
                    inquiryModal.style.display = 'none';
                };
            }
        });
    </script>

    <!-- Billing modal -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const overlay = document.getElementById('billingModalOverlay');
            const closeBtn = document.getElementById('closeBillingModal');
            const titleEl = document.getElementById('billingModalTitle');
            const numEl = document.getElementById('bmInvoiceNumber');
            const statusEl = document.getElementById('bmInvoiceStatus');
            const amountEl = document.getElementById('bmInvoiceAmount');
            const actionsEl = document.getElementById('billingModalActions');

            function openBillingModal(type, data) {
                numEl.textContent = data.number;
                amountEl.textContent = data.amount;
                statusEl.textContent = data.statusLabel;

                actionsEl.innerHTML = '';

                if (type === 'pay') {
                    titleEl.innerHTML = '<i class="fas fa-wallet"></i> Pay Invoice';

                    const payBtn = document.createElement('button');
                    payBtn.className = 'btn-primary';
                    payBtn.textContent = 'Pay Now';
                    payBtn.onclick = function() {
                        window.location.href = 'invoice_view.php?id=' + data.id + '&mode=pay';
                    };

                    const cancelBtn = document.createElement('button');
                    cancelBtn.className = 'btn-light';
                    cancelBtn.textContent = 'Cancel';
                    cancelBtn.onclick = closeBillingModal;

                    actionsEl.appendChild(payBtn);
                    actionsEl.appendChild(cancelBtn);
                } else {
                    titleEl.innerHTML = '<i class="fas fa-file-invoice"></i> View Invoice';

                    const viewBtn = document.createElement('button');
                    viewBtn.className = 'btn-primary';
                    viewBtn.textContent = 'Open Invoice';
                    viewBtn.onclick = function() {
                        window.location.href = 'invoice_view.php?id=' + data.id;
                    };

                    const closeBtn2 = document.createElement('button');
                    closeBtn2.className = 'btn-light';
                    closeBtn2.textContent = 'Close';
                    closeBtn2.onclick = closeBillingModal;

                    actionsEl.appendChild(viewBtn);
                    actionsEl.appendChild(closeBtn2);
                }

                overlay.style.display = 'flex';
            }

            function closeBillingModal() {
                overlay.style.display = 'none';
            }

            closeBtn.addEventListener('click', closeBillingModal);
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) closeBillingModal();
            });

            document.querySelectorAll('.billing-pay-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    openBillingModal('pay', {
                        id: this.dataset.invoiceId,
                        number: this.dataset.invoiceNumber,
                        amount: this.dataset.invoiceAmount,
                        statusLabel: 'Unpaid'
                    });
                });
            });

            document.querySelectorAll('.billing-view-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    openBillingModal('view', {
                        id: this.dataset.invoiceId,
                        number: this.dataset.invoiceNumber,
                        amount: this.dataset.invoiceAmount,
                        statusLabel: 'Paid'
                    });
                });
            });
        });
    </script>

    <!-- Password modal -->
    <script>
        document.getElementById('openPasswordModal').onclick = function() {
            document.getElementById('passwordModalOverlay').style.display = 'flex';
        };
        document.getElementById('closePasswordModal').onclick = function() {
            document.getElementById('passwordModalOverlay').style.display = 'none';
            document.getElementById('changePasswordForm').reset();
            document.getElementById('passwordModalMsg').textContent = '';
        };
        document.getElementById('cancelPasswordModal').onclick = function() {
            document.getElementById('closePasswordModal').click();
        };
        document.getElementById('changePasswordForm').onsubmit = function(e) {
            var np = document.getElementById('new_password').value;
            var cp = document.getElementById('confirm_password').value;
            if (np !== cp) {
                document.getElementById('passwordModalMsg').textContent = 'New passwords do not match.';
                e.preventDefault();
                return false;
            }
        };
    </script>

    <!-- Alerts auto-dismiss -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const alertSuccess = document.getElementById('alertSuccess');
            const alertError = document.getElementById('alertError');

            if (alertSuccess) {
                setTimeout(function() {
                    closeAlert('alertSuccess');
                }, 5000);
            }

            if (alertError) {
                setTimeout(function() {
                    closeAlert('alertError');
                }, 5000);
            }
        });

        function closeAlert(alertId) {
            const alert = document.getElementById(alertId);
            if (alert) {
                alert.classList.add('hide');
                setTimeout(function() {
                    alert.remove();
                }, 400);
            }
        }
    </script>

    <!-- Navigation, profile, sidebar logic -->
    <script>
        function toggleMenu() {
            const navLinks = document.querySelector('.nav-links');
            const hamburger = document.querySelector('.hamburger');
            if (navLinks) navLinks.classList.toggle('active');
            if (hamburger) hamburger.classList.toggle('active');
        }

        const editBtn = document.getElementById('editProfileBtn');
        const displayDiv = document.getElementById('profileDisplay');
        const editDiv = document.getElementById('profileEdit');
        const cancelBtn = document.getElementById('cancelEditBtn');
        const profileForm = document.getElementById('profileForm');

        function initializeProfileState() {
            if (displayDiv) {
                displayDiv.style.display = 'grid';
                if (!displayDiv.classList.contains('profile-grid')) {
                    displayDiv.classList.add('profile-grid');
                }
            }
            if (editDiv) {
                editDiv.style.display = 'none';
            }
            if (editBtn) {
                editBtn.style.display = 'inline-flex';
            }
        }

        function enterEditMode() {
            if (displayDiv) displayDiv.style.display = 'none';
            if (editDiv) editDiv.style.display = 'block';
            if (editBtn) editBtn.style.display = 'none';
        }

        function enterDisplayMode() {
            if (displayDiv) {
                displayDiv.style.display = 'grid';
                displayDiv.classList.remove("profile-edit");
                if (!displayDiv.classList.contains('profile-grid')) {
                    displayDiv.className = 'profile-grid';
                }
            }
            if (editDiv) {
                editDiv.style.display = 'none';
            }
            if (editBtn) {
                editBtn.style.display = 'inline-flex';
            }

            if (editDiv) {
                const inputs = editDiv.querySelectorAll('input');
                inputs.forEach(input => {
                    input.classList.remove('error');
                    input.blur();
                });
            }
        }

        function updateProfileDisplayFromForm() {
            if (!profileForm || !displayDiv) return;
            displayDiv.innerHTML = `
        <div class="field"><span>Full Name</span><strong>${profileForm.first_name.value} ${profileForm.last_name.value}</strong></div>
        <div class="field"><span>Username</span><strong>${profileForm.username.value}</strong></div>
        <div class="field"><span>Email</span><strong>${profileForm.email.value}</strong></div>
        <div class="field"><span>Phone</span><strong>${profileForm.phone.value || 'N/A'}</strong></div>
        <div class="field full"><span>Address</span><strong>${profileForm.address.value || 'N/A'}</strong></div>
        <div class="field"><span>City</span><strong>${profileForm.city.value || 'N/A'}</strong></div>
        <div class="field"><span>State</span><strong>${profileForm.state.value || 'N/A'}</strong></div>
        <div class="field"><span>Postal Code</span><strong>${profileForm.postal_code.value || 'N/A'}</strong></div>
    `;
            displayDiv.className = 'profile-grid';
        }

        if (editBtn) {
            editBtn.addEventListener('click', function() {
                enterEditMode();
            });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                if (profileForm) profileForm.reset();
                enterDisplayMode();
            });
        }

        /* Sidebar navigation */
        const sidebarLinks = document.querySelectorAll('.sidebar-menu a');
        const cards = document.querySelectorAll('.dashboard-card');

        function showSection(sectionId) {
            cards.forEach(card => card.classList.remove('active'));
            const targetCard = document.getElementById(sectionId);
            if (targetCard) targetCard.classList.add('active');

            if (sectionId !== 'profile') {
                enterDisplayMode();
            }
        }

        function setActiveSidebarLink(activeLink) {
            sidebarLinks.forEach(link => link.classList.remove('active'));
            if (activeLink) activeLink.classList.add('active');
        }

        function hasUnsavedChanges() {
            if (!profileForm) return false;
            const inputs = profileForm.querySelectorAll('input[type="text"], input[type="email"]');
            for (let input of inputs) {
                if (input.value !== input.defaultValue) {
                    return true;
                }
            }
            return false;
        }

        function handleSidebarClick(link, targetId) {
            const inEditMode = editDiv && editDiv.style.display === 'block';

            if (inEditMode && hasUnsavedChanges()) {
                if (typeof openDiscardModal === 'function') {
                    openDiscardModal(function() {
                        if (profileForm) profileForm.reset();
                        enterDisplayMode();
                        setActiveSidebarLink(link);
                        showSection(targetId);
                    });
                } else {
                    if (confirm('You have unsaved changes. Discard them?')) {
                        if (profileForm) profileForm.reset();
                        enterDisplayMode();
                        setActiveSidebarLink(link);
                        showSection(targetId);
                    }
                }
            } else {
                setActiveSidebarLink(link);
                showSection(targetId);
            }
        }

        sidebarLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                const href = link.getAttribute('href') || '';
                if (!href.startsWith('#')) return;
                e.preventDefault();
                const targetId = href.substring(1);
                handleSidebarClick(link, targetId);
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            initializeProfileState();
            showSection('profile');
        });

        window.addEventListener('load', function() {
            initializeProfileState();
        });
    </script>

</body>

</html>