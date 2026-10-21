<?php 
require_once __DIR__ . '/session_config.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

include 'config.php';

$user_id = $_SESSION['user_id'];
$message = "";

//Adding a new meal to the plan
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_meal'])) {
  $plan_date = $_POST['plan_date'];
  $meal_type = $_POST['meal_type'];
  $meal_name = $_POST['meal_name'];

  $sql = "INSERT INTO meal_plans (user_id, plan_date, meal_type, meal_name)
  VALUES (?, ?, ?, ?)";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("isss", $user_id, $plan_date, $meal_type, $meal_name);
  $stmt->execute();
  $message = "Meal added to plan!";
}

//delete a meal from the plan
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_meal'])) {
  $meal_id = $_POST['meal_id'];

  $stmt = $conn->prepare("DELETE FROM meal_plans WHERE id = ? AND user_id = ?");
  $stmt->bind_param("ii", $meal_id, $user_id);
  $stmt->execute();
  $message = "Meal removed.";
}

// (meal of the day reminder)
$today = date('Y-m-d');
$sql = "SELECT * FROM meal_plans WHERE user_id = ? AND plan_date = ? ORDER BY meal_type";
$stmt = $conn->prepare($sql);
if ($stmt === false) { die("Query error: ". $conn->error); }
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$todays_meals = $stmt->get_result();

// view filter: week or month
$view = isset($_GET['view']) ? $_GET['view'] : 'week';

if ($view == 'month') {
  $start = date('Y-m-01');
  $end = date('Y-m-t');
} else {
  //Current week(Monday to Sunday)
  $start = date('Y-m-d', strtotime('monday this week'));
  $end = date('Y-m-d', strtotime('sunday this week'));
}

$sql = "SELECT * FROM meal_plans WHERE user_id = ? AND plan_date BETWEEN ? AND ? ORDER BY plan_date, meal_type";
$stmt = $conn->prepare($sql);
if ($stmt === false) { die("Query error: ". $conn->error); }
$stmt->bind_param("iss", $user_id, $start, $end);
$stmt->execute();
$meal_plan = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Meal Planner</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="page-mealplan">
<div class="container">

    <div class="page-header">
        <h2>Meal Planner</h2>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>

    <?php if ($message != "") { ?>
        <div class="msg-success"><?php echo $message; ?></div>
    <?php } ?>

    <div class="card">
        <h3>Today's Meals — <?php echo date('l, d F Y'); ?></h3>
        <?php if ($todays_meals->num_rows > 0) { ?>
      <ul style="padding-left:20px;">
      <?php while ($meal = $todays_meals->fetch_assoc()) { ?>
          <li style="margin-bottom:6px;">
              <strong><?php echo $meal['meal_type']; ?>:</strong> <?php echo $meal['meal_name']; ?>
          </li>
      <?php } ?>
      </ul>
        <?php } else { ?>
            <p style="color:#777;">No meals planned for today. Add some below!</p>
        <?php } ?>
    </div>

<div class="card">
  <h3>Add a Meal to Your Plan</h3>
  <form method="POST" action="mealplan.php">
      <label>Date:</label>
      <input type="date" name="plan_date" required>

      <label>Meal Type:</label>
      <select name="meal_type" required>
        <option value="Breakfast">Breakfast</option>
        <option value="Lunch">Lunch</option>
        <option value="Dinner">Dinner</option>
        <option value="Snack">Snack</option>
      </select>

        <label>Meal Name:</label>
        <input type="text" name="meal_name" placeholder="e.g. Rice and Chicken" required>

        <button type="submit" name="add_meal" class="btn btn-green">Add Meal</button>
    </form>
    </div>

    <div class="card">
        <h3>Your Meal Plan</h3>
        <div style="margin-bottom:15px;">
            <a href="mealplan.php?view=week" class="btn <?php echo $view == 'week' ? 'btn-green' : 'btn-gray'; ?>">This Week</a>
            &nbsp;
            <a href="mealplan.php?view=month" class="btn <?php echo $view == 'month' ? 'btn-green' : 'btn-gray'; ?>">This Month</a>
        </div>

        <p style="margin-bottom:10px; font-size:14px; color:#555;">
            Showing: <?php echo date('d M', strtotime($start)); ?> to <?php echo date('d M Y', strtotime($end)); ?>
        </p>

        <?php if ($meal_plan->num_rows > 0) { ?>
<table>
    <tr>
        <th>Date</th>
        <th>Day</th>
        <th>Meal Type</th>
        <th>Meal</th>
        <th>Remove</th>
    </tr>
    <?php while ($meal = $meal_plan->fetch_assoc()) { ?>
    <tr>
        <td><?php echo $meal['plan_date']; ?></td>
        <td><?php echo date('l', strtotime($meal['plan_date'])); ?></td>
        <td><?php echo $meal['meal_type']; ?></td>
        <td><?php echo $meal['meal_name']; ?></td>
        <td>
        <form method="POST" action="mealplan.php?view=<?php echo $view; ?>">
        <input type="hidden" name="meal_id" value="<?php echo $meal['id']; ?>">
        <button type="submit" name="delete_meal" class="btn btn-red">Remove</button>
        </form>
    </td>
    </tr>
    <?php } ?>
            </table>
        <?php } else { ?>
            <p style="color:#777;">No meals planned for this period yet.</p>
        <?php } ?>
    </div>

    <div class="card" style="text-align:center;">
        <a href="meal_suggestions.php" class="btn btn-green">Get Meal Suggestions</a>
    </div>

</div>
</body>
</html>

