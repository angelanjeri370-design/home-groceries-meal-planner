<?php
require_once __DIR__ . '/session_config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['pending_order_id'])) {
    header("Location: my_orders.php");
    exit();
}

include 'config.php';

$user_id  = $_SESSION['user_id'];
$order_id = $_SESSION['pending_order_id'];
$total    = $_SESSION['pending_order_total'];

$message = "";
$error   = "";

// ----------------------------------------------------------------
// M-PESA DARAJA API CREDENTIALS (Sandbox)
// Replace with live credentials for production
// ----------------------------------------------------------------
$consumer_key       = "YOUR_CONSUMER_KEY_HERE";
$consumer_secret    = "YOUR_CONSUMER_SECRET_HERE";
$business_shortcode = "174379";
$passkey            = "bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919";
$callback_url       = "https://yourdomain.com/mpesa_callback.php";

function getMpesaToken($consumer_key, $consumer_secret) {
    $credentials = base64_encode($consumer_key . ':' . $consumer_secret);
    $url = "https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials";
    $ch  = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Basic " . $credentials]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);
    return $result['access_token'] ?? null;
}

function initiateSTKPush($token, $phone, $amount, $order_id, $shortcode, $passkey, $callback_url) {
    $timestamp = date('YmdHis');
    $password  = base64_encode($shortcode . $passkey . $timestamp);
    $phone     = '254' . ltrim($phone, '0');
    $payload   = [
        "BusinessShortCode" => $shortcode,
        "Password"          => $password,
        "Timestamp"         => $timestamp,
        "TransactionType"   => "CustomerPayBillOnline",
        "Amount"            => intval($amount),
        "PartyA"            => $phone,
        "PartyB"            => $shortcode,
        "PhoneNumber"       => $phone,
        "CallBackURL"       => $callback_url,
        "AccountReference"  => "Order#" . $order_id,
        "TransactionDesc"   => "Grocery Order Payment"
    ];
    $url = "https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest";
    $ch  = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $token,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

$stk_initiated = false;

// STK Push
if (isset($_POST['initiate_payment'])) {
    $phone = $_POST['mpesa_phone'];
    $token = getMpesaToken($consumer_key, $consumer_secret);
    if ($token) {
        $response = initiateSTKPush($token, $phone, $total, $order_id, $business_shortcode, $passkey, $callback_url);
        if (isset($response['ResponseCode']) && $response['ResponseCode'] == '0') {
            $checkout_id = $response['CheckoutRequestID'];
            $stmt = $conn->prepare("INSERT INTO mpesa_transactions (order_id, user_id, phone_number, amount, mpesa_code, status) VALUES (?, ?, ?, ?, ?, 'pending')");
            $stmt->bind_param("iisds", $order_id, $user_id, $phone, $total, $checkout_id);
            $stmt->execute();
            $stk_initiated = true;
            $message = "Payment request sent to $phone. Check your phone and enter your M-Pesa PIN.";
        } else {
            $error = "Failed to send payment request. " . ($response['errorMessage'] ?? 'Please try again.');
        }
    } else {
        $error = "Could not connect to M-Pesa. Please use the manual code option below.";
    }
}

// Manual M-Pesa code confirmation (for demo/testing)
if (isset($_POST['confirm_manual'])) {
    $mpesa_code = strtoupper(trim($_POST['mpesa_code']));
    $phone      = $_POST['mpesa_phone_manual'];

    // Validate M-Pesa code format
    // Real M-Pesa codes are exactly 10 characters
    // They start with 2-3 uppercase letters followed by 7-8 digits
    // Examples: QEZ1234567, RBK2489031, PGH8765432
    if (empty($mpesa_code)) {
        $error = "Please enter your M-Pesa transaction code.";
    } elseif (!preg_match('/^[A-Z]{2,3}[0-9]{7,8}$/', $mpesa_code)) {
        $error = "Invalid M-Pesa code format. A valid M-Pesa code contains 2-3 letters followed by 7-8 numbers (e.g. QEZ1234567). Please check your M-Pesa SMS and try again.";
    } else {
        // Check if this code has already been used
        $check = $conn->prepare("SELECT id FROM mpesa_transactions WHERE mpesa_code = ?");
        $check->bind_param("s", $mpesa_code);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "This M-Pesa code has already been used. Please check your M-Pesa SMS for the correct code.";
        } else {
            $stmt = $conn->prepare("INSERT INTO mpesa_transactions (order_id, user_id, phone_number, amount, mpesa_code, status) VALUES (?, ?, ?, ?, ?, 'completed')");
            $stmt->bind_param("iisds", $order_id, $user_id, $phone, $total, $mpesa_code);
            $stmt->execute();

            $stmt = $conn->prepare("UPDATE orders SET status = 'confirmed' WHERE id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();

            unset($_SESSION['pending_order_id']);
            unset($_SESSION['pending_order_total']);

            header("Location: my_orders.php?success=1");
            exit();
        }
    }
}

// Get order items
$stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_items = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="page-shopping">
<div class="container">

    <div class="page-header">
        <h2>Complete Payment</h2>
        <a href="my_orders.php">Back to My Orders</a>
    </div>

    <?php if ($message != "") { ?>
        <div class="msg-success"><?php echo $message; ?></div>
    <?php } ?>
    <?php if ($error != "") { ?>
        <div class="msg-error"><?php echo $error; ?></div>
    <?php } ?>

    <!-- ORDER SUMMARY -->
    <div class="card">
        <h3>Order Summary — Order #<?php echo $order_id; ?></h3>
        <table>
            <tr>
                <th>Item</th>
                <th>Quantity</th>
                <th>Unit</th>
                <th>Price (KSh)</th>
                <th>Subtotal (KSh)</th>
            </tr>
            <?php while ($item = $order_items->fetch_assoc()) { ?>
            <tr>
                <td><?php echo $item['item_name']; ?></td>
                <td><?php echo $item['quantity']; ?></td>
                <td><?php echo $item['unit']; ?></td>
                <td>KSh <?php echo number_format($item['price'], 2); ?></td>
                <td>KSh <?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
            </tr>
            <?php } ?>
            <tr>
                <td colspan="4"><strong>Total Amount</strong></td>
                <td><strong>KSh <?php echo number_format($total, 2); ?></strong></td>
            </tr>
        </table>
    </div>

    <?php if (!$stk_initiated) { ?>

    <!-- STK PUSH -->
    <div class="card">
        <h3>Pay via M-Pesa STK Push</h3>
        <p style="font-size:14px; color:#555; margin-bottom:15px;">
            Enter your M-Pesa number. A payment prompt will be sent to your phone.
        </p>
        <form method="POST">
            <label>M-Pesa Phone Number:</label>
            <input type="text" name="mpesa_phone" placeholder="e.g. 0712345678" required>
            <p style="font-size:13px; color:#777; margin-bottom:15px;">
                Amount: <strong>KSh <?php echo number_format($total, 2); ?></strong>
            </p>
            <button type="submit" name="initiate_payment" class="btn btn-green">Send Payment Request</button>
        </form>
    </div>

    <!-- MANUAL CODE ENTRY -->
    <div class="card">
        <h3>Enter M-Pesa Code Manually</h3>
        <p style="font-size:14px; color:#555; margin-bottom:15px;">
            If you have already paid via M-Pesa, enter the confirmation code from your SMS below.
        </p>
        <form method="POST">
            <label>Phone Number Used:</label>
            <input type="text" name="mpesa_phone_manual" placeholder="e.g. 0712345678" required>
            <label>M-Pesa Transaction Code:</label>
            <input type="text" name="mpesa_code" placeholder="e.g. QEZ1234567" required style="text-transform:uppercase;">
            <button type="submit" name="confirm_manual" class="btn btn-green">Confirm Payment</button>
        </form>
    </div>

    <?php } else { ?>

    <!-- AFTER STK PUSH - ENTER CODE -->
    <div class="card">
        <h3>Enter M-Pesa Confirmation Code</h3>
        <p style="font-size:14px; color:#555; margin-bottom:15px;">
            After completing payment on your phone, enter the M-Pesa confirmation code from your SMS.
        </p>
        <form method="POST">
            <label>Phone Number Used:</label>
            <input type="text" name="mpesa_phone_manual" placeholder="e.g. 0712345678" required>
            <label>M-Pesa Transaction Code:</label>
            <input type="text" name="mpesa_code" placeholder="e.g. QEZ1234567" required style="text-transform:uppercase;">
            <button type="submit" name="confirm_manual" class="btn btn-green">Confirm Payment</button>
        </form>
    </div>

    <?php } ?>

</div>
</body>
</html>