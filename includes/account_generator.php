<?php

/**
 * Generate unique account number
 * Checks BOTH users and employees tables to ensure uniqueness
 */
function generateAccountNumber($conn, $type = 'employee')
{
    if ($type === 'admin') {
        // Admin numbering: ADM-XXXX
        $prefix = "ADM-";

        // Get the latest admin account number
        $stmt = $conn->prepare("SELECT account_number FROM users WHERE account_number LIKE 'ADM-%' ORDER BY account_number DESC LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $last_number = (int)str_replace('ADM-', '', $row['account_number']);
            $new_number = str_pad($last_number + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $new_number = '0001';
        }

        return $prefix . $new_number;
    } else {
        // Employee numbering: EMP-YYYYMMDD-XXXX
        $date = date('Ymd');
        $prefix = "EMP-{$date}-";

        // Get the latest employee account number for today
        $stmt = $conn->prepare("SELECT account_number FROM employees WHERE account_number LIKE ? ORDER BY account_number DESC LIMIT 1");
        $pattern = $prefix . '%';
        $stmt->bind_param("s", $pattern);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $last_number = substr($row['account_number'], -4);
            $new_number = str_pad((int)$last_number + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $new_number = '0001';
        }

        return $prefix . $new_number;
    }
}

/**
 * Legacy function for backward compatibility
 */
function generateEmployeeAccountNumber($conn)
{
    return generateAccountNumber($conn, 'employee');
}
