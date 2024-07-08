<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit();
}

$conn = get_db_connection();

$response = array('success' => false, 'message' => '');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $response['message'] = 'CSRF token validation failed';
    } else {
        $request_id = intval($_POST['request_id']);
        $action = $_POST['action'];
        $status = ($action == 'Approve') ? '承認済み' : '却下';

        // Prepare an SQL statement with placeholders
        $sql = "UPDATE requests SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);

        // Bind the actual values to the placeholders
        $stmt->bind_param('si', $status, $request_id);

        // Execute the statement
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = "リクエストが" . ($status == '承認済み' ? '承認' : '却下') . "されました。";
        } else {
            $response['message'] = "エラー: " . $stmt->error;
        }

        // Close the statement
        $stmt->close();
    }
}

// Set the response header to JSON
header('Content-Type: application/json');
echo json_encode($response);

$conn->close();
?>