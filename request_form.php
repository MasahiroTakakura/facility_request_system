<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

require_once 'config.php';
$conn = get_db_connection();

$building_id = isset($_GET['building_id']) ? $_GET['building_id'] : '';
$room_id = isset($_GET['room_id']) ? $_GET['room_id'] : '';
$date = isset($_GET['date']) ? $_GET['date'] : '';

$sql_buildings = "SELECT * FROM buildings";
$buildings = $conn->query($sql_buildings);

$sql_rooms = $building_id ? "SELECT * FROM rooms WHERE building_id = $building_id" : "SELECT * FROM rooms";
$rooms = $conn->query($sql_rooms);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_SESSION['username'];
    $room_id = $_POST['room_id'];
    $usage_dates = $_POST['usage_dates'];
    $usage_start_times = $_POST['usage_start_times'];
    $usage_end_times = $_POST['usage_end_times'];
    $reason = $_POST['reason'];

    $is_valid = true;
    $errors = [];
    foreach ($usage_dates as $index => $usage_date) {
        $start_time = $usage_start_times[$index];
        $end_time = $usage_end_times[$index];

        $availability_query = "SELECT available_start_time, available_end_time FROM room_availability WHERE room_id = ? AND date = ?";
        $stmt = $conn->prepare($availability_query);
        $stmt->bind_param('is', $room_id, $usage_date);
        $stmt->execute();
        $stmt->bind_result($available_start_time, $available_end_time);
        $stmt->fetch();
        $stmt->close();

        if ($available_start_time && $available_end_time) {
            if ($start_time < $available_start_time || $end_time > $available_end_time || $start_time >= $end_time) {
                $is_valid = false;
                $errors[] = "$usage_date の利用可能時間は $available_start_time から $available_end_time までです。管理者に問い合わせてください。";
            }
        } else {
            $is_valid = false;
            $errors[] = "$usage_date は利用できません。管理者に問い合わせてください。";
        }
    }

    if (!$is_valid) {
        foreach ($errors as $error) {
            echo "<script>alert('$error');</script>";
        }
    }

    // リクエストごとにレコードを追加
    $stmt = $conn->prepare("INSERT INTO requests (username, room_id, usage_dates, usage_start_times, usage_end_times, reason, status) VALUES (?, ?, ?, ?, ?, ?, '申請中')");
    foreach ($usage_dates as $index => $usage_date) {
        $usage_start_time = $usage_start_times[$index];
        $usage_end_time = $usage_end_times[$index];
        $stmt->bind_param('sissss', $username, $room_id, $usage_date, $usage_start_time, $usage_end_time, $reason);
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
            margin-top: 50px;
        }
        .form-control, .btn {
            margin-bottom: 15px;
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
                <label for="building_id">建物</label>
                <select class="form-control" name="building_id" id="building_id" required>
                    <option value="">選択してください</option>
                    <?php while ($row = $buildings->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($row['id']); ?>" <?php if ($building_id == $row['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($row['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="room_id">部屋</label>
                <select class="form-control" name="room_id" id="room_id" required>
                    <option value="">先に建物を選択してください</option>
                    <?php while ($row = $rooms->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($row['id']); ?>" <?php if ($room_id == $row['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($row['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div id="usage-schedule">
                <div class="form-group">
                    <label>使用スケジュール</label>
                    <div class="input-group mb-3">
                        <input type="date" class="form-control" name="usage_dates[]" value="<?php echo htmlspecialchars($date); ?>" required>
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
            <a href="availability.php" class="btn btn-secondary btn-block">空き状況確認に戻る</a>
            <a href="dashboard.php" class="btn btn-info btn-block">ダッシュボードに戻る</a>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            var initialBuildingId = '<?php echo $building_id; ?>';
            if (initialBuildingId) {
                $.ajax({
                    type: 'POST',
                    url: 'get_rooms.php',
                    data: { building_id: initialBuildingId },
                    success: function(html) {
                        $('#room_id').html(html);
                        $('#room_id').val('<?php echo $room_id; ?>');
                    }
                });
            }

            $('#building_id').on('change', function() {
                var building_id = $(this).val();
                if (building_id) {
                    $.ajax({
                        type: 'POST',
                        url: 'get_rooms.php',
                        data: 'building_id=' + building_id,
                        success: function(html) {
                            $('#room_id').html(html);
                        }
                    });
                } else {
                    $('#room_id').html('<option value="">先に建物を選択してください</option>');
                }
            });

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
<?php
$conn->close();
?>