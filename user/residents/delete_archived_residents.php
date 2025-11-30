<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

header("Content-Type: application/json");

include '../db.php';

if (!isset($_POST['resident_ids']) || !is_array($_POST['resident_ids'])) {
    echo json_encode(['success' => false, 'message' => 'No residents selected.']);
    exit();
}

$residentIds = array_map('intval', $_POST['resident_ids']);
$undeleted = [];

foreach ($residentIds as $id) {
    // Check if resident is a household head
    $check = $conn->query("SELECT * FROM households WHERE head_resident_id = $id LIMIT 1");
    if ($check && $check->num_rows > 0) {
        $undeleted[] = $id;
        continue; // skip deletion
    }

    // Safe to delete
    $delete = $conn->query("DELETE FROM residents WHERE resident_id = $id");
    if (!$delete) {
        error_log("SQL ERROR: " . $conn->error);
        $undeleted[] = $id;
        continue;
    }

    // Log activity
    $user_id = $_SESSION['user_id'] ?? 0;
    $stmt = $conn->prepare("INSERT INTO log_activity (user_id, action, description) VALUES (?, ?, ?)");
    $action = "Permanent Delete";
    $description = "Permanently deleted resident ID: $id";
    $stmt->bind_param("iss", $user_id, $action, $description);
    $stmt->execute();
    $stmt->close();
}

if (!empty($undeleted)) {
    echo json_encode([
        'success' => false,
        'message' => 'Some residents could not be deleted because they are household heads.',
        'undeleted_ids' => $undeleted
    ]);
} else {
    echo json_encode([
        'success' => true,
        'message' => 'Selected residents deleted successfully!'
    ]);
}
?>
