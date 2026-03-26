<?php
session_start();
require_once __DIR__ . '/connection/dbconn.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/encryption_key.php';
require_once __DIR__ . '/includes/activity_logger.php';

// AES‑256 key from config/encryption.key
$FILE_KEY = get_file_encryption_key();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: services.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$service_slug = $_POST['service_slug'] ?? '';
$additional_notes = $_POST['notes'] ?? '';

// Ensure service is valid
if (empty($service_slug)) {
    $_SESSION['error'] = "Invalid service selected.";
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}

// Fetch service by slug
$stmt = $conn->prepare("
    SELECT name, price 
    FROM services 
    WHERE slug = ? AND is_active = 1 
    LIMIT 1
");
$stmt->bind_param("s", $service_slug);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Invalid or inactive service selected.";
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}

$service_data = $result->fetch_assoc();
$service_name = $service_data['name'];
$price = $service_data['price'];
$stmt->close();

// Validation
if (empty($service_name)) {
    $_SESSION['error'] = "Service name is required.";
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}

// Create uploads directory if it doesn't exist
$upload_base_dir = 'uploads/inquiries/';
if (!file_exists($upload_base_dir)) {
    mkdir($upload_base_dir, 0755, true);
}

// Create user-specific directory
$user_upload_dir = $upload_base_dir . $user_id . '/';
if (!file_exists($user_upload_dir)) {
    mkdir($user_upload_dir, 0755, true);
}

// Create inquiry-specific directory with timestamp
$inquiry_timestamp = date('Y-m-d_His');
$inquiry_dir = $user_upload_dir . $inquiry_timestamp . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $service_name) . '/';
if (!file_exists($inquiry_dir)) {
    mkdir($inquiry_dir, 0755, true);
}

// Allowed file types and max size
$allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
$max_file_size = 5 * 1024 * 1024; // 5MB
$uploaded_files = [];
$errors = [];

// Debug: Check if files are being uploaded
if (isset($_FILES['requirements_files']) && is_array($_FILES['requirements_files']['name'])) {
    foreach ($_FILES['requirements_files']['name'] as $index => $filename) {
        if (empty($filename)) {
            continue;
        }

        $file_tmp = $_FILES['requirements_files']['tmp_name'][$index];
        $file_size = $_FILES['requirements_files']['size'][$index];
        $file_error = $_FILES['requirements_files']['error'][$index];

        // Debug: Output error for each file
        if ($file_error !== UPLOAD_ERR_OK) {
            $errors[] = "Error uploading file: $filename (Error Code: $file_error)";
            continue;
        }

        // Validate file size
        if ($file_size > $max_file_size) {
            $errors[] = "File '$filename' exceeds 5MB limit";
            continue;
        }

        // Validate file extension
        $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($file_extension, $allowed_extensions)) {
            $errors[] = "Invalid file type for '$filename'. Only PDF, JPG, and PNG are allowed.";
            continue;
        }

        // Generate safe filename
        $safe_filename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', pathinfo($filename, PATHINFO_FILENAME));
        $new_filename = $safe_filename . '_' . uniqid() . '.' . $file_extension;
        $destination = $inquiry_dir . $new_filename;

        // Read uploaded file contents
        $plaintext = file_get_contents($file_tmp);
        if ($plaintext === false) {
            $errors[] = "Failed to read uploaded file: $filename";
            continue;
        }

        // AES‑256‑CBC encryption
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', $FILE_KEY, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            $errors[] = "Failed to encrypt file: $filename";
            continue;
        }

        // Save encrypted file
        if (file_put_contents($destination, $ciphertext) === false) {
            $errors[] = "Failed to save file: $filename";
            continue;
        }

        $uploaded_files[] = [
            'index' => $index,
            'original_name' => $filename,
            'stored_name' => $new_filename,
            'path' => $destination,
            'id_type' => $_POST["id_type_$index"] ?? null,
            'file_size' => $file_size,
            'file_type' => $file_extension,
            'iv' => $iv,
        ];
    }
} else {
    $errors[] = "No files were uploaded.";
}

// Debug: Output any errors
if (!empty($errors)) {
    $_SESSION['error'] = implode('<br>', $errors);
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}

// Check if any files were uploaded
if (empty($uploaded_files) && empty($errors)) {
    $_SESSION['error'] = "Please upload at least one required document.";
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}

// If there were errors, clean up and redirect
if (!empty($errors)) {
    foreach ($uploaded_files as $file) {
        if (file_exists($file['path'])) {
            unlink($file['path']);
        }
    }
    if (is_dir($inquiry_dir) && count(scandir($inquiry_dir)) == 2) {
        rmdir($inquiry_dir);
    }

    $_SESSION['error'] = implode('<br>', $errors);
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}

// Begin database transaction
$conn->begin_transaction();

try {
    // Generate unique inquiry number
    $inquiry_number = generateInquiryNumber($conn);

    // Insert inquiry record
    $stmt = $conn->prepare("
        INSERT INTO inquiries 
        (inquiry_number, user_id, service_name, price, additional_notes, status, created_at) 
        VALUES (?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->bind_param(
        "sisds",
        $inquiry_number,
        $user_id,
        $service_name,
        $price,
        $additional_notes
    );
    $stmt->execute();
    $inquiry_id = $conn->insert_id;
    $stmt->close();

    logActivity(
        $_SESSION['user_id'],
        'user',
        'inquiry_created',
        'Created inquiry ' . $inquiry_number . ' for service ' . $service_name
    );

    // Insert uploaded files records with IV
    $stmt = $conn->prepare("
        INSERT INTO inquiry_documents 
        (inquiry_id, file_name, file_path, id_type, file_size, file_type, iv, uploaded_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    foreach ($uploaded_files as $file) {
        $relative_path = str_replace('../', '', $file['path']);
        // i s s s i s b (iv as binary param)
        $stmt->bind_param(
            "isssisb",
            $inquiry_id,
            $file['original_name'],
            $relative_path,
            $file['id_type'],
            $file['file_size'],
            $file['file_type'],
            $file['iv']
        );
        $stmt->send_long_data(6, $file['iv']);
        $stmt->execute();
    }
    $stmt->close();

    // Commit transaction
    $conn->commit();

    // Store summary in session
    $_SESSION['inquiry_summary'] = [
        'inquiry_number' => $inquiry_number,
        'service_name' => $service_name,
        'additional_notes' => $additional_notes,
        'created_at' => date('F j, Y, g:i a'),
        'uploaded_files' => $uploaded_files,
        'user_id' => $user_id
    ];
    header("Location: inquiry_summary.php");
    exit();
} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();

    // Clean up uploaded files
    foreach ($uploaded_files as $file) {
        if (file_exists($file['path'])) {
            unlink($file['path']);
        }
    }
    if (is_dir($inquiry_dir) && count(scandir($inquiry_dir)) == 2) {
        rmdir($inquiry_dir);
    }

    error_log("Inquiry submission error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while processing your inquiry. Please try again.";
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}
