<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// ユーザーがログインしていない場合、ログインページにリダイレクト
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$is_admin = $_SESSION['is_admin'] ?? false;

// 管理者の場合、admin_dashboard.phpにリダイレクト
if ($is_admin) {
    header("Location: admin_dashboard.php");
    exit();
}

generate_csrf_token();

$conn = get_db_connection();

// ユーザーの最新のリクエストを取得
$sql = "SELECT r.id, b.name AS building_name, ro.name AS room_name, r.usage_dates, r.usage_start_times, r.usage_end_times, r.status
        FROM requests r
        JOIN rooms ro ON r.room_id = ro.id
        JOIN buildings b ON ro.building_id = b.id
        WHERE r.username = ?
        ORDER BY r.id DESC
        LIMIT 5";

$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();

// ユーザーの通知を取得
$notifications = get_user_notifications($username);

$conn->close();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ダッシュボード</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding-top: 60px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
        <a class="navbar-brand" href="#">施設リクエストシステム</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNavDropdown">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a href="edit_profile.php" class="nav-link">プロフィール編集</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">ログアウト</a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <h1 class="my-4">ようこそ、<?php echo h($username); ?>さん</h1>
        
        <div class="row">
            <div class="col-md-8">
                <h2>最近のリクエスト</h2>
                <?php if ($result->num_rows > 0): ?>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>建物</th>
                                <th>部屋</th>
                                <th>日付</th>
                                <th>時間</th>
                                <th>状態</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo h($row['building_name']); ?></td>
                                    <td><?php echo h($row['room_name']); ?></td>
                                    <td><?php echo h($row['usage_dates']); ?></td>
                                    <td><?php echo h($row['usage_start_times']) . ' - ' . h($row['usage_end_times']); ?></td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        switch ($row['status']) {
                                            case '申請中':
                                                $status_class = 'text-warning';
                                                break;
                                            case '承認済み':
                                                $status_class = 'text-success';
                                                break;
                                            case '却下':
                                                $status_class = 'text-danger';
                                                break;
                                        }
                                        ?>
                                        <span class="<?php echo $status_class; ?>"><?php echo h($row['status']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($row['status'] == '申請中'): ?>
                                            <a href="edit_request.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">変更</a>
                                            <button class="btn btn-sm btn-danger cancel-request" data-id="<?php echo $row['id']; ?>">キャンセル</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>最近のリクエストはありません。</p>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <h2>通知</h2>
                <?php if (empty($notifications)): ?>
                    <p>新しい通知はありません。</p>
                <?php else: ?>
                    <ul class="list-group">
                        <?php foreach ($notifications as $notification): ?>
                            <li class="list-group-item">
                                <?php echo h($notification['message']); ?>
                                <small class="text-muted d-block"><?php echo h($notification['created_at']); ?></small>
                                <form method="post" action="mark_notification_read.php" class="mt-2">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="notification_id" value="<?php echo h($notification['id']); ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">既読にする</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <h2 class="mt-4">クイックリンク</h2>
                <ul class="list-group">
                    <li class="list-group-item">
                        <a href="request_form.php" class="btn btn-primary btn-block">新規リクエスト</a>
                    </li>
                    <li class="list-group-item">
                        <a href="availability.php" class="btn btn-info btn-block">空き状況確認</a>
                    </li>
                    <li class="list-group-item">
                        <a href="request_status.php" class="btn btn-secondary btn-block">リクエスト状況確認</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.cancel-request').click(function() {
                var requestId = $(this).data('id');
                if (confirm('このリクエストをキャンセルしてもよろしいですか？')) {
                    $.post('cancel_request.php', { 
                        request_id: requestId,
                        csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
                    }, function(response) {
                        if (response.success) {
                            alert('リクエストがキャンセルされました。');
                            location.reload();
                        } else {
                            alert('キャンセルに失敗しました: ' + response.message);
                        }
                    }, 'json');
                }
            });
        });
    </script>
</body>
</html>