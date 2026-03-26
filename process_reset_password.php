<?php
include 'connection/dbconn.php';
header('Content-Type: application/json');

$userId = $_POST['user_id'] ?? '';
$password = $_POST['password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

if (empty($password) || empty($confirm)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

if ($password !== $confirm) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
    exit;
}

// Fetch old hashed password
$stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (password_verify($password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'New password cannot be the same as the old password.']);
    exit;
}

// Hash new password
$hashed = password_hash($password, PASSWORD_DEFAULT);

// Update password and clear reset token/expiry
$stmt = $conn->prepare("UPDATE users SET password=?, reset_token=NULL, reset_expires=NULL WHERE id=?");
$stmt->bind_param("si", $hashed, $userId);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Password updated successfully. Redirecting you back...']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update password.']);
}
