<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/connection/dbconn.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/encryption_key.php';
require_once __DIR__ . '/includes/activity_logger.php';

$FILE_KEY = get_file_encryption_key();

// ── Auth guards ────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: services.php");
    exit();
}

$user_id          = $_SESSION['user_id'];
$service_slug     = trim($_POST['service_slug'] ?? '');
$additional_notes = trim($_POST['notes'] ?? '');

// ── Validate processing type ───────────────────────────────────────────────
$allowed_proc_types = ['standard', 'priority', 'express', 'rush', 'same_day'];
$processing_type    = trim($_POST['processing_type'] ?? 'standard');
if (!in_array($processing_type, $allowed_proc_types)) {
    $processing_type = 'standard';
}

if (empty($service_slug)) {
    $_SESSION['error'] = "Invalid service selected.";
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}

// ── Processing type display labels ─────────────────────────────────────────
$proc_labels = [
    'standard' => ['label' => 'Standard Processing', 'timeline' => '5–7 business days'],
    'priority' => ['label' => 'Priority Processing',  'timeline' => '3–4 business days'],
    'express'  => ['label' => 'Express Processing',   'timeline' => '2–3 business days'],
    'rush'     => ['label' => 'Rush Processing',      'timeline' => '1–2 business days'],
    'same_day' => ['label' => 'Same-Day Priority',    'timeline' => 'Same business day'],
];
$proc_display  = $proc_labels[$processing_type]['label'];
$proc_timeline = $proc_labels[$processing_type]['timeline'];



