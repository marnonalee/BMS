<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}
include 'db.php';
$user_id = $_SESSION["user_id"];
$userQuery = $conn->query("SELECT * FROM users WHERE id = '$user_id'");
$user = $userQuery->fetch_assoc();

$current = $_POST['current_password'] ?? '';
$new = $_POST['new_password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

if (!password_verify($current, $user['password'])) {
    $_SESSION['error'] = "Current password is incorrect!";
} elseif ($new !== $confirm) {
    $_SESSION['error'] = "New password and confirm password do not match!";
} elseif (
    strlen($new) < 8 ||
    !preg_match('/[A-Z]/', $new) ||
    !preg_match('/[a-z]/', $new) ||
    !preg_match('/[0-9]/', $new) ||
    !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new)
) {
    $_SESSION['error'] = "New password does not meet the required complexity!";
} else {
    $hashed = password_hash($new, PASSWORD_DEFAULT);
    $conn->query("UPDATE users SET password='$hashed' WHERE id='$user_id'");
    $_SESSION['success'] = "Password updated successfully!";
}

header("Location: manage_profile.php");
exit();
