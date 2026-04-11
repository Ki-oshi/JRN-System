<?php
session_start();
require_once 'connection/dbconn.php';
require_once __DIR__ . '/vendor/autoload.php';

if (!isset($_SESSION['inquiry_summary'])) {
    $_SESSION['error'] = 'Inquiry summary is not available.';
    header("Location: account_page.php");
    exit();
}

$summary = $_SESSION['inquiry_summary'];
unset($_SESSION['inquiry_summary']);

if (!isset($summary['inquiry_number'], $summary['service_name'], $summary['created_at'], $summary['uploaded_files'])) {
    $_SESSION['error'] = 'Invalid inquiry summary data.';
    header("Location: account_page.php");
    exit();
}

// ── Processing type meta ──────────────────────────────────────────────────────
$proc_meta = [
    'standard'  => ['label'=>'Standard Processing',  'timeline'=>'5–7 business days',   'icon'=>'fa-clock',             'color'=>'#2563eb','bg'=>'#dbeafe','text'=>'#1e40af'],
    'priority'  => ['label'=>'Priority Processing',  'timeline'=>'3–4 business days',   'icon'=>'fa-bolt',              'color'=>'#7c3aed','bg'=>'#ede9fe','text'=>'#5b21b6'],
    'express'   => ['label'=>'Express Processing',   'timeline'=>'2–3 business days',   'icon'=>'fa-shipping-fast',     'color'=>'#d97706','bg'=>'#fef3c7','text'=>'#92400e'],
    'rush'      => ['label'=>'Rush Processing',       'timeline'=>'1–2 business days',   'icon'=>'fa-fire',              'color'=>'#dc2626','bg'=>'#fee2e2','text'=>'#991b1b'],
    'same_day'  => ['label'=>'Same-Day Priority',     'timeline'=>'Same business day',   'icon'=>'fa-exclamation-circle','color'=>'#991b1b','bg'=>'#fce7f3','text'=>'#9d174d'],
];

$proc_key = $summary['processing_type_key'] ?? 'standard';
if (!isset($proc_meta[$proc_key])) {
    foreach ($proc_meta as $k => $v) {
        if ($v['label'] === ($summary['processing_type'] ?? '')) { $proc_key = $k; break; }
    }
}
$proc_info     = $proc_meta[$proc_key] ?? $proc_meta['standard'];
$proc_label    = $summary['processing_type']  ?? $proc_info['label'];
$proc_timeline = $summary['proc_timeline']    ?? $proc_info['timeline'];
$final_price   = $summary['final_price']      ?? 0;
$price_display = '₱' . number_format((float)$final_price, 2);

// ── Document label helper ────────────────────────────────────────────────────
// Generates a human-readable label for a document based on service or slug
function generate_doc_label($file, $service_name, $index) {
    // If a label was already assigned (from the form), use it
    if (!empty($file['file_label'])) return $file['file_label'];

    $original = $file['original_name'] ?? $file['file_name'] ?? '';
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));

    // Map common service slug keywords → friendly label prefixes
    $slug = strtolower($service_name ?? '');
    $labelMap = [
        'bir'               => 'BIR Document',
        'tax'               => 'Tax Document',
        'payroll'           => 'Payroll Record',
        'business permit'   => 'Business Permit',
        'permit'            => 'Permit Document',
        'registration'      => 'Registration Document',
        'sec'               => 'SEC Document',
        'dti'               => 'DTI Document',
        'accounting'        => 'Financial Record',
        'audit'             => 'Audit Document',
        'contract'          => 'Contract Document',
        'legal'             => 'Legal Document',
        'notarial'          => 'Notarial Document',
        'affidavit'         => 'Affidavit',
        'deed'              => 'Deed of Sale',
        'incorporation'     => 'Incorporation Document',
        'annual report'     => 'Annual Report',
        'financial'         => 'Financial Statement',
        'payable'           => 'Payables Record',
        'receivable'        => 'Receivables Record',
        'invoice'           => 'Invoice Document',
        'certificate'       => 'Certificate',
        'clearance'         => 'Clearance Document',
    ];

    $matched = 'Supporting Document';
    foreach ($labelMap as $keyword => $label) {
        if (str_contains($slug, $keyword)) { $matched = $label; break; }
    }

    // Add sequence if multiple docs
    $suffix = $index > 1 ? " {$index}" : '';
    return "{$matched}{$suffix}";
}

