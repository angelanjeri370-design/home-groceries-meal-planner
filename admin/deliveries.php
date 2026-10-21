<?php
include 'guard.php';
include '../config.php';

$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$query = "SELECT d.*, o.total_amount, o.delivery_address, o.status as order_status,
          u.first_name as customer_fname, u.last_name as customer_lname, u.phone as customer_phone,
          r.first_name as rider_fname, r.last_name as rider_lname, r.location as rider_location
          FROM deliveries d
          JOIN orders o ON d.order_id = o.id
          JOIN users u ON o.user_id = u.id
          LEFT JOIN users r ON d.rider_id = r.id
          WHERE 1=1";

if ($search != '') {
    $query .= " AND (u.first_name LIKE '%$search%' OR u.last_name LIKE '%$search%' OR r.first_name LIKE '%$search%')";
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
    <title>Deliveries - Admin</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body class="page-dashboard">
<div class="container">

    <div class="page-header">
        <h2>Deliveries</h2>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>

    <!-- SEARCH AND FILTER -->
    <div class="card">
        <h3>Search and Filter</h3>
        <form method="GET" action="deliveries.php" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
            <div>
                <label>Search:</label>
                <input type="text" name="search" placeholder="Customer or rider name" value="<?php echo $search; ?>" style="width:250px;">
            </div>
            <div>
                <label>Status:</label>
                <select name="status">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php if ($status_filter == 'pending') echo 'selected'; ?>>Pending</option>
                    <option value="assigned" <?php if ($status_filter == 'assigned') echo 'selected'; ?>>Assigned</option>
                    <option value="out_for_delivery" <?php if ($status_filter == 'out_for_delivery') echo 'selected'; ?>>Out for Delivery</option>
                    <option value="delivered" <?php if ($status_filter == 'delivered') echo 'selected'; ?>>Delivered</option>
                </select>
            </div>
            <button type="submit" class="btn btn-green">Search</button>
            <a href="deliveries.php" class="btn btn-gray">Clear</a>
        </form>
    </div>

    <!-- DELIVERIES TABLE -->
    <div class="card">
        <h3>All Deliveries</h3>
        <?php if ($deliveries->num_rows > 0) { ?>
        <table>
            <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Phone</th>
                <th>Delivery Address</th>
                <th>Rider</th>
                <th>Rider Location</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Delivered At</th>
            </tr>
            <?php while ($d = $deliveries->fetch_assoc()) {
                $status = $d['status'];
                $class = 'status-gray';
                if ($status == 'delivered') $class = 'status-ok';
                elseif ($status == 'out_for_delivery') $class = 'status-medium';
                elseif ($status == 'assigned') $class = 'status-medium';
            ?>
            <tr>
                <td>#<?php echo $d['order_id']; ?></td>
                <td><?php echo $d['customer_fname'] . ' ' . $d['customer_lname']; ?></td>
                <td><?php echo $d['customer_phone']; ?></td>
                <td><?php echo $d['delivery_address']; ?></td>
                <td><?php echo $d['rider_fname'] ? $d['rider_fname'] . ' ' . $d['rider_lname'] : 'Not assigned'; ?></td>
                <td><?php echo $d['rider_location'] ?? '—'; ?></td>
                <td>KSh <?php echo number_format($d['total_amount'], 2); ?></td>
                <td><span class="<?php echo $class; ?>"><?php echo ucwords(str_replace('_', ' ', $status)); ?></span></td>
                <td><?php echo $d['delivered_at'] ? date('d M Y', strtotime($d['delivered_at'])) : '—'; ?></td>
            </tr>
            <?php } ?>
        </table>
        <br>
        <button onclick="window.print();" class="btn btn-gray">Print This Report</button>
        <?php } else { ?>
            <p style="color:#777;">No deliveries found.</p>
        <?php } ?>
    </div>

</div>
</body>
</html>