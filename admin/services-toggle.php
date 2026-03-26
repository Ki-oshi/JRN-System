<?php
session_start();
require_once '../connection/dbconn.php';
require_once '../includes/auth.php';
require_once '../includes/activity_logger.php';

require_once '../PHPMailer/vendor/phpmailer/phpmailer/src/Exception.php';
require_once '../PHPMailer/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once '../PHPMailer/vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

requireAdmin();

$id     = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

// allow cancel_schedule as well
if (!$id || !in_array($action, ['activate', 'deactivate', 'cancel_schedule'], true)) {
    header("Location: services-admin.php?error=invalid");
    exit;
}

// Fetch current service info for logging (include schedule fields)
$stmt = $conn->prepare("SELECT name, is_active, scheduled_action, scheduled_effective_at FROM services WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$service = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$service) {
    header("Location: services-admin.php?error=not_found");
    exit;
}

// If cancel_schedule: clear schedule immediately and redirect
if ($action === 'cancel_schedule') {
    $stmt = $conn->prepare("
        UPDATE services
        SET scheduled_action = NULL,
            scheduled_at = NULL,
            scheduled_effective_at = NULL
        WHERE id = ?
    ");
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        $actorType = (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'employee')
            ? 'employee'
            : 'admin';

        logActivity(
            $_SESSION['user_id'],
            $actorType,
            'service_schedule_cancelled',
            "Scheduled status change for service {$service['name']} was cancelled"
        );

        header("Location: services-admin.php?schedule_cancelled=1");
        exit;
    } else {
        header("Location: services-admin.php?error=db");
        exit;
    }
}

// From here down: schedule activate/deactivate in 3 days

// Compute schedule: now + 3 days
$now       = new DateTime('now');
$effective = (clone $now)->modify('+3 days');

$scheduledAction = $action; // 'activate' or 'deactivate'
$scheduledAt     = $now->format('Y-m-d H:i:s');
$effectiveAt     = $effective->format('Y-m-d H:i:s');

