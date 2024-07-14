<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

generate_csrf_token();

$message = '';
$token = $_GET['token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }

    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $token = $_POST['token'];

    if ($new_password !== $confirm_password) {
        $message = "パスワードが一致しません。";
    } else {
        $conn = get_db_connection();

        // トークンの有効性確認
        $sql = "SELECT user_id FROM password_reset_tokens WHERE token = ? AND expires_at > NOW()";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $token_data = $result->fetch_assoc();

        if ($token_data) {
            // パスワードの更新
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE users SET password = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param('si', $hashed_password, $token_data['user_id']);
            $update_stmt->execute();

            // 使用済みトークンの削除
            $delete_sql = "DELETE FROM password_reset_tokens WHERE token = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param('s', $token);
            $delete_stmt->execute();

            $message = "パスワードが正常に更新されました。";
        } else {
            $message = "無効または期限切れのトークンです。";
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
    <title>新しいパスワードの設定</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <h2 class="text-center mb-4">新しいパスワードの設定</h2>
                <?php if ($message): ?>
                    <div class="alert alert-info" role="alert">
                        <?php echo h($message); ?>
                    </div>
                <?php endif; ?>
                <form method="post" action="reset_password.php">
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="token" value="<?php echo h($token); ?>">
                    <div class="form-group">
                        <label for="new_password">新しいパスワード</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">新しいパスワード（確認）</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">パスワードを更新</button>
                </form>
                <div class="text-center mt-3">
                    <a href="login.php">ログインページに戻る</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>