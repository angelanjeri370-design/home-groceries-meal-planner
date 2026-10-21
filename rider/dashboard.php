<?php
include 'guard.php';
include '../config.php';

$rider_id = $_SESSION['user_id'];
$location = $_SESSION['location'];

// Statistics
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM deliveries WHERE rider_id = ?");
$stmt->bind_param("i", $rider_id);
$stmt->execute();
$total_deliveries = $stmt->get_result()->fetch_assoc()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM deliveries WHERE rider_id = ? AND status = 'assigned'");
$stmt->bind_param("i", $rider_id);
$stmt->execute();
$assigned = $stmt->get_result()->fetch_assoc()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM deliveries WHERE rider_id = ? AND status = 'out_for_delivery'");
$stmt->bind_param("i", $rider_id);
$stmt->execute();
$out_for_delivery = $stmt->get_result()->fetch_assoc()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM deliveries WHERE rider_id = ? AND status = 'delivered'");
$stmt->bind_param("i", $rider_id);
$stmt->execute();
$completed = $stmt->get_result()->fetch_assoc()['count'];

// Recent deliveries
$stmt = $conn->prepare("SELECT d.id, d.status, d.assigned_at, o.delivery_address, o.total_amount,
                        u.first_name, u.last_name, u.phone
                        FROM deliveries d
                        JOIN orders o ON d.order_id = o.id
                        JOIN users u ON o.user_id = u.id
                        WHERE d.rider_id = ?
                        ORDER BY d.id DESC LIMIT 5");
$stmt->bind_param("i", $rider_id);
$stmt->execute();
$recent = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Rider Dashboard</title>
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
        <p>Rider Dashboard &mdash; Operating Area: <?php echo $location; ?></p>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-number"><?php echo $total_deliveries; ?></span>
            <div class="stat-label">Total Deliveries</div>
        </div>
        <div class="stat-card danger">
            <span class="stat-number"><?php echo $assigned; ?></span>
            <div class="stat-label">Newly Assigned</div>
        </div>
        <div class="stat-card warning">
            <span class="stat-number"><?php echo $out_for_delivery; ?></span>
            <div class="stat-label">Out for Delivery</div>
        </div>
        <div class="stat-card success">
            <span class="stat-number"><?php echo $completed; ?></span>
            <div class="stat-label">Completed</div>
        </div>
    </div>

    <?php if ($assigned > 0) { ?>
        <div class="msg-warning">
            You have <?php echo $assigned; ?> new delivery assignment(s).
            <a href="deliveries.php" style="font-weight:bold; margin-left:10px;">View Now</a>
        </div>
    <?php } ?>

    <!-- RECENT DELIVERIES -->
    <div class="card">
        <h3>Recent Deliveries</h3>
        <?php if ($recent->num_rows > 0) { ?>
        <table>
            <tr>
                <th>Customer</th>
                <th>Phone</th>
                <th>Delivery Address</th>
                <th>Amount (KSh)</th>
                <th>Status</th>
            </tr>
            <?php while ($d = $recent->fetch_assoc()) {
                $status = $d['status'];
                $class = 'status-gray';
                if ($status == 'delivered') $class = 'status-ok';
                elseif ($status == 'out_for_delivery') $class = 'status-medium';
                elseif ($status == 'assigned') $class = 'status-low';
            ?>
            <tr>
                <td><?php echo $d['first_name'] . ' ' . $d['last_name']; ?></td>
                <td><?php echo $d['phone']; ?></td>
                <td><?php echo $d['delivery_address']; ?></td>
                <td>KSh <?php echo number_format($d['total_amount'], 2); ?></td>
                <td><span class="<?php echo $class; ?>"><?php echo ucwords(str_replace('_', ' ', $status)); ?></span></td>
            </tr>
            <?php } ?>
        </table>
        <?php } else { ?>
            <p style="color:#777;">No deliveries assigned yet.</p>
        <?php } ?>
        <br>
        <a href="deliveries.php" class="btn btn-green">View All Deliveries</a>
    </div>

    <!-- QUICK LINKS -->
    <div class="card">
        <h3>Quick Links</h3>
        <div class="quick-links">
            <a href="deliveries.php">My Deliveries</a>
            <a href="reports.php">My Reports</a>
            <a href="../logout.php" class="logout">Logout</a>
        </div>
    </div>

</div>
</body>
</html>