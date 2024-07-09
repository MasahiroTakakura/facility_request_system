<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['username']) || !verify_csrf_token($_POST['csrf_token'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('アクセスが拒否されました。');
}

$conn = get_db_connection();

// 検索条件の取得
$building_id = isset($_POST['building_id']) ? intval($_POST['building_id']) : '';
$room_id = isset($_POST['room_id']) ? intval($_POST['room_id']) : '';
$date = isset($_POST['date']) ? $_POST['date'] : '';

// クエリのベース
$sql = "SELECT buildings.id AS building_id, buildings.name AS building_name, rooms.id AS room_id, rooms.name AS room_name, room_availability.date, room_availability.available_start_time, room_availability.available_end_time
        FROM buildings
        JOIN rooms ON buildings.id = rooms.building_id
        JOIN room_availability ON rooms.id = room_availability.room_id
        WHERE 1=1";

// パラメータのバインド用配列
$params = [];
$types = '';

// 条件に応じてクエリに追加
if ($building_id) {
    $sql .= " AND buildings.id = ?";
    $params[] = $building_id;
    $types .= 'i';
}

if ($room_id) {
    $sql .= " AND rooms.id = ?";
    $params[] = $room_id;
    $types .= 'i';
}

if ($date) {
    $sql .= " AND room_availability.date = ?";
    $params[] = $date;
    $types .= 's';
}

$sql .= " ORDER BY buildings.name, rooms.name, room_availability.date";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$availability = [];
while ($row = $result->fetch_assoc()) {
    $availability[] = [
        'title' => '利用可能: ' . h($row['available_start_time']) . ' - ' . h($row['available_end_time']),
        'start' => h($row['date']),
        'url' => 'request_form.php?room_id=' . h($row['room_id']) . '&date=' . h($row['date']) . '&building_id=' . h($row['building_id'])
    ];
}

header('Content-Type: application/json');
echo json_encode($availability);

$stmt->close();
$conn->close();
?>