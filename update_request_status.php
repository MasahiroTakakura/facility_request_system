<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

// 認証とアクセス制御
if (!isset($_SESSION['username']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $response['message'] = 'CSRF token validation failed';
    } else {
        $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
        $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);

        if ($request_id === false || $action === false) {
            $response['message'] = 'Invalid input';
        } else {
            $conn = get_db_connection();

            // トランザクション開始
            $conn->begin_transaction();

            try {
                $status = ($action == 'Approve') ? '承認済み' : '却下';

                $sql = "UPDATE requests SET status = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('si', $status, $request_id);

                if ($stmt->execute()) {
                    // ステータス更新のログを記録
                    $log_sql = "INSERT INTO request_logs (request_id, action, admin_username) VALUES (?, ?, ?)";
                    $log_stmt = $conn->prepare($log_sql);
                    $log_stmt->bind_param('iss', $request_id, $action, $_SESSION['username']);
                    $log_stmt->execute();
                    $log_stmt->close();

                    $conn->commit();
                    $response['success'] = true;
                    $response['message'] = "リクエストが" . h($status) . "されました。";
                } else {
                    throw new Exception("データベース更新エラー");
                }

                $stmt->close();
            } catch (Exception $e) {
                $conn->rollback();
                $response['message'] = "エラー: " . h($e->getMessage());
            }

            $conn->close();
        }
    }
} else {
    $response['message'] = 'Invalid request method';
}

echo json_encode($response);
?>