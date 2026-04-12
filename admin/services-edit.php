<?php
session_start();
require_once '../connection/dbconn.php';
require_once '../includes/auth.php';
require_once '../includes/activity_logger.php';

requireAdmin();
$error = "";
$success = "";

$id = intval($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM services WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

$service = $stmt->get_result()->fetch_assoc();
if (!$service) {
    die("Service not found.");
}
$originalService = $service;


$requirements = [];
$reqStmt = $conn->prepare("
    SELECT id, requirement_text, requires_id_type, sort_order
    FROM service_requirements
    WHERE service_id = ?
    ORDER BY sort_order ASC, id ASC
");
$reqStmt->bind_param("i", $id);
$reqStmt->execute();
$reqRes = $reqStmt->get_result();

while ($row = $reqRes->fetch_assoc()) {
    $requirements[] = $row;
}
$reqStmt->close();

$has_explicit = isset($explicit_prices[$service['slug']]);
$proc_multipliers = [
    'standard' => 1.00,
    'priority' => 1.15,
    'express'  => 1.30,
    'rush'     => 1.50,
    'same_day' => 1.70
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name              = trim($_POST['name']);
    $slug              = trim($_POST['slug']);
    $category          = trim($_POST['category']);
    $price             = isset($_POST['price']) ? floatval($_POST['price']) : 0.00;
    $short_description = trim($_POST['short_description']);
    $long_description  = $_POST['long_description'];
    $is_active         = isset($_POST['is_active']) ? 1 : 0;
    $image_path        = $service['image'];

    // Processing prices
    $standard_price = isset($_POST['standard_price']) ? floatval($_POST['standard_price']) : $price;
    $priority_price = isset($_POST['priority_price']) ? floatval($_POST['priority_price']) : ($price * 1.15);
    $express_price  = isset($_POST['express_price']) ? floatval($_POST['express_price']) : ($price * 1.30);
    $rush_price     = isset($_POST['rush_price']) ? floatval($_POST['rush_price']) : ($price * 1.50);
    $same_day_price = isset($_POST['same_day_price']) ? floatval($_POST['same_day_price']) : ($price * 1.70);

    // Processing statuses
    $standard_status = isset($_POST['standard_status']) ? 1 : 0;
    $priority_status = isset($_POST['priority_status']) ? 1 : 0;
    $express_status  = isset($_POST['express_status']) ? 1 : 0;
    $rush_status     = isset($_POST['rush_status']) ? 1 : 0;
    $same_day_status = isset($_POST['same_day_status']) ? 1 : 0;

    if (empty($name) || empty($slug) || empty($category)) {
        $error = "Service name, slug, and category are required.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM services WHERE slug = ? AND id != ?");
        $stmt->bind_param("si", $slug, $id);
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {
            $error = "Slug must be unique.";
        }
    }

    if (!$error && isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
        $target_dir = "../uploads/services/";

        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'svg'])) {
            $new_filename = uniqid("service_", true) . "." . $ext;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_dir . $new_filename)) {
                $image_path = "uploads/services/" . $new_filename;
            } else {
                $error = "Failed to upload image.";
            }
        } else {
            $error = "Invalid image type. Allowed: JPG, PNG, SVG.";
        }
    }

    if (!$error) {
        $stmt = $conn->prepare("
            UPDATE services SET
                name=?,
                slug=?,
                category=?,
                price=?,
                standard_price=?,
                priority_price=?,
                express_price=?,
                rush_price=?,
                same_day_price=?,
                standard_status=?,
                priority_status=?,
                express_status=?,
                rush_status=?,
                same_day_status=?,
                short_description=?,
                long_description=?,
                image=?,
                is_active=?
            WHERE id=?
        ");

        $stmt->bind_param(
            "sssddddddiiiiisssii",
            $name,
            $slug,
            $category,
            $price,
            $standard_price,
            $priority_price,
            $express_price,
            $rush_price,
            $same_day_price,
            $standard_status,
            $priority_status,
            $express_status,
            $rush_status,
            $same_day_status,
            $short_description,
            $long_description,
            $image_path,
            $is_active,
            $id
        );

        if ($stmt->execute()) {
            $changes = [];

            if ($originalService['name'] !== $name) {
                $changes[] = "name: '{$originalService['name']}' → '{$name}'";
            }

            if ($originalService['slug'] !== $slug) {
                $changes[] = "slug: '{$originalService['slug']}' → '{$slug}'";
            }

            if ($originalService['category'] !== $category) {
                $changes[] = "category changed";
            }

            if ((float)$originalService['price'] !== (float)$price) {
                $changes[] = "price: {$originalService['price']} → {$price}";
            }

            if ($originalService['short_description'] !== $short_description) {
                $changes[] = "short_description changed";
            }

            if ($originalService['long_description'] !== $long_description) {
                $changes[] = "long_description changed";
            }

            if ($originalService['image'] !== $image_path) {
                $changes[] = "image updated";
            }

            if ((int)$originalService['is_active'] !== (int)$is_active) {
                $changes[] = "status: " .
                    ($originalService['is_active'] ? 'active' : 'inactive') .
                    " → " .
                    ($is_active ? 'active' : 'inactive');
            }

            if (!empty($changes)) {
                $actorType = (
                    isset($_SESSION['account_type']) &&
                    $_SESSION['account_type'] === 'employee'
                ) ? 'employee' : 'admin';

                logActivity(
                    $_SESSION['user_id'],
                    $actorType,
                    'service_updated',
                    "Service {$originalService['name']} (ID {$id}) updated: " . implode('; ', $changes)
                );
            }

            $deleteOld = $conn->prepare("DELETE FROM service_requirements WHERE service_id = ?");
            $deleteOld->bind_param("i", $id);
            $deleteOld->execute();
            $deleteOld->close();

            if (!empty($_POST['requirements'])) {
                $insertReq = $conn->prepare("
        INSERT INTO service_requirements
        (service_id, requirement_text, requires_id_type, sort_order)
        VALUES (?, ?, ?, ?)
    ");

                foreach ($_POST['requirements'] as $index => $reqText) {
                    $reqText = trim($reqText);

                    if ($reqText === '') continue;

                    $requiresId = isset($_POST['requirements_id'][$index]) ? 1 : 0;
                    $sortOrder  = $index + 1;

                    $insertReq->bind_param(
                        "isii",
                        $id,
                        $reqText,
                        $requiresId,
                        $sortOrder
                    );
                    $insertReq->execute();
                }

                $insertReq->close();
            }

            header("Location: services-admin.php?edited=1");
            exit;
        } else {
            $error = "Failed to update service.";
        }
    }
}

$stmt = $conn->prepare("SELECT COUNT(*) as pending_count FROM inquiries WHERE status = 'pending'");
$stmt->execute();
$pending_inquiries = $stmt->get_result()->fetch_assoc()['pending_count'];
$stmt = $conn->prepare("SELECT COUNT(*) as pending_bills FROM billings WHERE status = 'unpaid'");
$stmt->execute();
$pending_bills = $stmt->get_result()->fetch_assoc()['pending_bills'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Service - JRN Admin</title>
    <link rel="stylesheet" href="assets/css/index-admin.css">
    <link rel="stylesheet" href="assets/css/services-edit.css">
    <link rel="stylesheet" href="assets/css/logout-modal.css">
    <script src="https://cdn.tiny.cloud/1/ozuk7q3prsvwl4thn94ccpkd86t8hi1v8cfoxk2n6e1kvuj1/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        tinymce.init({
            selector: '#long_description',
            height: 300,
            menubar: false,
            plugins: 'link image code lists',
            toolbar: 'undo redo | formatselect | bold italic underline | alignleft aligncenter alignright | bullist numlist | link | code'
        });
    </script>
    <style>
        .form-control {
            resize: vertical;
        }

        .pricing-preview-panel {
            background: linear-gradient(135deg, #0F3A40 0%, #1C4F50 100%);
            border-radius: 14px;
            padding: 1.25rem 1.4rem;
            margin-top: 0.75rem;
        }

        .pricing-preview-panel h4 {
            color: #D9FF00;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin: 0 0 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .pricing-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            margin-bottom: 0.4rem;
            font-size: 0.83rem;
            background: rgba(255, 255, 255, 0.07);
        }

        .pricing-row .pr-label {
            color: rgba(255, 255, 255, 0.8);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .pricing-row .pr-badge {
            font-size: 0.62rem;
            font-weight: 700;
            padding: 0.15rem 0.5rem;
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .pricing-row .pr-amount {
            color: #D9FF00;
            font-weight: 800;
            font-size: 0.9rem;
        }

        .pricing-row .pr-fixed {
            color: #86efac;
            font-weight: 800;
            font-size: 0.85rem;
        }

        .pricing-row .pr-na {
            color: rgba(255, 255, 255, 0.3);
            font-size: 0.75rem;
            font-style: italic;
        }

        .fixed-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            background: rgba(134, 239, 172, 0.15);
            border: 1px solid rgba(134, 239, 172, 0.3);
            color: #86efac;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 0.1rem 0.5rem;
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-left: auto;
        }

        .pricing-note {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.72rem;
            margin: 0.75rem 0 0;
        }

        .form-hint {
            display: block;
            font-size: 0.78rem;
            color: var(--text-secondary, #6b7280);
            margin-top: 0.3rem;
        }

        .current-price-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 0.85rem;
            background: #ecfdf5;
            border: 1px solid #d1fae5;
            border-radius: 8px;
            margin-bottom: 0.75rem;
            font-size: 0.82rem;
            color: #166534;
        }

        /* Processing types grid */
        .proc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 0.6rem;
            margin-top: 0.6rem;
        }

        .proc-item {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.65rem 0.9rem;
            border: 1.5px solid var(--border-color, #e5e7eb);
            border-radius: 8px;
            cursor: pointer;
            transition: border-color 0.15s, background 0.15s;
        }

        .proc-item:has(input:checked) {
            border-color: var(--primary, #0F3A40);
            background: rgba(15, 58, 64, 0.06);
        }

        .proc-item input[type="checkbox"] {
            accent-color: var(--primary, #0F3A40);
        }

        .proc-item span {
            font-size: 0.85rem;
            font-weight: 500;
        }

        /* Requirements */
        .req-row {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 10px;
            align-items: center;
        }

        .form-control {
            resize: vertical;
        }
    </style>
    <style>
        :root {
            color-scheme: light only !important;
            --primary: #0F3A40;
            --primary-light: #1a5560;
            --primary-xlight: #e8f4f5;
            --accent: #2fb8c4;
            --accent-soft: #e0f7fa;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --bg-page: #f8fafc;
            --bg-card: #ffffff;
            --bg-secondary: #f1f5f9;
            --border-color: #e2e8f0;
            --border-strong: #cbd5e1;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.08), 0 2px 6px rgba(0, 0, 0, 0.04);
            --shadow-lg: 0 12px 40px rgba(0, 0, 0, 0.12), 0 4px 14px rgba(0, 0, 0, 0.06);
            --shadow-xl: 0 24px 64px rgba(0, 0, 0, 0.14), 0 8px 24px rgba(0, 0, 0, 0.08);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            background: var(--bg-page) !important;
            color: var(--text-primary) !important;
            font-family: 'DM Sans', -apple-system, sans-serif !important;
        }

        /* ── Processing type pills ── */
        .proc-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            padding: 0.25rem 0.7rem;
            border-radius: 999px;
            white-space: nowrap;
            font-family: 'DM Sans', sans-serif;
        }

        .proc-standard {
            background: #dbeafe;
            color: #1e40af;
        }

        .proc-priority {
            background: #ede9fe;
            color: #5b21b6;
        }

        .proc-express {
            background: #fef3c7;
            color: #92400e;
        }

        .proc-rush {
            background: #fee2e2;
            color: #991b1b;
        }

        .proc-same_day {
            background: #fce7f3;
            color: #9d174d;
        }

        /* ── Secondary filter bar ── */
        .proc-filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            align-items: center;
            padding-bottom: 0.85rem;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 0.85rem;
        }

        .proc-filter-bar .pf-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            margin-right: 0.25rem;
        }

        .proc-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            height: 32px;
            padding: 0 0.9rem;
            border: 1.5px solid var(--border-color);
            border-radius: 999px;
            background: #fff;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.18s ease;
            box-shadow: var(--shadow-sm);
        }

        .proc-btn:hover {
            border-color: var(--primary);
            background: var(--primary-xlight);
            color: var(--primary);
        }

        .proc-btn.active {
            background: var(--primary);
            color: #fff;
            border-color: transparent;
            box-shadow: 0 2px 8px rgba(15, 58, 64, 0.25);
        }

        .proc-btn .count {
            font-size: 0.68rem;
            opacity: 0.75;
        }

        /* ── Search bar ── */
        .search-bar {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-bar input {
            height: 38px;
            padding: 0 1rem;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            min-width: 260px;
            background: #fff;
            color: var(--text-primary);
            transition: border-color 0.18s;
            font-family: 'DM Sans', sans-serif;
        }

        .search-bar input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(47, 184, 196, 0.12);
        }

        .search-bar input::placeholder {
            color: var(--text-muted);
        }

        .search-bar button {
            height: 38px;
            padding: 0 1.1rem;
            font-size: 0.83rem;
            white-space: nowrap;
        }

        /* ── Alert banners ── */
        .alert {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.9rem 1.25rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.25rem;
            font-size: 0.88rem;
            font-weight: 500;
        }

        .alert::before {
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .alert--success {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .alert--success::before {
            content: '✓';
        }

        .alert--error {
            background: #fff1f2;
            color: #be123c;
            border: 1px solid #fecdd3;
        }

        .alert--error::before {
            content: '✕';
        }

        /* ── Status badges ── */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            padding: 0.3rem 0.75rem;
            border-radius: 999px;
        }

        .status-badge::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
            flex-shrink: 0;
        }

        .status-pending {
            background: #fef9ee;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .status-in_review {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        .status-completed {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .status-rejected {
            background: #fff1f2;
            color: #be123c;
            border: 1px solid #fecdd3;
        }

        /* ── Table enhancements ── */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .data-table thead th {
            background: var(--bg-secondary);
            color: var(--text-secondary);
            font-size: 0.71rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            padding: 0.75rem 1rem;
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }

        .data-table thead th:first-child {
            border-radius: var(--radius-sm) 0 0 0;
        }

        .data-table thead th:last-child {
            border-radius: 0 var(--radius-sm) 0 0;
        }

        .data-table tbody tr {
            transition: background 0.15s;
        }

        .data-table tbody tr:hover {
            background: #f8fafc;
        }

        .data-table tbody td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.84rem;
            color: var(--text-primary);
            vertical-align: middle;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        code.inq-num {
            font-family: 'DM Mono', monospace;
            font-size: 0.75rem;
            color: var(--primary);
            font-weight: 500;
            background: var(--primary-xlight);
            padding: 0.2rem 0.5rem;
            border-radius: 5px;
        }

        code.acc-num {
            font-family: 'DM Mono', monospace;
            font-size: 0.72rem;
            color: var(--text-secondary);
        }

        .client-cell strong {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .client-cell small {
            display: block;
            color: var(--text-secondary);
            font-size: 0.73rem;
            margin-top: 0.1rem;
        }

        .fee-cell strong {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--primary);
        }

        .fee-cell small {
            display: block;
            color: var(--text-muted);
            text-decoration: line-through;
            font-size: 0.73rem;
            margin-top: 0.1rem;
        }

        .doc-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--primary);
            background: var(--primary-xlight);
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
        }

        .btn-view {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            height: 32px;
            padding: 0 0.85rem;
            font-size: 0.78rem;
            font-weight: 600;
            border-radius: var(--radius-sm);
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s;
            border: 1.5px solid var(--border-color);
            background: #fff;
            color: var(--text-primary);
        }

        .btn-view:hover {
            background: var(--bg-secondary);
            border-color: var(--border-strong);
        }

        .btn-update {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            height: 32px;
            padding: 0 0.85rem;
            font-size: 0.78rem;
            font-weight: 600;
            border-radius: var(--radius-sm);
            cursor: pointer;
            border: none;
            background: var(--primary);
            color: #fff;
            transition: all 0.15s;
        }

        .btn-update:hover {
            background: var(--primary-light);
            box-shadow: 0 2px 8px rgba(15, 58, 64, 0.25);
        }

        .btn-closed {
            display: inline-flex;
            align-items: center;
            height: 32px;
            padding: 0 0.85rem;
            font-size: 0.78rem;
            font-weight: 600;
            border-radius: var(--radius-sm);
            background: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
            border: 1.5px solid #e2e8f0;
        }

        /* ═══════════════════════════════════════════
           MODAL SYSTEM — Completely Redesigned
        ═══════════════════════════════════════════ */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-overlay.active .modal-panel {
            transform: translateY(0) scale(1);
            opacity: 1;
        }

        .modal-panel {
            background: #fff;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            width: 100%;
            max-height: 92vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transform: translateY(24px) scale(0.97);
            opacity: 0;
            transition: transform 0.3s cubic-bezier(0.34, 1.2, 0.64, 1), opacity 0.25s ease;
        }

        .modal-panel--view {
            max-width: 800px;
        }

        .modal-panel--update {
            max-width: 540px;
        }

        /* Modal header */
        .modal-head {
            padding: 1.5rem 1.75rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            flex-shrink: 0;
        }

        .modal-head-info {}

        .modal-head-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 0.25rem;
            line-height: 1.3;
        }

        .modal-head-sub {
            font-size: 0.8rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-close-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            background: var(--bg-secondary);
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
            transition: all 0.15s;
            line-height: 1;
        }

        .modal-close-btn:hover {
            background: #fee2e2;
            color: #dc2626;
        }

        /* Modal tabs */
        .modal-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            padding: 0 1.75rem;
            flex-shrink: 0;
        }

        .modal-tab {
            padding: 0.9rem 1.1rem 0.85rem;
            font-size: 0.84rem;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 2.5px solid transparent;
            margin-bottom: -1px;
            color: var(--text-secondary);
            transition: color 0.15s, border-color 0.15s;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .modal-tab:hover {
            color: var(--text-primary);
        }

        .modal-tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .tab-count {
            font-size: 0.68rem;
            background: var(--bg-secondary);
            color: var(--text-secondary);
            border-radius: 999px;
            padding: 0.1rem 0.5rem;
            font-weight: 700;
        }

        .modal-tab.active .tab-count {
            background: var(--primary-xlight);
            color: var(--primary);
        }

        /* Modal body */
        .modal-body {
            padding: 1.5rem 1.75rem;
            overflow-y: auto;
            flex: 1;
        }

        .modal-panel-content {
            display: none;
        }

        .modal-panel-content.active {
            display: block;
        }

        /* Modal footer */
        .modal-foot {
            padding: 1.1rem 1.75rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 0.65rem;
            justify-content: flex-end;
            flex-shrink: 0;
            background: var(--bg-secondary);
        }

        .btn-modal-primary {
            height: 40px;
            padding: 0 1.4rem;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-family: 'DM Sans', sans-serif;
        }

        .btn-modal-primary:hover {
            background: var(--primary-light);
            box-shadow: 0 3px 12px rgba(15, 58, 64, 0.3);
        }

        .btn-modal-primary:disabled {
            opacity: 0.45;
            cursor: not-allowed;
            box-shadow: none;
        }

        .btn-modal-outline {
            height: 40px;
            padding: 0 1.2rem;
            background: #fff;
            color: var(--text-primary);
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
            font-family: 'DM Sans', sans-serif;
        }

        .btn-modal-outline:hover {
            border-color: var(--border-strong);
            background: var(--bg-secondary);
        }

        /* ── Detail grid in view modal ── */
        .detail-section-title {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-muted);
            margin: 0 0 0.85rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem 1.5rem;
            margin-bottom: 1.5rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .detail-item.full {
            grid-column: 1 / -1;
        }

        .di-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--text-muted);
        }

        .di-value {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-primary);
            line-height: 1.4;
        }

        .price-main {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
        }

        .price-was {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-decoration: line-through;
            margin-top: 0.25rem;
        }

        .client-avatar {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .client-card {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
        }

        .client-card-info {}

        .client-card-name {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .client-card-meta {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 0.25rem;
        }

        .client-card-meta span {
            font-size: 0.78rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        /* Rejection box */
        .rejection-card {
            display: flex;
            gap: 0.85rem;
            padding: 1rem 1.1rem;
            background: #fff1f2;
            border: 1px solid #fecdd3;
            border-radius: var(--radius-md);
            margin-top: 0.5rem;
        }

        .rejection-icon {
            width: 32px;
            height: 32px;
            background: #fee2e2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: #dc2626;
            font-size: 0.9rem;
        }

        .rejection-card p {
            margin: 0;
            font-size: 0.88rem;
            color: #9f1239;
            font-weight: 500;
        }

        .rejection-card small {
            display: block;
            margin-top: 0.35rem;
            font-size: 0.72rem;
            color: #be123c;
            opacity: 0.8;
        }

        /* Notes card */
        .notes-card {
            padding: 0.9rem 1rem;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            color: var(--text-primary);
            white-space: pre-wrap;
            line-height: 1.6;
        }

        /* Document cards */
        .doc-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.85rem 1rem;
            background: #fff;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            margin-bottom: 0.6rem;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .doc-card:hover {
            border-color: var(--accent);
            box-shadow: 0 2px 12px rgba(47, 184, 196, 0.1);
        }

        .doc-card-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .doc-icon {
            width: 38px;
            height: 38px;
            background: var(--primary-xlight);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            flex-shrink: 0;
        }

        .doc-name {
            font-family: 'DM Mono', monospace;
            font-size: 0.8rem;
            color: var(--primary);
            font-weight: 500;
        }

        .doc-meta {
            font-size: 0.72rem;
            color: var(--text-secondary);
            margin-top: 0.15rem;
        }

        .doc-download {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            height: 32px;
            padding: 0 0.85rem;
            font-size: 0.77rem;
            font-weight: 600;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-sm);
            color: var(--primary);
            background: var(--primary-xlight);
            text-decoration: none;
            transition: all 0.15s;
        }

        .doc-download:hover {
            background: var(--primary);
            color: #fff;
            border-color: transparent;
        }

        .empty-docs {
            text-align: center;
            padding: 2.5rem 1rem;
            color: var(--text-muted);
        }

        .empty-docs svg {
            opacity: 0.35;
            margin-bottom: 0.75rem;
        }

        .empty-docs p {
            font-size: 0.88rem;
            margin: 0;
        }

        /* ── Update status modal ── */
        .status-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.65rem;
            margin-top: 0.75rem;
        }

        .status-card {
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 0.9rem 1rem;
            cursor: pointer;
            transition: all 0.18s;
            position: relative;
            background: #fff;
        }

        .status-card:hover {
            border-color: var(--accent);
            box-shadow: 0 2px 12px rgba(47, 184, 196, 0.1);
        }

        .status-card.selected {
            border-color: var(--primary);
            background: var(--primary-xlight);
            box-shadow: 0 0 0 1px var(--primary);
        }

        .status-card input[type="radio"] {
            display: none;
        }

        .status-card-check {
            position: absolute;
            top: 0.6rem;
            right: 0.6rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 2px solid var(--border-color);
            background: #fff;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .status-card.selected .status-card-check {
            background: var(--primary);
            border-color: var(--primary);
        }

        .status-card.selected .status-card-check::after {
            content: '';
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: #fff;
        }

        .sc-icon {
            font-size: 1.3rem;
            margin-bottom: 0.35rem;
        }

        .sc-label {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-primary);
            display: block;
        }

        .sc-desc {
            font-size: 0.73rem;
            color: var(--text-secondary);
            margin-top: 0.15rem;
            display: block;
        }

        .status-card[data-value="rejected"] .sc-label {
            color: #be123c;
        }

        /* Rejection reason textarea */
        .rejection-group {
            margin-top: 1.1rem;
            padding: 1rem;
            background: #fff1f2;
            border: 1px solid #fecdd3;
            border-radius: var(--radius-md);
        }

        .rejection-group label {
            font-size: 0.82rem;
            font-weight: 700;
            color: #9f1239;
            display: flex;
            align-items: center;
            gap: 0.35rem;
            margin-bottom: 0.6rem;
        }

        .rejection-group textarea {
            width: 100%;
            border: 1.5px solid #fecdd3;
            border-radius: var(--radius-sm);
            padding: 0.75rem;
            font-size: 0.85rem;
            resize: vertical;
            min-height: 100px;
            background: #fff;
            color: var(--text-primary);
            font-family: 'DM Sans', sans-serif;
            transition: border-color 0.15s;
        }

        .rejection-group textarea:focus {
            outline: none;
            border-color: #f87171;
            box-shadow: 0 0 0 3px rgba(248, 113, 113, 0.15);
        }

        .rejection-warning {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.73rem;
            color: #9f1239;
            margin-top: 0.5rem;
        }

        /* Current status display */
        .current-status-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.85rem 1rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            border: 1px solid var(--border-color);
        }

        .csb-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.07em;
        }

        /* Empty state */
        .empty-state {
            padding: 4rem 2rem;
            text-align: center;
        }

        .empty-state svg {
            opacity: 0.2;
            margin-bottom: 1rem;
        }

        .empty-state p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin: 0 0 1rem;
        }

        /* Filter card wrapper */
        .filter-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1rem 1.25rem;
            margin-bottom: 1.1rem;
            box-shadow: var(--shadow-sm);
        }

        /* Divider */
        .modal-divider {
            height: 1px;
            background: var(--border-color);
            margin: 1.25rem 0;
        }
    </style>
