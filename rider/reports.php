<?php
include 'guard.php';
include '../config.php';

$rider_id = $_SESSION['user_id'];
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('m');
$selected_year  = isset($_GET['year'])  ? $_GET['year']  : date('Y');
$search         = isset($_GET['search']) ? $_GET['search'] : '';

$months = ['01'=>'January','02'=>'February','03'=>'March','04'=>'April',
           '05'=>'May','06'=>'June','07'=>'July','08'=>'August',
           '09'=>'September','10'=>'October','11'=>'November','12'=>'December'];
$years = [];
for ($y = date('Y'); $y >= date('Y') - 2; $y--) { $years[] = $y; }

// Monthly deliveries
$query = "SELECT d.id, d.status, d.delivered_at, d.notes,
          o.delivery_address, o.total_amount,
          u.first_name, u.last_name, u.phone
          FROM deliveries d
          JOIN orders o ON d.order_id = o.id
          JOIN users u ON o.user_id = u.id
          WHERE d.rider_id = $rider_id
          AND MONTH(o.created_at) = $selected_month
          AND YEAR(o.created_at) = $selected_year";
if ($search != '') {
    $query .= " AND (u.first_name LIKE '%$search%' OR u.last_name LIKE '%$search%')";
}
$query .= " ORDER BY d.id DESC";
$deliveries = $conn->query($query);

// Count completed this month
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM deliveries d JOIN orders o ON d.order_id = o.id WHERE d.rider_id = ? AND d.status = 'delivered' AND MONTH(o.created_at) = ? AND YEAR(o.created_at) = ?");
$stmt->bind_param("iii", $rider_id, $selected_month, $selected_year);
$stmt->execute();
$completed_count = $stmt->get_result()->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reports - Rider</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        @media print { .no-print { display:none; } }
    </style>
</head>
<body class="page-dashboard">
<div class="container">

    <div class="page-header no-print">
        <h2>My Delivery Reports</h2>
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

    <!-- SUMMARY -->
    <div class="card">
        <h3>Summary — <?php echo $months[$selected_month] . ' ' . $selected_year; ?></h3>
        <table>
            <tr>
                <td><strong>Completed Deliveries This Month:</strong></td>
                <td class="status-ok"><strong><?php echo $completed_count; ?></strong></td>
            </tr>
        </table>
    </div>

    <!-- DELIVERIES REPORT -->
    <div class="card">
        <h3>Deliveries — <?php echo $months[$selected_month] . ' ' . $selected_year; ?></h3>
        <?php if ($deliveries->num_rows > 0) { ?>
        <table>
            <tr>
                <th>Customer</th>
                <th>Phone</th>
                <th>Delivery Address</th>
                <th>Amount (KSh)</th>
                <th>Status</th>
                <th>Notes</th>
                <th>Delivered At</th>
            </tr>
            <?php while ($d = $deliveries->fetch_assoc()) {
                $status = $d['status'];
                $class = 'status-gray';
                if ($status == 'delivered') $class = 'status-ok';
                elseif ($status == 'out_for_delivery') $class = 'status-medium';
            ?>
            <tr>
                <td><?php echo $d['first_name'] . ' ' . $d['last_name']; ?></td>
                <td><?php echo $d['phone']; ?></td>
                <td><?php echo $d['delivery_address']; ?></td>
                <td>KSh <?php echo number_format($d['total_amount'], 2); ?></td>
                <td><span class="<?php echo $class; ?>"><?php echo ucwords(str_replace('_', ' ', $status)); ?></span></td>
                <td><?php echo $d['notes'] ?? '—'; ?></td>
                <td><?php echo $d['delivered_at'] ? date('d M Y', strtotime($d['delivered_at'])) : '—'; ?></td>
            </tr>
            <?php } ?>
        </table>
        <?php } else { ?>
            <p style="color:#777;">No deliveries for this period.</p>
        <?php } ?>
        <br>
        <button onclick="window.print();" class="btn btn-gray no-print">Print</button>
    </div>

</div>
</body>
</html>