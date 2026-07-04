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

$message = "";
$message_type = "";

// Handle Add Funds
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_funds'])) {
    $student_id = intval($_POST['student_id']);
    $amount = floatval($_POST['amount']);
    
    // Check if student exists
    $student_check = $conn->query("SELECT student_id FROM Students WHERE student_id = $student_id");
    if ($student_check && $student_check->num_rows > 0) {
        // Check if student already has a fund record
        $fund_check = $conn->query("SELECT fund_id FROM FeedingFunds WHERE student_id = $student_id");
        
        if ($fund_check && $fund_check->num_rows > 0) {
            // Update existing record
            $stmt = $conn->prepare("UPDATE FeedingFunds SET remaining_balance = remaining_balance + ? WHERE student_id = ?");
            if ($stmt) {
                $stmt->bind_param("di", $amount, $student_id);
                if ($stmt->execute()) {
                    $message = "✓ Funds added successfully! Amount: KSh " . number_format($amount, 2);
                    $message_type = "success";
                } else {
                    $message = "✗ Error adding funds: " . $stmt->error;
                    $message_type = "error";
                }
                $stmt->close();
            }
        } else {
            // Create new record
            $stmt = $conn->prepare("INSERT INTO FeedingFunds (student_id, remaining_balance) VALUES (?, ?)");
            if ($stmt) {
                $stmt->bind_param("id", $student_id, $amount);
                if ($stmt->execute()) {
                    $message = "✓ Funds created successfully! Amount: KSh " . number_format($amount, 2);
                    $message_type = "success";
                } else {
                    $message = "✗ Error creating funds: " . $stmt->error;
                    $message_type = "error";
                }
                $stmt->close();
            }
        }
    } else {
        $message = "✗ Student not found!";
        $message_type = "error";
    }
}

// Handle Update Balance
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_balance'])) {
    $fund_id = intval($_POST['fund_id']);
    $new_balance = floatval($_POST['new_balance']);
    
    $stmt = $conn->prepare("UPDATE FeedingFunds SET remaining_balance = ? WHERE fund_id = ?");
    if ($stmt) {
        $stmt->bind_param("di", $new_balance, $fund_id);
        if ($stmt->execute()) {
            $message = "✓ Balance updated successfully!";
            $message_type = "success";
        } else {
            $message = "✗ Error updating balance.";
            $message_type = "error";
        }
        $stmt->close();
    }
}

// Handle Deduct Funds
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['deduct_funds'])) {
    $fund_id = intval($_POST['fund_id']);
    $amount = floatval($_POST['deduct_amount']);
    
    // Get current balance
    $result = $conn->query("SELECT remaining_balance FROM FeedingFunds WHERE fund_id = $fund_id");
    if ($result && $row = $result->fetch_assoc()) {
        $current_balance = $row['remaining_balance'];
        if ($current_balance >= $amount) {
            $new_balance = $current_balance - $amount;
            $stmt = $conn->prepare("UPDATE FeedingFunds SET remaining_balance = ? WHERE fund_id = ?");
            if ($stmt) {
                $stmt->bind_param("di", $new_balance, $fund_id);
                if ($stmt->execute()) {
                    $message = "✓ Funds deducted successfully! Amount: KSh " . number_format($amount, 2);
                    $message_type = "success";
                } else {
                    $message = "✗ Error deducting funds.";
                    $message_type = "error";
                }
                $stmt->close();
            }
        } else {
            $message = "✗ Insufficient balance! Available: KSh " . number_format($current_balance, 2);
            $message_type = "error";
        }
    }
}

// Handle Delete Fund
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM FeedingFunds WHERE fund_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = "✓ Fund record deleted successfully!";
            $message_type = "success";
        } else {
            $message = "✗ Error deleting fund record.";
            $message_type = "error";
        }
        $stmt->close();
    }
}

