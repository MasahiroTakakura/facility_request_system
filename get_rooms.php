<?php
require_once 'config.php';

$conn = get_db_connection();

if (isset($_POST['building_id'])) {
    $building_id = $_POST['building_id'];

    $query = "SELECT id, name, available_start_time, available_end_time FROM rooms WHERE building_id = ?";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param('i', $building_id);
        $stmt->execute();
        $result = $stmt->get_result();

        echo '<option value="">部屋を選択してください</option>';
        while ($row = $result->fetch_assoc()) {
            echo '<option value="' . htmlspecialchars($row['id']) . '" data-start-time="' . htmlspecialchars($row['available_start_time']) . '" data-end-time="' . htmlspecialchars($row['available_end_time']) . '">' . htmlspecialchars($row['name']) . '</option>';
        }

        $stmt->close();
    } else {
        echo 'クエリの準備に失敗しました。';
    }
}

$conn->close();
?>