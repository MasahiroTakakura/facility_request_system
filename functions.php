<?php

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function verify_csrf_token($token) {
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }
    return false;
}

function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// 以下は通知機能の実装のために追加

function create_notification($user_id, $message) {
    $conn = get_db_connection();
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $message);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

function get_user_notifications($user_id) {
    $conn = get_db_connection();
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
    return $notifications;
}

function mark_notification_as_read($notification_id) {
    $conn = get_db_connection();
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $stmt->bind_param("i", $notification_id);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

function check_session_timeout() {
    $current_time = time();
    $session_lifetime = isset($_SESSION['LAST_ACTIVITY']) ? $current_time - $_SESSION['LAST_ACTIVITY'] : 0;
    
    if ($session_lifetime > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        return false;
    }
    
    $_SESSION['LAST_ACTIVITY'] = $current_time;
    return true;
}

?>