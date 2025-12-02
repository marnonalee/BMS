<?php
session_start(); 
include '../db.php';

$data = json_decode(file_get_contents("php://input"), true);

$household_id = intval($data['household_id'] ?? 0);
$resident_id = intval($data['resident_id'] ?? 0);
$relationship = trim($data['relationship'] ?? '');

$response = ['success' => false, 'message' => 'Something went wrong.'];

if($household_id && $resident_id){
    $check = $conn->query("SELECT first_name, last_name, household_id, is_family_head, is_archived FROM residents WHERE resident_id = $resident_id AND is_archived = 0");
    $row = $check->fetch_assoc();

    if(!$row){
        $response['message'] = "Resident not found or archived.";
    }
    elseif($row['is_family_head']){
        $response['message'] = "Cannot add the family head as a member.";
    }
    elseif($row['household_id'] && $row['household_id'] != 0){
        $response['message'] = "Resident is already assigned to a family.";
    } else {
        $headQuery = $conn->query("SELECT first_name, last_name, resident_address, street FROM residents WHERE household_id = $household_id AND is_family_head = 1 AND is_archived = 0");
        if($head = $headQuery->fetch_assoc()){
            $resident_address = $head['resident_address'];
            $street = $head['street'];

            $stmt = $conn->prepare("UPDATE residents SET household_id = ?, resident_address = ?, street = ?, relationship = ? WHERE resident_id = ?");
            $stmt->bind_param("ssssi", $household_id, $resident_address, $street, $relationship, $resident_id);
            if($stmt->execute()){
                $response['success'] = true;
                $response['message'] = "Member added successfully!";

                $user_id = $_SESSION['user_id'] ?? 0;
                $action = "Add Household Member";
                $description = "Added ".$row['first_name']." ".$row['last_name']." as '$relationship' to the family of ".$head['first_name']." ".$head['last_name'];
                $logStmt = $conn->prepare("INSERT INTO log_activity (user_id, action, description) VALUES (?,?,?)");
                $logStmt->bind_param("iss", $user_id, $action, $description);
                $logStmt->execute();
                $logStmt->close();
            } else {
                $response['message'] = "Failed to add member.";
            }
            $stmt->close();
        } else {
            $response['message'] = "Family head not found or archived.";
        }
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>
