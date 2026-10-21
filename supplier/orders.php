<?php
include 'guard.php';
include '../config.php';

$supplier_id = $_SESSION['user_id'];
$message = "";
$error   = "";

// Submit price quote for an order
if (isset($_POST['submit_quote'])) {
    $order_id    = $_POST['order_id'];
    $item_ids    = $_POST['item_ids'];
    $item_prices = $_POST['item_prices'];
    $total = 0;

    foreach ($item_ids as $i => $item_id) {
        $price = floatval($item_prices[$i]);

        // Update price for each item
        $stmt = $conn->prepare("UPDATE order_items SET price = ? WHERE id = ? AND order_id = ?");
        if ($stmt === false) { die("Error: " . $conn->error); }
        $stmt->bind_param("dii", $price, $item_id, $order_id);
        $stmt->execute();

        // Get quantity to calculate total
        $qty_stmt = $conn->prepare("SELECT quantity FROM order_items WHERE id = ?");
        $qty_stmt->bind_param("i", $item_id);
        $qty_stmt->execute();
        $qty = $qty_stmt->get_result()->fetch_assoc()['quantity'];
        $total += $price * $qty;
    }

    // Update order total and status to quoted
    $stmt = $conn->prepare("UPDATE orders SET total_amount = ?, status = 'quoted' WHERE id = ? AND supplier_id = ?");
    if ($stmt === false) { die("Error: " . $conn->error); }
    $stmt->bind_param("dii", $total, $order_id, $supplier_id);
    $stmt->execute();
    $message = "Price quote sent to customer successfully.";
}

// Reject an order
if (isset($_POST['reject_order'])) {
    $order_id = $_POST['order_id'];
    $stmt = $conn->prepare("UPDATE orders SET status = 'rejected' WHERE id = ? AND supplier_id = ?");
    if ($stmt === false) { die("Error: " . $conn->error); }
    $stmt->bind_param("ii", $order_id, $supplier_id);
    $stmt->execute();
    $message = "Order rejected.";
}

$search        = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$query = "SELECT o.id, o.total_amount, o.status, o.created_at, o.delivery_address,
          u.first_name, u.last_name, u.phone, u.location
          FROM orders o
          JOIN users u ON o.user_id = u.id
          WHERE o.supplier_id = $supplier_id";

