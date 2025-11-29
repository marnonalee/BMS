<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit();
}

include '../db.php';

$data = json_decode(file_get_contents('php://input'), true);

if(!isset($data['resident_id'])){
    echo json_encode(['success'=>false,'message'=>'Resident ID is required']);
    exit();
}

$resident_id = intval($data['resident_id']);
$checkHead = $conn->query("SELECT is_family_head, household_id FROM residents WHERE resident_id = $resident_id")->fetch_assoc();
if($checkHead && $checkHead['is_family_head']){
    echo json_encode(['success'=>false,'message'=>'Cannot remove the family head']);
    exit();
}

$delete = $conn->query("UPDATE residents SET household_id = NULL WHERE resident_id = $resident_id");

if($delete){
    $user_id = $_SESSION['user_id'] ?? 0;
    $action = "Remove Household Member";
    $household_id = $checkHead['household_id'] ?? 0;
    $description = "Removed resident_id $resident_id from household_id $household_id";
    $logStmt = $conn->prepare("INSERT INTO log_activity (user_id, action, description) VALUES (?,?,?)");
    $logStmt->bind_param("iss", $user_id, $action, $description);
    $logStmt->execute();
    $logStmt->close();
  

    echo json_encode(['success'=>true,'message'=>'Member removed successfully']);
} else {
    echo json_encode(['success'=>false,'message'=>'Failed to remove member']);
}
?>
