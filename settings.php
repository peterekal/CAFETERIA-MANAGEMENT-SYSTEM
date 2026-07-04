<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("db_connect.php");

// Redirect if not logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$success_msg = "";
$error_msg = "";

// Handle password reset form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['new_password'])) {
    $new_password = trim($_POST['new_password']);
    $admin_id = $_SESSION['admin_id'];

    if (!empty($new_password)) {
        $query = "UPDATE Admins SET password_hash=? WHERE admin_id=?";
        $stmt = $conn->prepare($query);
        $hash = hash('sha256', $new_password);
        $stmt->bind_param("si", $hash, $admin_id);

        if ($stmt->execute()) {
            $success_msg = "✅ Password updated successfully!";
        } else {
            $error_msg = "❌ Error updating password. Please try again.";
        }
    } else {
        $error_msg = "❌ Password cannot be empty.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f4f4f4; margin:0; }
        .container {
            width:350px; margin:100px auto; background:#fff; padding:20px;
            border-radius:10px; box-shadow:0 4px 8px rgba(0,0,0,0.1);
        }
        h2 { text-align:center; color:#2c3e50; }
        input[type=password] {
            width:100%; padding:10px; margin:10px 0; border:1px solid #ccc; border-radius:5px;
        }
        button {
            width:100%; padding:10px; background:#2c3e50; color:#fff; border:none; border-radius:5px;
            cursor:pointer; font-weight:bold;
        }
        button:hover { background:#34495e; }
        .msg { text-align:center; font-weight:bold; margin:10px 0; }
        .success { color:green; }
        .error { color:red; }
        .back-btn {
            display:block; text-align:center; margin-top:15px;
            background:#27ae60; color:#fff; padding:10px; border-radius:5px;
            text-decoration:none; font-weight:bold;
        }
        .back-btn:hover { background:#2ecc71; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Reset Password</h2>
        <?php if (!empty($success_msg)) echo "<p class='msg success'>$success_msg</p>"; ?>
        <?php if (!empty($error_msg)) echo "<p class='msg error'>$error_msg</p>"; ?>
        <form method="POST">
            <input type="password" name="new_password" placeholder="Enter new password" required>
            <button type="submit">Update Password</button>
        </form>
        <!-- Go Back Button -->
        <a href="admin_index.php" class="back-btn">⬅ Go Back to Dashboard</a>
    </div>
</body>
</html>
