<?php 
include '../db.php';

header('Content-Type: application/json');

if(isset($_GET['search'])){
    $search = '%'.$_GET['search'].'%';
    $stmt = $conn->prepare("SELECT resident_id, first_name, last_name, civil_status, household_address, age 
                            FROM residents 
                            WHERE (first_name LIKE ? OR last_name LIKE ?) AND is_archived = 0
                            ORDER BY first_name ASC 
                            LIMIT 10");
    $stmt->bind_param("ss", $search, $search);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode($result);
    exit;
}

echo json_encode([]);
?>
