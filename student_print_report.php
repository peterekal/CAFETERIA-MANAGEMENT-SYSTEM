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

// Get student info
$student_result = $conn->query("SELECT * FROM Students WHERE student_id = $student_id");
$student = $student_result ? $student_result->fetch_assoc() : [];

// Get ONLY the most recent transaction (current order)
$current_order = null;
$current_transaction = $conn->query("
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
    LIMIT 1
");

if ($current_transaction && $current_transaction->num_rows > 0) {
    $current_order = $current_transaction->fetch_assoc();
}

// Get current balance
$balance = 0;
$result = $conn->query("SELECT remaining_balance FROM FeedingFunds WHERE student_id = $student_id");
if ($result && $row = $result->fetch_assoc()) {
    $balance = $row['remaining_balance'];
}

// Get manager info (from settings or use default)
$manager_name = "Cafeteria Manager";
$manager_result = $conn->query("SELECT * FROM Admins LIMIT 1");
if ($manager_result && $admin = $manager_result->fetch_assoc()) {
    $manager_name = $admin['username'] ?? "Cafeteria Manager";
}

// Generate automatic signature (based on date/time and name)
function generateSignature($name, $timestamp) {
    $hash = substr(md5($name . $timestamp), 0, 8);
    return strtoupper($hash);
}

$manager_signature = generateSignature($manager_name, date('Y-m-d'));

// Check if receipt has already been printed
$receipt_printed = isset($_SESSION['receipt_printed']) && $_SESSION['receipt_printed'] === true;

if ($current_order && !$receipt_printed) {
    // Mark receipt as printed in session
    $_SESSION['receipt_printed'] = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Receipt - AIU Cafeteria System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            background: white;
            color: #333;
            line-height: 1.6;
            padding: 20px;
        }

        @media print {
            body {
                padding: 0;
                margin: 0;
                background: white;
            }

            .no-print {
                display: none !important;
            }

            .receipt-container {
                box-shadow: none;
                border: none;
                padding: 0;
                margin: 0;
                max-width: 100%;
            }
        }

        .receipt-container {
            max-width: 600px;
            margin: 0 auto;
            border: 2px solid #333;
            padding: 20px;
            background: white;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }

        .school-name {
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 2px;
            margin-bottom: 5px;
        }

        .school-tag {
            font-size: 12px;
            font-weight: bold;
            color: #666;
            margin-bottom: 10px;
        }

        .receipt-title {
            font-size: 14px;
            font-weight: bold;
            margin: 10px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .datetime {
            font-size: 11px;
            margin: 5px 0;
        }

        .separator {
            border-top: 1px dashed #333;
            margin: 10px 0;
        }

        .stamp {
            text-align: center;
            margin: 15px 0;
            font-size: 40px;
            opacity: 0.3;
            transform: rotate(-15deg);
            font-weight: bold;
            color: red;
        }

        .student-info {
            font-size: 11px;
            margin-bottom: 15px;
        }

        .student-info-label {
            font-weight: bold;
            width: 120px;
            display: inline-block;
        }

        .order-section {
            margin: 15px 0;
            font-size: 11px;
        }

        .order-header {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #333;
        }

        .order-item-name {
            font-weight: bold;
        }

        .order-item-amount {
            text-align: right;
        }

        .total-section {
            background: #f0f0f0;
            padding: 15px;
            margin: 15px 0;
            text-align: center;
            border: 2px solid #333;
        }

        .total-label {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .total-amount {
            font-size: 28px;
            font-weight: bold;
            margin: 8px 0;
            color: #333;
        }

        .transaction-ref {
            font-size: 10px;
            color: #666;
            margin-top: 5px;
        }

        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 10px;
            border-top: 1px dashed #333;
            padding-top: 15px;
        }

        .footer-text {
            margin: 5px 0;
        }

        .signature-section {
            margin-top: 25px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            font-size: 10px;
        }

        .signature-box {
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #333;
            padding-top: 5px;
            margin-top: 30px;
            min-height: 30px;
            display: flex;
            align-items: flex-end;
            justify-content: center;
        }

        .signature-name {
            font-size: 9px;
            margin-top: 3px;
        }

        .print-button {
            text-align: center;
            margin: 20px 0;
        }

        .print-button button {
            padding: 10px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .print-button button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .empty-message {
            text-align: center;
            padding: 30px;
            font-size: 14px;
            color: #999;
        }

        .official-seal {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            font-weight: bold;
            color: #999;
        }

        .auto-signature {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            font-size: 11px;
            letter-spacing: 1px;
            color: #333;
        }

        .verified-badge {
            text-align: center;
            font-size: 10px;
            color: #27ae60;
            font-weight: bold;
            margin-top: 5px;
        }

        .receipt-status {
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            color: #27ae60;
            margin: 10px 0;
            padding: 10px;
            background: #f0fff4;
            border: 2px solid #27ae60;
            border-radius: 5px;
        }

        .print-disabled {
            text-align: center;
            font-size: 14px;
            color: #c0392b;
            font-weight: bold;
            margin: 20px 0;
            padding: 20px;
            background: #fadbd8;
            border: 2px solid #c0392b;
            border-radius: 8px;
        }

        @media (max-width: 600px) {
            .receipt-container {
                border: none;
                padding: 10px;
            }

            .signature-section {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php if ($current_order && !$receipt_printed): ?>
        <div class="print-button no-print">
            <button onclick="printReceipt()">🖨️ Print Receipt</button>
        </div>
    <?php endif; ?>

    <div class="receipt-container">
        <!-- Header with School Stamp -->
        <div class="header">
            <div class="school-name">🍽️ AIU CAFETERIA</div>
            <div class="school-tag">CAFETERIA MANAGEMENT SYSTEM</div>
            <div class="stamp">✓ OFFICIAL</div>
            <div class="receipt-title">Order Receipt</div>
            <div class="datetime">
                Date: <?php echo date('d/m/Y'); ?><br>
                Time: <?php echo date('H:i:s'); ?>
            </div>
        </div>

        <div class="separator"></div>

        <!-- Student Information -->
        <div class="student-info">
            <div><span class="student-info-label">Student Name:</span><?php echo htmlspecialchars($student_name); ?></div>
            <div><span class="student-info-label">Admission No:</span><?php echo htmlspecialchars($admission_number); ?></div>
            <div><span class="student-info-label">Department:</span><?php echo htmlspecialchars($student['department'] ?? 'N/A'); ?></div>
            <div><span class="student-info-label">Year:</span><?php echo $student['year_of_study'] ?? 'N/A'; ?></div>
        </div>

        <div class="separator"></div>

        <!-- Current Order Only -->
        <?php if ($current_order): ?>
            
            <?php if ($receipt_printed): ?>
            <div class="receipt-status">
                ✓ RECEIPT ALREADY PRINTED<br>
                This receipt can only be printed once
            </div>
            <?php endif; ?>

            <div class="order-section">
                <div class="order-header">📋 Current Order Details</div>
                
                <div class="order-item">
                    <div class="order-item-name">Meal:</div>
                    <div><?php echo htmlspecialchars($current_order['meal_name']); ?></div>
                </div>

                <div class="order-item">
                    <div class="order-item-name">Time:</div>
                    <div><?php echo date('d/m/Y H:i:s', strtotime($current_order['transaction_date'])); ?></div>
                </div>

                <div class="order-item">
                    <div class="order-item-name">Unit Price:</div>
                    <div>KSh <?php echo number_format($current_order['price'], 2); ?></div>
                </div>

                <div class="order-item" style="border-bottom: 2px solid #333; font-weight: bold;">
                    <div>Amount Paid:</div>
                    <div>KSh <?php echo number_format($current_order['amount'], 2); ?></div>
                </div>
            </div>

            <div class="separator"></div>

            <!-- Total Amount Section (Prominent) -->
            <div class="total-section">
                <div class="total-label">💰 Total Amount</div>
                <div class="total-amount">KSh <?php echo number_format($current_order['amount'], 2); ?></div>
                <div class="transaction-ref">
                    Transaction ID: #<?php echo $current_order['transaction_id']; ?><br>
                    Reference: <?php echo htmlspecialchars($admission_number); ?>
                </div>
            </div>

            <div class="separator"></div>

            <!-- Footer -->
            <div class="footer">
                <div class="footer-text">═════════════════════════════════════</div>
                <div class="footer-text">This receipt is proof of your purchase</div>
                <div class="footer-text">Generated: <?php echo date('d/m/Y H:i:s'); ?></div>
                <div class="footer-text">═════════════════════════════════════</div>
            </div>

            <!-- Signature Section with Automatic Manager Signature -->
            <div class="signature-section">
                <div class="signature-box">
                    <div style="font-weight: bold; font-size: 10px; margin-bottom: 5px;">Student</div>
                    <div class="signature-line">
                        <div style="width: 100%;">................................</div>
                    </div>
                    <div class="signature-name">Student Signature</div>
                </div>

                <div class="signature-box">
                    <div style="font-weight: bold; font-size: 10px; margin-bottom: 5px;">Manager</div>
                    <div class="signature-line">
                        <div class="auto-signature"><?php echo $manager_signature; ?></div>
                    </div>
                    <div class="verified-badge">✓ VERIFIED</div>
                    <div class="signature-name"><?php echo htmlspecialchars($manager_name); ?></div>
                </div>
            </div>

            <!-- Official Seal -->
            <div class="official-seal">
                ⭐ OFFICIAL RECEIPT ⭐
            </div>

        <?php else: ?>
            <div class="empty-message">
                <p>No orders found. Please place an order first.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function printReceipt() {
            // After printing, show disabled message
            window.print();
            
            // After print dialog closes, show that receipt can only be printed once
            setTimeout(function() {
                if (<?php echo $receipt_printed ? 'true' : 'false'; ?>) {
                    document.querySelector('.print-button').innerHTML = '<div class="print-disabled">⚠️ Receipt Already Printed<br>This receipt can only be printed once</div>';
                }
            }, 500);
        }

        // Auto disable print button after first print
        window.addEventListener('afterprint', function() {
            const printBtn = document.querySelector('.print-button button');
            if (printBtn) {
                printBtn.disabled = true;
                printBtn.textContent = '✓ Receipt Printed (Cannot Print Again)';
                printBtn.style.background = '#95a5a6';
                printBtn.style.cursor = 'not-allowed';
            }
        });

        // Optional: Auto print on first load
        // Uncomment the line below to auto-print on page load
        // window.addEventListener('load', function() {
        //     if (!<?php echo $receipt_printed ? 'true' : 'false'; ?>) {
        //         window.print();
        //     }
        // });
    </script>
</body>
</html>
