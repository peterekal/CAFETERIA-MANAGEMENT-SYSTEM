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

// Get parameters
$student_filter = isset($_GET['student_id']) ? intval($_GET['student_id']) : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'all';

// Build WHERE clause
$where_clause = "WHERE 1=1";
if (!empty($student_filter)) {
    $where_clause .= " AND ff.student_id = $student_filter";
}

// Get Statistics
$stats_query = "
    SELECT 
        COUNT(DISTINCT t.student_id) as total_transactions_students,
        COUNT(t.transaction_id) as total_transactions,
        SUM(t.amount) as total_spent,
        AVG(t.amount) as avg_transaction
    FROM Transactions t
    $where_clause
    AND DATE(t.transaction_date) BETWEEN '$start_date' AND '$end_date'
";

$stats_result = $conn->query($stats_query);
$stats = $stats_result ? $stats_result->fetch_assoc() : [];

$fund_stats_query = "
    SELECT 
        COUNT(DISTINCT ff.student_id) as students_with_funds,
        SUM(ff.remaining_balance) as total_funds,
        AVG(ff.remaining_balance) as avg_balance
    FROM FeedingFunds ff
    $where_clause
    AND DATE(ff.created_at) BETWEEN '$start_date' AND '$end_date'
";

$fund_stats_result = $conn->query($fund_stats_query);
$fund_stats = $fund_stats_result ? $fund_stats_result->fetch_assoc() : [];

// Get transactions
$transaction_query = "
    SELECT 
        t.transaction_id,
        t.student_id,
        t.transaction_date,
        t.amount,
        s.admission_number,
        s.full_name,
        m.meal_name
    FROM Transactions t
    JOIN Students s ON t.student_id = s.student_id
    JOIN Meals m ON t.meal_id = m.meal_id
    $where_clause
    AND DATE(t.transaction_date) BETWEEN '$start_date' AND '$end_date'
    ORDER BY t.transaction_date DESC
    LIMIT 500
";

$transactions = $conn->query($transaction_query);

// Get deposits
$deposits_query = "
    SELECT 
        ff.fund_id,
        ff.student_id,
        ff.remaining_balance,
        ff.created_at,
        s.admission_number,
        s.full_name,
        s.department
    FROM FeedingFunds ff
    JOIN Students s ON ff.student_id = s.student_id
    $where_clause
    AND DATE(ff.created_at) BETWEEN '$start_date' AND '$end_date'
    ORDER BY ff.created_at DESC
    LIMIT 500
";

$deposits = $conn->query($deposits_query);

// Get meals
$meals_query = "
    SELECT 
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

// Generate HTML for PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background: white;
        }
        
        .receipt {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            page-break-after: always;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #667eea;
        }
        
        .header h1 {
            font-size: 28px;
            color: #1e1e2e;
            margin-bottom: 5px;
        }
        
        .header p {
            font-size: 12px;
            color: #666;
            margin: 2px 0;
        }
        
        .section-divider {
            border-top: 2px dashed #ccc;
            margin: 20px 0;
            padding: 15px 0;
        }
        
        .report-title {
            font-size: 16px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
            font-size: 12px;
        }
        
        .info-item {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
        }
        
        .info-label {
            font-weight: bold;
            color: #667eea;
            font-size: 11px;
            text-transform: uppercase;
        }
        
        .info-value {
            font-size: 14px;
            color: #1e1e2e;
            margin-top: 5px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px;
            border-radius: 5px;
            text-align: center;
            font-size: 11px;
        }
        
        .stat-box .value {
            font-size: 18px;
            font-weight: bold;
            margin: 5px 0;
        }
        
        .stat-box .label {
            font-size: 10px;
            opacity: 0.9;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin: 15px 0;
        }
        
        thead {
            background: #667eea;
            color: white;
        }
        
        th {
            padding: 8px;
            text-align: left;
            font-weight: bold;
            border-bottom: 2px solid #667eea;
        }
        
        td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        
        tbody tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        tbody tr:hover {
            background: #f0f0f0;
        }
        
        .total-row {
            font-weight: bold;
            background: #e8e8f0 !important;
            border-top: 2px solid #667eea;
            border-bottom: 2px solid #667eea;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px dashed #ccc;
            font-size: 10px;
            color: #999;
        }
        
        .page-break {
            page-break-before: always;
            margin-top: 20px;
        }
        
        .signature-line {
            margin-top: 30px;
            display: flex;
            justify-content: space-around;
        }
        
        .signature-box {
            text-align: center;
            width: 150px;
            border-top: 1px solid #000;
            padding-top: 5px;
            font-size: 11px;
        }
    </style>
