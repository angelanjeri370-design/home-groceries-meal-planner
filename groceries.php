<?php 
require_once __DIR__ . '/session_config.php';

if(!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

include 'config.php';

$user_id = $_SESSION['user_id'];
$message = "";

//AUTO DEDUCT QUANTITIES BASED ON DAYS PASSED
//For each item,calculate how many days have passed since last_updated
//and subtract the consumed amount from the stored quantity
$stmt = $conn->prepare("SELECT id, quantity, daily_consumption, last_updated FROM groceries WHERE user_id = ? AND daily_consumption > 0");
if ($stmt === false) { die("Error: " . $conn->error); }
$stmt->bind_param("i", $user_id);
$stmt->execute();
$items_to_update = $stmt->get_result();

while ($row = $items_to_update->fetch_assoc()) {
  if ($row['last_updated'] != null) {
    $last_updated = new DateTime($row['last_updated']);
    $today = new DateTime(date('Y-m-d'));
    $days_passed = (int)$today->diff($last_updated)->days;

    if ($days_passed > 0) {
      $consumed = $days_passed * $row['daily_consumption'];
      $new_quantity = max(0, $row['quantity'] - $consumed);

      //update the quantity and reset last_updated to today
      $update = $conn->prepare("UPDATE groceries SET quantity = ?, last_updated = CURDATE() WHERE id = ? AND user_id = ?");
      if ($update === false) { die("Error: " . $conn->error); }
      $update->bind_param("dii", $new_quantity, $row['id'], $user_id);
      $update->execute();
    }
  }
}

//add new grocery item
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_item'])) {
  $item_name = $_POST['item_name'];
  $quantity = $_POST['quantity'];
  $unit = $_POST['unit'];
  $daily_consumption = $_POST['daily_consumption'];

  $stmt = $conn->prepare("INSERT INTO groceries (user_id, item_name, quantity, unit, daily_consumption, last_updated) VALUES (?, ?, ?, ?, ?, CURDATE())");
  if ($stmt === false) { die("Error: " . $conn->error); }
  $stmt->bind_param("isdsd", $user_id, $item_name, $quantity, $unit, $daily_consumption);
  $stmt->execute();
  $message = "Grocery item added.";
}

  //updating quantity
  if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_item'])) {
    $item_id = $_POST['item_id'];
    $new_quantity = $_POST['new_quantity'];

    $stmt = $conn->prepare("UPDATE groceries SET quantity = ?, last_updated = CURDATE() WHERE id = ? AND user_id = ?");
    if ($stmt === false) { die("Error: " . $conn->error); }
    $stmt->bind_param("dii", $new_quantity, $item_id, $user_id);
    $stmt->execute();
    $message = "Grocery item updated.";
  }

  //Delete a grocery item
  if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_item'])) {
    $item_id = $_POST['item_id'];

    $stmt = $conn->prepare("DELETE FROM groceries WHERE id = ? AND user_id = ?");
    if ($stmt === false) { die("Error: " . $conn->error); }
    $stmt->bind_param("ii", $item_id, $user_id);
    $stmt->execute();
    $message = "Grocery item deleted.";
  }

  //fetch the groceries
  $stmt = $conn->prepare("SELECT * FROM groceries WHERE user_id = ?");
  if ($stmt === false) { die("Query failed: " . $conn->error . " - Check that the groceries table exists."); }
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $groceries = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Groceries</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="page-groceries">
<div class="container">

    <div class="page-header">
        <h2>Manage Groceries</h2>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>

    <?php if ($message != "") { ?>
        <div class="msg-success"><?php echo $message; ?></div>
    <?php } ?>

    <div class="card">
        <h3>Add New Grocery Item</h3>
        <form method="POST" action="groceries.php">
            <label>Item Name:</label>
            <input type="text" name="item_name" required>

            <label>Quantity:</label>
            <input type="number" step="0.01" name="quantity" required>

            <label>Unit (e.g. kg, liters, pieces):</label>
            <input type="text" name="unit" value="units">

            <label>Estimated Daily Consumption (optional):</label>
            <input type="number" step="0.01" name="daily_consumption">

            <button type="submit" name="add_item" class="btn btn-green">Add Item</button>
        </form>
    </div>

    <div class="card">
        <h3>Your Groceries</h3>
        <table>
            <tr>
                <th>Item</th>
                <th>Quantity</th>
                <th>Unit</th>
                <th>Daily Use</th>
                <th>Days Remaining</th>
                <th>Status</th>
                <th>Update</th>
                <th>Delete</th>
            </tr>

            <?php while ($item = $groceries->fetch_assoc()) {
                $days_remaining = "N/A";
                $status = "OK";
                $status_class = "status-ok";
                if ($item['daily_consumption'] > 0) {
                    $days_remaining = round($item['quantity'] / $item['daily_consumption'], 1);
                    if ($days_remaining <= 0) {
                        $status = "Out of stock!";
                        $status_class = "status-low";
                    } elseif ($days_remaining <= 3) {
                        $status = "Low - restock soon";
                        $status_class = "status-low";
                    } elseif ($days_remaining <= 7) {
                        $status = "Getting low";
                        $status_class = "status-medium";
                    }
                }
            ?>
            <tr>
                <td><?php echo $item['item_name']; ?></td>
                <td><?php echo $item['quantity']; ?></td>
                <td><?php echo $item['unit']; ?></td>
                <td><?php echo $item['daily_consumption'] > 0 ? $item['daily_consumption'] : 'Not set'; ?></td>
                <td><?php echo $days_remaining; ?></td>
                <td class="<?php echo $status_class; ?>"><?php echo $status; ?></td>
                <td>
                    <form method="POST" action="groceries.php" style="display:flex; gap:5px;">
                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                        <input type="number" step="0.01" name="new_quantity" placeholder="New qty" required style="width:80px; padding:5px; border:1px solid #ccc; border-radius:4px;">
                        <button type="submit" name="update_item" class="btn btn-green">Update</button>
                    </form>
                </td>
                <td>
                    <form method="POST" action="groceries.php" onsubmit="return confirm('Are you sure you want to delete this item?');">
                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                        <button type="submit" name="delete_item" class="btn btn-red">Delete</button>
                    </form>
                </td>
            </tr>
            <?php } ?>
        </table>
    </div>

</div>
</body>
</html>