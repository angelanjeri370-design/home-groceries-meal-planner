<?php
include 'guard.php';
include '../config.php';

$supplier_id = $_SESSION['user_id'];
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('m');
$selected_year  = isset($_GET['year'])  ? $_GET['year']  : date('Y');
$search         = isset($_GET['search']) ? $_GET['search'] : '';

$months = ['01'=>'January','02'=>'February','03'=>'March','04'=>'April',
           '05'=>'May','06'=>'June','07'=>'July','08'=>'August',
           '09'=>'September','10'=>'October','11'=>'November','12'=>'December'];
$years = [];
for ($y = date('Y'); $y >= date('Y') - 2; $y--) { $years[] = $y; }

// Monthly orders for this supplier
$query = "SELECT o.id, o.total_amount, o.status, o.created_at, o.delivery_address,
          u.first_name, u.last_name, u.phone
          FROM orders o
          JOIN users u ON o.user_id = u.id
          WHERE o.supplier_id = $supplier_id
          AND MONTH(o.created_at) = $selected_month
          AND YEAR(o.created_at) = $selected_year";
if ($search != '') {
    $query .= " AND (u.first_name LIKE '%$search%' OR u.last_name LIKE '%$search%')";
}
$query .= " ORDER BY o.created_at DESC";
$orders = $conn->query($query);

// Monthly revenue
$rev_stmt = $conn->prepare("SELECT SUM(total_amount) as revenue FROM orders WHERE supplier_id = ? AND status = 'delivered' AND MONTH(created_at) = ? AND YEAR(created_at) = ?");
$rev_stmt->bind_param("iii", $supplier_id, $selected_month, $selected_year);
$rev_stmt->execute();
$revenue = $rev_stmt->get_result()->fetch_assoc()['revenue'] ?? 0;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reports - Supplier</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        @media print { .no-print { display:none; } }
    </style>
</head>
<body class="page-dashboard">
<div class="container">

    <div class="page-header no-print">
        <h2>My Reports</h2>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>

    <div class="card no-print">
        <h3>Filter</h3>
        <form method="GET" action="reports.php" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
            <div>
                <label>Search:</label>
                <input type="text" name="search" placeholder="Customer name" value="<?php echo $search; ?>" style="width:200px;">
            </div>
            <div>
                <label>Month:</label>
                <select name="month">
                    <?php foreach ($months as $num => $name) { ?>
                        <option value="<?php echo $num; ?>" <?php if ($num == $selected_month) echo 'selected'; ?>><?php echo $name; ?></option>
                    <?php } ?>
                </select>
            </div>
            <div>
                <label>Year:</label>
                <select name="year">
                    <?php foreach ($years as $year) { ?>
                        <option value="<?php echo $year; ?>" <?php if ($year == $selected_year) echo 'selected'; ?>><?php echo $year; ?></option>
                    <?php } ?>
                </select>
            </div>
            <button type="submit" class="btn btn-green">Filter</button>
            <a href="reports.php" class="btn btn-gray">Clear</a>
            <button type="button" onclick="window.print();" class="btn btn-gray">Print</button>
        </form>
    </div>

    <!-- REVENUE SUMMARY -->
    <div class="card">
        <h3>Revenue Summary — <?php echo $months[$selected_month] . ' ' . $selected_year; ?></h3>
        <table>
            <tr>
                <td><strong>Total Revenue (Delivered Orders):</strong></td>
                <td class="status-ok"><strong>KSh <?php echo number_format($revenue, 2); ?></strong></td>
            </tr>
        </table>
    </div>

    <!-- ORDERS REPORT -->
    <div class="card">
        <h3>Orders — <?php echo $months[$selected_month] . ' ' . $selected_year; ?></h3>
        <?php if ($orders->num_rows > 0) { ?>
        <table>
            <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Phone</th>
                <th>Delivery Address</th>
                <th>Amount (KSh)</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
            <?php while ($o = $orders->fetch_assoc()) {
                $status = $o['status'];
                $class = 'status-gray';
                if ($status == 'delivered') $class = 'status-ok';
                elseif ($status == 'pending_payment') $class = 'status-low';
                elseif ($status == 'confirmed') $class = 'status-medium';
            ?>
            <tr>
                <td>#<?php echo $o['id']; ?></td>
                <td><?php echo $o['first_name'] . ' ' . $o['last_name']; ?></td>
                <td><?php echo $o['phone']; ?></td>
                <td><?php echo $o['delivery_address']; ?></td>
                <td>KSh <?php echo number_format($o['total_amount'], 2); ?></td>
                <td><span class="<?php echo $class; ?>"><?php echo ucwords(str_replace('_', ' ', $status)); ?></span></td>
                <td><?php echo date('d M Y', strtotime($o['created_at'])); ?></td>
            </tr>
            <?php } ?>
        </table>
        <?php } else { ?>
            <p style="color:#777;">No orders for this period.</p>
        <?php } ?>
        <br>
        <button onclick="window.print();" class="btn btn-gray no-print">Print</button>
    </div>

</div>
</body>
</html>