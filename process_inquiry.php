<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/connection/dbconn.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/encryption_key.php';
require_once __DIR__ . '/includes/activity_logger.php';

// AES-256 key from config/encryption.key
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

// Fetch service
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

// Upload setup
$upload_base_dir = 'uploads/inquiries/';
if (!file_exists($upload_base_dir)) mkdir($upload_base_dir, 0755, true);

$user_upload_dir = $upload_base_dir . $user_id . '/';
if (!file_exists($user_upload_dir)) mkdir($user_upload_dir, 0755, true);

$inquiry_timestamp = date('Y-m-d_His');
$inquiry_dir = $user_upload_dir . $inquiry_timestamp . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $service_name) . '/';
if (!file_exists($inquiry_dir)) mkdir($inquiry_dir, 0755, true);

// File validation
$allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
$max_file_size = 5 * 1024 * 1024;
$uploaded_files = [];
$errors = [];

foreach ($_FILES['requirements_files']['name'] as $index => $filename) {
    if (empty($filename)) continue;

    $tmp = $_FILES['requirements_files']['tmp_name'][$index];
    $size = $_FILES['requirements_files']['size'][$index];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if ($size > $max_file_size) continue;
    if (!in_array($ext, $allowed_extensions)) continue;

    $safe = preg_replace('/[^a-zA-Z0-9_.-]/', '_', pathinfo($filename, PATHINFO_FILENAME));
    $new_name = $safe . '_' . uniqid() . '.' . $ext;
    $destination = $inquiry_dir . $new_name;

    $plaintext = file_get_contents($tmp);
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plaintext, 'AES-256-CBC', $FILE_KEY, OPENSSL_RAW_DATA, $iv);

    file_put_contents($destination, $cipher);

    $uploaded_files[] = [
        'original_name' => $filename,
        'path' => $destination,
        'file_size' => $size,
        'file_type' => $ext,
        'iv' => $iv,
    ];
}

$conn->begin_transaction();

