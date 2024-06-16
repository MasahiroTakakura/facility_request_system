<?php
session_start();
require_once 'config.php';

$conn = get_db_connection();

$building_id = isset($_GET['building_id']) ? $_GET['building_id'] : '';
$room_id = isset($_GET['room_id']) ? $_GET['room_id'] : '';

$sql_buildings = "SELECT * FROM buildings";
$buildings = $conn->query($sql_buildings);

$sql_rooms = $building_id ? "SELECT * FROM rooms WHERE building_id = $building_id" : "SELECT * FROM rooms";
$rooms = $conn->query($sql_rooms);

$room_availability = [];

if ($room_id) {
    // 使用可能時間を取得
    $sql_room_availability = "SELECT * FROM room_availability WHERE room_id = ?";
    $stmt = $conn->prepare($sql_room_availability);
    $stmt->bind_param('i', $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $room_availability[] = $row;
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>空き状況確認</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            max-width: 800px;
            margin-top: 50px;
        }
        .fc-event-title {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #toast-container > .toast-info {
            background-color: #007bff;
            opacity: 1; /* 透けないように設定 */
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

    <div class="container">
        <h2 class="text-center">空き状況確認</h2>
        <form method="get" action="availability.php">
            <div class="form-group">
                <label for="building_id">建物</label>
                <select class="form-control" name="building_id" id="building_id" onchange="this.form.submit()">
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
                <select class="form-control" name="room_id" id="room_id" onchange="this.form.submit()">
                    <option value="">選択してください</option>
                    <?php while ($row = $rooms->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($row['id']); ?>" <?php if ($room_id == $row['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($row['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </form>

        <!-- カレンダーを表示 -->
        <div id="calendar" class="mt-5"></div>
        <div class="mt-3">
            <a href="request_form.php" class="btn btn-secondary">リクエストフォームに戻る</a>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                events: [
                    <?php foreach ($room_availability as $availability): ?>
                    {
                        title: '利用可能: <?php echo $availability['available_start_time']; ?> - <?php echo $availability['available_end_time']; ?>',
                        start: '<?php echo $availability['date']; ?>',
                        url: 'request_form.php?room_id=<?php echo $room_id; ?>&date=<?php echo $availability['date']; ?>&building_id=<?php echo $building_id; ?>'
                    },
                    <?php endforeach; ?>
                ],
                eventColor: '#378006',
                eventMouseEnter: function(info) {
                    toastr.options = {
                        "closeButton": false,
                        "debug": false,
                        "newestOnTop": false,
                        "progressBar": false,
                        "positionClass": "toast-top-center",
                        "preventDuplicates": false,
                        "onclick": null,
                        "showDuration": "300",
                        "hideDuration": "1000",
                        "timeOut": "5000",
                        "extendedTimeOut": "1000",
                        "showEasing": "swing",
                        "hideEasing": "linear",
                        "showMethod": "fadeIn",
                        "hideMethod": "fadeOut"
                    };
                    toastr.info(info.event.title);
                },
                eventMouseLeave: function() {
                    toastr.clear();
                }
            });
            calendar.render();
        });
    </script>
</body>
</html>