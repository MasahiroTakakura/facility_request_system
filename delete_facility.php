<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

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

    $facility_id = isset($_POST['facility_id']) ? intval($_POST['facility_id']) : 0;

    if ($facility_id > 0) {
        // トランザクション開始
        $conn->begin_transaction();

        try {
            // 関連する予約を削除
            $stmt = $conn->prepare("DELETE FROM requests WHERE room_id = ?");
            $stmt->bind_param("i", $facility_id);
            $stmt->execute();
            $stmt->close();

            // 部屋の利用可能時間を削除
            $stmt = $conn->prepare("DELETE FROM room_availability WHERE room_id = ?");
            $stmt->bind_param("i", $facility_id);
            $stmt->execute();
            $stmt->close();

            // 部屋を削除
            $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
            $stmt->bind_param("i", $facility_id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $conn->commit();
                $message = "施設が正常に削除されました。";
            } else {
                throw new Exception('施設の削除に失敗しました。');
            }

            $stmt->close();
        } catch (Exception $e) {
            $conn->rollback();
            $message = 'エラー: ' . $e->getMessage();
        }
    } else {
        $message = "無効な施設IDです。";
    }
}

// 施設一覧を取得
$sql = "SELECT rooms.id, rooms.name AS room_name, buildings.name AS building_name
        FROM rooms
        JOIN buildings ON rooms.building_id = buildings.id
        ORDER BY buildings.name, rooms.name";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>施設削除</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            max-width: 600px;
            margin-top: 50px;
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
        <h2 class="text-center mb-4">施設削除</h2>

        <?php if ($message): ?>
            <div class="alert <?php echo strpos($message, 'エラー') !== false ? 'alert-danger' : 'alert-success'; ?>" role="alert">
                <?php echo h($message); ?>
            </div>
        <?php endif; ?>

        <form id="delete-facility-form" method="post" action="delete_facility.php">
            <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
            <div class="form-group">
                <label for="facility_id">削除する施設を選択:</label>
                <select class="form-control" name="facility_id" id="facility_id" required>
                    <option value="">選択してください</option>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <option value="<?php echo h($row['id']); ?>">
                            <?php echo h($row['building_name'] . ' - ' . $row['room_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-danger btn-block">削除</button>
        </form>
        <a href="admin_dashboard.php" class="btn btn-secondary btn-block mt-3">管理者ダッシュボードに戻る</a>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#delete-facility-form').on('submit', function(e) {
                if (!confirm('この施設を削除してもよろしいですか？関連するすべての予約も削除されます。')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>