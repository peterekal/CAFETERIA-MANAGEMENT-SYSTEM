<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['student_id'])) {
    header("Location: student_dashboard.php");
    exit();
}

include("db_connect.php");

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admission_number = trim($_POST['admission_number']);
    $password = $_POST['password'];

    $query = "SELECT * FROM Students WHERE admission_number = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $admission_number);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Check if student is active
        if (strtolower($row['status']) === 'inactive') {
            $error = "⚠️ Your account is inactive. Please contact the administrator.";
        } else if (hash('sha256', $password) === $row['password_hash']) {
            // Verify password
            $_SESSION['student_id'] = $row['student_id'];
            $_SESSION['student_name'] = $row['full_name'];
            $_SESSION['admission_number'] = $row['admission_number'];
            $_SESSION['status'] = $row['status'];
            header("Location: student_dashboard.php");
            exit();
        } else {
            $error = "Invalid admission number or password.";
        }
    } else {
        $error = "Invalid admission number or password.";
    }
    $stmt->close();
}

// Check for inactive logout message
$inactive_message = "";
if (isset($_GET['status']) && $_GET['status'] === 'inactive') {
    $inactive_message = "Your account has been deactivated. You no longer have access to the system.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login - AIU Cafeteria System</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 400px;
            padding: 40px;
            animation: slideIn 0.5s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            font-size: 50px;
            margin-bottom: 10px;
        }

        .login-header h1 {
            color: #1e1e2e;
            font-size: 24px;
            margin-bottom: 5px;
        }

        .login-header p {
            color: #667eea;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
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

        .login-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .error-message {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 13px;
            border-left: 4px solid #dc3545;
            animation: shake 0.3s ease;
        }

        .inactive-message {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 13px;
            border-left: 4px solid #dc3545;
            animation: slideIn 0.3s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .divider {
            text-align: center;
            color: #999;
            margin: 20px 0;
            font-size: 13px;
        }

        .admin-link {
            text-align: center;
            margin-top: 20px;
        }

        .admin-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s ease;
        }

        .admin-link a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .info-box {
            background: #f0f4ff;
            border-left: 4px solid #667eea;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 12px;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">🍽️</div>
            <h1>AIU Cafeteria</h1>
            <p>Student Portal</p>
        </div>

        <div class="info-box">
            <strong>📝 Demo Credentials:</strong><br>
            Use your admission number to login
        </div>

        <?php if (!empty($inactive_message)): ?>
        <div class="inactive-message">
            ⚠️ <?php echo $inactive_message; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
        <div class="error-message">
            ⚠️ <?php echo $error; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Admission Number</label>
                <input type="text" name="admission_number" placeholder="e.g., ADM001" required autofocus>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>

            <button type="submit" class="login-btn">🔓 Login to Portal</button>
        </form>

        <div class="divider">───────────────────</div>

        <div class="admin-link">
            👨‍💼 <a href="admin_login.php">Admin Login</a>
        </div>
    </div>
</body>
</html>
