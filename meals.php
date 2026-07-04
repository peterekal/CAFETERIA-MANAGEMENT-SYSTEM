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

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/meals/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$message = "";
$message_type = "";

// Handle Add Meal
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_meal'])) {
    $meal_name = trim($_POST['meal_name']);
    $description = trim(isset($_POST['description']) ? $_POST['description'] : '');
    $price = floatval($_POST['price']);
    
    $image_path = "";
    
    // Handle Image Upload
    if (isset($_FILES['meal_image']) && $_FILES['meal_image']['error'] == 0 && $_FILES['meal_image']['size'] > 0) {
        $file_name = basename($_FILES['meal_image']['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_ext, $allowed_ext)) {
            $new_file_name = uniqid('meal_') . '.' . $file_ext;
            $file_path = $upload_dir . $new_file_name;
            
            if (move_uploaded_file($_FILES['meal_image']['tmp_name'], $file_path)) {
                $image_path = $file_path;
                error_log("Image uploaded successfully: " . $image_path);
            } else {
                error_log("Failed to move uploaded file");
            }
        } else {
            error_log("Invalid file extension: " . $file_ext);
        }
    }
    
    // Debug: Log what we're about to insert
    error_log("Inserting meal - Name: $meal_name, Image: $image_path, Price: $price");
    
    $stmt = $conn->prepare("INSERT INTO Meals (meal_name, description, price, image_path, available) VALUES (?, ?, ?, ?, 1)");
    if ($stmt) {
        $stmt->bind_param("ssds", $meal_name, $description, $price, $image_path);
        if ($stmt->execute()) {
            $message = "✓ Meal added successfully!";
            $message_type = "success";
            error_log("Meal inserted with image_path: " . $image_path);
        } else {
            $message = "✗ Error adding meal: " . $stmt->error;
            $message_type = "error";
            error_log("Insert error: " . $stmt->error);
        }
        $stmt->close();
    } else {
        $message = "✗ Database error: " . $conn->error;
        $message_type = "error";
        error_log("Prepare error: " . $conn->error);
    }
}

// Handle Delete Meal
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Get image path to delete
    $result = $conn->query("SELECT image_path FROM Meals WHERE meal_id = $id");
    if ($result && $row = $result->fetch_assoc()) {
        if (!empty($row['image_path']) && file_exists($row['image_path'])) {
            unlink($row['image_path']);
            error_log("Image deleted: " . $row['image_path']);
        }
    }
    
    $stmt = $conn->prepare("DELETE FROM Meals WHERE meal_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = "✓ Meal deleted successfully!";
            $message_type = "success";
        } else {
            $message = "✗ Error deleting meal.";
            $message_type = "error";
        }
        $stmt->close();
    }
}

// Handle Edit Meal
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_meal'])) {
    $id = intval($_POST['meal_id']);
    $meal_name = trim($_POST['meal_name']);
    $description = trim(isset($_POST['description']) ? $_POST['description'] : '');
    $price = floatval($_POST['price']);
    
    // Get current image
    $result = $conn->query("SELECT image_path FROM Meals WHERE meal_id = $id");
    $old_image = "";
    if ($result && $row = $result->fetch_assoc()) {
        $old_image = $row['image_path'];
    }
    
    $image_path = $old_image;
    
    // Handle New Image Upload
    if (isset($_FILES['meal_image']) && $_FILES['meal_image']['error'] == 0 && $_FILES['meal_image']['size'] > 0) {
        $file_name = basename($_FILES['meal_image']['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_ext, $allowed_ext)) {
            $new_file_name = uniqid('meal_') . '.' . $file_ext;
            $file_path = $upload_dir . $new_file_name;
            
            if (move_uploaded_file($_FILES['meal_image']['tmp_name'], $file_path)) {
                // Delete old image
                if (!empty($old_image) && file_exists($old_image)) {
                    unlink($old_image);
                    error_log("Old image deleted: " . $old_image);
                }
                $image_path = $file_path;
                error_log("New image uploaded: " . $image_path);
            }
        }
    }
    
    $stmt = $conn->prepare("UPDATE Meals SET meal_name = ?, description = ?, price = ?, image_path = ? WHERE meal_id = ?");
    if ($stmt) {
        $stmt->bind_param("ssdsi", $meal_name, $description, $price, $image_path, $id);
        if ($stmt->execute()) {
            $message = "✓ Meal updated successfully!";
            $message_type = "success";
        } else {
            $message = "✗ Error updating meal.";
            $message_type = "error";
        }
        $stmt->close();
    }
}

