<?php
include("db_connect.php");

echo "<h2>Meals Debug Information</h2>";

// Check if uploads directory exists
$upload_dir = 'uploads/meals/';
echo "<p><strong>Upload Directory:</strong> " . $upload_dir . "</p>";
echo "<p><strong>Directory Exists:</strong> " . (is_dir($upload_dir) ? "✓ YES" : "✗ NO") . "</p>";
echo "<p><strong>Directory Writable:</strong> " . (is_writable($upload_dir) ? "✓ YES" : "✗ NO") . "</p>";

// Check database
$result = $conn->query("SELECT meal_id, meal_name, image_path, description, price FROM Meals LIMIT 5");

if ($result) {
    echo "<h3>Meals in Database:</h3>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Name</th><th>Image Path</th><th>Image Exists</th><th>Description</th><th>Price</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        $image_exists = (file_exists($row['image_path'])) ? "✓ YES" : "✗ NO";
        echo "<tr>";
        echo "<td>" . $row['meal_id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['meal_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['image_path']) . "</td>";
        echo "<td>" . $image_exists . "</td>";
        echo "<td>" . htmlspecialchars(substr($row['description'], 0, 50)) . "</td>";
        echo "<td>" . $row['price'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>✗ Error fetching meals: " . $conn->error . "</p>";
}

// List files in uploads/meals/
echo "<h3>Files in uploads/meals/:</h3>";
if (is_dir($upload_dir)) {
    $files = scandir($upload_dir);
    echo "<ul>";
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "<li>" . htmlspecialchars($file) . "</li>";
        }
    }
    echo "</ul>";
} else {
    echo "<p>✗ Directory does not exist</p>";
}
?>
