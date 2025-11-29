<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

header("Content-Type: application/json");

include '../db.php';

if (!isset($_POST['delete_resident_id'])) {
    echo json_encode(['success' => false, 'error' => 'No ID sent']);
    exit();
}

$id = (int)$_POST['delete_resident_id'];

$q = $conn->query("DELETE FROM residents WHERE resident_id = $id");

if (!$q) {
    error_log("SQL ERROR: " . $conn->error);
    echo json_encode(['success' => false, 'error' => 'SQL Error']);
    exit();
}

$user_id = $_SESSION['user_id'] ?? 0;
$stmt = $conn->prepare("INSERT INTO log_activity (user_id, action, description) VALUES (?, ?, ?)");
$action = "Permanent Delete";
$description = "Permanently deleted resident ID: $id";
$stmt->bind_param("iss", $user_id, $action, $description);
$stmt->execute();
$stmt->close();

echo json_encode([
    'success' => true,
    'message' => 'Resident permanently deleted!'
]);
