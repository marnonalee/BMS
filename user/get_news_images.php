<?php
include '../db.php';

$announcement_id = $_GET['id'] ?? 0;

$stmt = $conn->prepare("SELECT id, image_path FROM news_images WHERE news_id=?");
$stmt->bind_param("i", $announcement_id);
$stmt->execute();
$result = $stmt->get_result();

$images = [];
while($row = $result->fetch_assoc()){
    $images[] = $row;
}

echo json_encode($images);
?>
