<?php
session_start();
require_once 'connection/dbconn.php';
require_once 'includes/auth.php';

requireUser();

if (!isset($_GET['invoice_id']) || !ctype_digit($_GET['invoice_id'])) {
    $_SESSION['error'] = 'Payment was cancelled.';
    header('Location: account_page.php#billing');
    exit;
}

$invoice_id = (int)$_GET['invoice_id'];

// Optional: you can log the cancellation or set status back to unpaid/pending if needed.
// For now we just redirect with a message.

$_SESSION['error'] = 'You cancelled the payment for this invoice. No charges were made.';
header('Location: account_page.php#billing');
exit;
