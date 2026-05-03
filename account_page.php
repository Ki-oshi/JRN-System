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

$status_display = ['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'];
$current_status = $status_display[$user['status'] ?? 'active'] ?? 'Unknown';

$status_class = ['active' => 'status-active', 'inactive' => 'status-inactive', 'suspended' => 'status-suspended'];
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
    ['name' => 'Facebook',  'icon' => 'facebook.svg',  'url' => 'https://www.facebook.com/JRNBaras'],
    ['name' => 'Twitter',   'icon' => 'twitter.svg',   'url' => '#'],
    ['name' => 'Instagram', 'icon' => 'instagram.svg', 'url' => '#']
];

// Fetch user availed services (single query with processing_type)
$user_services = [];
$stmt = $conn->prepare("
    SELECT i.id,
           i.inquiry_number,
           i.service_name,
           i.additional_notes,
           i.status,
           i.created_at,
           i.qr_code_path,
           i.processing_type,
           COUNT(d.id) AS document_count
    FROM inquiries i
    LEFT JOIN inquiry_documents d ON i.id = d.inquiry_id
    WHERE i.user_id = ?
      AND i.status IN ('pending','in_review','completed','rejected')
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

// Count by status for stats chips
$status_counts = ['pending' => 0, 'in_review' => 0, 'completed' => 0];
foreach ($user_services as $s) {
    if (isset($status_counts[$s['status']])) $status_counts[$s['status']]++;
}

// Fetch user invoices
$user_invoices = [];
$stmt = $conn->prepare("
    SELECT b.id,
           b.invoice_number,
           b.total_amount,
           b.status,
           b.created_at,
           b.service_name,
           b.base_fee,
           b.processing_fee,
           b.other_fees,
           b.discount
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

$unpaidCount = 0;
$unpaidTotal = 0.0;
foreach ($user_invoices as $inv) {
    if (in_array($inv['status'], ['unpaid', 'pending'])) {
        $unpaidCount++;
        $unpaidTotal += (float)$inv['total_amount'];
    }
}
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

    <style>
        /* ══ FOLLOW-UP UPLOAD ══ */
        .followup-upload-wrap {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .followup-info-banner {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            background: #f0f9ff;
            border: 1px solid rgba(99, 102, 241, 0.18);
            border-radius: 8px;
            padding: 10px 13px;
            font-size: 0.8rem;
            color: #3730a3;
            line-height: 1.5;
        }

        .followup-info-banner i {
            margin-top: 2px;
            flex-shrink: 0;
            color: #6366f1;
        }

        .followup-note-row label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 5px;
        }

        .followup-note-row input[type="text"] {
            width: 100%;
            padding: 8px 11px;
            border: 1.5px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.85rem;
            color: #111827;
            outline: none;
            transition: border-color .2s;
            box-sizing: border-box;
        }

        .followup-note-row input[type="text"]:focus {
            border-color: #6366f1;
        }

        .followup-drop-zone {
            border: 2px dashed #d1d5db;
            border-radius: 10px;
            padding: 22px 16px;
            text-align: center;
            cursor: pointer;
            background: #fafafa;
            transition: border-color .2s, background .2s;
        }

        .followup-drop-zone.dragover {
            border-color: #6366f1;
            background: #f5f3ff;
        }

        .followup-drop-inner {
            pointer-events: none;
        }

        .followup-drop-icon {
            font-size: 1.8rem;
            color: #a5b4fc;
            margin-bottom: 6px;
            display: block;
        }

        .followup-drop-zone p {
            font-size: 0.85rem;
            color: #374151;
            margin: 0 0 4px;
        }

        .followup-drop-zone small {
            font-size: 0.75rem;
            color: #9ca3af;
        }

        .followup-browse-link {
            color: #6366f1;
            font-weight: 700;
            cursor: pointer;
            pointer-events: all;
            text-decoration: underline dotted;
        }

        .followup-browse-link:hover {
            color: #4f46e5;
        }

        .followup-file-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .followup-file-item {
            display: flex;
            align-items: center;
            gap: 9px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 8px 11px;
            font-size: 0.8rem;
            color: #374151;
        }

        .followup-file-item i {
            color: #6366f1;
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        .followup-file-name {
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-weight: 500;
        }

        .followup-file-size {
            color: #9ca3af;
            flex-shrink: 0;
        }

        .followup-file-remove {
            background: none;
            border: none;
            color: #dc2626;
            cursor: pointer;
            padding: 0 2px;
            font-size: 0.75rem;
            opacity: 0.7;
            flex-shrink: 0;
        }

        .followup-file-remove:hover {
            opacity: 1;
        }

        .followup-submit-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .followup-status-msg {
            font-size: 0.8rem;
            font-weight: 500;
        }

        .followup-status-msg.success {
            color: #16a34a;
        }

        .followup-status-msg.error {
            color: #dc2626;
        }

        .followup-status-msg.loading {
            color: #6366f1;
        }
    </style>
</head>

<body>

    <!-- Alerts -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success" id="alertSuccess">
            <div class="alert-content">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $_SESSION['success'];
                        unset($_SESSION['success']); ?></span>
            </div>
            <button class="alert-close" onclick="closeAlert('alertSuccess')"><i class="fas fa-times"></i></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error" id="alertError">
            <div class="alert-content">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $_SESSION['error'];
                        unset($_SESSION['error']); ?></span>
            </div>
            <button class="alert-close" onclick="closeAlert('alertError')"><i class="fas fa-times"></i></button>
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
                <div class="meta-item">
                    <span>Services</span>
                    <strong><?php echo count($user_services); ?></strong>
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

            <p class="sidebar-nav-label">Navigation</p>
            <ul class="sidebar-menu">
                <li>
                    <a class="active" href="#profile" data-section="profile">
                        <i class="fas fa-user-circle"></i>
                        <span>Profile Info</span>
                    </a>
                </li>
                <li>
                    <a href="#services" data-section="services">
                        <i class="fas fa-briefcase"></i>
                        <span>My Services</span>
                        <?php if (count($user_services) > 0): ?>
                            <span class="sidebar-badge"><?php echo count($user_services); ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="#billing" data-section="billing">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span>Billing</span>
                        <?php if ($unpaidCount > 0): ?>
                            <span class="sidebar-badge"><?php echo $unpaidCount; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li><a href="inquiry_tracker.php"><i class="fas fa-map-marker-alt"></i> <span>Inquiry Tracker</span></a></li>
                <li>
                    <a href="#settings" data-section="settings">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </li>
            </ul>

            <ul class="sidebar-menu sidebar-footer">
                <li><a href="terms.php"><i class="fas fa-file-contract"></i> <span>Terms of Service</span></a></li>
                <li><a href="privacy.php"><i class="fas fa-shield-alt"></i> <span>Privacy Policy</span></a></li>
                <li><a href="#" class="report-link"><i class="fas fa-flag"></i> <span>Report an Issue</span></a></li>
            </ul>
        </aside>

        <main class="account-main">



            <!-- ══ PROFILE ══ -->
            <section class="dashboard-card active" id="profile">
                <div class="card-head">
                    <div class="card-head-meta">
                        <div class="card-head-icon"><i class="fas fa-user"></i></div>
                        <div>
                            <h3>Profile Information</h3>
                            <p class="card-desc">Manage your personal details and contact info</p>
                        </div>
                    </div>
                    <button type="button" class="btn-light" id="editProfileBtn">
                        <i class="fas fa-pen"></i> Edit Profile
                    </button>
                </div>

                <div class="card-content">
                    <form id="profileForm" method="POST" action="update_profile.php">
                        <!-- Display Mode -->
                        <div id="profileDisplay" class="profile-grid">
                            <div class="field">
                                <span>Full Name</span>
                                <strong><?php echo htmlspecialchars($display_name); ?></strong>
                            </div>
                            <div class="field">
                                <span>Username</span>
                                <strong><?php echo isset($user['username']) ? htmlspecialchars($user['username']) : 'N/A'; ?></strong>
                            </div>
                            <div class="field">
                                <span>Email Address</span>
                                <strong><?php echo isset($user['email']) ? htmlspecialchars($user['email']) : 'N/A'; ?></strong>
                            </div>
                            <div class="field">
                                <span>Phone Number</span>
                                <strong><?php echo isset($user['phone']) ? htmlspecialchars($user['phone']) : 'N/A'; ?></strong>
                            </div>
                            <div class="field full">
                                <span>Street Address</span>
                                <strong><?php echo isset($user['address']) ? htmlspecialchars($user['address']) : 'N/A'; ?></strong>
                            </div>
                            <div class="field">
                                <span>City</span>
                                <strong><?php echo isset($user['city']) ? htmlspecialchars($user['city']) : 'N/A'; ?></strong>
                            </div>
                            <div class="field">
                                <span>State / Province</span>
                                <strong><?php echo isset($user['state']) ? htmlspecialchars($user['state']) : 'N/A'; ?></strong>
                            </div>
                            <div class="field">
                                <span>Postal Code</span>
                                <strong><?php echo isset($user['postal_code']) ? htmlspecialchars($user['postal_code']) : 'N/A'; ?></strong>
                            </div>
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
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required />
                            </div>
                            <div class="input-row">
                                <label for="phone">Phone Number</label>
                                <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" />
                            </div>
                            <div class="input-row">
                                <label for="address">Street Address</label>
                                <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" />
                            </div>
                            <div class="input-grid-3">
                                <div class="input-row">
                                    <label for="city">City</label>
                                    <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>" />
                                </div>
                                <div class="input-row">
                                    <label for="state">State / Province</label>
                                    <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($user['state'] ?? ''); ?>" />
                                </div>
                                <div class="input-row">
                                    <label for="postal_code">Postal Code</label>
                                    <input type="text" id="postal_code" name="postal_code" value="<?php echo htmlspecialchars($user['postal_code'] ?? ''); ?>" />
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                                <button type="button" class="btn-light" id="cancelEditBtn"><i class="fas fa-times"></i> Cancel</button>
                            </div>
                        </div>
                    </form>
                </div>
            </section>

            <!-- ══ SERVICES ══ -->
            <section class="dashboard-card" id="services">
                <div class="card-head">
                    <div class="card-head-meta">
                        <div class="card-head-icon"><i class="fas fa-handshake"></i></div>
                        <div>
                            <h3>My Services</h3>
                            <p class="card-desc">Track all your service inquiries and their current status</p>
                        </div>
                    </div>
                    <a href="services.php" class="btn-light"><i class="fas fa-plus"></i> Inquire Now</a>
                </div>
                <div class="card-content">

                    <!-- Stats row -->
                    <?php if (count($user_services) > 0): ?>
                        <div class="services-toolbar">
                            <div class="services-stats">
                                <?php if ($status_counts['pending'] > 0): ?>
                                    <span class="stat-chip pending"><i class="fas fa-clock"></i> <?php echo $status_counts['pending']; ?> Pending</span>
                                <?php endif; ?>
                                <?php if ($status_counts['in_review'] > 0): ?>
                                    <span class="stat-chip in_review"><i class="fas fa-search"></i> <?php echo $status_counts['in_review']; ?> In Review</span>
                                <?php endif; ?>
                                <?php if ($status_counts['completed'] > 0): ?>
                                    <span class="stat-chip completed"><i class="fas fa-check-circle"></i> <?php echo $status_counts['completed']; ?> Completed</span>
                                <?php endif; ?>
                            </div>
                            <a href="inquiry_tracker.php" class="btn-light btn-xs">
                                <i class="fas fa-map-marker-alt"></i> Track an Inquiry
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if (count($user_services) > 0): ?>
                        <div class="service-grid">
                            <?php foreach ($user_services as $srv):
                                $proc_icons = [
                                    'standard'  => 'fa-clock',
                                    'priority'  => 'fa-bolt',
                                    'express'   => 'fa-shipping-fast',
                                    'rush'      => 'fa-fire',
                                    'same_day'  => 'fa-exclamation-circle',
                                ];
                                $proc_key = strtolower($srv['processing_type'] ?? 'standard');
                                $proc_icon = $proc_icons[$proc_key] ?? 'fa-clock';
                                $proc_label_map = [
                                    'standard'  => 'Standard',
                                    'priority'  => 'Priority',
                                    'express'   => 'Express',
                                    'rush'      => 'Rush',
                                    'same_day'  => 'Same-Day',
                                ];
                                $proc_display = $proc_label_map[$proc_key] ?? ucfirst($proc_key);
                            ?>
                                <div class="service-card">
                                    <div class="service-card-bar <?php echo htmlspecialchars($srv['status']); ?>"></div>
                                    <div class="service-card-inner">
                                        <div class="service-card-top">
                                            <div class="service-icon">
                                                <i class="fas fa-clipboard-check"></i>
                                            </div>
                                            <div class="service-title-row service-body">
                                                <h4><?php echo htmlspecialchars($srv['service_name']); ?></h4>
                                                <span class="status-pill <?php echo htmlspecialchars($srv['status']); ?>">
                                                    <?php
                                                    $labels = ['pending' => 'Pending', 'in_review' => 'In Review', 'completed' => 'Completed', 'rejected' => 'Rejected'];
                                                    echo $labels[$srv['status']] ?? ucfirst($srv['status']);
                                                    ?>
                                                </span>
                                            </div>
                                        </div>

                                        <?php if (!empty($srv['additional_notes'])): ?>
                                            <p class="service-notes"><?php echo nl2br(htmlspecialchars($srv['additional_notes'])); ?></p>
                                        <?php endif; ?>

                                        <div class="service-meta-grid">
                                            <div class="service-meta-item">
                                                <span>Reference #</span>
                                                <strong style="font-family:'Courier New',monospace;font-size:0.78rem;"><?php echo htmlspecialchars($srv['inquiry_number']); ?></strong>
                                            </div>
                                            <div class="service-meta-item">
                                                <span>Date Requested</span>
                                                <strong><?php echo date('M j, Y', strtotime($srv['created_at'])); ?></strong>
                                            </div>
                                            <div class="service-meta-item">
                                                <span>Processing</span>
                                                <strong>
                                                    <i class="fas <?php echo $proc_icon; ?>" style="font-size:0.72rem;margin-right:3px;"></i>
                                                    <?php echo $proc_display; ?>
                                                </strong>
                                            </div>
                                            <div class="service-meta-item">
                                                <span>Documents</span>
                                                <strong><?php echo (int)$srv['document_count']; ?> file(s)</strong>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Tracker strip -->
                                    <div class="tracker-strip">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span>Track status:</span>
                                        <a href="inquiry_tracker.php?ref=<?php echo urlencode($srv['inquiry_number']); ?>">
                                            Use reference #<?php echo htmlspecialchars($srv['inquiry_number']); ?>
                                        </a>
                                    </div>

                                    <div class="service-card-actions">
                                        <button type="button"
                                            class="view-inquiry-btn"
                                            data-inquiry-id="<?php echo (int)$srv['id']; ?>">
                                            <i class="fas fa-eye"></i> View Details
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="fas fa-briefcase"></i></div>
                            <p>No services availed yet</p>
                            <span>Once you submit an inquiry, your services will appear here.</span>
                            <a href="services.php" class="btn-primary btn-xs"><i class="fas fa-plus"></i> Browse Services</a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- ══ BILLING ══ -->
            <section class="dashboard-card" id="billing">
                <div class="card-head">
                    <div class="card-head-meta">
                        <div class="card-head-icon"><i class="fas fa-wallet"></i></div>
                        <div>
                            <h3>Billing &amp; Payments</h3>
                            <p class="card-desc">View and pay your invoices</p>
                        </div>
                    </div>
                    <?php if ($unpaidCount > 0): ?>
                        <span class="billing-summary-pill">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong><?php echo $unpaidCount; ?></strong> unpaid — <strong>₱<?php echo number_format($unpaidTotal, 2); ?></strong>
                        </span>
                    <?php elseif (count($user_invoices) > 0): ?>
                        <span class="billing-summary-pill" style="background:#f0fdf4;color:#16a34a;border-color:rgba(22,163,74,0.2);">
                            <i class="fas fa-check-circle"></i> All settled
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
                                        <th>Service</th>
                                        <th>Issued On</th>
                                        <th>Total (₱)</th>
                                        <th>Status</th>
                                        <th style="text-align:center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($user_invoices as $inv): ?>
                                        <tr>
                                            <td><span class="invoice-num"><?php echo htmlspecialchars($inv['invoice_number']); ?></span></td>
                                            <td style="font-size:0.83rem;color:#4f5d62;"><?php echo htmlspecialchars($inv['service_name'] ?? '—'); ?></td>
                                            <td style="font-size:0.85rem;"><?php echo $inv['created_at'] ? date('M j, Y', strtotime($inv['created_at'])) : '—'; ?></td>
                                            <td><span class="invoice-amount">₱<?php echo number_format((float)$inv['total_amount'], 2); ?></span></td>
                                            <td>
                                                <?php
                                                $sClass = 'status-pill ';
                                                $sClass .= match ($inv['status']) {
                                                    'paid'       => 'status-paid',
                                                    'unpaid', 'pending' => 'status-unpaid',
                                                    'cancelled'  => 'status-cancelled',
                                                    default      => 'status-other'
                                                };
                                                ?>
                                                <span class="<?php echo $sClass; ?>"><?php echo ucfirst($inv['status']); ?></span>
                                            </td>
                                            <td style="text-align:center;">
                                                <?php if (in_array($inv['status'], ['unpaid', 'pending'])): ?>
                                                    <button type="button"
                                                        class="btn-primary btn-xs billing-pay-btn"
                                                        data-invoice-id="<?php echo (int)$inv['id']; ?>"
                                                        data-invoice-number="<?php echo htmlspecialchars($inv['invoice_number']); ?>"
                                                        data-invoice-amount="<?php echo number_format((float)$inv['total_amount'], 2); ?>"
                                                        data-invoice-status="<?php echo htmlspecialchars($inv['status']); ?>"
                                                        data-invoice-service="<?php echo htmlspecialchars($inv['service_name'] ?? ''); ?>"
                                                        data-invoice-base="<?php echo number_format((float)($inv['base_fee'] ?? 0), 2); ?>"
                                                        data-invoice-proc="<?php echo number_format((float)($inv['processing_fee'] ?? 0), 2); ?>"
                                                        data-invoice-other="<?php echo number_format((float)($inv['other_fees'] ?? 0), 2); ?>"
                                                        data-invoice-discount="<?php echo number_format((float)($inv['discount'] ?? 0), 2); ?>">
                                                        <i class="fas fa-credit-card"></i> Pay
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button"
                                                        class="btn-light btn-xs billing-view-btn"
                                                        data-invoice-id="<?php echo (int)$inv['id']; ?>"
                                                        data-invoice-number="<?php echo htmlspecialchars($inv['invoice_number']); ?>"
                                                        data-invoice-amount="<?php echo number_format((float)$inv['total_amount'], 2); ?>"
                                                        data-invoice-status="<?php echo htmlspecialchars($inv['status']); ?>"
                                                        data-invoice-service="<?php echo htmlspecialchars($inv['service_name'] ?? ''); ?>"
                                                        data-invoice-base="<?php echo number_format((float)($inv['base_fee'] ?? 0), 2); ?>"
                                                        data-invoice-proc="<?php echo number_format((float)($inv['processing_fee'] ?? 0), 2); ?>"
                                                        data-invoice-other="<?php echo number_format((float)($inv['other_fees'] ?? 0), 2); ?>"
                                                        data-invoice-discount="<?php echo number_format((float)($inv['discount'] ?? 0), 2); ?>">
                                                        <i class="fas fa-eye"></i> View
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
                            <div class="empty-state-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                            <p>No invoices yet</p>
                            <span>Billing invoices will appear here once your inquiries are processed.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- ══ SETTINGS ══ -->
            <section class="dashboard-card" id="settings">
                <div class="card-head">
                    <div class="card-head-meta">
                        <div class="card-head-icon"><i class="fas fa-sliders-h"></i></div>
                        <div>
                            <h3>Account Settings</h3>
                            <p class="card-desc">Manage your security and preferences</p>
                        </div>
                    </div>
                </div>
                <div class="card-content settings-list">
                    <div class="setting-row">
                        <div class="setting-row-left">
                            <div class="setting-row-icon"><i class="fas fa-lock"></i></div>
                            <div>
                                <h4>Change Password</h4>
                                <p>Keep your account safe by updating your password regularly. We recommend a strong, unique password.</p>
                            </div>
                        </div>
                        <button type="button" class="btn-primary" id="openPasswordModal">
                            <i class="fas fa-key"></i> Change
                        </button>
                    </div>
                    <div class="settings-hint">
                        <i class="fas fa-info-circle"></i>
                        For further security assistance, please contact <a href="support.php">our support team</a>.
                    </div>
                </div>
            </section>

            <!-- ══ INQUIRY DETAILS MODAL ══ -->
            <div id="inquiryModal" style="display:none;">
                <div class="inquiry-modal">
                    <div class="inquiry-modal-header">
                        <div class="inquiry-modal-header-icon"><i class="fas fa-clipboard-list"></i></div>
                        <div style="flex:1;min-width:0;">
                            <h2>Inquiry Details</h2>
                            <div class="modal-ref" id="modal-ref-display"></div>
                        </div>
                    </div>
                    <button class="close-modal" id="closeInquiryModal" title="Close">&times;</button>

                    <div id="inquiryModalBody">
                        <!-- Service Info -->
                        <div class="inquiry-detail-section">
                            <div class="inquiry-detail-section-label">Service Information</div>
                            <div class="inquiry-detail-grid">
                                <div class="inquiry-detail-item full">
                                    <div class="d-label">Service Requested</div>
                                    <div class="d-value" id="modal-service-name">—</div>
                                </div>
                                <div class="inquiry-detail-item">
                                    <div class="d-label">Reference Number</div>
                                    <div class="d-value mono" id="modal-inquiry-number">—</div>
                                </div>
                                <div class="inquiry-detail-item">
                                    <div class="d-label">Date Requested</div>
                                    <div class="d-value" id="modal-requested-date">—</div>
                                </div>
                                <div class="inquiry-detail-item">
                                    <div class="d-label">Status</div>
                                    <div class="d-value" id="modal-status">—</div>
                                </div>
                                <div class="inquiry-detail-item">
                                    <div class="d-label">Processing Type</div>
                                    <div class="d-value" id="modal-processing-type">—</div>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="inquiry-detail-section" id="modal-notes-section">
                            <div class="inquiry-detail-section-label">Additional Notes</div>
                            <div class="inquiry-notes" id="modal-additional-notes"></div>
                        </div>

                        <!-- QR Code -->
                        <div class="inquiry-detail-section" id="modal-qr-section" style="display:none;">
                            <div class="inquiry-detail-section-label">Inquiry QR Code</div>
                            <div class="inquiry-qr-block">
                                <img id="modal-qr-img" src="" alt="QR Code" />
                                <a id="modal-qr-download" href="" download="" class="btn-light btn-xs">
                                    <i class="fas fa-download"></i> Download QR
                                </a>
                            </div>
                        </div>

                        <!-- Documents -->
                        <div class="inquiry-detail-section" id="modal-docs-section" style="display:none;">
                            <div class="inquiry-docs-title" id="modal-docs-title">Attached Documents</div>
                            <div id="modal-attached-docs"></div>
                        </div>

                        <!-- ══ FOLLOW-UP UPLOAD ══ -->
                        <div class="inquiry-detail-section" id="modal-upload-section" style="display:none;">
                            <div class="inquiry-detail-section-label">
                                <i class="fas fa-paperclip" style="margin-right:5px;color:#6366f1;"></i>
                                Submit Additional / Missing Files
                            </div>
                            <div class="followup-upload-wrap">
                                <div class="followup-info-banner">
                                    <i class="fas fa-info-circle"></i>
                                    Upload any missing documents or files requested by our team. We'll be notified immediately.
                                </div>
                                <div class="followup-note-row">
                                    <label for="followupNote">Brief note <span style="color:#9ca3af;font-weight:400;">(optional)</span></label>
                                    <input type="text" id="followupNote" maxlength="200"
                                        placeholder="e.g. Uploading the missing BIR Form 2303…" />
                                </div>
                                <div class="followup-drop-zone" id="followupDropZone">
                                    <div class="followup-drop-inner">
                                        <i class="fas fa-cloud-upload-alt followup-drop-icon"></i>
                                        <p>Drag &amp; drop files here, or
                                            <label for="followupFileInput" class="followup-browse-link">browse</label>
                                        </p>
                                        <small>PDF · JPG · PNG · DOCX &nbsp;—&nbsp; max 10 MB per file</small>
                                    </div>
                                    <input type="file" id="followupFileInput" multiple
                                        accept=".pdf,.jpg,.jpeg,.png,.docx" style="display:none;" />
                                </div>

                                <ul class="followup-file-list" id="followupFileList"></ul>

                                <div class="followup-submit-row">
                                    <button type="button" class="btn-primary btn-xs" id="followupSubmitBtn" disabled>
                                        <i class="fas fa-paper-plane"></i> Submit Files
                                    </button>
                                    <span id="followupStatusMsg" class="followup-status-msg"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Track link -->
                        <div id="modal-track-strip" style="display:none;margin-top:4px;padding:10px 12px;background:#f0f9ff;border:1px solid rgba(37,99,235,0.15);border-radius:10px;font-size:0.82rem;color:#1e40af;">
                            <i class="fas fa-map-marker-alt" style="margin-right:6px;"></i>
                            Track this inquiry anytime at
                            <a id="modal-track-link" href="#" style="color:#1d4ed8;font-weight:700;" target="_blank">Inquiry Tracker</a>
                        </div>
                    </div>

                    <div class="inquiry-modal-actions">
                        <a id="modal-track-btn" href="#" class="btn-light" target="_blank">
                            <i class="fas fa-search"></i> Track Inquiry
                        </a>
                        <button class="btn-primary" id="closeInquiryModalBtn">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>
                </div>
            </div>

            <!-- ══ BILLING MODAL ══ -->
            <div class="modal-overlay" id="billingModalOverlay" style="display:none;">
                <div class="billing-modal">
                    <div class="billing-modal-top">
                        <h2 id="billingModalTitle"><i class="fas fa-file-invoice-dollar"></i> Invoice</h2>
                        <div class="billing-modal-invoice-ref" id="bmInvoiceRef"></div>
                    </div>
                    <button class="close-modal" id="closeBillingModal">&times;</button>

                    <div class="billing-modal-body">
                        <p style="font-size:0.78rem;color:#9ca3af;margin-bottom:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;">
                            Service
                        </p>
                        <p style="font-weight:600;color:#111827;margin-bottom:16px;font-size:0.95rem;" id="bmServiceName">—</p>

                        <p style="font-size:0.78rem;color:#9ca3af;margin-bottom:8px;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;">
                            Fee Breakdown
                        </p>
                        <table class="fee-breakdown" id="bmFeeBreakdown">
                            <tbody>
                                <tr>
                                    <td class="fee-row-label">Base Service Fee</td>
                                    <td class="fee-row-amount" id="bmBaseFee">—</td>
                                </tr>
                                <tr>
                                    <td class="fee-row-label">Processing Fee</td>
                                    <td class="fee-row-amount" id="bmProcFee">—</td>
                                </tr>
                                <tr id="bmOtherRow">
                                    <td class="fee-row-label">Other Fees</td>
                                    <td class="fee-row-amount" id="bmOtherFee">—</td>
                                </tr>
                                <tr id="bmDiscountRow">
                                    <td class="fee-row-label" style="color:#16a34a;">Discount</td>
                                    <td class="fee-row-amount" style="color:#16a34a;" id="bmDiscount">—</td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="fee-total-row">
                            <span class="fee-total-label">Total Amount</span>
                            <span class="fee-total-amount">₱<span id="bmInvoiceAmount">0.00</span></span>
                        </div>

                        <p style="margin-top:10px;font-size:0.72rem;color:#9ca3af;">
                            Status: <span id="bmInvoiceStatus" style="font-weight:700;"></span>
                        </p>
                    </div>

                    <div class="billing-modal-actions" id="billingModalActions"></div>
                </div>
            </div>

            <!-- ══ PASSWORD MODAL ══ -->
            <div class="modal-overlay" id="passwordModalOverlay" style="display:none;">
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
                            <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Update Password</button>
                            <button type="button" class="btn-light" id="cancelPasswordModal">Cancel</button>
                        </div>
                    </form>
                    <div id="passwordModalMsg" style="margin-top:10px;font-size:0.85rem;color:#dc2626;"></div>
                </div>
            </div>

        </main>
    </div>

    <!-- Footer -->
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
                        <a href="<?php echo htmlspecialchars($social['url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo htmlspecialchars($social['name']); ?>">
                            <img src="assets/img/icons/<?php echo htmlspecialchars($social['icon']); ?>" alt="<?php echo htmlspecialchars($social['name']); ?>" height="24">
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="footer-links">
                <div class="footer-column">
                    <h4>Quick Access</h4>
                    <ul>
                        <?php foreach ($quickLinks as $link): ?>
                            <li><a href="<?php echo htmlspecialchars($link['url']); ?>"><?php echo htmlspecialchars($link['text']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="footer-column">
                    <h4>Resources</h4>
                    <ul>
                        <?php foreach ($resourceLinks as $link): ?>
                            <li><a href="<?php echo htmlspecialchars($link['url']); ?>"><?php echo htmlspecialchars($link['text']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date("Y"); ?> <?php echo htmlspecialchars($company_name); ?>. All Rights Reserved. |
                <a href="privacy.php">Privacy Policy</a> |
                <a href="terms.php">Terms of Service</a>
            </p>
        </div>
    </footer>

    <!-- Logout Modal -->
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

    <script src="assets/js/logout-modal.js"></script>
    <script src="assets/js/discard-modal.js"></script>

    <script>
        // ── Services data from PHP ──
        const services = <?php echo json_encode($user_services); ?>;

        // ══════════════════════════════════════════════
        //  TAB + SIDEBAR NAVIGATION
        // ══════════════════════════════════════════════
        function showSection(sectionId) {
            document.querySelectorAll('.dashboard-card').forEach(c => c.classList.remove('active'));
            const target = document.getElementById(sectionId);
            if (target) {
                target.classList.add('active');
            }

            // Update tab bar
            document.querySelectorAll('.account-tab').forEach(t => {
                t.classList.toggle('active', t.dataset.target === sectionId);
            });

            // Update sidebar
            document.querySelectorAll('.sidebar-menu a[data-section]').forEach(l => {
                l.classList.toggle('active', l.dataset.section === sectionId);
            });

            if (sectionId !== 'profile') enterDisplayMode();
        }

        document.querySelectorAll('.account-tab').forEach(tab => {
            tab.addEventListener('click', () => showSection(tab.dataset.target));
        });

        document.querySelectorAll('.sidebar-menu a[data-section]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const id = this.dataset.section;

                const editDiv = document.getElementById('profileEdit');
                const inEditMode = editDiv && editDiv.style.display === 'block';
                const hasChanges = (() => {
                    const form = document.getElementById('profileForm');
                    if (!form) return false;
                    for (let inp of form.querySelectorAll('input')) {
                        if (inp.value !== inp.defaultValue) return true;
                    }
                    return false;
                })();

                if (inEditMode && hasChanges && typeof openDiscardModal === 'function') {
                    openDiscardModal(() => {
                        document.getElementById('profileForm')?.reset();
                        enterDisplayMode();
                        showSection(id);
                    });
                } else {
                    showSection(id);
                }
            });
        });

        // Handle URL hash on load
        document.addEventListener('DOMContentLoaded', () => {
            const hash = window.location.hash.replace('#', '');
            const validSections = ['profile', 'services', 'billing', 'settings'];
            showSection(validSections.includes(hash) ? hash : 'profile');
            initializeProfileState();
        });

        // ══════════════════════════════════════════════
        //  PROFILE EDIT
        // ══════════════════════════════════════════════
        const profileForm = document.getElementById('profileForm');
        const displayDiv = document.getElementById('profileDisplay');
        const editDiv = document.getElementById('profileEdit');
        const editBtn = document.getElementById('editProfileBtn');
        const cancelBtn = document.getElementById('cancelEditBtn');

        function initializeProfileState() {
            if (displayDiv) displayDiv.style.display = 'grid';
            if (editDiv) editDiv.style.display = 'none';
            if (editBtn) editBtn.style.display = 'inline-flex';
        }

        function enterEditMode() {
            if (displayDiv) displayDiv.style.display = 'none';
            if (editDiv) editDiv.style.display = 'block';
            if (editBtn) editBtn.style.display = 'none';
        }

        function enterDisplayMode() {
            if (displayDiv) {
                displayDiv.style.display = 'grid';
                displayDiv.className = 'profile-grid';
            }
            if (editDiv) editDiv.style.display = 'none';
            if (editBtn) editBtn.style.display = 'inline-flex';
        }

        editBtn?.addEventListener('click', enterEditMode);
        cancelBtn?.addEventListener('click', () => {
            profileForm?.reset();
            enterDisplayMode();
        });

        // ══════════════════════════════════════════════
        //  INQUIRY DETAILS MODAL
        // ══════════════════════════════════════════════
        const inquiryModal = document.getElementById('inquiryModal');

        function openInquiryModal(id) {
            const srv = services.find(s => s.id == id);
            if (!srv) return;

            // Header ref
            document.getElementById('modal-ref-display').textContent = '#' + srv.inquiry_number;
            document.getElementById('modal-service-name').textContent = srv.service_name;
            document.getElementById('modal-inquiry-number').textContent = srv.inquiry_number;
            document.getElementById('modal-requested-date').textContent = new Date(srv.created_at).toLocaleDateString('en-PH', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

            // Status pill
            const statusLabels = {
                pending: 'Pending',
                in_review: 'In Review',
                completed: 'Completed',
                rejected: 'Rejected'
            };
            const statusColors = {
                pending: '#c26400',
                in_review: '#1d4ed8',
                completed: '#16a34a',
                rejected: '#dc2626'
            };
            const statusBg = {
                pending: '#fff7e6',
                in_review: '#eff6ff',
                completed: '#f0fdf4',
                rejected: '#fef2f2'
            };
            const st = srv.status;
            document.getElementById('modal-status').innerHTML =
                `<span class="status-pill ${st}" style="background:${statusBg[st]||'#f1f5f9'};color:${statusColors[st]||'#374151'};border-color:transparent;">
                ${statusLabels[st] || st}
            </span>`;

            // Processing type
            const procMap = {
                standard: 'Standard',
                priority: 'Priority',
                express: 'Express',
                rush: 'Rush',
                same_day: 'Same-Day'
            };
            const procIcons = {
                standard: 'fa-clock',
                priority: 'fa-bolt',
                express: 'fa-shipping-fast',
                rush: 'fa-fire',
                same_day: 'fa-exclamation-circle'
            };
            const procClass = {
                standard: 'standard',
                priority: 'priority',
                express: 'express',
                rush: 'rush',
                same_day: 'same_day'
            };
            const pk = srv.processing_type || 'standard';
            const plabel = procMap[pk] || pk;
            const picon = procIcons[pk] || 'fa-clock';
            const pcls = procClass[pk] || 'standard';
            document.getElementById('modal-processing-type').innerHTML =
                `<span class="processing-type ${pcls}"><i class="fas ${picon}"></i> ${plabel}</span>`;

            // Notes
            const notesEl = document.getElementById('modal-additional-notes');
            notesEl.textContent = srv.additional_notes || 'No additional notes provided.';

            // QR
            if (srv.qr_code_path) {
                document.getElementById('modal-qr-img').src = srv.qr_code_path;
                document.getElementById('modal-qr-download').href = srv.qr_code_path;
                document.getElementById('modal-qr-download').download = 'JRN-' + srv.inquiry_number + '-QR.png';
                document.getElementById('modal-qr-section').style.display = 'block';
            } else {
                document.getElementById('modal-qr-section').style.display = 'none';
            }

            // Track links
            const trackUrl = 'inquiry_tracker.php?ref=' + encodeURIComponent(srv.inquiry_number);
            document.getElementById('modal-track-link').href = trackUrl;
            document.getElementById('modal-track-strip').style.display = 'block';
            document.getElementById('modal-track-btn').href = trackUrl;

            // Docs — async fetch
            const docsSection = document.getElementById('modal-docs-section');
            const docsContainer = document.getElementById('modal-attached-docs');
            const docsTitle = document.getElementById('modal-docs-title');
            docsSection.style.display = 'none';
            docsContainer.innerHTML = '<p style="font-size:0.82rem;color:#9ca3af;">Loading documents…</p>';

            fetch(`get_my_inquiry_documents.php?inquiry_id=${id}`)
                .then(r => r.json())
                .then(docs => {
                    if (!docs.length) {
                        docsContainer.innerHTML = '<p style="font-size:0.82rem;color:#9ca3af;padding:8px 0;">No documents attached.</p>';
                    } else {
                        docsTitle.textContent = `Attached Documents (${docs.length})`;
                        docsContainer.innerHTML = docs.map(doc => {
                            const ext = (doc.file_name || '').split('.').pop().toLowerCase();
                            const iconClass = ext === 'pdf' ? 'fa-file-pdf' : (['jpg', 'jpeg', 'png', 'gif'].includes(ext) ? 'fa-file-image' : 'fa-file');
                            const sizeKb = (doc.file_size / 1024).toFixed(1);
                            return `<div class="doc-row">
                            <div style="flex:1;min-width:0;">
                                <strong><i class="fas ${iconClass}" style="margin-right:6px;color:#6b7280;font-size:0.8rem;"></i>${doc.file_label || doc.file_name}</strong>
                                <small>${sizeKb} KB · ${ext.toUpperCase()}</small>
                            </div>
                            <a href="download_document.php?id=${doc.id}" class="btn-light btn-xs" target="_blank">
                                <i class="fas fa-download"></i> Download
                            </a>
                        </div>`;
                        }).join('');
                    }
                    docsSection.style.display = 'block';
                })
                .catch(() => {
                    docsContainer.innerHTML = '<p style="font-size:0.82rem;color:#dc2626;">Could not load documents.</p>';
                    docsSection.style.display = 'block';
                });

            // Upload section — show only for pending / in_review
            const uploadSection = document.getElementById('modal-upload-section');
            uploadSection.style.display = ['pending', 'in_review'].includes(srv.status) ? 'block' : 'none';
            resetFollowupUpload(srv.id);

            // Show modal
            inquiryModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeInquiryModal() {
            inquiryModal.style.display = 'none';
            document.body.style.overflow = '';
        }

        document.querySelectorAll('.view-inquiry-btn').forEach(btn => {
            btn.addEventListener('click', () => openInquiryModal(btn.dataset.inquiryId));
        });

        document.getElementById('closeInquiryModal')?.addEventListener('click', closeInquiryModal);
        document.getElementById('closeInquiryModalBtn')?.addEventListener('click', closeInquiryModal);

        // Close on overlay click
        inquiryModal?.addEventListener('click', function(e) {
            if (e.target === inquiryModal) closeInquiryModal();
        });


        // ══════════════════════════════════════════════
        //  FOLLOW-UP FILE UPLOAD
        // ══════════════════════════════════════════════
        let followupFiles = [];
        let currentFollowupInquiryId = null;

        function resetFollowupUpload(inquiryId) {
            currentFollowupInquiryId = inquiryId;
            followupFiles = [];
            renderFollowupFileList();
            document.getElementById('followupNote').value = '';
            document.getElementById('followupStatusMsg').textContent = '';
            document.getElementById('followupStatusMsg').className = 'followup-status-msg';
            document.getElementById('followupFileInput').value = '';
        }

        function renderFollowupFileList() {
            const list = document.getElementById('followupFileList');
            const submitBtn = document.getElementById('followupSubmitBtn');
            list.innerHTML = followupFiles.map((f, i) => {
                const ext = f.name.split('.').pop().toUpperCase();
                const icon = ext === 'PDF' ? 'fa-file-pdf' : ['JPG', 'JPEG', 'PNG'].includes(ext) ? 'fa-file-image' : 'fa-file-word';
                const sizeKb = (f.size / 1024).toFixed(1);
                return `<li class="followup-file-item">
            <i class="fas ${icon}"></i>
            <span class="followup-file-name" title="${f.name}">${f.name}</span>
            <span class="followup-file-size">${sizeKb} KB</span>
            <button class="followup-file-remove" onclick="removeFollowupFile(${i})" title="Remove">
                <i class="fas fa-times"></i>
            </button>
        </li>`;
            }).join('');
            submitBtn.disabled = followupFiles.length === 0;
        }

        function removeFollowupFile(index) {
            followupFiles.splice(index, 1);
            renderFollowupFileList();
        }

        function addFollowupFiles(newFiles) {
            const allowed = ['pdf', 'jpg', 'jpeg', 'png', 'docx'];
            const maxSize = 10 * 1024 * 1024;
            const statusMsg = document.getElementById('followupStatusMsg');
            const errors = [];

            Array.from(newFiles).forEach(f => {
                const ext = f.name.split('.').pop().toLowerCase();
                if (!allowed.includes(ext)) {
                    errors.push(`"${f.name}" — unsupported type.`);
                    return;
                }
                if (f.size > maxSize) {
                    errors.push(`"${f.name}" — exceeds 10 MB.`);
                    return;
                }
                if (followupFiles.some(e => e.name === f.name && e.size === f.size)) return; // dedupe
                followupFiles.push(f);
            });

            if (errors.length) {
                statusMsg.textContent = errors[0];
                statusMsg.className = 'followup-status-msg error';
            } else {
                statusMsg.textContent = '';
                statusMsg.className = 'followup-status-msg';
            }
            renderFollowupFileList();
        }

        // File input change
        document.getElementById('followupFileInput')?.addEventListener('change', function() {
            addFollowupFiles(this.files);
            this.value = '';
        });

        // Drag & drop
        const dropZone = document.getElementById('followupDropZone');
        dropZone?.addEventListener('click', () => document.getElementById('followupFileInput').click());
        dropZone?.addEventListener('dragover', e => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });
        dropZone?.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
        dropZone?.addEventListener('drop', e => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            addFollowupFiles(e.dataTransfer.files);
        });

        // Submit
        document.getElementById('followupSubmitBtn')?.addEventListener('click', async () => {
            if (!followupFiles.length || !currentFollowupInquiryId) return;

            const submitBtn = document.getElementById('followupSubmitBtn');
            const statusMsg = document.getElementById('followupStatusMsg');

            const formData = new FormData();
            formData.append('inquiry_id', currentFollowupInquiryId);
            formData.append('note', document.getElementById('followupNote').value.trim());
            followupFiles.forEach(f => formData.append('files[]', f));

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading…';
            statusMsg.textContent = 'Sending files…';
            statusMsg.className = 'followup-status-msg loading';

            try {
                const res = await fetch('upload_followup_document.php', {
                    method: 'POST',
                    body: formData
                });
                const json = await res.json();
                if (json.success) {
                    statusMsg.textContent = `✓ ${json.message || 'Files submitted successfully!'}`;
                    statusMsg.className = 'followup-status-msg success';
                    followupFiles = [];
                    renderFollowupFileList();
                    document.getElementById('followupNote').value = '';
                    submitBtn.innerHTML = '<i class="fas fa-check"></i> Submitted';
                    // Refresh attached docs list
                    setTimeout(() => {
                        document.querySelector(`.view-inquiry-btn[data-inquiry-id="${currentFollowupInquiryId}"]`)?.click();
                    }, 1800);
                } else {
                    throw new Error(json.message || 'Upload failed.');
                }
            } catch (err) {
                statusMsg.textContent = err.message;
                statusMsg.className = 'followup-status-msg error';
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Files';
            }
        });

        // ══════════════════════════════════════════════
        //  BILLING MODAL  (with fee breakdown)
        // ══════════════════════════════════════════════
        const billingOverlay = document.getElementById('billingModalOverlay');

        function openBillingModal(type, data) {
            document.getElementById('billingModalTitle').innerHTML =
                type === 'pay' ?
                '<i class="fas fa-wallet"></i> Pay Invoice' :
                '<i class="fas fa-file-invoice"></i> Invoice Details';

            document.getElementById('bmInvoiceRef').textContent = data.number;
            document.getElementById('bmServiceName').textContent = data.service || '—';
            document.getElementById('bmInvoiceAmount').textContent = data.amount;
            document.getElementById('bmInvoiceStatus').textContent = data.statusLabel;

            // Fee breakdown
            const base = parseFloat(data.base) || 0;
            const proc = parseFloat(data.proc) || 0;
            const other = parseFloat(data.other) || 0;
            const discount = parseFloat(data.discount) || 0;

            document.getElementById('bmBaseFee').textContent = base > 0 ? '₱' + base.toFixed(2) : '—';
            document.getElementById('bmProcFee').textContent = proc > 0 ? '₱' + proc.toFixed(2) : '—';

            const otherRow = document.getElementById('bmOtherRow');
            otherRow.style.display = other > 0 ? '' : 'none';
            if (other > 0) document.getElementById('bmOtherFee').textContent = '₱' + other.toFixed(2);

            const discRow = document.getElementById('bmDiscountRow');
            discRow.style.display = discount > 0 ? '' : 'none';
            if (discount > 0) document.getElementById('bmDiscount').textContent = '-₱' + discount.toFixed(2);

            // Actions
            const actionsEl = document.getElementById('billingModalActions');
            actionsEl.innerHTML = '';
            if (type === 'pay') {
                const payBtn = document.createElement('button');
                payBtn.className = 'btn-primary';
                payBtn.innerHTML = '<i class="fas fa-credit-card"></i> Pay Now';
                payBtn.onclick = () => window.location.href = 'invoice_view.php?id=' + data.id + '&mode=pay';
                actionsEl.appendChild(payBtn);
            } else {
                const viewBtn = document.createElement('button');
                viewBtn.className = 'btn-primary';
                viewBtn.innerHTML = '<i class="fas fa-external-link-alt"></i> Open Invoice';
                viewBtn.onclick = () => window.location.href = 'invoice_view.php?id=' + data.id;
                actionsEl.appendChild(viewBtn);
            }
            const cancelBtn2 = document.createElement('button');
            cancelBtn2.className = 'btn-light';
            cancelBtn2.textContent = 'Close';
            cancelBtn2.onclick = closeBillingModal;
            actionsEl.appendChild(cancelBtn2);

            billingOverlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeBillingModal() {
            billingOverlay.style.display = 'none';
            document.body.style.overflow = '';
        }

        document.getElementById('closeBillingModal')?.addEventListener('click', closeBillingModal);
        billingOverlay?.addEventListener('click', e => {
            if (e.target === billingOverlay) closeBillingModal();
        });

        function getBillingData(btn) {
            return {
                id: btn.dataset.invoiceId,
                number: btn.dataset.invoiceNumber,
                amount: btn.dataset.invoiceAmount,
                service: btn.dataset.invoiceService,
                base: btn.dataset.invoiceBase,
                proc: btn.dataset.invoiceProc,
                other: btn.dataset.invoiceOther,
                discount: btn.dataset.invoiceDiscount,
                statusLabel: btn.dataset.invoiceStatus
            };
        }

        document.querySelectorAll('.billing-pay-btn').forEach(btn => {
            btn.addEventListener('click', () => openBillingModal('pay', getBillingData(btn)));
        });
        document.querySelectorAll('.billing-view-btn').forEach(btn => {
            btn.addEventListener('click', () => openBillingModal('view', getBillingData(btn)));
        });

        // ══════════════════════════════════════════════
        //  PASSWORD MODAL
        // ══════════════════════════════════════════════
        document.getElementById('openPasswordModal')?.addEventListener('click', () => {
            document.getElementById('passwordModalOverlay').style.display = 'flex';
        });
        document.getElementById('closePasswordModal')?.addEventListener('click', () => {
            document.getElementById('passwordModalOverlay').style.display = 'none';
            document.getElementById('changePasswordForm').reset();
            document.getElementById('passwordModalMsg').textContent = '';
        });
        document.getElementById('cancelPasswordModal')?.addEventListener('click', () => {
            document.getElementById('closePasswordModal').click();
        });
        document.getElementById('changePasswordForm')?.addEventListener('submit', function(e) {
            const np = document.getElementById('new_password').value;
            const cp = document.getElementById('confirm_password').value;
            if (np !== cp) {
                document.getElementById('passwordModalMsg').textContent = 'New passwords do not match.';
                e.preventDefault();
            }
        });

        // ══════════════════════════════════════════════
        //  ALERTS AUTO-DISMISS
        // ══════════════════════════════════════════════
        document.addEventListener('DOMContentLoaded', () => {
            ['alertSuccess', 'alertError'].forEach(id => {
                const el = document.getElementById(id);
                if (el) setTimeout(() => closeAlert(id), 5000);
            });
        });

        function closeAlert(id) {
            const el = document.getElementById(id);
            if (el) {
                el.classList.add('hide');
                setTimeout(() => el.remove(), 400);
            }
        }

        // ══════════════════════════════════════════════
        //  NAVBAR
        // ══════════════════════════════════════════════
        function toggleMenu() {
            document.querySelector('.nav-links')?.classList.toggle('active');
            document.querySelector('.hamburger')?.classList.toggle('active');
        }
    </script>
</body>

</html>