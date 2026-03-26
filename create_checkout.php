<?php
session_start();
require_once 'connection/dbconn.php';
require_once 'includes/auth.php';

requireUser();

// Basic validation
if (!isset($_GET['invoice_id']) || !ctype_digit($_GET['invoice_id'])) {
    die('Invalid invoice ID.');
}
$invoice_id = (int)$_GET['invoice_id'];

// Fetch invoice
$stmt = $conn->prepare("
    SELECT id, invoice_number, client_name, total_amount, status
    FROM billings
    WHERE id = ?
");
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$invoice) {
    die('Invoice not found.');
}

// Only allow unpaid/pending invoices to be paid
if (!in_array($invoice['status'], ['unpaid', 'pending'], true)) {
    $_SESSION['error'] = 'This invoice is not payable.';
    header('Location: account_page.php#billing');
    exit;
}

// PayMongo config (test keys)
$secretKey = 'sk_test_FMQQ3fHLvC9NWz1Fdhnowj6r';

// Amount in centavos
$amount = (int) round($invoice['total_amount'] * 100);

// Build Checkout Session payload
$baseUrl = 'http://localhost/SIAA'; // adjust to your local/production base URL

$payload = [
    'data' => [
        'attributes' => [
            'description' => 'Invoice #' . $invoice['invoice_number'],
            'line_items'  => [
                [
                    'name'        => 'Invoice ' . $invoice['invoice_number'],
                    'amount'      => $amount,
                    'currency'    => 'PHP',
                    'quantity'    => 1,
                    'description' => 'Services for ' . $invoice['client_name'],
                ],
            ],
            'payment_method_types' => ['card', 'gcash', 'paymaya'],
            'reference_number'     => $invoice['invoice_number'],
            'success_url'          => $baseUrl . '/payment_success.php?invoice_id=' . $invoice_id,
            'cancel_url'           => $baseUrl . '/payment_cancelled.php?invoice_id=' . $invoice_id,
        ]
    ]
];


$ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode($secretKey . ':'),
    ],
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
]);

$response = curl_exec($ch);

if ($response === false) {
    $err = curl_error($ch);
    curl_reset($ch); // instead of curl_close
    die('Curl error: ' . htmlspecialchars($err));
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_reset($ch); // instead of curl_close

$data = json_decode($response, true);

// On success, redirect to checkout_url
if ($httpCode >= 200 && $httpCode < 300 && isset($data['data']['attributes']['checkout_url'])) {
    $checkoutUrl = $data['data']['attributes']['checkout_url'];
    header('Location: ' . $checkoutUrl);
    exit;
}

// Debug for test mode
echo 'Failed to create checkout session (HTTP ' . (int)$httpCode . ').<br>';
echo '<pre>' . htmlspecialchars($response) . '</pre>';
