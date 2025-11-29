<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include '../db.php';

if (isset($_POST['request_id'])) {
    $request_id = intval($_POST['request_id']);

    $stmt = $conn->prepare("UPDATE barangay_id_requests SET is_printed = 1 WHERE id = ?");
    $stmt->bind_param("i", $request_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Request ID missing']);
}
?>
