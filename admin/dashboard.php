<?php
include 'guard.php';
include '../config.php';

// Count statistics for dashboard
$total_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'")->fetch_assoc()['count'];
$total_suppliers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'supplier' AND status = 'active'")->fetch_assoc()['count'];
$total_riders = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'rider' AND status = 'active'")->fetch_assoc()['count'];
$pending_approvals = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'pending'")->fetch_assoc()['count'];
$total_orders = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'];
$pending_orders = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending_payment'")->fetch_assoc()['count'];
$total_deliveries = $conn->query("SELECT COUNT(*) as count FROM deliveries")->fetch_assoc()['count'];
$completed_deliveries = $conn->query("SELECT COUNT(*) as count FROM deliveries WHERE status = 'delivered'")->fetch_assoc()['count'];

// Recent orders
$recent_orders = $conn->query("SELECT o.id, o.total_amount, o.status, o.created_at, u.first_name, u.last_name FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 5");

// Pending approvals list
$pending_users = $conn->query("SELECT id, first_name, last_name, role, location FROM users WHERE status = 'pending' ORDER BY id DESC LIMIT 5");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
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
        <h2>Admin Dashboard</h2>
        <p>Welcome, <?php echo $_SESSION['full_name']; ?>. Here is an overview of the system.</p>
    </div>

    <!-- STATS GRID -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-number"><?php echo $total_users; ?></span>
            <div class="stat-label">Registered Users</div>
        </div>
        <div class="stat-card success">
            <span class="stat-number"><?php echo $total_suppliers; ?></span>
            <div class="stat-label">Active Suppliers</div>
        </div>
        <div class="stat-card success">
            <span class="stat-number"><?php echo $total_riders; ?></span>
            <div class="stat-label">Active Riders</div>
        </div>
        <div class="stat-card warning">
            <span class="stat-number"><?php echo $pending_approvals; ?></span>
            <div class="stat-label">Pending Approvals</div>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?php echo $total_orders; ?></span>
            <div class="stat-label">Total Orders</div>
        </div>
        <div class="stat-card danger">
            <span class="stat-number"><?php echo $pending_orders; ?></span>
            <div class="stat-label">Pending Orders</div>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?php echo $total_deliveries; ?></span>
            <div class="stat-label">Total Deliveries</div>
        </div>
        <div class="stat-card success">
            <span class="stat-number"><?php echo $completed_deliveries; ?></span>
            <div class="stat-label">Completed Deliveries</div>
        </div>
    </div>

    <!-- PENDING APPROVALS ALERT -->
    <?php if ($pending_approvals > 0) { ?>
    <div class="msg-warning">
        You have <?php echo $pending_approvals; ?> account(s) waiting for approval.
        <a href="manage_users.php" style="font-weight:bold; margin-left:10px;">Review Now</a>
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
                <th>Amount (KSh)</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
            <?php while ($order = $recent_orders->fetch_assoc()) { ?>
            <tr>
                <td>#<?php echo $order['id']; ?></td>
                <td><?php echo $order['first_name'] . ' ' . $order['last_name']; ?></td>
                <td>KSh <?php echo number_format($order['total_amount'], 2); ?></td>
                <td>
                    <?php
                    $status = $order['status'];
                    $class = 'status-gray';
                    if ($status == 'delivered') $class = 'status-ok';
                    elseif ($status == 'pending_payment') $class = 'status-low';
                    elseif ($status == 'confirmed') $class = 'status-medium';
                    ?>
                    <span class="<?php echo $class; ?>"><?php echo ucwords(str_replace('_', ' ', $status)); ?></span>
                </td>
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

    <!-- PENDING APPROVALS PREVIEW -->
    <?php if ($pending_users->num_rows > 0) { ?>
    <div class="card">
        <h3>Pending Account Approvals</h3>
        <table>
            <tr>
                <th>Name</th>
                <th>Role</th>
                <th>Location</th>
                <th>Action</th>
            </tr>
            <?php while ($u = $pending_users->fetch_assoc()) { ?>
            <tr>
                <td><?php echo $u['first_name'] . ' ' . $u['last_name']; ?></td>
                <td><?php echo ucfirst($u['role']); ?></td>
                <td><?php echo $u['location']; ?></td>
                <td><a href="manage_users.php" class="btn btn-green" style="padding:5px 12px; font-size:13px;">Review</a></td>
            </tr>
            <?php } ?>
        </table>
    </div>
    <?php } ?>

    <!-- QUICK LINKS -->
    <div class="card">
        <h3>Quick Links</h3>
        <div class="quick-links">
            <a href="manage_users.php">Manage Users</a>
            <a href="orders.php">All Orders</a>
            <a href="deliveries.php">Deliveries</a>
            <a href="reports.php">Reports</a>
            <a href="../logout.php" class="logout">Logout</a>
        </div>
    </div>

</div>
</body>
</html>