// Search functionality
$search_query = "";
$funds = null;

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = $_GET['search'];
    $search_term = "%{$search_query}%";
    
    $stmt = $conn->prepare("
        SELECT f.fund_id, f.student_id, f.remaining_balance, f.created_at, s.admission_number, s.full_name, s.status
        FROM FeedingFunds f
        JOIN Students s ON f.student_id = s.student_id
        WHERE s.admission_number LIKE ? OR s.full_name LIKE ?
        ORDER BY f.created_at DESC
    ");
    if ($stmt) {
        $stmt->bind_param("ss", $search_term, $search_term);
        $stmt->execute();
        $funds = $stmt->get_result();
        $stmt->close();
    }
} else {
    $funds = $conn->query("
        SELECT f.fund_id, f.student_id, f.remaining_balance, f.created_at, s.admission_number, s.full_name, s.status
        FROM FeedingFunds f
        JOIN Students s ON f.student_id = s.student_id
        ORDER BY f.created_at DESC
    ");
}

// Get statistics
$total_funds = 0;
$result = $conn->query("SELECT SUM(remaining_balance) AS total FROM FeedingFunds");
if ($result && $row = $result->fetch_assoc()) {
    $total_funds = $row['total'] ?? 0;
}

$students_with_funds = 0;
$result = $conn->query("SELECT COUNT(*) AS count FROM FeedingFunds");
if ($result && $row = $result->fetch_assoc()) {
    $students_with_funds = $row['count'];
}

$avg_balance = 0;
if ($students_with_funds > 0) {
    $avg_balance = $total_funds / $students_with_funds;
}

// Get all students for dropdown
$all_students = $conn->query("SELECT student_id, admission_number, full_name FROM Students WHERE status = 'Active' ORDER BY full_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Funds Management - Cafeteria System</title>
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

        /* Sidebar Navigation */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 80px;
            height: 100vh;
            background: #1e1e2e;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px 0;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.3);
        }

        .sidebar:hover {
            width: 250px;
        }

        .sidebar-logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: bold;
            color: white;
            margin-bottom: 30px;
            transition: all 0.3s ease;
        }

        .sidebar:hover .sidebar-logo {
            transform: scale(1.1);
        }

        .sidebar-menu {
            list-style: none;
            width: 100%;
            flex: 1;
        }

        .sidebar-menu li {
            width: 100%;
            padding: 0;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 18px 0;
            color: #b0b0b0;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 14px;
            position: relative;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(102, 126, 234, 0.3);
            color: #667eea;
            border-right: 4px solid #667eea;
        }

        .sidebar-menu a span:first-child {
            font-size: 24px;
            min-width: 30px;
            text-align: center;
        }

        .sidebar-menu a span:last-child {
            display: none;
            margin-left: 15px;
            white-space: nowrap;
        }

        .sidebar:hover .sidebar-menu a span:last-child {
            display: inline;
        }

        /* Main Content */
        .main-content {
            margin-left: 80px;
            padding: 30px;
            transition: margin-left 0.3s ease;
        }

        .page-header {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .page-header h1 {
            font-size: 28px;
            color: #1e1e2e;
            font-weight: 600;
        }

        .add-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        /* Message Alert */
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

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            font-size: 40px;
            width: 70px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            color: white;
        }

        .stat-info h3 {
            font-size: 12px;
            color: #999;
            margin-bottom: 8px;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 1px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #1e1e2e;
        }

        /* Search Bar */
        .search-bar {
            background: rgba(255, 255, 255, 0.95);
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .search-bar input {
            flex: 1;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .search-bar input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 10px rgba(102, 126, 234, 0.2);
        }

        .search-bar button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .search-bar button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .clear-btn {
            background: #95a5a6;
        }

        .clear-btn:hover {
            background: #7f8c8d;
        }

        /* Table */
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
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        tbody tr {
            transition: all 0.3s ease;
        }

        tbody tr:hover {
            background: #f9f9f9;
        }

        tbody tr:nth-child(even) {
            background: #f5f5f5;
        }

        .student-info {
            display: flex;
            flex-direction: column;
        }

        .student-name {
            font-weight: 600;
            color: #1e1e2e;
        }

        .student-admission {
            font-size: 12px;
            color: #999;
            margin-top: 4px;
        }

        .balance {
            font-size: 16px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-active {
            background: #84fab0;
            color: #155724;
        }

        .status-inactive {
            background: #fa709a;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-add {
            background: #27ae60;
            color: white;
        }

        .btn-add:hover {
            background: #229954;
            transform: scale(1.05);
        }

        .btn-deduct {
            background: #e67e22;
            color: white;
        }

        .btn-deduct:hover {
            background: #d35400;
            transform: scale(1.05);
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .btn-delete:hover {
            background: #c0392b;
            transform: scale(1.05);
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
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
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
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
        }

        .empty-state-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }

        .empty-state h2 {
            color: #1e1e2e;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #999;
            margin-bottom: 25px;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            table {
                font-size: 12px;
            }

            th, td {
                padding: 12px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }

            .main-content {
                margin-left: 70px;
                padding: 15px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .search-bar {
                flex-direction: column;
            }

            .action-buttons {
                flex-direction: column;
            }

            .modal-content {
                width: 95%;
                margin: 30% auto;
            }

            table {
                font-size: 11px;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .stat-card {
            animation: fadeIn 0.5s ease-out forwards;
        }

        .stat-card:nth-child(1) { animation-delay: 0.05s; }
        .stat-card:nth-child(2) { animation-delay: 0.1s; }
        .stat-card:nth-child(3) { animation-delay: 0.15s; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-logo">🍽️</div>
        <ul class="sidebar-menu">
            <li><a href="admin_index.php"><span>📊</span><span>Dashboard</span></a></li>
            <li><a href="students.php"><span>👥</span><span>Students</span></a></li>
            <li><a href="meals.php"><span>🍴</span><span>Meals</span></a></li>
            <li><a href="funds.php" class="active"><span>💰</span><span>Funds</span></a></li>
            <li><a href="reports.php"><span>📋</span><span>Reports</span></a></li>
            <li><a href="settings.php"><span>⚙️</span><span>Settings</span></a></li>
            <li style="margin-top: auto;"><a href="logout.php"><span>🚪</span><span>Logout</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>💰 Student Funds Management</h1>
            <button class="add-btn" onclick="openAddModal()">➕ Add Funds</button>
        </div>

        <!-- Message Alert -->
        <?php if (!empty($message)): ?>
        <div class="message <?php echo $message_type; ?>">
            <span><?php echo $message; ?></span>
        </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-info">
                    <h3>Total Funds</h3>
                    <div class="stat-value">KSh <?php echo number_format($total_funds, 2); ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-info">
                    <h3>Students with Funds</h3>
                    <div class="stat-value"><?php echo $students_with_funds; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-info">
                    <h3>Average Balance</h3>
                    <div class="stat-value">KSh <?php echo number_format($avg_balance, 2); ?></div>
                </div>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="search-bar">
            <form method="GET" style="display: flex; gap: 10px; width: 100%;">
                <input type="text" name="search" placeholder="Search by admission number or student name..." value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit">🔍 Search</button>
                <?php if (!empty($search_query)): ?>
                <a href="funds.php" style="text-decoration: none;">
                    <button type="button" class="clear-btn">Clear</button>
                </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table -->
        <?php if ($funds && $funds->num_rows > 0): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Admission No.</th>
                        <th>Student Name</th>
                        <th>Balance (KSh)</th>
                        <th>Status</th>
                        <th>Created Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($fund = $funds->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $fund['student_id']; ?></td>
                        <td><?php echo htmlspecialchars($fund['admission_number']); ?></td>
                        <td>
                            <div class="student-info">
                                <span class="student-name"><?php echo htmlspecialchars($fund['full_name']); ?></span>
                            </div>
                        </td>
                        <td><span class="balance">KSh <?php echo number_format($fund['remaining_balance'], 2); ?></span></td>
                        <td>
                            <span class="status-badge <?php echo $fund['status'] == 'Active' ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $fund['status']; ?>
                            </span>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($fund['created_at'])); ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-add" onclick="openAddMoreModal(<?php echo $fund['fund_id']; ?>, '<?php echo htmlspecialchars($fund['full_name']); ?>')">➕ Add</button>
                                <button class="btn btn-deduct" onclick="openDeductModal(<?php echo $fund['fund_id']; ?>, '<?php echo htmlspecialchars($fund['full_name']); ?>', <?php echo $fund['remaining_balance']; ?>)">➖ Deduct</button>
                                <button class="btn btn-delete" onclick="deleteFund(<?php echo $fund['fund_id']; ?>)">🗑️ Delete</button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">💰</div>
            <h2>No Funds Found</h2>
            <p>Start by adding funds for students.</p>
            <button class="add-btn" onclick="openAddModal()">➕ Add Funds</button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Add Funds Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>➕ Add Funds for Student</h2>
                <button class="close-btn" onclick="closeModal('addModal')">×</button>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>Select Student *</label>
                    <select name="student_id" required>
                        <option value="">-- Choose Student --</option>
                        <?php 
                        if ($all_students) {
                            while ($student = $all_students->fetch_assoc()) {
                                echo "<option value='" . $student['student_id'] . "'>" . htmlspecialchars($student['admission_number'] . " - " . $student['full_name']) . "</option>";
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Amount (KSh) *</label>
                    <input type="number" name="amount" placeholder="e.g., 5000" step="0.01" min="0" required>
                </div>

                <button type="submit" name="add_funds" class="submit-btn">➕ Add Funds</button>
            </form>
        </div>
    </div>

    <!-- Add More Funds Modal -->
    <div id="addMoreModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>➕ Add More Funds</h2>
                <button class="close-btn" onclick="closeModal('addMoreModal')">×</button>
            </div>
            <form method="POST">
                <input type="hidden" name="fund_id" id="addMoreFundId">
                
                <div class="form-group">
                    <label>Student: <span id="addMoreStudent"></span></label>
                </div>

                <div class="form-group">
                    <label>Amount to Add (KSh) *</label>
                    <input type="number" name="amount" placeholder="e.g., 1000" step="0.01" min="0" required>
                </div>

                <button type="submit" name="add_funds" class="submit-btn">➕ Add Funds</button>
            </form>
        </div>
    </div>

    <!-- Deduct Funds Modal -->
    <div id="deductModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>➖ Deduct Funds</h2>
                <button class="close-btn" onclick="closeModal('deductModal')">×</button>
            </div>
            <form method="POST">
                <input type="hidden" name="fund_id" id="deductFundId">
                
                <div class="form-group">
                    <label>Student: <span id="deductStudent"></span></label>
                </div>

                <div class="form-group">
                    <label>Available Balance: <strong id="availableBalance"></strong></label>
                </div>

                <div class="form-group">
                    <label>Amount to Deduct (KSh) *</label>
                    <input type="number" name="deduct_amount" placeholder="e.g., 500" step="0.01" min="0" required>
                </div>

                <button type="submit" name="deduct_funds" class="submit-btn">➖ Deduct Funds</button>
            </form>
        </div>
    </div>

    <script>
        // Modal Functions
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }

        function openAddMoreModal(fundId, studentName) {
            document.getElementById('addMoreModal').style.display = 'block';
            document.getElementById('addMoreFundId').value = fundId;
            document.getElementById('addMoreStudent').textContent = studentName;
        }

        function openDeductModal(fundId, studentName, balance) {
            document.getElementById('deductModal').style.display = 'block';
            document.getElementById('deductFundId').value = fundId;
            document.getElementById('deductStudent').textContent = studentName;
            document.getElementById('availableBalance').textContent = 'KSh ' + parseFloat(balance).toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function deleteFund(fundId) {
            if (confirm('Are you sure you want to delete this fund record?')) {
                window.location.href = 'funds.php?delete=' + fundId;
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // Close message alert after 5 seconds
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