// ── Fetch service from DB ──────────────────────────────────────────────────
$stmt = $conn->prepare("
SELECT
    name,
    price,
    standard_price, priority_price, express_price, rush_price, same_day_price,
    standard_status, priority_status, express_status, rush_status, same_day_status
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
$base_price   = (float)$service_data['price'];
$stmt->close();

$price_map = [
    'standard' => $service_data['standard_status'] ? $service_data['standard_price'] : null,
    'priority' => $service_data['priority_status'] ? $service_data['priority_price'] : null,
    'express'  => $service_data['express_status'] ? $service_data['express_price'] : null,
    'rush'     => $service_data['rush_status'] ? $service_data['rush_price'] : null,
    'same_day' => $service_data['same_day_status'] ? $service_data['same_day_price'] : null,
];

if (!isset($price_map[$processing_type]) || $price_map[$processing_type] === null) {
    $processing_type = 'standard';
}

$final_price = (float)$price_map[$processing_type];
$proc_display  = $proc_labels[$processing_type]['label'];
$proc_timeline = $proc_labels[$processing_type]['timeline'];

// ── Upload setup ───────────────────────────────────────────────────────────
$upload_base_dir = 'uploads/inquiries/';
if (!file_exists($upload_base_dir)) mkdir($upload_base_dir, 0755, true);

$user_upload_dir = $upload_base_dir . $user_id . '/';
if (!file_exists($user_upload_dir)) mkdir($user_upload_dir, 0755, true);

$inquiry_timestamp = date('Y-m-d_His');
$inquiry_dir       = $user_upload_dir . $inquiry_timestamp . '_'
    . preg_replace('/[^a-zA-Z0-9_-]/', '_', $service_name) . '/';
if (!file_exists($inquiry_dir)) mkdir($inquiry_dir, 0755, true);

// ── File validation & encrypted upload ────────────────────────────────────
$allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
$max_file_size      = 5 * 1024 * 1024;
$uploaded_files     = [];

foreach ($_FILES['requirements_files']['name'] as $index => $filename) {
    if (empty($filename)) continue;

    $tmp  = $_FILES['requirements_files']['tmp_name'][$index];
    $size = $_FILES['requirements_files']['size'][$index];
    $ext  = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if ($size > $max_file_size || !in_array($ext, $allowed_extensions)) continue;

    $safe        = preg_replace('/[^a-zA-Z0-9_.-]/', '_', pathinfo($filename, PATHINFO_FILENAME));
    $new_name    = $safe . '_' . uniqid() . '.' . $ext;
    $destination = $inquiry_dir . $new_name;

    $plaintext = file_get_contents($tmp);
    $iv        = random_bytes(16);
    $cipher    = openssl_encrypt($plaintext, 'AES-256-CBC', $FILE_KEY, OPENSSL_RAW_DATA, $iv);
    file_put_contents($destination, $cipher);

    $uploaded_files[] = [
        'original_name' => $filename,
        'path'          => $destination,
        'file_size'     => $size,
        'file_type'     => $ext,
        'iv'            => $iv,
    ];
}

// ── Database transaction ───────────────────────────────────────────────────
$conn->begin_transaction();

try {
    $inquiry_number = generateInquiryNumber($conn);

    // Insert inquiry with processing_type and base_price
    $stmt = $conn->prepare("
        INSERT INTO inquiries
            (inquiry_number, user_id, service_name, processing_type, base_price, price, additional_notes, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->bind_param(
        "sissdds",
        $inquiry_number,
        $user_id,
        $service_name,
        $processing_type,
        $base_price,
        $final_price,
        $additional_notes
    );
    $stmt->execute();
    $inquiry_id = $conn->insert_id;
    $stmt->close();

    logActivity(
        $user_id,
        'user',
        'inquiry_created',
        "Created inquiry {$inquiry_number} – {$service_name} ({$proc_display})"
    );

    // Insert uploaded documents
    $stmt = $conn->prepare("
        INSERT INTO inquiry_documents
            (inquiry_id, file_name, file_path, file_size, file_type, iv, uploaded_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    foreach ($uploaded_files as $file) {
        $stmt->bind_param(
            "issisb",
            $inquiry_id,
            $file['original_name'],
            $file['path'],
            $file['file_size'],
            $file['file_type'],
            $file['iv']
        );
        $stmt->send_long_data(5, $file['iv']);
        $stmt->execute();
    }
    $stmt->close();

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    error_log($e->getMessage());
    $_SESSION['error'] = "Something went wrong. Please try again.";
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}

// ── Fetch user details for email ───────────────────────────────────────────
$user_stmt = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user      = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

$fullname  = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$user_email = $user['email'] ?? '';

// ── Email config ───────────────────────────────────────────────────────────
require __DIR__ . '/PHPMailer/vendor/autoload.php';

$smtp_host     = 'smtp.gmail.com';
$smtp_user     = 'kioshiiofficial@gmail.com';   // ← change if needed
$smtp_pass     = 'jzri aqgh tovz hepu';          // ← change if needed
$smtp_port     = 587;
$admin_email   = 'kioshiiofficial@gmail.com';    // ← admin notification recipient
$company_name  = 'JRN Business Solutions Co.';
$submitted_at  = date('F j, Y, g:i a');
$price_display = '₱' . number_format($final_price, 2);

// ── Helper: build mailer instance ─────────────────────────────────────────
function buildMailer(string $host, string $user, string $pass, int $port): PHPMailer
{
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $host;
    $mail->SMTPAuth   = true;
    $mail->Username   = $user;
    $mail->Password   = $pass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $port;
    $mail->isHTML(true);
    $mail->setFrom($user, 'JRN Business Solutions');
    return $mail;
}

// ─────────────────────────────────────────────────────────────────────────
// EMAIL 1 — USER CONFIRMATION
// ─────────────────────────────────────────────────────────────────────────
if (!empty($user_email)) {
    try {
        $mail = buildMailer($smtp_host, $smtp_user, $smtp_pass, $smtp_port);
        $mail->addAddress($user_email, $fullname);
        $mail->Subject = "Inquiry Received – {$inquiry_number} | {$company_name}";
        $mail->Body    = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:40px 20px;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0"
           style="background:linear-gradient(135deg,#0F3A40 0%,#1C4F50 100%);border-radius:20px;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,0.15);">

      <!-- Header -->
      <tr><td style="padding:36px 40px 28px;text-align:center;border-bottom:2px solid rgba(217,255,0,0.25);">
        <h1 style="color:#D9FF00;font-size:22px;margin:0;font-weight:700;letter-spacing:-0.3px;">
          {$company_name}
        </h1>
        <p style="color:rgba(255,255,255,0.65);font-size:13px;margin:6px 0 0;">
          Your Partner in Business Compliance and Growth
        </p>
      </td></tr>

      <!-- Success Banner -->
      <tr><td style="padding:0;">
        <div style="background:rgba(217,255,0,0.12);padding:16px 40px;text-align:center;border-bottom:1px solid rgba(217,255,0,0.2);">
          <span style="font-size:28px;">✅</span>
          <span style="color:#D9FF00;font-size:16px;font-weight:700;vertical-align:middle;margin-left:10px;">
            Inquiry Successfully Submitted
          </span>
        </div>
      </td></tr>

      <!-- Body -->
      <tr><td style="padding:36px 40px;color:#fff;">
        <p style="font-size:17px;margin:0 0 8px;">Hello, <strong style="color:#D9FF00;">{$fullname}</strong>!</p>
        <p style="font-size:14px;color:rgba(255,255,255,0.85);line-height:1.7;margin:0 0 28px;">
          We have received your service inquiry and our team will review it shortly.
          Here are your inquiry details for your reference:
        </p>

        <!-- Details Card -->
        <table width="100%" cellpadding="0" cellspacing="0"
               style="background:rgba(255,255,255,0.08);border-radius:12px;border:1px solid rgba(255,255,255,0.12);margin-bottom:24px;">
          <tr><td style="padding:20px 24px;">
            <p style="color:#D9FF00;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;margin:0 0 16px;">
              📋 Inquiry Details
            </p>
            <table width="100%" cellpadding="7" cellspacing="0">
              <tr><td style="color:rgba(255,255,255,0.65);font-size:13px;width:140px;">Reference #</td>
                  <td style="color:#fff;font-size:13px;font-weight:700;font-family:monospace;">{$inquiry_number}</td></tr>
              <tr><td style="color:rgba(255,255,255,0.65);font-size:13px;">Service</td>
                  <td style="color:#fff;font-size:13px;">{$service_name}</td></tr>
              <tr><td style="color:rgba(255,255,255,0.65);font-size:13px;">Processing Type</td>
                  <td style="font-size:13px;">
                    <span style="background:rgba(217,255,0,0.2);color:#D9FF00;padding:3px 10px;border-radius:999px;font-weight:700;font-size:12px;">
                      {$proc_display}
                    </span>
                  </td></tr>
              <tr><td style="color:rgba(255,255,255,0.65);font-size:13px;">Est. Timeline</td>
                  <td style="color:#fff;font-size:13px;">{$proc_timeline}</td></tr>
              <tr><td style="color:rgba(255,255,255,0.65);font-size:13px;">Estimated Fee</td>
                  <td style="color:#D9FF00;font-size:14px;font-weight:700;">{$price_display}</td></tr>
              <tr><td style="color:rgba(255,255,255,0.65);font-size:13px;">Date Submitted</td>
                  <td style="color:#fff;font-size:13px;">{$submitted_at}</td></tr>
            </table>
          </td></tr>
        </table>

        <!-- Next Steps -->
        <table width="100%" cellpadding="0" cellspacing="0"
               style="background:rgba(255,255,255,0.06);border-radius:12px;border:1px solid rgba(255,255,255,0.10);margin-bottom:24px;">
          <tr><td style="padding:20px 24px;">
            <p style="color:#D9FF00;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;margin:0 0 14px;">
              🚀 What Happens Next?
            </p>
            <table cellpadding="0" cellspacing="0">
              <tr>
                <td style="padding:6px 0;vertical-align:top;">
                  <span style="background:#D9FF00;color:#0F3A40;font-size:11px;font-weight:800;width:20px;height:20px;border-radius:50%;display:inline-block;text-align:center;line-height:20px;margin-right:10px;">1</span>
                </td>
                <td style="padding:6px 0;color:rgba(255,255,255,0.85);font-size:13px;line-height:1.5;">
                  Our team will review your submitted documents and inquiry details.
                </td>
              </tr>
              <tr>
                <td style="padding:6px 0;vertical-align:top;">
                  <span style="background:#D9FF00;color:#0F3A40;font-size:11px;font-weight:800;width:20px;height:20px;border-radius:50%;display:inline-block;text-align:center;line-height:20px;margin-right:10px;">2</span>
                </td>
                <td style="padding:6px 0;color:rgba(255,255,255,0.85);font-size:13px;line-height:1.5;">
                  A quotation and billing invoice will be generated and visible in your
                  <strong style="color:#D9FF00;">Account &gt; Billing</strong> section.
                </td>
              </tr>
              <tr>
                <td style="padding:6px 0;vertical-align:top;">
                  <span style="background:#D9FF00;color:#0F3A40;font-size:11px;font-weight:800;width:20px;height:20px;border-radius:50%;display:inline-block;text-align:center;line-height:20px;margin-right:10px;">3</span>
                </td>
                <td style="padding:6px 0;color:rgba(255,255,255,0.85);font-size:13px;line-height:1.5;">
                  You can track your inquiry status anytime using your reference number
                  <strong style="color:#D9FF00;">{$inquiry_number}</strong> in your account dashboard.
                </td>
              </tr>
              <tr>
                <td style="padding:6px 0;vertical-align:top;">
                  <span style="background:#D9FF00;color:#0F3A40;font-size:11px;font-weight:800;width:20px;height:20px;border-radius:50%;display:inline-block;text-align:center;line-height:20px;margin-right:10px;">4</span>
                </td>
                <td style="padding:6px 0;color:rgba(255,255,255,0.85);font-size:13px;line-height:1.5;">
                  Once confirmed, we will begin processing your request within the
                  <strong style="color:#D9FF00;">{$proc_timeline}</strong> timeline.
                </td>
              </tr>
            </table>
          </td></tr>
        </table>

        <!-- Contact -->
        <table width="100%" cellpadding="0" cellspacing="0"
               style="background:rgba(255,255,255,0.06);border-radius:12px;border:1px solid rgba(255,255,255,0.10);">
          <tr><td style="padding:16px 24px;">
            <p style="color:#D9FF00;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;margin:0 0 10px;">
              📞 Need Help?
            </p>
            <p style="color:rgba(255,255,255,0.8);font-size:13px;margin:0;line-height:1.6;">
              Email us at <a href="mailto:{$smtp_user}" style="color:#D9FF00;">{$smtp_user}</a>
              or visit your account dashboard to track your inquiry.<br>
              Please include your reference number <strong style="color:#D9FF00;">{$inquiry_number}</strong>
              in all communications.
            </p>
          </td></tr>
        </table>
      </td></tr>

      <!-- Security Notice -->
      <tr><td style="padding:18px 40px;background:rgba(0,0,0,0.18);border-top:1px solid rgba(255,255,255,0.08);">
        <p style="color:rgba(255,255,255,0.65);font-size:12px;margin:0;text-align:center;">
          🔒 If you did not submit this inquiry, please contact us immediately.
        </p>
      </td></tr>

      <!-- Footer -->
      <tr><td style="padding:24px 40px;background:#0B2B2E;text-align:center;">
        <p style="color:rgba(255,255,255,0.5);font-size:12px;margin:0;">
          This is an automated message from <strong style="color:#D9FF00;">{$company_name}</strong>.
          Please do not reply to this email.<br>
          © {$company_name}. All Rights Reserved.
        </p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
        $mail->send();
    } catch (Exception $e) {
        error_log("User email error: " . $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────────────────
// EMAIL 2 — ADMIN NOTIFICATION
// ─────────────────────────────────────────────────────────────────────────
try {
    $mail2 = buildMailer($smtp_host, $smtp_user, $smtp_pass, $smtp_port);
    $mail2->addAddress($admin_email, 'JRN Admin');
    $mail2->Subject = "🔔 New Inquiry – {$inquiry_number} | {$proc_display}";
    $mail2->Body    = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:40px 20px;">
  <tr><td align="center">
    <table width="580" cellpadding="0" cellspacing="0"
           style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.10);border:1px solid #e2e8f0;">

      <!-- Header -->
      <tr><td style="background:linear-gradient(135deg,#0F3A40,#1C4F50);padding:28px 36px;text-align:center;">
        <p style="color:#D9FF00;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;margin:0 0 6px;">
          JRN Admin Notification
        </p>
        <h1 style="color:#fff;font-size:20px;margin:0;font-weight:700;">🔔 New Service Inquiry</h1>
      </td></tr>

      <!-- Alert Badge -->
      <tr><td style="padding:0;">
        <div style="background:#fef3c7;border-bottom:1px solid #fde68a;padding:12px 36px;text-align:center;">
          <span style="color:#92400e;font-size:13px;font-weight:600;">
            ⚡ Action Required — Please review and update the inquiry status.
          </span>
        </div>
      </td></tr>

      <!-- Inquiry Details -->
      <tr><td style="padding:32px 36px;">
        <p style="font-size:14px;color:#374151;margin:0 0 20px;">
          A new inquiry has been submitted through the JRN Business Solutions portal.
          Here are the details:
        </p>

        <table width="100%" cellpadding="0" cellspacing="0"
               style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;margin-bottom:24px;">
          <tr style="background:#f9fafb;">
            <td colspan="2" style="padding:12px 18px;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.07em;border-bottom:1px solid #e5e7eb;">
              Inquiry Information
            </td>
          </tr>
          <tr style="border-bottom:1px solid #f3f4f6;">
            <td style="padding:11px 18px;font-size:13px;color:#6b7280;width:160px;">Reference #</td>
            <td style="padding:11px 18px;font-size:13px;font-weight:700;color:#0F3A40;font-family:monospace;">{$inquiry_number}</td>
          </tr>
          <tr style="border-bottom:1px solid #f3f4f6;background:#fafafa;">
            <td style="padding:11px 18px;font-size:13px;color:#6b7280;">Customer Name</td>
            <td style="padding:11px 18px;font-size:13px;font-weight:600;color:#111827;">{$fullname}</td>
          </tr>
          <tr style="border-bottom:1px solid #f3f4f6;">
            <td style="padding:11px 18px;font-size:13px;color:#6b7280;">Customer Email</td>
            <td style="padding:11px 18px;font-size:13px;color:#111827;">{$user_email}</td>
          </tr>
          <tr style="border-bottom:1px solid #f3f4f6;background:#fafafa;">
            <td style="padding:11px 18px;font-size:13px;color:#6b7280;">Service Requested</td>
            <td style="padding:11px 18px;font-size:13px;font-weight:600;color:#111827;">{$service_name}</td>
          </tr>
          <tr style="border-bottom:1px solid #f3f4f6;">
            <td style="padding:11px 18px;font-size:13px;color:#6b7280;">Processing Type</td>
            <td style="padding:11px 18px;">
              <span style="background:#ecfdf5;color:#065f46;font-size:12px;font-weight:700;padding:3px 10px;border-radius:999px;border:1px solid #a7f3d0;">
                {$proc_display}
              </span>
            </td>
          </tr>
          <tr style="border-bottom:1px solid #f3f4f6;background:#fafafa;">
            <td style="padding:11px 18px;font-size:13px;color:#6b7280;">Est. Timeline</td>
            <td style="padding:11px 18px;font-size:13px;color:#111827;">{$proc_timeline}</td>
          </tr>
          <tr style="border-bottom:1px solid #f3f4f6;">
            <td style="padding:11px 18px;font-size:13px;color:#6b7280;">Estimated Fee</td>
            <td style="padding:11px 18px;font-size:14px;font-weight:700;color:#0F3A40;">{$price_display}</td>
          </tr>
          <tr>
            <td style="padding:11px 18px;font-size:13px;color:#6b7280;">Date Submitted</td>
            <td style="padding:11px 18px;font-size:13px;color:#111827;">{$submitted_at}</td>
          </tr>
        </table>

        <!-- Action Reminder -->
        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px 20px;">
          <p style="color:#1e40af;font-size:13px;font-weight:600;margin:0 0 8px;">📌 Quick Action Checklist</p>
          <ul style="color:#1e40af;font-size:13px;margin:0;padding-left:18px;line-height:1.8;">
            <li>Log in to the admin panel and locate inquiry <strong>{$inquiry_number}</strong></li>
            <li>Review the uploaded documents</li>
            <li>Update the status to <em>In Review</em></li>
            <li>Generate a billing invoice for this service</li>
            <li>Notify the client of any additional requirements if needed</li>
          </ul>
        </div>
      </td></tr>

      <!-- Footer -->
      <tr><td style="padding:20px 36px;background:#f9fafb;border-top:1px solid #e5e7eb;text-align:center;">
        <p style="color:#9ca3af;font-size:12px;margin:0;">
          Automated notification from <strong style="color:#0F3A40;">{$company_name}</strong> Admin System.
        </p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
    $mail2->send();
} catch (Exception $e) {
    error_log("Admin email error: " . $e->getMessage());
}

// ── Session summary & redirect ─────────────────────────────────────────────
$_SESSION['inquiry_summary'] = [
    'inquiry_number'  => $inquiry_number,
    'service_name'    => $service_name,
    'processing_type' => $proc_display,
    'proc_timeline'   => $proc_timeline,
    'final_price'     => $final_price,
    'additional_notes' => $additional_notes,
    'created_at'      => $submitted_at,
    'uploaded_files'  => $uploaded_files,
    'user_id'         => $user_id,
];

header("Location: inquiry_summary.php");
exit();
