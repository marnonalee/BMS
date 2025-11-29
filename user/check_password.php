<?php
session_start();
include 'db.php';

if (!isset($_SESSION["user_id"]) || empty($_POST['current_password'])) {
    echo json_encode(['status' => 'error']);
    exit;
}

$user_id = $_SESSION["user_id"];
$current_password = $_POST['current_password'];

$userQuery = $conn->query("SELECT password FROM users WHERE id = '$user_id'");
$user = $userQuery->fetch_assoc();

if (password_verify($current_password, $user['password'])) {
    echo json_encode(['status' => 'match']);
} else {
    echo json_encode(['status' => 'no_match']);
}
?>
