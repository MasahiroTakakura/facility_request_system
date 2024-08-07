<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// 認証とアクセス制御
if (!isset($_SESSION['username']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit();
}

// セッションタイムアウトチェック
if (!check_session_timeout()) {
    header("Location: login.php?timeout=1");
    exit();
}

generate_csrf_token();

$conn = get_db_connection();
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }

    $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';
    $action = htmlspecialchars($action, ENT_QUOTES, 'UTF-8');

    if ($request_id === false || !in_array($action, ['Approve', 'Reject'])) {
        $message = "Invalid input.";
    } else {
        $status = ($action == 'Approve') ? '承認済み' : '却下';

        // トランザクション開始
        $conn->begin_transaction();

        try {
            // リクエストのステータス更新
            $sql = "UPDATE requests SET status = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('si', $status, $request_id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                // リクエストのユーザー名を取得
                $user_query = "SELECT username FROM requests WHERE id = ?";
                $user_stmt = $conn->prepare($user_query);
                $user_stmt->bind_param('i', $request_id);
                $user_stmt->execute();
                $user_result = $user_stmt->get_result();
                $user = $user_result->fetch_assoc();
                $user_stmt->close();

                // ユーザー名からユーザーIDを取得
                $user_id_query = "SELECT id FROM users WHERE username = ?";
                $user_id_stmt = $conn->prepare($user_id_query);
                $user_id_stmt->bind_param('s', $user['username']);
                $user_id_stmt->execute();
                $user_id_result = $user_id_stmt->get_result();
                $user_id_data = $user_id_result->fetch_assoc();
                $user_id_stmt->close();

                if ($user_id_data) {
                    $user_id = $user_id_data['id'];
                    $notification_message = "あなたのリクエスト（ID: {$request_id}）が{$status}されました。";
                    create_notification($user_id, $notification_message);
                } else {
                    // ユーザーIDが見つからない場合のエラー処理
                    throw new Exception("User ID not found for username: " . $user['username']);
                }

                $conn->commit();
                $message = "リクエストが" . h($status) . "されました。";
            } else {
                throw new Exception('リクエストの更新に失敗しました。');
            }
        } catch (Exception $e) {
            $conn->rollback();
            $message = 'エラー: ' . h($e->getMessage());
            error_log('Approve request error: ' . $e->getMessage());
        }

        $stmt->close();
    }
}

// 保留中のリクエストを取得
$sql = "SELECT requests.id, users.username, buildings.name AS building_name, rooms.name AS room_name, 
        requests.usage_dates, requests.usage_start_times, requests.usage_end_times, requests.reason, requests.status
        FROM requests
        JOIN users ON requests.username = users.username
        JOIN rooms ON requests.room_id = rooms.id
        JOIN buildings ON rooms.building_id = buildings.id
        WHERE requests.status = '申請中'";
$result = $conn->query($sql);

$conn->close();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>リクエスト承認・却下</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .container { margin-top: 50px; }
        .table thead th { background-color: #007bff; color: white; }
        .table tbody tr:nth-child(odd) { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <a class="navbar-brand" href="#">施設リクエストシステム</a>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link" href="admin_dashboard.php">管理者ダッシュボード</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">ログアウト</a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <h2 class="text-center mb-4">リクエスト承認・却下</h2>

        <?php if ($message): ?>
            <div class="alert <?php echo strpos($message, 'エラー') !== false ? 'alert-danger' : 'alert-success'; ?>" role="alert">
                <?php echo h($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($result->num_rows > 0): ?>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ユーザー名</th>
                        <th>建物名</th>
                        <th>部屋名</th>
                        <th>使用日</th>
                        <th>開始時間</th>
                        <th>終了時間</th>
                        <th>理由</th>
                        <th>アクション</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo h($row['id']); ?></td>
                        <td><?php echo h($row['username']); ?></td>
                        <td><?php echo h($row['building_name']); ?></td>
                        <td><?php echo h($row['room_name']); ?></td>
                        <td><?php echo h($row['usage_dates']); ?></td>
                        <td><?php echo h($row['usage_start_times']); ?></td>
                        <td><?php echo h($row['usage_end_times']); ?></td>
                        <td><?php echo h($row['reason']); ?></td>
                        <td>
                            <form method="post" action="approve_request.php" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="request_id" value="<?php echo h($row['id']); ?>">
                                <button type="submit" name="action" value="Approve" class="btn btn-success btn-sm">承認</button>
                                <button type="submit" name="action" value="Reject" class="btn btn-danger btn-sm">却下</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-center">現在、承認待ちのリクエストはありません。</p>
        <?php endif; ?>

        <!-- 管理者ダッシュボードへ戻るボタン -->
        <div class="text-center mt-4">
            <a href="admin_dashboard.php" class="btn btn-primary">管理者ダッシュボードへ戻る</a>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <!-- セッションタイムアウトチェック用のJavaScript -->
    <script>
    setInterval(function() {
        fetch('check_session.php')
            .then(response => response.json())
            .then(data => {
                if (!data.valid) {
                    alert('セッションがタイムアウトしました。再度ログインしてください。');
                    window.location.href = 'login.php?timeout=1';
                }
            });
    }, 5 * 60 * 1000); // 5分ごとにチェック
    </script>
</body>
</html>