<?php
require_once 'config.php';
$conn = get_db_connection();

if (isset($_POST['building_id'])) {
    $building_id = $_POST['building_id'];
    $sql = "SELECT * FROM rooms WHERE building_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $building_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo '<option value="">部屋を選択してください</option>';
        while ($row = $result->fetch_assoc()) {
            echo '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['name']) . '</option>';
        }
    } else {
        echo '<option value="">部屋が見つかりません</option>';
    }
    $stmt->close();
}
$conn->close();
?>