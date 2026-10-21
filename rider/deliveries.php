<?php
include 'guard.php';
include '../config.php';

$rider_id = $_SESSION['user_id'];
$message = "";

// Update delivery status
if (isset($_POST['update_status'])) {
    $delivery_id = $_POST['delivery_id'];
    $new_status  = $_POST['new_status'];
    $notes       = $_POST['notes'];

    if ($new_status == 'delivered') {
        $stmt = $conn->prepare("UPDATE deliveries SET status = ?, notes = ?, delivered_at = NOW() WHERE id = ? AND rider_id = ?");
        $stmt->bind_param("ssii", $new_status, $notes, $delivery_id, $rider_id);
        $stmt->execute();

        // Also update the order status to delivered
        $order_stmt = $conn->prepare("UPDATE orders o JOIN deliveries d ON o.id = d.order_id SET o.status = 'delivered' WHERE d.id = ? AND d.rider_id = ?");
        $order_stmt->bind_param("ii", $delivery_id, $rider_id);
        $order_stmt->execute();
    } else {
        $stmt = $conn->prepare("UPDATE deliveries SET status = ?, notes = ? WHERE id = ? AND rider_id = ?");
        $stmt->bind_param("ssii", $new_status, $notes, $delivery_id, $rider_id);
        $stmt->execute();

        // Update order status too
        $order_stmt = $conn->prepare("UPDATE orders o JOIN deliveries d ON o.id = d.order_id SET o.status = ? WHERE d.id = ? AND d.rider_id = ?");
        $order_stmt->bind_param("sii", $new_status, $delivery_id, $rider_id);
        $order_stmt->execute();
    }

    $message = "Delivery status updated.";
}

$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$query = "SELECT d.id, d.status, d.notes, d.assigned_at, d.delivered_at,
          o.id as order_id, o.delivery_address, o.total_amount,
          u.first_name, u.last_name, u.phone, u.location as customer_location
          FROM deliveries d
          JOIN orders o ON d.order_id = o.id
          JOIN users u ON o.user_id = u.id
          WHERE d.rider_id = $rider_id";

if ($search != '') {
    $query .= " AND (u.first_name LIKE '%$search%' OR u.last_name LIKE '%$search%' OR o.delivery_address LIKE '%$search%')";
}
if ($status_filter != '') {
    $query .= " AND d.status = '$status_filter'";
}
$query .= " ORDER BY d.id DESC";
$deliveries = $conn->query($query);
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Deliveries - Rider</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body class="page-dashboard">
<div class="container">

    <div class="page-header">
        <h2>My Deliveries</h2>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>

    <?php if ($message != "") { ?>
        <div class="msg-success"><?php echo $message; ?></div>
    <?php } ?>

    <!-- SEARCH AND FILTER -->
    <div class="card">
        <h3>Search and Filter</h3>
        <form method="GET" action="deliveries.php" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
            <div>
                <label>Search:</label>
                <input type="text" name="search" placeholder="Customer name or address" value="<?php echo $search; ?>" style="width:230px;">
            </div>
            <div>
                <label>Status:</label>
                <select name="status">
                    <option value="">All Statuses</option>
                    <option value="assigned" <?php if ($status_filter == 'assigned') echo 'selected'; ?>>Assigned</option>
                    <option value="out_for_delivery" <?php if ($status_filter == 'out_for_delivery') echo 'selected'; ?>>Out for Delivery</option>
                    <option value="delivered" <?php if ($status_filter == 'delivered') echo 'selected'; ?>>Delivered</option>
                </select>
            </div>
            <button type="submit" class="btn btn-green">Search</button>
            <a href="deliveries.php" class="btn btn-gray">Clear</a>
        </form>
    </div>

    <!-- DELIVERIES -->
    <div class="card">
        <h3>All My Deliveries</h3>
        <?php if ($deliveries->num_rows > 0) { ?>
        <table>
            <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Phone</th>
                <th>Delivery Address</th>
                <th>Amount (KSh)</th>
                <th>Status</th>
                <th>Notes</th>
                <th>Update Status</th>
            </tr>
            <?php while ($d = $deliveries->fetch_assoc()) {
                $status = $d['status'];
                $class = 'status-gray';
                if ($status == 'delivered') $class = 'status-ok';
                elseif ($status == 'out_for_delivery') $class = 'status-medium';
                elseif ($status == 'assigned') $class = 'status-low';
            ?>
            <tr>
                <td>#<?php echo $d['order_id']; ?></td>
                <td><?php echo $d['first_name'] . ' ' . $d['last_name']; ?></td>
                <td><?php echo $d['phone']; ?></td>
                <td><?php echo $d['delivery_address']; ?></td>
                <td>KSh <?php echo number_format($d['total_amount'], 2); ?></td>
                <td><span class="<?php echo $class; ?>"><?php echo ucwords(str_replace('_', ' ', $status)); ?></span></td>
                <td><?php echo $d['notes'] ?? '—'; ?></td>
                <td>
                    <?php if ($status != 'delivered') { ?>
                    <form method="POST">
                        <input type="hidden" name="delivery_id" value="<?php echo $d['id']; ?>">
                        <select name="new_status" style="width:130px; padding:4px; margin-bottom:5px;">
                            <option value="assigned" <?php if ($status == 'assigned') echo 'selected'; ?>>Assigned</option>
                            <option value="out_for_delivery" <?php if ($status == 'out_for_delivery') echo 'selected'; ?>>Out for Delivery</option>
                            <option value="delivered">Delivered</option>
                        </select><br>
                        <input type="text" name="notes" placeholder="Add notes (optional)" style="width:130px; padding:4px; margin-bottom:5px;"><br>
                        <button type="submit" name="update_status" class="btn btn-green" style="padding:5px 10px; font-size:12px;">Update</button>
                    </form>
                    <?php } else { ?>
                        <span class="status-ok">Completed</span>
                        <?php if ($d['delivered_at']) { ?>
                            <br><small><?php echo date('d M Y', strtotime($d['delivered_at'])); ?></small>
                        <?php } ?>
                    <?php } ?>
                </td>
            </tr>
            <?php } ?>
        </table>
        <br>
        <button onclick="window.print();" class="btn btn-gray">Print</button>
        <?php } else { ?>
            <p style="color:#777;">No deliveries assigned yet.</p>
        <?php } ?>
    </div>

</div>
</body>
</html>