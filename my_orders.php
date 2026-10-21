<?php
require_once __DIR__ . '/session_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

$user_id = $_SESSION['user_id'];
$message = "";

$placed  = isset($_GET['placed'])  ? true : false;
$success = isset($_GET['success']) ? true : false;

// User confirms a quote and proceeds to payment
if (isset($_POST['confirm_quote'])) {
    $order_id = $_POST['order_id'];
    $stmt = $conn->prepare("UPDATE orders SET status = 'pending_payment' WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();

    $stmt = $conn->prepare("SELECT total_amount FROM orders WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total_amount'];

    $_SESSION['pending_order_id']    = $order_id;
    $_SESSION['pending_order_total'] = $total;

    header("Location: payment.php");
    exit();
}

$search        = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Fetch all orders for this user in one query
$query = "SELECT o.id, o.total_amount, o.status, o.created_at, o.delivery_address,
          s.first_name as sup_fname, s.last_name as sup_lname,
          d.status as delivery_status, d.notes as delivery_notes,
          r.first_name as rider_fname, r.last_name as rider_lname, r.phone as rider_phone
          FROM orders o
          LEFT JOIN users s ON o.supplier_id = s.id
          LEFT JOIN deliveries d ON o.id = d.order_id
          LEFT JOIN users r ON d.rider_id = r.id
          WHERE o.user_id = $user_id";

if ($search != '') {
    $query .= " AND (s.first_name LIKE '%$search%'
                OR s.last_name LIKE '%$search%'
                OR o.delivery_address LIKE '%$search%'
                OR o.status LIKE '%$search%')";
}
if ($status_filter != '') {
    $query .= " AND o.status = '$status_filter'";
}
$query .= " ORDER BY o.created_at DESC";
$orders_result = $conn->query($query);

// Collect all orders first
$all_orders = [];
$order_ids  = [];
if ($orders_result && $orders_result->num_rows > 0) {
    while ($row = $orders_result->fetch_assoc()) {
        $all_orders[$row['id']] = $row;
        $order_ids[] = $row['id'];
    }
}

// Fetch ALL order items in ONE query using IN clause
$all_items = [];
if (!empty($order_ids)) {
    $ids_string = implode(',', $order_ids);
    $items_result = $conn->query("SELECT * FROM order_items WHERE order_id IN ($ids_string)");
    while ($item = $items_result->fetch_assoc()) {
        $all_items[$item['order_id']][] = $item;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Orders</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="page-shopping">
<div class="container">

    <div class="page-header">
        <h2>My Orders</h2>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>

    <?php if ($placed) { ?>
        <div class="msg-success">Your order has been sent to the supplier. They will review it and send you a price quote shortly.</div>
    <?php } ?>
    <?php if ($success) { ?>
        <div class="msg-success">Payment confirmed! Your order is now being processed.</div>
    <?php } ?>

    <!-- SEARCH AND FILTER -->
    <div class="card">
        <h3>Search and Filter</h3>
        <form method="GET" action="my_orders.php" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
            <div>
                <label>Search:</label>
                <input type="text" name="search" placeholder="Supplier name or address" value="<?php echo $search; ?>" style="width:220px;">
            </div>
            <div>
                <label>Status:</label>
                <select name="status">
                    <option value="">All Statuses</option>
                    <option value="pending_quote"    <?php if ($status_filter == 'pending_quote')    echo 'selected'; ?>>Pending Quote</option>
                    <option value="quoted"           <?php if ($status_filter == 'quoted')           echo 'selected'; ?>>Quote Received</option>
                    <option value="pending_payment"  <?php if ($status_filter == 'pending_payment')  echo 'selected'; ?>>Pending Payment</option>
                    <option value="confirmed"        <?php if ($status_filter == 'confirmed')        echo 'selected'; ?>>Confirmed</option>
                    <option value="out_for_delivery" <?php if ($status_filter == 'out_for_delivery') echo 'selected'; ?>>Out for Delivery</option>
                    <option value="delivered"        <?php if ($status_filter == 'delivered')        echo 'selected'; ?>>Delivered</option>
                    <option value="rejected"         <?php if ($status_filter == 'rejected')         echo 'selected'; ?>>Rejected</option>
                </select>
            </div>
            <button type="submit" class="btn btn-green">Search</button>
            <a href="my_orders.php" class="btn btn-gray">Clear</a>
        </form>
    </div>

    <!-- ORDERS -->
    <div class="card">
        <h3>All My Orders</h3>

        <?php if (!empty($all_orders)) { ?>

        <?php foreach ($all_orders as $o) {
            $status = $o['status'];
            $class = 'status-gray';
            if ($status == 'delivered')            $class = 'status-ok';
            elseif ($status == 'pending_quote')    $class = 'status-low';
            elseif ($status == 'quoted')           $class = 'status-medium';
            elseif ($status == 'pending_payment')  $class = 'status-low';
            elseif ($status == 'out_for_delivery') $class = 'status-medium';
            elseif ($status == 'confirmed')        $class = 'status-medium';
            elseif ($status == 'rejected')         $class = 'status-low';

            // Get items for this order from pre-loaded array
            $order_items = isset($all_items[$o['id']]) ? $all_items[$o['id']] : [];
        ?>

        <div style="border:1px solid #e0e4ea; border-radius:8px; padding:18px; margin-bottom:20px;">

            <!-- Order Header -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:10px;">
                <div>
                    <strong>Order #<?php echo $o['id']; ?></strong> —
                    Supplier: <?php echo ($o['sup_fname'] ? $o['sup_fname'] . ' ' . $o['sup_lname'] : 'Unknown'); ?>
                </div>
                <div>
                    <span class="<?php echo $class; ?>"><?php echo ucwords(str_replace('_', ' ', $status)); ?></span>
                    &nbsp;|&nbsp;
                    <?php echo date('d M Y', strtotime($o['created_at'])); ?>
                </div>
            </div>

            <p style="font-size:13px; color:#777; margin-bottom:10px;">
                <strong>Delivery Address:</strong> <?php echo $o['delivery_address']; ?>
            </p>

            <!-- ORDER ITEMS TABLE -->
            <?php if (!empty($order_items)) { ?>
            <table>
                <tr>
                    <th>Item</th>
                    <th>Quantity</th>
                    <th>Unit</th>
                    <th>Price per Unit (KSh)</th>
                    <th>Subtotal (KSh)</th>
                </tr>
                <?php foreach ($order_items as $item) { ?>
                <tr>
                    <td><?php echo $item['item_name']; ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td><?php echo $item['unit']; ?></td>
                    <td>
                        <?php if ($item['price'] > 0) { ?>
                            KSh <?php echo number_format($item['price'], 2); ?>
                        <?php } else { ?>
                            <span class="status-gray">Awaiting quote</span>
                        <?php } ?>
                    </td>
                    <td>
                        <?php if ($item['price'] > 0) { ?>
                            KSh <?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                        <?php } else { ?>
                            —
                        <?php } ?>
                    </td>
                </tr>
                <?php } ?>
                <?php if ($o['total_amount'] > 0) { ?>
                <tr>
                    <td colspan="4"><strong>Total</strong></td>
                    <td><strong>KSh <?php echo number_format($o['total_amount'], 2); ?></strong></td>
                </tr>
                <?php } ?>
            </table>
            <?php } ?>

            <!-- QUOTE RECEIVED — user confirms and pays -->
            <?php if ($status == 'quoted') { ?>
                <div class="msg-success" style="margin-top:15px;">
                    The supplier has sent a price quote of
                    <strong>KSh <?php echo number_format($o['total_amount'], 2); ?></strong>.
                    Please review the items and prices above and confirm to proceed to payment.
                </div>
                <form method="POST" style="margin-top:10px;">
                    <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                    <button type="submit" name="confirm_quote" class="btn btn-green">Confirm Quote and Pay</button>
                </form>
            <?php } ?>

            <!-- REJECTED -->
            <?php if ($status == 'rejected') { ?>
                <div class="msg-error" style="margin-top:15px;">
                    This order was rejected by the supplier. You can place a new order with a different supplier.
                </div>
            <?php } ?>

            <!-- DELIVERY STATUS -->
            <?php if ($o['delivery_status']) { ?>
                <div style="margin-top:15px; padding:10px; background:#f5f7fa; border-radius:6px; font-size:14px;">
                    <strong>Delivery Status:</strong>
                    <?php
                    $d_class = 'status-gray';
                    if ($o['delivery_status'] == 'delivered')          $d_class = 'status-ok';
                    elseif ($o['delivery_status'] == 'out_for_delivery') $d_class = 'status-medium';
                    elseif ($o['delivery_status'] == 'assigned')       $d_class = 'status-medium';
                    ?>
                    <span class="<?php echo $d_class; ?>"><?php echo ucwords(str_replace('_', ' ', $o['delivery_status'])); ?></span>
                    <?php if ($o['delivery_notes']) { ?>
                        — <em><?php echo $o['delivery_notes']; ?></em>
                    <?php } ?>
                    <?php if ($o['rider_fname']) { ?>
                        <br><strong>Rider:</strong> <?php echo $o['rider_fname'] . ' ' . $o['rider_lname']; ?>
                        (<?php echo $o['rider_phone']; ?>)
                    <?php } ?>
                </div>
            <?php } ?>

        </div>

        <?php } ?>

        <button onclick="window.print();" class="btn btn-gray">Print</button>

        <?php } else { ?>
            <p style="color:#777;">You have not placed any orders yet.</p>
            <br>
            <a href="shopping_list.php" class="btn btn-green">Go to Shopping List</a>
        <?php } ?>
    </div>

</div>
</body>
</html>