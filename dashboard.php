<?php
require_once __DIR__ . '/session_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

$user_id   = $_SESSION['user_id'];
$full_name = trim($_SESSION['full_name']);

// Auto-deduct quantities based on days passed
$stmt = $conn->prepare("SELECT id, quantity, daily_consumption, last_updated FROM groceries WHERE user_id = ? AND daily_consumption > 0");
if ($stmt === false) { die("Error: " . $conn->error); }
$stmt->bind_param("i", $user_id);
$stmt->execute();
$items_to_update = $stmt->get_result();

while ($row = $items_to_update->fetch_assoc()) {
    if ($row['last_updated'] != null) {
        $last_updated = new DateTime($row['last_updated']);
        $today        = new DateTime(date('Y-m-d'));
        $days_passed  = (int)$today->diff($last_updated)->days;
        if ($days_passed > 0) {
            $consumed     = $days_passed * $row['daily_consumption'];
            $new_quantity = max(0, $row['quantity'] - $consumed);
            $update = $conn->prepare("UPDATE groceries SET quantity = ?, last_updated = CURDATE() WHERE id = ? AND user_id = ?");
            $update->bind_param("dii", $new_quantity, $row['id'], $user_id);
            $update->execute();
        }
    }
}

// Low stock items
$stmt = $conn->prepare("SELECT item_name, quantity, unit, daily_consumption FROM groceries WHERE user_id = ? AND daily_consumption > 0 AND (quantity / daily_consumption) <= 3");
if ($stmt === false) { die("Error: " . $conn->error); }
$stmt->bind_param("i", $user_id);
$stmt->execute();
$low_stock = $stmt->get_result();

// Today's meals
$today = date('Y-m-d');
$stmt2 = $conn->prepare("SELECT * FROM meal_plans WHERE user_id = ? AND plan_date = ? ORDER BY meal_type");
if ($stmt2 === false) { die("Error: " . $conn->error); }
$stmt2->bind_param("is", $user_id, $today);
$stmt2->execute();
$todays_meals = $stmt2->get_result();

// Active orders
$stmt3 = $conn->prepare("SELECT o.id, o.status, o.total_amount, o.created_at,
                         d.status as delivery_status,
                         r.first_name as rider_fname, r.last_name as rider_lname, r.phone as rider_phone
                         FROM orders o
                         LEFT JOIN deliveries d ON o.id = d.order_id
                         LEFT JOIN users r ON d.rider_id = r.id
                         WHERE o.user_id = ? AND o.status != 'delivered'
                         ORDER BY o.created_at DESC");
if ($stmt3 === false) { die("Error: " . $conn->error); }
$stmt3->bind_param("i", $user_id);
$stmt3->execute();
$active_orders = $stmt3->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="page-dashboard">
<div class="container">

    <div class="welcome-box">
        <h2>Welcome, <?php echo $full_name; ?>!</h2>
        <p>Today is <?php echo date('l, d F Y'); ?></p>
    </div>

    <!-- LOW STOCK ALERTS -->
    <div class="card">
        <h3>Low Stock Alerts</h3>
        <?php if ($low_stock->num_rows > 0) { ?>
            <div class="msg-error">The following items are running low. Please restock soon:</div>
            <ul style="margin-top:10px; padding-left:20px;">
            <?php while ($item = $low_stock->fetch_assoc()) {
                $days_left = round($item['quantity'] / $item['daily_consumption'], 1);
            ?>
                <li style="margin-bottom:6px;">
                    <strong><?php echo $item['item_name']; ?></strong> —
                    <?php echo $item['quantity']; ?> <?php echo $item['unit']; ?> left
                    <span class="status-low">(<?php echo $days_left; ?> day(s) remaining)</span>
                </li>
            <?php } ?>
            </ul>
            <br>
            <a href="shopping_list.php" class="btn btn-green">Order Now</a>
        <?php } else { ?>
            <div class="msg-success">All grocery items are sufficiently stocked.</div>
        <?php } ?>
    </div>

    <!-- ACTIVE ORDERS -->
    <?php if ($active_orders->num_rows > 0) { ?>
    <div class="card">
        <h3>Active Orders</h3>
        <table>
            <tr>
                <th>Order #</th>
                <th>Amount (KSh)</th>
                <th>Order Status</th>
                <th>Delivery Status</th>
                <th>Rider</th>
                <th>Date</th>
            </tr>
            <?php while ($o = $active_orders->fetch_assoc()) {
                $status = $o['status'];
                $class = 'status-gray';
                if ($status == 'pending_payment') $class = 'status-low';
                elseif ($status == 'confirmed') $class = 'status-medium';
                elseif ($status == 'out_for_delivery') $class = 'status-medium';

                $d_status = $o['delivery_status'] ?? 'Not assigned';
                $d_class = 'status-gray';
                if ($d_status == 'out_for_delivery') $d_class = 'status-medium';
                elseif ($d_status == 'assigned') $d_class = 'status-medium';
            ?>
            <tr>
                <td>#<?php echo $o['id']; ?></td>
                <td>KSh <?php echo number_format($o['total_amount'], 2); ?></td>
                <td><span class="<?php echo $class; ?>"><?php echo ucwords(str_replace('_', ' ', $status)); ?></span></td>
                <td><span class="<?php echo $d_class; ?>"><?php echo ucwords(str_replace('_', ' ', $d_status)); ?></span></td>
                <td>
                    <?php if ($o['rider_fname']) { ?>
                        <?php echo $o['rider_fname'] . ' ' . $o['rider_lname']; ?>
                        <br><small><?php echo $o['rider_phone']; ?></small>
                    <?php } else { ?>
                        <span class="status-gray">—</span>
                    <?php } ?>
                </td>
                <td><?php echo date('d M Y', strtotime($o['created_at'])); ?></td>
            </tr>
            <?php } ?>
        </table>
        <br>
        <a href="my_orders.php" class="btn btn-green">View All Orders</a>
    </div>
    <?php } ?>

    <!-- TODAY'S MEALS -->
    <div class="card">
        <h3>Today's Meals</h3>
        <?php if ($todays_meals->num_rows > 0) { ?>
            <ul style="padding-left:20px;">
            <?php while ($meal = $todays_meals->fetch_assoc()) { ?>
                <li style="margin-bottom:6px;">
                    <strong><?php echo $meal['meal_type']; ?>:</strong> <?php echo $meal['meal_name']; ?>
                </li>
            <?php } ?>
            </ul>
        <?php } else { ?>
            <p style="color:#777;">No meals planned for today. <a href="mealplan.php">Add meals to your plan</a>.</p>
        <?php } ?>
    </div>

    <!-- QUICK LINKS -->
    <div class="card">
        <h3>Quick Links</h3>
        <div class="quick-links">
            <a href="groceries.php">Manage Groceries</a>
            <a href="mealplan.php">Meal Planner</a>
            <a href="meal_suggestions.php">Meal Suggestions</a>
            <a href="budget.php">Budget Tracker</a>
            <a href="shopping_list.php">Shopping List</a>
            <a href="new_order.php">New Order</a>
            <a href="my_orders.php">My Orders</a>
            <a href="reports.php">Reports</a>
            <a href="logout.php" class="logout">Logout</a>
        </div>
    </div>

</div>
</body>
</html>