<?php
require_once '../connection/dbconn.php';

// Find services whose scheduled_effective_at has passed
$sql = "
    SELECT id, name, scheduled_action
    FROM services
    WHERE scheduled_action IS NOT NULL
      AND scheduled_effective_at IS NOT NULL
      AND scheduled_effective_at <= NOW()
";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $newStatus = ($row['scheduled_action'] === 'activate') ? 1 : 0;

        $stmt = $conn->prepare("
            UPDATE services
            SET is_active = ?, scheduled_action = NULL, scheduled_at = NULL, scheduled_effective_at = NULL, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param('ii', $newStatus, $row['id']);
        $stmt->execute();
    }
}
