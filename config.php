<?php
// データベース接続設定
$servername = "localhost";
$db_username = "root";
$db_password = "root";
$dbname = "facility_requests";

// データベース接続を確立する関数
function get_db_connection() {
    global $servername, $db_username, $db_password, $dbname;
    $conn = new mysqli($servername, $db_username, $db_password, $dbname);

    // 接続をチェックする
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    return $conn;
}
?>