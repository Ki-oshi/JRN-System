<?php
session_start();
include 'connection/dbconn.php';
require_once 'includes/activity_logger.php';

header('Content-Type: application/json');

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Validation
if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
    exit;
}

// Email format validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
    exit;
}

// Check EMPLOYEES table for role=admin only
$stmt = $conn->prepare("
    SELECT id, email, password, role, status, first_name, last_name 
    FROM employees 
    WHERE email = ? AND role = 'admin'
");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    logActivity(null, 'admin', 'login_failed', 'Failed admin login attempt (not found): ' . $email);
    echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
    exit;
}

$admin = $result->fetch_assoc();

// Check if account is archived
if ($admin['status'] === 'archived') {
    logActivity(null, 'admin', 'login_failed', 'Failed admin login attempt (archived): ' . $email);
    echo json_encode([
        'success' => false,
        'message' => 'This admin account has been archived. Please contact support.'
    ]);
    exit;
}

// Check if account is inactive
if ($admin['status'] === 'inactive') {
    logActivity(null, 'admin', 'login_failed', 'Failed admin login attempt (inactive): ' . $email);
    echo json_encode([
        'success' => false,
        'message' => 'This admin account is inactive. Please contact support.'
    ]);
    exit;
}

// Verify password
if (!password_verify($password, $admin['password'])) {
    logActivity(null, 'admin', 'login_failed', 'Failed admin login attempt (wrong password): ' . $email);
    echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
    exit;
}

// Regenerate session ID for security
session_regenerate_id(true);

// Set session variables for ADMIN
$_SESSION['user_id'] = $admin['id'];
$_SESSION['email'] = $admin['email'];
$_SESSION['role'] = $admin['role']; // 'admin'
$_SESSION['account_type'] = 'employee'; // Flag for admin/employee
$_SESSION['name'] = $admin['first_name'] . ' ' . $admin['last_name'];
$_SESSION['first_name'] = $admin['first_name'];
$_SESSION['last_name'] = $admin['last_name'];

// Log successful login
logActivity(
    $admin['id'],
    'admin',
    'login',
    $_SESSION['name'] . ' (admin) logged in successfully'
);

// Redirect to admin dashboard
echo json_encode([
    'success' => true,
    'message' => 'Login successful! Redirecting to admin panel...',
    'redirect' => 'admin/index-admin.php'
]);
exit;
