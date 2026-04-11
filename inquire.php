<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'connection/dbconn.php';

$service = trim($_GET['service'] ?? '');

// ── Service requirements map

// ── Load service + requirements from database ──────────────────────────────
$stmt = $conn->prepare("
    SELECT id, name, slug
    FROM services
    WHERE slug = ? AND is_active = 1
    LIMIT 1
");
$stmt->bind_param("s", $service);
$stmt->execute();
$service_row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$service_row) {
    $_SESSION['error'] = "Invalid service selected.";
    header("Location: services.php");
    exit();
}

/* icon map only (UI display) */
$icon_map = [
    'dti-registration'      => 'fa-file-alt',
    'sec-registration'      => 'fa-building',
    'mayors-permit'         => 'fa-landmark',
    'bir-registration'      => 'fa-receipt',
    'closure'               => 'fa-door-closed',
    'renewal'               => 'fa-sync',
    'amendment'             => 'fa-edit',
    'bir-open-cases'        => 'fa-gavel',
    'bookkeeping'           => 'fa-book',
    'retainership'          => 'fa-handshake',
    'bir-tax-filing'        => 'fa-file-invoice',
    'annual-income-tax'     => 'fa-calculator',
    'business-consultation' => 'fa-comments',
    'tax-advice'            => 'fa-lightbulb',
    'payroll-management'    => 'fa-users'
];

