<?php
session_start();
include '../db.php';

if (!isset($_POST['delete_resident_id'])) {
    echo json_encode(['success' => false, 'error' => 'No ID']);
    exit();
}

$id = (int)$_POST['delete_resident_id'];

$conn->query("UPDATE residents SET is_archived = 1 WHERE resident_id = $id");
$conn->query("UPDATE profile_update_requests SET is_archived = 1 WHERE resident_id = $id");
$conn->query("UPDATE family_member_requests SET is_archived = 1 WHERE household_head_id = $id OR member_resident_id = $id");
$conn->query("UPDATE barangay_id_requests SET is_archived = 1 WHERE resident_id = $id");
$conn->query("UPDATE blotter_records SET archived = 1 WHERE complainant_id = $id OR victim_id = $id OR suspect_id = $id");

$user_id = $_SESSION['user_id'] ?? 0;
$stmt = $conn->prepare("INSERT INTO log_activity (user_id, action, description) VALUES (?, ?, ?)");
$action = "Archive Resident";
$description = "Archived resident ID: $id and all related records";
$stmt->bind_param("iss", $user_id, $action, $description);
$stmt->execute();
$stmt->close();

$resident = $conn->query("SELECT * FROM residents WHERE resident_id = $id")->fetch_assoc();

echo json_encode([
    'success' => true,
    'message' => 'Resident and all related records archived successfully!',
    'resident' => $resident
]);
?>