// ── QR code generation ───────────────────────────────────────────────────────
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . $_SERVER['HTTP_HOST'];
$baseUrl .= rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$payload  = $baseUrl . '/inquiry_tracker.php?ref=' . urlencode($summary['inquiry_number']);

$qrDir = __DIR__ . '/uploads/qrcodes/';
if (!is_dir($qrDir)) mkdir($qrDir, 0755, true);

$qrFilename  = 'inquiry_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $summary['inquiry_number']) . '.png';
$qrSavePath  = $qrDir . $qrFilename;
$qrPublicUrl = 'uploads/qrcodes/' . $qrFilename;

$apiUrl = 'https://api.qrserver.com/v1/create-qr-code/?'
    . http_build_query([
        'size'           => '200x200',
        'data'           => $payload,
        'color'          => '0F3A40',
        'bgcolor'        => 'FFFFFF',
        'charset-source' => 'UTF-8',
        'charset-target' => 'UTF-8',
        'margin'         => '10',
    ]);

$pngData = @file_get_contents($apiUrl);
if ($pngData !== false) file_put_contents($qrSavePath, $pngData);

$update = $conn->prepare("UPDATE inquiries SET qr_code_path = ? WHERE inquiry_number = ?");
$update->bind_param("ss", $qrPublicUrl, $summary['inquiry_number']);
$update->execute();
$update->close();

// ── What Happens Next steps ──────────────────────────────────────────────────
$steps = [
    [
        'num'  => '1',
        'icon' => 'fa-search',
        'title'=> 'Documents Review',
        'desc' => 'Our team will carefully review your submitted documents and inquiry details. This usually takes 1 business day.',
        'color'=> '#2563eb',
        'bg'   => '#eff6ff',
    ],
    [
        'num'  => '2',
        'icon' => 'fa-file-invoice',
        'title'=> 'Billing Generated',
        'desc' => 'A quotation and billing invoice will be created and will appear in your Account → Billing section for your review.',
        'color'=> '#7c3aed',
        'bg'   => '#f5f3ff',
    ],
    [
        'num'  => '3',
        'icon' => 'fa-cog',
        'title'=> 'Processing Begins',
        'desc' => 'Once you have reviewed and confirmed the billing, our team begins processing your request within the selected timeline.',
        'color'=> '#d97706',
        'bg'   => '#fffbeb',
    ],
    [
        'num'  => '4',
        'icon' => 'fa-check-circle',
        'title'=> 'Service Completed',
        'desc' => 'You will be notified by email once your request is fully completed. Your documents will be made available for pickup or delivery.',
        'color'=> '#16a34a',
        'bg'   => '#f0fdf4',
    ],
];

