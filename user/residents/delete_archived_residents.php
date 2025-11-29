<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['message' => 'Unauthorized']);
    exit;
}

include '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $residentIds = $_POST['resident_ids'] ?? [];

    if (empty($residentIds)) {
        echo json_encode(['message' => 'No residents selected']);
        exit;
    }

    $residentIds = array_map('intval', $residentIds);
    $idsStr = implode(',', $residentIds);

    $sql = "DELETE FROM residents WHERE resident_id IN ($idsStr) AND is_archived=1";

    if ($conn->query($sql)) {
        echo json_encode(['message' => count($residentIds) . ' archived resident(s) deleted successfully.']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error deleting residents: ' . $conn->error]);
    }
} else {
    http_response_code(405);
    echo json_encode(['message' => 'Invalid request method']);
}
?>
