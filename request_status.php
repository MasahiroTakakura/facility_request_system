<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];

// データベース接続
require_once 'config.php';
$conn = get_db_connection();

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT requests.id, facilities.name AS facility, requests.usage_dates, requests.usage_start_times, requests.usage_end_times, requests.reason, requests.status 
        FROM requests 
        JOIN facilities ON requests.facility_id = facilities.id 
        WHERE requests.username = ?";
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
    <title>Request Status</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            max-width: 800px;
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
        <a class="navbar-brand" href="#">Facility Request System</a>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container mt-5">
        <h2 class="text-center">Your Requests</h2>
        <table class="table table-bordered mt-4">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>施設名</th>
                    <th>使用日</th>
                    <th>使用開始時間</th>
                    <th>使用終了時間</th>
                    <th>申請理由</th>
                    <th>ステータス</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['id']); ?></td>
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
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <div class="text-center back-button">
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
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