<?php
session_start();
require_once 'connection/dbconn.php';

$ref = $_GET['ref'] ?? '';
if (empty($ref)) {
    http_response_code(400);
    echo 'Missing inquiry reference.';
    exit();
}

$stmt = $conn->prepare("
    SELECT inquiry_number, user_id, service_name, price, additional_notes,
           status, rejection_reason, created_at, qr_code_path
    FROM inquiries
    WHERE inquiry_number = ?
    LIMIT 1
");
$stmt->bind_param("s", $ref);
$stmt->execute();
$result  = $stmt->get_result();
$inquiry = $result->fetch_assoc();
$stmt->close();

if (!$inquiry) {
    http_response_code(404);
    echo 'Inquiry not found.';
    exit();
}

// optional client details
$u = $conn->prepare("SELECT fullname, first_name, last_name, email, phone FROM users WHERE id = ?");
$u->bind_param("i", $inquiry['user_id']);
$u->execute();
$user = $u->get_result()->fetch_assoc();
$u->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Inquiry Status | JRN Business Solutions Co.</title>
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/track-inquiry.css">
</head>

<body>
    <main class="track-page">
        <div class="track-card">

            <div class="track-header">
                <div class="track-brand">
                    <h1>JRN Business Solutions Co.</h1>
                    <p>Inquiry status and service tracking</p>
                </div>
                <div class="track-meta">
                    <h2>Inquiry Status</h2>
                    <span>Reference: <?php echo htmlspecialchars($inquiry['inquiry_number']); ?></span>
                    <span>Submitted: <?php echo htmlspecialchars($inquiry['created_at']); ?></span>
                </div>
            </div>

            <div class="track-section">
                <div class="track-column">
                    <h3>Service Details</h3>
                    <p><strong>Service:</strong> <?php echo htmlspecialchars($inquiry['service_name']); ?></p>
                    <p><strong>Price:</strong> ₱<?php echo htmlspecialchars(number_format($inquiry['price'], 2)); ?></p>
                    <p>
                        <strong>Status:</strong>
                        <?php
                        $status = $inquiry['status'];
                        $statusClass = 'status-' . $status;
                        ?>
                        <span class="status-pill <?php echo htmlspecialchars($statusClass); ?>">
                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $status))); ?>
                        </span>
                    </p>
                    <?php if (!empty($inquiry['rejection_reason'])): ?>
                        <p><strong>Reason:</strong> <?php echo nl2br(htmlspecialchars($inquiry['rejection_reason'])); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($inquiry['additional_notes'])): ?>
                        <p><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($inquiry['additional_notes'])); ?></p>
                    <?php endif; ?>
                </div>

                <?php if ($user): ?>
                    <div class="track-column">
                        <h3>Client Information</h3>
                        <p><strong>Name:</strong>
                            <?php
                            $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                            echo htmlspecialchars($name ?: ($user['fullname'] ?? '—'));
                            ?>
                        </p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email'] ?? '—'); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($user['phone'] ?? '—'); ?></p>
                    </div>
                <?php endif; ?>

                <div class="track-column track-qr">
                    <h3>Inquiry QR Code</h3>
                    <?php if (!empty($inquiry['qr_code_path'])): ?>
                        <img src="<?php echo htmlspecialchars($inquiry['qr_code_path']); ?>"
                            alt="Inquiry QR Code">
                        <a href="<?php echo htmlspecialchars($inquiry['qr_code_path']); ?>"
                            download="inquiry-<?php echo htmlspecialchars($inquiry['inquiry_number']); ?>-qr.png">
                            Download QR Code
                        </a>
                    <?php else: ?>
                        <p>No QR code available for this inquiry.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="track-actions">
                <button class="btn-outline" onclick="window.print()">Print</button>
                <button class="btn-outline" onclick="window.location.href='account_page.php#services'">
                    Back to Account
                </button>
            </div>

        </div>
    </main>
</body>

</html>