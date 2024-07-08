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

        // リクエストの詳細を取得
        $sql = "SELECT status FROM requests WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $request = $result->fetch_assoc();
        $stmt->close();

        if ($request) {
            // リクエストを削除
            $sql = "DELETE FROM requests WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $request_id);

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $response['success'] = true;
                    $response['message'] = 'リクエストが正常に削除されました。';
                } else {
                    $response['message'] = 'リクエストの削除に失敗しました。';
                }
            } else {
                $response['message'] = 'エラー: ' . $stmt->error;
            }

            $stmt->close();
        } else {
            $response['message'] = 'リクエストが見つかりません。';
        }
    }
}

// JSONレスポンスを返す
header('Content-Type: application/json');
echo json_encode($response);

$conn->close();
?>