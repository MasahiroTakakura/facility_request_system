<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['notification_id'])) {
    $notification_id = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT);
    if ($notification_id) {
        mark_notification_as_read($notification_id);
    }
}

header("Location: dashboard.php");
exit();