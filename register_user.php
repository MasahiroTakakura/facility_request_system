<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
generate_csrf_token();

$conn = get_db_connection();

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }
    $userid = $_POST['userid'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // 入力値の検証
    if (empty($userid) || empty($username) || empty($password)) {
        $error_message = "全てのフィールドを入力してください。";
    } else {
        // ユーザーIDの重複チェック
        $stmt = $conn->prepare("SELECT * FROM users WHERE userid = ?");
        $stmt->bind_param('s', $userid);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $error_message = "このユーザーIDは既に使用されています。";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $sql = "INSERT INTO users (userid, username, password, is_admin) VALUES (?, ?, ?, 0)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sss', $userid, $username, $hashed_password);

            if ($stmt->execute()) {
                $success_message = "登録が完了しました。ログインしてください。";
            } else {
                $error_message = "エラー: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ユーザー登録</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            max-width: 600px;
            margin-top: 50px;
        }
        .form-group label {
            font-weight: bold;
        }
        .btn-block {
            margin-top: 20px;
        }
        .navbar-brand, .nav-link {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <a class="navbar-brand" href="#">施設リクエストシステム</a>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h2 class="text-center">ユーザー登録</h2>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo h($error_message); ?>
                    </div>
                <?php endif; ?>
                <?php if ($success_message): ?>
                    <div class="alert alert-success" role="alert">
                        <?php echo h($success_message); ?>
                    </div>
                <?php endif; ?>
                <form method="post" action="register_user.php">
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                    <div class="form-group">
                        <label for="userid">ユーザーID</label>
                        <input type="text" class="form-control" name="userid" id="userid" required value="<?php echo isset($_POST['userid']) ? h($_POST['userid']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="username">ユーザー名</label>
                        <input type="text" class="form-control" name="username" id="username" required value="<?php echo isset($_POST['username']) ? h($_POST['username']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="password">パスワード</label>
                        <input type="password" class="form-control" name="password" id="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">新規登録</button>
                </form>
                <div class="text-center mt-3">
                    <a href="login.php" class="btn btn-secondary btn-block">ログイン</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>