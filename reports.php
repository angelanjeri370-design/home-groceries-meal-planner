<?php
require_once __DIR__ . '/session_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

$user_id = $_SESSION['user_id'];

// Get selected month and year, default to current month
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('m');
$selected_year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// --- REPORT 1: Monthly Spending ---
// Get all budget sessions for the selected month
$stmt = $conn->prepare("SELECT bs.id, bs.budget_amount, bs.session_date, SUM(bi.price) as total_spent
    FROM budget_sessions bs
    LEFT JOIN budget_items bi ON bs.id = bi.session_id
    WHERE bs.user_id = ? AND MONTH(bs.session_date) = ? AND YEAR(bs.session_date) = ?
    GROUP BY bs.id, bs.budget_amount, bs.session_date
    ORDER BY bs.session_date ASC");
if ($stmt === false) { die("Error: " . $conn->error); }
$stmt->bind_param("iii", $user_id, $selected_month, $selected_year);
$stmt->execute();
$spending_report = $stmt->get_result();

// Calculate overall monthly total
$monthly_total = 0;
$sessions = [];
while ($row = $spending_report->fetch_assoc()) {
    $sessions[] = $row;
    $monthly_total += $row['total_spent'];
}

// --- REPORT 2: Grocery Consumption Report ---
// Show each grocery item, its daily consumption, and estimated days remaining
$stmt = $conn->prepare("SELECT item_name, quantity, unit, daily_consumption, last_updated FROM groceries WHERE user_id = ? ORDER BY item_name ASC");
if ($stmt === false) { die("Error: " . $conn->error); }
$stmt->bind_param("i", $user_id);
$stmt->execute();
$consumption_report = $stmt->get_result();

// Build month dropdown options
$months = [
    '01' => 'January', '02' => 'February', '03' => 'March',
    '04' => 'April', '05' => 'May', '06' => 'June',
    '07' => 'July', '08' => 'August', '09' => 'September',
    '10' => 'October', '11' => 'November', '12' => 'December'
];

// Build year dropdown (last 3 years)
$years = [];
for ($y = date('Y'); $y >= date('Y') - 2; $y--) {
    $years[] = $y;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reports</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="page-reports">
<div class="container">
<div class="page-header">
<h2>Reports</h2>
<a href="dashboard.php">Back to Dashboard</a>
</div>

<div class="card">
 <h3>Filter by Month</h3>
  <form method="GET" action="reports.php" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
 <div>
    <label>Month:</label>
     <select name="month">
     <?php foreach ($months as $num => $name) { ?>
    <option value="<?php echo $num; ?>" <?php if ($num == $selected_month) echo 'selected'; ?>>
    <?php echo $name; ?>
        </option>
<?php } ?>
</select>
 </div>
    <div>
    <label>Year:</label>
    <select name="year">
     <?php foreach ($years as $year) { ?>
        <option value="<?php echo $year; ?>" <?php if ($year == $selected_year) echo 'selected'; ?>>
                <?php echo $year; ?>
    </option>
    <?php } ?>
</select>
</div>
<button type="submit" class="btn btn-green">View Report</button>
 </form>
    </div>

 <div class="card">
    <h3>Monthly Spending — <?php echo $months[$selected_month] . ' ' . $selected_year; ?></h3>

<?php if (count($sessions) > 0) { ?>
<table>
<tr>
    <th>Date</th>
    <th>Budget (KSh)</th>
    <th>Spent (KSh)</th>
    <th>Status</th>
</tr>
<?php foreach ($sessions as $session) {
    $spent = $session['total_spent'] ?? 0;
    $over = $spent > $session['budget_amount'];
?>
 <tr>
<td><?php echo date('d M Y', strtotime($session['session_date'])); ?></td>
    <td>KSh <?php echo number_format($session['budget_amount'], 2); ?></td>
    <td>KSh <?php echo number_format($spent, 2); ?></td>
    <td class="<?php echo $over ? 'status-low' : 'status-ok'; ?>">
        <?php echo $over ? 'Over Budget' : 'Within Budget'; ?>
    </td>
    </tr>
    <?php } ?>
    <tr>
     <td colspan="2"><strong>Total Spent This Month</strong></td>
     <td colspan="2"><strong>KSh <?php echo number_format($monthly_total, 2); ?></strong></td>
     </tr>
    </table>
<?php } else { ?>
    <p style="color:#777;">No shopping trips recorded for <?php echo $months[$selected_month] . ' ' . $selected_year; ?>.</p>
<?php } ?>
</div>

<div class="card">
<h3>Grocery Consumption Report</h3>
<p style="font-size:14px; color:#555; margin-bottom:15px;">Based on your current stock levels and daily consumption rates.</p>

<?php if ($consumption_report->num_rows > 0) { ?>
<table>
 <tr>
    <th>Item</th>
    <th>Quantity</th>
    <th>Unit</th>
    <th>Daily Use</th>
    <th>Days Remaining</th>
    <th>Status</th>
</tr>
<?php while ($item = $consumption_report->fetch_assoc()) {
 if ($item['daily_consumption'] > 0) {
    $days_left = round($item['quantity'] / $item['daily_consumption'], 1);
    if ($days_left <= 3) { $status = "Low"; $class = "status-low"; }
    elseif ($days_left <= 7) { $status = "Getting Low"; $class = "status-medium"; }
    else { $status = "OK"; $class = "status-ok"; }
        } else {
            $days_left = "N/A";
            $status = "No consumption set";
            $class = "status-gray";
        }
    ?>
    <tr>
        <td><?php echo $item['item_name']; ?></td>
        <td><?php echo $item['quantity']; ?></td>
        <td><?php echo $item['unit']; ?></td>
        <td><?php echo $item['daily_consumption'] > 0 ? $item['daily_consumption'] : 'Not set'; ?></td>
        <td><?php echo $days_left; ?></td>
        <td class="<?php echo $class; ?>"><?php echo $status; ?></td>
    </tr>
    <?php } ?>
</table>
<?php } else { ?>
<p style="color:#777;">No grocery items found. <a href="groceries.php">Add groceries</a> to see your consumption report.</p>
<?php } ?>
    </div>

</div>
</body>
</html>