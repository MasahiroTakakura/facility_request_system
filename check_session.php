<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

$response = ['valid' => check_session_timeout()];
echo json_encode($response);

?>