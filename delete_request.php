<?php
session_start();
if (!isset($_SESSION['username']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_id'])) {
    $request_id = intval($_POST['request_id']);

    // Include the database configuration file
    require_once 'config.php';

    // Get the database connection
    $conn = get_db_connection();

    // Prepare an SQL statement with placeholders
    $sql = "DELETE FROM requests WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $request_id);

    // Execute the statement
    if ($stmt->execute()) {
        // 成功した場合、管理者ダッシュボードにリダイレクト
        header("Location: admin_dashboard.php");
        exit();
    } else {
        // 失敗した場合、エラーメッセージを表示
        echo "Error deleting record: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>