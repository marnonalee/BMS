<?php
session_start();
include 'db.php';

if(isset($_GET['id'])){
    $id = intval($_GET['id']);
    $conn->query("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE notification_id = $id");
}
?>
