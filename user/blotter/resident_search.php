<?php
include '../db.php';

$q = $_GET['q'] ?? '';
$q = $conn->real_escape_string($q);

$stmt = $conn->prepare("
    SELECT 
        resident_id, 
        CONCAT(first_name,' ',last_name) AS fullname, 
        COALESCE(NULLIF(resident_address, ''), street) AS resident_address, 
        contact_number 
    FROM residents 
    WHERE CONCAT(first_name,' ',last_name) LIKE ? 
    LIMIT 10
");
$searchTerm = "%$q%";
$stmt->bind_param("s", $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$residents = [];
while($row = $result->fetch_assoc()){
    $residents[] = $row;
}

header('Content-Type: application/json');
echo json_encode($residents);