// Save schedule on service (do NOT flip is_active yet)
$stmt = $conn->prepare("
    UPDATE services
    SET scheduled_action = ?, scheduled_at = ?, scheduled_effective_at = ?
    WHERE id = ?
");
$stmt->bind_param('sssi', $scheduledAction, $scheduledAt, $effectiveAt, $id);

if ($stmt->execute()) {
    // Send email to all users
    sendServiceScheduleEmails($conn, $service['name'], $scheduledAction, $effectiveAt);

    // Log scheduled change
    $actorType = (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'employee')
        ? 'employee'
        : 'admin';

    $prettyAction = $scheduledAction === 'deactivate' ? 'deactivated' : 'activated';

    logActivity(
        $_SESSION['user_id'],
        $actorType,
        'service_status_scheduled',
        "Service {$service['name']} scheduled to be {$prettyAction} on {$effectiveAt}"
    );

    header("Location: services-admin.php?status_scheduled=1");
    exit;
} else {
    header("Location: services-admin.php?error=db");
    exit;
}

/**
 * Send email to all users notifying about upcoming activation/deactivation.
 */
function sendServiceScheduleEmails(mysqli $conn, string $serviceName, string $action, string $effectiveAt): void
{
    $result = $conn->query("SELECT email, first_name FROM users WHERE email IS NOT NULL AND email != ''");
    if (!$result) return;

    $dt      = new DateTime($effectiveAt);
    $dateStr = $dt->format('F j, Y');
    $year    = date('Y');

    $subject = $action === 'deactivate'
        ? "Service Deactivation Notice | {$serviceName} | JRN Business Solutions Co."
        : "Service Activation Notice | {$serviceName} | JRN Business Solutions Co.";

    while ($row = $result->fetch_assoc()) {
        $email    = $row['email'];
        $userName = $row['first_name'] ?: 'Valued Client';

        if ($action === 'deactivate') {
            $mainTitle = "📢 Service Deactivation Notice";
            $introText = "Hi <strong style='color: #D9FF00;'>{$userName}</strong>,";
            $bodyText  = "We would like to inform you that our service <strong style='color:#D9FF00;'>&quot;{$serviceName}&quot;</strong> "
                . "is scheduled to be <strong>deactivated</strong> in 3 days, on <strong style='color:#D9FF00;'>{$dateStr}</strong>.";
            $extraText = "You may still use or finalize this service until that date. After deactivation, this service will no longer be available in your account.";
            $ctaLabel  = "View Service Details";
        } else {
            $mainTitle = "✅ Service Activation Notice";
            $introText = "Hi <strong style='color: #D9FF00;'>{$userName}</strong>,";
            $bodyText  = "We are pleased to inform you that our service <strong style='color:#D9FF00;'>&quot;{$serviceName}&quot;</strong> "
                . "is scheduled to be <strong>activated</strong> in 3 days, on <strong style='color:#D9FF00;'>{$dateStr}</strong>.";
            $extraText = "Once activated, you will be able to inquire and use this service directly from your JRN Business Solutions Co. account.";
            $ctaLabel  = "Browse Services";
        }

        $ctaLink = 'https://your-domain.com/services.php';

        $body = "
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
                                        <img src='https://your-domain.com/assets/img/Logo.jpg' alt='JRN Logo' style='width: 90px; height: 90px; border-radius: 50%; border: 3px solid rgba(217, 255, 0, 0.4); margin-bottom: 16px;'>
                                        <h1 style='color: #D9FF00; font-size: 24px; margin: 0; font-weight: 700; letter-spacing: -0.5px;'>
                                            JRN Business Solutions Co.
                                        </h1>
                                    </td>
                                </tr>

                                <!-- Main Content -->
                                <tr>
                                    <td style='padding: 40px; color: #ffffff;'>
                                        <h2 style='color: #D9FF00; font-size: 26px; margin: 0 0 16px; text-align: center; font-weight: 700;'>
                                            {$mainTitle}
                                        </h2>

                                        <p style='color: rgba(255, 255, 255, 0.95); font-size: 16px; line-height: 1.7; margin: 0 0 16px; text-align: center;'>
                                            {$introText}
                                        </p>

                                        <p style='color: rgba(255, 255, 255, 0.9); font-size: 15px; line-height: 1.6; margin: 0 0 24px; text-align: center;'>
                                            {$bodyText}
                                        </p>

                                        <p style='color: rgba(255, 255, 255, 0.9); font-size: 14px; line-height: 1.6; margin: 0 0 32px; text-align: center;'>
                                            {$extraText}
                                        </p>

                                        <!-- CTA Button -->
                                        <table width='100%' cellpadding='0' cellspacing='0'>
                                            <tr>
                                                <td align='center' style='padding: 20px 0;'>
                                                    <a href='{$ctaLink}' style='
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
                                                        {$ctaLabel}
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Info Box -->
                                        <table width='100%' cellpadding='0' cellspacing='0' style='margin: 32px 0; background: rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 24px; border: 1px solid rgba(255, 255, 255, 0.1);'>
                                            <tr>
                                                <td>
                                                    <p style='color: #D9FF00; font-size: 15px; font-weight: 600; margin: 0 0 12px; text-align: center;'>
                                                        ℹ️ Important Information
                                                    </p>
                                                    <p style='color: rgba(255, 255, 255, 0.9); font-size: 14px; line-height: 1.6; margin: 0; text-align: center;'>
                                                        This change will take effect on <strong style='color:#D9FF00;'>{$dateStr}</strong>.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>

                                        <p style='color: rgba(255, 255, 255, 0.8); font-size: 14px; line-height: 1.6; margin: 0 0 16px; text-align: center;'>
                                            If you have any questions about this change, you may contact our support team.
                                        </p>

                                    </td>
                                </tr>

                                <!-- Footer -->
                                <tr>
                                    <td style='padding: 24px 40px; background: #0B2B2E; text-align: center;'>
                                        <p style='color: rgba(255, 255, 255, 0.6); font-size: 13px; margin: 0 0 10px; line-height: 1.6;'>
                                            This is an automated message from <strong style='color: #D9FF00;'>JRN Business Solutions Co.</strong><br>
                                            Please do not reply directly to this email.
                                        </p>
                                        <p style='color: rgba(255, 255, 255, 0.5); font-size: 12px; margin: 0;'>
                                            © {$year} JRN Business Solutions Co. All Rights Reserved.
                                        </p>
                                    </td>
                                </tr>

                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
        ";

        // PHPMailer with your SMTP config
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
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body);

            $mail->send();
        } catch (Exception $e) {
            error_log('Email error to ' . $email . ': ' . $mail->ErrorInfo);
        }
    }
}
