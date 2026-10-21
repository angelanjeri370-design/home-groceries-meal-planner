<?php
include 'guard.php';
include '../config.php';

$selected_month = isset($_GET['month']) ? $_GET['month'] : date('m');
$selected_year  = isset($_GET['year'])  ? $_GET['year']  : date('Y');
$search         = isset($_GET['search']) ? $_GET['search'] : '';

$months = ['01'=>'January','02'=>'February','03'=>'March','04'=>'April',
           '05'=>'May','06'=>'June','07'=>'July','08'=>'August',
           '09'=>'September','10'=>'October','11'=>'November','12'=>'December'];
$years = [];
for ($y = date('Y'); $y >= date('Y') - 2; $y--) { $years[] = $y; }

// Report 1: All registered users
$user_query = "SELECT first_name, last_name, username, email, phone, role, location, status FROM users WHERE role != 'admin'";
if ($search != '') {
    $user_query .= " AND (first_name LIKE '%$search%' OR last_name LIKE '%$search%' OR username LIKE '%$search%')";
}
$all_users = $conn->query($user_query);

// Report 2: Monthly orders
$orders_query = "SELECT o.id, o.total_amount, o.status, o.created_at, o.delivery_address,
                 u.first_name, u.last_name,
                 s.first_name as sup_fname, s.last_name as sup_lname
                 FROM orders o
                 JOIN users u ON o.user_id = u.id
                 JOIN users s ON o.supplier_id = s.id
                 WHERE MONTH(o.created_at) = $selected_month
                 AND YEAR(o.created_at) = $selected_year";
if ($search != '') {
    $orders_query .= " AND (u.first_name LIKE '%$search%' OR u.last_name LIKE '%$search%')";
}
$orders_query .= " ORDER BY o.created_at DESC";
$monthly_orders = $conn->query($orders_query);

// Monthly total
$total_stmt = $conn->prepare("SELECT SUM(total_amount) as total FROM orders WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?");
$total_stmt->bind_param("ii", $selected_month, $selected_year);
$total_stmt->execute();
$monthly_total = $total_stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Report 3: All deliveries
$delivery_query = "SELECT d.status, d.delivered_at, o.delivery_address, o.total_amount,
                   u.first_name as cust_fname, u.last_name as cust_lname,
                   r.first_name as rider_fname, r.last_name as rider_lname
                   FROM deliveries d
                   JOIN orders o ON d.order_id = o.id
                   JOIN users u ON o.user_id = u.id
                   LEFT JOIN users r ON d.rider_id = r.id
                   WHERE 1=1";
