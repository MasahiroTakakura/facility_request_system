<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
generate_csrf_token();

if (!check_session_timeout()) {
    header("Location: login.php?timeout=1");
    exit();
}

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$conn = get_db_connection();

$building_id = isset($_POST['building_id']) ? intval($_POST['building_id']) : '';
$room_id = isset($_POST['room_id']) ? intval($_POST['room_id']) : '';

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
            opacity: 1;
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
        <form id="availability-form">
            <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
            <div class="form-group">
                <label for="building_id">建物</label>
                <select class="form-control" name="building_id" id="building_id">
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
                <select class="form-control" name="room_id" id="room_id">
                    <option value="">選択してください</option>
                    <?php while ($rooms && $row = $rooms->fetch_assoc()): ?>
                        <option value="<?php echo h($row['id']); ?>" <?php if ($room_id == $row['id']) echo 'selected'; ?>>
                            <?php echo h($row['name']); ?>
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
        let calendar;

        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                events: [],
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

        $('#building_id').change(function() {
            var building_id = $(this).val();
            if (building_id) {
                $.ajax({
                    url: 'get_rooms.php',
                    type: 'POST',
                    data: {
                        building_id: building_id,
                        csrf_token: $('input[name="csrf_token"]').val()
                    },
                    success: function(response) {
                        $('#room_id').html(response);
                        calendar.removeAllEvents();
                    }
                });
            } else {
                $('#room_id').html('<option value="">選択してください</option>');
                calendar.removeAllEvents();
            }
        });

        $('#room_id').change(function() {
            var room_id = $(this).val();
            var building_id = $('#building_id').val();
            if (room_id) {
                $.ajax({
                    url: 'get_availability.php',
                    type: 'POST',
                    data: {
                        room_id: room_id,
                        building_id: building_id,
                        csrf_token: $('input[name="csrf_token"]').val()
                    },
                    dataType: 'json',
                    success: function(response) {
                        calendar.removeAllEvents();
                        calendar.addEventSource(response);
                    }
                });
            } else {
                calendar.removeAllEvents();
            }
        });
    </script>

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