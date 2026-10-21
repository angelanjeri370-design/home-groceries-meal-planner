<?php
include "config.php";
include "locations.php";

$error   = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstname = $_POST['firstname'];
    $lastname  = $_POST['lastname'];
    $username  = $_POST['username'];
    $email     = $_POST['email'];
    $password  = $_POST['password'];
    $confirm   = $_POST['confirmpassword'];
    $role      = $_POST['role'];
    $location  = $_POST['location'];
    $phone     = $_POST['phone'];

    if ($password == $confirm) {
        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "Username already taken. Please choose another.";
        } else {
            $status = ($role == 'supplier' || $role == 'rider') ? 'pending' : 'active';

            $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, username, email, password, role, location, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssss", $firstname, $lastname, $username, $email, $password, $role, $location, $phone, $status);

            if ($stmt->execute()) {
                if ($status == 'pending') {
                    $success = "Registration successful! Your account is pending admin approval.";
                } else {
                    $success = "Registration successful! You can now log in.";
                }
            } else {
                $error = "Error: " . $conn->error;
            }
        }
    } else {
        $error = "Passwords do not match.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="page-register">

<div class="login-wrapper">
    <div class="login-box" style="max-width:500px;">
        <h2>Create an Account</h2>

        <?php if ($error != "") { ?>
            <div class="msg-error"><?php echo $error; ?></div>
        <?php } ?>
        <?php if ($success != "") { ?>
            <div class="msg-success"><?php echo $success; ?></div>
        <?php } ?>

        <form action="" method="POST">
            <label>First Name:</label>
            <input type="text" name="firstname" required>

            <label>Last Name:</label>
            <input type="text" name="lastname">

            <label>Username:</label>
            <input type="text" name="username" required>

            <label>Email:</label>
            <input type="email" name="email" required>

            <label>Phone Number:</label>
            <input type="text" name="phone" placeholder="e.g. 0712345678" required>

            <label>Your Area / Location:</label>
            <select name="location" required>
                <option value="">-- Select your area --</option>
                <?php foreach ($locations as $loc) {
                    if (str_starts_with($loc, '--')) { ?>
                        <option disabled><?php echo $loc; ?></option>
                    <?php } else { ?>
                        <option value="<?php echo $loc; ?>"><?php echo $loc; ?></option>
                    <?php }
                } ?>
            </select>

            <label>Registering as:</label>
            <select name="role" required id="role-select" onchange="showRoleNote()">
                <option value="user">Household User</option>
                <option value="supplier">Supplier</option>
                <option value="rider">Bodaboda Rider</option>
            </select>

            <p id="role-note" style="font-size:13px; color:#f57f17; display:none; margin-bottom:10px;">
                Supplier and Rider accounts require admin approval before you can log in.
            </p>

            <label>Password:</label>
            <input type="password" name="password" autocomplete="off" required>

            <label>Confirm Password:</label>
            <input type="password" name="confirmpassword" required>

            <button type="submit" class="btn btn-green" style="width:100%;">Register</button>
        </form>

        <p>Already have an account? <a href="login.php">Login here</a></p>
    </div>
</div>

<script>
function showRoleNote() {
    var role = document.getElementById('role-select').value;
    var note = document.getElementById('role-note');
    note.style.display = (role === 'supplier' || role === 'rider') ? 'block' : 'none';
}
</script>

</body>
</html>