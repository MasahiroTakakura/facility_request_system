<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

generate_csrf_token();

if (!check_session_timeout()) {
    header("Location: login.php?timeout=1");
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }

    // FILTER_SANITIZE_STRING の代わりに、htmlspecialchars を使用
    $userid = htmlspecialchars($_POST['userid'] ?? '', ENT_QUOTES, 'UTF-8');

    if (empty($userid)) {
        $message = "ユーザーIDを入力してください。";
    } else {
        $conn = get_db_connection();

        // ユーザーの存在確認
        $sql = "SELECT id, username FROM users WHERE userid = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $userid);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user) {
            // トークンの生成
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // トークンの保存
            $sql = "INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('iss', $user['id'], $token, $expires_at);
            $stmt->execute();

            // リセットリンクの生成
            $reset_link = "http://yourdomain.com/reset_password.php?token=" . urlencode($token);

            // ここでメールを送信するか、リセットリンクを表示します
            $message = "パスワードリセットリンク: " . h($reset_link);
            // 注意: 実際の運用では、このリンクをメールで送信し、ここには表示しないようにしてください。

        } else {
            $message = "入力されたユーザーIDに関連するアカウントが見つかりません。";
        }

        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>パスワードリセット</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <h2 class="text-center mb-4">パスワードリセット</h2>
                <?php if ($message): ?>
                    <div class="alert alert-info" role="alert">
                        <?php echo h($message); ?>
                    </div>
                <?php endif; ?>
                <form method="post" action="forgot_password.php">
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                    <div class="form-group">
                        <label for="userid">ユーザーID</label>
                        <input type="text" class="form-control" id="userid" name="userid" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">リセットリンクを送信</button>
                </form>
                <div class="text-center mt-3">
                    <a href="login.php">ログインページに戻る</a>
                </div>
            </div>
        </div>
    </div>
    <script>
    // 5分ごとにセッションをチェック
    setInterval(function() {
        fetch('check_session.php')
            .then(response => response.json())
            .then(data => {
                if (!data.valid) {
                    alert('セッションがタイムアウトしました。再度ログインしてください。');
                    window.location.href = 'login.php?timeout=1';
                }
            });
    }, 5 * 60 * 1000);
</script>
</body>
</html>