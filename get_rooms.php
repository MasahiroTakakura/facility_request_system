<?php
require_once 'config.php';

if (isset($_POST['building_id'])) {
    $building_id = $_POST['building_id'];

    $conn = get_db_connection();
    $sql = "SELECT id, name FROM rooms WHERE building_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $building_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo '<option value="">選択してください</option>';
        while ($row = $result->fetch_assoc()) {
            echo '<option value="'.htmlspecialchars($row['id']).'">'.htmlspecialchars($row['name']).'</option>';
        }
    } else {
        echo '<option value="">部屋が見つかりません</option>';
    }

    $stmt->close();
    $conn->close();
}
?>