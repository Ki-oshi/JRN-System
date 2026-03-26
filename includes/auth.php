<?php

/**
 * Authentication and Authorization Functions
 * Simplified: Only admin (from users table) and employee (from employees table)
 */

/**
 * Check if user is logged in
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

/**
 * Check if user is admin (from users table with role='admin')
 */
function isAdmin()
{
    return isLoggedIn() && $_SESSION['role'] === 'admin';
}

/**
 * Check if user is employee (from employees table)
 */
function isEmployee()
{
    return isLoggedIn() && $_SESSION['role'] === 'employee';
}

/**
 * Check if user is regular user
 */
function isUser()
{
    return isLoggedIn() && $_SESSION['role'] === 'user';
}

/**
 * Require login - redirect to login if not logged in
 */
function requireLogin()
{
    if (!isLoggedIn()) {
        header("Location: /SIAA/login.php");
        exit();
    }
}

/**
 * Require admin or employee access
 */
function requireAdmin()
{
    if (!isLoggedIn()) {
        header("Location: /SIAA/login.php");
        exit();
    }

    // Allow both admins and employees
    if (!isAdmin() && !isEmployee()) {
        header("Location: /SIAA/account_page.php");
        exit();
    }
}

/**
 * Require ONLY admin (for employee management)
 */
function requireAdminOnly()
{
    if (!isLoggedIn()) {
        header("Location: /SIAA/login.php");
        exit();
    }

    // Only admins from users table can access
    if (!isAdmin()) {
        header("Location: /SIAA/admin/index-admin.php");
        exit();
    }
}

/**
 * Require user role - redirect if admin tries to access
 */
function requireUser()
{
    if (!isLoggedIn()) {
        header("Location: /SIAA/login.php");
        exit();
    }

    if (isAdmin() || isEmployee()) {
        header("Location: /SIAA/admin/index-admin.php");
        exit();
    }
}

/**
 * Block logged-in users from accessing public pages
 */
function blockIfLoggedIn()
{
    if (!isLoggedIn()) {
        return;
    }

    if (isAdmin() || isEmployee()) {
        header("Location: /SIAA/admin/index-admin.php");
        exit();
    } else {
        header("Location: /SIAA/account_page.php");
        exit();
    }
}

/**
 * Check if employee has specific permission
 */
function hasPermission($conn, $permission_name, $action = 'view')
{
    // Admins have all permissions
    if (isAdmin()) {
        return true;
    }

    // Regular employees need to check permissions
    if (!isEmployee()) {
        return false;
    }

    $employee_id = $_SESSION['user_id'];
    $column = "can_" . $action; // can_view, can_create, can_edit, can_delete

    $stmt = $conn->prepare("SELECT $column FROM employee_permissions WHERE employee_id = ? AND permission_name = ?");
    $stmt->bind_param("is", $employee_id, $permission_name);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        return (bool)$row[$column];
    }

    return false;
}

/**
 * Get all permissions for an employee
 */
function getEmployeePermissions($conn, $employee_id)
{
    $stmt = $conn->prepare("
        SELECT permission_name, can_view, can_create, can_edit, can_delete 
        FROM employee_permissions 
        WHERE employee_id = ?
    ");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Redirect to appropriate page based on role
 */
function redirectToDashboard()
{
    if (isAdmin() || isEmployee()) {
        header("Location: /SIAA/admin/index-admin.php");
    } else {
        header("Location: /SIAA/account_page.php");
    }
    exit();
}
