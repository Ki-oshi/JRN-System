<?php
session_start();
require_once 'connection/dbconn.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');

try {
    requireUser();
} catch (Throwable $e) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id   = $_SESSION['user_id'] ?? 0;
$inquiry_id = isset($_GET['inquiry_id']) ? (int)$_GET['inquiry_id'] : 0;

if ($user_id <= 0 || $inquiry_id <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT d.id,
               d.inquiry_id,
               d.file_name,
               d.file_path,
               d.id_type,
               d.file_size,
               d.file_type,
               d.uploaded_at
        FROM inquiry_documents d
        INNER JOIN inquiries i ON d.inquiry_id = i.id
        WHERE d.inquiry_id = ?
          AND i.user_id = ?
        ORDER BY d.uploaded_at DESC
    ");
    $stmt->bind_param('ii', $inquiry_id, $user_id);
    $stmt->execute();
    $docs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode($docs);
} catch (Throwable $e) {
    error_log('get_my_inquiry_documents error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
