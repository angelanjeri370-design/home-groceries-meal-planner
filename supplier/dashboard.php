<?php
include 'guard.php';
include '../config.php';

$supplier_id = $_SESSION['user_id'];
$location = $_SESSION['location'];

// Statistics
$total_orders = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE supplier_id = ?");
$total_orders->bind_param("i", $supplier_id);
$total_orders->execute();
$total_orders = $total_orders->get_result()->fetch_assoc()['count'];

$pending_orders = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE supplier_id = ? AND status = 'pending_payment'");
$pending_orders->bind_param("i", $supplier_id);
$pending_orders->execute();
$pending_orders = $pending_orders->get_result()->fetch_assoc()['count'];

$confirmed_orders = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE supplier_id = ? AND status = 'confirmed'");
$confirmed_orders->bind_param("i", $supplier_id);
$confirmed_orders->execute();
$confirmed_orders = $confirmed_orders->get_result()->fetch_assoc()['count'];

$completed_orders = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE supplier_id = ? AND status = 'delivered'");
$completed_orders->bind_param("i", $supplier_id);
$completed_orders->execute();
$completed_orders = $completed_orders->get_result()->fetch_assoc()['count'];

// Recent orders
$stmt = $conn->prepare("SELECT o.id, o.total_amount, o.status, o.created_at, o.delivery_address,
                        u.first_name, u.last_name, u.phone
                        FROM orders o
                        JOIN users u ON o.user_id = u.id
                        WHERE o.supplier_id = ?
                        ORDER BY o.created_at DESC LIMIT 5");
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$recent_orders = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Supplier Dashboard</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 5px solid #3a5068;
        }
        .stat-card.warning { border-left-color: #f57f17; }
        .stat-card.success { border-left-color: #2e7d32; }
        .stat-card.danger  { border-left-color: #c62828; }
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #3a5068;
            display: block;
        }
        .stat-label {
            font-size: 13px;
            color: #777;
            margin-top: 5px;
        }
    </style>
</head>
<body class="page-dashboard">
<div class="container">

    <div class="welcome-box">
        <h2>Welcome, <?php echo $_SESSION['full_name']; ?>!</h2>
        <p>Supplier Dashboard &mdash; Location: <?php echo $location; ?></p>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-number"><?php echo $total_orders; ?></span>
            <div class="stat-label">Total Orders</div>
        </div>
        <div class="stat-card danger">
            <span class="stat-number"><?php echo $pending_orders; ?></span>
            <div class="stat-label">Pending Payment</div>
        </div>
        <div class="stat-card warning">
            <span class="stat-number"><?php echo $confirmed_orders; ?></span>
            <div class="stat-label">Confirmed Orders</div>
        </div>
        <div class="stat-card success">
            <span class="stat-number"><?php echo $completed_orders; ?></span>
            <div class="stat-label">Completed Orders</div>
        </div>
    </div>

    <?php if ($pending_orders > 0) { ?>
        <div class="msg-warning">
            You have <?php echo $pending_orders; ?> order(s) awaiting payment confirmation.
            <a href="orders.php" style="font-weight:bold; margin-left:10px;">View Orders</a>
        </div>
    <?php } ?>

    <!-- RECENT ORDERS -->
    <div class="card">
        <h3>Recent Orders</h3>
        <?php if ($recent_orders->num_rows > 0) { ?>
        <table>
            <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Phone</th>
                <th>Amount (KSh)</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
            <?php while ($order = $recent_orders->fetch_assoc()) {
                $status = $order['status'];
                $class = 'status-gray';
                if ($status == 'delivered') $class = 'status-ok';
                elseif ($status == 'pending_payment') $class = 'status-low';
                elseif ($status == 'confirmed') $class = 'status-medium';
            ?>
            <tr>
                <td>#<?php echo $order['id']; ?></td>
                <td><?php echo $order['first_name'] . ' ' . $order['last_name']; ?></td>
                <td><?php echo $order['phone']; ?></td>
                <td>KSh <?php echo number_format($order['total_amount'], 2); ?></td>
                <td><span class="<?php echo $class; ?>"><?php echo ucwords(str_replace('_', ' ', $status)); ?></span></td>
                <td><?php echo date('d M Y', strtotime($order['created_at'])); ?></td>
            </tr>
            <?php } ?>
        </table>
        <?php } else { ?>
            <p style="color:#777;">No orders yet.</p>
        <?php } ?>
        <br>
        <a href="orders.php" class="btn btn-green">View All Orders</a>
    </div>

    <!-- QUICK LINKS -->
    <div class="card">
        <h3>Quick Links</h3>
        <div class="quick-links">
            <a href="orders.php">Manage Orders</a>
            <a href="reports.php">My Reports</a>
            <a href="../logout.php" class="logout">Logout</a>
        </div>
    </div>

</div>
</body>
</html>