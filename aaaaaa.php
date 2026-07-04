<?php
session_start();
include("db_connect.php");

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin:0; background:#f4f4f4; }
        .sidebar {
            width:220px; background:#2c3e50; color:#fff; height:100vh; position:fixed; left:0; top:0;
        }
        .sidebar h2 { text-align:center; padding:20px; background:#1a252f; margin:0; }
        .sidebar a {
            display:block; padding:12px; color:#fff; text-decoration:none; border-bottom:1px solid #1a252f;
        }
        .sidebar a:hover { background:#34495e; }
        .content {
            margin-left:220px; padding:20px;
        }
        .topbar {
            background:#ecf0f1; padding:10px; text-align:right;
        }
        .topbar a {
            margin-left:15px; text-decoration:none; color:#2c3e50; font-weight:bold;
        }
        .topbar a:hover { color:#e74c3c; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h2>Admin Menu</h2>
        <a href="students.php">Students</a>
        <a href="meals.php">Meals Available</a>
        <a href="funds.php">Student Funds</a>
        <a href="reports.php">Reports</a>
    </div>

    <!-- Content -->
    <div class="content">
        <!-- Top bar with settings and logout -->
        <div class="topbar">
            <a href="settings.php">⚙️ Settings</a>
            <a href="logout.php">🚪 Logout</a>
        </div>

        <h1>Welcome, Admin</h1>
        <p>This is your dashboard. Use the sidebar to manage students, meals, funds, and reports.</p>
    </div>
</body>
</html>
