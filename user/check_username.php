<?php
session_start();
include 'db.php';

if (isset($_POST['username'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $user_id = $_SESSION['user_id'];

    $query = $conn->query("SELECT id FROM users WHERE username='$username' AND id != '$user_id'");
    if ($query->num_rows > 0) {
        echo json_encode(['status' => 'taken']);
    } else {
        echo json_encode(['status' => 'available']);
    }
}
?>
