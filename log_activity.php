<?php
include 'db.php';

function logActivity($user_id, $username, $action) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, username, action) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $username, $action);
    $stmt->execute();
    $stmt->close();
}
