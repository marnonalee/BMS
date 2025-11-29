<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit();
}
include '../db.php';
$data = json_decode(file_get_contents("php://input"), true);
$resident_id = intval($data['resident_id'] ?? 0);
$first_name = trim($data['first_name'] ?? '');
$last_name = trim($data['last_name'] ?? '');
$response = ['success' => false, 'message' => 'Something went wrong.'];
if($resident_id && $first_name && $last_name){
    $old = $conn->query("SELECT first_name,last_name FROM residents WHERE resident_id = $resident_id")->fetch_assoc();
    $stmt = $conn->prepare("UPDATE residents SET first_name = ?, last_name = ? WHERE resident_id = ?");
    $stmt->bind_param("ssi", $first_name, $last_name, $resident_id);
    if($stmt->execute()){
        $response['success'] = true;
        $response['message'] = "Member updated successfully!";
        $user_id = $_SESSION['user_id'] ?? 0;
        $action = "Update Household Member";
        $description = "Changed resident_id $resident_id name from ".$old['first_name']." ".$old['last_name']." to $first_name $last_name";
        $logStmt = $conn->prepare("INSERT INTO log_activity (user_id, action, description) VALUES (?,?,?)");
        $logStmt->bind_param("iss", $user_id, $action, $description);
        $logStmt->execute();
        $logStmt->close();
    } else {
        $response['message'] = "Failed to update member.";
    }
    $stmt->close();
}
header('Content-Type: application/json');
echo json_encode($response);
?>
