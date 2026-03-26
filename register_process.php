<?php
session_start();
include 'connection/dbconn.php';

require __DIR__ . '/PHPMailer/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // ✅ Validate password match
    if ($password !== $confirm_password) {
        echo json_encode(["success" => false, "error" => "Passwords do not match."]);
        exit;
    }

    // ✅ Validate password strength
    if (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/", $password)) {
        echo json_encode([
            "success" => false,
            "error" => "Password must be at least 8 characters long and include upper, lower case letters, and a number."
        ]);
        exit;
    }

    // ✅ Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo json_encode(["success" => false, "error" => "This email is already registered."]);
        $stmt->close();
        exit;
    }
    $stmt->close();

    // ✅ Generate hashed password and verification token
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $token = bin2hex(random_bytes(16));

    // ✅ Function to generate unique account number
    function generateAccountNumber($conn)
    {
        do {
            $accountNumber = 'JRN-' . strtoupper(bin2hex(random_bytes(4))); // e.g. JRN-9A3C4F1B
            $check = $conn->prepare("SELECT id FROM users WHERE account_number = ?");
            $check->bind_param("s", $accountNumber);
            $check->execute();
            $check->store_result();
            $exists = $check->num_rows > 0;
            $check->close();
        } while ($exists);
        return $accountNumber;
    }

    $accountNumber = generateAccountNumber($conn);

    // ✅ Insert new user (unverified)
    $stmt = $conn->prepare("
        INSERT INTO users (fullname, email, password, verification_token, is_verified, account_number)
        VALUES (?, ?, ?, ?, 0, ?)
    ");
    $stmt->bind_param("sssss", $fullname, $email, $hashedPassword, $token, $accountNumber);

    if ($stmt->execute()) {
        // ✅ Create verification link
        $verifyLink = "http://localhost/SIAA/verify.php?token=$token&email=" . urlencode($email);

        // ✅ Send verification email
        $mail = new PHPMailer(true);
        try {
            // SMTP Configuration
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'kioshiiofficial@gmail.com';
            $mail->Password   = 'jzri aqgh tovz hepu'; // App password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Email setup
            $mail->setFrom('kioshiiofficial@gmail.com', 'JRN Business Solutions');
            $mail->addAddress($email, $fullname);

            $mail->isHTML(true);
            $mail->Subject = 'Verify Your Email | JRN Business Solutions Co.';
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
                                                    Welcome, $fullname!
                                                </h2>
                                                
                                                <p style='color: rgba(255, 255, 255, 0.95); font-size: 16px; line-height: 1.7; margin: 0 0 24px; text-align: center;'>
                                                    Thank you for choosing JRN Business Solutions Co. as your trusted partner in business compliance and growth.
                                                </p>
                                                
                                                <p style='color: rgba(255, 255, 255, 0.9); font-size: 15px; line-height: 1.6; margin: 0 0 32px; text-align: center;'>
                                                    To activate your account and start accessing our comprehensive business services, please verify your email address by clicking the button below:
                                                </p>
                                                
                                                <!-- CTA Button -->
                                                <table width='100%' cellpadding='0' cellspacing='0'>
                                                    <tr>
                                                        <td align='center' style='padding: 20px 0;'>
                                                            <a href='$verifyLink' style='
                                                                background: linear-gradient(135deg, #D9FF00 0%, #b8d900 100%);
                                                                color: #0F3A40;
                                                                text-decoration: none;
                                                                padding: 16px 40px;
                                                                border-radius: 10px;
                                                                font-weight: 700;
                                                                font-size: 17px;
                                                                display: inline-block;
                                                                box-shadow: 0 6px 20px rgba(217, 255, 0, 0.3);
                                                                transition: all 0.3s ease;
                                                            '>
                                                                ✓ Verify My Email
                                                            </a>
                                                        </td>
                                                    </tr>
                                                </table>
                                                
                                                <!-- Features Box -->
                                                <table width='100%' cellpadding='0' cellspacing='0' style='margin: 32px 0; background: rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 24px; border: 1px solid rgba(255, 255, 255, 0.1);'>
                                                    <tr>
                                                        <td>
                                                            <p style='color: #D9FF00; font-size: 15px; font-weight: 600; margin: 0 0 16px; text-align: center;'>
                                                                What's included with your account:
                                                            </p>
                                                            <table width='100%' cellpadding='8' cellspacing='0'>
                                                                <tr>
                                                                    <td style='color: rgba(255, 255, 255, 0.9); font-size: 14px; padding: 6px 0;'>
                                                                        <span style='color: #D9FF00; font-size: 16px; margin-right: 8px;'>✓</span>
                                                                        Access to 15+ business services
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style='color: rgba(255, 255, 255, 0.9); font-size: 14px; padding: 6px 0;'>
                                                                        <span style='color: #D9FF00; font-size: 16px; margin-right: 8px;'>✓</span>
                                                                        Real-time application tracking
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style='color: rgba(255, 255, 255, 0.9); font-size: 14px; padding: 6px 0;'>
                                                                        <span style='color: #D9FF00; font-size: 16px; margin-right: 8px;'>✓</span>
                                                                        Secure document management
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style='color: rgba(255, 255, 255, 0.9); font-size: 14px; padding: 6px 0;'>
                                                                        <span style='color: #D9FF00; font-size: 16px; margin-right: 8px;'>✓</span>
                                                                        Email notifications & updates
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </td>
                                                    </tr>
                                                </table>
                                                
                                                <!-- Alternative Link -->
                                                <p style='color: rgba(255, 255, 255, 0.7); font-size: 13px; line-height: 1.6; margin: 24px 0 0; text-align: center;'>
                                                    If the button doesn't work, copy and paste this link into your browser:<br>
                                                    <a href='$verifyLink' style='color: #D9FF00; text-decoration: underline; word-break: break-all;'>
                                                        $verifyLink
                                                    </a>
                                                </p>
                                            </td>
                                        </tr>
                                        
                                        <!-- Security Notice -->
                                        <tr>
                                            <td style='padding: 24px 40px; background: rgba(0, 0, 0, 0.2); border-top: 1px solid rgba(255, 255, 255, 0.1);'>
                                                <p style='color: rgba(255, 255, 255, 0.8); font-size: 14px; line-height: 1.6; margin: 0; text-align: center;'>
                                                    🔒 <strong>Security Notice:</strong> If you didn't create an account with JRN Business Solutions Co., please ignore this email and your information will not be stored.
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
                                                
                                                <!-- Social Links (Optional) -->
                                                <table width='100%' cellpadding='0' cellspacing='0' style='margin-top: 20px;'>
                                                    <tr>
                                                        <td align='center'>
                                                            <a href='#' style='display: inline-block; margin: 0 8px;'>
                                                                <img src='assets/img/icons/Facebook.svg' alt='Facebook' style='width: 24px; height: 24px; opacity: 0.6;'>
                                                            </a>
                                                            <a href='#' style='display: inline-block; margin: 0 8px;'>
                                                                <img src='assets/img/icons/Twitter.svg' alt='Twitter' style='width: 24px; height: 24px; opacity: 0.6;'>
                                                            </a>
                                                            <a href='#' style='display: inline-block; margin: 0 8px;'>
                                                                <img src='assets/img/icons/Instagram.svg' alt='Instagram' style='width: 24px; height: 24px; opacity: 0.6;'>
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
            echo json_encode(["success" => true, "message" => "Registration successful. Please check your email to verify your account."]);
        } catch (Exception $e) {
            echo json_encode(["success" => false, "error" => "Mailer Error: {$mail->ErrorInfo}"]);
        }
    } else {
        echo json_encode(["success" => false, "error" => $stmt->error]);
    }

    $stmt->close();
    $conn->close();
}
