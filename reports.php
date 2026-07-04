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

// Handle date range and student filtering
$student_filter = isset($_GET['student_id']) ? intval($_GET['student_id']) : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'all';

// Get all students for dropdown
$all_students = $conn->query("SELECT student_id, admission_number, full_name FROM Students ORDER BY full_name ASC");

// Build WHERE clause
$where_clause = "WHERE 1=1";

if (!empty($student_filter)) {
    $where_clause .= " AND ff.student_id = $student_filter";
}

// Get Transaction History
$transaction_query = "
    SELECT 
        t.transaction_id,
        t.student_id,
        t.meal_id,
        t.transaction_date,
        t.amount,
        s.admission_number,
        s.full_name,
        m.meal_name,
        m.price
    FROM Transactions t
    JOIN Students s ON t.student_id = s.student_id
    JOIN Meals m ON t.meal_id = m.meal_id
    $where_clause
    AND DATE(t.transaction_date) BETWEEN '$start_date' AND '$end_date'
    ORDER BY t.transaction_date DESC
    LIMIT 1000
";

$transactions = $conn->query($transaction_query);

// Get Fund Deposits History
$deposits_query = "
    SELECT 
        ff.fund_id,
        ff.student_id,
        ff.remaining_balance,
        ff.created_at,
        ff.updated_at,
        s.admission_number,
        s.full_name,
        s.email,
        s.phone,
        s.department,
        s.year_of_study,
        s.status
    FROM FeedingFunds ff
    JOIN Students s ON ff.student_id = s.student_id
    $where_clause
    AND DATE(ff.created_at) BETWEEN '$start_date' AND '$end_date'
    ORDER BY ff.created_at DESC
    LIMIT 1000
";

$deposits = $conn->query($deposits_query);

// Get Overall Statistics
$stats_query = "
    SELECT 
        COUNT(DISTINCT t.student_id) as total_transactions_students,
        COUNT(t.transaction_id) as total_transactions,
        SUM(t.amount) as total_spent,
        AVG(t.amount) as avg_transaction,
        MIN(t.transaction_date) as first_transaction,
        MAX(t.transaction_date) as last_transaction
    FROM Transactions t
    $where_clause
    AND DATE(t.transaction_date) BETWEEN '$start_date' AND '$end_date'
";

$stats_result = $conn->query($stats_query);
$stats = $stats_result ? $stats_result->fetch_assoc() : [];

// Get Fund Statistics
$fund_stats_query = "
    SELECT 
        COUNT(DISTINCT ff.student_id) as students_with_funds,
        SUM(ff.remaining_balance) as total_funds,
        AVG(ff.remaining_balance) as avg_balance,
        MAX(ff.remaining_balance) as max_balance,
        MIN(ff.remaining_balance) as min_balance
    FROM FeedingFunds ff
    $where_clause
    AND DATE(ff.created_at) BETWEEN '$start_date' AND '$end_date'
";

$fund_stats_result = $conn->query($fund_stats_query);
$fund_stats = $fund_stats_result ? $fund_stats_result->fetch_assoc() : [];

// Get Meals Popularity
$meals_query = "
    SELECT 
        m.meal_id,
        m.meal_name,
        m.price,
        COUNT(*) as times_purchased,
        SUM(t.amount) as total_revenue
    FROM Transactions t
    JOIN Meals m ON t.meal_id = m.meal_id
    $where_clause
    AND DATE(t.transaction_date) BETWEEN '$start_date' AND '$end_date'
    GROUP BY m.meal_id, m.meal_name, m.price
    ORDER BY times_purchased DESC
    LIMIT 20
";

$meals = $conn->query($meals_query);

// Get Student-wise Expenditure
$student_exp_query = "
    SELECT 
        s.student_id,
        s.admission_number,
        s.full_name,
        s.department,
        COUNT(t.transaction_id) as meals_purchased,
        SUM(t.amount) as total_spent,
        AVG(t.amount) as avg_per_meal,
        ff.remaining_balance
    FROM Students s
    LEFT JOIN Transactions t ON s.student_id = t.student_id AND DATE(t.transaction_date) BETWEEN '$start_date' AND '$end_date'
    LEFT JOIN FeedingFunds ff ON s.student_id = ff.student_id
    WHERE s.student_id = COALESCE($student_filter, s.student_id)
    GROUP BY s.student_id, s.admission_number, s.full_name, s.department, ff.remaining_balance
    ORDER BY total_spent DESC
    LIMIT 500
";

$student_exp = $conn->query($student_exp_query);

// Handle Print Mode
$print_mode = isset($_GET['print']) && $_GET['print'] == '1';

