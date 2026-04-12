<?php
session_start();
include 'connection/dbconn.php';
require_once 'includes/auth.php';

requireUser();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id      = $_SESSION['user_id'];
$company_name = 'JRN Business Solutions Co.';

// Fetch display name for sidebar
$stmt = $conn->prepare("SELECT first_name, last_name, fullname, username, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$urow = $stmt->get_result()->fetch_assoc();
$stmt->close();

$display_name = (!empty($urow['first_name']) && !empty($urow['last_name']))
    ? $urow['first_name'] . ' ' . $urow['last_name']
    : ($urow['fullname'] ?? $urow['username'] ?? 'User');

$first_initial = strtoupper(substr($urow['first_name'] ?? $urow['fullname'] ?? 'U', 0, 1));

// ── Resolve inquiry from URL ref param ──
$prefilled_ref = trim($_GET['ref'] ?? '');
$inquiry       = null;
$search_error  = '';
$documents     = [];

// Processing type meta
$proc_meta = [
    'standard'  => ['label' => 'Standard Processing',  'timeline' => '5–7 business days',   'icon' => 'fa-clock',             'bg' => '#dbeafe', 'text' => '#1e40af'],
    'priority'  => ['label' => 'Priority Processing',  'timeline' => '3–4 business days',   'icon' => 'fa-bolt',              'bg' => '#ede9fe', 'text' => '#5b21b6'],
    'express'   => ['label' => 'Express Processing',   'timeline' => '2–3 business days',   'icon' => 'fa-shipping-fast',     'bg' => '#fef3c7', 'text' => '#92400e'],
    'rush'      => ['label' => 'Rush Processing',       'timeline' => '1–2 business days',   'icon' => 'fa-fire',              'bg' => '#fee2e2', 'text' => '#991b1b'],
    'same_day'  => ['label' => 'Same-Day Priority',     'timeline' => 'Same business day',   'icon' => 'fa-exclamation-circle', 'bg' => '#fce7f3', 'text' => '#9d174d'],
];

$status_meta = [
    'pending'    => ['label' => 'Pending Review',   'icon' => 'fa-clock',       'color' => '#c26400', 'bg' => '#fff7e6', 'step' => 1],
    'in_review'  => ['label' => 'Under Review',     'icon' => 'fa-search',      'color' => '#1d4ed8', 'bg' => '#eff6ff', 'step' => 2],
    'completed'  => ['label' => 'Completed',         'icon' => 'fa-check-circle', 'color' => '#16a34a', 'bg' => '#f0fdf4', 'step' => 4],
    'rejected'   => ['label' => 'Rejected',          'icon' => 'fa-times-circle', 'color' => '#dc2626', 'bg' => '#fef2f2', 'step' => 0],
];

function lookup_inquiry($conn, $ref, $user_id)
{
    $stmt = $conn->prepare("
        SELECT i.id, i.inquiry_number, i.service_name, i.additional_notes,
               i.status, i.created_at, i.qr_code_path, i.processing_type,
               COUNT(d.id) AS document_count
        FROM inquiries i
        LEFT JOIN inquiry_documents d ON i.id = d.inquiry_id
        WHERE i.inquiry_number = ? AND i.user_id = ?
        GROUP BY i.id
        LIMIT 1
    ");
    $stmt->bind_param("si", $ref, $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row;
}

function get_inquiry_docs($conn, $inquiry_id, $user_id)
{
    // Verify ownership before fetching docs
    $stmt = $conn->prepare("
        SELECT d.id, d.file_name, d.file_size, d.file_type, d.file_label
        FROM inquiry_documents d
        INNER JOIN inquiries i ON i.id = d.inquiry_id
        WHERE d.inquiry_id = ? AND i.user_id = ?
    ");
    $stmt->bind_param("ii", $inquiry_id, $user_id);
    $stmt->execute();
    $res  = $stmt->get_result();
    $docs = [];
    while ($r = $res->fetch_assoc()) $docs[] = $r;
    $stmt->close();
    return $docs;
}

// Auto-lookup from URL param (user-scoped)
if ($prefilled_ref !== '') {
    $inquiry = lookup_inquiry($conn, $prefilled_ref, $user_id);
    if (!$inquiry) {
        $search_error = 'No inquiry found with that reference number under your account.';
    } else {
        $documents = get_inquiry_docs($conn, $inquiry['id'], $user_id);
    }
}

// POST search
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ref_number'])) {
    $ref = trim($_POST['ref_number']);
    if ($ref === '') {
        $search_error = 'Please enter a reference number.';
    } else {
        $inquiry = lookup_inquiry($conn, $ref, $user_id);
        if (!$inquiry) {
            $search_error = 'No inquiry found with that reference number under your account.';
        } else {
            $documents = get_inquiry_docs($conn, $inquiry['id'], $user_id);
        }
    }
}

$quickLinks    = [['text' => 'Home', 'url' => 'index.php'], ['text' => 'Services', 'url' => 'services.php'], ['text' => 'Account', 'url' => 'account_page.php']];
$resourceLinks = [['text' => 'FAQ', 'url' => '#'], ['text' => 'Support', 'url' => '#'], ['text' => 'Contact Us', 'url' => '#']];
$socialLinks   = [['name' => 'Facebook', 'icon' => 'facebook.svg', 'url' => 'https://www.facebook.com/JRNBaras'], ['name' => 'Twitter', 'icon' => 'twitter.svg', 'url' => '#'], ['name' => 'Instagram', 'icon' => 'instagram.svg', 'url' => '#']];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Inquiry Tracker | JRN Business Solutions Co.</title>
    <link rel="icon" type="image/x-icon" href="assets/img/Logo.jpg" />
    <link rel="stylesheet" href="assets/css/global.css" />
    <link rel="stylesheet" href="assets/css/index.css" />
    <link rel="stylesheet" href="assets/css/responsive.css" />
    <link rel="stylesheet" href="assets/css/logout-modal.css" />
    <link rel="stylesheet" href="assets/css/account-page.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        /* ── Tracker-specific overrides ── */
        .tracker-hero {
            background:
                radial-gradient(80% 120% at -10% -10%, rgba(217, 255, 0, 0.11) 0%, transparent 60%),
                linear-gradient(135deg, #0f3a40 0%, #1c4f50 100%);
            color: #fff;
            padding: 32px 10% 28px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.10);
        }

        .tracker-hero-inner {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
        }

        .tracker-hero-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: rgba(217, 255, 0, 0.15);
            border: 1px solid rgba(217, 255, 0, 0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: #d9ff00;
            flex-shrink: 0;
        }

        .tracker-hero h1 {
            font-size: 1.5rem;
            margin: 0 0 3px;
        }

        .tracker-hero p {
            font-size: 0.9rem;
            opacity: 0.75;
            margin: 0;
        }

        /* Search card */
        .search-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 28px rgba(15, 58, 64, 0.12);
            border: 1px solid rgba(15, 58, 64, 0.08);
            overflow: hidden;
            margin-bottom: 22px;
        }

        .search-card-top {
            background: linear-gradient(135deg, rgba(15, 58, 64, 0.04) 0%, rgba(15, 58, 64, 0.01) 100%);
            padding: 16px 22px 14px;
            border-bottom: 1px solid rgba(15, 58, 64, 0.07);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-card-top h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #0f3a40;
            margin: 0;
        }

        .search-form-row {
            display: flex;
            gap: 10px;
            padding: 18px 22px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .search-field {
            flex: 1;
            min-width: 220px;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .search-field label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #5a6a70;
        }

        .search-field input {
            padding: 11px 14px;
            border: 1px solid rgba(15, 58, 64, 0.18);
            border-radius: 10px;
            background: #fff;
            color: #102a2f;
            font-size: 0.95rem;
            font-family: 'Courier New', monospace;
            letter-spacing: 0.05em;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .search-field input:focus {
            border-color: #0f3a40;
            box-shadow: 0 0 0 3px rgba(15, 58, 64, 0.09);
            outline: none;
        }

        .search-btn {
            padding: 11px 22px;
            background: linear-gradient(135deg, #0f3a40 0%, #1c4f50 100%);
            color: #fff;
            font-weight: 700;
            font-size: 0.88rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: opacity 0.18s, transform 0.18s;
            display: flex;
            align-items: center;
            gap: 7px;
            flex-shrink: 0;
        }

        .search-btn:hover {
            opacity: 0.88;
            transform: translateY(-1px);
        }

        .search-error {
            margin: 0 22px 16px;
            padding: 11px 14px;
            background: #fef2f2;
            border: 1px solid rgba(220, 38, 38, 0.20);
            border-radius: 10px;
            font-size: 0.85rem;
            color: #dc2626;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Result card */
        .result-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 28px rgba(15, 58, 64, 0.12);
            border: 1px solid rgba(15, 58, 64, 0.08);
            overflow: hidden;
            animation: fadeSlideIn 0.35s ease both;
        }

        @keyframes fadeSlideIn {
            from {
                opacity: 0;
                transform: translateY(14px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .result-header {
            background: linear-gradient(135deg, #0f3a40 0%, #1c4f50 100%);
            padding: 18px 22px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .result-header-left h2 {
            color: #fff;
            font-size: 1rem;
            margin: 0 0 3px;
        }

        .result-header-left .result-ref {
            font-size: 0.78rem;
            color: rgba(255, 255, 255, 0.6);
            font-family: monospace;
        }

        /* Status badge */
        .status-badge-lg {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 16px;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 700;
        }

        /* Progress stepper */
        .progress-stepper {
            padding: 22px 22px 0;
        }

        .progress-stepper-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #9ca3af;
            margin-bottom: 14px;
        }

        .stepper-track {
            display: flex;
            align-items: flex-start;
            gap: 0;
            position: relative;
            margin-bottom: 20px;
        }

        .stepper-step {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .stepper-step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 17px;
            left: calc(50% + 17px);
            right: calc(-50% + 17px);
            height: 2px;
            background: #e5e7eb;
            z-index: 0;
        }

        .stepper-step.done:not(:last-child)::after {
            background: linear-gradient(90deg, #10b981, #34d399);
        }

        .stepper-step.current:not(:last-child)::after {
            background: linear-gradient(90deg, #3b82f6 50%, #e5e7eb 50%);
        }

        .step-circle {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #e5e7eb;
            color: #9ca3af;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            border: 3px solid #e5e7eb;
            transition: all 0.25s;
            position: relative;
            z-index: 2;
        }

        .stepper-step.done .step-circle {
            background: #10b981;
            color: #fff;
            border-color: #10b981;
        }

        .stepper-step.current .step-circle {
            background: #3b82f6;
            color: #fff;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.20);
        }

        .stepper-step.rejected .step-circle {
            background: #dc2626;
            color: #fff;
            border-color: #dc2626;
        }

        .step-label {
            font-size: 0.68rem;
            font-weight: 600;
            color: #9ca3af;
            margin-top: 6px;
            text-align: center;
            line-height: 1.3;
        }

        .stepper-step.done .step-label {
            color: #059669;
        }

        .stepper-step.current .step-label {
            color: #1d4ed8;
            font-weight: 700;
        }

        .stepper-step.rejected .step-label {
            color: #dc2626;
        }

        /* Detail grid */
        .result-body {
            padding: 18px 22px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .result-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .result-detail-item {
            background: #f8fbfb;
            border: 1px solid rgba(15, 58, 64, 0.08);
            border-radius: 10px;
            padding: 11px 14px;
        }

        .result-detail-item.full {
            grid-column: 1/-1;
        }

        .result-detail-item .rd-label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #9ca3af;
            margin-bottom: 3px;
        }

        .result-detail-item .rd-value {
            font-size: 0.9rem;
            font-weight: 600;
            color: #111827;
        }

        .result-detail-item .rd-value.mono {
            font-family: 'Courier New', monospace;
            color: #0f3a40;
        }

        /* Documents */
        .section-sub-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #9ca3af;
            margin-bottom: 10px;
            padding-bottom: 6px;
            border-bottom: 1px solid #f0f0f0;
        }

        .tracker-doc-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .tracker-doc-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            background: #f8fbfb;
            border: 1px solid rgba(15, 58, 64, 0.08);
            border-radius: 10px;
            font-size: 0.84rem;
        }

        .tracker-doc-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.82rem;
            flex-shrink: 0;
        }

        .tracker-doc-icon.pdf {
            background: #fee2e2;
            color: #dc2626;
        }

        .tracker-doc-icon.image {
            background: #ecfdf5;
            color: #059669;
        }

        .tracker-doc-icon.other {
            background: #f0f4ff;
            color: #4338ca;
        }

        .tracker-doc-name {
            font-weight: 600;
            color: #111827;
            flex: 1;
            word-break: break-all;
        }

        .tracker-doc-meta {
            font-size: 0.72rem;
            color: #9ca3af;
            white-space: nowrap;
        }

        /* QR block */
        .tracker-qr-block {
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 14px;
            background: #f8fbfb;
            border: 1px solid rgba(15, 58, 64, 0.08);
            border-radius: 12px;
            flex-wrap: wrap;
        }

        .tracker-qr-img {
            width: 96px;
            height: 96px;
            border-radius: 10px;
            border: 1px solid rgba(15, 58, 64, 0.12);
            padding: 5px;
            background: #fff;
            object-fit: contain;
            flex-shrink: 0;
        }

        .tracker-qr-info p {
            font-size: 0.8rem;
            color: #6b7280;
            margin: 0 0 8px;
            line-height: 1.5;
        }

        /* Action row */
        .result-actions {
            padding: 14px 22px 18px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            border-top: 1px solid #f0f0f0;
            background: #fafafa;
        }

        /* Empty / hint state */
        .tracker-hint {
            padding: 40px 24px;
            text-align: center;
            color: #6b7a80;
            background: #fff;
            border-radius: 16px;
            border: 2px dashed rgba(15, 58, 64, 0.12);
        }

        .tracker-hint-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background: rgba(15, 58, 64, 0.07);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            color: #0f3a40;
            margin: 0 auto 14px;
        }

        .tracker-hint h3 {
            color: #0f3a40;
            font-size: 1.05rem;
            margin: 0 0 6px;
        }

        .tracker-hint p {
            font-size: 0.85rem;
            margin: 0 0 16px;
        }

        /* Recent inquiries list */
        .recent-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .recent-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 11px 14px;
            background: #f8fbfb;
            border: 1px solid rgba(15, 58, 64, 0.08);
            border-radius: 10px;
            cursor: pointer;
            transition: border-color 0.2s, box-shadow 0.2s;
            text-decoration: none;
        }

        .recent-item:hover {
            border-color: rgba(15, 58, 64, 0.2);
            box-shadow: 0 2px 10px rgba(15, 58, 64, 0.08);
        }

        .recent-item-left {
            min-width: 0;
        }

        .recent-item-name {
            font-size: 0.88rem;
            font-weight: 700;
            color: #0f3a40;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .recent-item-ref {
            font-size: 0.73rem;
            color: #6b7280;
            font-family: monospace;
            margin-top: 2px;
        }

        @media (max-width: 640px) {
            .result-detail-grid {
                grid-template-columns: 1fr;
            }

            .stepper-step .step-label {
                display: none;
            }
        }
    </style>
</head>

<body>

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
                <li><a href="account_page.php">Account</a></li>
                <li><a href="#" id="logout-btn">Logout</a></li>
            </ul>
        </nav>
    </header>

    <!-- Hero -->
    <section class="tracker-hero">
        <div class="tracker-hero-inner">
            <div class="tracker-hero-icon"><i class="fas fa-map-marker-alt"></i></div>
            <div>
                <h1>Inquiry Tracker</h1>
                <p>Track your service inquiries using your personal reference number</p>
            </div>
            <div style="margin-left:auto;">
                <a href="account_page.php#services" class="btn-light" style="background:rgba(255,255,255,0.12);color:#fff;border-color:rgba(255,255,255,0.2);">
                    <i class="fas fa-arrow-left"></i> Back to Account
                </a>
            </div>
        </div>
    </section>

    <div class="account-container">
        <!-- Sidebar -->
        <aside class="account-sidebar">
            <div class="sidebar-header">
                <img src="assets/img/icons/ProfileIcon.svg" alt="Profile" class="sidebar-avatar-img" width="84" height="84">
                <h3><?php echo htmlspecialchars($display_name); ?></h3>
                <p class="sidebar-email"><?php echo htmlspecialchars($urow['email'] ?? 'N/A'); ?></p>
            </div>
            <p class="sidebar-nav-label">Navigation</p>
            <ul class="sidebar-menu">
                <li><a href="account_page.php" data-section="profile"><i class="fas fa-user-circle"></i> <span>Profile Info</span></a></li>
                <li><a href="account_page.php#services"><i class="fas fa-briefcase"></i> <span>My Services</span></a></li>
                <li><a href="account_page.php#billing"><i class="fas fa-file-invoice-dollar"></i> <span>Billing</span></a></li>
                <li><a class="active" href="inquiry_tracker.php"><i class="fas fa-map-marker-alt"></i> <span>Inquiry Tracker</span></a></li>
                <li><a href="account_page.php#settings"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
            </ul>
            <ul class="sidebar-menu sidebar-footer">
                <li><a href="terms.php"><i class="fas fa-file-contract"></i> <span>Terms of Service</span></a></li>
                <li><a href="privacy.php"><i class="fas fa-shield-alt"></i> <span>Privacy Policy</span></a></li>
            </ul>
        </aside>

        <main class="account-main" style="gap:20px;display:flex;flex-direction:column;">

            <!-- Search card -->
            <div class="search-card">
                <div class="search-card-top">
                    <div style="width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,#0f3a40,#1c4f50);display:flex;align-items:center;justify-content:center;color:#d9ff00;font-size:0.8rem;flex-shrink:0;">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3>Search by Reference Number</h3>
                </div>
                <form method="POST" action="inquiry_tracker.php">
                    <div class="search-form-row">
                        <div class="search-field">
                            <label for="ref_number">Your Reference Number</label>
                            <input type="text"
                                id="ref_number"
                                name="ref_number"
                                placeholder="e.g. INQ-2024-00001"
                                value="<?php echo htmlspecialchars($_POST['ref_number'] ?? $prefilled_ref); ?>"
                                autocomplete="off"
                                required />
                        </div>
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i> Track Inquiry
                        </button>
                    </div>
                    <?php if ($search_error): ?>
                        <div class="search-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($search_error); ?>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <?php if ($inquiry): ?>
                <?php
                $st_key   = $inquiry['status'];
                $st_info  = $status_meta[$st_key] ?? ['label' => ucfirst($st_key), 'icon' => 'fa-circle', 'color' => '#374151', 'bg' => '#f1f5f9', 'step' => 1];
                $pk       = strtolower($inquiry['processing_type'] ?? 'standard');
                $p_info   = $proc_meta[$pk] ?? $proc_meta['standard'];
                $current_step = $st_info['step'];

                $steps = [
                    ['label' => 'Submitted',   'icon' => 'fa-paper-plane'],
                    ['label' => 'Pending',     'icon' => 'fa-clock'],
                    ['label' => 'In Review',   'icon' => 'fa-search'],
                    ['label' => 'Processing',  'icon' => 'fa-cog'],
                    ['label' => 'Completed',   'icon' => 'fa-check-circle'],
                ];
                ?>

                <!-- Result card -->
                <div class="result-card">
                    <!-- Result header -->
                    <div class="result-header">
                        <div class="result-header-left">
                            <h2><?php echo htmlspecialchars($inquiry['service_name']); ?></h2>
                            <div class="result-ref"><?php echo htmlspecialchars($inquiry['inquiry_number']); ?></div>
                        </div>
                        <span class="status-badge-lg"
                            style="background:<?php echo $st_info['bg']; ?>;color:<?php echo $st_info['color']; ?>;">
                            <i class="fas <?php echo $st_info['icon']; ?>"></i>
                            <?php echo $st_info['label']; ?>
                        </span>
                    </div>

                    <!-- Progress stepper (not shown for rejected) -->
                    <?php if ($st_key !== 'rejected'): ?>
                        <div class="progress-stepper">
                            <div class="progress-stepper-label">Processing Progress</div>
                            <div class="stepper-track">
                                <?php foreach ($steps as $si => $step):
                                    if ($si === 0) $cls = 'done'; // always submitted
                                    elseif ($current_step === 0) $cls = '';
                                    elseif ($si < $current_step) $cls = 'done';
                                    elseif ($si === $current_step) $cls = 'current';
                                    else $cls = '';
                                ?>
                                    <div class="stepper-step <?php echo $cls; ?>">
                                        <div class="step-circle">
                                            <?php if ($cls === 'done'): ?>
                                                <i class="fas fa-check" style="font-size:0.7rem;"></i>
                                            <?php elseif ($cls === 'current'): ?>
                                                <i class="fas <?php echo $step['icon']; ?>" style="font-size:0.7rem;"></i>
                                            <?php else: ?>
                                                <?php echo $si + 1; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="step-label"><?php echo $step['label']; ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="result-body">
                        <!-- Details grid -->
                        <div>
                            <div class="section-sub-label">Inquiry Details</div>
                            <div class="result-detail-grid">
                                <div class="result-detail-item">
                                    <div class="rd-label">Reference Number</div>
                                    <div class="rd-value mono"><?php echo htmlspecialchars($inquiry['inquiry_number']); ?></div>
                                </div>
                                <div class="result-detail-item">
                                    <div class="rd-label">Date Submitted</div>
                                    <div class="rd-value"><?php echo date('F j, Y', strtotime($inquiry['created_at'])); ?></div>
                                </div>
                                <div class="result-detail-item full">
                                    <div class="rd-label">Service Requested</div>
                                    <div class="rd-value"><?php echo htmlspecialchars($inquiry['service_name']); ?></div>
                                </div>
                                <div class="result-detail-item">
                                    <div class="rd-label">Processing Type</div>
                                    <div class="rd-value">
                                        <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;font-size:0.78rem;font-weight:700;background:<?php echo $p_info['bg']; ?>;color:<?php echo $p_info['text']; ?>;">
                                            <i class="fas <?php echo $p_info['icon']; ?>"></i>
                                            <?php echo $p_info['label']; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="result-detail-item">
                                    <div class="rd-label">Est. Timeline</div>
                                    <div class="rd-value"><i class="fas fa-calendar-check" style="color:#0f3a40;margin-right:4px;"></i><?php echo $p_info['timeline']; ?></div>
                                </div>
                                <div class="result-detail-item">
                                    <div class="rd-label">Documents Attached</div>
                                    <div class="rd-value"><?php echo count($documents); ?> file(s)</div>
                                </div>
                                <?php if (!empty($inquiry['additional_notes'])): ?>
                                    <div class="result-detail-item full">
                                        <div class="rd-label">Additional Notes</div>
                                        <div class="rd-value" style="font-weight:400;line-height:1.5;white-space:pre-wrap;"><?php echo htmlspecialchars($inquiry['additional_notes']); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Documents -->
                        <?php if (count($documents) > 0): ?>
                            <div>
                                <div class="section-sub-label">Attached Documents (<?php echo count($documents); ?>)</div>
                                <div class="tracker-doc-list">
                                    <?php foreach ($documents as $doc):
                                        $ext = strtolower($doc['file_type'] ?? pathinfo($doc['file_name'] ?? '', PATHINFO_EXTENSION));
                                        $isImg = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                        $iconClass = $ext === 'pdf' ? 'fa-file-pdf' : ($isImg ? 'fa-file-image' : 'fa-file');
                                        $iconType  = $ext === 'pdf' ? 'pdf' : ($isImg ? 'image' : 'other');
                                        $sizeKb    = round($doc['file_size'] / 1024, 1);
                                        // Use human-readable label if available, else derive from service
                                        $docLabel  = !empty($doc['file_label']) ? $doc['file_label'] : $doc['file_name'];
                                    ?>
                                        <div class="tracker-doc-item">
                                            <div class="tracker-doc-icon <?php echo $iconType; ?>">
                                                <i class="fas <?php echo $iconClass; ?>"></i>
                                            </div>
                                            <span class="tracker-doc-name"><?php echo htmlspecialchars($docLabel); ?></span>
                                            <span class="tracker-doc-meta"><?php echo strtoupper($ext); ?> · <?php echo $sizeKb; ?> KB</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- QR Code -->
                        <?php if (!empty($inquiry['qr_code_path'])): ?>
                            <div>
                                <div class="section-sub-label">Inquiry QR Code</div>
                                <div class="tracker-qr-block">
                                    <img src="<?php echo htmlspecialchars($inquiry['qr_code_path']); ?>"
                                        alt="Inquiry QR Code"
                                        class="tracker-qr-img" />
                                    <div class="tracker-qr-info">
                                        <p>Scan this QR code to instantly view your inquiry details. Present to our staff for quick assistance.</p>
                                        <a href="<?php echo htmlspecialchars($inquiry['qr_code_path']); ?>"
                                            download="JRN-<?php echo htmlspecialchars($inquiry['inquiry_number']); ?>-QR.png"
                                            class="btn-light btn-xs">
                                            <i class="fas fa-download"></i> Download QR
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="result-actions">
                        <a href="account_page.php#services" class="btn-primary">
                            <i class="fas fa-briefcase"></i> Back to My Services
                        </a>
                        <a href="services.php" class="btn-light">
                            <i class="fas fa-plus"></i> New Inquiry
                        </a>
                    </div>
                </div>

            <?php elseif (!$search_error): ?>

                <!-- No search yet — show hint + recent inquiries -->
                <div class="tracker-hint">
                    <div class="tracker-hint-icon"><i class="fas fa-map-marker-alt"></i></div>
                    <h3>Enter a Reference Number to Track</h3>
                    <p>Your reference number was provided when you submitted your inquiry. You can also find it in <strong>My Services</strong>.</p>
                    <a href="account_page.php#services" class="btn-primary btn-xs">
                        <i class="fas fa-briefcase"></i> View My Services
                    </a>
                </div>

                <?php
                // Show recent inquiries as quick-access links
                $recent_stmt = $conn->prepare("
                SELECT inquiry_number, service_name, status, created_at
                FROM inquiries
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 5
            ");
                $recent_stmt->bind_param("i", $user_id);
                $recent_stmt->execute();
                $recent_result = $recent_stmt->get_result();
                $recent_inquiries = [];
                while ($r = $recent_result->fetch_assoc()) $recent_inquiries[] = $r;
                $recent_stmt->close();
                ?>

                <?php if (count($recent_inquiries) > 0): ?>
                    <div class="search-card">
                        <div class="search-card-top">
                            <div style="width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,#0f3a40,#1c4f50);display:flex;align-items:center;justify-content:center;color:#d9ff00;font-size:0.8rem;flex-shrink:0;">
                                <i class="fas fa-history"></i>
                            </div>
                            <h3>Your Recent Inquiries</h3>
                        </div>
                        <div style="padding:14px 22px 18px;">
                            <div class="recent-list">
                                <?php foreach ($recent_inquiries as $ri):
                                    $ri_st = $status_meta[$ri['status']] ?? ['label' => ucfirst($ri['status']), 'color' => '#374151', 'bg' => '#f1f5f9'];
                                ?>
                                    <a href="inquiry_tracker.php?ref=<?php echo urlencode($ri['inquiry_number']); ?>"
                                        class="recent-item">
                                        <div class="recent-item-left">
                                            <div class="recent-item-name"><?php echo htmlspecialchars($ri['service_name']); ?></div>
                                            <div class="recent-item-ref"><?php echo htmlspecialchars($ri['inquiry_number']); ?></div>
                                        </div>
                                        <div style="display:flex;align-items:center;gap:10px;flex-shrink:0;">
                                            <span class="status-pill" style="background:<?php echo $ri_st['bg']; ?>;color:<?php echo $ri_st['color']; ?>;border-color:transparent;font-size:0.75rem;">
                                                <?php echo $ri_st['label']; ?>
                                            </span>
                                            <i class="fas fa-chevron-right" style="color:#9ca3af;font-size:0.75rem;"></i>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

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
                    <ul><?php foreach ($quickLinks as $link): ?><li><a href="<?php echo htmlspecialchars($link['url']); ?>"><?php echo htmlspecialchars($link['text']); ?></a></li><?php endforeach; ?></ul>
                </div>
                <div class="footer-column">
                    <h4>Resources</h4>
                    <ul><?php foreach ($resourceLinks as $link): ?><li><a href="<?php echo htmlspecialchars($link['url']); ?>"><?php echo htmlspecialchars($link['text']); ?></a></li><?php endforeach; ?></ul>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date("Y"); ?> <?php echo htmlspecialchars($company_name); ?>. All Rights Reserved. |
                <a href="privacy.php">Privacy Policy</a> | <a href="terms.php">Terms of Service</a>
            </p>
        </div>
    </footer>

    <!-- Logout modal -->
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
    <script>
        function toggleMenu() {
            document.querySelector('.nav-links')?.classList.toggle('active');
            document.querySelector('.hamburger')?.classList.toggle('active');
        }
    </script>
</body>

</html>