<?php
// ZenoPay Order Status Checker

if (!isset($_GET['order_id'])) {
    if (isset($_GET['format']) && $_GET['format'] === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Order ID required']);
    } else {
        echo "Please provide order_id parameter\nExample: check_status.php?order_id=ZP-20250623-154922-782222c8-57\n";
    }
    exit;
}

$order_id = $_GET['order_id'];
$apiKey = 'DtxN1A89j3uqrl4I4efP0ieRH6YpnXMhCu2ehxrdBWUaqT1FCve3taocadtPWN1ui3MpWdZ0vDYo927IvorEuw';
$isJsonRequest = isset($_GET['format']) && $_GET['format'] === 'json';

if (!$isJsonRequest) {
    echo "🔍 Checking status for Order ID: $order_id\n\n";
}

// Use the OFFICIAL ZenoPay API endpoint for status checking
$endpointUrl = "https://zenoapi.com/api/payments/order-status?order_id=" . urlencode($order_id);

// Initialize cURL
$ch = curl_init($endpointUrl);

// Set cURL options for GET request with API key header
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'x-api-key: ' . $apiKey
]);

// Execute the request and get the response
$response = curl_exec($ch);

// Check for cURL errors
if (curl_errno($ch)) {
    $errorResponse = [
        "status" => "error",
        "message" => 'cURL error: ' . curl_error($ch)
    ];
    
    if ($isJsonRequest) {
        header('Content-Type: application/json');
        echo json_encode($errorResponse);
    } else {
        echo json_encode($errorResponse);
    }
} else {
    // Decode the JSON response
    $responseData = json_decode($response, true);

    if ($isJsonRequest) {
        // Return JSON response for AJAX requests
        header('Content-Type: application/json');
        
        // Check for successful response and data array
        if (isset($responseData['result']) && $responseData['result'] === 'SUCCESS' && isset($responseData['data'][0])) {
            $orderData = $responseData['data'][0];
            echo json_encode([
                "status" => "success",
                "order_id" => $orderData['order_id'] ?? 'N/A',
                "message" => $responseData['message'] ?? 'Order found',
                "payment_status" => $orderData['payment_status'] ?? 'PENDING',
                "amount" => $orderData['amount'] ?? 'N/A',
                "channel" => $orderData['channel'] ?? 'N/A',
                "reference" => $orderData['reference'] ?? 'N/A'
            ]);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => $responseData['message'] ?? 'Order not found or API error'
            ]);
        }
    } else {
        // Display human-readable output
        echo "📡 Raw Response:\n$response\n\n";

        if (isset($responseData['result']) && $responseData['result'] === 'SUCCESS' && isset($responseData['data'][0])) {
            $orderData = $responseData['data'][0];
            echo "✅ Status Check Result:\n";
            echo "Order ID: " . ($orderData['order_id'] ?? 'N/A') . "\n";
            echo "Creation Date: " . ($orderData['creation_date'] ?? 'N/A') . "\n";
            echo "Amount: " . ($orderData['amount'] ?? 'N/A') . " TZS\n";
            echo "Payment Status: " . ($orderData['payment_status'] ?? 'N/A') . "\n";
            echo "Channel: " . ($orderData['channel'] ?? 'N/A') . "\n";
            echo "Reference: " . ($orderData['reference'] ?? 'N/A') . "\n";
            echo "Phone: " . ($orderData['msisdn'] ?? 'N/A') . "\n";
            
            if (isset($orderData['payment_status']) && $orderData['payment_status'] === 'COMPLETED') {
                echo "🎉 Payment completed successfully!\n";
            }
        } else {
            echo "❌ Error checking status:\n";
            echo "Message: " . ($responseData['message'] ?? 'Order not found or API error') . "\n";
        }
    }
}

// Close cURL session
curl_close($ch);
?> 