// Company info
$companyInfo   = ['name'=>'JRN Business Solutions Co.','tagline'=>'Your Partner in Business Compliance and Growth'];
$quickLinks    = [['text'=>'Home','url'=>'index.php'],['text'=>'Services','url'=>'services.php'],['text'=>'About Us','url'=>'index.php#about']];
if (isset($_SESSION['user_id'])) $quickLinks[] = ['text'=>'Account','url'=>'account_page.php'];
else { $quickLinks[] = ['text'=>'Login','url'=>'login.php']; $quickLinks[] = ['text'=>'Sign Up','url'=>'signup.php']; }
$resourceLinks = [['text'=>'FAQ','url'=>'#faq'],['text'=>'Support','url'=>'#support'],['text'=>'Contact Us','url'=>'#contact'],['text'=>'Privacy','url'=>'privacy.php']];
$socialLinks   = [['name'=>'Facebook','icon'=>'facebook.svg','url'=>'#'],['name'=>'Twitter','icon'=>'twitter.svg','url'=>'#'],['name'=>'Instagram','icon'=>'instagram.svg','url'=>'#']];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Inquiry Submitted – <?php echo htmlspecialchars($summary['inquiry_number']); ?> | JRN Business Solutions Co.</title>
    <link rel="icon" type="image/x-icon" href="assets/img/Logo.jpg" />
    <link rel="stylesheet" href="assets/css/global.css" />
    <link rel="stylesheet" href="assets/css/index.css" />
    <link rel="stylesheet" href="assets/css/account-page.css" />
    <link rel="stylesheet" href="assets/css/inquiry-summary.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
    /* ── Shared page vars ── */
    :root {
        --brand-dark: #0F3A40;
        --brand-mid:  #1C4F50;
        --brand-acc:  #D9FF00;
    }

    /* ── Hero ── */
    .summary-hero {
        background: linear-gradient(135deg, var(--brand-dark) 0%, var(--brand-mid) 55%, #0a2b30 100%);
        padding: 3.5rem 1.5rem 5.5rem;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    .summary-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(ellipse 60% 50% at 20% 40%, rgba(217,255,0,0.08) 0%, transparent 70%),
            radial-gradient(ellipse 50% 60% at 80% 70%, rgba(255,255,255,0.04) 0%, transparent 70%);
        pointer-events: none;
    }
    .hero-badge {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        background: rgba(217,255,0,.15);
        border: 1px solid rgba(217,255,0,.35);
        color: var(--brand-acc);
        font-size: .75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .1em;
        padding: .4rem 1rem;
        border-radius: 999px;
        margin-bottom: 1.25rem;
        animation: fadeInDown .5s ease both;
    }
    .hero-success-icon {
        width: 72px; height: 72px;
        background: rgba(217,255,0,.15);
        border: 2px solid rgba(217,255,0,.4);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 1.25rem;
        font-size: 2rem; color: var(--brand-acc);
        animation: popIn .5s cubic-bezier(.175,.885,.32,1.275) .1s both;
    }
    .hero-title {
        color: #fff;
        font-size: clamp(1.6rem, 3vw, 2.4rem);
        font-weight: 800;
        margin: 0 0 .6rem;
        animation: fadeInUp .5s ease .15s both;
    }
    .hero-subtitle {
        color: rgba(255,255,255,.7);
        font-size: 1rem;
        margin: 0 0 1.5rem;
        animation: fadeInUp .5s ease .2s both;
    }
    .hero-ref {
        display: inline-flex;
        align-items: center;
        gap: .6rem;
        background: rgba(255,255,255,.1);
        border: 1px solid rgba(255,255,255,.2);
        border-radius: 10px;
        padding: .6rem 1.25rem;
        color: #fff;
        font-size: .85rem;
        animation: fadeInUp .5s ease .25s both;
    }
    .hero-ref code {
        color: var(--brand-acc);
        font-size: 1rem;
        font-weight: 700;
        font-family: 'Courier New', monospace;
        letter-spacing: .05em;
    }
    .copy-btn {
        cursor: pointer;
        background: rgba(217,255,0,.2);
        border: none;
        color: var(--brand-acc);
        border-radius: 6px;
        padding: .25rem .6rem;
        font-size: .7rem;
        font-weight: 700;
        transition: background .15s;
    }
    .copy-btn:hover { background: rgba(217,255,0,.35); }

    /* ── Layout ── */
    .summary-page-wrap {
        max-width: 1080px;
        margin: -2.5rem auto 3rem;
        padding: 0 1.25rem;
        display: grid;
        grid-template-columns: 1fr 340px;
        gap: 1.25rem;
        align-items: start;
    }
    @media (max-width: 820px) {
        .summary-page-wrap { grid-template-columns: 1fr; margin-top: -1.5rem; }
    }

    .left-stack { display: flex; flex-direction: column; gap: 1.25rem; }

    /* ── Cards ── */
    .summ-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(15,58,64,.07);
        overflow: hidden;
    }
    .summ-card-header {
        display: flex;
        align-items: center;
        gap: .65rem;
        padding: 1.1rem 1.4rem;
        border-bottom: 1px solid #f0f0f0;
        background: #fafafa;
    }
    .summ-card-header .card-icon {
        width: 32px; height: 32px;
        border-radius: 8px;
        background: linear-gradient(135deg, var(--brand-dark), var(--brand-mid));
        display: flex; align-items: center; justify-content: center;
        color: var(--brand-acc);
        font-size: .8rem;
        flex-shrink: 0;
    }
    .summ-card-header h3 { font-size: .9rem; font-weight: 700; color: var(--brand-dark); margin: 0; }
    .summ-card-body { padding: 1.25rem 1.4rem; }

    /* ── Detail grid ── */
    .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem 1.25rem; }
    .detail-item { display: flex; flex-direction: column; gap: .2rem; }
    .detail-item.full { grid-column: 1/-1; }
    .detail-label { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #9ca3af; }
    .detail-value { font-size: .92rem; font-weight: 600; color: #111827; }
    .detail-value.mono { font-family: 'Courier New', monospace; color: var(--brand-dark); font-size: .95rem; }
    .detail-value.accent { color: var(--brand-dark); }

    /* Proc pill */
    .proc-pill-lg {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        padding: .45rem 1rem;
        border-radius: 999px;
        font-size: .8rem;
        font-weight: 700;
    }

    /* Fee strip */
    .fee-strip {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: linear-gradient(135deg, var(--brand-dark) 0%, var(--brand-mid) 100%);
        border-radius: 12px;
        padding: 1rem 1.25rem;
        margin-top: 1rem;
        flex-wrap: wrap;
        gap: .5rem;
    }
    .fee-strip .fee-label  { font-size: .75rem; color: rgba(255,255,255,.65); font-weight: 600; text-transform: uppercase; letter-spacing: .06em; }
    .fee-strip .fee-type   { color: #fff; font-size: .9rem; font-weight: 700; }
    .fee-strip .fee-amount { font-size: 1.5rem; font-weight: 800; color: var(--brand-acc); }
    .fee-strip .fee-note   { font-size: .68rem; color: rgba(255,255,255,.5); }

    /* ── Documents ── */
    .doc-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: .6rem; }
    .doc-item {
        display: flex; align-items: center; gap: .75rem;
        padding: .65rem .85rem;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        font-size: .82rem;
    }
    .doc-item .doc-icon {
        width: 32px; height: 32px;
        border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        font-size: .76rem;
        flex-shrink: 0;
        background: #e0f2fe; color: #0369a1;
    }
    .doc-item .doc-icon.pdf  { background: #fee2e2; color: #dc2626; }
    .doc-item .doc-icon.img  { background: #ecfdf5; color: #059669; }
    .doc-name { font-weight: 600; color: #374151; flex: 1; }
    .doc-sub  { font-size: .7rem; color: #9ca3af; display: block; margin-top: 1px; }
    .doc-meta { font-size: .7rem; color: #9ca3af; white-space: nowrap; }

    /* ── What Happens Next — Redesigned ── */
    .next-steps-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 14px;
        margin-bottom: 1.1rem;
    }
    @media (max-width: 580px) { .next-steps-grid { grid-template-columns: 1fr; } }

    .next-step-card {
        border-radius: 12px;
        padding: 14px 16px;
        border: 1px solid transparent;
        position: relative;
        transition: transform .2s, box-shadow .2s;
    }
    .next-step-card:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(15,58,64,.10); }

    .next-step-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 8px;
    }
    .next-step-num {
        width: 26px; height: 26px;
        border-radius: 50%;
        background: var(--brand-dark);
        color: var(--brand-acc);
        display: flex; align-items: center; justify-content: center;
        font-size: .72rem;
        font-weight: 800;
        flex-shrink: 0;
    }
    .next-step-icon {
        width: 34px; height: 34px;
        border-radius: 9px;
        display: flex; align-items: center; justify-content: center;
        font-size: .82rem;
        flex-shrink: 0;
    }
    .next-step-title {
        font-size: .88rem;
        font-weight: 700;
        color: #111827;
        line-height: 1.2;
    }
    .next-step-desc {
        font-size: .78rem;
        color: #6b7280;
        line-height: 1.55;
        margin: 0;
    }

    /* Info banner */
    .track-info-banner {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 13px 16px;
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 11px;
        margin-bottom: 1rem;
    }
    .track-info-banner i { color: #16a34a; font-size: 1rem; flex-shrink: 0; margin-top: 1px; }
    .track-info-banner p { font-size: .82rem; color: #166534; line-height: 1.55; margin: 0; }
    .track-info-banner strong { color: #14532d; }

    .cta-row {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: .65rem;
    }

    /* ── QR card ── */
    .qr-card { position: sticky; top: 1.5rem; }
    .qr-body { padding: 1.4rem; text-align: center; }
    .qr-frame {
        width: 176px; height: 176px;
        border: 3px solid var(--brand-dark);
        border-radius: 16px;
        padding: 8px;
        margin: 0 auto 1rem;
        display: flex; align-items: center; justify-content: center;
        background: #fff;
    }
    .qr-frame img { width: 100%; height: 100%; object-fit: contain; border-radius: 8px; }
    .qr-ref {
        font-size: .7rem;
        font-family: 'Courier New', monospace;
        color: #6b7280;
        background: #f3f4f6;
        border-radius: 6px;
        padding: .35rem .75rem;
        margin-bottom: 1rem;
        display: inline-block;
        word-break: break-all;
    }
    .qr-note { font-size: .73rem; color: #9ca3af; line-height: 1.5; margin-bottom: 1rem; }

    .btn-download {
        width: 100%;
        display: flex; align-items: center; justify-content: center; gap: .5rem;
        padding: .7rem 1rem;
        background: linear-gradient(135deg, var(--brand-dark), var(--brand-mid));
        color: #fff;
        font-weight: 700; font-size: .82rem;
        border: none; border-radius: 10px;
        cursor: pointer; transition: opacity .18s;
    }
    .btn-download:hover { opacity: .88; }

    .btn-track {
        width: 100%;
        display: flex; align-items: center; justify-content: center; gap: .5rem;
        padding: .65rem 1rem;
        background: transparent;
        color: var(--brand-dark);
        font-weight: 700; font-size: .82rem;
        border: 2px solid var(--brand-dark);
        border-radius: 10px;
        cursor: pointer; transition: background .18s, color .18s;
        margin-top: .6rem;
        text-decoration: none;
    }
    .btn-track:hover { background: var(--brand-dark); color: var(--brand-acc); }

    .btn-account {
        display: inline-flex; align-items: center; gap: .5rem;
        padding: .75rem 1.5rem;
        background: linear-gradient(135deg, var(--brand-dark), var(--brand-mid));
        color: #fff;
        font-weight: 700; font-size: .9rem;
        border-radius: 10px; text-decoration: none;
        transition: opacity .18s;
    }
    .btn-account:hover { opacity: .88; }

    .btn-services {
        display: inline-flex; align-items: center; gap: .5rem;
        padding: .75rem 1.5rem;
        background: transparent;
        border: 2px solid var(--brand-dark);
        color: var(--brand-dark);
        font-weight: 700; font-size: .9rem;
        border-radius: 10px; text-decoration: none;
        transition: background .18s, color .18s;
    }
    .btn-services:hover { background: var(--brand-dark); color: var(--brand-acc); }

    /* Toast */
    .copy-toast {
        position: fixed; bottom: 2rem; left: 50%;
        transform: translateX(-50%) translateY(20px);
        background: var(--brand-dark); color: var(--brand-acc);
        font-size: .82rem; font-weight: 700;
        padding: .6rem 1.25rem; border-radius: 999px;
        opacity: 0; transition: opacity .2s, transform .2s;
        pointer-events: none; z-index: 9999;
    }
    .copy-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

    /* Animations */
    @keyframes fadeInDown { from{opacity:0;transform:translateY(-12px)} to{opacity:1;transform:translateY(0)} }
    @keyframes fadeInUp   { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
    @keyframes popIn {
        0%  {transform:scale(.5);opacity:0}
        80% {transform:scale(1.1)}
        100%{transform:scale(1);opacity:1}
    }

    @media (max-width: 600px) {
        .detail-grid { grid-template-columns: 1fr; }
        .cta-row { flex-direction: column; align-items: stretch; }
    }
    </style>
</head>
<body>

    <!-- Navbar -->
    <header class="navbar">
        <div class="logo-container">
            <img src="assets/img/Logo.jpg" alt="JRN Logo" class="logo-img" />
            <span class="logo-text">JRN Business Solutions Co.</span>
        </div>
        <div class="hamburger" onclick="toggleMenu()"><span></span><span></span><span></span></div>
        <nav>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="services.php">Services</a></li>
                <li><a href="index.php#about">About Us</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="account_page.php">Account</a></li>
                    <li><a href="#" id="logout-btn">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <!-- Hero -->
    <section class="summary-hero">
        <div class="hero-badge"><i class="fas fa-check"></i> Submission Confirmed</div>
        <div class="hero-success-icon"><i class="fas fa-check"></i></div>
        <h1 class="hero-title">Inquiry Successfully Submitted!</h1>
        <p class="hero-subtitle">Our team will review your request and get back to you shortly.</p>
        <div class="hero-ref">
            <span>Reference&nbsp;#</span>
            <code id="refCode"><?php echo htmlspecialchars($summary['inquiry_number']); ?></code>
            <button class="copy-btn" onclick="copyRef()"><i class="fas fa-copy"></i> Copy</button>
        </div>
    </section>

    <!-- Main content -->
    <div class="summary-page-wrap">

        <!-- LEFT -->
        <div class="left-stack">

            <!-- Inquiry Details -->
            <div class="summ-card" style="animation: fadeInUp 0.45s ease 0.1s both;">
                <div class="summ-card-header">
                    <div class="card-icon"><i class="fas fa-file-alt"></i></div>
                    <h3>Inquiry Details</h3>
                </div>
                <div class="summ-card-body">
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Reference Number</span>
                            <span class="detail-value mono"><?php echo htmlspecialchars($summary['inquiry_number']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Date Submitted</span>
                            <span class="detail-value"><?php echo htmlspecialchars($summary['created_at']); ?></span>
                        </div>
                        <div class="detail-item full">
                            <span class="detail-label">Service Requested</span>
                            <span class="detail-value accent"><?php echo htmlspecialchars($summary['service_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Processing Type</span>
                            <span class="proc-pill-lg"
                                  style="background:<?php echo $proc_info['bg']; ?>;color:<?php echo $proc_info['text']; ?>;">
                                <i class="fas <?php echo $proc_info['icon']; ?>"></i>
                                <?php echo htmlspecialchars($proc_label); ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Est. Timeline</span>
                            <span class="detail-value">
                                <i class="fas fa-calendar-check" style="color:var(--brand-dark);margin-right:.3rem;"></i>
                                <?php echo htmlspecialchars($proc_timeline); ?>
                            </span>
                        </div>
                        <?php if (!empty($summary['additional_notes'])): ?>
                            <div class="detail-item full">
                                <span class="detail-label">Your Notes</span>
                                <span class="detail-value" style="font-weight:400;color:#374151;line-height:1.5;white-space:pre-wrap;">
                                    <?php echo htmlspecialchars($summary['additional_notes']); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($final_price > 0): ?>
                        <div class="fee-strip">
                            <div>
                                <div class="fee-label">Processing Type</div>
                                <div class="fee-type"><?php echo htmlspecialchars($proc_label); ?></div>
                                <div class="fee-note"><?php echo htmlspecialchars($proc_timeline); ?></div>
                            </div>
                            <div style="text-align:right;">
                                <div class="fee-label">Estimated Service Fee</div>
                                <div class="fee-amount"><?php echo $price_display; ?></div>
                                <div class="fee-note">*Final amount subject to confirmation</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Uploaded Documents -->
            <div class="summ-card" style="animation: fadeInUp 0.45s ease 0.2s both;">
                <div class="summ-card-header">
                    <div class="card-icon"><i class="fas fa-paperclip"></i></div>
                    <h3>Uploaded Documents (<?php echo count($summary['uploaded_files']); ?>)</h3>
                </div>
                <div class="summ-card-body">
                    <?php if (!empty($summary['uploaded_files'])): ?>
                        <ul class="doc-list">
                            <?php foreach ($summary['uploaded_files'] as $idx => $file):
                                $ext     = strtolower($file['file_type'] ?? pathinfo($file['original_name'] ?? '', PATHINFO_EXTENSION));
                                $sizeKb  = round(($file['file_size'] ?? 0) / 1024, 1);
                                $isImg   = in_array($ext, ['jpg','jpeg','png','gif','webp']);
                                $iconCls = $ext === 'pdf' ? 'fa-file-pdf' : ($isImg ? 'fa-file-image' : 'fa-file-alt');
                                $iconTypeCls = $ext === 'pdf' ? 'pdf' : ($isImg ? 'img' : '');
                                // Human-readable label instead of raw filename
                                $docLabel = generate_doc_label($file, $summary['service_name'], $idx + 1);
                                $rawName  = $file['original_name'] ?? $file['file_name'] ?? '';
                            ?>
                                <li class="doc-item">
                                    <div class="doc-icon <?php echo $iconTypeCls; ?>">
                                        <i class="fas <?php echo $iconCls; ?>"></i>
                                    </div>
                                    <div style="flex:1;min-width:0;">
                                        <span class="doc-name"><?php echo htmlspecialchars($docLabel); ?></span>
                                        <span class="doc-sub" style="color:#9ca3af;font-size:.7rem;"><?php echo htmlspecialchars($rawName); ?></span>
                                    </div>
                                    <span class="doc-meta"><?php echo strtoupper($ext); ?> · <?php echo $sizeKb; ?> KB</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p style="font-size:.85rem;color:#9ca3af;text-align:center;padding:1rem 0;">No documents uploaded.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- What Happens Next — Redesigned -->
            <div class="summ-card" style="animation: fadeInUp 0.45s ease 0.3s both;">
                <div class="summ-card-header">
                    <div class="card-icon"><i class="fas fa-rocket"></i></div>
                    <h3>What Happens Next?</h3>
                </div>
                <div class="summ-card-body">

                    <div class="next-steps-grid">
                        <?php foreach ($steps as $step): ?>
                            <div class="next-step-card"
                                 style="background:<?php echo $step['bg']; ?>;border-color:<?php echo $step['color']; ?>22;">
                                <div class="next-step-header">
                                    <div class="next-step-num"><?php echo $step['num']; ?></div>
                                    <div class="next-step-icon"
                                         style="background:<?php echo $step['color']; ?>18;color:<?php echo $step['color']; ?>;">
                                        <i class="fas <?php echo $step['icon']; ?>"></i>
                                    </div>
                                    <div class="next-step-title"><?php echo $step['title']; ?></div>
                                </div>
                                <p class="next-step-desc"><?php echo $step['desc']; ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Tracking info banner -->
                    <div class="track-info-banner">
                        <i class="fas fa-info-circle"></i>
                        <p>
                            Track your inquiry status anytime using your reference number
                            <strong><?php echo htmlspecialchars($summary['inquiry_number']); ?></strong>
                            in your account dashboard or by scanning the QR code.
                            You can also visit the
                            <a href="inquiry_tracker.php?ref=<?php echo urlencode($summary['inquiry_number']); ?>"
                               style="color:#16a34a;font-weight:700;text-decoration:underline;">
                                Inquiry Tracker
                            </a>.
                        </p>
                    </div>

                    <div class="cta-row">
                        <a href="account_page.php#services" class="btn-account">
                            <i class="fas fa-clipboard-list"></i> View My Inquiries
                        </a>
                        <a href="services.php" class="btn-services">
                            <i class="fas fa-th-large"></i> Browse Services
                        </a>
                    </div>
                </div>
            </div>

        </div><!-- /left-stack -->

        <!-- RIGHT — QR Card -->
        <div class="qr-card" style="animation: fadeInUp 0.45s ease 0.25s both;">
            <div class="summ-card">
                <div class="summ-card-header">
                    <div class="card-icon"><i class="fas fa-qrcode"></i></div>
                    <h3>Inquiry QR Code</h3>
                </div>
                <div class="qr-body">
                    <div class="qr-frame">
                        <?php if (file_exists($qrSavePath)): ?>
                            <img src="<?php echo htmlspecialchars($qrPublicUrl); ?>" alt="Inquiry QR Code" id="qrcode-img" />
                        <?php else: ?>
                            <div style="color:#9ca3af;font-size:.75rem;padding:1rem;text-align:center;">
                                <i class="fas fa-qrcode" style="font-size:2rem;margin-bottom:.5rem;display:block;"></i>QR unavailable
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="qr-ref"><?php echo htmlspecialchars($summary['inquiry_number']); ?></div>

                    <p class="qr-note">
                        Scan this QR to instantly view the full status and details of your inquiry. Present to our staff for quick assistance.
                    </p>

                    <button class="btn-download"
                            id="downloadQRBtn"
                            data-download-url="<?php echo htmlspecialchars($qrPublicUrl); ?>">
                        <i class="fas fa-download"></i> Download QR Code
                    </button>

                    <!-- Track button now correctly points to inquiry_tracker.php -->
                    <a href="inquiry_tracker.php?ref=<?php echo urlencode($summary['inquiry_number']); ?>"
                       class="btn-track">
                        <i class="fas fa-search"></i> Track Inquiry Status
                    </a>
                </div>

                <!-- Contact reminder -->
                <div style="padding:.9rem 1.25rem;background:#fafafa;border-top:1px solid #f0f0f0;">
                    <p style="font-size:.72rem;color:#6b7280;margin:0;line-height:1.6;text-align:center;">
                        <i class="fas fa-envelope" style="color:var(--brand-dark);margin-right:.3rem;"></i>
                        Email us at
                        <a href="mailto:jrndocumentation@gmail.com"
                           style="color:var(--brand-dark);font-weight:700;">jrndocumentation@gmail.com</a><br>
                        and quote your reference number.
                    </p>
                </div>
            </div>
        </div>

    </div><!-- /summary-page-wrap -->

    <!-- Copy toast -->
    <div class="copy-toast" id="copyToast"><i class="fas fa-check"></i> Reference number copied!</div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-top">
            <div class="footer-logo-desc">
                <div class="footer-logo-name">
                    <img src="assets/img/logo.jpg" alt="JRN Logo" class="footer-logo">
                    <h3><?php echo htmlspecialchars($companyInfo['name']); ?></h3>
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
            <p>&copy; <?php echo date("Y"); ?> <?php echo htmlspecialchars($companyInfo['name']); ?>. All Rights Reserved. |
                <a href="privacy.php">Privacy Policy</a> | <a href="terms.php">Terms of Service</a>
            </p>
        </div>
    </footer>

    <script>
    function toggleMenu() {
        document.querySelector('.nav-links')?.classList.toggle('active');
        document.querySelector('.hamburger')?.classList.toggle('active');
    }

    function copyRef() {
        const ref = document.getElementById('refCode').textContent.trim();
        navigator.clipboard.writeText(ref).then(() => {
            const toast = document.getElementById('copyToast');
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 2200);
        });
    }

    document.getElementById('downloadQRBtn')?.addEventListener('click', function() {
        const url  = this.dataset.downloadUrl;
        const link = document.createElement('a');
        link.href  = url;
        link.download = 'JRN-Inquiry-QR-<?php echo htmlspecialchars($summary['inquiry_number']); ?>.png';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
    </script>
</body>
</html>