</head>
<body>
';

// Header
$html .= '
<div class="receipt">
    <div class="header">
        <h1>🍽️ CAFETERIA MANAGEMENT SYSTEM</h1>
        <p>FINANCIAL REPORT</p>
        <p>Generated on: ' . date('d/m/Y H:i:s') . '</p>
    </div>
    
    <div class="info-grid">
        <div class="info-item">
            <div class="info-label">Report Period</div>
            <div class="info-value">' . date('d/m/Y', strtotime($start_date)) . ' to ' . date('d/m/Y', strtotime($end_date)) . '</div>
        </div>
        <div class="info-item">
            <div class="info-label">Report Type</div>
            <div class="info-value">' . ucfirst($report_type == 'all' ? 'Comprehensive' : str_replace('_', ' ', $report_type)) . '</div>
        </div>
    </div>
    
    <div class="section-divider"></div>
    
    <div class="report-title">📊 SUMMARY STATISTICS</div>
    <div class="stats-grid">
        <div class="stat-box">
            <div class="label">Total Transactions</div>
            <div class="value">' . ($stats['total_transactions'] ?? 0) . '</div>
        </div>
        <div class="stat-box">
            <div class="label">Total Amount Spent</div>
            <div class="value">KSh ' . number_format($stats['total_spent'] ?? 0, 0) . '</div>
        </div>
        <div class="stat-box">
            <div class="label">Avg Transaction</div>
            <div class="value">KSh ' . number_format($stats['avg_transaction'] ?? 0, 0) . '</div>
        </div>
    </div>
    
    <div class="stats-grid">
        <div class="stat-box">
            <div class="label">Students w/ Funds</div>
            <div class="value">' . ($fund_stats['students_with_funds'] ?? 0) . '</div>
        </div>
        <div class="stat-box">
            <div class="label">Total Active Funds</div>
            <div class="value">KSh ' . number_format($fund_stats['total_funds'] ?? 0, 0) . '</div>
        </div>
        <div class="stat-box">
            <div class="label">Avg Balance</div>
            <div class="value">KSh ' . number_format($fund_stats['avg_balance'] ?? 0, 0) . '</div>
        </div>
    </div>
';

// Transaction History
if (($report_type == 'all' || $report_type == 'transactions') && $transactions && $transactions->num_rows > 0) {
    $html .= '
    <div class="section-divider"></div>
    <div class="report-title">📋 TRANSACTION HISTORY</div>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Date & Time</th>
                <th>Admission No.</th>
                <th>Student Name</th>
                <th>Meal</th>
                <th style="text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
    ';
    
    $trans_total = 0;
    while ($trans = $transactions->fetch_assoc()) {
        $trans_total += $trans['amount'];
        $html .= '
            <tr>
                <td>' . $trans['transaction_id'] . '</td>
                <td>' . date('d/m/Y H:i', strtotime($trans['transaction_date'])) . '</td>
                <td>' . htmlspecialchars($trans['admission_number']) . '</td>
                <td>' . htmlspecialchars($trans['full_name']) . '</td>
                <td>' . htmlspecialchars($trans['meal_name']) . '</td>
                <td style="text-align: right;">KSh ' . number_format($trans['amount'], 2) . '</td>
            </tr>
        ';
    }
    
    $html .= '
            <tr class="total-row">
                <td colspan="5" style="text-align: right;">TOTAL:</td>
                <td style="text-align: right;">KSh ' . number_format($trans_total, 2) . '</td>
            </tr>
        </tbody>
    </table>
    ';
}

