<?php
include '../db.php';

$household_id = intval($_GET['household_id'] ?? 0);
$members = [];

if($household_id){
    $sql = "SELECT resident_id, first_name, last_name 
            FROM residents 
            WHERE household_id = $household_id 
            AND is_family_head = 0
            AND is_archived = 0
            ORDER BY last_name ASC";
    $result = $conn->query($sql);
    while($row = $result->fetch_assoc()){
        $members[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode($members);
?>
