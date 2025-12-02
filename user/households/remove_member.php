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
$resident = $conn->query("SELECT first_name, last_name, household_id, is_family_head FROM residents WHERE resident_id = $resident_id")->fetch_assoc();
if(!$resident){
    echo json_encode(['success'=>false,'message'=>'Resident not found']);
    exit();
}

if($resident['is_family_head']){
    echo json_encode(['success'=>false,'message'=>'Cannot remove the family head']);
    exit();
}

$head = $conn->query("SELECT first_name, last_name FROM residents WHERE household_id = {$resident['household_id']} AND is_family_head = 1 AND is_archived = 0")->fetch_assoc();

$delete = $conn->query("UPDATE residents SET household_id = NULL WHERE resident_id = $resident_id");

if($delete){
    $user_id = $_SESSION['user_id'] ?? 0;
    $action = "Remove Household Member";
    $description = "Removed ".$resident['first_name']." ".$resident['last_name']." from the family of ".($head['first_name'] ?? 'Unknown')." ".($head['last_name'] ?? '');
    $logStmt = $conn->prepare("INSERT INTO log_activity (user_id, action, description) VALUES (?,?,?)");
    $logStmt->bind_param("iss", $user_id, $action, $description);
    $logStmt->execute();
    $logStmt->close();

    echo json_encode(['success'=>true,'message'=>'Member removed successfully']);
} else {
    echo json_encode(['success'=>false,'message'=>'Failed to remove member']);
}
?>