// Fund Deposits
if (($report_type == 'all' || $report_type == 'deposits') && $deposits && $deposits->num_rows > 0) {
    $html .= '
    <div class="page-break"></div>
    <div class="header">
        <h1>🍽️ CAFETERIA MANAGEMENT SYSTEM</h1>
        <p>FUND DEPOSITS REPORT</p>
    </div>
    
    <div class="report-title">💰 FUND DEPOSITS HISTORY</div>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Admission No.</th>
                <th>Student Name</th>
                <th>Department</th>
                <th style="text-align: right;">Balance</th>
            </tr>
        </thead>
        <tbody>
    ';
    
    $dep_total = 0;
    while ($dep = $deposits->fetch_assoc()) {
        $dep_total += $dep['remaining_balance'];
        $html .= '
            <tr>
                <td>' . date('d/m/Y H:i', strtotime($dep['created_at'])) . '</td>
                <td>' . htmlspecialchars($dep['admission_number']) . '</td>
                <td>' . htmlspecialchars($dep['full_name']) . '</td>
                <td>' . htmlspecialchars($dep['department']) . '</td>
                <td style="text-align: right;">KSh ' . number_format($dep['remaining_balance'], 2) . '</td>
            </tr>
        ';
    }
    
    $html .= '
            <tr class="total-row">
                <td colspan="4" style="text-align: right;">TOTAL FUNDS:</td>
                <td style="text-align: right;">KSh ' . number_format($dep_total, 2) . '</td>
            </tr>
        </tbody>
    </table>
    ';
}

// Meals Analytics
if (($report_type == 'all' || $report_type == 'meals') && $meals && $meals->num_rows > 0) {
    $html .= '
    <div class="page-break"></div>
    <div class="header">
        <h1>🍽️ CAFETERIA MANAGEMENT SYSTEM</h1>
        <p>MEALS ANALYTICS REPORT</p>
    </div>
    
    <div class="report-title">🍴 MEALS POPULARITY & REVENUE</div>
    <table>
        <thead>
            <tr>
                <th>Meal Name</th>
                <th>Unit Price</th>
                <th style="text-align: right;">Times Purchased</th>
                <th style="text-align: right;">Total Revenue</th>
            </tr>
        </thead>
        <tbody>
    ';
    
    $meals_total = 0;
    while ($meal = $meals->fetch_assoc()) {
        $meals_total += $meal['total_revenue'];
        $html .= '
            <tr>
                <td>' . htmlspecialchars($meal['meal_name']) . '</td>
                <td>KSh ' . number_format($meal['price'], 2) . '</td>
                <td style="text-align: right;">' . $meal['times_purchased'] . '</td>
                <td style="text-align: right;">KSh ' . number_format($meal['total_revenue'], 2) . '</td>
            </tr>
        ';
    }
    
    $html .= '
            <tr class="total-row">
                <td colspan="3" style="text-align: right;">TOTAL REVENUE:</td>
                <td style="text-align: right;">KSh ' . number_format($meals_total, 2) . '</td>
            </tr>
        </tbody>
    </table>
    ';
}

// Footer
$html .= '
    <div class="footer">
        <p>This is an official report from the Cafeteria Management System</p>
        <p>Generated by: Admin ID - ' . htmlspecialchars($_SESSION['admin_id']) . '</p>
        <p>Date Generated: ' . date('d/m/Y H:i:s') . '</p>
        <p style="margin-top: 20px;">--- END OF REPORT ---</p>
    </div>
    
    <div class="signature-line">
        <div class="signature-box">
            Prepared by: _______________
        </div>
        <div class="signature-box">
            Authorized by: _______________
        </div>
    </div>
</div>

</body>
</html>
';

// Output PDF
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Cafeteria_Report_' . date('d_m_Y_H_i') . '.pdf"');

// Use TCPDF or similar - for now output as HTML that can be printed to PDF
echo $html;
?>