<?php
session_start();

// Disable output except JSON
error_reporting(E_ALL);
ini_set('display_errors', 0); // Do NOT display errors to client
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include 'residents_functions.php'; // make sure this does not output anything

try {
    // Handle single or multiple restores
    if (isset($_POST['resident_id'])) {
        $ids = [(int)$_POST['resident_id']];
    } elseif (isset($_POST['resident_ids'])) {
        $ids = array_map('intval', $_POST['resident_ids']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No resident ID provided']);
        exit;
    }

    foreach ($ids as $id) {
        $stmt = $conn->prepare("UPDATE residents SET is_archived = 0 WHERE resident_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode(['success' => true, 'message' => 'Resident(s) restored successfully!']);
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while restoring.']);
}
