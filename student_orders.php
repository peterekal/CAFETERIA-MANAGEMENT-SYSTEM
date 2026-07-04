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

// Get student transactions
$transactions = $conn->query("
    SELECT 
        t.transaction_id,
        t.transaction_date,
        t.amount,
        m.meal_name,
        m.price
    FROM Transactions t
    JOIN Meals m ON t.meal_id = m.meal_id
    WHERE t.student_id = $student_id
    ORDER BY t.transaction_date DESC
    LIMIT 100
");

// Get current balance
$balance = 0;
$result = $conn->query("SELECT remaining_balance FROM FeedingFunds WHERE student_id = $student_id");
if ($result && $row = $result->fetch_assoc()) {
    $balance = $row['remaining_balance'];
}

// Get statistics
$stats_result = $conn->query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(amount) as total_spent,
        AVG(amount) as avg_per_order
    FROM Transactions
    WHERE student_id = $student_id
");
$stats = $stats_result ? $stats_result->fetch_assoc() : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - AIU Cafeteria System</title>
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
        }

        .page-header {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            font-size: 24px;
            color: #1e1e2e;
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-print {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }

        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        .btn-back {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            color: white;
            text-decoration: none;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-label {
            font-size: 12px;
            color: #999;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Table Container */
        .table-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        tbody tr:hover {
            background: #f9f9f9;
        }

        tbody tr:nth-child(even) {
            background: #f5f5f5;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-icon {
            font-size: 60px;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .header-actions {
                width: 100%;
            }

            .header-actions .btn {
                flex: 1;
            }

            table {
                font-size: 12px;
            }

            th, td {
                padding: 8px;
            }
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
            <li><a href="student_orders.php" class="active">📋 My Orders</a></li>
            <li><a href="student_settings.php">⚙️ Settings</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1>📋 My Orders</h1>
                <p style="color: #999; margin-top: 5px;">Student: <?php echo htmlspecialchars($student_name); ?></p>
            </div>
            <div class="header-actions">
                <button class="btn btn-print" onclick="printOrders()">🖨️ Print</button>
                <a href="student_dashboard.php" class="btn btn-back">← Back</a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Orders</div>
                <div class="stat-value"><?php echo $stats['total_orders'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Amount Spent</div>
                <div class="stat-value">KSh <?php echo number_format($stats['total_spent'] ?? 0, 0); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Current Balance</div>
                <div class="stat-value">KSh <?php echo number_format($balance, 0); ?></div>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="table-container">
            <?php if ($transactions && $transactions->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Date & Time</th>
                        <th>Meal Name</th>
                        <th>Price (KSh)</th>
                        <th>Amount Spent (KSh)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($trans = $transactions->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $trans['transaction_id']; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($trans['transaction_date'])); ?></td>
                        <td><?php echo htmlspecialchars($trans['meal_name']); ?></td>
                        <td><?php echo number_format($trans['price'], 2); ?></td>
                        <td style="color: #667eea; font-weight: 600;">KSh <?php echo number_format($trans['amount'], 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <h3>No Orders Yet</h3>
                <p>You haven't placed any orders. Go to dashboard and order a meal!</p>
                <a href="student_dashboard.php" style="color: #667eea; text-decoration: none; font-weight: 600; margin-top: 10px; display: inline-block;">→ Order Now</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function printOrders() {
            window.print();
        }
    </script>
</body>
</html>
