<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "resident") {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include '../../db.php';

$blotter_id = isset($_GET['blotter_id']) ? (int)$_GET['blotter_id'] : 0;

if ($blotter_id > 0) {
    $stmt = $conn->prepare("UPDATE blotter_records SET status = 'cancelled' WHERE blotter_id = ?");
    $stmt->bind_param("i", $blotter_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid blotter ID']);
}

$conn->close();
?>
