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
$admission_number = $_SESSION['admission_number'];

$message = "";
$message_type = "";

// Get student current balance
$balance = 0;
$result = $conn->query("SELECT remaining_balance FROM FeedingFunds WHERE student_id = $student_id");
if ($result && $row = $result->fetch_assoc()) {
    $balance = $row['remaining_balance'];
}

// Get available meals
$meals = $conn->query("SELECT * FROM Meals WHERE available = 1 ORDER BY meal_id DESC");

// Handle meal order
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['order_meal'])) {
    $meal_id = intval($_POST['meal_id']);
    $quantity = intval($_POST['quantity']);
    
    // Get meal price
    $meal_result = $conn->query("SELECT meal_name, price FROM Meals WHERE meal_id = $meal_id");
    if ($meal_result && $meal_row = $meal_result->fetch_assoc()) {
        $total_amount = $meal_row['price'] * $quantity;
        
        if ($balance >= $total_amount) {
            // Create transaction
            $stmt = $conn->prepare("INSERT INTO Transactions (student_id, meal_id, transaction_date, amount) VALUES (?, ?, NOW(), ?)");
            if ($stmt) {
                $stmt->bind_param("iid", $student_id, $meal_id, $total_amount);
                if ($stmt->execute()) {
                    // Deduct from balance
                    $new_balance = $balance - $total_amount;
                    $update_stmt = $conn->prepare("UPDATE FeedingFunds SET remaining_balance = ? WHERE student_id = ?");
                    if ($update_stmt) {
                        $update_stmt->bind_param("di", $new_balance, $student_id);
                        $update_stmt->execute();
                        $update_stmt->close();
                    }
                    
                    $balance = $new_balance;
                    $message = "✓ Order placed successfully! " . $meal_row['meal_name'] . " x" . $quantity . " | Amount: KSh " . number_format($total_amount, 2);
                    $message_type = "success";
                } else {
                    $message = "✗ Error placing order.";
                    $message_type = "error";
                }
                $stmt->close();
            }
        } else {
            $message = "✗ Insufficient balance! Available: KSh " . number_format($balance, 2) . " | Required: KSh " . number_format($total_amount, 2);
            $message_type = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - AIU Cafeteria System</title>
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
            overflow-x: hidden;
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

        .nav-brand span {
            font-size: 24px;
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
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .nav-menu a:hover {
            color: #667eea;
        }

        .logout-btn {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }

        /* Main Content */
        .main-content {
            padding: 30px;
        }

        /* Header */
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-info h1 {
            font-size: 24px;
            color: #1e1e2e;
            margin-bottom: 5px;
        }

        .header-info p {
            color: #999;
            font-size: 13px;
        }

        /* Balance Card */
        .balance-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .balance-label {
            font-size: 13px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .balance-amount {
            font-size: 40px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .balance-status {
            font-size: 12px;
            opacity: 0.8;
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

        /* Meals Grid */
        .meals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .meal-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .meal-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
        }

        .meal-image {
            width: 100%;
            height: 150px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            overflow: hidden;
            position: relative;
        }

        .meal-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .meal-info {
            padding: 15px;
        }

        .meal-name {
            font-size: 16px;
            font-weight: 700;
            color: #1e1e2e;
            margin-bottom: 8px;
        }

        .meal-description {
            font-size: 12px;
            color: #999;
            margin-bottom: 12px;
            line-height: 1.4;
        }

        .meal-price {
            font-size: 20px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 12px;
        }

        .meal-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .quantity-input {
            width: 50px;
            padding: 6px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 13px;
            text-align: center;
        }

        .order-btn {
            flex: 1;
            padding: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .order-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .order-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        /* Section Title */
        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: white;
            margin-bottom: 20px;
        }

        /* Quick Action Buttons */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .action-btn {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .action-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
        }

        .action-icon {
            font-size: 30px;
        }

        .action-text {
            font-size: 13px;
            font-weight: 600;
            color: #1e1e2e;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            animation: fadeIn 0.3s ease;
            overflow-y: auto;
        }

        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
        }

        .modal-header h2 {
            color: #1e1e2e;
            font-size: 20px;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
            transition: all 0.3s ease;
        }

        .close-btn:hover {
            color: #e74c3c;
            transform: rotate(90deg);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #1e1e2e;
            font-weight: 600;
            font-size: 13px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 13px;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 10px rgba(102, 126, 234, 0.2);
        }

        .submit-btn {
            width: 100%;
            padding: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
            }

            .meals-grid {
                grid-template-columns: 1fr;
            }

            .nav-menu {
                gap: 10px;
            }

            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            color: #999;
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
            <li><a href="student_settings.php">⚙️ Settings</a></li>
            <li><button class="logout-btn" onclick="logout()">🚪 Logout</button></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="header-info">
                <h1>Welcome, <?php echo htmlspecialchars($student_name); ?>!</h1>
                <p>Admission No: <?php echo htmlspecialchars($admission_number); ?></p>
            </div>
            <div class="balance-card" style="flex: 0 0 250px;">
                <div class="balance-label">💰 Current Balance</div>
                <div class="balance-amount">KSh <?php echo number_format($balance, 2); ?></div>
                <div class="balance-status"><?php echo ($balance > 0) ? '✓ Sufficient funds' : '⚠️ Low balance'; ?></div>
            </div>
        </div>

        <!-- Message -->
        <?php if (!empty($message)): ?>
        <div class="message <?php echo $message_type; ?>">
            <span><?php echo $message; ?></span>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="section-title">Quick Actions</div>
        <div class="quick-actions">
            <a href="student_orders.php" class="action-btn">
                <div class="action-icon">📋</div>
                <div class="action-text">My Orders</div>
            </a>
            <a href="student_settings.php" class="action-btn">
                <div class="action-icon">🔑</div>
                <div class="action-text">Change Password</div>
            </a>
            <button class="action-btn" onclick="printReport()">
                <div class="action-icon">🖨️</div>
                <div class="action-text">Print Report</div>
            </button>
            <a href="student_login.php?logout=true" class="action-btn" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white;">
                <div class="action-icon">🚪</div>
                <div class="action-text">Logout</div>
            </a>
        </div>

        <!-- Available Meals -->
        <div class="section-title">🍴 Available Meals</div>
        <?php if ($meals && $meals->num_rows > 0): ?>
        <div class="meals-grid">
            <?php while ($meal = $meals->fetch_assoc()): ?>
            <div class="meal-card">
                <div class="meal-image">
                    <?php if ($meal['image_path'] && file_exists($meal['image_path'])): ?>
                        <img src="<?php echo htmlspecialchars($meal['image_path']); ?>" alt="<?php echo htmlspecialchars($meal['meal_name']); ?>">
                    <?php else: ?>
                        <span>🍽️</span>
                    <?php endif; ?>
                </div>

                <div class="meal-info">
                    <div class="meal-name"><?php echo htmlspecialchars($meal['meal_name']); ?></div>
                    <div class="meal-description"><?php echo htmlspecialchars(substr($meal['description'], 0, 60)); ?></div>
                    <div class="meal-price">KSh <?php echo number_format($meal['price'], 2); ?></div>

                    <form method="POST" class="meal-actions">
                        <input type="hidden" name="meal_id" value="<?php echo $meal['meal_id']; ?>">
                        <input type="number" name="quantity" class="quantity-input" value="1" min="1" required>
                        <button type="submit" name="order_meal" class="order-btn" <?php echo ($balance < $meal['price']) ? 'disabled' : ''; ?>>
                            Order
                        </button>
                    </form>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <p>No meals available at the moment</p>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'student_logout.php';
            }
        }

        function printReport() {
            window.open('student_print_report.php', '_blank');
        }

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
