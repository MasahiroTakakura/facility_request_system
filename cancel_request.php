<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['username'])) {
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
        $username = $_SESSION['username'];

        // リクエストがユーザー自身のものであり、ステータスが'申請中'であることを確認
        $sql = "DELETE FROM requests WHERE id = ? AND username = ? AND status = '申請中'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('is', $request_id, $username);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $response['success'] = true;
                $response['message'] = 'リクエストが正常に取り消されました。';
            } else {
                $response['message'] = 'リクエストの取り消しに失敗しました。リクエストが見つからないか、既に処理されている可能性があります。';
            }
        } else {
            $response['message'] = 'エラー: ' . $stmt->error;
        }

        $stmt->close();
    }
}

// JSONレスポンスを返す
header('Content-Type: application/json');
echo json_encode($response);

$conn->close();
?>