<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$service = isset($_GET['service']) ? $_GET['service'] : '';

$services_map = [
    'dti-registration' => [
        'name' => 'DTI Registration',
        'icon' => 'fa-file-alt',
        'requirements' => [
            'Valid government-issued ID',
            'Proposed business name (3 options)',
            'Barangay clearance (if applicable)',
            'Payment for registration fee'
        ]
    ],
    'sec-registration' => [
        'name' => 'SEC Registration',
        'icon' => 'fa-building',
        'requirements' => [
            'Articles of Incorporation/Partnership',
            'Bylaws (for corporations)',
            "Treasurer's Affidavit",
            'Proof of Address'
        ]
    ],
    'mayors-permit' => [
        'name' => "Mayor's Permit",
        'icon' => 'fa-landmark',
        'requirements' => [
            'DTI/SEC Registration',
            'Barangay Clearance',
            'Lease Contract / Proof of Business Address',
            'Sanitary Permit (if applicable)',
            'Fire Safety Inspection Certificate'
        ]
    ],
    'bir-registration' => [
        'name' => 'BIR Registration',
        'icon' => 'fa-receipt',
        'requirements' => [
            'DTI/SEC Certificate',
            "Mayor's Permit / Application",
            'Valid ID of Owner',
            'Lease Contract / Proof of Address'
        ]
    ],
    'closure' => [
        'name' => 'Business Closure',
        'icon' => 'fa-door-closed',
        'requirements' => [
            'BIR Certificate of Registration',
            "Mayor's Permit",
            'Board Resolution / Affidavit of Closure'
        ]
    ],
    'renewal' => [
        'name' => 'Business Renewal',
        'icon' => 'fa-sync',
        'requirements' => [
            "Previous Year's Mayor's Permit",
            'Barangay Clearance',
            'Updated Financial Statements'
        ]
    ],
    'amendment' => [
        'name' => 'Business Amendment',
        'icon' => 'fa-edit',
        'requirements' => [
            'Existing Business Registration Documents',
            'Amended Articles of Incorporation / DTI Certificate',
            'Board Resolution for Amendment'
        ]
    ],
    'bir-open-cases' => [
        'name' => 'BIR Open Cases Assistance',
        'icon' => 'fa-gavel',
        'requirements' => [
            'Notice from BIR',
            'Previous Tax Filings',
            'Proof of Payment / Receipts'
        ]
    ],
    'bookkeeping' => [
        'name' => 'Bookkeeping Services',
        'icon' => 'fa-book',
        'requirements' => [
            'Previous Books of Accounts (if any)',
            'Receipts and Vouchers',
            'Sales Invoices'
        ]
    ],
    'retainership' => [
        'name' => 'Accounting Retainership',
        'icon' => 'fa-handshake',
        'requirements' => [
            'List of Business Transactions',
            'Company Profile / Scope of Work'
        ]
    ],
    'bir-tax-filing' => [
        'name' => 'BIR Tax Filing & Compliance',
        'icon' => 'fa-file-invoice',
        'requirements' => [
            'Books of Accounts',
            'Official Receipts / Invoices',
            'Payroll (if applicable)'
        ]
    ],
    'annual-income-tax' => [
        'name' => 'Annual Income Tax Filing',
        'icon' => 'fa-calculator',
        'requirements' => [
            'Financial Statements',
            'Certificate of Income Tax Withheld (if any)'
        ]
    ],
    'business-consultation' => [
        'name' => 'Business Consultation',
        'icon' => 'fa-comments',
        'requirements' => [
            'Basic Business Info / Idea',
            'List of Questions / Concerns'
        ]
    ],
    'tax-advice' => [
        'name' => 'Tax Advice',
        'icon' => 'fa-lightbulb',
        'requirements' => [
            'Income or Expense Records',
            'Previous Tax Returns (if any)'
        ]
    ],
    'payroll-management' => [
        'name' => 'Payroll Management',
        'icon' => 'fa-users',
        'requirements' => [
            'Employee List with Details',
            'Payroll Summary (if existing)',
            'Government ID numbers (SSS, PhilHealth, Pag-IBIG)'
        ]
    ]
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
    'PWD ID'
];

// Get service details
$service_info = $services_map[$service] ?? ['name' => 'Unknown Service', 'icon' => 'fa-question', 'requirements' => []];

// If the service doesn't exist, redirect
if (empty($service_info)) {
    $_SESSION['error'] = "Invalid service selected.";
    header("Location: services.php");
    exit();
}

// Display any session error messages
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Service Inquiry - <?php echo htmlspecialchars($service_info['name']); ?> | JRN Business Solutions Co.</title>
    <link rel="icon" type="image/x-icon" href="assets/img/Logo.jpg" />
    <link rel="stylesheet" href="assets/css/global.css" />
    <link rel="stylesheet" href="assets/css/index.css" />
    <link rel="stylesheet" href="assets/css/responsive.css" />
    <link rel="stylesheet" href="assets/css/inquire.css" />
    <style>
        /* Mark required labels with red asterisk */
        label.required::after {
            content: " *";
            color: red;
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

    <!-- Hero Section -->
    <section class="inquire-hero">
        <div class="hero-content">
            <h1>Service Inquiry</h1>
            <p>Submit your requirements for <strong><?php echo htmlspecialchars($service_info['name']); ?></strong></p>
        </div>
    </section>

    <!-- Inquiry Form -->
    <section class="inquire-form">
        <div class="container">
            <div class="form-header">
                <h2><i class="fas <?php echo htmlspecialchars($service_info['icon']); ?>"></i> <?php echo htmlspecialchars($service_info['name']); ?></h2>
                <p>Please fill out the form below and upload the required documents</p>
            </div>

            <!-- Display errors (if any) -->
            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <p><?php echo $error_message; ?></p>
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

                <!-- Required Documents -->
                <?php if (!empty($service_info['requirements'])): ?>
                    <div class="requirements-section">
                        <h3><i class="fas fa-clipboard-list"></i> Required Documents</h3>
                        <p class="section-subtitle">Please upload the following documents (Max 5MB each)</p>

                        <?php foreach ($service_info['requirements'] as $index => $req): ?>
                            <div class="requirement-block">
                                <div class="req-header">
                                    <span class="req-number"><?php echo $index + 1; ?></span>
                                    <label><?php echo htmlspecialchars($req); ?></label>
                                </div>

                                <?php if (stripos($req, 'ID') !== false): ?>
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
                                    <input type="file" id="file_<?php echo $index; ?>" name="requirements_files[<?php echo $index; ?>]" accept=".pdf,.jpg,.jpeg,.png" required />
                                    <small class="file-note"><i class="fas fa-info-circle"></i> Formats: PDF, JPG, PNG • Max size: 5MB</small>
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
                        <textarea id="notes" name="notes" placeholder="Enter any additional details, questions, or special requests here..." rows="5"></textarea>
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
            const navLinks = document.querySelector('.nav-links');
            const hamburger = document.querySelector('.hamburger');
            if (navLinks) navLinks.classList.toggle('active');
            if (hamburger) hamburger.classList.toggle('active');
        }

        // File upload preview
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function() {
                const label = this.previousElementSibling;
                const fileName = label.querySelector('.file-name');
                const fileText = label.querySelector('.file-text');

                if (this.files.length > 0) {
                    const file = this.files[0];
                    fileName.textContent = file.name;
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