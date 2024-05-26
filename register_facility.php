<?php
session_start();
if (!isset($_SESSION['username']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $facility_name = $_POST['facility_name'];

    // データベース設定ファイルを読み込む
    require_once 'config.php';

    // データベース接続を取得
    $conn = get_db_connection();

    $sql = "INSERT INTO facilities (name) VALUES (?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $facility_name);

    if ($stmt->execute()) {
        echo "<script>alert('施設が正常に登録されました'); window.location.href='admin_dashboard.php';</script>";
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
    <title>施設登録</title>
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
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">ログアウト</a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="text-center w-100">施設登録</h2>
                    <a href="admin_dashboard.php" class="btn btn-secondary">管理者ダッシュボードへ</a>
                </div>
                <form method="post" action="register_facility.php">
                    <div class="form-group">
                        <label for="facility_name">施設名</label>
                        <input type="text" class="form-control" name="facility_name" id="facility_name" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">登録</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>