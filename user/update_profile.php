<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

include '../db.php';

$user_id = $_SESSION["user_id"];
$userQuery = $conn->query("SELECT * FROM users WHERE id='$user_id'");
$user = $userQuery->fetch_assoc();

if (!$user) {
    $_SESSION['error'] = "User not found!";
    header("Location: manage_profile.php");
    exit();
}

if (isset($_POST['update_profile'])) {
    $first_name = $conn->real_escape_string($_POST['first_name']);
    $last_name = $conn->real_escape_string($_POST['last_name']);
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);

    $checkUsername = $conn->query("SELECT id FROM users WHERE username='$username' AND id != '$user_id'");
    if ($checkUsername->num_rows > 0) {
        $_SESSION['error'] = "Username already taken!";
        header("Location: manage_profile.php");
        exit();
    }

    $checkEmail = $conn->query("SELECT id FROM users WHERE email='$email' AND id != '$user_id'");
    if ($checkEmail->num_rows > 0) {
        $_SESSION['error'] = "Email already in use!";
        header("Location: manage_profile.php");
        exit();
    }

    $update = $conn->query("UPDATE users SET 
        username='$username',
        email='$email'
        WHERE id='$user_id'
    ");

    if ($update) {
        $_SESSION['success'] = "Profile updated successfully!";
    } else {
        $_SESSION['error'] = "Something went wrong!";
    }

    header("Location: manage_profile.php");
    exit();
} else {
    $_SESSION['error'] = "Invalid request!";
    header("Location: manage_profile.php");
    exit();
}
?>
