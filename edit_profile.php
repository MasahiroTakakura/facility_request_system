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

$username = $_SESSION['username'];
$message = '';

$conn = get_db_connection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }

    $new_username = htmlspecialchars($_POST['new_username'] ?? '', ENT_QUOTES, 'UTF-8');
    $new_userid = htmlspecialchars($_POST['new_userid'] ?? '', ENT_QUOTES, 'UTF-8');
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // 現在のパスワードを確認
    $stmt = $conn->prepare("SELECT password FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (password_verify($current_password, $user['password'])) {
        $update_fields = [];
        $types = '';
        $params = [];

        if (!empty($new_username) && $new_username !== $username) {
            $update_fields[] = "username = ?";
            $types .= 's';
            $params[] = $new_username;
        }

        if (!empty($new_userid)) {
            $update_fields[] = "userid = ?";
            $types .= 's';
            $params[] = $new_userid;
        }

        if (!empty($new_password)) {
            if ($new_password === $confirm_password) {
                $update_fields[] = "password = ?";
                $types .= 's';
                $params[] = password_hash($new_password, PASSWORD_DEFAULT);
            } else {
                $message = "新しいパスワードが一致しません。";
            }
        }

        if (!empty($update_fields) && empty($message)) {
            $sql = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE username = ?";
            $types .= 's';
            $params[] = $username;

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $message = "プロフィールが更新されました。";
                if (isset($new_username) && $new_username !== $username) {
                    $_SESSION['username'] = $new_username;
                }
            } else {
                $message = "エラーが発生しました。もう一度お試しください。";
            }
        }
    } else {
        $message = "現在のパスワードが正しくありません。";
    }
}

// ユーザー情報を取得
$stmt = $conn->prepare("SELECT username, userid FROM users WHERE username = ?");
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>プロフィール編集</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>プロフィール編集</h2>
        <?php if ($message): ?>
            <div class="alert alert-info" role="alert">
                <?php echo h($message); ?>
            </div>
        <?php endif; ?>
        <form method="post" action="edit_profile.php">
            <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
            <div class="form-group">
                <label for="new_username">ユーザー名</label>
                <input type="text" class="form-control" id="new_username" name="new_username" value="<?php echo h($user['username']); ?>">
            </div>
            <div class="form-group">
                <label for="new_userid">ユーザーID</label>
                <input type="text" class="form-control" id="new_userid" name="new_userid" value="<?php echo h($user['userid']); ?>">
            </div>
            <div class="form-group">
                <label for="current_password">現在のパスワード（必須）</label>
                <input type="password" class="form-control" id="current_password" name="current_password" required>
            </div>
            <div class="form-group">
                <label for="new_password">新しいパスワード（変更する場合のみ）</label>
                <input type="password" class="form-control" id="new_password" name="new_password">
            </div>
            <div class="form-group">
                <label for="confirm_password">新しいパスワード（確認）</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
            </div>
            <button type="submit" class="btn btn-primary">更新</button>
        </form>
        <a href="dashboard.php" class="btn btn-secondary mt-3">ダッシュボードに戻る</a>
    </div>
</body>
</html>