<?php
session_start();
include("db_connect.php"); // make sure this file exists in your cafeteria folder

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Query admin table
    $query = "SELECT * FROM Admins WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Verify password
        if (hash('sha256', $password) === $row['password_hash']) {
            $_SESSION['admin_id'] = $row['admin_id'];
            header("Location: admin_index.php"); // redirect to dashboard
            exit();
        } else {
            $error = "Invalid details.";
        }
    } else {
        $error = "Invalid details.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Login</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f4f4f4; }
        .login-box {
            width:300px; margin:100px auto; padding:20px; background:#fff;
            border:1px solid #ccc; border-radius:5px; box-shadow:0 0 10px rgba(0,0,0,0.1);
        }
        h2 { text-align:center; }
        input[type=text], input[type=password] {
            width:100%; padding:10px; margin:10px 0; border:1px solid #ccc; border-radius:3px;
        }
        button {
            width:100%; padding:10px; background:#2c3e50; color:#fff; border:none; border-radius:3px;
            cursor:pointer;
        }
        button:hover { background:#34495e; }
        .error { color:red; text-align:center; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Admin Login</h2>
        <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>
