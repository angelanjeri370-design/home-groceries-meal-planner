<?php 
require_once __DIR__ . '/session_config.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

include 'config.php';

$user_id = $_SESSION['user_id'];
$message = "";

//Start a new shopping session with a budget
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['set_budget'])) {
  $budget_amount = $_POST['budget_amount'];
  $today = date('Y-m-d');

  $stmt = $conn->prepare("INSERT INTO budget_sessions (user_id, budget_amount, session_date) VALUES (?, ?, ?)");
  if ($stmt === false) { die("Error: " . $conn->error); }
  $stmt->bind_param("ids", $user_id, $budget_amount, $today);
  $stmt->execute();

  //save the session ID so we keep adding items
  $_SESSION['budget_session_id'] = $conn->insert_id;
  $message = "Budget set! Now add your items below.";
}

//add an item to the current session
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_budget_item'])) {
  if (!isset($_SESSION['budget_session_id'])) {
    $message = "Please set a budget first.";
  } else {
    $session_id = $_SESSION['budget_session_id'];
    $item_name = $_POST['item_name'];
    $price = $_POST['price'];

    $stmt = $conn->prepare("INSERT INTO budget_items (session_id, user_id, item_name, price) VALUES (?, ?, ?, ?)");
    if ($stmt === false) { die("Error: " . $conn->error); }
    $stmt->bind_param("iisd", $session_id, $user_id, $item_name, $price);
    $stmt->execute();
    $message = "Item added.";
  }
  }
// Delete an item from the current session
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_budget_item'])) {
  $item_id = $_POST['item_id'];
  $stmt = $conn->prepare("DELETE FROM budget_items WHERE id = ? AND user_id = ?");
  if ($stmt === false) { die("Error: " . $conn->error); }
  $stmt->bind_param("ii", $item_id, $user_id);
  $stmt->execute();
  $message = "Item removed.";
}

  //Clear the session to start a new shopping trip
  if (isset($_POST['new_session'])) {
    unset($_SESSION['budget_session_id']);
    header("Location: budget.php");
    exit();
  }

  //Load current session data if one is active
  $current_budget = 0;
  $total_spent = 0;
  $items = [];

  if (isset($_SESSION['budget_session_id'])) {
    $session_id = $_SESSION['budget_session_id'];

    //Get budget amount for this session
    $stmt = $conn->prepare("SELECT budget_amount FROM budget_sessions WHERE id = ? AND user_id = ?");
    if ($stmt === false) { die("Error: " . $conn->error); }
    $stmt->bind_param("ii", $session_id, $user_id);
    $stmt->execute();
    $session_result = $stmt->get_result()->fetch_assoc();
    if ($session_result) {
      $current_budget = $session_result['budget_amount'];
    }

    //Get all items in this session
    $stmt = $conn->prepare("SELECT * FROM budget_items WHERE session_id = ? AND user_id = ?");
    if ($stmt === false) { die("Error: " . $conn->error); }
    $stmt->bind_param("ii", $session_id, $user_id);
    $stmt->execute();
    $items_result = $stmt->get_result();
    while ($row = $items_result->fetch_assoc()) {
      $items[] = $row;
      $total_spent += $row['price'];
    }
  }

  $remaining = $current_budget - $total_spent;
  $over_budget = $total_spent > $current_budget;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Budget Tracker</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="page-budget">
<div class="container">

    <div class="page-header">
        <h2>Shopping Budget Tracker</h2>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>

    <?php if ($message != "") { ?>
        <div class="msg-success"><?php echo $message; ?></div>
    <?php } ?>

    <?php if (!isset($_SESSION['budget_session_id'])) { ?>

  <div class="card">
      <h3>Start a New Shopping Trip</h3>
      <form method="POST" action="budget.php">
      <label>Enter Your Budget (KSh):</label>
      <input type="number" step="0.01" name="budget_amount" required>
      <button type="submit" name="set_budget" class="btn btn-green">Set Budget</button>
  </form>
  </div>

    <?php } else { ?>

  <div class="card">
  <h3>Current Shopping Trip</h3>
  <table>
      <tr>
        <td><strong>Budget:</strong></td>
        <td>KSh <?php echo number_format($current_budget, 2); ?></td>
      </tr>
     <tr>
      <td><strong>Total Spent:</strong></td>
      <td>KSh <?php echo number_format($total_spent, 2); ?></td>
        </tr>
        <tr>
        <td><strong>Remaining:</strong></td>
      <td class="<?php echo $over_budget ? 'status-low' : 'status-ok'; ?>">
          <?php if ($over_budget) { ?>
          Over budget by KSh <?php echo number_format(abs($remaining), 2); ?>!
      <?php } else { ?>
          KSh <?php echo number_format($remaining, 2); ?> remaining
            <?php } ?>
            </td>
        </tr>
    </table>

   <?php if ($over_budget) { ?>
     <div class="msg-error" style="margin-top:15px;">Warning: You have exceeded your budget!</div>
    <?php } else { ?>
    <div class="msg-success" style="margin-top:15px;">You are within budget.</div>
     <?php } ?>
        </div>

        <div class="card">
  <h3>Add Item</h3>
  <form method="POST" action="budget.php">
    <label>Item Name:</label>
    <input type="text" name="item_name" required>

    <label>Price (KSh):</label>
    <input type="number" step="0.01" name="price" required>

    <button type="submit" name="add_budget_item" class="btn btn-green">Add Item</button>
  </form>
</div>

<?php if (count($items) > 0) { ?>
<div class="card">
<h3>Items in This Trip</h3>
<table>
<tr>
    <th>Item</th>
    <th>Price (KSh)</th>
    <th>Remove</th>
</tr>
<?php foreach ($items as $item) { ?>
<tr>
    <td><?php echo $item['item_name']; ?></td>
    <td>KSh <?php echo number_format($item['price'], 2); ?></td>
    <td>
        <form method="POST" action="budget.php">
            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
            <button type="submit" name="delete_budget_item" class="btn btn-red">Remove</button>
        </form>
    </td>
</tr>
<?php } ?>
<tr>
    <td><strong>Total</strong></td>
    <td><strong>KSh <?php echo number_format($total_spent, 2); ?></strong></td>
    <td></td>
</tr>
</table>
</div>
<?php } ?>

<div class="card" style="text-align:center;">
  <form method="POST" action="budget.php" onsubmit="return confirm('Start a new shopping trip? This will clear the current session.');">
      <button type="submit" name="new_session" class="btn btn-gray">Start New Shopping Trip</button>
            </form>
        </div>

    <?php } ?>

    <div class="card" style="text-align:center;">
        <a href="shopping_list.php" class="btn btn-green">View Shopping List</a>
    </div>

</div>
</body>
</html>