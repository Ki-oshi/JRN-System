<?php
session_start();
require_once '../connection/dbconn.php';
require_once '../includes/auth.php';
require_once '../includes/activity_logger.php';

requireAdmin();
$error = "";
$success = "";

// Fetch service
$stmt = $conn->prepare("SELECT * FROM services WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$service = $stmt->get_result()->fetch_assoc(); // Fetch the result here


$originalService = $service;

$proc_multipliers = [
    'standard' => 1.00,
    'priority' => 1.15,
    'express' => 1.30,
    'rush' => 1.50,
    'same_day' => 1.70
];

// Form processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = trim($_POST['name']);
    $slug = trim($_POST['slug']);
    $category = trim($_POST['category']);
    $short_description = trim($_POST['short_description']);
    $long_description = $_POST['long_description'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $admin_id = $_SESSION['user_id'];
    $image_path = ''; // Default image path

    // Default values for prices and statuses
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0.00;
    $standard_price = isset($_POST['standard_price']) ? floatval($_POST['standard_price']) : 0.00;
    $priority_price = isset($_POST['priority_price']) ? floatval($_POST['priority_price']) : 0.00;
    $express_price = isset($_POST['express_price']) ? floatval($_POST['express_price']) : 0.00;
    $rush_price = isset($_POST['rush_price']) ? floatval($_POST['rush_price']) : 0.00;
    $same_day_price = isset($_POST['same_day_price']) ? floatval($_POST['same_day_price']) : 0.00;

    $standard_status = isset($_POST['standard_status']) ? 1 : 0;
    $priority_status = isset($_POST['priority_status']) ? 1 : 0;
    $express_status = isset($_POST['express_status']) ? 1 : 0;
    $rush_status = isset($_POST['rush_status']) ? 1 : 0;
    $same_day_status = isset($_POST['same_day_status']) ? 1 : 0;

    // Validations
    if (empty($name) || empty($slug) || empty($category)) {
        $error = "Service name, slug, and category are required.";
    } else {
        // Check if the slug is unique
        $stmt = $conn->prepare("SELECT id FROM services WHERE slug = ? AND id != ?");
        $stmt->bind_param("si", $slug, $id);
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {
            $error = "Slug must be unique.";
        }
    }

    // File upload logic
    if (!$error && isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
        $target_dir = "../uploads/services/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'svg'])) {
            $new_filename = uniqid("service_", true) . "." . $ext;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_dir . $new_filename)) {
                $image_path = "uploads/services/" . $new_filename;
            } else {
                $error = "Failed to upload image.";
            }
        } else {
            $error = "Invalid image type.";
        }
    }

    // Inserting/updating the service record
    if (!$error) {
        $stmt = $conn->prepare("
            UPDATE services SET
                name=?, slug=?, category=?, price=?, standard_price=?, priority_price=?, express_price=?,
                rush_price=?, same_day_price=?, standard_status=?, priority_status=?, express_status=?,
                rush_status=?, same_day_status=?, short_description=?, long_description=?, image=?, is_active=?
            WHERE id=?
        ");

        $stmt->bind_param(
            "sssddddddiiiiisssi",
            $name,
            $slug,
            $category,
            $price,
            $standard_price,
            $priority_price,
            $express_price,
            $rush_price,
            $same_day_price,
            $standard_status,
            $priority_status,
            $express_status,
            $rush_status,
            $same_day_status,
            $short_description,
            $long_description,
            $image_path,
            $is_active,
            $id
        );

        if ($stmt->execute()) {
            header("Location: services-admin.php?edited=1");
            exit;
        } else {
            $error = "Failed to update service.";
        }
    }
}

// Fetching the counts for pending inquiries and unpaid bills
$stmt = $conn->prepare("SELECT COUNT(*) as pending_count FROM inquiries WHERE status = 'pending'");
$stmt->execute();
$pending_inquiries = $stmt->get_result()->fetch_assoc()['pending_count'];

