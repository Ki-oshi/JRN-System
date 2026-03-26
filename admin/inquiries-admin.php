<?php
session_start();
require_once '../connection/dbconn.php';
require_once '../includes/auth.php';
require_once '../includes/activity_logger.php';

requireAdmin();

$message = '';
$message_type = '';

// Handle status update
if (isset($_POST['update_status'])) {
    require_once '../includes/activity_logger.php';

    $inquiry_id = (int)$_POST['inquiry_id'];
    $status = $_POST['status'];
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');

    // Get inquiry details for logging
    $inquiry_stmt = $conn->prepare("
        SELECT i.inquiry_number, i.service_name, i.status as old_status,
               u.first_name, u.last_name, u.email
        FROM inquiries i
        LEFT JOIN users u ON i.user_id = u.id
        WHERE i.id = ?
    ");
    $inquiry_stmt->bind_param("i", $inquiry_id);
    $inquiry_stmt->execute();
    $inquiry_details = $inquiry_stmt->get_result()->fetch_assoc();

    if (!$inquiry_details) {
        $message = "Inquiry not found";
        $message_type = "error";
    } elseif ($inquiry_details['old_status'] === 'rejected') {
        $message = "Cannot modify a rejected inquiry. Rejected inquiries are final.";
        $message_type = "error";

        // Log attempt to modify rejected inquiry
        logActivity(
            $_SESSION['user_id'],
            isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'employee'
                ? ((isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 'admin' : 'employee')
                : 'user',
            'inquiry_update_failed',
            "Attempted to modify rejected inquiry #{$inquiry_details['inquiry_number']}"
        );
    } else {
        // If status is rejected, rejection reason is required
        if ($status === 'rejected' && empty($rejection_reason)) {
            $message = "Rejection reason is required when rejecting an inquiry";
            $message_type = "error";
        } else {
            // Update with rejection reason if provided
            if ($status === 'rejected') {
                $stmt = $conn->prepare("UPDATE inquiries SET status = ?, rejection_reason = ?, updated_at = NOW() WHERE id = ? AND status != 'rejected'");
                $stmt->bind_param("ssi", $status, $rejection_reason, $inquiry_id);
            } else {
                // Clear rejection reason if status is not rejected
                $stmt = $conn->prepare("UPDATE inquiries SET status = ?, rejection_reason = NULL, updated_at = NOW() WHERE id = ? AND status != 'rejected'");
                $stmt->bind_param("si", $status, $inquiry_id);
            }

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $message = "Inquiry status updated successfully";
                $message_type = "success";

                // Determine user type for logging
                $user_type = 'user';
                if (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'employee') {
                    $user_type = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 'admin' : 'employee';
                }

                // Build description with user info
                $user_info = $inquiry_details['first_name'] && $inquiry_details['last_name']
                    ? "{$inquiry_details['first_name']} {$inquiry_details['last_name']}"
                    : $inquiry_details['email'];

                $old_status_formatted = ucfirst(str_replace('_', ' ', $inquiry_details['old_status']));
                $new_status_formatted = ucfirst(str_replace('_', ' ', $status));

                // Create detailed log description
                $log_description = "Updated inquiry #{$inquiry_details['inquiry_number']} for {$user_info} - " .
                    "Service: {$inquiry_details['service_name']} - " .
                    "Status changed from '{$old_status_formatted}' to '{$new_status_formatted}'";

                // Add rejection reason to log if applicable
                if ($status === 'rejected' && !empty($rejection_reason)) {
                    $log_description .= " - Reason: " . substr($rejection_reason, 0, 100);
                }

                // Log the inquiry status update
                logActivity(
                    $_SESSION['user_id'],
                    $user_type,
                    'inquiry_status_updated',
                    $log_description
                );
            } elseif ($stmt->affected_rows === 0) {
                $message = "Cannot modify a rejected inquiry";
                $message_type = "error";
            } else {
                $message = "Error updating inquiry status";
                $message_type = "error";
            }
        }
    }
}
// Filter by status
$filter = $_GET['filter'] ?? 'all';
$allowed_filters = ['all', 'pending', 'in_review', 'completed', 'rejected'];
if (!in_array($filter, $allowed_filters)) {
    $filter = 'all';
}

// Pending bills badge
$stmt = $conn->prepare("SELECT COUNT(*) as pending_bills FROM billings WHERE status = 'unpaid'");
$stmt->execute();
$pending_bills = $stmt->get_result()->fetch_assoc()['pending_bills'];

// Fetch inquiries with user information
if ($filter === 'all') {
    $stmt = $conn->prepare("
        SELECT i.*, 
               u.first_name, u.last_name, u.email, u.phone, u.account_number,
               COUNT(d.id) as document_count
        FROM inquiries i
        LEFT JOIN users u ON i.user_id = u.id
        LEFT JOIN inquiry_documents d ON i.id = d.inquiry_id
        GROUP BY i.id
        ORDER BY i.created_at DESC
    ");
} else {
    $stmt = $conn->prepare("
        SELECT i.*, 
               u.first_name, u.last_name, u.email, u.phone, u.account_number,
               COUNT(d.id) as document_count
        FROM inquiries i
        LEFT JOIN users u ON i.user_id = u.id
        LEFT JOIN inquiry_documents d ON i.id = d.inquiry_id
        WHERE i.status = ?
        GROUP BY i.id
        ORDER BY i.created_at DESC
    ");
    $stmt->bind_param("s", $filter);
}

$stmt->execute();
$inquiries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Count by status
$stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM inquiries GROUP BY status");
$stmt->execute();
$status_counts = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $status_counts[$row['status']] = $row['count'];
}
$total_inquiries = array_sum($status_counts);

// Get admin info
$is_from_employees = isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'employee';
if ($is_from_employees) {
    $stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
} else {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
}
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inquiries Management - JRN Admin</title>
    <link rel="stylesheet" href="assets/css/index-admin.css">
    <link rel="stylesheet" href="assets/css/inquiries-admin.css">
    <link rel="stylesheet" href="assets/css/logout-modal.css">
</head>

<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <img src="../assets/img/logo.jpg" alt="Logo" class="logo-small">
                <h2>JRN Admin</h2>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="index-admin.php" class="nav-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                Dashboard
            </a>

            <a href="inquiries-admin.php" class="nav-item active">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                Inquiries
                <?php if (isset($pending_inquiries) && $pending_inquiries > 0): ?>
                    <span class="badge"><?php echo $pending_inquiries; ?></span>
                <?php endif; ?>
            </a>

            <?php if (isAdmin()): ?>
                <a href="billing-admin.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="6" rx="1" />
                        <rect x="3" y="12" width="18" height="8" rx="1" />
                        <line x1="7" y1="16" x2="11" y2="16" />
                        <line x1="7" y1="19" x2="15" y2="19" />
                    </svg>
                    Billing
                    <?php if (isset($pending_bills) && $pending_bills > 0): ?>
                        <span class="badge"><?php echo $pending_bills; ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

            <a href="users-admin.php" class="nav-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                </svg>
                Users
            </a>

            <?php if (isAdmin()): ?>
                <a href="employees-admin.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    Employees
                </a>
                <a href="activity-logs.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    Activity Logs
                </a>
                <a href="services-admin.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="6" width="18" height="12" rx="2" />
                        <path d="M3 10h18" />
                    </svg>
                    Manage Services
                </a>
            <?php endif; ?>

            <div style="margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                <a href="admin-account.php" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M12 1v6m0 6v6m6-11h-6m-6 0H1m18.4-3.6l-4.2 4.2m-8.4 0l-4.2-4.2M18.4 18.4l-4.2-4.2m-8.4 0l-4.2 4.2"></path>
                    </svg>
                    My Account
                </a>
            </div>
        </nav>
        <div class="sidebar-footer">
            <a href="#" class="nav-item logout" id="logout-btn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                Logout
            </a>
        </div>
    </aside>

    <!-- Logout Modal -->
    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="logout-modal-overlay" id="logout-modal-overlay">
            <div class="logout-modal">
                <h2>Confirm Logout</h2>
                <p>Are you sure you want to log out?</p>
                <div class="logout-modal-buttons">
                    <button class="logout-btn-confirm" id="logout-confirm">Yes</button>
                    <button class="logout-btn-cancel" id="logout-cancel">Cancel</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="assets/js/logout-modal.js"></script>

    <main class="main-content">
        <header class="admin-header">
            <div class="admin-header-left">
                <h1>Service Inquiries</h1>
                <p class="header-subtitle">Manage customer service requests and applications</p>
            </div>
            <div class="admin-header-right">
                <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">
                    <svg class="moon-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                    </svg>
                    <svg class="sun-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="5"></circle>
                        <line x1="12" y1="1" x2="12" y2="3"></line>
                        <line x1="12" y1="21" x2="12" y2="23"></line>
                        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                        <line x1="1" y1="12" x2="3" y2="12"></line>
                        <line x1="21" y1="12" x2="23" y2="12"></line>
                        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                    </svg>
                </button>
                <div class="avatar-circle"><?php echo strtoupper(substr($admin['first_name'] ?? 'A', 0, 1)); ?></div>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert--<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                All Inquiries
                <span class="count"><?php echo $total_inquiries; ?></span>
            </a>
            <a href="?filter=pending" class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                Pending
                <span class="count"><?php echo $status_counts['pending'] ?? 0; ?></span>
            </a>
            <a href="?filter=in_review" class="filter-tab <?php echo $filter === 'in_review' ? 'active' : ''; ?>">
                In Review
                <span class="count"><?php echo $status_counts['in_review'] ?? 0; ?></span>
            </a>
            <a href="?filter=completed" class="filter-tab <?php echo $filter === 'completed' ? 'active' : ''; ?>">
                Completed
                <span class="count"><?php echo $status_counts['completed'] ?? 0; ?></span>
            </a>
            <a href="?filter=rejected" class="filter-tab <?php echo $filter === 'rejected' ? 'active' : ''; ?>">
                Rejected
                <span class="count"><?php echo $status_counts['rejected'] ?? 0; ?></span>
            </a>
        </div>

        <div class="card">
            <div class="card-header">
                <h2><?php echo ucfirst(str_replace('_', ' ', $filter)); ?> Inquiries (<?php echo count($inquiries); ?>)</h2>
            </div>
            <?php if (count($inquiries) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Inquiry #</th>
                            <th>Account #</th>
                            <th>User</th>
                            <th>Service</th>
                            <th>Documents</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inquiries as $inquiry): ?>
                            <tr>
                                <td>
                                    <code style="font-size:0.75rem;color:var(--primary);font-weight:600;">
                                        <?php echo htmlspecialchars($inquiry['inquiry_number'] ?? 'N/A'); ?>
                                    </code>
                                </td>
                                <td>
                                    <code style="font-size:0.75rem;color:var(--text-secondary);font-weight:500;">
                                        <?php echo htmlspecialchars($inquiry['account_number'] ?? 'N/A'); ?>
                                    </code>
                                </td>
                                <td>
                                    <div class="contact-cell">
                                        <strong>
                                            <?php
                                            if (!empty($inquiry['first_name']) && !empty($inquiry['last_name'])) {
                                                echo htmlspecialchars($inquiry['first_name'] . ' ' . $inquiry['last_name']);
                                            } else {
                                                echo 'Unknown User';
                                            }
                                            ?>
                                        </strong>
                                        <small><?php echo htmlspecialchars($inquiry['email'] ?? 'N/A'); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($inquiry['service_name']); ?></strong>
                                    <?php if (!empty($inquiry['additional_notes'])): ?>
                                        <p class="message-preview"><?php echo htmlspecialchars(substr($inquiry['additional_notes'], 0, 60)) . '...'; ?></p>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($inquiry['document_count'] > 0): ?>
                                        <span class="document-badge">
                                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                                                <polyline points="13 2 13 9 20 9"></polyline>
                                            </svg>
                                            <?php echo $inquiry['document_count']; ?> file<?php echo $inquiry['document_count'] > 1 ? 's' : ''; ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);font-size:0.75rem;">No files</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = match ($inquiry['status']) {
                                        'pending'   => 'status--warning',
                                        'in_review' => 'status--info',
                                        'completed' => 'status--success',
                                        'rejected'  => 'status--error',
                                        default     => 'status--secondary'
                                    };
                                    ?>
                                    <span class="status <?php echo $statusClass; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $inquiry['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($inquiry['created_at'])); ?></td>
                                <td>
                                    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                                        <button class="btn btn--sm btn--outline" onclick="viewInquiry(<?php echo $inquiry['id']; ?>)">View</button>
                                        <?php if ($inquiry['status'] !== 'rejected'): ?>
                                            <button class="btn btn--sm btn--primary" onclick="updateStatus(<?php echo $inquiry['id']; ?>, '<?php echo $inquiry['status']; ?>')">Update</button>
                                        <?php else: ?>
                                            <span class="btn btn--sm" style="background:var(--gray-200);color:var(--text-secondary);cursor:not-allowed;" title="Rejected inquiries cannot be modified">
                                                Rejected
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <svg width="64" height="64" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                    <p>No <?php echo $filter === 'all' ? '' : $filter; ?> inquiries found</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- View Inquiry Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Inquiry Details</h2>
                <button class="modal-close" onclick="closeModal('viewModal')">&times;</button>
            </div>
            <div id="modalBody" class="modal-body"></div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Update Inquiry Status</h2>
                <button class="modal-close" onclick="closeModal('updateModal')">&times;</button>
            </div>
            <form method="POST" class="modal-body">
                <input type="hidden" name="inquiry_id" id="updateInquiryId">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="updateStatus" class="form-control" required onchange="toggleRejectionReason()">
                        <option value="pending">Pending</option>
                        <option value="in_review">In Review</option>
                        <option value="completed">Completed</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="form-group" id="rejectionReasonGroup" style="display:none;">
                    <label>Rejection Reason <span style="color:var(--danger);">*</span></label>
                    <textarea name="rejection_reason" id="rejectionReason" class="form-control" rows="4" placeholder="Please provide a detailed reason for rejecting this inquiry..."></textarea>
                    <small style="color:var(--text-secondary);display:block;margin-top:0.5rem;">
                        This will be visible to the user.
                    </small>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_status" class="btn btn--primary">Update Status</button>
                    <button type="button" class="btn btn--outline" onclick="closeModal('updateModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const themeToggle = document.getElementById('themeToggle');
        const htmlElement = document.documentElement;
        const currentTheme = localStorage.getItem('theme') || 'light';
        htmlElement.setAttribute('data-theme', currentTheme);
        themeToggle.addEventListener('click', () => {
            const theme = htmlElement.getAttribute('data-theme');
            const newTheme = theme === 'light' ? 'dark' : 'light';
            htmlElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        });

        const inquiries = <?php echo json_encode($inquiries); ?>;

        function generateMaskedName() {
            const length = 16;
            const required = "JRN"; // must appear somewhere
            const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()-_=+[]{}";

            // Start with required token
            let result = required.split("");

            // Fill remaining characters randomly
            while (result.length < length) {
                const randomChar = chars.charAt(Math.floor(Math.random() * chars.length));
                result.push(randomChar);
            }

            // Shuffle to mix JRN into random positions
            for (let i = result.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [result[i], result[j]] = [result[j], result[i]];
            }

            return result.join("");
        }

        function viewInquiry(id) {
            const inquiry = inquiries.find(i => i.id == id);
            if (!inquiry) return;

            fetch(`get_inquiry_documents.php?inquiry_id=${id}`)
                .then(res => {
                    if (!res.ok) {
                        console.error('Documents request failed:', res.status, res.statusText);
                        return [];
                    }
                    return res.text().then(text => {
                        if (!text) {
                            return [];
                        }
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('Invalid JSON from get_inquiry_documents.php:', text);
                            return [];
                        }
                    });
                })
                .then(documents => {
                    let documentsHtml = '';
                    if (documents.length > 0) {
                        documentsHtml = `
        <div class="detail-row full">
            <strong>Attached Documents:</strong>
            <div style="margin-top: 0.5rem;">
                ${documents.map(doc => `
                    <div style="padding: 0.5rem; background: var(--gray-100); border-radius: 6px; margin-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong>${generateMaskedName()}</strong>
<small style="display: block; color: var(--text-secondary);">
    ${doc.id_type ? doc.id_type + ' • ' : ''}${(doc.file_size / 1024).toFixed(2)} KB
</small>
                        </div>
                        <a href="../download_document.php?id=${doc.id}" target="_blank" class="btn btn--sm btn--outline">Download</a>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
                    }

                    document.getElementById('modalBody').innerHTML = `
                        <div class="inquiry-detail">
                            <div class="detail-row">
                                <strong>Inquiry Number:</strong>
                                <span style="color:var(--primary);font-weight:600;font-family:monospace;">${inquiry.inquiry_number || 'N/A'}</span>
                            </div>
                            <div class="detail-row">
                                <strong>User:</strong>
                                <span>${inquiry.first_name ? inquiry.first_name + ' ' + inquiry.last_name : 'Unknown'}</span>
                            </div>
                            <div class="detail-row">
                                <strong>Account #:</strong>
                                <span>${inquiry.account_number || 'N/A'}</span>
                            </div>
                            <div class="detail-row">
                                <strong>Email:</strong>
                                <span>${inquiry.email || 'N/A'}</span>
                            </div>
                            <div class="detail-row">
                                <strong>Phone:</strong>
                                <span>${inquiry.phone || 'N/A'}</span>
                            </div>
                            <div class="detail-row">
                                <strong>Service:</strong>
                                <span>${inquiry.service_name}</span>
                            </div>
                            ${inquiry.additional_notes ? `
                            <div class="detail-row full">
                                <strong>Additional Notes:</strong>
                                <p style="margin-top:0.5rem;white-space:pre-wrap;">${inquiry.additional_notes}</p>
                            </div>
                            ` : ''}
                            ${documentsHtml}
                            <div class="detail-row">
                                <strong>Status:</strong>
                                <span style="${inquiry.status === 'rejected' ? 'color:var(--danger);font-weight:600;' : ''}">
                                    ${inquiry.status.replace('_',' ').charAt(0).toUpperCase() + inquiry.status.slice(1).replace('_',' ')}
                                </span>
                            </div>
                            ${inquiry.status === 'rejected' && inquiry.rejection_reason ? `
                            <div class="detail-row full">
                                <strong>Rejection Reason:</strong>
                                <p style="margin-top:0.5rem;padding:0.75rem;background:rgba(239,68,68,0.1);border-left:3px solid var(--danger);border-radius:6px;color:var(--text-primary);">
                                    ${inquiry.rejection_reason}
                                </p>
                                <small style="display:block;margin-top:0.5rem;color:var(--text-secondary);font-style:italic;">
                                    This inquiry has been permanently rejected and cannot be modified.
                                </small>
                            </div>
                            ` : ''}
                            <div class="detail-row">
                                <strong>Submitted:</strong>
                                <span>${new Date(inquiry.created_at).toLocaleString()}</span>
                            </div>
                            <div class="detail-row">
                                <strong>Last Updated:</strong>
                                <span>${new Date(inquiry.updated_at).toLocaleString()}</span>
                            </div>
                        </div>
                    `;
                    document.getElementById('viewModal').classList.add('active');
                })
                .catch(err => {
                    console.error('Error loading documents:', err);
                    alert('Error loading documents, check console for details.');
                });
        }

        function updateStatus(id, currentStatus) {
            document.getElementById('updateInquiryId').value = id;
            document.getElementById('updateStatus').value = currentStatus;
            document.getElementById('updateModal').classList.add('active');
        }

        function toggleRejectionReason() {
            const status = document.getElementById('updateStatus').value;
            const rejectionGroup = document.getElementById('rejectionReasonGroup');
            const rejectionTextarea = document.getElementById('rejectionReason');

            if (status === 'rejected') {
                rejectionGroup.style.display = 'block';
                rejectionTextarea.required = true;
            } else {
                rejectionGroup.style.display = 'none';
                rejectionTextarea.required = false;
                rejectionTextarea.value = '';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            toggleRejectionReason();
        });

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>

</html>