</head>

<body>

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo"><img src="../assets/img/logo.jpg" alt="Logo" class="logo-small">
                <h2>JRN Admin</h2>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="index-admin.php" class="nav-item"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>Dashboard</a>
            <a href="inquiries-admin.php" class="nav-item"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" overflow="visible">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>Inquiries<?php if (isset($pending_inquiries) && $pending_inquiries > 0): ?><span class="badge"><?php echo $pending_inquiries; ?></span><?php endif; ?></a>
            <?php if (isAdmin()): ?>
                <a href="billing-admin.php" class="nav-item"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="6" rx="1" />
                        <rect x="3" y="12" width="18" height="8" rx="1" />
                        <line x1="7" y1="16" x2="11" y2="16" />
                        <line x1="7" y1="19" x2="15" y2="19" />
                    </svg>Billing<?php if (isset($pending_bills) && $pending_bills > 0): ?><span class="badge"><?php echo $pending_bills; ?></span><?php endif; ?></a>
            <?php endif; ?>
            <a href="users-admin.php" class="nav-item"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" overflow="visible">
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                </svg>Users</a>
            <?php if (isAdmin()): ?>
                <a href="employees-admin.php" class="nav-item"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>Employees</a>
                <a href="activity-logs.php" class="nav-item"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>Activity Logs</a>
                <a href="services-admin.php" class="nav-item active"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="6" width="18" height="12" rx="2" />
                        <path d="M3 10h18" />
                    </svg>Manage Services</a>
                <a href="payroll-reports-admin.php" class="nav-item"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" />
                        <line x1="8" y1="21" x2="16" y2="21" />
                        <line x1="12" y1="17" x2="12" y2="21" />
                        <path d="M6 8h.01M10 8h4M6 12h12" />
                    </svg>Payroll Reports</a>
            <?php endif; ?>
            <div style="margin-top:auto;padding-top:1rem;border-top:1px solid var(--border-color);">
                <a href="admin-account.php" class="nav-item"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M12 1v6m0 6v6m6-11h-6m-6 0H1m18.4-3.6l-4.2 4.2m-8.4 0l-4.2-4.2M18.4 18.4l-4.2-4.2m-8.4 0l-4.2 4.2"></path>
                    </svg>My Account</a>
            </div>
        </nav>
        <div class="sidebar-footer">
            <a href="#" class="nav-item logout" id="logout-btn"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>Logout</a>
        </div>
    </aside>

    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="logout-modal-overlay" id="logout-modal-overlay">
            <div class="logout-modal">
                <h2>Confirm Logout</h2>
                <p>Are you sure you want to log out?</p>
                <div class="logout-modal-buttons"><button class="logout-btn-confirm" id="logout-confirm">Yes</button><button class="logout-btn-cancel" id="logout-cancel">Cancel</button></div>
            </div>
        </div>
    <?php endif; ?>
    <script src="assets/js/logout-modal.js"></script>

    <main class="main-content">
        <header class="admin-header">
            <div class="admin-header-left">
                <h1>Edit Service</h1>
                <p class="header-subtitle">Modify service details — price changes reflect immediately in inquire.php for non-fixed services.</p>
            </div>
            <div class="admin-header-right">
                <a href="services-admin.php" class="btn btn--outline">← Back to Services</a>
            </div>
        </header>

        <section class="card">
            <div class="card-header">
                <h2>Edit: <?= htmlspecialchars($service['name']) ?></h2>
            </div>
            <div class="card-body">
                <?php if ($error): ?><div class="alert alert--error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert--success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

                <form method="POST" class="service-form" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="name">Service Name <span style="color:#EF4444;">*</span></label>
                        <input class="form-control" type="text" id="name" name="name" required maxlength="150" value="<?= htmlspecialchars($service['name']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="slug">Unique Slug <span style="color:#EF4444;">*</span></label>
                        <input class="form-control" type="text" id="slug" name="slug" required maxlength="150" pattern="^[a-z0-9-]+$" value="<?= htmlspecialchars($service['slug']) ?>">
                        <span class="form-hint">This slug is used in inquire.php to match prices. Changing it will affect pricing lookups.</span>
                    </div>
                    <div class="form-group">
                        <label for="category">Category <span style="color:#EF4444;">*</span></label>
                        <input class="form-control" type="text" id="category" name="category" required value="<?= htmlspecialchars($service['category']) ?>">
                    </div>

                    <!-- ── Price ────────────────────────────────────────────────── -->
                    <div class="form-group">
                        <label for="price">
                            Standard Processing Price (₱)
                            <?php if ($has_explicit): ?>
                                <span style="background:#fef3c7;color:#92400e;font-size:0.72rem;font-weight:700;padding:0.15rem 0.5rem;border-radius:999px;margin-left:0.4rem;">
                                    ⚡ This service has fixed prices in inquire.php — updating this won't change displayed processing prices
                                </span>
                            <?php endif; ?>
                        </label>

                        <?php if ($has_explicit): ?>
                            <div class="current-price-info">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                                Current DB price: <strong>₱<?= number_format($service['price'], 2) ?></strong> — Processing prices for this service are fixed in code (inquire.php). See preview below.
                            </div>
                        <?php else: ?>
                            <div class="current-price-info">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                                Current price: <strong>₱<?= number_format($service['price'], 2) ?></strong> — Changing this will update all processing type prices in inquire.php via multipliers.
                            </div>
                        <?php endif; ?>

                        <input class="form-control" type="number" id="price" name="price" step="0.01" min="0"
                            value="<?= htmlspecialchars(number_format($service['price'], 2, '.', '')) ?>"
                            oninput="updatePricingPreview()">

                        <!-- Live / Fixed pricing preview -->
                        <div class="pricing-preview-panel">
                            <h4>Processing Type Prices
                                <?php if ($has_explicit): ?><span style="background:rgba(134,239,172,0.2);color:#86efac;font-size:0.65rem;padding:0.15rem 0.6rem;border-radius:999px;font-weight:700;">FIXED IN CODE</span><?php endif; ?>
                            </h4>

                            <?php
                            $proc_def = [
                                'standard' => ['label' => 'Standard Processing', 'badge_color' => '#2563eb', 'badge' => 'Standard'],
                                'priority' => ['label' => 'Priority Processing', 'badge_color' => '#7c3aed', 'badge' => 'Priority'],
                                'express'  => ['label' => 'Express Processing',  'badge_color' => '#d97706', 'badge' => 'Express'],
                                'rush'     => ['label' => 'Rush Processing',     'badge_color' => '#dc2626', 'badge' => 'Rush'],
                                'same_day' => ['label' => 'Same-Day Priority',   'badge_color' => '#991b1b', 'badge' => 'Same-Day'],
                            ];
                            ?>
                            <?php foreach ($proc_def as $pkey => $pdef): ?>
                                <div class="pricing-row">
                                    <span class="pr-label">
                                        <span class="pr-badge" style="background:<?= $pdef['badge_color'] ?>;color:#fff;"><?= $pdef['badge'] ?></span>
                                        <?= $pdef['label'] ?>
                                    </span>
                                    <?php if ($has_explicit): ?>
                                        <?php $fx = $explicit_prices[$service['slug']][$pkey] ?? null; ?>
                                        <?php if ($fx !== null): ?>
                                            <span class="pr-fixed">₱<?= number_format($fx, 2) ?></span>
                                        <?php else: ?>
                                            <span class="pr-na">Not Available</span>
                                        <?php endif; ?>
                                    <?php elseif ($pkey === 'same_day'): ?>
                                        <span class="pr-na">Disabled by default</span>
                                    <?php else: ?>
                                        <span class="pr-amount" id="pp-<?= $pkey ?>">₱<?= number_format($service['price'] * $proc_multipliers[$pkey], 2) ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>

                            <p class="pricing-note">
                                <?php if ($has_explicit): ?>
                                    ✅ This service uses hardcoded prices from the quotation document. To change them, edit the <code>$service_type_prices</code> array in inquire.php and process_inquiry.php.
                                <?php else: ?>
                                    ⚠ Processing type prices are calculated as: Standard × multiplier (Priority×1.15, Express×1.30, Rush×1.50). Update the base price above to change all tiers.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <!-- Processing Type Availability -->
                    <div class="form-group">
                        <label>Processing Types Availability</label>
                        <div class="proc-grid">
                            <label class="proc-item">
                                <input type="checkbox" name="standard_status" value="1"
                                    <?= !empty($_POST) && isset($_POST['standard_status']) ? 'checked' : '' ?>>
                                <span>Standard Processing</span>
                            </label>
                            <label class="proc-item">
                                <input type="checkbox" name="priority_status" value="1"
                                    <?= !empty($_POST) && isset($_POST['priority_status']) ? 'checked' : '' ?>>
                                <span>Priority Processing</span>
                            </label>
                            <label class="proc-item">
                                <input type="checkbox" name="express_status" value="1"
                                    <?= !empty($_POST) && isset($_POST['express_status']) ? 'checked' : '' ?>>
                                <span>Express Processing</span>
                            </label>
                            <label class="proc-item">
                                <input type="checkbox" name="rush_status" value="1"
                                    <?= !empty($_POST) && isset($_POST['rush_status']) ? 'checked' : '' ?>>
                                <span>Rush Processing</span>
                            </label>
                            <label class="proc-item">
                                <input type="checkbox" name="same_day_status" value="1"
                                    <?= !empty($_POST) && isset($_POST['same_day_status']) ? 'checked' : '' ?>>
                                <span>Same-Day Priority</span>
                            </label>
                        </div>
                        <span class="form-hint">Checked = Available &bull; Unchecked = Not Available</span>
                    </div>

                    <div class="form-group">
                        <label>Requirements</label>

                        <div id="requirements-wrapper" style="display:grid;gap:10px;margin-top:10px;">

                            <?php if (!empty($requirements)): ?>
                                <?php foreach ($requirements as $req): ?>
                                    <div class="req-row" style="display:grid;grid-template-columns:1fr auto auto;gap:10px;align-items:center;">

                                        <input
                                            type="text"
                                            name="requirements[]"
                                            class="form-control"
                                            value="<?= htmlspecialchars($req['requirement_text']) ?>"
                                            placeholder="Requirement text">

                                        <label style="white-space:nowrap;">
                                            <input
                                                type="checkbox"
                                                class="req-checkbox"
                                                <?= $req['requires_id_type'] ? 'checked' : '' ?>>
                                            Needs ID Type
                                        </label>

                                        <button type="button"
                                            onclick="removeRequirement(this)"
                                            class="btn btn--outline">
                                            Remove
                                        </button>

                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                        </div>

                        <button type="button"
                            onclick="addRequirement()"
                            class="btn btn--outline"
                            style="margin-top:10px;">
                            + Add Requirement
                        </button>

                        <span class="form-hint">
                            Add, remove, check, or uncheck requirements. Changes reflect in inquire.php after saving.
                        </span>
                    </div>

                    <div class="form-group">
                        <label for="short_description">Short Description</label>
                        <textarea class="form-control" id="short_description" name="short_description" maxlength="300"><?= htmlspecialchars($service['short_description']) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="long_description">Full Service Description</label>
                        <textarea id="long_description" name="long_description"><?= htmlspecialchars($service['long_description']) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="image">Hero/Image Upload</label>
                        <input class="form-control" type="file" id="image" name="image" accept="image/*">
                        <span class="form-hint">Recommended: 4K–8K resolution. Allowed: JPG, PNG, SVG.</span>
                        <?php if (!empty($service['image'])): ?>
                            <div style="margin-top:8px;">
                                <img src="../<?= htmlspecialchars($service['image']) ?>" alt="Current Image" style="max-width:180px;border-radius:8px;border:1px solid #e5e7eb;">
                                <span class="form-hint">Uploading a new image will replace the current one.</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" name="is_active" value="1" <?= $service['is_active'] ? 'checked' : '' ?>> Active/Visible</label>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn--primary">Save Changes</button>
                        <a href="services-admin.php" class="btn btn--outline" style="margin-left:1rem;">Cancel</a>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <script>
        const hasExplicit = <?php echo $has_explicit ? 'true' : 'false'; ?>;
        const multipliers = {
            standard: 1.00,
            priority: 1.15,
            express: 1.30,
            rush: 1.50
        };

        function updatePricingPreview() {
            if (hasExplicit) return; // fixed prices, don't override
            const base = parseFloat(document.getElementById('price').value) || 0;
            const fmt = v => '₱' + v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            Object.entries(multipliers).forEach(([key, mult]) => {
                const el = document.getElementById('pp-' + key);
                if (el) el.textContent = fmt(base * mult);
            });
        }


        function updateRequirementIndexes() {
            const rows = document.querySelectorAll('.req-row');

            rows.forEach((row, index) => {
                const checkbox = row.querySelector('.req-checkbox');

                checkbox.name = `requirements_id[${index}]`;
                checkbox.value = "1";
            });
        }

        function addRequirement() {
            const wrapper = document.getElementById('requirements-wrapper');

            const row = document.createElement('div');
            row.className = 'req-row';
            row.style =
                'display:grid;grid-template-columns:1fr auto auto;gap:10px;align-items:center;';

            row.innerHTML = `
        <input
            type="text"
            name="requirements[]"
            class="form-control"
            placeholder="Requirement text">

        <label style="white-space:nowrap;">
            <input type="checkbox" class="req-checkbox">
            Needs ID Type
        </label>

        <button type="button"
            onclick="removeRequirement(this)"
            class="btn btn--outline">
            Remove
        </button>
    `;

            wrapper.appendChild(row);
            updateRequirementIndexes();
        }

        function removeRequirement(btn) {
            btn.closest('.req-row').remove();
            updateRequirementIndexes();
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateRequirementIndexes();
        });
    </script>
</body>

</html>