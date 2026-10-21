<?php
// ----------------------------------------------------------------
// M-PESA CALLBACK URL
// Safaricom sends payment confirmation to this URL automatically
// This file must be publicly accessible on a live server
// ----------------------------------------------------------------

include 'config.php';

// Get the callback data from Safaricom
$callback_data = file_get_contents('php://input');
$data = json_decode($callback_data, true);

if ($data) {
    $result_code = $data['Body']['stkCallback']['ResultCode'];
    $checkout_id = $data['Body']['stkCallback']['CheckoutRequestID'];

    if ($result_code == 0) {
        // Payment was successful
        $items = $data['Body']['stkCallback']['CallbackMetadata']['Item'];
        $mpesa_code = '';
        $amount     = 0;
        $phone      = '';

        foreach ($items as $item) {
            if ($item['Name'] == 'MpesaReceiptNumber') $mpesa_code = $item['Value'];
            if ($item['Name'] == 'Amount')              $amount     = $item['Value'];
            if ($item['Name'] == 'PhoneNumber')         $phone      = $item['Value'];
        }

        // Update transaction record
        $stmt = $conn->prepare("UPDATE mpesa_transactions SET status = 'completed', mpesa_code = ? WHERE mpesa_code = ?");
        $stmt->bind_param("ss", $mpesa_code, $checkout_id);
        $stmt->execute();

        // Get the order_id from the transaction
        $stmt = $conn->prepare("SELECT order_id FROM mpesa_transactions WHERE mpesa_code = ?");
        $stmt->bind_param("s", $mpesa_code);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result) {
            // Update order status to confirmed
            $stmt = $conn->prepare("UPDATE orders SET status = 'confirmed' WHERE id = ?");
            $stmt->bind_param("i", $result['order_id']);
            $stmt->execute();
        }
    } else {
        // Payment failed — update transaction status
        $stmt = $conn->prepare("UPDATE mpesa_transactions SET status = 'failed' WHERE mpesa_code = ?");
        $stmt->bind_param("s", $checkout_id);
        $stmt->execute();
    }
}

http_response_code(200);
echo json_encode(['status' => 'received']);