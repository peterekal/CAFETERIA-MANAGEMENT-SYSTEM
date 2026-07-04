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

// Handle Add Student
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_student'])) {
    $admission_number = $_POST['admission_number'];
    $full_name        = $_POST['full_name'];
    $email            = $_POST['email'];
    $phone            = $_POST['phone'];
    $department       = $_POST['department'];
    $year_of_study    = $_POST['year_of_study'];
    $password         = $_POST['password'];
    $status           = "Active";

    $password_hash = hash('sha256', $password);

    $stmt = $conn->prepare("INSERT INTO Students 
        (admission_number, full_name, email, phone, department, year_of_study, password_hash, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssssisss", $admission_number, $full_name, $email, $phone, $department, $year_of_study, $password_hash, $status);
        $stmt->execute();
        $stmt->close();
    }
}

// Handle Delete Student
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM Students WHERE student_id=?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
}

// Handle Toggle Active/Inactive
if (isset($_GET['toggle'])) {
    $id = $_GET['toggle'];
    $stmt = $conn->prepare("UPDATE Students SET status = IF(status='Active','Inactive','Active') WHERE student_id=?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
}

// Handle Edit Student
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_student'])) {
    $id              = $_POST['student_id'];
    $full_name       = $_POST['full_name'];
    $email           = $_POST['email'];
    $phone           = $_POST['phone'];
    $department      = $_POST['department'];
    $year_of_study   = $_POST['year_of_study'];

    $stmt = $conn->prepare("UPDATE Students SET full_name=?, email=?, phone=?, department=?, year_of_study=? WHERE student_id=?");
    if ($stmt) {
        $stmt->bind_param("ssssii", $full_name, $email, $phone, $department, $year_of_study, $id);
        $stmt->execute();
        $stmt->close();
    }
}

// Handle Change Password
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $id       = $_POST['student_id'];
    $password = $_POST['password'];
    $password_hash = hash('sha256', $password);

    $stmt = $conn->prepare("UPDATE Students SET password_hash=? WHERE student_id=?");
    if ($stmt) {
        $stmt->bind_param("si", $password_hash, $id);
        $stmt->execute();
        $stmt->close();
    }
}

