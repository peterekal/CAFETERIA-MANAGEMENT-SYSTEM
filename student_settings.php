<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("db_connect.php");

// Redirect if not logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit();
}

// CHECK STUDENT STATUS - LOGOUT IF INACTIVE
include("check_student_status.php");

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];
$message = "";
$message_type = "";

// Handle password change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $message = "✗ New passwords do not match!";
        $message_type = "error";
    } else if (strlen($new_password) < 6) {
        $message = "✗ Password must be at least 6 characters!";
        $message_type = "error";
    } else {
        // Verify current password
        $result = $conn->query("SELECT password_hash FROM Students WHERE student_id = $student_id");
        if ($result && $row = $result->fetch_assoc()) {
            if (hash('sha256', $current_password) === $row['password_hash']) {
                $new_hash = hash('sha256', $new_password);
                $stmt = $conn->prepare("UPDATE Students SET password_hash = ? WHERE student_id = ?");
                if ($stmt) {
                    $stmt->bind_param("si", $new_hash, $student_id);
                    if ($stmt->execute()) {
                        $message = "✓ Password changed successfully!";
                        $message_type = "success";
                    } else {
                        $message = "✗ Error updating password.";
                        $message_type = "error";
                    }
                    $stmt->close();
                }
            } else {
                $message = "✗ Current password is incorrect!";
                $message_type = "error";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - AIU Cafeteria System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        /* Top Navigation */
        .top-nav {
            background: rgba(30, 30, 46, 0.95);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            font-weight: 600;
        }

        .nav-menu {
            display: flex;
            gap: 20px;
            list-style: none;
        }

        .nav-menu a {
            color: #b0b0b0;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .nav-menu a:hover,
        .nav-menu a.active {
            color: #667eea;
        }

        /* Main Content */
        .main-content {
            padding: 30px;
            max-width: 600px;
            margin: 0 auto;
        }

        .settings-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .settings-header {
            margin-bottom: 30px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 20px;
        }

        .settings-header h1 {
            font-size: 24px;
            color: #1e1e2e;
            margin-bottom: 5px;
        }

        .settings-header p {
            color: #999;
            font-size: 13px;
        }

        /* Message */
        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }

        .message.success {
            background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .message.error {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #1e1e2e;
            font-weight: 600;
            font-size: 13px;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 10px rgba(102, 126, 234, 0.2);
        }

        .password-strength {
            font-size: 12px;
            margin-top: 5px;
            color: #999;
        }

        .submit-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .back-btn {
            background: #95a5a6;
        }

        .back-btn:hover {
            background: #7f8c8d;
        }

        .button-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <div class="top-nav">
        <div class="nav-brand">
            <span>🍽️</span>
            <span>AIU Cafeteria System</span>
        </div>
        <ul class="nav-menu">
            <li><a href="student_dashboard.php">📊 Dashboard</a></li>
            <li><a href="student_orders.php">📋 My Orders</a></li>
            <li><a href="student_settings.php" class="active">⚙️ Settings</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="settings-container">
            <div class="settings-header">
                <h1>⚙️ Account Settings</h1>
                <p>Student: <?php echo htmlspecialchars($student_name); ?></p>
            </div>

            <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <span><?php echo $message; ?></span>
            </div>
            <?php endif; ?>

            <h3 style="color: #1e1e2e; margin-bottom: 20px; font-size: 16px;">🔐 Change Password</h3>

            <form method="POST" action="">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" placeholder="Enter current password" required>
                </div>

                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" placeholder="Enter new password (min 6 characters)" required>
                    <div class="password-strength">Passwords must be at least 6 characters long</div>
                </div>

                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" placeholder="Confirm new password" required>
                </div>

                <div class="button-group">
                    <button type="submit" name="change_password" class="submit-btn">💾 Update Password</button>
                    <a href="student_dashboard.php" style="text-decoration: none;">
                        <button type="button" class="submit-btn back-btn">← Back</button>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Close message after 5 seconds
        const message = document.querySelector('.message');
        if (message) {
            setTimeout(() => {
                message.style.opacity = '0';
                message.style.transform = 'translateX(-20px)';
                setTimeout(() => message.remove(), 300);
            }, 5000);
        }
    </script>
</body>
</html>