$stmt = $conn->prepare("SELECT COUNT(*) as pending_bills FROM billings WHERE status = 'unpaid'");
$stmt->execute();
$pending_bills = $stmt->get_result()->fetch_assoc()['pending_bills'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Service - JRN Admin</title>
    <link rel="stylesheet" href="assets/css/index-admin.css">
    <link rel="stylesheet" href="assets/css/services-add.css">
    <link rel="stylesheet" href="assets/css/logout-modal.css">
    <script src="https://cdn.tiny.cloud/1/ozuk7q3prsvwl4thn94ccpkd86t8hi1v8cfoxk2n6e1kvuj1/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        tinymce.init({
            selector: '#long_description',
            height: 300,
            menubar: false,
            plugins: 'link image code lists',
            toolbar: 'undo redo | formatselect | bold italic underline | alignleft aligncenter alignright | bullist numlist | link | code'
        });
    </script>
    <style>
        .form-control {
            resize: vertical;
        }

        /* ── Pricing preview panel ── */
        .pricing-preview-panel {
            background: linear-gradient(135deg, #0F3A40 0%, #1C4F50 100%);
            border-radius: 14px;
            padding: 1.25rem 1.4rem;
            margin-top: 0.75rem;
        }

        .pricing-preview-panel h4 {
            color: #D9FF00;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin: 0 0 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .pricing-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            margin-bottom: 0.4rem;
            font-size: 0.83rem;
            background: rgba(255, 255, 255, 0.07);
        }

        .pricing-row .pr-label {
            color: rgba(255, 255, 255, 0.8);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .pricing-row .pr-badge {
            font-size: 0.62rem;
            font-weight: 700;
            padding: 0.15rem 0.5rem;
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .pricing-row .pr-amount {
            color: #D9FF00;
            font-weight: 800;
            font-size: 0.9rem;
        }

        .pricing-row .pr-na {
            color: rgba(255, 255, 255, 0.3);
            font-size: 0.75rem;
            font-style: italic;
        }

        .pricing-note {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.72rem;
            margin: 0.75rem 0 0;
        }

        .form-hint {
            display: block;
            font-size: 0.78rem;
            color: var(--text-secondary, #6b7280);
            margin-top: 0.3rem;
        }

        .price-group {
            display: flex;
            gap: 0.75rem;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .price-group .form-group {
            flex: 1;
            min-width: 180px;
        }
    </style>
</head>

<body>

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo"><img src="../assets/img/logo.jpg" alt="Logo" class="logo-small">
                <h2>JRN Admin</h2>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="index-admin.php" class="nav-item"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>Dashboard</a>
            <a href="inquiries-admin.php" class="nav-item"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" overflow="visible">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>Inquiries<?php if (isset($pending_inquiries) && $pending_inquiries > 0): ?><span class="badge"><?php echo $pending_inquiries; ?></span><?php endif; ?></a>
            <?php if (isAdmin()): ?>
                <a href="billing-admin.php" class="nav-item"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="6" rx="1" />
                        <rect x="3" y="12" width="18" height="8" rx="1" />
                        <line x1="7" y1="16" x2="11" y2="16" />
                        <line x1="7" y1="19" x2="15" y2="19" />
                    </svg>Billing<?php if (isset($pending_bills) && $pending_bills > 0): ?><span class="badge"><?php echo $pending_bills; ?></span><?php endif; ?></a>
            <?php endif; ?>
            <a href="users-admin.php" class="nav-item"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" overflow="visible">
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                </svg>Users</a>
            <?php if (isAdmin()): ?>
                <a href="employees-admin.php" class="nav-item"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>Employees</a>
                <a href="activity-logs.php" class="nav-item"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>Activity Logs</a>
                <a href="services-admin.php" class="nav-item active"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="6" width="18" height="12" rx="2" />
                        <path d="M3 10h18" />
                    </svg>Manage Services</a>
                <a href="payroll-reports-admin.php" class="nav-item"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" />
                        <line x1="8" y1="21" x2="16" y2="21" />
                        <line x1="12" y1="17" x2="12" y2="21" />
                        <path d="M6 8h.01M10 8h4M6 12h12" />
                    </svg>Payroll Reports</a>
            <?php endif; ?>
            <div style="margin-top:auto;padding-top:1rem;border-top:1px solid var(--border-color);">
                <a href="admin-account.php" class="nav-item"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M12 1v6m0 6v6m6-11h-6m-6 0H1m18.4-3.6l-4.2 4.2m-8.4 0l-4.2-4.2M18.4 18.4l-4.2-4.2m-8.4 0l-4.2 4.2"></path>
                    </svg>My Account</a>
            </div>
        </nav>
        <div class="sidebar-footer">
            <a href="#" class="nav-item logout" id="logout-btn"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>Logout</a>
        </div>
    </aside>

    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="logout-modal-overlay" id="logout-modal-overlay">
            <div class="logout-modal">
                <h2>Confirm Logout</h2>
                <p>Are you sure you want to log out?</p>
                <div class="logout-modal-buttons"><button class="logout-btn-confirm" id="logout-confirm">Yes</button><button class="logout-btn-cancel" id="logout-cancel">Cancel</button></div>
            </div>
        </div>
    <?php endif; ?>
    <script src="assets/js/logout-modal.js"></script>

    <main class="main-content">
        <header class="admin-header">
            <div class="admin-header-left">
                <h1>Add New Service</h1>
                <p class="header-subtitle">Create a new business service with pricing for all processing types.</p>
            </div>
            <div class="admin-header-right">
                <a href="services-admin.php" class="btn btn--outline">← Back to Services</a>
            </div>
        </header>

        <section class="card">
            <div class="card-header">
                <h2>Service Details</h2>
            </div>
            <div class="card-body">
                <?php if ($error): ?><div class="alert alert--error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert--success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

                <form method="POST" class="service-form" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="name">Service Name <span style="color:#EF4444;">*</span></label>
                        <input class="form-control" type="text" id="name" name="name" required maxlength="150">
                    </div>
                    <div class="form-group">
                        <label for="slug">Unique Slug <span style="color:#EF4444;">*</span></label>
                        <input class="form-control" type="text" id="slug" name="slug" required maxlength="150" pattern="^[a-z0-9-]+$" placeholder="e.g. bir-registration">
                        <span class="form-hint">Lowercase letters, numbers, and hyphens only. Used as the URL identifier and to link prices in inquire.php.</span>
                    </div>
                    <div class="form-group">
                        <label for="category">Category <span style="color:#EF4444;">*</span></label>
                        <input class="form-control" type="text" id="category" name="category" required placeholder="e.g. Business Registration">
                    </div>

                    <!-- ── Price + Processing Type Preview ─────────────────────── -->
                    <div class="form-group">
                        <label for="price">
                            Standard Processing Price (₱) <span style="color:#EF4444;">*</span>
                            <span style="font-weight:400;font-size:0.75rem;color:#6b7280;"> — This is the base price displayed in inquire.php for Standard processing</span>
                        </label>
                        <input class="form-control" type="number" id="price" name="price" step="0.01" min="0" required placeholder="e.g. 9500.00" oninput="updatePricingPreview()">

                        <!-- Live pricing preview -->
                        <div class="pricing-preview-panel">
                            <h4>Processing Type Price Preview</h4>
                            <div class="pricing-row">
                                <span class="pr-label"><span class="pr-badge" style="background:#2563eb;color:#fff;">Standard</span> Standard Processing</span>
                                <span class="pr-amount" id="pp-standard">₱0.00</span>
                            </div>
                            <div class="pricing-row">
                                <span class="pr-label"><span class="pr-badge" style="background:#7c3aed;color:#fff;">Priority</span> Priority Processing</span>
                                <span class="pr-amount" id="pp-priority">₱0.00</span>
                            </div>
                            <div class="pricing-row">
                                <span class="pr-label"><span class="pr-badge" style="background:#d97706;color:#fff;">Express</span> Express Processing</span>
                                <span class="pr-amount" id="pp-express">₱0.00</span>
                            </div>
                            <div class="pricing-row">
                                <span class="pr-label"><span class="pr-badge" style="background:#dc2626;color:#fff;">Rush</span> Rush Processing</span>
                                <span class="pr-amount" id="pp-rush">₱0.00</span>
                            </div>
                            <div class="pricing-row">
                                <span class="pr-label"><span class="pr-badge" style="background:#991b1b;color:#fff;">Same-Day</span> Same-Day Priority</span>
                                <span class="pr-na">Not applicable</span>
                            </div>
                            <p class="pricing-note">⚠ This base price is used as a multiplier fallback for all other services.</p>
                        </div>
                    </div>


                    <div class="form-group">
                        <label>Processing Types Availability</label>

                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin-top:10px;">

                            <label><input type="checkbox" name="standard_status" value="1"
                                    <?= $service['standard_status'] ? 'checked' : '' ?>> Standard Processing</label>

                            <label><input type="checkbox" name="priority_status" value="1"
                                    <?= $service['priority_status'] ? 'checked' : '' ?>> Priority Processing</label>

                            <label><input type="checkbox" name="express_status" value="1"
                                    <?= $service['express_status'] ? 'checked' : '' ?>> Express Processing</label>

                            <label><input type="checkbox" name="rush_status" value="1"
                                    <?= $service['rush_status'] ? 'checked' : '' ?>> Rush Processing</label>

                            <label><input type="checkbox" name="same_day_status" value="1"
                                    <?= $service['same_day_status'] ? 'checked' : '' ?>> Same-Day Priority</label>

                        </div>

                        <span class="form-hint">
                            Checked = Available • Unchecked = Not Available
                        </span>
                    </div>

                    <div class="form-group">
                        <label>Requirements</label>

                        <div id="requirements-wrapper" style="display:grid;gap:10px;margin-top:10px;">

                            <?php if (!empty($requirements)): ?>
                                <?php foreach ($requirements as $req): ?>
                                    <div class="req-row" style="display:grid;grid-template-columns:1fr auto auto;gap:10px;align-items:center;">

                                        <input
                                            type="text"
                                            name="requirements[]"
                                            class="form-control"
                                            value="<?= htmlspecialchars($req['requirement_text']) ?>"
                                            placeholder="Requirement text">

                                        <label style="white-space:nowrap;">
                                            <input
                                                type="checkbox"
                                                class="req-checkbox"
                                                <?= $req['requires_id_type'] ? 'checked' : '' ?>>
                                            Needs ID Type
                                        </label>

                                        <button type="button"
                                            onclick="removeRequirement(this)"
                                            class="btn btn--outline">
                                            Remove
                                        </button>

                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                        </div>

                        <button type="button"
                            onclick="addRequirement()"
                            class="btn btn--outline"
                            style="margin-top:10px;">
                            + Add Requirement
                        </button>

                        <span class="form-hint">
                            Add, remove, check, or uncheck requirements. Changes reflect in inquire.php after saving.
                        </span>
                    </div>

                    <div class="form-group">
                        <label for="short_description">Short Description</label>
                        <textarea class="form-control" id="short_description" name="short_description" maxlength="300"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="long_description">Full Service Description</label>
                        <textarea class="form-control" id="long_description" name="long_description" rows="6"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="image">Hero/Image Upload</label>
                        <input class="form-control" type="file" id="image" name="image" accept="image/*">
                        <span class="form-hint">Recommended: 4K–8K resolution. Allowed: JPG, PNG, SVG.</span>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" name="is_active" value="1" checked> Active/Visible</label>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn--primary">Add Service</button>
                        <a href="services-admin.php" class="btn btn--outline" style="margin-left:1rem;">Cancel</a>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <script>
        const multipliers = {
            standard: 1.00,
            priority: 1.15,
            express: 1.30,
            rush: 1.50
        };

        function updatePricingPreview() {
            const base = parseFloat(document.getElementById('price').value) || 0;
            const fmt = v => '₱' + v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            Object.entries(multipliers).forEach(([key, mult]) => {
                const el = document.getElementById('pp-' + key);
                if (el) el.textContent = fmt(base * mult);
            });
        }

        // Auto-generate slug from name
        document.getElementById('name').addEventListener('input', function() {
            const slugEl = document.getElementById('slug');
            if (!slugEl.dataset.manual) {
                slugEl.value = this.value.toLowerCase().trim().replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
            }
        });
        document.getElementById('slug').addEventListener('input', function() {
            this.dataset.manual = 'true';
        });


        function updateRequirementIndexes() {
            const rows = document.querySelectorAll('.req-row');

            rows.forEach((row, index) => {
                const checkbox = row.querySelector('.req-checkbox');

                checkbox.name = `requirements_id[${index}]`;
                checkbox.value = "1";
            });
        }

        function addRequirement() {
            const wrapper = document.getElementById('requirements-wrapper');

            const row = document.createElement('div');
            row.className = 'req-row';
            row.style =
                'display:grid;grid-template-columns:1fr auto auto;gap:10px;align-items:center;';

            row.innerHTML = `
        <input
            type="text"
            name="requirements[]"
            class="form-control"
            placeholder="Requirement text">

        <label style="white-space:nowrap;">
            <input type="checkbox" class="req-checkbox">
            Needs ID Type
        </label>

        <button type="button"
            onclick="removeRequirement(this)"
            class="btn btn--outline">
            Remove
        </button>
    `;

            wrapper.appendChild(row);
            updateRequirementIndexes();
        }

        function removeRequirement(btn) {
            btn.closest('.req-row').remove();
            updateRequirementIndexes();
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateRequirementIndexes();
        });
    </script>
</body>

</html>