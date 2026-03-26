<?php
session_start();
require_once '../connection/dbconn.php';
require_once '../includes/auth.php';
require_once '../includes/activity_logger.php';

requireAdmin();
$error = "";
$success = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name              = trim($_POST['name']);
    $slug              = trim($_POST['slug']);
    $category          = trim($_POST['category']);
    $short_description = trim($_POST['short_description']);
    $long_description  = $_POST['long_description']; // Allow HTML from TinyMCE
    $is_active         = isset($_POST['is_active']) ? 1 : 0;
    $admin_id          = $_SESSION['user_id'];
    $image_path        = '';

    $price = isset($_POST['price']) && $_POST['price'] !== ''
        ? floatval($_POST['price'])
        : 0.00;

    // Required validation
    if (empty($name) || empty($slug) || empty($category)) {
        $error = "Service name, slug, and category are required.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM services WHERE slug = ?");
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "Slug must be unique. Another service already uses this slug.";
        }
    }

    // Image upload
    if (!$error && isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
        $target_dir = "../uploads/services/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $ext         = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'svg'];
        if (in_array($ext, $allowed_ext)) {
            $new_filename = uniqid("service_", true) . "." . $ext;
            $target_path  = $target_dir . $new_filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                $image_path = "uploads/services/" . $new_filename;
            } else {
                $error = "Failed to upload image.";
            }
        } else {
            $error = "Invalid image type. Allowed: JPG, PNG, SVG.";
        }
    }

    if (!$error) {
        $stmt = $conn->prepare("
            INSERT INTO services 
            (name, slug, category, price, short_description, long_description, image, is_active, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "sssdsssii",
            $name,
            $slug,
            $category,
            $price,
            $short_description,
            $long_description,
            $image_path,
            $is_active,
            $admin_id
        );

        if ($stmt->execute()) {
            // Get the new service ID
            $newServiceId = $stmt->insert_id;

            // Determine actor type (admin vs employee, if you use that)
            $actorType = (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'employee')
                ? 'employee'
                : 'admin';

            // Build a concise description
            $statusText = $is_active ? 'active' : 'inactive';
            $description = "Service {$name} (ID {$newServiceId}) created in category '{$category}' with price {$price} and status '{$statusText}'.";

            logActivity(
                $admin_id,
                $actorType,
                'service_created',
                $description
            );

            $success = "Service added successfully!";
            header("Location: services-admin.php?added=1");
            exit;
        } else {
            $error = "Failed to add service. Please try again.";
        }
    }
}

// Pending inquiries badge
$stmt = $conn->prepare("SELECT COUNT(*) as pending_count FROM inquiries WHERE status = 'pending'");
$stmt->execute();
$pending_inquiries = $stmt->get_result()->fetch_assoc()['pending_count'];

// Pending bills badge
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
    </style>
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

            <?php if (isAdmin()): ?>
                <a href="billing-admin.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="6" rx="1" />
                        <rect x="3" y="12" width="18" height="8" rx="1" />
                        <line x1="7" y1="16" x2="11" y2="16" />
                        <line x1="7" y1="19" x2="15" y2="19" />
                    </svg>
                    Billing
                    <?php if (isset($pending_bills) && $pending_bills > 0): ?>
                        <span class="badge"><?php echo $pending_bills; ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

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
                <a href="services-admin.php" class="nav-item active">
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
                <h1>Add New Service</h1>
                <p class="header-subtitle">Create a new business service with full details and show/hide options.</p>
            </div>
            <div class="admin-header-right">
                <a href="services-admin.php" class="btn btn--outline">← Back to Manage Services</a>
            </div>
        </header>
        <section class="card">
            <div class="card-header">
                <h2>Service Details</h2>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert--error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert--success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <form method="POST" class="service-form" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="name">Service Name <span style="color:#EF4444;">*</span></label>
                        <input class="form-control" type="text" id="name" name="name" required maxlength="150">
                    </div>
                    <div class="form-group">
                        <label for="slug">Unique Slug (for URL) <span style="color:#EF4444;">*</span></label>
                        <input class="form-control" type="text" id="slug" name="slug" required maxlength="150" pattern="^[a-z0-9-]+$" placeholder="e.g. bir-registration">
                    </div>
                    <div class="form-group">
                        <label for="category">Category <span style="color:#EF4444;">*</span></label>
                        <input class="form-control" type="text" id="category" name="category" required placeholder="e.g. Business Registration">
                    </div>
                    <div class="form-group">
                        <label for="price">Service Price (₱) <span style="color:#EF4444;">*</span></label>
                        <input
                            class="form-control"
                            type="number"
                            id="price"
                            name="price"
                            step="0.01"
                            min="0"
                            required
                            placeholder="e.g. 1500.00">
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
                        <span class="form-hint">Recommended dimensions: (e.g. 3840×2160px to 7680×4320px, 4K–8K). Allowed types: JPG, PNG, SVG.</span>
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
</body>

</html>