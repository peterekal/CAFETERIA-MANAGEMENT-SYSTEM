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

// Fetch number of students
$students_count = 0;
$result = $conn->query("SELECT COUNT(*) AS total FROM Students");
if ($result && $row = $result->fetch_assoc()) {
    $students_count = $row['total'];
}

// Fetch number of meals available
$meals_count = 0;
$result = $conn->query("SELECT COUNT(*) AS total FROM Meals");
if ($result && $row = $result->fetch_assoc()) {
    $meals_count = $row['total'];
}

// Fetch total cash in student funds
$total_funds = 0;
$result = $conn->query("SELECT SUM(remaining_balance) AS total FROM FeedingFunds");
if ($result && $row = $result->fetch_assoc()) {
    $total_funds = $row['total'] ?? 0;
}

// Fetch most frequently eaten meal
$popular_meal = "No data";
$result = $conn->query("SELECT Meals.meal_name, COUNT(*) AS freq 
                        FROM Transactions 
                        JOIN Meals ON Transactions.meal_id = Meals.meal_id 
                        GROUP BY Meals.meal_name 
                        ORDER BY freq DESC LIMIT 1");
if ($result && $row = $result->fetch_assoc()) {
    $popular_meal = $row['meal_name'];
}

// Fetch peak serving time
$peak_time = "No data";
$result = $conn->query("SELECT HOUR(transaction_date) AS hr, COUNT(*) AS freq 
                        FROM Transactions 
                        GROUP BY hr 
                        ORDER BY freq DESC LIMIT 1");
if ($result && $row = $result->fetch_assoc()) {
    $peak_time = $row['hr'] . ":00 hrs";
}

// Prepare data for charts
$meal_labels = [];
$meal_counts = [];
$result = $conn->query("SELECT Meals.meal_name, COUNT(*) AS freq 
                        FROM Transactions 
                        JOIN Meals ON Transactions.meal_id = Meals.meal_id 
                        GROUP BY Meals.meal_name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $meal_labels[] = $row['meal_name'];
        $meal_counts[] = $row['freq'];
    }
}

$hour_labels = [];
$hour_counts = [];
$result = $conn->query("SELECT HOUR(transaction_date) AS hr, COUNT(*) AS freq 
                        FROM Transactions 
                        GROUP BY hr ORDER BY hr ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $hour_labels[] = $row['hr'] . ":00";
        $hour_counts[] = $row['freq'];
    }
}

