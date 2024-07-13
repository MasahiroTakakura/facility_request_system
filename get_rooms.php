<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// ユーザーがログインしていない場合、エラーを返す
if (!isset($_SESSION['username'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// CSRF対策
if (!verify_csrf_token($_POST['csrf_token'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'CSRF token validation failed']);
    exit();
}

// POSTリクエストのみを許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['error' => 'Invalid request method']);
    exit();
}

$building_id = filter_input(INPUT_POST, 'building_id', FILTER_VALIDATE_INT);

if ($building_id === false || $building_id === null) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Invalid building ID']);
    exit();
}

$conn = get_db_connection();

try {
    $sql = "SELECT id, name FROM rooms WHERE building_id = ? ORDER BY name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $building_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $rooms = [];
    while ($row = $result->fetch_assoc()) {
        $rooms[] = [
            'id' => $row['id'],
            'name' => h($row['name'])
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($rooms);

} catch (Exception $e) {
    error_log('Database error: ' . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'An error occurred while fetching rooms']);
} finally {
    $stmt->close();
    $conn->close();
}
?>