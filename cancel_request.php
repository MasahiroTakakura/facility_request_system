<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_id'])) {
    $request_id = $_POST['request_id'];
    $username = $_SESSION['username'];

    // データベース設定ファイルを読み込む
    require_once 'config.php';
    $conn = get_db_connection();

    // リクエストがユーザー自身のものであり、ステータスがPendingであることを確認
    $sql = "DELETE FROM requests WHERE id = ? AND username = ? AND status = '申請中'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $request_id, $username);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo "<script>alert('リクエストが正常に取り消されました'); window.location.href='request_status.php';</script>";
    } else {
        echo "<script>alert('リクエストの取り消しに失敗しました'); window.location.href='request_status.php';</script>";
    }

    $stmt->close();
    $conn->close();
} else {
    header("Location: request_status.php");
    exit();
}
?>