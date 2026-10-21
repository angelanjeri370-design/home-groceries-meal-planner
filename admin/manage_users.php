<?php
include 'guard.php';
include '../config.php';

$message = "";

// Approve a user
if (isset($_POST['approve_user'])) {
    $user_id = $_POST['user_id'];
    $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $message = "Account approved successfully.";
}

// Reject/delete a user
if (isset($_POST['reject_user'])) {
    $user_id = $_POST['user_id'];
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $message = "Account rejected and removed.";
}

// Deactivate an active user
if (isset($_POST['deactivate_user'])) {
    $user_id = $_POST['user_id'];
    $stmt = $conn->prepare("UPDATE users SET status = 'pending' WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $message = "Account deactivated.";
}

// Search
$search = isset($_GET['search']) ? $_GET['search'] : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';

$query = "SELECT * FROM users WHERE role != 'admin'";
if ($search != '') {
    $query .= " AND (first_name LIKE '%$search%' OR last_name LIKE '%$search%' OR username LIKE '%$search%' OR location LIKE '%$search%')";
}
if ($role_filter != '') {
    $query .= " AND role = '$role_filter'";
}
$query .= " ORDER BY status ASC, id DESC";
$users = $conn->query($query);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Users - Admin</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body class="page-dashboard">
<div class="container">

    <div class="page-header">
        <h2>Manage Users</h2>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>

    <?php if ($message != "") { ?>
        <div class="msg-success"><?php echo $message; ?></div>
    <?php } ?>

    <!-- SEARCH AND FILTER -->
    <div class="card">
        <h3>Search and Filter</h3>
        <form method="GET" action="manage_users.php" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
            <div>
                <label>Search:</label>
                <input type="text" name="search" placeholder="Name, username or location" value="<?php echo $search; ?>" style="width:250px;">
            </div>
            <div>
                <label>Role:</label>
                <select name="role">
                    <option value="">All Roles</option>
                    <option value="user" <?php if ($role_filter == 'user') echo 'selected'; ?>>Household Users</option>
                    <option value="supplier" <?php if ($role_filter == 'supplier') echo 'selected'; ?>>Suppliers</option>
                    <option value="rider" <?php if ($role_filter == 'rider') echo 'selected'; ?>>Riders</option>
                </select>
            </div>
            <button type="submit" class="btn btn-green">Search</button>
            <a href="manage_users.php" class="btn btn-gray">Clear</a>
        </form>
    </div>

    <!-- USERS TABLE -->
    <div class="card">
        <h3>All Users</h3>
        <?php if ($users->num_rows > 0) { ?>
        <table>
            <tr>
                <th>Name</th>
                <th>Username</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Role</th>
                <th>Location</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            <?php while ($u = $users->fetch_assoc()) { ?>
            <tr>
                <td><?php echo $u['first_name'] . ' ' . $u['last_name']; ?></td>
                <td><?php echo $u['username']; ?></td>
                <td><?php echo $u['email']; ?></td>
                <td><?php echo $u['phone']; ?></td>
                <td><?php echo ucfirst($u['role']); ?></td>
                <td><?php echo $u['location']; ?></td>
                <td>
                    <?php if ($u['status'] == 'active') { ?>
                        <span class="status-ok">Active</span>
                    <?php } else { ?>
                        <span class="status-low">Pending</span>
                    <?php } ?>
                </td>
                <td style="display:flex; gap:5px; flex-wrap:wrap;">
                    <?php if ($u['status'] == 'pending') { ?>
                        <form method="POST">
                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                            <button type="submit" name="approve_user" class="btn btn-green" style="padding:5px 10px; font-size:12px;">Approve</button>
                        </form>
                        <form method="POST" onsubmit="return confirm('Reject and delete this account?');">
                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                            <button type="submit" name="reject_user" class="btn btn-red" style="padding:5px 10px; font-size:12px;">Reject</button>
                        </form>
                    <?php } else { ?>
                        <form method="POST" onsubmit="return confirm('Deactivate this account?');">
                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                            <button type="submit" name="deactivate_user" class="btn btn-gray" style="padding:5px 10px; font-size:12px;">Deactivate</button>
                        </form>
                    <?php } ?>
                </td>
            </tr>
            <?php } ?>
        </table>

        <!-- PRINT BUTTON -->
        <br>
        <button onclick="window.print();" class="btn btn-gray">Print This Report</button>

        <?php } else { ?>
            <p style="color:#777;">No users found.</p>
        <?php } ?>
    </div>

</div>
</body>
</html>