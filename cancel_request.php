<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// ユーザーがログインしていない場合、ログインページにリダイレクト
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

generate_csrf_token();

$conn = get_db_connection();

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $response['message'] = 'CSRF token validation failed';
    } else {
        $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
        $username = $_SESSION['username'];

        if ($request_id === false) {
            $response['message'] = 'Invalid request ID';
        } else {
            // トランザクション開始
            $conn->begin_transaction();

            try {
                // リクエストがユーザー自身のものであり、ステータスが'申請中'であることを確認
                $sql = "SELECT * FROM requests WHERE id = ? AND username = ? AND status = '申請中' FOR UPDATE";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('is', $request_id, $username);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 0) {
                    throw new Exception('リクエストが見つからないか、既に処理されています。');
                }

                // リクエストを削除
                $sql = "DELETE FROM requests WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $request_id);
                
                if ($stmt->execute()) {
                    // 削除操作のログを記録
                    $log_sql = "INSERT INTO request_logs (request_id, action, username) VALUES (?, 'Cancelled', ?)";
                    $log_stmt = $conn->prepare($log_sql);
                    $log_stmt->bind_param('is', $request_id, $username);
                    $log_stmt->execute();
                    $log_stmt->close();

                    $conn->commit();
                    $response['success'] = true;
                    $response['message'] = 'リクエストが正常に取り消されました。';
                } else {
                    throw new Exception('リクエストの取り消しに失敗しました。');
                }

                $stmt->close();
            } catch (Exception $e) {
                $conn->rollback();
                $response['message'] = 'エラー: ' . $e->getMessage();
            }
        }
    }
} else {
    $response['message'] = 'Invalid request method';
}

$conn->close();

// JSONレスポンスを返す
header('Content-Type: application/json');
echo json_encode($response);