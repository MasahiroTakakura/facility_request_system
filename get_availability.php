<?php
require_once 'config.php';

$conn = get_db_connection();

// 検索条件の取得
$building_name = isset($_GET['building_name']) ? $_GET['building_name'] : '';
$room_name = isset($_GET['room_name']) ? $_GET['room_name'] : '';
$date = isset($_GET['date']) ? $_GET['date'] : '';

// クエリのベース
$sql = "SELECT buildings.name AS building_name, rooms.name AS room_name, room_availability.date, room_availability.available_start_time, room_availability.available_end_time
        FROM buildings
        JOIN rooms ON buildings.id = rooms.building_id
        JOIN room_availability ON rooms.id = room_availability.room_id
        WHERE 1=1";

// パラメータのバインド用配列
$params = [];
$types = '';

// 条件に応じてクエリに追加
if ($building_name) {
    $sql .= " AND buildings.name LIKE ?";
    $params[] = '%' . $building_name . '%';
    $types .= 's';
}

if ($room_name) {
    $sql .= " AND rooms.name LIKE ?";
    $params[] = '%' . $room_name . '%';
    $types .= 's';
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
    $availability[] = $row;
}

header('Content-Type: application/json');
echo json_encode($availability);

$stmt->close();
$conn->close();
?>