<?php
include 'guard.php';
include '../config.php';

$message = "";

// Assign rider to an order
if (isset($_POST['assign_rider'])) {
    $order_id = $_POST['order_id'];
    $rider_id = $_POST['rider_id'];

    $check = $conn->prepare("SELECT id FROM deliveries WHERE order_id = ?");
    $check->bind_param("i", $order_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE deliveries SET rider_id = ?, status = 'assigned', assigned_at = NOW() WHERE order_id = ?");
        $stmt->bind_param("ii", $rider_id, $order_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO deliveries (order_id, rider_id, status, assigned_at) VALUES (?, ?, 'assigned', NOW())");
        $stmt->bind_param("ii", $order_id, $rider_id);
    }
    $stmt->execute();

    $update = $conn->prepare("UPDATE orders SET status = 'out_for_delivery' WHERE id = ?");
    $update->bind_param("i", $order_id);
    $update->execute();

    $message = "Rider assigned successfully.";
}

$search        = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$query = "SELECT o.*, u.first_name, u.last_name, u.location, u.phone,
          s.first_name as sup_fname, s.last_name as sup_lname
          FROM orders o
          JOIN users u ON o.user_id = u.id
          JOIN users s ON o.supplier_id = s.id
          WHERE 1=1";

if ($search != '') {
    $query .= " AND (u.first_name LIKE '%$search%' OR u.last_name LIKE '%$search%' OR u.location LIKE '%$search%')";
}
if ($status_filter != '') {
    $query .= " AND o.status = '$status_filter'";
}
$query .= " ORDER BY o.created_at DESC";
$orders = $conn->query($query);

// Get active riders
$riders = $conn->query("SELECT id, first_name, last_name, location FROM users WHERE role = 'rider' AND status = 'active'");
$riders_list = [];
while ($r = $riders->fetch_assoc()) {
    $riders_list[] = $r;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>All Orders - Admin</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body class="page-dashboard">
<div class="container">

    <div class="page-header">
        <h2>All Orders</h2>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>

    <?php if ($message != "") { ?>
        <div class="msg-success"><?php echo $message; ?></div>
    <?php } ?>

    <div class="card">
        <h3>Search and Filter</h3>
        <form method="GET" action="orders.php" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
            <div>
                <label>Search:</label>
                <input type="text" name="search" placeholder="Customer name or location" value="<?php echo $search; ?>" style="width:230px;">
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

    <div class="card">
        <h3>All Orders</h3>
        <?php if ($orders->num_rows > 0) { ?>
        <table>
            <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Phone</th>
                <th>Delivery Address</th>
                <th>Supplier</th>
                <th>Amount (KSh)</th>
                <th>Status</th>
                <th>Date</th>
                <th>Assign Rider</th>
            </tr>
            <?php while ($order = $orders->fetch_assoc()) {
                $status = $order['status'];
                $class = 'status-gray';
                if ($status == 'delivered')        $class = 'status-ok';
                elseif ($status == 'pending_quote') $class = 'status-low';
                elseif ($status == 'quoted')        $class = 'status-medium';
                elseif ($status == 'pending_payment') $class = 'status-low';
                elseif ($status == 'out_for_delivery') $class = 'status-medium';
                elseif ($status == 'rejected')      $class = 'status-low';
            ?>
            <tr>
                <td>#<?php echo $order['id']; ?></td>
                <td><?php echo $order['first_name'] . ' ' . $order['last_name']; ?></td>
                <td><?php echo $order['phone']; ?></td>
                <td><?php echo $order['delivery_address']; ?></td>
                <td><?php echo $order['sup_fname'] . ' ' . $order['sup_lname']; ?></td>
                <td>KSh <?php echo number_format($order['total_amount'], 2); ?></td>
                <td><span class="<?php echo $class; ?>"><?php echo ucwords(str_replace('_', ' ', $status)); ?></span></td>
                <td><?php echo date('d M Y', strtotime($order['created_at'])); ?></td>
                <td>
                    <?php if ($status == 'confirmed') { ?>
                    <form method="POST">
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        <select name="rider_id" required style="width:130px; padding:4px; margin-bottom:5px;">
                            <option value="">Select Rider</option>
                            <?php foreach ($riders_list as $rider) { ?>
                                <option value="<?php echo $rider['id']; ?>">
                                    <?php echo $rider['first_name'] . ' (' . $rider['location'] . ')'; ?>
                                </option>
                            <?php } ?>
                        </select><br>
                        <button type="submit" name="assign_rider" class="btn btn-green" style="padding:5px 10px; font-size:12px;">Assign</button>
                    </form>
                    <?php } else { ?>
                        <span class="status-gray">—</span>
                    <?php } ?>
                </td>
            </tr>
            <?php } ?>
        </table>
        <br>
        <button onclick="window.print();" class="btn btn-gray">Print This Report</button>
        <?php } else { ?>
            <p style="color:#777;">No orders found.</p>
        <?php } ?>
    </div>

</div>
</body>
</html>