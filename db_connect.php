<?php
$servername = "localhost";   // XAMPP default
$username   = "root";        // default MySQL user
$password   = "";            // default MySQL password is empty
$dbname     = "cafeteria";   // your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
