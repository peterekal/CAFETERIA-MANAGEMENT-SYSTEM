<?php
/**
 * check_student_status.php
 * This file should be included at the top of every student page
 * It checks if the student's status is still active
 * If inactive, it logs them out immediately
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only check if student is logged in
if (isset($_SESSION['student_id'])) {
    include("db_connect.php");
    
    $student_id = $_SESSION['student_id'];
    
    // Check current student status from database
    $result = $conn->query("SELECT status FROM Students WHERE student_id = $student_id");
    
    if ($result && $row = $result->fetch_assoc()) {
        // If status is inactive, log out the student
        if ($row['status'] === 'inactive' || strtolower($row['status']) === 'inactive') {
            // Destroy session
            session_unset();
            session_destroy();
            
            // Redirect to login with message
            header("Location: student_login.php?status=inactive");
            exit();
        }
    } else {
        // Student not found in database, log them out
        session_unset();
        session_destroy();
        header("Location: student_login.php");
        exit();
    }
}
?>
