<?php
session_start();
require_once '../connection/dbconn.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

requireAdminOnly(); // Only admin can edit employees

// Get the employee ID from URL
$employee_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check if the logged-in admin/employee is trying to edit their own account
if (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'employee') {
    // If the ID matches the logged-in user, redirect to personal settings
    if ($employee_id === $_SESSION['user_id']) {
        header("Location: admin-account.php");
        exit;
    }
}
$message = '';
$message_type = '';
$employee_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($employee_id === 0) {
    $_SESSION['error'] = "Invalid employee ID";
    header("Location: employees-admin.php");
    exit();
}

// Fetch employee data
$stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();

if (!$employee) {
    $_SESSION['error'] = "Employee not found";
    header("Location: employees-admin.php");
    exit();
}

// Fetch current permissions
$stmt = $conn->prepare("SELECT * FROM employee_permissions WHERE employee_id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$permissions_result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$current_permissions = [];
foreach ($permissions_result as $perm) {
    $current_permissions[$perm['permission_name']] = [
        'can_view' => (bool)$perm['can_view'],
        'can_create' => (bool)$perm['can_create'],
        'can_edit' => (bool)$perm['can_edit'],
        'can_archive' => (bool)$perm['can_archive']
    ];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_employee'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $position = trim($_POST['position']);
    $department = trim($_POST['department']);
    $status = $_POST['status'];
    $role = $_POST['role'];
    $password = $_POST['password'] ?? '';

    // Validation
    $errors = [];

    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";

    // Phone validation (optional but if provided, must be valid PH format)
    if (!empty($phone)) {
        $phone_clean = preg_replace('/[\s\-]/', '', $phone);
        if (!preg_match('/^(09|\+639)[0-9]{9}$/', $phone_clean)) {
            $errors[] = "Invalid phone number. Use format: 09XX XXX XXXX";
        } else {
            if (substr($phone_clean, 0, 4) === '+639') {
                $phone = '0' . substr($phone_clean, 3);
            } else {
                $phone = $phone_clean;
            }
        }
    }

    // Check if email exists for other employees
    if (empty($errors)) {
        $check_stmt = $conn->prepare("SELECT id FROM employees WHERE email = ? AND id != ?");
        $check_stmt->bind_param("si", $email, $employee_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $errors[] = "Email already exists";
        }
    }

    // Password validation (only if provided)
    if (!empty($password)) {
        if (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters";
        }
        if ($password !== $_POST['confirm_password']) {
            $errors[] = "Passwords do not match";
        }
    }

    if (empty($errors)) {
        $conn->begin_transaction();

        try {
            // Update employee
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("
                    UPDATE employees 
                    SET first_name = ?, last_name = ?, position = ?, department = ?, email = ?, phone = ?, password = ?, role = ?, status = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->bind_param("sssssssssi", $first_name, $last_name, $position, $department, $email, $phone, $hashed_password, $role, $status, $employee_id);
            } else {
                $stmt = $conn->prepare("
                    UPDATE employees 
                    SET first_name = ?, last_name = ?, position = ?, department = ?, email = ?, phone = ?, role = ?, status = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->bind_param("ssssssssi", $first_name, $last_name, $position, $department, $email, $phone, $role, $status, $employee_id);
            }

            if ($stmt->execute()) {
                // Delete old permissions
                $stmt = $conn->prepare("DELETE FROM employee_permissions WHERE employee_id = ?");
                $stmt->bind_param("i", $employee_id);
                $stmt->execute();

                // Insert new permissions
                if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
                    $perm_stmt = $conn->prepare("
                        INSERT INTO employee_permissions (employee_id, permission_name, can_view, can_create, can_edit, can_archive) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");

                    foreach ($_POST['permissions'] as $module => $perms) {
                        $can_view = isset($perms['can_view']) ? 1 : 0;
                        $can_create = isset($perms['can_create']) ? 1 : 0;
                        $can_edit = isset($perms['can_edit']) ? 1 : 0;
                        $can_archive = isset($perms['can_archive']) ? 1 : 0;

                        $perm_stmt->bind_param("isiiii", $employee_id, $module, $can_view, $can_create, $can_edit, $can_archive);
                        $perm_stmt->execute();
                    }
                }


                // Log employee update
                require_once '../includes/activity_logger.php';
                logActivity(
                    $_SESSION['user_id'],
                    'admin',
                    'employee_updated',
                    "Updated employee ID: {$employee_id} - {$first_name} {$last_name}"
                );

                $conn->commit();


                $_SESSION['success'] = "Employee updated successfully!";
                header("Location: employees-admin.php");
                exit();
            } else {
                throw new Exception("Error updating employee: " . $conn->error);
            }
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error updating employee: " . $e->getMessage();
            $message_type = "error";
        }
    } else {
        $message = implode("<br>", $errors);
        $message_type = "error";
    }
}

// Get pending inquiries count for badge
$stmt = $conn->prepare("SELECT COUNT(*) as pending_count FROM inquiries WHERE status = 'pending'");
$stmt->execute();
$pending_inquiries = $stmt->get_result()->fetch_assoc()['pending_count'];

// Pending bills badge
$stmt = $conn->prepare("SELECT COUNT(*) as pending_bills FROM billings WHERE status = 'unpaid'");
$stmt->execute();
$pending_bills = $stmt->get_result()->fetch_assoc()['pending_bills'];

// Get admin info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employee - JRN Admin</title>
    <link rel="stylesheet" href="assets/css/index-admin.css">
    <link rel="stylesheet" href="assets/css/employee-edit.css">
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
                <a href="employees-admin.php" class="nav-item active">
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
                <a href="employees-admin.php" class="back-link">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7" />
                    </svg>
                    Back to Employees
                </a>
                <h1>Edit Employee</h1>
                <p class="header-subtitle">Update employee information and permissions</p>
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

        <?php if ($message): ?>
            <div class="alert alert--<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <div>
                    <h2>Employee Information</h2>
                    <p style="color: var(--text-secondary); font-size: 0.875rem; margin-top: 0.25rem;">
                        Account #: <strong><?php echo htmlspecialchars($employee['account_number']); ?></strong>
                    </p>
                </div>
                <span class="status status--<?php echo $employee['status'] === 'active' ? 'success' : 'error'; ?>">
                    <?php echo ucfirst($employee['status']); ?>
                </span>
            </div>
            <div class="card-body">
                <form method="POST" class="employee-form">
                    <div class="form-section">
                        <h3 class="section-title">Personal Information</h3>
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label>First Name <span class="required">*</span></label>
                                <input type="text" name="first_name" class="form-control" required value="<?php echo htmlspecialchars($employee['first_name']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Last Name <span class="required">*</span></label>
                                <input type="text" name="last_name" class="form-control" required value="<?php echo htmlspecialchars($employee['last_name']); ?>">
                            </div>
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label>Email <span class="required">*</span></label>
                                <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($employee['email']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="tel" name="phone" id="phone" class="form-control" placeholder="09XX XXX XXXX" pattern="09[0-9]{9}" maxlength="13" value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>">
                                <small class="form-hint">Format: 09XX XXX XXXX (e.g., 0917 123 4567)</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">Employment Details</h3>
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label>Position</label>
                                <input type="text" name="position" class="form-control" placeholder="e.g., Manager, Accountant" value="<?php echo htmlspecialchars($employee['position'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Department</label>
                                <input type="text" name="department" class="form-control" placeholder="e.g., Finance, HR" value="<?php echo htmlspecialchars($employee['department'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label>Role <span class="required">*</span></label>
                                <select name="role" class="form-control" required>
                                    <option value="employee" <?php echo $employee['role'] === 'employee' ? 'selected' : ''; ?>>Employee</option>
                                    <option value="admin" <?php echo $employee['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                                <small class="form-hint">Admin has full access, Employee has restricted access based on permissions</small>
                            </div>
                            <div class="form-group">
                                <label>Status <span class="required">*</span></label>
                                <select name="status" class="form-control" required>
                                    <option value="active" <?php echo $employee['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $employee['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="archived" <?php echo $employee['status'] === 'archived' ? 'selected' : ''; ?>>Archived</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">Permissions & Access Control</h3>
                        <p style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 1.5rem;">
                            Set what this employee can do in the system. Leave unchecked to deny access.
                        </p>

                        <?php
                        $permission_modules = [
                            'inquiries' => 'Inquiries Management',
                            'billing'   => 'Billing Management',
                            'users'     => 'User Management',
                            'employees' => 'Employee Management',
                            'logs'      => 'Activity Log Management'
                        ];
                        ?>

                        <div class="permissions-grid">
                            <?php foreach ($permission_modules as $module_key => $module_name): ?>
                                <div class="permission-card">
                                    <div class="permission-header">
                                        <h4><?php echo $module_name; ?></h4>
                                    </div>
                                    <div class="permission-options">
                                        <label class="permission-checkbox">
                                            <input type="checkbox" name="permissions[<?php echo $module_key; ?>][can_view]" value="1" <?php echo ($current_permissions[$module_key]['can_view'] ?? false) ? 'checked' : ''; ?>>
                                            <span class="checkbox-label">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                    <circle cx="12" cy="12" r="3"></circle>
                                                </svg>
                                                View
                                            </span>
                                        </label>
                                        <label class="permission-checkbox">
                                            <input type="checkbox" name="permissions[<?php echo $module_key; ?>][can_create]" value="1" <?php echo ($current_permissions[$module_key]['can_create'] ?? false) ? 'checked' : ''; ?>>
                                            <span class="checkbox-label">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                                </svg>
                                                Create
                                            </span>
                                        </label>
                                        <label class="permission-checkbox">
                                            <input type="checkbox" name="permissions[<?php echo $module_key; ?>][can_edit]" value="1" <?php echo ($current_permissions[$module_key]['can_edit'] ?? false) ? 'checked' : ''; ?>>
                                            <span class="checkbox-label">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                                </svg>
                                                Edit
                                            </span>
                                        </label>
                                        <label class="permission-checkbox">
                                            <input type="checkbox" name="permissions[<?php echo $module_key; ?>][can_archive]" value="1" <?php echo ($current_permissions[$module_key]['can_archive'] ?? false) ? 'checked' : ''; ?>>
                                            <span class="checkbox-label">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="3 6 5 6 21 6"></polyline>
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                </svg>
                                                Archive
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="permission-note">
                            <svg width="16" height="16" fill="currentColor">
                                <circle cx="8" cy="8" r="8" opacity="0.2" />
                                <path d="M8 4v4m0 4h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                            </svg>
                            <span>Changes to permissions take effect immediately after saving.</span>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">Change Password (Optional)</h3>
                        <p style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 1rem;">
                            Leave blank to keep current password
                        </p>
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="password" class="form-control" minlength="6">
                                <small class="form-hint">Minimum 6 characters</small>
                            </div>
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" minlength="6">
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="update_employee" class="btn btn--primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                <polyline points="7 3 7 8 15 8"></polyline>
                            </svg>
                            Update Employee
                        </button>
                        <a href="employees-admin.php" class="btn btn--outline">Cancel</a>
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

        // Role-based permissions auto-select
        const roleSelect = document.querySelector('select[name="role"]');
        if (roleSelect) {
            roleSelect.addEventListener('change', function() {
                const permissionCheckboxes = document.querySelectorAll('.permission-checkbox input[type="checkbox"]');

                if (this.value === 'admin') {
                    permissionCheckboxes.forEach(checkbox => {
                        checkbox.checked = true;
                    });
                } else {
                    permissionCheckboxes.forEach(checkbox => {
                        checkbox.checked = false;
                    });
                }
            });
        }

        // Phone number formatting
        const phoneInput = document.getElementById('phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');

                if (value.length > 0 && value[0] !== '0') {
                    value = '0' + value;
                }
                if (value.length > 1 && value[1] !== '9') {
                    value = '09' + value.substring(2);
                }

                value = value.substring(0, 11);

                if (value.length > 4 && value.length <= 7) {
                    value = value.substring(0, 4) + ' ' + value.substring(4);
                } else if (value.length > 7) {
                    value = value.substring(0, 4) + ' ' + value.substring(4, 7) + ' ' + value.substring(7);
                }

                e.target.value = value;
            });

            phoneInput.addEventListener('blur', function(e) {
                const value = e.target.value.replace(/\s/g, '');
                if (value && value.length !== 11) {
                    e.target.setCustomValidity('Phone number must be 11 digits');
                } else if (value && !value.match(/^09[0-9]{9}$/)) {
                    e.target.setCustomValidity('Phone number must start with 09');
                } else {
                    e.target.setCustomValidity('');
                }
            });
        }
    </script>
</body>

</html>