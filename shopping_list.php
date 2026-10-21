<?php
require_once __DIR__ . '/session_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';
include 'locations.php';

$user_id  = $_SESSION['user_id'];

// Selected location from dropdown
$selected_location = isset($_GET['location']) ? $_GET['location'] : '';

// Get low stock items
$stmt = $conn->prepare("SELECT item_name, quantity, unit, daily_consumption FROM groceries WHERE user_id = ? AND daily_consumption > 0 AND (quantity / daily_consumption) <= 3 ORDER BY (quantity / daily_consumption) ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$low_stock_items = $stmt->get_result();

$shopping_list = [];
while ($item = $low_stock_items->fetch_assoc()) {
    $days_left = round($item['quantity'] / $item['daily_consumption'], 1);
    $shopping_list[] = [
        'item_name' => $item['item_name'],
        'unit'      => $item['unit'],
        'days_left' => $days_left,
    ];
}

// Get suppliers based on selected location
$supplier_list = [];
if ($selected_location != '') {
    $stmt = $conn->prepare("SELECT id, first_name, last_name, location FROM users WHERE role = 'supplier' AND status = 'active' AND location = ?");
    $stmt->bind_param("s", $selected_location);
    $stmt->execute();
    $suppliers = $stmt->get_result();
    while ($s = $suppliers->fetch_assoc()) {
        $supplier_list[] = $s;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Shopping List</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="page-shopping">
<div class="container">

    <div class="page-header">
        <h2>Shopping List</h2>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>

    <?php if (count($shopping_list) > 0) { ?>

        <!-- LOW STOCK ITEMS -->
        <div class="card">
            <h3>Items Needing Restock</h3>
            <table>
                <tr>
                    <th>Item</th>
                    <th>Unit</th>
                    <th>Days Left</th>
                </tr>
                <?php foreach ($shopping_list as $item) { ?>
                <tr>
                    <td><?php echo $item['item_name']; ?></td>
                    <td><?php echo $item['unit']; ?></td>
                    <td class="status-low"><?php echo $item['days_left']; ?> day(s)</td>
                </tr>
                <?php } ?>
            </table>
        </div>

        <!-- STEP 1: SELECT LOCATION -->
        <div class="card">
            <h3>Step 1 — Select Supplier Location</h3>
            <p style="font-size:14px; color:#555; margin-bottom:15px;">
                Choose the area you want to order from. Suppliers in that area will appear below.
            </p>
            <form method="GET" action="shopping_list.php" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
                <div>
                    <label>Select Location:</label>
                    <select name="location" style="width:250px; padding:9px; border:1px solid #ccc; border-radius:5px;">
                        <option value="">-- Choose an area --</option>
                        <?php foreach ($locations as $loc) {
                            if (str_starts_with($loc, '--')) { ?>
                                <option disabled><?php echo $loc; ?></option>
                            <?php } else { ?>
                                <option value="<?php echo $loc; ?>" <?php if ($loc == $selected_location) echo 'selected'; ?>>
                                    <?php echo $loc; ?>
                                </option>
                            <?php }
                        } ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-green">Find Suppliers</button>
            </form>
        </div>

        <?php if ($selected_location != '') { ?>

        <!-- STEP 2: ORDER FORM -->
        <div class="card">
            <h3>Step 2 — Place Your Order</h3>
            <p style="font-size:14px; color:#555; margin-bottom:15px;">
                Select the items you want to order. The supplier will send a price quote before payment.
            </p>

            <form method="POST" action="place_order.php">

                <table>
                    <tr>
                        <th><input type="checkbox" id="select-all" onclick="toggleAll(this)"> Select All</th>
                        <th>Item</th>
                        <th>Unit</th>
                        <th>Days Left</th>
                        <th>Order Quantity</th>
                    </tr>
                    <?php foreach ($shopping_list as $index => $item) { ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="selected_items[]" value="<?php echo $index; ?>" class="item-checkbox">
                            <input type="hidden" name="item_name_<?php echo $index; ?>" value="<?php echo $item['item_name']; ?>">
                            <input type="hidden" name="item_unit_<?php echo $index; ?>" value="<?php echo $item['unit']; ?>">
                        </td>
                        <td><?php echo $item['item_name']; ?></td>
                        <td><?php echo $item['unit']; ?></td>
                        <td class="status-low"><?php echo $item['days_left']; ?> day(s)</td>
                        <td>
                            <input type="number" step="0.01" min="0.01" name="item_qty_<?php echo $index; ?>" value="1" style="width:70px; padding:4px; border:1px solid #ccc; border-radius:4px;">
                        </td>
                    </tr>
                    <?php } ?>
                </table>

                <br>

                <!-- SUPPLIER -->
                <div style="margin-bottom:15px;">
                    <label><strong>Select Supplier in <?php echo $selected_location; ?>:</strong></label><br><br>
                    <?php if (count($supplier_list) > 0) { ?>
                        <select name="supplier_id" required style="width:100%; max-width:400px; padding:9px; border:1px solid #ccc; border-radius:5px;">
                            <option value="">-- Choose a Supplier --</option>
                            <?php foreach ($supplier_list as $s) { ?>
                                <option value="<?php echo $s['id']; ?>">
                                    <?php echo $s['first_name'] . ' ' . $s['last_name'] . ' (' . $s['location'] . ')'; ?>
                                </option>
                            <?php } ?>
                        </select>
                    <?php } else { ?>
                        <div class="msg-warning">
                            No active suppliers found in <?php echo $selected_location; ?>. Try a different area.
                        </div>
                    <?php } ?>
                </div>

                <!-- DELIVERY ADDRESS -->
                <div style="margin-bottom:15px;">
                    <label><strong>Delivery Address / Landmark:</strong></label><br>
                    <input type="text" name="delivery_address" placeholder="e.g. Near Total petrol station, Westlands" required style="width:100%; max-width:500px; margin-top:8px;">
                    <p style="font-size:12px; color:#777; margin-top:5px;">Use a landmark the rider can easily find.</p>
                </div>

                <!-- PHONE -->
                <div style="margin-bottom:20px;">
                    <label><strong>Phone Number for Delivery:</strong></label><br>
                    <input type="text" name="delivery_phone" placeholder="e.g. 0712345678" required style="width:100%; max-width:300px; margin-top:8px;">
                </div>

                <input type="hidden" name="total_items" value="<?php echo count($shopping_list); ?>">

                <?php if (count($supplier_list) > 0) { ?>
                    <button type="submit" class="btn btn-green">Send Order to Supplier</button>
                    <p style="font-size:13px; color:#777; margin-top:10px;">
                        The supplier will review your items and send a price quote. You will pay after confirming the quote.
                    </p>
                <?php } ?>

            </form>
        </div>

        <?php } ?>

    <?php } else { ?>
        <div class="card">
            <div class="empty-state">
                <div class="msg-success">All your grocery items are sufficiently stocked. No shopping needed right now!</div>
            </div>
        </div>
    <?php } ?>

    <div class="card" style="text-align:center;">
        <a href="new_order.php" class="btn btn-green">Place a New Order</a>
        &nbsp;
        <a href="my_orders.php" class="btn btn-gray">View My Orders</a>
        &nbsp;
        <a href="budget.php" class="btn btn-gray">Budget Tracker</a>
    </div>

</div>

<script>
function toggleAll(source) {
    document.querySelectorAll('.item-checkbox').forEach(function(cb) {
        cb.checked = source.checked;
    });
}
</script>

</body>
</html>