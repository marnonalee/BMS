<?php
session_start();
include '../db.php';
$term = trim($_GET['term'] ?? '');
if (!$term) { 
    echo json_encode([]); 
    exit; 
}
$stmt = $conn->prepare("SELECT * FROM residents WHERE is_archived = 0 AND CONCAT(first_name,' ',last_name) LIKE CONCAT('%',?,'%') LIMIT 5");
$stmt->bind_param("s", $term);
$stmt->execute();
$result = $stmt->get_result();
$residents = [];
while($row = $result->fetch_assoc()) $residents[] = $row;
echo json_encode($residents);
?>
