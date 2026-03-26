<?php
session_start();
require_once 'connection/dbconn.php';
require_once 'includes/auth.php';
require_once 'includes/encryption_key.php';

$FILE_KEY = get_file_encryption_key();

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    http_response_code(400);
    echo 'Invalid document ID.';
    exit;
}
$doc_id = (int)$_GET['id'];

// Fetch document + parent inquiry
$stmt = $conn->prepare("
    SELECT d.id,
           d.file_name,
           d.file_path,
           d.file_type,
           d.file_size,
           d.iv,
           i.user_id
    FROM inquiry_documents d
    JOIN inquiries i ON d.inquiry_id = i.id
    WHERE d.id = ?
");
$stmt->bind_param("i", $doc_id);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$doc) {
    http_response_code(404);
    echo 'Document not found.';
    exit;
}

// Access control: admin/employee OR owner user
$isAdmin = false;
try {
    requireAdmin();
    $isAdmin = true;
} catch (Exception $e) {
    $isAdmin = false;
}

if (!$isAdmin) {
    if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] !== (int)$doc['user_id']) {
        http_response_code(403);
        echo 'Unauthorized.';
        exit;
    }
}

// Read encrypted file from disk
$path = __DIR__ . '/' . $doc['file_path'];
if (!file_exists($path)) {
    http_response_code(404);
    echo 'File not found on server.';
    exit;
}

$ciphertext = file_get_contents($path);
if ($ciphertext === false) {
    http_response_code(500);
    echo 'Failed to read file.';
    exit;
}

$iv = $doc['iv'];
if ($iv === null || strlen($iv) !== 16) {
    http_response_code(500);
    echo 'Missing IV.';
    exit;
}

// Decrypt with AES-256-CBC
$plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', $FILE_KEY, OPENSSL_RAW_DATA, $iv);
if ($plaintext === false) {
    http_response_code(500);
    echo 'Failed to decrypt file.';
    exit;
}

// Determine mime type
$ext  = strtolower($doc['file_type'] ?? pathinfo($doc['file_name'], PATHINFO_EXTENSION));
$mime = 'application/octet-stream';
if (in_array($ext, ['jpg', 'jpeg'])) $mime = 'image/jpeg';
elseif ($ext === 'png')              $mime = 'image/png';
elseif ($ext === 'pdf')             $mime = 'application/pdf';

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($doc['file_name']) . '"');
header('Content-Length: ' . strlen($plaintext));

echo $plaintext;
exit;
