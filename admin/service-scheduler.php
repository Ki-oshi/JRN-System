<?php
require_once '../connection/dbconn.php';

$sql = "
    SELECT id, name, is_active, scheduled_action, scheduled_effective_at, NOW() AS server_now
    FROM services
    WHERE scheduled_action IS NOT NULL
      AND scheduled_effective_at IS NOT NULL
      AND scheduled_effective_at <= NOW()
";
$result = $conn->query($sql);

if (!$result) {
    // Log SQL error if any
    error_log('Scheduler SELECT error: ' . $conn->error);
    exit;
}

while ($row = $result->fetch_assoc()) {
    $newStatus = ($row['scheduled_action'] === 'activate') ? 1 : 0;

    $stmt = $conn->prepare("
        UPDATE services
        SET is_active = ?, 
            scheduled_action = NULL, 
            scheduled_at = NULL, 
            scheduled_effective_at = NULL
        WHERE id = ?
    ");
    $stmt->bind_param('ii', $newStatus, $row['id']);
    $stmt->execute();
    $stmt->close();
}

$conn->close();
