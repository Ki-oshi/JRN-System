<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../connection/dbconn.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Only allow logged‑in admin/employee (adjust to your auth helper)
try {
    requireAdmin();   // uses your existing admin gate
} catch (Throwable $e) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$inquiry_id = isset($_GET['inquiry_id']) ? (int)$_GET['inquiry_id'] : 0;
if ($inquiry_id <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $conn->prepare(
        "SELECT id, inquiry_id, file_name, file_path, id_type, file_size, file_type, uploaded_at
         FROM inquiry_documents
         WHERE inquiry_id = ?
         ORDER BY uploaded_at DESC"
    );
    $stmt->bind_param("i", $inquiry_id);
    $stmt->execute();
    $documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode($documents);
} catch (Throwable $e) {
    // Log server-side, return generic error to client
    error_log('get_inquiry_documents error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
