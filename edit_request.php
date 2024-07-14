<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// ユーザーがログインしていない場合、ログインページにリダイレクト
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

generate_csrf_token();

$username = $_SESSION['username'];
$request_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$conn = get_db_connection();

// リクエストの情報を取得
$sql = "SELECT r.*, b.name AS building_name, ro.name AS room_name, ro.id AS room_id, b.id AS building_id
        FROM requests r
        JOIN rooms ro ON r.room_id = ro.id
        JOIN buildings b ON ro.building_id = b.id
        WHERE r.id = ? AND r.username = ? AND r.status = '申請中'";

$stmt = $conn->prepare($sql);
$stmt->bind_param('is', $request_id, $username);
$stmt->execute();
$result = $stmt->get_result();
$request = $result->fetch_assoc();

if (!$request) {
    die('リクエストが見つからないか、編集権限がありません。');
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }

    $new_room_id = intval($_POST['room_id']);
    $new_usage_date = $_POST['usage_date'];
    $new_start_time = $_POST['start_time'];
    $new_end_time = $_POST['end_time'];
    $new_reason = $_POST['reason'];

    // 利用可能時間をチェック
    $availability_sql = "SELECT * FROM room_availability WHERE room_id = ? AND date = ? AND ? >= available_start_time AND ? <= available_end_time";
    $availability_stmt = $conn->prepare($availability_sql);
    $availability_stmt->bind_param('isss', $new_room_id, $new_usage_date, $new_start_time, $new_end_time);
    $availability_stmt->execute();
    $availability_result = $availability_stmt->get_result();

    if ($availability_result->num_rows > 0) {
        // リクエストを更新
        $update_sql = "UPDATE requests SET room_id = ?, usage_dates = ?, usage_start_times = ?, usage_end_times = ?, reason = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param('issssi', $new_room_id, $new_usage_date, $new_start_time, $new_end_time, $new_reason, $request_id);

        if ($update_stmt->execute()) {
            $message = "リクエストが正常に更新されました。";
            // 更新後の情報を再取得
            $stmt->execute();
            $result = $stmt->get_result();
            $request = $result->fetch_assoc();
        } else {
            $message = "リクエストの更新に失敗しました。";
        }
    } else {
        $message = "選択した時間帯は利用できません。";
    }
}

// 建物一覧を取得
$buildings_sql = "SELECT * FROM buildings";
$buildings_result = $conn->query($buildings_sql);

// 部屋一覧を取得
$rooms_sql = "SELECT * FROM rooms WHERE building_id = ?";
$rooms_stmt = $conn->prepare($rooms_sql);
$rooms_stmt->bind_param('i', $request['building_id']);
$rooms_stmt->execute();
$rooms_result = $rooms_stmt->get_result();

$conn->close();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>リクエスト編集</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>リクエスト編集</h1>
        <?php if ($message): ?>
            <div class="alert alert-info" role="alert">
                <?php echo h($message); ?>
            </div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
            <div class="form-group">
                <label for="building_id">建物</label>
                <select class="form-control" id="building_id" required>
                    <?php while ($building = $buildings_result->fetch_assoc()): ?>
                        <option value="<?php echo h($building['id']); ?>" <?php echo $building['id'] == $request['building_id'] ? 'selected' : ''; ?>>
                            <?php echo h($building['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="room_id">部屋</label>
                <select class="form-control" id="room_id" name="room_id" required>
                    <?php while ($room = $rooms_result->fetch_assoc()): ?>
                        <option value="<?php echo h($room['id']); ?>" <?php echo $room['id'] == $request['room_id'] ? 'selected' : ''; ?>>
                            <?php echo h($room['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="usage_date">利用日</label>
                <input type="date" class="form-control" id="usage_date" name="usage_date" value="<?php echo h($request['usage_dates']); ?>" required>
            </div>
            <div class="form-group">
                <label for="start_time">開始時間</label>
                <input type="time" class="form-control" id="start_time" name="start_time" value="<?php echo h($request['usage_start_times']); ?>" required>
            </div>
            <div class="form-group">
                <label for="end_time">終了時間</label>
                <input type="time" class="form-control" id="end_time" name="end_time" value="<?php echo h($request['usage_end_times']); ?>" required>
            </div>
            <div class="form-group">
                <label for="reason">理由</label>
                <textarea class="form-control" id="reason" name="reason" rows="3" required><?php echo h($request['reason']); ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">更新</button>
            <a href="dashboard.php" class="btn btn-secondary">キャンセル</a>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#building_id').change(function() {
                var building_id = $(this).val();
                $.ajax({
                    url: 'get_rooms.php',
                    method: 'POST',
                    data: { building_id: building_id, csrf_token: '<?php echo h($_SESSION['csrf_token']); ?>' },
                    success: function(data) {
                        $('#room_id').html(data);
                    }
                });
            });
        });
    </script>
</body>
</html>