// Handle PDF Generation
if (isset($_GET['generate_pdf'])) {
    ob_start();
    include 'generate_pdf_report.php';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Cafeteria System</title>
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

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .pdf-btn {
            background: #e74c3c;
            color: white;
        }

        .pdf-btn:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }

        .export-btn {
            background: #27ae60;
            color: white;
        }

        .export-btn:hover {
            background: #229954;
            transform: translateY(-2px);
        }

        /* Filters */
        .filters {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #1e1e2e;
            font-size: 13px;
        }

        .filter-group input,
        .filter-group select {
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 13px;
            transition: all 0.3s ease;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 10px rgba(102, 126, 234, 0.2);
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .filter-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .stat-label {
            font-size: 12px;
            color: #999;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Page Section */
        .page-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: #1e1e2e;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        /* Table */
        .table-container {
            overflow-x: auto;
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
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
        }

        tbody tr:hover {
            background: #f9f9f9;
        }

        tbody tr:nth-child(even) {
            background: #f5f5f5;
        }

        .text-success {
            color: #27ae60;
            font-weight: 600;
        }

        .text-danger {
            color: #e74c3c;
            font-weight: 600;
        }

        .text-info {
            color: #3498db;
            font-weight: 600;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .filter-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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

            .filter-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .page-section {
                padding: 15px;
            }

            table {
                font-size: 11px;
            }

            th, td {
                padding: 8px;
            }
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
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
            <li><a href="funds.php"><span>💰</span><span>Funds</span></a></li>
            <li><a href="reports.php" class="active"><span>📋</span><span>Reports</span></a></li>
            <li><a href="settings.php"><span>⚙️</span><span>Settings</span></a></li>
            <li style="margin-top: auto;"><a href="logout.php"><span>🚪</span><span>Logout</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>📋 System Reports & Analytics</h1>
            <div class="header-actions">
                <button class="btn pdf-btn" onclick="generatePDF()">📄 Download PDF</button>
                <button class="btn export-btn" onclick="exportToCSV()">📥 Export CSV</button>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" action="">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label>Select Student (Optional)</label>
                        <select name="student_id">
                            <option value="">-- All Students --</option>
                            <?php 
                            if ($all_students) {
                                while ($student = $all_students->fetch_assoc()) {
                                    $selected = ($student['student_id'] == $student_filter) ? 'selected' : '';
                                    echo "<option value='" . $student['student_id'] . "' $selected>" . htmlspecialchars($student['admission_number'] . " - " . $student['full_name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>

                    <div class="filter-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>

                    <div class="filter-group">
                        <label>Report Type</label>
                        <select name="report_type">
                            <option value="all" <?php echo ($report_type == 'all') ? 'selected' : ''; ?>>All Reports</option>
                            <option value="transactions" <?php echo ($report_type == 'transactions') ? 'selected' : ''; ?>>Transactions Only</option>
                            <option value="deposits" <?php echo ($report_type == 'deposits') ? 'selected' : ''; ?>>Deposits Only</option>
                            <option value="meals" <?php echo ($report_type == 'meals') ? 'selected' : ''; ?>>Meals Analytics</option>
                        </select>
                    </div>
                </div>

                <div class="filter-buttons">
                    <button type="submit" class="filter-btn">🔍 Generate Report</button>
                    <a href="reports.php" style="text-decoration: none;">
                        <button type="button" class="filter-btn" style="background: #95a5a6;">↺ Reset</button>
                    </a>
                </div>
            </form>
        </div>

        <!-- Statistics Summary -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Transactions</div>
                <div class="stat-value"><?php echo $stats['total_transactions'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Amount Spent</div>
                <div class="stat-value">KSh <?php echo number_format($stats['total_spent'] ?? 0, 2); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Average Transaction</div>
                <div class="stat-value">KSh <?php echo number_format($stats['avg_transaction'] ?? 0, 2); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Students with Funds</div>
                <div class="stat-value"><?php echo $fund_stats['students_with_funds'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Active Funds</div>
                <div class="stat-value">KSh <?php echo number_format($fund_stats['total_funds'] ?? 0, 2); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Average Balance</div>
                <div class="stat-value">KSh <?php echo number_format($fund_stats['avg_balance'] ?? 0, 2); ?></div>
            </div>
        </div>

        <!-- Transaction History -->
        <?php if ($report_type == 'all' || $report_type == 'transactions'): ?>
        <div class="page-section">
            <h2 class="section-title">📊 Transaction History</h2>
            <?php if ($transactions && $transactions->num_rows > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>Date & Time</th>
                            <th>Admission No.</th>
                            <th>Student Name</th>
                            <th>Meal Name</th>
                            <th>Amount (KSh)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_spent_calc = 0;
                        while ($trans = $transactions->fetch_assoc()): 
                            $total_spent_calc += $trans['amount'];
                        ?>
                        <tr>
                            <td><?php echo $trans['transaction_id']; ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($trans['transaction_date'])); ?></td>
                            <td><?php echo htmlspecialchars($trans['admission_number']); ?></td>
                            <td><?php echo htmlspecialchars($trans['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($trans['meal_name']); ?></td>
                            <td class="text-success"><?php echo number_format($trans['amount'], 2); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <p>No transactions found for the selected period.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Fund Deposits History -->
        <?php if ($report_type == 'all' || $report_type == 'deposits'): ?>
        <div class="page-section">
            <h2 class="section-title">💰 Fund Deposits History</h2>
            <?php if ($deposits && $deposits->num_rows > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Admission No.</th>
                            <th>Student Name</th>
                            <th>Balance (KSh)</th>
                            <th>Department</th>
                            <th>Year</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($dep = $deposits->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($dep['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($dep['admission_number']); ?></td>
                            <td><?php echo htmlspecialchars($dep['full_name']); ?></td>
                            <td class="text-success"><?php echo number_format($dep['remaining_balance'], 2); ?></td>
                            <td><?php echo htmlspecialchars($dep['department']); ?></td>
                            <td><?php echo $dep['year_of_study']; ?></td>
                            <td><?php echo $dep['status']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <p>No fund deposits found for the selected period.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Meals Analytics -->
        <?php if ($report_type == 'all' || $report_type == 'meals'): ?>
        <div class="page-section">
            <h2 class="section-title">🍴 Meals Popularity & Revenue</h2>
            <?php if ($meals && $meals->num_rows > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Meal Name</th>
                            <th>Unit Price (KSh)</th>
                            <th>Times Purchased</th>
                            <th>Total Revenue (KSh)</th>
                            <th>Average per Transaction (KSh)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($meal = $meals->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($meal['meal_name']); ?></td>
                            <td><?php echo number_format($meal['price'], 2); ?></td>
                            <td class="text-info"><?php echo $meal['times_purchased']; ?></td>
                            <td class="text-success"><?php echo number_format($meal['total_revenue'], 2); ?></td>
                            <td><?php echo number_format($meal['total_revenue'] / $meal['times_purchased'], 2); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <p>No meal data found for the selected period.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Student-wise Expenditure -->
        <div class="page-section">
            <h2 class="section-title">👥 Student-wise Expenditure Report</h2>
            <?php if ($student_exp && $student_exp->num_rows > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Admission No.</th>
                            <th>Student Name</th>
                            <th>Department</th>
                            <th>Meals Purchased</th>
                            <th>Total Spent (KSh)</th>
                            <th>Avg per Meal (KSh)</th>
                            <th>Current Balance (KSh)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($exp = $student_exp->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($exp['admission_number']); ?></td>
                            <td><?php echo htmlspecialchars($exp['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($exp['department']); ?></td>
                            <td><?php echo $exp['meals_purchased'] ?? 0; ?></td>
                            <td class="text-danger"><?php echo number_format($exp['total_spent'] ?? 0, 2); ?></td>
                            <td><?php echo number_format(($exp['total_spent'] ?? 0) / max(1, $exp['meals_purchased'] ?? 1), 2); ?></td>
                            <td class="text-success"><?php echo number_format($exp['remaining_balance'] ?? 0, 2); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <p>No expenditure data found for the selected criteria.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function generatePDF() {
            const student_id = document.querySelector('select[name="student_id"]').value;
            const start_date = document.querySelector('input[name="start_date"]').value;
            const end_date = document.querySelector('input[name="end_date"]').value;
            const report_type = document.querySelector('select[name="report_type"]').value;
            
            const url = `generate_pdf_report.php?student_id=${student_id}&start_date=${start_date}&end_date=${end_date}&report_type=${report_type}`;
            window.open(url, '_blank');
        }

        function exportToCSV() {
            let csv = [];
            let tables = document.querySelectorAll('table');
            
            tables.forEach((table, index) => {
                csv.push('');
                csv.push(document.querySelectorAll('.section-title')[index]?.innerText || 'Report ' + (index + 1));
                csv.push('');
                
                let rows = table.querySelectorAll('tr');
                rows.forEach(row => {
                    let cells = row.querySelectorAll('th, td');
                    let rowData = [];
                    cells.forEach(cell => {
                        rowData.push('"' + cell.innerText.replace(/"/g, '""') + '"');
                    });
                    csv.push(rowData.join(','));
                });
            });

            let csvContent = csv.join('\n');
            let blob = new Blob([csvContent], { type: 'text/csv' });
            let link = document.createElement('a');
            link.href = window.URL.createObjectURL(blob);
            link.download = 'report_' + new Date().getTime() + '.csv';
            link.click();
        }
    </script>
</body>
</html>