<?php
require_once __DIR__ . '/session_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';
include 'locations.php';

$user_id  = $_SESSION['user_id'];
$message  = "";
$error    = "";

// Selected location from dropdown
$selected_location = isset($_GET['location']) ? $_GET['location'] : '';

// Handle order submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['place_order'])) {
    $supplier_id      = $_POST['supplier_id'];
    $delivery_address = $_POST['delivery_address'];
    $delivery_phone   = $_POST['delivery_phone'];
    $item_names       = $_POST['item_name'];
    $item_units       = $_POST['item_unit'];
    $item_quantities  = $_POST['item_quantity'];

    $valid_items = [];
    foreach ($item_names as $i => $name) {
        if (!empty(trim($name)) && $item_quantities[$i] > 0) {
            $valid_items[] = [
                'name'     => trim($name),
                'unit'     => $item_units[$i],
                'quantity' => floatval($item_quantities[$i]),
            ];
        }
    }

    if (empty($valid_items)) {
        $error = "Please add at least one item to your order.";
    } elseif (empty($supplier_id)) {
        $error = "Please select a supplier.";
    } elseif (empty($delivery_address)) {
        $error = "Please enter a delivery address.";
    } else {
        $stmt = $conn->prepare("INSERT INTO orders (user_id, supplier_id, total_amount, delivery_address, status) VALUES (?, ?, 0, ?, 'pending_quote')");
        $stmt->bind_param("iis", $user_id, $supplier_id, $delivery_address);
        $stmt->execute();
        $order_id = $conn->insert_id;

        foreach ($valid_items as $item) {
            $stmt = $conn->prepare("INSERT INTO order_items (order_id, item_name, quantity, unit, price) VALUES (?, ?, ?, ?, 0)");
            $stmt->bind_param("isds", $order_id, $item['name'], $item['quantity'], $item['unit']);
            $stmt->execute();
        }

        $_SESSION['delivery_phone'] = $delivery_phone;
        header("Location: my_orders.php?placed=1");
        exit();
    }
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

// Get user's grocery items as autocomplete suggestions
$stmt = $conn->prepare("SELECT item_name, unit FROM groceries WHERE user_id = ? ORDER BY item_name ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$grocery_suggestions = $stmt->get_result();
$suggestions = [];
while ($g = $grocery_suggestions->fetch_assoc()) {
    $suggestions[] = $g;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>New Order</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .item-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
        }
        .item-row input, .item-row select { margin-bottom: 0; width: 100%; }
        .remove-btn { background:#c62828; color:white; border:none; border-radius:5px; padding:7px 12px; cursor:pointer; font-size:16px; }
        .remove-btn:hover { background:#8e0000; }
    </style>
</head>
<body class="page-shopping">
<div class="container">

    <div class="page-header">
        <h2>Place New Order</h2>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>

    <?php if ($error != "") { ?>
        <div class="msg-error"><?php echo $error; ?></div>
    <?php } ?>

    <!-- STEP 1: SELECT LOCATION -->
    <div class="card">
        <h3>Step 1 — Select Supplier Location</h3>
        <p style="font-size:14px; color:#555; margin-bottom:15px;">
            Choose the area you want to order from. Suppliers available in that area will appear below.
        </p>
        <form method="GET" action="new_order.php" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
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
        <h3>Step 2 — Fill in Your Order</h3>
        <p style="font-size:14px; color:#555; margin-bottom:20px;">
            Add items you want to order. The supplier will review and send a price quote before payment.
        </p>

        <form method="POST" action="new_order.php?location=<?php echo urlencode($selected_location); ?>" id="order-form">

            <!-- ITEMS -->
            <h3 style="margin-bottom:15px;">Items</h3>
            <div class="item-row" style="font-weight:bold; font-size:13px; color:#555;">
                <div>Item Name</div>
                <div>Unit</div>
                <div>Quantity</div>
                <div></div>
            </div>

            <div id="items-container">
                <div class="item-row" id="row-0">
                    <div>
                        <input type="text" name="item_name[]" placeholder="e.g. Milk, Rice, Sugar" required list="grocery-suggestions">
                    </div>
                    <div>
                        <select name="item_unit[]">
                            <option value="kg">kg</option>
                            <option value="liters">liters</option>
                            <option value="pieces">pieces</option>
                            <option value="packets">packets</option>
                            <option value="grams">grams</option>
                            <option value="units">units</option>
                            <option value="crates">crates</option>
                            <option value="dozen">dozen</option>
                        </select>
                    </div>
                    <div>
                        <input type="number" name="item_quantity[]" step="0.01" min="0.01" value="1" required>
                    </div>
                    <div>
                        <button type="button" class="remove-btn" onclick="removeRow(0)" style="visibility:hidden;">✕</button>
                    </div>
                </div>
            </div>

            <datalist id="grocery-suggestions">
                <?php foreach ($suggestions as $s) { ?>
                    <option value="<?php echo $s['item_name']; ?>">
                <?php } ?>
            </datalist>

            <br>
            <button type="button" class="btn btn-gray" onclick="addRow()">+ Add Another Item</button>

            <hr style="margin:25px 0;">

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

            <?php if (count($supplier_list) > 0) { ?>
                <button type="submit" name="place_order" class="btn btn-green">Send Order to Supplier</button>
                <p style="font-size:13px; color:#777; margin-top:10px;">
                    The supplier will review your items and send a price quote. You will pay after confirming the quote.
                </p>
            <?php } ?>

        </form>
    </div>

    <?php } ?>

    <div class="card" style="text-align:center;">
        <a href="my_orders.php" class="btn btn-gray">View My Orders</a>
        &nbsp;
        <a href="shopping_list.php" class="btn btn-green">View Shopping List</a>
    </div>

</div>

<script>
var rowCount = 1;

function addRow() {
    var container = document.getElementById('items-container');
    var index = rowCount;
    var row = document.createElement('div');
    row.className = 'item-row';
    row.id = 'row-' + index;
    row.innerHTML = `
        <div><input type="text" name="item_name[]" placeholder="e.g. Milk, Rice, Sugar" required list="grocery-suggestions"></div>
        <div>
            <select name="item_unit[]">
                <option value="kg">kg</option>
                <option value="liters">liters</option>
                <option value="pieces">pieces</option>
                <option value="packets">packets</option>
                <option value="grams">grams</option>
                <option value="units">units</option>
                <option value="crates">crates</option>
                <option value="dozen">dozen</option>
            </select>
        </div>
        <div><input type="number" name="item_quantity[]" step="0.01" min="0.01" value="1" required></div>
        <div><button type="button" class="remove-btn" onclick="removeRow(${index})">✕</button></div>
    `;
    container.appendChild(row);
    rowCount++;
    updateRemoveButtons();
}

function removeRow(index) {
    var row = document.getElementById('row-' + index);
    if (row) row.remove();
    updateRemoveButtons();
}

function updateRemoveButtons() {
    var rows = document.querySelectorAll('.item-row');
    rows.forEach(function(row) {
        var btn = row.querySelector('.remove-btn');
        if (btn) btn.style.visibility = rows.length > 1 ? 'visible' : 'hidden';
    });
}
</script>

</body>
</html>