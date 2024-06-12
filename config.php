<?php
function get_db_connection() {
    $servername = "localhost";
    $username = "root";
    $password = "root";
    $dbname = "facility_requests";

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}
?>