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

// Check USERS table for role=user only
$stmt = $conn->prepare("
    SELECT id, email, password, is_verified, role, status, first_name, last_name, fullname 
    FROM users 
    WHERE email = ? AND role = 'user'
");

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    logActivity(null, 'user', 'login_failed', 'Failed login attempt (user not found): ' . $email);
    echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
    exit;
}

$user = $result->fetch_assoc();

// Check if account is suspended
if ($user['status'] === 'suspended') {
    logActivity(null, 'user', 'login_failed', 'Failed login attempt (suspended account): ' . $email);
    echo json_encode([
        'success' => false,
        'message' => 'Your account has been suspended. Please contact support.'
    ]);
    exit;
}

// Check if account is inactive
if ($user['status'] === 'inactive') {
    logActivity(null, 'user', 'login_failed', 'Failed login attempt (inactive account): ' . $email);
    echo json_encode([
        'success' => false,
        'message' => 'Your account is inactive. Please contact support to reactivate.'
    ]);
    exit;
}

// Verify password
if (!password_verify($password, $user['password'])) {
    logActivity(null, 'user', 'login_failed', 'Failed login attempt (wrong password): ' . $email);
    echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
    exit;
}

// Check email verification
if (!$user['is_verified']) {
    echo json_encode([
        'success' => false,
        'message' => 'Please verify your email address before logging in. Check your inbox for the verification link.'
    ]);
    exit;
}

// Regenerate session ID for security
session_regenerate_id(true);

// Set session variables for REGULAR USER
$_SESSION['user_id'] = $user['id'];
$_SESSION['email'] = $user['email'];
$_SESSION['role'] = $user['role']; // 'user'
$_SESSION['account_type'] = 'user'; // Flag to distinguish from employees table

// Build display name
if (!empty($user['first_name']) && !empty($user['last_name'])) {
    $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'];
} elseif (!empty($user['fullname'])) {
    $_SESSION['name'] = $user['fullname'];
} else {
    $_SESSION['name'] = 'User';
}

// Log successful login
logActivity(
    $user['id'],
    'user',
    'login',
    $_SESSION['name'] . ' logged in successfully'
);

// Redirect to user dashboard
echo json_encode([
    'success' => true,
    'message' => 'Login successful! Redirecting...',
    'redirect' => 'account_page.php'
]);
exit;