// Toggle Availability
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $stmt = $conn->prepare("UPDATE Meals SET available = IF(available=1, 0, 1) WHERE meal_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
}

// Fetch all meals
$meals = $conn->query("SELECT meal_id, meal_name, description, price, image_path, available FROM Meals ORDER BY meal_id DESC");

if (!$meals) {
    echo "Query Error: " . $conn->error;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meals Management - Cafeteria System</title>
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

        /* Meals Grid - 5 Columns */
        .meals-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        /* Meal Card */
        .meal-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .meal-card:hover {
            transform: translateY(-12px) scale(1.02);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.2);
        }

        .meal-image {
            width: 100%;
            height: 140px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            overflow: hidden;
            position: relative;
        }

        .meal-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .meal-card:hover .meal-image img {
            transform: scale(1.1);
        }

        .meal-image.no-image {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }

        .meal-info {
            padding: 15px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .meal-name {
            font-size: 14px;
            font-weight: 700;
            color: #1e1e2e;
            margin-bottom: 6px;
            word-break: break-word;
            line-height: 1.3;
            min-height: 28px;
        }

        .meal-description {
            font-size: 11px;
            color: #666;
            margin-bottom: 10px;
            line-height: 1.4;
            min-height: 30px;
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .meal-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 10px;
            border-top: 1px solid #eee;
            gap: 8px;
        }

        .meal-price {
            font-size: 16px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            white-space: nowrap;
        }

        .meal-actions {
            display: flex;
            gap: 4px;
        }

        .action-btn {
            padding: 6px 8px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 1;
            min-width: 24px;
            height: 24px;
        }

        .btn-edit {
            background: #3498db;
            color: white;
        }

        .btn-edit:hover {
            background: #2980b9;
            transform: scale(1.1);
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .btn-delete:hover {
            background: #c0392b;
            transform: scale(1.1);
        }

        .btn-toggle {
            background: #f39c12;
            color: white;
        }

        .btn-toggle:hover {
            background: #d35400;
            transform: scale(1.1);
        }

        .availability-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .available {
            background: #27ae60;
            color: white;
        }

        .unavailable {
            background: #e74c3c;
            color: white;
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
            padding: 25px;
            border-radius: 12px;
            width: 90%;
            max-width: 380px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
            max-height: 85vh;
            overflow-y: auto;
        }

        .modal-content::-webkit-scrollbar {
            width: 6px;
        }

        .modal-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .modal-content::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 10px;
        }

        .modal-content::-webkit-scrollbar-thumb:hover {
            background: #764ba2;
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
            padding-bottom: 12px;
        }

        .modal-header h2 {
            color: #1e1e2e;
            font-size: 18px;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
            transition: all 0.3s ease;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
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
            margin-bottom: 6px;
            color: #1e1e2e;
            font-weight: 600;
            font-size: 12px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 13px;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 10px rgba(102, 126, 234, 0.2);
            transform: translateY(-2px);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 70px;
        }

        .image-preview {
            width: 100%;
            max-height: 150px;
            border-radius: 8px;
            object-fit: cover;
            margin-top: 8px;
            border: 2px solid #e0e0e0;
        }

        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }

        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }

        .file-input-label {
            display: block;
            padding: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 6px;
            text-align: center;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .file-input-label:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
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

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            grid-column: 1 / -1;
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

        /* Responsive Design */
        @media (max-width: 1920px) {
            .meals-grid {
                grid-template-columns: repeat(5, 1fr);
            }
        }

        @media (max-width: 1440px) {
            .meals-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 1024px) {
            .meals-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 18px;
            }

            .page-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .meal-image {
                height: 120px;
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

            .meals-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }

            .meal-image {
                height: 100px;
            }

            .page-header h1 {
                font-size: 22px;
            }

            .modal-content {
                width: 95%;
                max-width: 350px;
                margin: 30% auto;
            }
        }

        @media (max-width: 480px) {
            .meals-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .meal-image {
                height: 120px;
            }

            .page-header h1 {
                font-size: 18px;
            }

            .modal-content {
                width: 95%;
                max-width: 300px;
                padding: 20px;
                margin: 40% auto;
            }

            .action-btn {
                padding: 5px 6px;
                font-size: 9px;
            }
        }

        /* Scroll Animation */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .meal-card {
            animation: slideInUp 0.5s ease-out forwards;
        }

        .meal-card:nth-child(1) { animation-delay: 0.05s; }
        .meal-card:nth-child(2) { animation-delay: 0.1s; }
        .meal-card:nth-child(3) { animation-delay: 0.15s; }
        .meal-card:nth-child(4) { animation-delay: 0.2s; }
        .meal-card:nth-child(5) { animation-delay: 0.25s; }
        .meal-card:nth-child(6) { animation-delay: 0.3s; }
        .meal-card:nth-child(7) { animation-delay: 0.35s; }
        .meal-card:nth-child(8) { animation-delay: 0.4s; }
        .meal-card:nth-child(9) { animation-delay: 0.45s; }
        .meal-card:nth-child(10) { animation-delay: 0.5s; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-logo">🍽️</div>
        <ul class="sidebar-menu">
            <li><a href="admin_index.php"><span>📊</span><span>Dashboard</span></a></li>
            <li><a href="students.php"><span>👥</span><span>Students</span></a></li>
            <li><a href="meals.php" class="active"><span>🍴</span><span>Meals</span></a></li>
            <li><a href="funds.php"><span>💰</span><span>Funds</span></a></li>
            <li><a href="reports.php"><span>📋</span><span>Reports</span></a></li>
            <li><a href="settings.php"><span>⚙️</span><span>Settings</span></a></li>
            <li style="margin-top: auto;"><a href="logout.php"><span>🚪</span><span>Logout</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>🍴 Meals Menu</h1>
            <button class="add-btn" onclick="openAddModal()">➕ Add New Meal</button>
        </div>

        <!-- Message Alert -->
        <?php if (!empty($message)): ?>
        <div class="message <?php echo $message_type; ?>">
            <span><?php echo $message; ?></span>
        </div>
        <?php endif; ?>

        <!-- Meals Grid - 5 Columns -->
        <?php if ($meals && $meals->num_rows > 0): ?>
        <div class="meals-grid">
            <?php while ($meal = $meals->fetch_assoc()): ?>
            <div class="meal-card">
                <div class="meal-image <?php echo (empty($meal['image_path']) || !file_exists($meal['image_path'])) ? 'no-image' : ''; ?>">
                    <?php if (!empty($meal['image_path']) && file_exists($meal['image_path'])): ?>
                        <img src="<?php echo htmlspecialchars($meal['image_path']); ?>" alt="<?php echo htmlspecialchars($meal['meal_name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <span>🍽️</span>
                    <?php endif; ?>
                    <div class="availability-badge <?php echo $meal['available'] ? 'available' : 'unavailable'; ?>">
                        <?php echo $meal['available'] ? '✓' : '✗'; ?>
                    </div>
                </div>

                <div class="meal-info">
                    <div class="meal-name"><?php echo htmlspecialchars($meal['meal_name']); ?></div>
                    <div class="meal-description"><?php echo !empty($meal['description']) ? htmlspecialchars($meal['description']) : 'No description'; ?></div>

                    <div class="meal-footer">
                        <div class="meal-price">KSh <?php echo htmlspecialchars($meal['price']); ?></div>
                        <div class="meal-actions">
                            <button class="action-btn btn-edit" onclick="openEditModal(<?php echo $meal['meal_id']; ?>, '<?php echo addslashes($meal['meal_name']); ?>', '<?php echo addslashes($meal['description']); ?>', '<?php echo $meal['price']; ?>', '<?php echo htmlspecialchars($meal['image_path']); ?>')" title="Edit">✏️</button>
                            <button class="action-btn btn-toggle" onclick="toggleAvailability(<?php echo $meal['meal_id']; ?>)" title="Toggle"><?php echo $meal['available'] ? '⊘' : '✓'; ?></button>
                            <button class="action-btn btn-delete" onclick="deleteMeal(<?php echo $meal['meal_id']; ?>)" title="Delete">🗑️</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">🍽️</div>
            <h2>No Meals Available</h2>
            <p>Start by adding your first meal to the system.</p>
            <button class="add-btn" onclick="openAddModal()">➕ Add Meal</button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Add Meal Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>➕ Add New Meal</h2>
                <button class="close-btn" onclick="closeModal('addModal')">×</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Meal Name *</label>
                    <input type="text" name="meal_name" placeholder="e.g., Ugali & Sukuma" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Describe the meal..."></textarea>
                </div>

                <div class="form-group">
                    <label>Price (KSh) *</label>
                    <input type="number" name="price" placeholder="100" step="0.01" min="0" required>
                </div>

                <div class="form-group">
                    <label>Meal Image</label>
                    <div class="file-input-wrapper">
                        <input type="file" id="addImage" name="meal_image" accept="image/*" onchange="previewImage(this, 'addPreview')">
                        <label class="file-input-label" for="addImage">📷 Choose Image</label>
                    </div>
                    <img id="addPreview" class="image-preview" style="display: none;">
                </div>

                <button type="submit" name="add_meal" class="submit-btn">➕ Add Meal</button>
            </form>
        </div>
    </div>

    <!-- Edit Meal Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✏️ Edit Meal</h2>
                <button class="close-btn" onclick="closeModal('editModal')">×</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="meal_id" id="editMealId">

                <div class="form-group">
                    <label>Meal Name *</label>
                    <input type="text" id="editName" name="meal_name" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea id="editDescription" name="description"></textarea>
                </div>

                <div class="form-group">
                    <label>Price (KSh) *</label>
                    <input type="number" id="editPrice" name="price" step="0.01" min="0" required>
                </div>

                <div class="form-group">
                    <label>Meal Image</label>
                    <img id="editPreview" class="image-preview" style="display: none;">
                    <div class="file-input-wrapper">
                        <input type="file" id="editImage" name="meal_image" accept="image/*" onchange="previewImage(this, 'editPreview')">
                        <label class="file-input-label" for="editImage">📷 Change Image</label>
                    </div>
                </div>

                <button type="submit" name="edit_meal" class="submit-btn">💾 Save Changes</button>
            </form>
        </div>
    </div>

    <script>
        // Modal Functions
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
            document.getElementById('addImage').value = '';
            document.getElementById('addPreview').style.display = 'none';
        }

        function openEditModal(id, name, description, price, imagePath) {
            document.getElementById('editModal').style.display = 'block';
            document.getElementById('editMealId').value = id;
            document.getElementById('editName').value = name;
            document.getElementById('editDescription').value = description;
            document.getElementById('editPrice').value = price;
            
            if (imagePath && imagePath.trim()) {
                document.getElementById('editPreview').src = imagePath;
                document.getElementById('editPreview').style.display = 'block';
            } else {
                document.getElementById('editPreview').style.display = 'none';
            }
            document.getElementById('editImage').value = '';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function deleteMeal(id) {
            if (confirm('Are you sure you want to delete this meal?')) {
                window.location.href = 'meals.php?delete=' + id;
            }
        }

        function toggleAvailability(id) {
            window.location.href = 'meals.php?toggle=' + id;
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