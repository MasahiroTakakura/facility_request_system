<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// すでにログインしている場合はダッシュボードにリダイレクト
if (isset($_SESSION['username'])) {
    header("Location: dashboard.php");
    exit();
}

generate_csrf_token();

$max_attempts = 5; // 最大試行回数
$lockout_time = 15 * 60; // ロックアウト時間（秒）

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }

    $userid = filter_input(INPUT_POST, 'userid', FILTER_SANITIZE_STRING);
    $password = $_POST['password'];

    $conn = get_db_connection();

    // ユーザー情報を取得
    $sql = "SELECT * FROM users WHERE userid = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $userid);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        // アカウントがロックされているかチェック
        if ($user['login_attempts'] >= $max_attempts && time() - strtotime($user['last_attempt_time']) < $lockout_time) {
            $error_message = "アカウントが一時的にロックされています。しばらく待ってから再試行してください。";
        } else {
            if (password_verify($password, $user['password'])) {
                // ログイン成功
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = $user['is_admin'];

                // ログイン試行回数をリセット
                $reset_sql = "UPDATE users SET login_attempts = 0, last_attempt_time = NULL WHERE userid = ?";
                $reset_stmt = $conn->prepare($reset_sql);
                $reset_stmt->bind_param('s', $userid);
                $reset_stmt->execute();
                $reset_stmt->close();

                header("Location: dashboard.php");
                exit();
            } else {
                // ログイン失敗
                $error_message = "ユーザーIDまたはパスワードが正しくありません。";

                // ログイン試行回数を更新
                $update_sql = "UPDATE users SET login_attempts = login_attempts + 1, last_attempt_time = CURRENT_TIMESTAMP WHERE userid = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param('s', $userid);
                $update_stmt->execute();
                $update_stmt->close();
            }
        }
    } else {
        $error_message = "ユーザーIDまたはパスワードが正しくありません。";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン - 施設予約システム</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            margin-top: 100px;
        }
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem 0 rgba(0, 0, 0, 0.1);
        }
        .card-body {
            padding: 2rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h2 class="text-center mb-4">ログイン</h2>
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo h($error_message); ?>
                            </div>
                        <?php endif; ?>
                        <form method="post" action="login.php">
                            <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                            <div class="form-group">
                                <label for="userid">ユーザーID</label>
                                <input type="text" class="form-control" id="userid" name="userid" required>
                            </div>
                            <div class="form-group">
                                <label for="password">パスワード</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">ログイン</button>
                        </form>
                        <div class="text-center mt-3">
                            <a href="register_user.php">新規登録はこちら</a>
                        </div>
                        <div class="text-center mt-2">
                            <a href="forgot_password.php">パスワードを忘れた場合</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>