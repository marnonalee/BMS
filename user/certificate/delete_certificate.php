<?php
include '../db.php';

if(!isset($_GET['id']) || empty($_GET['id'])){
    http_response_code(400);
    echo "Invalid request.";
    exit;
}

$id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT file_path FROM certificate_templates WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if($result->num_rows === 0){
    http_response_code(404);
    echo "Certificate not found.";
    exit;
}
$row = $result->fetch_assoc();
$file_path = $row['file_path'];

$delStmt = $conn->prepare("DELETE FROM certificate_templates WHERE id = ?");
$delStmt->bind_param("i", $id);

if($delStmt->execute()){
    if($file_path && file_exists("../$file_path")){
        unlink("../$file_path");
    }
    echo "Deleted successfully.";
} else {
    http_response_code(500);
    echo "Error deleting certificate.";
}

$delStmt->close();
$stmt->close();
$conn->close();
