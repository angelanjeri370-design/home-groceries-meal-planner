<?php
require_once __DIR__ . '/session_config.php';
include 'config.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

        if ($password == $user['password']) {

            // Check if account is pending approval
            if ($user['status'] == 'pending') {
                $error = "Your account is pending admin approval. Please wait.";
            } else {
                // Set session variables
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['location']  = $user['location'];

                // Redirect based on role
                if ($user['role'] == 'admin') {
                    header("Location: admin/dashboard.php");
                } elseif ($user['role'] == 'supplier') {
                    header("Location: supplier/dashboard.php");
                } elseif ($user['role'] == 'rider') {
                    header("Location: rider/dashboard.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit();
            }
        } else {
            $error = "Incorrect password.";
        }
    } else {
        $error = "No account found with that username.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - Home Groceries Management and Meal Planner</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="page-login">

<div class="login-wrapper">
    <div class="login-box">
        <h2>Home Groceries & Meal Planner</h2>

        <?php if ($error != "") { ?>
            <div class="msg-error"><?php echo $error; ?></div>
        <?php } ?>

        <form method="POST" action="login.php">
            <label>Username:</label>
            <input type="text" name="username" required>

            <label>Password:</label>
            <input type="password" name="password" required>

            <button type="submit" class="btn btn-green" style="width:100%;">Login</button>
        </form>

        <p>Don't have an account? <a href="register.php">Register here</a></p>
    </div>
</div>

</body>
</html>