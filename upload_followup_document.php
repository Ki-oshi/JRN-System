<?php
session_start();
include 'connection/dbconn.php';
require_once 'includes/auth.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

function respond($success, $message, $extra = [])
{
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra));
    exit;
}

try {

    requireUser();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(false, 'Invalid request method.');
    }

    $user_id    = $_SESSION['user_id'] ?? null;
    $inquiry_id = (int)($_POST['inquiry_id'] ?? 0);
    $note       = trim($_POST['note'] ?? '');

    if (!$user_id || !$inquiry_id) {
        respond(false, 'Missing required fields.');
    }

    // Validate inquiry
    $stmt = $conn->prepare("
        SELECT id, inquiry_number, status
        FROM inquiries
        WHERE id = ? AND user_id = ? AND status IN ('pending', 'in_review')
        LIMIT 1
    ");

    if (!$stmt) respond(false, 'DB prepare failed.');

    $stmt->bind_param("ii", $inquiry_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $inquiry = $result->fetch_assoc();
    $stmt->close();

    if (!$inquiry) {
        respond(false, 'Inquiry not found or closed.');
    }

    if (empty($_FILES['files']['name'][0])) {
        respond(false, 'No files uploaded.');
    }

    $allowed_exts  = ['pdf', 'jpg', 'jpeg', 'png', 'docx'];
    $max_size      = 10 * 1024 * 1024;

    $upload_dir = 'uploads/inquiry_documents/' . $inquiry['inquiry_number'] . '/followup/';

    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            respond(false, 'Failed to create upload directory.');
        }
    }

    $uploaded = 0;
    $errors = [];

    foreach ($_FILES['files']['name'] as $i => $name) {

        $tmp  = $_FILES['files']['tmp_name'][$i];
        $size = $_FILES['files']['size'][$i];

        if (!file_exists($tmp)) {
            $errors[] = "$name: temp file missing.";
            continue;
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed_exts)) {
            $errors[] = "$name: invalid file type.";
            continue;
        }

        if ($size > $max_size) {
            $errors[] = "$name: too large.";
            continue;
        }

        $safe_name = 'followup_' . time() . "_$i." . $ext;
        $dest = $upload_dir . $safe_name;

        if (!move_uploaded_file($tmp, $dest)) {
            $errors[] = "$name: upload failed.";
            continue;
        }

        $label = $note ?: "Follow-up: $name";
        $file_type = $ext;

        // ✅ FIXED INSERT
        $stmt = $conn->prepare("
            INSERT INTO inquiry_documents 
            (inquiry_id, file_name, file_path, file_label, file_size, file_type)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            $errors[] = "$name: DB prepare failed.";
            continue;
        }

        $stmt->bind_param(
            "isssis",
            $inquiry_id,
            $name,
            $dest,
            $label,
            $size,
            $file_type
        );

        if (!$stmt->execute()) {
            $errors[] = "$name: DB insert failed.";
            $stmt->close();
            continue;
        }

        $stmt->close();
        $uploaded++;
    }

    if ($uploaded === 0) {
        respond(false, $errors[0] ?? 'Upload failed.');
    }

    respond(true, "$uploaded file(s) uploaded successfully.", [
        'uploaded' => $uploaded,
        'warnings' => $errors
    ]);
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
