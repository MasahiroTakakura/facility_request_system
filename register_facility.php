<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
require_once 'vendor/autoload.php'; // PhpSpreadsheetのオートロード

if (!check_session_timeout()) {
    header("Location: login.php?timeout=1");
    exit();
}

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION['username']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit();
}

generate_csrf_token();

$conn = get_db_connection();
$inserted_rows = [];
$messages = [];

function register_facility($building_name, $room_name, $date, $available_start_time, $available_end_time) {
    global $conn, $inserted_rows, $messages;

    if (empty($building_name) || empty($room_name) || empty($date) || empty($available_start_time) || empty($available_end_time)) {
        $messages[] = ['type' => 'danger', 'text' => "全てのフィールドを入力してください。"];
        return false;
    }

    if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $date)) {
        $messages[] = ['type' => 'danger', 'text' => "無効な日付形式です。YYYY-MM-DD形式で入力してください。"];
        return false;
    }

    if (!preg_match("/^(?:2[0-3]|[01][0-9]):[0-5][0-9]$/", $available_start_time) || 
        !preg_match("/^(?:2[0-3]|[01][0-9]):[0-5][0-9]$/", $available_end_time)) {
        $messages[] = ['type' => 'danger', 'text' => "無効な時間形式です。HH:MM形式で入力してください。"];
        return false;
    }

    $stmt = $conn->prepare("SELECT id FROM buildings WHERE name = ?");
    $stmt->bind_param('s', $building_name);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $building_id = $result->fetch_assoc()['id'];
    } else {
        $stmt = $conn->prepare("INSERT INTO buildings (name) VALUES (?)");
        $stmt->bind_param('s', $building_name);
        $stmt->execute();
        $building_id = $stmt->insert_id;
    }

    $stmt = $conn->prepare("SELECT id FROM rooms WHERE name = ? AND building_id = ?");
    $stmt->bind_param('si', $room_name, $building_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $room_id = $result->fetch_assoc()['id'];
    } else {
        $stmt = $conn->prepare("INSERT INTO rooms (building_id, name) VALUES (?, ?)");
        $stmt->bind_param('is', $building_id, $room_name);
        $stmt->execute();
        $room_id = $stmt->insert_id;
    }

    $stmt = $conn->prepare("INSERT INTO room_availability (room_id, date, available_start_time, available_end_time) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('isss', $room_id, $date, $available_start_time, $available_end_time);
    $stmt->execute();

    $inserted_rows[] = [
        'building_name' => $building_name,
        'room_name' => $room_name,
        'date' => $date,
        'available_start_time' => $available_start_time,
        'available_end_time' => $available_end_time
    ];

    return true;
}

function delete_facility($building_name, $room_name, $date) {
    global $conn, $messages;

    $stmt = $conn->prepare("DELETE FROM room_availability WHERE room_id = (SELECT id FROM rooms WHERE name = ? AND building_id = (SELECT id FROM buildings WHERE name = ?)) AND date = ?");
    $stmt->bind_param('sss', $room_name, $building_name, $date);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $messages[] = ['type' => 'success', 'text' => "施設が正常に削除されました。"];
    } else {
        $messages[] = ['type' => 'danger', 'text' => "施設の削除に失敗しました。"];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }
    
    if (isset($_POST['delete_building_name'], $_POST['delete_room_name'], $_POST['delete_date'])) {
        delete_facility($_POST['delete_building_name'], $_POST['delete_room_name'], $_POST['delete_date']);
    } elseif (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['excel_file']['tmp_name'];
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        foreach ($rows as $index => $row) {
            if ($index == 0) continue;
            register_facility($row[0], $row[1], $row[2], $row[3], $row[4]);
        }

        if (!empty($inserted_rows)) {
            $messages[] = ['type' => 'success', 'text' => "施設が正常に登録されました。"];
        }
    } elseif (isset($_POST['building_name'], $_POST['room_name'], $_POST['date'], $_POST['available_start_time'], $_POST['available_end_time'])) {
        if (register_facility($_POST['building_name'], $_POST['room_name'], $_POST['date'], $_POST['available_start_time'], $_POST['available_end_time'])) {
            $messages[] = ['type' => 'success', 'text' => "施設が正常に登録されました。"];
        }
    }
}

$facilities = $conn->query("SELECT buildings.name as building_name, rooms.name as room_name, room_availability.date, room_availability.available_start_time, room_availability.available_end_time FROM buildings JOIN rooms ON buildings.id = rooms.building_id JOIN room_availability ON rooms.id = room_availability.room_id ORDER BY buildings.name, rooms.name, room_availability.date");
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>施設登録</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .container { margin-top: 50px; max-width: 800px; }
        .form-control, .btn { margin-bottom: 15px; }
        .table-container { margin-top: 30px; }
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
                    <a class="nav-link" href="admin_dashboard.php">管理者ダッシュボードへ</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">ログアウト</a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <h2 class="text-center">施設登録</h2>
        
        <?php foreach ($messages as $msg): ?>
            <div class="alert alert-<?php echo h($msg['type']); ?>" role="alert">
                <?php echo h($msg['text']); ?>
            </div>
        <?php endforeach; ?>

        <form method="post" enctype="multipart/form-data" action="register_facility.php">
            <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
            <div class="form-group">
                <label for="excel_file">Excelファイルを選択</label>
                <input type="file" class="form-control" name="excel_file" id="excel_file" accept=".xlsx, .xls">
            </div>
            <div class="form-group">
                <label for="building_name">建物名</label>
                <input type="text" class="form-control" name="building_name" id="building_name">
            </div>
            <div class="form-group">
                <label for="room_name">部屋名</label>
                <input type="text" class="form-control" name="room_name" id="room_name">
            </div>
            <div class="form-group">
                <label for="date">日付</label>
                <input type="date" class="form-control" name="date" id="date">
            </div>
            <div class="form-group">
                <label for="available_start_time">利用可能開始時間</label>
                <input type="time" class="form-control" name="available_start_time" id="available_start_time">
            </div>
            <div class="form-group">
                <label for="available_end_time">利用可能終了時間</label>
                <input type="time" class="form-control" name="available_end_time" id="available_end_time">
            </div>
            <button type="submit" class="btn btn-primary btn-block">登録</button>
        </form>

        <?php if ($facilities->num_rows > 0): ?>
            <div class="table-container">
                <h3 class="text-center">登録済み施設一覧</h3>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>建物名</th>
                            <th>部屋名</th>
                            <th>日付</th>
                            <th>利用可能開始時間</th>
                            <th>利用可能終了時間</th>
                            <th>削除</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $facilities->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo h($row['building_name']); ?></td>
                            <td><?php echo h($row['room_name']); ?></td>
                            <td><?php echo h($row['date']); ?></td>
                            <td><?php echo h($row['available_start_time']); ?></td>
                            <td><?php echo h($row['available_end_time']); ?></td>
                            <td>
                                <form method="post" action="register_facility.php" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="delete_building_name" value="<?php echo h($row['building_name']); ?>">
                                    <input type="hidden" name="delete_room_name" value="<?php echo h($row['room_name']); ?>">
                                    <input type="hidden" name="delete_date" value="<?php echo h($row['date']); ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">削除</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
    // 5分ごとにセッションをチェック
    setInterval(function() {
        fetch('check_session.php')
            .then(response => response.json())
            .then(data => {
                if (!data.valid) {
                    alert('セッションがタイムアウトしました。再度ログインしてください。');
                    window.location.href = 'login.php?timeout=1';
                }
            });
    }, 5 * 60 * 1000);
</script>
</body>
</html>
<?php $conn->close(); ?>