// Handle Search
$search_query = "";
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = $_GET['search'];
    $search_term = "%{$search_query}%";
    $stmt = $conn->prepare("SELECT * FROM Students WHERE admission_number LIKE ? ORDER BY student_id DESC");
    if ($stmt) {
        $stmt->bind_param("s", $search_term);
        $stmt->execute();
        $students = $stmt->get_result();
        $stmt->close();
    }
} else {
    // Fetch all Students if no search
    $students = $conn->query("SELECT * FROM Students ORDER BY student_id DESC");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Student Management</title>
    <style>
        body { font-family: Arial, sans-serif; margin:0; background:#f4f4f4; display:flex; }
        /* Sidebar */
        .sidebar { width:200px; background:#2c3e50; color:#fff; height:100vh; padding-top:20px; font-size:14px; position:fixed; }
        .sidebar h2 { text-align:center; font-size:16px; margin-bottom:20px; }
        .sidebar a { display:block; padding:8px 12px; color:#fff; text-decoration:none; border-bottom:1px solid #1a252f; }
        .sidebar a:hover { background:#34495e; }
        /* Main Container */
        .main-container { margin-left:200px; flex:1; display:flex; flex-direction:column; }
        /* Top Search Bar */
        .search-bar { background:#fff; padding:15px 20px; box-shadow:0 2px 4px rgba(0,0,0,0.1); display:flex; justify-content:flex-end; gap:10px; }
        .search-bar input { padding:8px 12px; border:1px solid #ccc; border-radius:5px; font-size:13px; width:250px; }
        .search-bar button { padding:8px 20px; background:#3498db; color:#fff; border:none; border-radius:5px; cursor:pointer; font-weight:bold; }
        .search-bar button:hover { background:#2980b9; }
        .clear-btn { background:#95a5a6; }
        .clear-btn:hover { background:#7f8c8d; }
        /* Content Area */
        .content { display:flex; flex:1; }
        /* Middle Form */
        .form-section { flex:1; padding:20px; max-width:350px; }
        .form-box { background:#fff; padding:20px; border-radius:10px; box-shadow:0 4px 8px rgba(0,0,0,0.1); }
        input, button, select { width:100%; padding:8px; margin:8px 0; border-radius:5px; font-size:13px; box-sizing:border-box; }
        input, select { border:1px solid #ccc; }
        button { background:#27ae60; color:#fff; border:none; cursor:pointer; font-weight:bold; }
        button:hover { background:#2ecc71; }
        /* Right Table */
        .table-section { flex:2; padding:20px; overflow-x:auto; }
        table { width:100%; border-collapse:collapse; background:#fff; font-size:13px; }
        th, td { padding:6px 8px; border:1px solid #ddd; text-align:left; }
        th { background:#2c3e50; color:#fff; font-size:12px; letter-spacing:0.5px; }
        tr:nth-child(even) { background:#f9f9f9; }
        tr:hover { background:#eef; transition:0.2s; }
        .action-btn { padding:4px 8px; font-size:12px; border:none; border-radius:4px; cursor:pointer; margin:2px; display:inline-block; }
        .delete-btn { background:#e74c3c; color:#fff; }
        .delete-btn:hover { background:#c0392b; }
        .toggle-btn { background:#f39c12; color:#fff; }
        .toggle-btn:hover { background:#d35400; }
        .edit-btn { background:#3498db; color:#fff; }
        .edit-btn:hover { background:#2980b9; }
        .password-btn { background:#8e44ad; color:#fff; }
        .password-btn:hover { background:#6c3483; }
        /* Modal */
        .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%;
            background:rgba(0,0,0,0.5); justify-content:center; align-items:center; z-index:1000; }
        .modal-content { background:#fff; padding:20px; border-radius:10px; width:350px; font-size:13px; }
        .close { float:right; cursor:pointer; color:#e74c3c; font-weight:bold; }
        .fade-in { animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from {opacity:0; transform:scale(0.95);} to {opacity:1; transform:scale(1);} }
        .no-results { background:#fff; padding:20px; border-radius:10px; text-align:center; color:#7f8c8d; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h2>Admin Menu</h2>
        <a href="admin_index.php">Dashboard</a>
        <a href="students.php">Students</a>
        <a href="meals.php">Meals Available</a>
        <a href="funds.php">Student Funds</a>
        <a href="reports.php">Reports</a>
        <a href="settings.php">Settings</a>
        <a href="logout.php">Logout</a>
    </div>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Search Bar -->
        <div class="search-bar">
            <form method="GET" style="display:flex; gap:10px; width:100%; justify-content:flex-end;">
                <input type="text" name="search" placeholder="Search by Admission Number..." value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit">🔍 Search</button>
                <?php if (!empty($search_query)) { ?>
                <a href="students.php" style="text-decoration:none;">
                    <button type="button" class="clear-btn">Clear</button>
                </a>
                <?php } ?>
            </form>
        </div>

        <!-- Content Area -->
        <div class="content">
            <!-- Middle Form -->
            <div class="form-section">
                <div class="form-box">
                    <h3>Add New Student</h3>
                    <form method="POST">
                        <input type="text" name="admission_number" placeholder="Admission Number" required>
                        <input type="text" name="full_name" placeholder="Full Name" required>
                        <input type="email" name="email" placeholder="Email Address">
                        <input type="text" name="phone" placeholder="Phone Number">
                        <input type="text" name="department" placeholder="Department">
                        <input type="number" name="year_of_study" placeholder="Year of Study" min="1" max="4">
                        <input type="password" name="password" placeholder="Password" required>
                        <button type="submit" name="add_student">Add Student</button>
                    </form>
                </div>
            </div>

            <!-- Right Table -->
            <div class="table-section">
                <h3>
                    <?php 
                    if (!empty($search_query)) {
                        echo "Search Results for: <strong>" . htmlspecialchars($search_query) . "</strong>";
                    } else {
                        echo "Enrolled Students";
                    }
                    ?>
                </h3>
                <?php if ($students && $students->num_rows > 0) { ?>
                <table>
                    <tr>
                        <th>ID</th><th>Admission No.</th><th>Full Name</th><th>Email</th><th>Phone</th><th>Department</th><th>Year</th><th>Status</th><th>Actions</th>
                    </tr>
                    <?php while($row = $students->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['admission_number']); ?></td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars($row['phone']); ?></td>
                        <td><?php echo htmlspecialchars($row['department']); ?></td>
                        <td><?php echo htmlspecialchars($row['year_of_study']); ?></td>
                        <td><?php echo htmlspecialchars($row['status']); ?></td>
                        <td>
                            <a href="students.php?toggle=<?php echo $row['student_id']; ?>" style="text-decoration:none;">
                                <button class="action-btn toggle-btn" type="button">
                                    <?php echo ($row['status'] == 'Active') ? 'Deactivate' : 'Activate'; ?>
                                </button>
                            </a>
                            <button class="action-btn edit-btn" type="button" onclick="openEditModal(
                                <?php echo $row['student_id']; ?>,
                                '<?php echo addslashes($row['full_name']); ?>',
                                '<?php echo addslashes($row['email']); ?>',
                                '<?php echo addslashes($row['phone']); ?>',
                                '<?php echo addslashes($row['department']); ?>',
                                <?php echo $row['year_of_study']; ?>
                            )">Edit</button>
                            <button class="action-btn password-btn" type="button" onclick="openPasswordModal(<?php echo $row['student_id']; ?>)">Change Password</button>
                            <a href="students.php?delete=<?php echo $row['student_id']; ?>" style="text-decoration:none;"
                               onclick="return confirm('Are you sure you want to delete this student?')">
                               <button class="action-btn delete-btn" type="button">Delete</button>
                            </a>
                        </td>
                    </tr>
                    <?php } ?>
                </table>
                <?php } else { ?>
                <div class="no-results">
                    <?php 
                    if (!empty($search_query)) {
                        echo "No students found with admission number: <strong>" . htmlspecialchars($search_query) . "</strong>";
                    } else {
                        echo "No students enrolled yet.";
                    }
                    ?>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content fade-in">
            <span class="close" onclick="closeModal('editModal')">✖</span>
            <h3>Edit Student</h3>
            <form method="POST">
                <input type="hidden" name="student_id" id="edit_id">
                <input type="text" name="full_name" id="edit_name" placeholder="Full Name" required>
                <input type="email" name="email" id="edit_email" placeholder="Email">
                <input type="text" name="phone" id="edit_phone" placeholder="Phone">
                <input type="text" name="department" id="edit_department" placeholder="Department">
                <input type="number" name="year_of_study" id="edit_year" placeholder="Year of Study" min="1" max="4">
                <button type="submit" name="edit_student">Save Changes</button>
            </form>
        </div>
    </div>

    <!-- Password Modal -->
    <div class="modal" id="passwordModal">
        <div class="modal-content fade-in">
            <span class="close" onclick="closeModal('passwordModal')">✖</span>
            <h3>Change Password</h3>
            <form method="POST">
                <input type="hidden" name="student_id" id="password_id">
                <input type="password" name="password" placeholder="New Password" required>
                <button type="submit" name="change_password">Update Password</button>
            </form>
        </div>
    </div>

    <script>
        // Modal control
        function openEditModal(id, name, email, phone, department, year) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_phone').value = phone;
            document.getElementById('edit_department').value = department;
            document.getElementById('edit_year').value = year;
            document.getElementById('editModal').style.display = 'flex';
        }
        function openPasswordModal(id) {
            document.getElementById('password_id').value = id;
            document.getElementById('passwordModal').style.display = 'flex';
        }
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>