try {
    $inquiry_number = generateInquiryNumber($conn);

    $stmt = $conn->prepare("
        INSERT INTO inquiries 
        (inquiry_number, user_id, service_name, price, additional_notes, status, created_at) 
        VALUES (?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->bind_param("sisds", $inquiry_number, $user_id, $service_name, $price, $additional_notes);
    $stmt->execute();
    $inquiry_id = $conn->insert_id;
    $stmt->close();

    logActivity($user_id, 'user', 'inquiry_created', 'Created inquiry ' . $inquiry_number);

    // Save files
    $stmt = $conn->prepare("
        INSERT INTO inquiry_documents 
        (inquiry_id, file_name, file_path, file_size, file_type, iv, uploaded_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    foreach ($uploaded_files as $file) {
        $stmt->bind_param("issisb", $inquiry_id, $file['original_name'], $file['path'], $file['file_size'], $file['file_type'], $file['iv']);
        $stmt->send_long_data(5, $file['iv']);
        $stmt->execute();
    }

    $stmt->close();
    $conn->commit();

    // =========================
    // ✅ EMAIL STARTS HERE
    // =========================
    require __DIR__ . '/PHPMailer/vendor/autoload.php';


    $user_stmt = $conn->prepare("SELECT fullname, email FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user = $user_stmt->get_result()->fetch_assoc();

    if ($user) {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'kioshiiofficial@gmail.com';
            $mail->Password = 'jzri aqgh tovz hepu';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('kioshiiofficial@gmail.com', 'JRN Business Solutions');
            $mail->addAddress($user['email'], $user['fullname']);

            $mail->isHTML(true);
            $mail->Subject = 'Inquiry Submitted | JRN Business Solutions';

            $fullname = $user['fullname'];
            $mail->Body = "
                    <!DOCTYPE html>
                    <html lang='en'>
                    <head>
                        <meta charset='UTF-8'>
                        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    </head>
                    <body style='margin: 0; padding: 0; background-color: #f9f7f7; font-family: Poppins, Arial, sans-serif;'>
                        <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f9f7f7; padding: 40px 20px;'>
                            <tr>
                                <td align='center'>
                                    <table width='600' cellpadding='0' cellspacing='0' style='background: linear-gradient(135deg, #0F3A40 0%, #1C4F50 100%); border-radius: 20px; overflow: hidden; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);'>
                                        
                                        <!-- Header -->
                                        <tr>
                                            <td style='padding: 40px 40px 30px; text-align: center; border-bottom: 2px solid rgba(217, 255, 0, 0.3);'>
                                                <img src='assets/img/Logo.jpg' alt='JRN Logo' style='width: 90px; height: 90px; border-radius: 50%; border: 3px solid rgba(217, 255, 0, 0.4); margin-bottom: 16px;'>
                                                <h1 style='color: #D9FF00; font-size: 24px; margin: 0; font-weight: 700; letter-spacing: -0.5px;'>
                                                    JRN Business Solutions Co.
                                                </h1>
                                            </td>
                                        </tr>
                                        
                                        <!-- Main Content -->
                                        <tr>
                                            <td style='padding: 40px; color: #ffffff;'>
                                                <h2 style='color: #D9FF00; font-size: 26px; margin: 0 0 16px; text-align: center; font-weight: 700;'>
                                                    Hello, $fullname!
                                                </h2>
                                                
                                                <p style='color: rgba(255, 255, 255, 0.95); font-size: 16px; line-height: 1.7; margin: 0 0 24px; text-align: center;'>
                                                    Your inquiry has been successfully submitted to JRN Business Solutions Co.
                                                </p>
                                                
                                                <p style='color: rgba(255, 255, 255, 0.9); font-size: 15px; line-height: 1.6; margin: 0 0 32px; text-align: center;'>
                                                    Our team is currently reviewing your request. You can track your inquiry anytime through your dashboard.
                                                </p>
                                                
                                                <!-- Inquiry Details -->
                                                <table width='100%' cellpadding='0' cellspacing='0' style='margin: 32px 0; background: rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 24px; border: 1px solid rgba(255, 255, 255, 0.1);'>
                                                    <tr>
                                                        <td>
                                                            <p style='color: #D9FF00; font-size: 15px; font-weight: 600; margin: 0 0 16px; text-align: center;'>
                                                                Inquiry Details
                                                            </p>
                                                            <table width='100%' cellpadding='8' cellspacing='0'>
                                                                <tr>
                                                                    <td style='color: rgba(255, 255, 255, 0.9); font-size: 14px;'>
                                                                        <strong>Inquiry Number:</strong> $inquiry_number
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style='color: rgba(255, 255, 255, 0.9); font-size: 14px;'>
                                                                        <strong>Service:</strong> $service_name
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style='color: rgba(255, 255, 255, 0.9); font-size: 14px;'>
                                                                        <strong>Date:</strong> " . date('F j, Y, g:i a') . "
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </td>
                                                    </tr>
                                                </table>
                                                
                                                <p style='color: rgba(255, 255, 255, 0.9); font-size: 14px; text-align: center;'>
                                                    Thank you for trusting us with your business needs.
                                                </p>
                                            </td>
                                        </tr>
                                        
                                        <!-- Security Notice -->
                                        <tr>
                                            <td style='padding: 24px 40px; background: rgba(0, 0, 0, 0.2); border-top: 1px solid rgba(255, 255, 255, 0.1);'>
                                                <p style='color: rgba(255, 255, 255, 0.8); font-size: 14px; line-height: 1.6; margin: 0; text-align: center;'>
                                                    🔒 <strong>Security Notice:</strong> If you did not submit this inquiry, please ignore this email.
                                                </p>
                                            </td>
                                        </tr>
                                        
                                        <!-- Footer -->
                                        <tr>
                                            <td style='padding: 30px 40px; background: #0B2B2E; text-align: center;'>
                                                <p style='color: rgba(255, 255, 255, 0.6); font-size: 13px; margin: 0 0 12px; line-height: 1.6;'>
                                                    This is an automated message from <strong style='color: #D9FF00;'>JRN Business Solutions Co.</strong><br>
                                                    Please do not reply directly to this email.
                                                </p>
                                                <p style='color: rgba(255, 255, 255, 0.5); font-size: 12px; margin: 0;'>
                                                    © " . date('Y') . " JRN Business Solutions Co. All Rights Reserved.
                                                </p>
                                                
                                                <table width='100%' cellpadding='0' cellspacing='0' style='margin-top: 20px;'>
                                                    <tr>
                                                        <td align='center'>
                                                            <a href='#' style='display: inline-block; margin: 0 8px;'>
                                                                <img src='assets/img/icons/Facebook.svg' style='width: 24px; opacity: 0.6;'>
                                                            </a>
                                                            <a href='#' style='display: inline-block; margin: 0 8px;'>
                                                                <img src='assets/img/icons/Twitter.svg' style='width: 24px; opacity: 0.6;'>
                                                            </a>
                                                            <a href='#' style='display: inline-block; margin: 0 8px;'>
                                                                <img src='assets/img/icons/Instagram.svg' style='width: 24px; opacity: 0.6;'>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                        
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </body>
                    </html>
                    ";

            $mail->send();
        } catch (Exception $e) {
            error_log("Email error: " . $mail->ErrorInfo);
        }
    }

    // =========================
    // END EMAIL
    // =========================

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
    $conn->rollback();
    error_log($e->getMessage());
    $_SESSION['error'] = "Something went wrong.";
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}
