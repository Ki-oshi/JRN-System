<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/vendor/autoload.php';
include 'connection/dbconn.php';
header('Content-Type: application/json');

// Ensure consistent timezone
date_default_timezone_set('Asia/Manila');
mysqli_query($conn, "SET time_zone = '+08:00'");

$email = trim($_POST['email'] ?? '');
if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email is required.']);
    exit;
}

// Check user
$stmt = $conn->prepare("SELECT id, fullname FROM users WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'No account found with that email.']);
    exit;
}

$user = $result->fetch_assoc();
$userId = $user['id'];
$userName = htmlspecialchars($user['fullname']);

// Generate token and expiry
$token = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

// Update user
$update = $conn->prepare("UPDATE users SET reset_token=?, reset_expires=? WHERE id=?");
$update->bind_param("ssi", $token, $expires, $userId);
$update->execute();

// Reset link
$resetLink = "http://localhost/SIAA/reset_password.php?email=" . urlencode($email) . "&token=" . urlencode($token);

// PHPMailer
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'kioshiiofficial@gmail.com';
    $mail->Password   = 'jzri aqgh tovz hepu'; // App password
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    $mail->setFrom('no-reply@jrn.com', 'JRN Business Solutions');
    $mail->addAddress($email, $userName);
    $mail->isHTML(true);
    $mail->Subject = 'Password Reset Request | JRN Business Solutions Co.';
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
                                                    🔐 Password Reset Request
                                                </h2>
                                                
                                                <p style='color: rgba(255, 255, 255, 0.95); font-size: 16px; line-height: 1.7; margin: 0 0 24px; text-align: center;'>
                                                    Hi <strong style='color: #D9FF00;'>$userName</strong>,
                                                </p>
                                                
                                                <p style='color: rgba(255, 255, 255, 0.9); font-size: 15px; line-height: 1.6; margin: 0 0 32px; text-align: center;'>
                                                    We received a request to reset the password for your JRN Business Solutions Co. account. Click the button below to create a new password:
                                                </p>
                                                
                                                <!-- CTA Button -->
                                                <table width='100%' cellpadding='0' cellspacing='0'>
                                                    <tr>
                                                        <td align='center' style='padding: 20px 0;'>
                                                            <a href='$resetLink' style='
                                                                background: linear-gradient(135deg, #D9FF00 0%, #b8d900 100%);
                                                                color: #0F3A40;
                                                                text-decoration: none;
                                                                padding: 16px 40px;
                                                                border-radius: 10px;
                                                                font-weight: 700;
                                                                font-size: 17px;
                                                                display: inline-block;
                                                                box-shadow: 0 6px 20px rgba(217, 255, 0, 0.3);
                                                            '>
                                                                🔑 Reset My Password
                                                            </a>
                                                        </td>
                                                    </tr>
                                                </table>
                                                
                                                <!-- Info Box -->
                                                <table width='100%' cellpadding='0' cellspacing='0' style='margin: 32px 0; background: rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 24px; border: 1px solid rgba(255, 255, 255, 0.1);'>
                                                    <tr>
                                                        <td>
                                                            <p style='color: #D9FF00; font-size: 15px; font-weight: 600; margin: 0 0 12px; text-align: center;'>
                                                                ⏱️ Important Information
                                                            </p>
                                                            <p style='color: rgba(255, 255, 255, 0.9); font-size: 14px; line-height: 1.6; margin: 0; text-align: center;'>
                                                                This password reset link is valid for <strong style='color: #D9FF00;'>1 hour only</strong>.<br>
                                                                After that, you'll need to request a new one.
                                                            </p>
                                                        </td>
                                                    </tr>
                                                </table>
                                                
                                                <!-- Alternative Link -->
                                                <p style='color: rgba(255, 255, 255, 0.7); font-size: 13px; line-height: 1.6; margin: 24px 0 0; text-align: center;'>
                                                    If the button doesn't work, copy and paste this link into your browser:<br>
                                                    <a href='$resetLink' style='color: #D9FF00; text-decoration: underline; word-break: break-all;'>
                                                        $resetLink
                                                    </a>
                                                </p>
                                            </td>
                                        </tr>
                                        
                                        <!-- Security Notice -->
                                        <tr>
                                            <td style='padding: 24px 40px; background: rgba(0, 0, 0, 0.2); border-top: 1px solid rgba(255, 255, 255, 0.1);'>
                                                <p style='color: rgba(255, 255, 255, 0.8); font-size: 14px; line-height: 1.6; margin: 0; text-align: center;'>
                                                    🛡️ <strong>Didn't request this?</strong> If you didn't request a password reset, you can safely ignore this email. Your account remains secure and no changes will be made.
                                                </p>
                                            </td>
                                        </tr>
                                        
                                        <!-- Security Tips -->
                                        <tr>
                                            <td style='padding: 24px 40px; background: rgba(217, 255, 0, 0.05);'>
                                                <p style='color: #D9FF00; font-size: 14px; font-weight: 600; margin: 0 0 12px; text-align: center;'>
                                                    💡 Security Tips
                                                </p>
                                                <table width='100%' cellpadding='4' cellspacing='0'>
                                                    <tr>
                                                        <td style='color: rgba(255, 255, 255, 0.85); font-size: 13px; padding: 4px 0;'>
                                                            <span style='color: #D9FF00; margin-right: 8px;'>•</span>
                                                            Use a strong, unique password
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: rgba(255, 255, 255, 0.85); font-size: 13px; padding: 4px 0;'>
                                                            <span style='color: #D9FF00; margin-right: 8px;'>•</span>
                                                            Never share your password with anyone
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style='color: rgba(255, 255, 255, 0.85); font-size: 13px; padding: 4px 0;'>
                                                            <span style='color: #D9FF00; margin-right: 8px;'>•</span>
                                                            Enable two-factor authentication if available
                                                        </td>
                                                    </tr>
                                                </table>
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
                                                
                                                <!-- Social Links -->
                                                <table width='100%' cellpadding='0' cellspacing='0' style='margin-top: 20px;'>
                                                    <tr>
                                                        <td align='center'>
                                                            <a href='#' style='display: inline-block; margin: 0 8px;'>
                                                                <img src='https://yourdomain.com/assets/img/icons/facebook.svg' alt='Facebook' style='width: 24px; height: 24px; opacity: 0.6;'>
                                                            </a>
                                                            <a href='#' style='display: inline-block; margin: 0 8px;'>
                                                                <img src='https://yourdomain.com/assets/img/icons/twitter.svg' alt='Twitter' style='width: 24px; height: 24px; opacity: 0.6;'>
                                                            </a>
                                                            <a href='#' style='display: inline-block; margin: 0 8px;'>
                                                                <img src='https://yourdomain.com/assets/img/icons/instagram.svg' alt='Instagram' style='width: 24px; height: 24px; opacity: 0.6;'>
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
    echo json_encode(['success' => true, 'message' => 'A reset link has been sent to your email.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Email could not be sent: ' . $mail->ErrorInfo]);
}