if ($search != '') {
    $delivery_query .= " AND (u.first_name LIKE '%$search%' OR r.first_name LIKE '%$search%')";
}
$delivery_query .= " ORDER BY d.id DESC";
$all_deliveries = $conn->query($delivery_query);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reports - Admin</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        @media print {
            .no-print { display: none; }
            .card { box-shadow: none; border: 1px solid #ccc; }
        }
    </style>
</head>
<body class="page-dashboard">
<div class="container">

    <div class="page-header no-print">
        <h2>Admin Reports</h2>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>

    <!-- FILTERS -->
    <div class="card no-print">
        <h3>Filter Reports</h3>
        <form method="GET" action="reports.php" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
            <div>
                <label>Search:</label>
                <input type="text" name="search" placeholder="Name or username" value="<?php echo $search; ?>" style="width:200px;">
            </div>
            <div>
                <label>Month:</label>
                <select name="month">
                    <?php foreach ($months as $num => $name) { ?>
                        <option value="<?php echo $num; ?>" <?php if ($num == $selected_month) echo 'selected'; ?>>
                            <?php echo $name; ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div>
                <label>Year:</label>
                <select name="year">
                    <?php foreach ($years as $year) { ?>
                        <option value="<?php echo $year; ?>" <?php if ($year == $selected_year) echo 'selected'; ?>>
                            <?php echo $year; ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <button type="submit" class="btn btn-green">Filter</button>
            <a href="reports.php" class="btn btn-gray">Clear</a>
            <button type="button" onclick="window.print();" class="btn btn-gray">Print All Reports</button>
        </form>
    </div>

    <!-- REPORT 1: ALL USERS -->
    <div class="card">
        <h3>Report 1: Registered Users</h3>
        <table>
            <tr>
                <th>Name</th>
                <th>Username</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Role</th>
                <th>Location</th>
                <th>Status</th>
            </tr>
            <?php while ($u = $all_users->fetch_assoc()) { ?>
            <tr>
                <td><?php echo $u['first_name'] . ' ' . $u['last_name']; ?></td>
                <td><?php echo $u['username']; ?></td>
                <td><?php echo $u['email']; ?></td>
                <td><?php echo $u['phone']; ?></td>
                <td><?php echo ucfirst($u['role']); ?></td>
                <td><?php echo $u['location']; ?></td>
                <td class="<?php echo $u['status'] == 'active' ? 'status-ok' : 'status-low'; ?>">
                    <?php echo ucfirst($u['status']); ?>
                </td>
            </tr>
            <?php } ?>
        </table>
        <br>
        <button onclick="window.print();" class="btn btn-gray no-print">Print</button>
    </div>

    <!-- REPORT 2: MONTHLY ORDERS -->
    <div class="card">
        <h3>Report 2: Orders — <?php echo $months[$selected_month] . ' ' . $selected_year; ?></h3>
        <?php if ($monthly_orders->num_rows > 0) { ?>
        <table>
            <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Supplier</th>
                <th>Delivery Address</th>
                <th>Amount (KSh)</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
            <?php while ($o = $monthly_orders->fetch_assoc()) {
                $status = $o['status'];
                $class = 'status-gray';
                if ($status == 'delivered') $class = 'status-ok';
                elseif ($status == 'pending_payment') $class = 'status-low';
            ?>
            <tr>
                <td>#<?php echo $o['id']; ?></td>
                <td><?php echo $o['first_name'] . ' ' . $o['last_name']; ?></td>
                <td><?php echo $o['sup_fname'] . ' ' . $o['sup_lname']; ?></td>
                <td><?php echo $o['delivery_address']; ?></td>
                <td>KSh <?php echo number_format($o['total_amount'], 2); ?></td>
                <td><span class="<?php echo $class; ?>"><?php echo ucwords(str_replace('_', ' ', $status)); ?></span></td>
                <td><?php echo date('d M Y', strtotime($o['created_at'])); ?></td>
            </tr>
            <?php } ?>
            <tr>
                <td colspan="4"><strong>Monthly Total</strong></td>
                <td colspan="3"><strong>KSh <?php echo number_format($monthly_total, 2); ?></strong></td>
            </tr>
        </table>
        <?php } else { ?>
            <p style="color:#777;">No orders for this period.</p>
        <?php } ?>
        <br>
        <button onclick="window.print();" class="btn btn-gray no-print">Print</button>
    </div>

    <!-- REPORT 3: DELIVERIES -->
    <div class="card">
        <h3>Report 3: All Deliveries</h3>
        <?php if ($all_deliveries->num_rows > 0) { ?>
        <table>
            <tr>
                <th>Customer</th>
                <th>Rider</th>
                <th>Delivery Address</th>
                <th>Amount (KSh)</th>
                <th>Status</th>
                <th>Delivered At</th>
            </tr>
            <?php while ($d = $all_deliveries->fetch_assoc()) {
                $status = $d['status'];
                $class = 'status-gray';
                if ($status == 'delivered') $class = 'status-ok';
                elseif ($status == 'out_for_delivery') $class = 'status-medium';
            ?>
            <tr>
                <td><?php echo $d['cust_fname'] . ' ' . $d['cust_lname']; ?></td>
                <td><?php echo $d['rider_fname'] ? $d['rider_fname'] . ' ' . $d['rider_lname'] : 'Not assigned'; ?></td>
                <td><?php echo $d['delivery_address']; ?></td>
                <td>KSh <?php echo number_format($d['total_amount'], 2); ?></td>
                <td><span class="<?php echo $class; ?>"><?php echo ucwords(str_replace('_', ' ', $status)); ?></span></td>
                <td><?php echo $d['delivered_at'] ? date('d M Y', strtotime($d['delivered_at'])) : '—'; ?></td>
            </tr>
            <?php } ?>
        </table>
        <?php } else { ?>
            <p style="color:#777;">No deliveries yet.</p>
        <?php } ?>
        <br>
        <button onclick="window.print();" class="btn btn-gray no-print">Print</button>
    </div>

</div>
</body>
</html>