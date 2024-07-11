<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

generate_csrf_token();

$username = $_SESSION['username'];

$conn = get_db_connection();

$sql = "SELECT requests.id, buildings.name AS building_name, rooms.name AS room_name, requests.usage_dates, requests.usage_start_times, requests.usage_end_times, requests.reason, requests.status
        FROM requests
        JOIN rooms ON requests.room_id = rooms.id
        JOIN buildings ON rooms.building_id = buildings.id
        WHERE requests.username = ?
        ORDER BY requests.usage_dates DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>リクエスト状況</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            max-width: 1000px;
        }
        .table thead th {
            background-color: #007bff;
            color: white;
        }
        .table tbody tr:nth-child(odd) {
            background-color: #f2f2f2;
        }
        .back-button {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <a class="navbar-brand" href="#">施設リクエストシステム</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">ログアウト</a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container mt-5">
        <h2 class="text-center">あなたのリクエスト</h2>
        <div class="table-responsive">
            <table class="table table-bordered mt-4">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>建物名</th>
                        <th>部屋名</th>
                        <th>使用日</th>
                        <th>開始時間</th>
                        <th>終了時間</th>
                        <th>申請理由</th>
                        <th>ステータス</th>
                        <th>アクション</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo h($row['id']); ?></td>
                        <td><?php echo h($row['building_name']); ?></td>
                        <td><?php echo h($row['room_name']); ?></td>
                        <td><?php echo h($row['usage_dates']); ?></td>
                        <td><?php echo h($row['usage_start_times']); ?></td>
                        <td><?php echo h($row['usage_end_times']); ?></td>
                        <td><?php echo h($row['reason']); ?></td>
                        <td>
                            <?php if ($row['status'] == '承認済み'): ?>
                                <span class="badge badge-success"><?php echo h($row['status']); ?></span>
                            <?php elseif ($row['status'] == '却下'): ?>
                                <span class="badge badge-danger"><?php echo h($row['status']); ?></span>
                            <?php else: ?>
                                <span class="badge badge-warning"><?php echo h($row['status']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['status'] == '申請中'): ?>
                                <form method="post" action="cancel_request.php" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="request_id" value="<?php echo h($row['id']); ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">取り消し</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <div class="text-center back-button">
            <a href="dashboard.php" class="btn btn-secondary">ダッシュボードに戻る</a>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>