if ($search != '') {
    $query .= " AND (u.first_name LIKE '%$search%' OR u.last_name LIKE '%$search%')";
}
if ($status_filter != '') {
    $query .= " AND o.status = '$status_filter'";
}
$query .= " ORDER BY o.created_at DESC";
$orders = $conn->query($query);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Orders - Supplier</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body class="page-dashboard">
<div class="container">

    <div class="page-header">
        <h2>Manage Orders</h2>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>

    <?php if ($message != "") { ?>
        <div class="msg-success"><?php echo $message; ?></div>
    <?php } ?>
    <?php if ($error != "") { ?>
        <div class="msg-error"><?php echo $error; ?></div>
    <?php } ?>

    <!-- SEARCH AND FILTER -->
    <div class="card">
        <h3>Search and Filter</h3>
        <form method="GET" action="orders.php" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
            <div>
                <label>Search:</label>
                <input type="text" name="search" placeholder="Customer name" value="<?php echo $search; ?>" style="width:220px;">
            </div>
            <div>
                <label>Status:</label>
                <select name="status">
                    <option value="">All Statuses</option>
                    <option value="pending_quote"    <?php if ($status_filter == 'pending_quote')    echo 'selected'; ?>>Pending Quote</option>
                    <option value="quoted"           <?php if ($status_filter == 'quoted')           echo 'selected'; ?>>Quoted</option>
                    <option value="pending_payment"  <?php if ($status_filter == 'pending_payment')  echo 'selected'; ?>>Pending Payment</option>
                    <option value="confirmed"        <?php if ($status_filter == 'confirmed')        echo 'selected'; ?>>Confirmed</option>
                    <option value="out_for_delivery" <?php if ($status_filter == 'out_for_delivery') echo 'selected'; ?>>Out for Delivery</option>
                    <option value="delivered"        <?php if ($status_filter == 'delivered')        echo 'selected'; ?>>Delivered</option>
                    <option value="rejected"         <?php if ($status_filter == 'rejected')         echo 'selected'; ?>>Rejected</option>
                </select>
            </div>
            <button type="submit" class="btn btn-green">Search</button>
            <a href="orders.php" class="btn btn-gray">Clear</a>
        </form>
    </div>

    <!-- ORDERS -->
    <div class="card">
        <h3>All My Orders</h3>

        <?php if ($orders && $orders->num_rows > 0) { ?>

        <?php while ($order = $orders->fetch_assoc()) {
            $status = $order['status'];
            $class = 'status-gray';
            if ($status == 'delivered')           $class = 'status-ok';
            elseif ($status == 'pending_quote')   $class = 'status-low';
            elseif ($status == 'quoted')          $class = 'status-medium';
            elseif ($status == 'confirmed')       $class = 'status-medium';
            elseif ($status == 'rejected')        $class = 'status-low';

            // Get order items
            $items_stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $items_stmt->bind_param("i", $order['id']);
            $items_stmt->execute();
            $items = $items_stmt->get_result();
            $items_array = [];
            while ($row = $items->fetch_assoc()) {
                $items_array[] = $row;
            }
        ?>

        <div style="border:1px solid #e0e4ea; border-radius:8px; padding:18px; margin-bottom:20px;">

            <!-- Order Header -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:10px;">
                <div>
                    <strong>Order #<?php echo $order['id']; ?></strong> —
                    <?php echo $order['first_name'] . ' ' . $order['last_name']; ?>
                    (<?php echo $order['phone']; ?>) —
                    <?php echo $order['location']; ?>
                </div>
                <div>
                    <span class="<?php echo $class; ?>"><?php echo ucwords(str_replace('_', ' ', $status)); ?></span>
                    &nbsp;|&nbsp;
                    <?php echo date('d M Y', strtotime($order['created_at'])); ?>
                </div>
            </div>

            <p style="font-size:13px; color:#777; margin-bottom:15px;">
                <strong>Delivery Address:</strong> <?php echo $order['delivery_address']; ?>
            </p>

            <?php if ($status == 'pending_quote') { ?>
            <!-- SUPPLIER ENTERS PRICES HERE -->
            <form method="POST" action="orders.php">
                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                <table>
                    <tr>
                        <th>Item</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Your Price per Unit (KSh)</th>
                        <th>Estimated Subtotal</th>
                    </tr>
                    <?php foreach ($items_array as $item) { ?>
                    <tr>
                        <td><?php echo $item['item_name']; ?></td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td><?php echo $item['unit']; ?></td>
                        <td>
                            <input type="hidden" name="item_ids[]" value="<?php echo $item['id']; ?>">
                            <input type="number" step="0.01" min="0.01" name="item_prices[]"
                                   placeholder="e.g. 150.00" required
                                   style="width:120px; padding:5px; border:1px solid #ccc; border-radius:4px;"
                                   oninput="updateSubtotal(this, <?php echo $item['quantity']; ?>, 'sub_<?php echo $item['id']; ?>')">
                        </td>
                        <td id="sub_<?php echo $item['id']; ?>">—</td>
                    </tr>
                    <?php } ?>
                    <tr>
                        <td colspan="4"><strong>Total</strong></td>
                        <td><strong id="grand_total_<?php echo $order['id']; ?>">KSh 0.00</strong></td>
                    </tr>
                </table>
                <br>
                <button type="submit" name="submit_quote" class="btn btn-green">Send Price Quote to Customer</button>
                &nbsp;
                <button type="submit" name="reject_order" class="btn btn-red"
                        onclick="return confirm('Are you sure you want to reject this order?');">
                    Reject Order
                </button>
            </form>

            <?php } else { ?>
            <!-- SHOW ITEMS WITH PRICES FOR OTHER STATUSES -->
            <table>
                <tr>
                    <th>Item</th>
                    <th>Quantity</th>
                    <th>Unit</th>
                    <th>Price per Unit (KSh)</th>
                    <th>Subtotal (KSh)</th>
                </tr>
                <?php foreach ($items_array as $item) { ?>
                <tr>
                    <td><?php echo $item['item_name']; ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td><?php echo $item['unit']; ?></td>
                    <td>
                        <?php if ($item['price'] > 0) { ?>
                            KSh <?php echo number_format($item['price'], 2); ?>
                        <?php } else { ?>
                            <span class="status-gray">—</span>
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
                <?php if ($order['total_amount'] > 0) { ?>
                <tr>
                    <td colspan="4"><strong>Total</strong></td>
                    <td><strong>KSh <?php echo number_format($order['total_amount'], 2); ?></strong></td>
                </tr>
                <?php } ?>
            </table>
            <?php } ?>

        </div>

        <?php } ?>

        <br>
        <button onclick="window.print();" class="btn btn-gray">Print</button>

        <?php } else { ?>
            <p style="color:#777;">No orders found.</p>
        <?php } ?>
    </div>

</div>

<script>
function updateSubtotal(input, qty, subId) {
    var price = parseFloat(input.value) || 0;
    var subtotal = price * qty;
    document.getElementById(subId).textContent = 'KSh ' + subtotal.toFixed(2);

    // Update grand total for this order
    var allPriceInputs = input.closest('form').querySelectorAll('input[name="item_prices[]"]');
    var allQtyHidden   = input.closest('form').querySelectorAll('input[name="item_ids[]"]');
    var total = 0;
    allPriceInputs.forEach(function(inp, i) {
        var p = parseFloat(inp.value) || 0;
        var row = inp.closest('tr');
        var qtyCell = row.querySelector('td:nth-child(2)');
        var q = parseFloat(qtyCell ? qtyCell.textContent : 1) || 1;
        total += p * q;
    });
    var grandTotalEl = input.closest('form').querySelector('[id^="grand_total_"]');
    if (grandTotalEl) grandTotalEl.textContent = 'KSh ' + total.toFixed(2);
}
</script>

</body>
</html>