<?php
session_start();
require_once 'connection/dbconn.php';
require_once 'includes/activity_logger.php';

// Log logout BEFORE destroying session
if (isset($_SESSION['user_id'])) {
    // Determine user type
    $user_type = 'user'; // Default
    if (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'employee') {
        // It's an employee or admin
        $user_type = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 'admin' : 'employee';
    }

    // Get user name for better log description
    $user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Unknown User';

    // Log the logout activity
    logActivity(
        $_SESSION['user_id'],
        $user_type,
        'logout',
        $user_name . ' logged out'
    );
}

// Now destroy the session
session_unset();
session_destroy();

header("Location: index.php");
exit;
