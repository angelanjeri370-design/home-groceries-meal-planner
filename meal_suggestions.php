<?php 
require_once __DIR__ . '/session_config.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

include 'config.php';
$user_id = $_SESSION['user_id'];
//count how many times each meal appears in past plans
$stmt = $conn->prepare("SELECT meal_name, meal_type, COUNT(*) as times_cooked FROM meal_plans WHERE user_id = ? GROUP BY meal_name, meal_type ORDER BY times_cooked DESC LIMIT 10");
if ($stmt === false) { die("Error: ". $conn->error); }
$stmt->bind_param("i",$user_id);
$stmt->execute();
$suggestions = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Meal Suggestions</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="page-suggestions">
<div class="container">

    <div class="page-header">
        <h2>Meal Suggestions</h2>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>

    <div class="card">
        <h3>Based on Your Past Meal Plans</h3>
        <p style="font-size:14px; color:#555; margin-bottom:15px;">
            These are meals you cook most often. Use them as ideas when you are not sure what to make!
        </p>

      <?php if ($suggestions->num_rows > 0) { ?>
          <?php while ($meal = $suggestions->fetch_assoc()) { ?>
      <div class="suggestion-card">
         <div class="meal-info">
          <h3><?php echo $meal['meal_name']; ?></h3>
              <span class="meal-badge"><?php echo $meal['meal_type']; ?></span>
          </div>
          <div class="meal-count">
              <span class="number"><?php echo $meal['times_cooked']; ?></span>
              <span class="label">times cooked</span>
          </div>
              </div>
          <?php } ?>
  <?php } else { ?>
  <div class="empty-state">
      <p>No suggestions yet! Add some meals to your meal plan first.</p>
      <p>Once you have planned a few meals, your most cooked meals will appear here.</p>
      <a href="mealplan.php" class="btn btn-green">Go to Meal Planner</a>
          </div>
      <?php } ?>
    </div>

</div>
</body>
</html>