<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

generate_csrf_token();

$conn = get_db_connection();

$building_id = isset($_GET['building_id']) ? intval($_GET['building_id']) : '';
$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : '';
$date = isset($_GET['date']) ? $_GET['date'] : '';

$sql_buildings = "SELECT * FROM buildings";
$buildings = $conn->query($sql_buildings);

$rooms = array();
if ($building_id) {
    $sql_rooms = "SELECT * FROM rooms WHERE building_id = ?";
    $stmt = $conn->prepare($sql_rooms);
    $stmt->bind_param('i', $building_id);
    $stmt->execute();
    $rooms = $stmt->get_result();
    $stmt->close();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }
    
    $username = $_SESSION['username'];
    $room_id = isset($_POST['room_id']) ? intval($_POST['room_id']) : 0;
    $usage_dates = $_POST['usage_dates'] ?? [];
    $usage_start_times = $_POST['usage_start_times'] ?? [];
    $usage_end_times = $_POST['usage_end_times'] ?? [];
    $reason = htmlspecialchars($_POST['reason'] ?? '', ENT_QUOTES, 'UTF-8');

    $is_valid = true;
    $errors = [];
    foreach ($usage_dates as $index => $usage_date) {
        $start_time = $usage_start_times[$index];
        $end_time = $usage_end_times[$index];

        // 重複チェック
        $check_sql = "SELECT id FROM requests 
                      WHERE room_id = ? AND usage_dates = ? AND status != '却下' AND
                      ((usage_start_times < ? AND usage_end_times > ?) OR
                       (usage_start_times >= ? AND usage_start_times < ?) OR
                       (usage_end_times > ? AND usage_end_times <= ?))";
        
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('isssssss', $room_id, $usage_date, $end_time, $start_time, $start_time, $end_time, $start_time, $end_time);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $is_valid = false;
            $errors[] = h($usage_date) . " の指定された時間帯は既に予約されています。";
        } else {
            // 利用可能時間チェック
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
                    $errors[] = h($usage_date) . " の利用可能時間は " . h($available_start_time) . " から " . h($available_end_time) . " までです。";
                }
            } else {
                $is_valid = false;
                $errors[] = h($usage_date) . " は利用できません。";
            }
        }
        $check_stmt->close();
    }

    if (!$is_valid) {
        $message = implode("<br>", $errors);
    } else {
        $stmt = $conn->prepare("INSERT INTO requests (username, room_id, usage_dates, usage_start_times, usage_end_times, reason, status) VALUES (?, ?, ?, ?, ?, ?, '申請中')");
        foreach ($usage_dates as $index => $usage_date) {
            $usage_start_time = $usage_start_times[$index];
            $usage_end_time = $usage_end_times[$index];
            $stmt->bind_param('sissss', $username, $room_id, $usage_date, $usage_start_time, $usage_end_time, $reason);
            $stmt->execute();
        }
        $message = "リクエストは正常に送信されました";
        $stmt->close();
    }
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
        <?php if ($message): ?>
            <div class="alert <?php echo strpos($message, 'エラー') !== false ? 'alert-danger' : 'alert-success'; ?>" role="alert">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        <form method="post" action="request_form.php" id="request-form">
            <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
            <div class="form-group">
                <label for="building_id">建物</label>
                <select class="form-control" name="building_id" id="building_id" required>
                    <option value="">選択してください</option>
                    <?php while ($row = $buildings->fetch_assoc()): ?>
                        <option value="<?php echo h($row['id']); ?>" <?php if ($building_id == $row['id']) echo 'selected'; ?>>
                            <?php echo h($row['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="room_id">部屋</label>
                <select class="form-control" name="room_id" id="room_id" required <?php if (!$building_id) echo 'disabled'; ?>>
                    <option value="">先に建物を選択してください</option>
                    <?php if ($rooms): ?>
                        <?php while ($row = $rooms->fetch_assoc()): ?>
                            <option value="<?php echo h($row['id']); ?>" <?php if ($room_id == $row['id']) echo 'selected'; ?>>
                                <?php echo h($row['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div id="usage-schedule">
                <div class="form-group">
                    <label>使用スケジュール</label>
                    <div class="input-group mb-3">
                        <input type="date" class="form-control" name="usage_dates[]" value="<?php echo h($date); ?>" required>
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
            $('#building_id').change(function() {
                var building_id = $(this).val();
                if (building_id) {
                    $.ajax({
                        url: 'get_rooms.php',
                        type: 'POST',
                        dataType: 'json',
                        data: { 
                            building_id: building_id,
                            csrf_token: '<?php echo h($_SESSION['csrf_token']); ?>'
                        },
                        success: function(response) {
                            var options = '<option value="">部屋を選択してください</option>';
                            $.each(response, function(index, room) {
                                options += '<option value="' + room.id + '">' + room.name + '</option>';
                            });
                            $('#room_id').html(options).prop('disabled', false);
                        },
                        error: function(xhr, status, error) {
                            console.error("Error fetching rooms:", error);
                            $('#room_id').html('<option value="">エラーが発生しました</option>').prop('disabled', true);
                        }
                    });
                } else {
                    $('#room_id').html('<option value="">先に建物を選択してください</option>').prop('disabled', true);
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
if ($conn->ping()) {
    $conn->close();
}
?>