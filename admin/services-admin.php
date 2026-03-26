<?php
session_start();
require_once '../connection/dbconn.php';
require_once '../includes/auth.php';

requireAdmin();

// Fetch unique categories for filter dropdown
$category_stmt = $conn->prepare("SELECT DISTINCT category FROM services ORDER BY category ASC");
$category_stmt->execute();
$categories = $category_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Selected category from filter
$category_filter = $_GET['category'] ?? '';

if ($category_filter && $category_filter !== 'all') {
    $stmt = $conn->prepare("SELECT * FROM services WHERE category = ? ORDER BY display_order ASC, name ASC");
    $stmt->bind_param("s", $category_filter);
} else {
    $stmt = $conn->prepare("SELECT * FROM services ORDER BY display_order ASC, name ASC");
}
$stmt->execute();
$services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle search
$search_query = trim($_GET['search'] ?? '');

// Paging
$per_page = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// Build WHERE clause
$where = [];
$params = [];
$types = '';

if ($category_filter && $category_filter !== 'all') {
    $where[] = 'category = ?';
    $params[] = $category_filter;
    $types .= 's';
}
if ($search_query) {
    $where[] = '(name LIKE ? OR slug LIKE ?)';
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $types .= 'ss';
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Get total count for paging
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM services $where_sql");
if ($params) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_count = $count_stmt->get_result()->fetch_row()[0] ?? 0;

// Fetch page of results
$sql = "SELECT * FROM services $where_sql ORDER BY display_order ASC, name ASC LIMIT $per_page OFFSET $offset";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Compute page count
$page_count = max(1, ceil($total_count / $per_page));

// Pending inquiries badge
$stmt = $conn->prepare("SELECT COUNT(*) as pending_count FROM inquiries WHERE status = 'pending'");
$stmt->execute();
$pending_inquiries = $stmt->get_result()->fetch_assoc()['pending_count'];

// Pending bills badge
$stmt = $conn->prepare("SELECT COUNT(*) as pending_bills FROM billings WHERE status = 'unpaid'");
$stmt->execute();
$pending_bills = $stmt->get_result()->fetch_assoc()['pending_bills'];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Services - JRN Admin</title>
    <link rel="stylesheet" href="assets/css/index-admin.css">
    <link rel="stylesheet" href="assets/css/services-admin.css">
    <link rel="stylesheet" href="assets/css/service-status-modal.css">
    <link rel="stylesheet" href="assets/css/service-delete-modal.css">
    <link rel="stylesheet" href="assets/css/logout-modal.css">
    <style>
        .service-schedule-info {
            margin-top: 4px;
            font-size: 0.75rem;
            color: #777;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
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
            <a href="inquiries-admin.php" class="nav-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" overflow="visible">
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
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" overflow="visible">
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
                <a href="services-admin.php" class="nav-item active">
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
                <h1>Manage Services</h1>
                <p class="header-subtitle">Add, edit, and enable/disable business services.</p>
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

        <section class="card">
            <div class="card-header">
                <h2>All Services</h2>
                <div class="admin-header-right">
                    <a href="services-add.php" class="btn btn--primary">+ Add New Service</a>
                </div>
            </div>
            <?php if (count($services) > 0): ?>
                <form method="GET" class="filter-search-bar">
                    <div style="display:flex;align-items:center;gap:0.7rem;">
                        <label for="search">Search:</label>
                        <input type="text" name="search" id="search" class="form-control"
                            placeholder="Search a Service" value="<?= htmlspecialchars($search_query ?? '') ?>">
                        <button class="btn btn--sm btn--primary" type="submit">Search</button>
                    </div>
                    <div style="display:flex;align-items:center;gap:0.7rem;">
                        <label for="category">Category:</label>
                        <select id="category" name="category" class="form-control" onchange="this.form.submit();">
                            <option value="all" <?= (!$category_filter || $category_filter === 'all') ? ' selected' : '' ?>>All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['category']) ?>" <?= ($cat['category'] === $category_filter) ? ' selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Service Name</th>
                            <th>Category</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $srv): ?>
                            <tr>
                                <td>
                                    <?php if ($srv['is_active']): ?>
                                        <span class="status status--success">Active</span>
                                    <?php else: ?>
                                        <span class="status status--error">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($srv['name']); ?></strong>
                                    <div style="font-size:0.8rem;color:#888;"><?php echo htmlspecialchars($srv['slug']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($srv['category']); ?></td>
                                <td>
                                    <div class="action-btn-group">
                                        <a href="services-edit.php?id=<?= $srv['id'] ?>" class="btn btn--sm btn--primary">Edit</a>

                                        <?php if ($srv['is_active']): ?>
                                            <a href="javascript:void(0);" class="btn btn--sm btn--danger"
                                                onclick="openStatusModal(<?= $srv['id'] ?>, 'deactivate', '<?= htmlspecialchars($srv['name']) ?>')">
                                                Deactivate
                                            </a>
                                        <?php else: ?>
                                            <a href="javascript:void(0);" class="btn btn--sm btn--success"
                                                onclick="openStatusModal(<?= $srv['id'] ?>, 'activate', '<?= htmlspecialchars($srv['name']) ?>')">
                                                Activate
                                            </a>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($srv['scheduled_action']) && !empty($srv['scheduled_effective_at'])): ?>
                                        <?php
                                        $dt = new DateTime($srv['scheduled_effective_at']);
                                        $when = $dt->format('F j, Y g:i A'); // [web:94][web:97]
                                        $label = ($srv['scheduled_action'] === 'deactivate') ? 'Deactivation' : 'Activation';
                                        ?>
                                        <div class="service-schedule-info">
                                            <small>
                                                <?= $label ?> scheduled on <strong><?= htmlspecialchars($when) ?></strong>
                                            </small>
                                        </div>
                                        <div class="action-btn-group" style="margin-top: 4px;">
                                            <a href="javascript:void(0);" class="btn btn--sm btn--outline"
                                                onclick="openCancelScheduleModal(<?= $srv['id'] ?>, '<?= htmlspecialchars($srv['name']) ?>')">
                                                Cancel schedule
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($page_count > 1): ?>
                    <nav class="pagination-bar">
                        <?php for ($p = 1; $p <= $page_count; $p++): ?>
                            <a href="?category=<?= urlencode($category_filter) ?>&search=<?= urlencode($search_query) ?>&page=<?= $p ?>"
                                class="btn btn--sm<?= $page == $p ? ' btn--primary active' : '' ?>">
                                <?= $p ?>
                            </a>
                        <?php endfor; ?>
                    </nav>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <p>No services added yet.</p>
                    <a href="services-add.php" class="btn btn--primary">+ Add New Service</a>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- Service Status (Activate/Deactivate) Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content status-modal">
            <div class="modal-header">
                <h2 id="statusModalTitle">Are you sure?</h2>
            </div>
            <div class="modal-body">
                <p id="statusModalMessage">
                    This will update the visibility status of the service.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn--primary" id="confirmStatusBtn">Yes, continue</button>
                <button class="btn btn--outline" type="button" onclick="closeStatusModal()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Service Delete Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content delete-modal">
            <div class="modal-header">
                <h2>Delete Service?</h2>
            </div>
            <div class="modal-body">
                <p id="deleteModalMessage">
                    This action cannot be undone.<br>
                    Are you sure you want to delete this service?
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn--primary" id="confirmDeleteBtn">Yes, delete</button>
                <button class="btn btn--outline" type="button" onclick="closeDeleteModal()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Service Schedule Cancel Modal -->
    <div id="cancelScheduleModal" class="modal">
        <div class="modal-content status-modal">
            <div class="modal-header">
                <h2>Cancel Scheduled Change?</h2>
            </div>
            <div class="modal-body">
                <p id="cancelScheduleModalMessage">
                    Are you sure you want to cancel the scheduled activation/deactivation for this service?
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn--primary" id="confirmCancelScheduleBtn">Yes, cancel it</button>
                <button class="btn btn--outline" type="button" onclick="closeCancelScheduleModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        let statusActionId = null,
            statusActionType = '',
            statusActionName = '',
            deleteActionId = null,
            deleteActionName = '',
            cancelScheduleId = null,
            cancelScheduleName = '';

        function openStatusModal(id, action, name) {
            statusActionId = id;
            statusActionType = action;
            statusActionName = name;
            document.getElementById('statusModalTitle').innerText =
                (action === 'deactivate' ? 'Deactivate Service?' : 'Activate Service?');
            document.getElementById('statusModalMessage').innerHTML =
                (action === 'deactivate' ?
                    `Are you sure you want to <b>deactivate</b> "<b>${name}</b>"? An email will be sent to all users and this service will be deactivated in <b>3 days</b>.` :
                    `Are you sure you want to <b>activate</b> "<b>${name}</b>"? An email will be sent to all users and this service will be activated in <b>3 days</b>.`);
            document.getElementById('statusModal').style.display = 'flex';
        }

        function closeStatusModal() {
            document.getElementById('statusModal').style.display = 'none';
        }
        document.getElementById('confirmStatusBtn').onclick = function() {
            if (statusActionId && statusActionType) {
                window.location.href = `services-toggle.php?id=${statusActionId}&action=${statusActionType}`;
            }
        };

        function openDeleteModal(id, name) {
            deleteActionId = id;
            deleteActionName = name;
            document.getElementById('deleteModalMessage').innerHTML =
                `This action cannot be undone.<br>Are you sure you want to delete <b>${name}</b>?`;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        document.getElementById('confirmDeleteBtn').onclick = function() {
            if (deleteActionId) {
                window.location.href = `services-delete.php?id=${deleteActionId}`;
            }
        };

        function openCancelScheduleModal(id, name) {
            cancelScheduleId = id;
            cancelScheduleName = name;
            document.getElementById('cancelScheduleModalMessage').innerHTML =
                `Are you sure you want to cancel the scheduled status change for <b>${name}</b>?`;
            document.getElementById('cancelScheduleModal').style.display = 'flex';
        }

        function closeCancelScheduleModal() {
            document.getElementById('cancelScheduleModal').style.display = 'none';
        }
        document.getElementById('confirmCancelScheduleBtn').onclick = function() {
            if (cancelScheduleId) {
                window.location.href = `services-toggle.php?id=${cancelScheduleId}&action=cancel_schedule`;
            }
        };

        window.onclick = function(event) {
            if (event.target === document.getElementById('statusModal')) closeStatusModal();
            if (event.target === document.getElementById('deleteModal')) closeDeleteModal();
            if (event.target === document.getElementById('cancelScheduleModal')) closeCancelScheduleModal();
        };
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeStatusModal();
                closeDeleteModal();
                closeCancelScheduleModal();
            }
        });
    </script>

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
    </script>
</body>

</html>