<?php
session_start();
if (!isset($_SESSION['username']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit();
}

// データベース設定ファイルを読み込む
require_once 'config.php';

// データベース接続を取得
$conn = get_db_connection();

$sql = "SELECT requests.id, users.username, facilities.name AS facility, requests.usage_dates, requests.usage_start_times, requests.usage_end_times, requests.reason, requests.status 
        FROM requests 
        JOIN users ON requests.username = users.userid 
        JOIN facilities ON requests.facility_id = facilities.id";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.10.21/css/jquery.dataTables.min.css" rel="stylesheet">
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
        .badge {
            font-size: 90%;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        #statusFilter {
            margin-bottom: 20px;
        }
        .top-buttons {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <a class="navbar-brand" href="#">Facility Request System</a>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="top-buttons">
            <h2 class="text-center">管理者ダッシュボード</h2>
            <a href="register_facility.php" class="btn btn-primary">施設登録</a>
        </div>
        <label for="statusFilter">ステータスでフィルター:</label>
        <select id="statusFilter" class="form-control">
            <option value="">全て</option>
            <option value="承認済み">承認済み</option>
            <option value="却下">却下</option>
            <option value="保留中">保留中</option>
        </select>
        <table id="requestTable" class="table table-striped table-hover mt-4">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ユーザー名</th>
                    <th>施設名</th>
                    <th>使用日</th>
                    <th>使用開始時間</th>
                    <th>使用終了時間</th>
                    <th>理由</th>
                    <th>ステータス</th>
                    <th>アクション</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['id']); ?></td>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['facility']); ?></td>
                    <td><?php echo htmlspecialchars($row['usage_dates']); ?></td>
                    <td><?php echo htmlspecialchars($row['usage_start_times']); ?></td>
                    <td><?php echo htmlspecialchars($row['usage_end_times']); ?></td>
                    <td><?php echo htmlspecialchars($row['reason']); ?></td>
                    <td>
                        <?php if ($row['status'] == 'Approved'): ?>
                            <span class="badge badge-success"><?php echo htmlspecialchars($row['status']); ?></span>
                        <?php elseif ($row['status'] == 'Rejected'): ?>
                            <span class="badge badge-danger"><?php echo htmlspecialchars($row['status']); ?></span>
                        <?php else: ?>
                            <span class="badge badge-warning"><?php echo htmlspecialchars($row['status']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <form method="post" action="update_request_status.php" class="d-inline">
                                <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($row['id']); ?>">
                                <button type="submit" name="action" value="Approve" class="btn btn-success btn-sm">承認</button>
                            </form>
                            <form method="post" action="update_request_status.php" class="d-inline">
                                <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($row['id']); ?>">
                                <button type="submit" name="action" value="Reject" class="btn btn-danger btn-sm">却下</button>
                            </form>
                            <form method="post" action="delete_request.php" class="d-inline">
                                <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($row['id']); ?>">
                                <button type="submit" name="action" value="Delete" class="btn btn-danger btn-sm">削除</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/plug-ins/1.10.21/i18n/Japanese.json"></script>
    <script>
        $(document).ready(function() {
            var table = $('#requestTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.10.21/i18n/Japanese.json"
                }
            });

            $('#statusFilter').on('change', function() {
                table.column(7).search(this.value).draw();
            });
        });
    </script>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>