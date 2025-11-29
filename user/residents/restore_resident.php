<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include 'residents_functions.php'; 

if (isset($_POST['resident_id'])) {
    $resident_id = intval($_POST['resident_id']);

    $stmt = $conn->prepare("UPDATE residents SET is_archived = 0 WHERE resident_id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'SQL prepare failed']);
        exit;
    }
    $stmt->bind_param("i", $resident_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Resident restored successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to restore resident.']);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Resident ID not provided.']);
}
