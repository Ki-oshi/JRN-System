<?php
include 'connection/dbconn.php';

if (isset($_GET['token']) && isset($_GET['email'])) {
    $token = $_GET['token'];
    $email = $_GET['email'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email=? AND verification_token=? AND is_verified=0");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $update = $conn->prepare("UPDATE users SET is_verified=1, verification_token=NULL WHERE email=?");
        $update->bind_param("s", $email);
        $update->execute();

        header("Location: login.php?verified=1");
        exit;
    } else {
        header("Location: signup.php?verified=0");
        exit;
    }
} else {
    header("Location: signup.php?verified=0");
    exit;
}