$req_stmt = $conn->prepare("
    SELECT requirement_text, requires_id_type, sort_order
    FROM service_requirements
    WHERE service_id = ?
    ORDER BY sort_order ASC, id ASC
");
$req_stmt->bind_param("i", $service_row['id']);
$req_stmt->execute();
$req_result = $req_stmt->get_result();

$requirements = [];
while ($row = $req_result->fetch_assoc()) {
    $requirements[] = $row;
}
$req_stmt->close();

$service_info = [
    'id' => $service_row['id'],
    'name' => $service_row['name'],
    'slug' => $service_row['slug'],
    'icon' => $icon_map[$service_row['slug']] ?? 'fa-briefcase',
    'requirements' => $requirements
];


$valid_ids = [
    'Philippine Passport',
    "Driver's License",
    'SSS ID / UMID',
    'PhilHealth ID',
    'Pag-IBIG ID',
    'Postal ID',
    "Voter's ID / Voter's Certificate",
    'PRC ID',
    'National ID (PhilSys)',
    'TIN ID',
    'Senior Citizen ID',
    'PWD ID',
];

// ── Processing type metadata ───────────────────────────────────────────────
$processing_types = [
    'standard' => [
        'label'       => 'Standard Processing',
        'timeline'    => '5–7 business days',
        'badge'       => 'Most Popular',
        'badge_color' => '#2563eb',
        'icon'        => 'fa-clock',
        'description' => 'Regular timeline with full quality review.',
        'color'       => '#2563eb',
    ],
    'priority' => [
        'label'       => 'Priority Processing',
        'timeline'    => '3–4 business days',
        'badge'       => 'Recommended',
        'badge_color' => '#7c3aed',
        'icon'        => 'fa-bolt',
        'description' => 'Expedited handling with priority queue placement.',
        'color'       => '#7c3aed',
    ],
    'express' => [
        'label'       => 'Express Processing',
        'timeline'    => '2–3 business days',
        'badge'       => 'Fast',
        'badge_color' => '#d97706',
        'icon'        => 'fa-shipping-fast',
        'description' => 'Urgent handling with dedicated staff assignment.',
        'color'       => '#d97706',
    ],
    'rush' => [
        'label'       => 'Rush Processing',
        'timeline'    => '1–2 business days',
        'badge'       => 'Urgent',
        'badge_color' => '#dc2626',
        'icon'        => 'fa-fire',
        'description' => 'Highest priority for time-critical requests.',
        'color'       => '#dc2626',
    ],
    'same_day' => [
        'label'       => 'Same-Day Priority',
        'timeline'    => 'Same business day',
        'badge'       => 'Emergency',
        'badge_color' => '#991b1b',
        'icon'        => 'fa-exclamation-circle',
        'description' => 'Emergency same-day processing (subject to availability).',
        'color'       => '#991b1b',
    ],
];

// ── Explicit per-service per-type prices from quotation document ───────────

$db_stmt = $conn->prepare("
SELECT
    price,
    standard_price, priority_price, express_price, rush_price, same_day_price,
    standard_status, priority_status, express_status, rush_status, same_day_status
FROM services
WHERE slug = ? AND is_active = 1
LIMIT 1
");
$db_stmt->bind_param("s", $service);
$db_stmt->execute();
$db_service = $db_stmt->get_result()->fetch_assoc();
$db_stmt->close();

$prices_for_service = [
    'standard' => $db_service['standard_status'] ? $db_service['standard_price'] : null,
    'priority' => $db_service['priority_status'] ? $db_service['priority_price'] : null,
    'express'  => $db_service['express_status'] ? $db_service['express_price'] : null,
    'rush'     => $db_service['rush_status'] ? $db_service['rush_price'] : null,
    'same_day' => $db_service['same_day_status'] ? $db_service['same_day_price'] : null,
];

// ── Session error ──────────────────────────────────────────────────────────
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Service Inquiry – <?php echo htmlspecialchars($service_info['name']); ?> | JRN Business Solutions Co.</title>
    <link rel="icon" type="image/x-icon" href="assets/img/Logo.jpg" />
    <link rel="stylesheet" href="assets/css/global.css" />
    <link rel="stylesheet" href="assets/css/index.css" />
    <link rel="stylesheet" href="assets/css/responsive.css" />
    <link rel="stylesheet" href="assets/css/inquire.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        /* ── Processing Type Cards ──────────────────────────────────── */
        .processing-type-section {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 1.75rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
        }

        [data-theme="dark"] .processing-type-section {
            background: var(--card-bg, #1e293b);
            border-color: var(--border-color, #334155);
        }

        .processing-type-section h3 {
            font-size: 1.05rem;
            font-weight: 700;
            color: #0F3A40;
            margin: 0 0 0.35rem;
        }

        .processing-type-section .section-subtitle {
            font-size: 0.85rem;
            color: #6b7280;
            margin: 0 0 1.25rem;
        }

        .proc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(155px, 1fr));
            gap: 0.875rem;
        }

        .proc-card {
            position: relative;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.1rem 0.9rem 1rem;
            cursor: pointer;
            transition: border-color 0.18s, box-shadow 0.18s, background 0.18s;
            text-align: center;
            user-select: none;
            background: #fafafa;
        }

        .proc-card:hover:not(.proc-unavailable) {
            border-color: #0F3A40;
            box-shadow: 0 4px 12px rgba(15, 58, 64, 0.12);
            background: #fff;
        }

        .proc-card.proc-selected {
            border-color: var(--proc-color, #0F3A40);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(15, 58, 64, 0.10);
        }

        .proc-card.proc-unavailable {
            opacity: 0.45;
            cursor: not-allowed;
            background: #f3f4f6;
        }

        .proc-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .proc-badge {
            display: inline-block;
            font-size: 0.62rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            color: #fff;
            margin-bottom: 0.55rem;
        }

        .proc-icon {
            font-size: 1.4rem;
            margin-bottom: 0.45rem;
            display: block;
        }

        .proc-label {
            font-size: 0.82rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 0.25rem;
            line-height: 1.3;
        }

        .proc-timeline {
            font-size: 0.72rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }

        .proc-price {
            font-size: 1.05rem;
            font-weight: 800;
            color: #0F3A40;
            margin-bottom: 0.3rem;
        }

        .proc-desc {
            font-size: 0.7rem;
            color: #9ca3af;
            line-height: 1.4;
        }

        .proc-unavailable-tag {
            font-size: 0.68rem;
            color: #ef4444;
            font-weight: 600;
            margin-top: 0.3rem;
        }

        .proc-check {
            position: absolute;
            top: 0.55rem;
            right: 0.55rem;
            width: 18px;
            height: 18px;
            border: 2px solid #d1d5db;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.6rem;
            color: #fff;
            background: #fff;
            transition: all 0.15s;
        }

        .proc-card.proc-selected .proc-check {
            background: var(--proc-color, #0F3A40);
            border-color: var(--proc-color, #0F3A40);
        }

        /* Price summary strip */
        .price-summary-strip {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(135deg, #0F3A40 0%, #1C4F50 100%);
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin: 1.25rem 0 0;
            color: #fff;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .price-summary-strip .pss-label {
            font-size: 0.82rem;
            opacity: 0.8;
        }

        .price-summary-strip .pss-type {
            font-size: 0.95rem;
            font-weight: 700;
        }

        .price-summary-strip .pss-price {
            font-size: 1.35rem;
            font-weight: 800;
            color: #D9FF00;
        }

        .price-summary-strip .pss-note {
            font-size: 0.7rem;
            opacity: 0.65;
        }

        label.required::after {
            content: " *";
            color: red;
        }

        @media (max-width: 600px) {
            .proc-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 360px) {
            .proc-grid {
                grid-template-columns: 1fr;
            }
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
    <section class="inquire-hero">
        <div class="hero-content">
            <h1>Service Inquiry</h1>
            <p>Submit your requirements for <strong><?php echo htmlspecialchars($service_info['name']); ?></strong></p>
        </div>
    </section>

    <!-- Form -->
    <section class="inquire-form">
        <div class="container">
            <div class="form-header">
                <h2><i class="fas <?php echo htmlspecialchars($service_info['icon']); ?>"></i>
                    <?php echo htmlspecialchars($service_info['name']); ?></h2>
                <p>Fill out the form, choose your processing speed, and upload the required documents</p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <p><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            <?php endif; ?>

            <form id="inquiryForm" action="process_inquiry.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="service_slug" value="<?php echo htmlspecialchars($service); ?>" />

                <!-- Service Info Card -->
                <div class="info-card">
                    <h3><i class="fas fa-info-circle"></i> Service Information</h3>
                    <div class="service-display">
                        <label>Selected Service:</label>
                        <div class="service-badge">
                            <i class="fas <?php echo htmlspecialchars($service_info['icon']); ?>"></i>
                            <span><?php echo htmlspecialchars($service_info['name']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- ── Processing Type Selection ────────────────────────────── -->
                <div class="processing-type-section">
                    <h3><i class="fas fa-tachometer-alt"></i> Processing Type</h3>
                    <p class="section-subtitle">
                        Select how fast you need your inquiry processed.
                        Faster options include a higher service fee.
                    </p>

                    <div class="proc-grid">
                        <?php foreach ($processing_types as $type_key => $type): ?>
                            <?php
                            $price_val   = $prices_for_service[$type_key];
                            $unavailable = ($price_val === null);
                            $price_disp  = $unavailable
                                ? 'Not Available'
                                : '₱' . number_format($price_val, 2);
                            $is_default  = ($type_key === 'standard');
                            ?>
                            <label class="proc-card <?php echo $unavailable ? 'proc-unavailable' : ''; ?> <?php echo $is_default ? 'proc-selected' : ''; ?>"
                                data-type="<?php echo $type_key; ?>"
                                data-price="<?php echo $price_val ?? 0; ?>"
                                data-label="<?php echo htmlspecialchars($type['label']); ?>"
                                data-color="<?php echo $type['color']; ?>"
                                style="--proc-color: <?php echo $type['color']; ?>;">
                                <input type="radio"
                                    name="processing_type"
                                    value="<?php echo $type_key; ?>"
                                    <?php echo $is_default && !$unavailable ? 'checked' : ''; ?>
                                    <?php echo $unavailable ? 'disabled' : ''; ?>>
                                <div class="proc-check">
                                    <?php if ($is_default && !$unavailable): ?>
                                        <i class="fas fa-check"></i>
                                    <?php endif; ?>
                                </div>
                                <?php if ($type['badge']): ?>
                                    <div class="proc-badge" style="background:<?php echo $type['badge_color']; ?>;">
                                        <?php echo htmlspecialchars($type['badge']); ?>
                                    </div>
                                <?php else: ?>
                                    <div style="height:1.55rem;"></div>
                                <?php endif; ?>
                                <span class="proc-icon" style="color:<?php echo $type['color']; ?>;">
                                    <i class="fas <?php echo $type['icon']; ?>"></i>
                                </span>
                                <div class="proc-label"><?php echo htmlspecialchars($type['label']); ?></div>
                                <div class="proc-timeline">
                                    <i class="fas fa-calendar-alt" style="font-size:0.65rem;"></i>
                                    <?php echo htmlspecialchars($type['timeline']); ?>
                                </div>
                                <div class="proc-price"><?php echo $price_disp; ?></div>
                                <div class="proc-desc"><?php echo htmlspecialchars($type['description']); ?></div>
                                <?php if ($unavailable): ?>
                                    <div class="proc-unavailable-tag"><i class="fas fa-ban"></i> Not Available</div>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <!-- Price Summary Strip -->
                    <div class="price-summary-strip">
                        <div>
                            <div class="pss-label">Selected Processing</div>
                            <div class="pss-type" id="stripTypeName">Standard Processing</div>
                        </div>
                        <div style="text-align:right;">
                            <div class="pss-label">Estimated Service Fee</div>
                            <div class="pss-price" id="stripPrice">
                                ₱<?php
                                    $std = $prices_for_service['standard'];
                                    echo $std !== null ? number_format($std, 2) : '—';
                                    ?>
                            </div>
                            <div class="pss-note">*Final amount subject to confirmation</div>
                        </div>
                    </div>
                </div>
                <!-- ── End Processing Type ──────────────────────────────────── -->

                <!-- Required Documents -->
                <?php if (!empty($service_info['requirements'])): ?>
                    <div class="requirements-section">
                        <h3><i class="fas fa-clipboard-list"></i> Required Documents</h3>
                        <p class="section-subtitle">Please upload the following documents (Max 5MB each)</p>

                        <?php foreach ($service_info['requirements'] as $index => $req): ?>
                            <div class="requirement-block">
                                <div class="req-header">
                                    <span class="req-number"><?php echo $index + 1; ?></span>
                                    <label><?php echo htmlspecialchars($req['requirement_text']); ?></label>
                                </div>
                                <?php if (!empty($req['requires_id_type'])): ?>
                                    <div class="form-group">
                                        <label for="id_type_<?php echo $index; ?>" class="required">Select ID Type</label>
                                        <select id="id_type_<?php echo $index; ?>" name="id_type_<?php echo $index; ?>" required>
                                            <option value="" disabled selected>-- Choose Valid ID --</option>
                                            <?php foreach ($valid_ids as $id): ?>
                                                <option value="<?php echo htmlspecialchars($id); ?>"><?php echo htmlspecialchars($id); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>
                                <div class="form-group file-upload-group">
                                    <label for="file_<?php echo $index; ?>" class="required file-label">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <span class="file-text">Choose file or drag here</span>
                                        <span class="file-name"></span>
                                    </label>
                                    <input type="file" id="file_<?php echo $index; ?>"
                                        name="requirements_files[<?php echo $index; ?>]"
                                        accept=".pdf,.jpg,.jpeg,.png" required />
                                    <small class="file-note">
                                        <i class="fas fa-info-circle"></i> Formats: PDF, JPG, PNG • Max size: 5MB
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Additional Notes -->
                <div class="notes-section">
                    <h3><i class="fas fa-comment-dots"></i> Additional Notes</h3>
                    <div class="form-group">
                        <label for="notes">Any additional information or special requests?</label>
                        <textarea id="notes" name="notes"
                            placeholder="Enter any additional details, questions, or special requests here..."
                            rows="5"></textarea>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="window.history.back()">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-paper-plane"></i> Submit Inquiry
                    </button>
                </div>
            </form>
        </div>
    </section>

    <script>
        function toggleMenu() {
            document.querySelector('.nav-links')?.classList.toggle('active');
            document.querySelector('.hamburger')?.classList.toggle('active');
        }

        // ── Processing type card selection ────────────────────────────────────
        const procCards = document.querySelectorAll('.proc-card:not(.proc-unavailable)');
        const stripPrice = document.getElementById('stripPrice');
        const stripType = document.getElementById('stripTypeName');

        function formatPrice(val) {
            if (!val || val == 0) return '—';
            return '₱' + parseFloat(val).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        procCards.forEach(card => {
            card.addEventListener('click', () => {
                // Deselect all
                document.querySelectorAll('.proc-card').forEach(c => {
                    c.classList.remove('proc-selected');
                    const chk = c.querySelector('.proc-check');
                    if (chk) chk.innerHTML = '';
                });
                // Select this
                card.classList.add('proc-selected');
                const chk = card.querySelector('.proc-check');
                if (chk) chk.innerHTML = '<i class="fas fa-check"></i>';
                card.querySelector('input[type="radio"]').checked = true;

                // Update strip
                stripType.textContent = card.dataset.label;
                stripPrice.textContent = formatPrice(card.dataset.price);
            });
        });

        // ── File upload preview ───────────────────────────────────────────────
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function() {
                const label = this.previousElementSibling;
                const fileName = label.querySelector('.file-name');
                const fileText = label.querySelector('.file-text');
                if (this.files.length > 0) {
                    fileName.textContent = this.files[0].name;
                    fileName.style.display = 'block';
                    fileText.style.display = 'none';
                    label.classList.add('has-file');
                } else {
                    fileName.textContent = '';
                    fileName.style.display = 'none';
                    fileText.style.display = 'inline';
                    label.classList.remove('has-file');
                }
            });
        });
    </script>
</body>

</html>