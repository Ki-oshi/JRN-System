<?php
session_start();
include 'connection/dbconn.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Sanitize and collect form data
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');
$city = trim($_POST['city'] ?? '');
$state = trim($_POST['state'] ?? '');
$postal_code = trim($_POST['postal_code'] ?? '');

// Build fullname from first_name and last_name
$fullname = trim($first_name . ' ' . $last_name);

// Simple validation
if (empty($first_name) || empty($last_name) || empty($email)) {
    $_SESSION['error'] = "First name, last name, and email are required.";
    header("Location: account_page.php");
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "Invalid email format.";
    header("Location: account_page.php");
    exit;
}

// Check if email is already taken by another user
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
$stmt->bind_param("si", $email, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $_SESSION['error'] = "Email address is already in use by another account.";
    header("Location: account_page.php");
    exit;
}

// Check if username is already taken by another user (if username is provided)
if (!empty($username)) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->bind_param("si", $username, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['error'] = "Username is already taken.";
        header("Location: account_page.php");
        exit;
    }
}

// Prepare and execute the update query
$stmt = $conn->prepare("
    UPDATE users 
    SET fullname = ?, first_name = ?, last_name = ?, username = ?, email = ?, 
        phone = ?, address = ?, city = ?, state = ?, postal_code = ?
    WHERE id = ?
");
$stmt->bind_param(
    "ssssssssssi",
    $fullname,
    $first_name,
    $last_name,
    $username,
    $email,
    $phone,
    $address,
    $city,
    $state,
    $postal_code,
    $user_id
);

if ($stmt->execute()) {
    $_SESSION['success'] = "Profile updated successfully!";
    header("Location: account_page.php");
    exit;
} else {
    $_SESSION['error'] = "Error updating profile. Please try again.";
    header("Location: account_page.php");
    exit;
}