// Fetch active and inactive students count
$active_students = 0;
$inactive_students = 0;
$result = $conn->query("SELECT status, COUNT(*) AS count FROM Students GROUP BY status");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        if ($row['status'] == 'Active') {
            $active_students = $row['count'];
        } else {
            $inactive_students = $row['count'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Cafeteria System</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .sidebar-menu a span:last-child::before {
            content: '';
            display: inline-block;
            width: 4px;
            height: 4px;
            background: #667eea;
            border-radius: 50%;
            margin-right: 8px;
        }

        /* Main Content */
        .main-content {
            margin-left: 80px;
            padding: 30px;
            transition: margin-left 0.3s ease;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255, 255, 255, 0.95);
            padding: 20px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        .topbar h1 {
            font-size: 28px;
            color: #1e1e2e;
            font-weight: 600;
        }

        .topbar-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .topbar-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .topbar-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .topbar-btn.logout {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        .topbar-btn.logout:hover {
            box-shadow: 0 5px 20px rgba(231, 76, 60, 0.4);
        }

        /* Dashboard Cards */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
        }

        .card-icon {
            font-size: 40px;
            margin-bottom: 15px;
        }

        .card h3 {
            color: #666;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .card-value {
            font-size: 32px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .card-stat {
            font-size: 12px;
            color: #27ae60;
            font-weight: 600;
        }

        .card-stat.negative {
            color: #e74c3c;
        }

        /* Charts Section */
        .charts-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .chart-box {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .chart-box h3 {
            color: #1e1e2e;
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: 600;
        }

        .chart-box canvas {
            max-height: 300px;
        }

        /* Status Table */
        .status-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .status-section h3 {
            color: #1e1e2e;
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: 600;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .status-item {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .status-item.active {
            background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
        }

        .status-item.inactive {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        .status-label {
            font-size: 12px;
            color: #555;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .status-value {
            font-size: 28px;
            font-weight: 700;
            color: #1e1e2e;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-content {
                padding: 20px;
            }

            .topbar {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .topbar h1 {
                font-size: 24px;
            }

            .dashboard-cards {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 15px;
            }

            .charts-container {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                padding: 15px 0;
            }

            .sidebar-logo {
                width: 50px;
                height: 50px;
                font-size: 24px;
                margin-bottom: 20px;
            }

            .sidebar-menu a {
                padding: 15px 0;
            }

            .main-content {
                margin-left: 70px;
                padding: 15px;
            }

            .topbar {
                padding: 15px 20px;
            }

            .topbar h1 {
                font-size: 20px;
            }

            .card {
                padding: 20px;
            }

            .card-value {
                font-size: 24px;
            }

            .chart-box {
                padding: 20px;
            }
        }

        /* Animations */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card, .chart-box, .status-section {
            animation: slideIn 0.5s ease forwards;
        }

        .card:nth-child(2) { animation-delay: 0.1s; }
        .card:nth-child(3) { animation-delay: 0.2s; }
        .card:nth-child(4) { animation-delay: 0.3s; }
        .card:nth-child(5) { animation-delay: 0.4s; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-logo">🍽️</div>
        <ul class="sidebar-menu">
            <li><a href="admin_index.php" class="active"><span>📊</span><span>Dashboard</span></a></li>
            <li><a href="students.php"><span>👥</span><span>Students</span></a></li>
            <li><a href="meals.php"><span>🍴</span><span>Meals</span></a></li>
            <li><a href="funds.php"><span>💰</span><span>Funds</span></a></li>
            <li><a href="reports.php"><span>📋</span><span>Reports</span></a></li>
            <li><a href="settings.php"><span>⚙️</span><span>Settings</span></a></li>
            <li style="margin-top: auto;"><a href="logout.php"><span>🚪</span><span>Logout</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="topbar">
            <h1>📊 Dashboard Overview</h1>
            <div class="topbar-actions">
                <button class="topbar-btn">🔔 Notifications</button>
                <button class="topbar-btn logout">🚪 Logout</button>
            </div>
        </div>

        <!-- Dashboard Cards -->
        <div class="dashboard-cards">
            <div class="card">
                <div class="card-icon">👥</div>
                <h3>Total Students</h3>
                <div class="card-value"><?php echo $students_count; ?></div>
                <div class="card-stat">Active: <?php echo $active_students; ?> | Inactive: <?php echo $inactive_students; ?></div>
            </div>

            <div class="card">
                <div class="card-icon">🍴</div>
                <h3>Available Meals</h3>
                <div class="card-value"><?php echo $meals_count; ?></div>
                <div class="card-stat">Popular: <?php echo $popular_meal; ?></div>
            </div>

            <div class="card">
                <div class="card-icon">💰</div>
                <h3>Total Funds</h3>
                <div class="card-value">KSh <?php echo number_format($total_funds, 0); ?></div>
                <div class="card-stat">Student Balance</div>
            </div>

            <div class="card">
                <div class="card-icon">⏰</div>
                <h3>Peak Serving Time</h3>
                <div class="card-value"><?php echo $peak_time; ?></div>
                <div class="card-stat">Highest Activity</div>
            </div>

            <div class="card">
                <div class="card-icon">📈</div>
                <h3>System Status</h3>
                <div class="card-value" style="color: #27ae60;">✓ Online</div>
                <div class="card-stat">All Systems Operational</div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-container">
            <div class="chart-box">
                <h3>🍽️ Meals Consumption</h3>
                <canvas id="mealsChart"></canvas>
            </div>
            <div class="chart-box">
                <h3>⏰ Serving Times Distribution</h3>
                <canvas id="timeChart"></canvas>
            </div>
        </div>

        <!-- Status Section -->
        <div class="status-section">
            <h3>📊 Quick Statistics</h3>
            <div class="status-grid">
                <div class="status-item active">
                    <div class="status-label">Active Students</div>
                    <div class="status-value"><?php echo $active_students; ?></div>
                </div>
                <div class="status-item inactive">
                    <div class="status-label">Inactive Students</div>
                    <div class="status-value"><?php echo $inactive_students; ?></div>
                </div>
                <div class="status-item">
                    <div class="status-label">Total Meals</div>
                    <div class="status-value"><?php echo $meals_count; ?></div>
                </div>
                <div class="status-item">
                    <div class="status-label">Available Funds</div>
                    <div class="status-value">KSh <?php echo number_format($total_funds, 0); ?></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Sidebar Interaction
        const sidebar = document.querySelector('.sidebar');
        sidebar.addEventListener('mouseenter', function() {
            this.style.width = '250px';
        });
        sidebar.addEventListener('mouseleave', function() {
            this.style.width = '80px';
        });

        // Active menu item
        document.querySelectorAll('.sidebar-menu a').forEach(link => {
            link.addEventListener('click', function() {
                document.querySelectorAll('.sidebar-menu a').forEach(l => l.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // Logout button
        document.querySelector('.topbar-btn.logout').addEventListener('click', function() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        });

        // Chart.js: Meals Consumption
        const mealLabels = <?php echo json_encode($meal_labels); ?>;
        const mealCounts = <?php echo json_encode($meal_counts); ?>;
        
        const mealsCtx = document.getElementById('mealsChart').getContext('2d');
        new Chart(mealsCtx, {
            type: 'doughnut',
            data: {
                labels: mealLabels,
                datasets: [{
                    data: mealCounts,
                    backgroundColor: [
                        'rgba(102, 126, 234, 0.8)',
                        'rgba(118, 75, 162, 0.8)',
                        'rgba(231, 76, 60, 0.8)',
                        'rgba(52, 152, 219, 0.8)',
                        'rgba(155, 89, 182, 0.8)',
                        'rgba(26, 188, 156, 0.8)'
                    ],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: { size: 12 },
                            padding: 15,
                            usePointStyle: true
                        }
                    }
                }
            }
        });

        // Chart.js: Serving Times
        const hourLabels = <?php echo json_encode($hour_labels); ?>;
        const hourCounts = <?php echo json_encode($hour_counts); ?>;
        
        const timeCtx = document.getElementById('timeChart').getContext('2d');
        new Chart(timeCtx, {
            type: 'line',
            data: {
                labels: hourLabels,
                datasets: [{
                    label: 'Students Served',
                    data: hourCounts,
                    borderColor: 'rgba(102, 126, 234, 1)',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: 'rgba(102, 126, 234, 1)',
                    pointBorderColor: '#fff',
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            font: { size: 11 }
                        }
                    },
                    x: {
                        ticks: {
                            font: { size: 11 }
                        }
                    }
                }
            }
        });

        // Logout button in topbar
        document.querySelectorAll('.topbar-btn.logout').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                if (confirm('Are you sure you want to logout?')) {
                    window.location.href = 'logout.php';
                }
            });
        });
    </script>
</body>
</html>