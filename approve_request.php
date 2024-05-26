<?php
session_start();
if (!isset($_SESSION['username']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $request_id = intval($_POST['request_id']);
    $action = $_POST['action'];
    $status = ($action == 'Approve') ? '承認済み' : '却下';

    // Include the database configuration file
    require_once 'config.php';

    // Get the database connection
    $conn = get_db_connection();

    // Prepare an SQL statement with placeholders
    $sql = "UPDATE requests SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);

    // Bind the actual values to the placeholders
    $stmt->bind_param('si', $status, $request_id);

    // Execute the statement
    if ($stmt->execute()) {
        echo "Request $status successfully";
    } else {
        echo "Error: " . $stmt->error;
    }

    // Close the statement and connection
    $stmt->close();
    $conn->close();
}

// Redirect to admin dashboard
header("Location: admin_dashboard.php");
exit();
?>