<?php
// session_start();
// if (!isset($_SESSION['username']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
//     header("Location: login.php");
//     exit();
// }

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $userid = $_POST['userid'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;

    $conn = get_db_connection();

    // SQLクエリの修正
    $sql = "INSERT INTO users (userid, username, password, is_admin) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssi', $userid, $username, $hashed_password, $is_admin);

    if ($stmt->execute()) {
        echo "<script>alert('ユーザーが正常に登録されました'); window.location.href='admin_dashboard.php';</script>";
    } else {
        echo "<div class='alert alert-danger' role='alert'>Error: " . $stmt->error . "</div>";
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
    </style>
</head>
<body>
    <div class="container">
        <h2 class="text-center">管理者ユーザー登録</h2>
        <form method="post" action="register_admin.php">
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
    </div>
</body>
</html>