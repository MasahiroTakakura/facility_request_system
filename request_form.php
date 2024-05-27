<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// データベース設定ファイルを読み込む
require_once 'config.php';

// データベース接続を取得
$conn = get_db_connection();

$sql = "SELECT * FROM facilities";
$result = $conn->query($sql);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_SESSION['username'];
    $facility_id = $_POST['facility_id'];
    $usage_dates = $_POST['usage_dates'];
    $usage_start_times = $_POST['usage_start_times'];
    $usage_end_times = $_POST['usage_end_times'];
    $reason = $_POST['reason'];

    // リクエストごとにレコードを追加
    $stmt = $conn->prepare("INSERT INTO requests (username, facility_id, usage_dates, usage_start_times, usage_end_times, reason) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($usage_dates as $index => $usage_date) {
        $usage_start_time = $usage_start_times[$index];
        $usage_end_time = $usage_end_times[$index];
        $stmt->bind_param('sissss', $username, $facility_id, $usage_date, $usage_start_time, $usage_end_time, $reason);
        $stmt->execute();
    }
    
    echo "<script>alert('リクエストは正常に送信されました'); window.location.href='dashboard.php';</script>";

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>リクエストフォーム</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            max-width: 800px;
        }
        .form-control, .btn {
            margin-bottom: 15px;
        }
        .back-button {
            margin-top: 20px;
        }
        .input-group-append .btn {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }
        .input-group-prepend .btn {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
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

    <div class="container mt-5">
        <h2 class="text-center">施設リクエストフォーム</h2>
        <form method="post" action="request_form.php">
            <div class="form-group">
                <label for="facility_id">施設名</label>
                <select class="form-control" name="facility_id" id="facility_id" required>
                    <option value="">選択してください</option>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($row['id']); ?>"><?php echo htmlspecialchars($row['name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div id="usage-schedule">
                <div class="form-group">
                    <label>使用スケジュール</label>
                    <div class="input-group mb-3">
                        <input type="date" class="form-control" name="usage_dates[]" required>
                        <input type="time" class="form-control" name="usage_start_times[]" required>
                        <input type="time" class="form-control" name="usage_end_times[]" required>
                        <div class="input-group-append">
                            <button type="button" class="btn btn-danger remove-schedule">削除</button>
                        </div>
                    </div>
                </div>
            </div>
            <button type="button" id="add-schedule" class="btn btn-success btn-block">使用スケジュールを追加</button>
            <div class="form-group">
                <label for="reason">理由</label>
                <textarea class="form-control" name="reason" id="reason" rows="3" placeholder="理由を入力してください" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-block">送信</button>
        </form>
        <div class="text-center back-button">
            <a href="dashboard.php" class="btn btn-secondary">ダッシュボードに戻る</a>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#add-schedule').on('click', function() {
                var scheduleTemplate = `
                    <div class="form-group">
                        <div class="input-group mb-3">
                            <input type="date" class="form-control" name="usage_dates[]" required>
                            <input type="time" class="form-control" name="usage_start_times[]" required>
                            <input type="time" class="form-control" name="usage_end_times[]" required>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-danger remove-schedule">削除</button>
                            </div>
                        </div>
                    </div>`;
                $('#usage-schedule').append(scheduleTemplate);
            });

            $(document).on('click', '.remove-schedule', function() {
                $(this).closest('.form-group').remove();
            });
        });
    </script>
</body>
</html>