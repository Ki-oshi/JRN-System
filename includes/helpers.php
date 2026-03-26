<?php

/**
 * Helper Functions for JRN Business Solutions
 */

/**
 * Generate unique inquiry number
 * Format: INQ-YYYYMMDD-XXXX
 */
function generateInquiryNumber($conn)
{
    $date = date('Ymd');
    $prefix = "INQ-{$date}-";

    // Get the last inquiry number for today
    $stmt = $conn->prepare("
        SELECT inquiry_number 
        FROM inquiries 
        WHERE inquiry_number LIKE ? 
        ORDER BY inquiry_number DESC 
        LIMIT 1
    ");
    $pattern = $prefix . '%';
    $stmt->bind_param("s", $pattern);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Extract the sequence number and increment
        $lastNumber = $row['inquiry_number'];
        $lastSequence = (int)substr($lastNumber, -4);
        $newSequence = $lastSequence + 1;
    } else {
        // First inquiry of the day
        $newSequence = 1;
    }

    // Format: INQ-YYYYMMDD-0001
    $inquiryNumber = $prefix . str_pad($newSequence, 4, '0', STR_PAD_LEFT);

    return $inquiryNumber;
}

/**
 * Generate unique employee account number
 * Format: EMP-YYYYMMDD-XXXX
 */
function generateEmployeeAccountNumber($conn)
{
    $date = date('Ymd');
    $prefix = "EMP-{$date}-";

    // Get the last employee account number for today
    $stmt = $conn->prepare("
        SELECT account_number 
        FROM employees 
        WHERE account_number LIKE ? 
        ORDER BY account_number DESC 
        LIMIT 1
    ");
    $pattern = $prefix . '%';
    $stmt->bind_param("s", $pattern);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Extract the sequence number and increment
        $lastNumber = $row['account_number'];
        $lastSequence = (int)substr($lastNumber, -4);
        $newSequence = $lastSequence + 1;
    } else {
        // First employee of the day
        $newSequence = 1;
    }

    // Format: EMP-YYYYMMDD-0001
    $accountNumber = $prefix . str_pad($newSequence, 4, '0', STR_PAD_LEFT);

    return $accountNumber;
}
