<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit();
}

$conn = get_db_connection();

$response = array('success' => false, 'message' => '');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $response['message'] = 'CSRF token validation failed';
    } else {
        $facility_id = intval($_POST['facility_id']);

        // トランザクション開始
        $conn->begin_transaction();

        try {
            // 関連する予約を削除
            $sql = "DELETE FROM requests WHERE room_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $facility_id);
            $stmt->execute();
            $stmt->close();

            // 部屋の利用可能時間を削除
            $sql = "DELETE FROM room_availability WHERE room_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $facility_id);
            $stmt->execute();
            $stmt->close();

            // 部屋を削除
            $sql = "DELETE FROM rooms WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $facility_id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $conn->commit();
                $response['success'] = true;
                $response['message'] = '施設が正常に削除されました。';
            } else {
                throw new Exception('施設の削除に失敗しました。');
            }

            $stmt->close();
        } catch (Exception $e) {
            $conn->rollback();
            $response['message'] = 'エラー: ' . $e->getMessage();
        }
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

    <div class="container mt-5">
        <h2 class="text-center mb-4">施設削除</h2>

        <?php if ($response['message']): ?>
            <div class="alert <?php echo $response['success'] ? 'alert-success' : 'alert-danger'; ?>" role="alert">
                <?php echo htmlspecialchars($response['message']); ?>
            </div>
        <?php endif; ?>

        <form id="delete-facility-form" method="post" action="delete_facility.php">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="form-group">
                <label for="facility_id">削除する施設を選択:</label>
                <select class="form-control" name="facility_id" id="facility_id" required>
                    <option value="">選択してください</option>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($row['id']); ?>">
                            <?php echo htmlspecialchars($row['building_name'] . ' - ' . $row['room_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-danger btn-block">削除</button>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#delete-facility-form').on('submit', function(e) {
                e.preventDefault();
                if (confirm('この施設を削除してもよろしいですか？関連するすべての予約も削除されます。')) {
                    this.submit();
                }
            });
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>