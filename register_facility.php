<?php
session_start();
if (!isset($_SESSION['username']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit();
}

require_once 'config.php';
$conn = get_db_connection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['building_name'])) {
        // 建物の登録
        $building_name = $_POST['building_name'];
        $sql = "INSERT INTO buildings (name) VALUES (?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $building_name);

        if ($stmt->execute()) {
            echo "<script>alert('建物が正常に登録されました');</script>";
        } else {
            echo "<div class='alert alert-danger' role='alert'>Error: " . $stmt->error . "</div>";
        }

        $stmt->close();
    } elseif (isset($_POST['room_name']) && isset($_POST['building_id'])) {
        // 部屋の登録
        $room_name = $_POST['room_name'];
        $building_id = $_POST['building_id'];
        $sql = "INSERT INTO rooms (building_id, name) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('is', $building_id, $room_name);

        if ($stmt->execute()) {
            echo "<script>alert('部屋が正常に登録されました');</script>";
        } else {
            echo "<div class='alert alert-danger' role='alert'>Error: " . $stmt->error . "</div>";
        }

        $stmt->close();
    } elseif (isset($_POST['delete_building_id'])) {
        // 建物の削除
        $delete_building_id = $_POST['delete_building_id'];
        $sql = "DELETE FROM buildings WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $delete_building_id);

        if ($stmt->execute()) {
            echo "<script>alert('建物が正常に削除されました');</script>";
        } else {
            echo "<div class='alert alert-danger' role='alert'>Error: " . $stmt->error . "</div>";
        }

        $stmt->close();
    } elseif (isset($_POST['delete_room_id'])) {
        // 部屋の削除
        $delete_room_id = $_POST['delete_room_id'];
        $sql = "DELETE FROM rooms WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $delete_room_id);

        if ($stmt->execute()) {
            echo "<script>alert('部屋が正常に削除されました');</script>";
        } else {
            echo "<div class='alert alert-danger' role='alert'>Error: " . $stmt->error . "</div>";
        }

        $stmt->close();
    }
}

// 建物と部屋の一覧を取得
$sql_buildings = "SELECT * FROM buildings";
$buildings = $conn->query($sql_buildings);

$sql_rooms = "SELECT rooms.id, rooms.name AS room_name, buildings.name AS building_name 
              FROM rooms JOIN buildings ON rooms.building_id = buildings.id";
$rooms = $conn->query($sql_rooms);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <title>施設登録</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
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
        .section-title {
            margin-top: 30px;
        }
        .card-header {
            background-color: #007bff;
            color: white;
        }
        .card {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <a class="navbar-brand" href="#">施設リクエストシステム</a>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link" href="admin_dashboard.php">管理者ダッシュボードへ</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">ログアウト</a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">建物の登録</div>
                    <div class="card-body">
                        <form method="post" action="register_facility.php">
                            <div class="form-group">
                                <label for="building_name">建物名</label>
                                <input type="text" class="form-control" name="building_name" id="building_name" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">建物を登録</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">部屋の登録</div>
                    <div class="card-body">
                        <form method="post" action="register_facility.php">
                            <div class="form-group">
                                <label for="building_id">建物</label>
                                <select class="form-control" name="building_id" id="building_id" required>
                                    <option value="">選択してください</option>
                                    <?php while ($row = $buildings->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($row['id']); ?>"><?php echo htmlspecialchars($row['name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="room_name">部屋名</label>
                                <input type="text" class="form-control" name="room_name" id="room_name" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">部屋を登録</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <h3 class="text-center section-title">登録済みの建物</h3>
        <table class="table table-bordered mt-3">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>建物名</th>
                    <th>アクション</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $buildings->data_seek(0); // 再度ループするためにポインタをリセット
                while ($row = $buildings->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['id']); ?></td>
                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                    <td>
                        <form method="post" action="register_facility.php" style="display:inline;">
                            <input type="hidden" name="delete_building_id" value="<?php echo htmlspecialchars($row['id']); ?>">
                            <button type="submit" class="btn btn-danger btn-sm">削除</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <h3 class="text-center section-title">登録済みの部屋</h3>
        <table class="table table-bordered mt-3">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>建物名</th>
                    <th>部屋名</th>
                    <th>アクション</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $rooms->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['id']); ?></td>
                    <td><?php echo htmlspecialchars($row['building_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['room_name']); ?></td>
                    <td>
                        <form method="post" action="register_facility.php" style="display:inline;">
                            <input type="hidden" name="delete_room_id" value="<?php echo htmlspecialchars($row['id']); ?>">
                            <button type="submit" class="btn btn-danger btn-sm">削除</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js”>
    </body>
</html>
<?php
$conn->close();
?>
