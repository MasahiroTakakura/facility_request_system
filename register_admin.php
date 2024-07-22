<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if (!check_session_timeout()) {
    header("Location: login.php?timeout=1");
    exit();
}

if (!isset($_SESSION['username']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit();
}

generate_csrf_token();

$conn = get_db_connection();
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }

    $userid = $_POST['userid'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;

    // Input validation
    if (empty($userid) || empty($username) || empty($password)) {
        $message = "全てのフィールドを入力してください。";
    } else {
        // Check if userid already exists
        $stmt = $conn->prepare("SELECT * FROM users WHERE userid = ?");
        $stmt->bind_param("s", $userid);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = "このユーザーIDは既に使用されています。";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (userid, username, password, is_admin) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $userid, $username, $hashed_password, $is_admin);

            if ($stmt->execute()) {
                $message = "ユーザーが正常に登録されました。";
            } else {
                $message = "エラー: " . $stmt->error;
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
    <title>管理者ユーザー登録</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            max-width: 400px;
            margin-top: 100px;
        }
        .form-control, .btn {
            margin-bottom: 15px;
        }
        .back-button {
            margin-top: 20px;
        }
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
        <h2 class="text-center">管理者ユーザー登録</h2>
        <?php if ($message): ?>
            <div class="alert <?php echo strpos($message, 'エラー') !== false ? 'alert-danger' : 'alert-success'; ?>" role="alert">
                <?php echo h($message); ?>
            </div>
        <?php endif; ?>
        <form method="post" action="register_admin.php">
            <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
            <div class="form-group">
                <label for="userid">ユーザーID</label>
                <input type="text" class="form-control" id="userid" name="userid" required>
            </div>
            <div class="form-group">
                <label for="username">ユーザー名</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">パスワード</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="is_admin" name="is_admin">
                <label class="form-check-label" for="is_admin">管理者権限を付与する</label>
            </div>
            <button type="submit" class="btn btn-primary btn-block">登録</button>
        </form>
        <div class="text-center back-button">
            <a href="admin_dashboard.php" class="btn btn-secondary btn-block">ダッシュボードに戻る</a>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
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