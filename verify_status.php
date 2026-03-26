<?php
include 'connection/dbconn.php';

if (isset($_GET['email'])) {
    $email = $_GET['email'];
    $stmt = $conn->prepare("SELECT is_verified FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode(['verified' => (bool)$row['is_verified']]);
    } else {
        echo json_encode(['verified' => false]);
    }
} else {
    echo json_encode(['verified' => false]);
}
