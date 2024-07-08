<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit();
}

$conn = get_db_connection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }

    $request_id = intval($_POST['request_id']);
    $action = $_POST['action'];
    $status = ($action == 'Approve') ? '承認済み' : '却下';

    // Prepare an SQL statement with placeholders
    $sql = "UPDATE requests SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);

    // Bind the actual values to the placeholders
    $stmt->bind_param('si', $status, $request_id);

    // Execute the statement
    if ($stmt->execute()) {
        $success_message = "リクエストが" . ($status == '承認済み' ? '承認' : '却下') . "されました。";
    } else {
        $error_message = "エラー: " . $stmt->error;
    }

    // Close the statement
    $stmt->close();
}

// Fetch all pending requests
$sql = "SELECT requests.id, users.username, buildings.name AS building_name, rooms.name AS room_name, 
        requests.usage_dates, requests.usage_start_times, requests.usage_end_times, requests.reason, requests.status
        FROM requests
        JOIN users ON requests.username = users.username
        JOIN rooms ON requests.room_id = rooms.id
        JOIN buildings ON rooms.building_id = buildings.id
        WHERE requests.status = '申請中'";
$result = $conn->query($sql);

?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>リクエスト承認・却下</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            margin-top: 50px;
        }
        .table thead th {
            background-color: #007bff;
            color: white;
        }
        .table tbody tr:nth-child(odd) {
            background-color: #f2f2f2;
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
        <h2 class="text-center mb-4">リクエスト承認・却下</h2>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ユーザー名</th>
                    <th>建物名</th>
                    <th>部屋名</th>
                    <th>使用日</th>
                    <th>開始時間</th>
                    <th>終了時間</th>
                    <th>理由</th>
                    <th>アクション</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['id']); ?></td>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['building_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['room_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['usage_dates']); ?></td>
                    <td><?php echo htmlspecialchars($row['usage_start_times']); ?></td>
                    <td><?php echo htmlspecialchars($row['usage_end_times']); ?></td>
                    <td><?php echo htmlspecialchars($row['reason']); ?></td>
                    <td>
                        <form method="post" action="approve_request.php" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($row['id']); ?>">
                            <button type="submit" name="action" value="Approve" class="btn btn-success btn-sm">承認</button>
                            <button type="submit" name="action" value="Reject" class="btn btn-danger btn-sm">却下</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>

<?php
$conn->close();
?>