<?php
session_start();
header('Content-Type: application/json');
include '../../db.php';

// Ensure user is logged in
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role']; // resident, staff, or admin

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$blotter_id = isset($input['blotter_id']) ? intval($input['blotter_id']) : 0;
$message = isset($input['message']) ? trim($input['message']) : '';

if (!$blotter_id || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

// Determine receiver_id
$receiver_id = null;

if ($user_role === 'resident') {
    // First: get latest staff/admin who replied to this blotter
    $receiverQuery = $conn->prepare("
        SELECT sender_id 
        FROM messages 
        WHERE blotter_id = ? AND sender_role IN ('staff','admin') 
        ORDER BY date_sent DESC LIMIT 1
    ");
    $receiverQuery->bind_param("i", $blotter_id);
    $receiverQuery->execute();
    $receiverResult = $receiverQuery->get_result();
    
    if ($receiverResult->num_rows > 0) {
        $receiver_id = $receiverResult->fetch_assoc()['sender_id'];
    } else {
        // fallback: pick the first staff/admin in users table
        $staffQuery = $conn->prepare("
            SELECT id FROM users WHERE role IN ('staff','admin') ORDER BY id ASC LIMIT 1
        ");
        $staffQuery->execute();
        $staffResult = $staffQuery->get_result();
        if ($staffResult->num_rows > 0) {
            $receiver_id = $staffResult->fetch_assoc()['id'];
        }
        $staffQuery->close();
    }
    $receiverQuery->close();

} else {
    // staff/admin sends -> receiver is complainant resident's user_id
    $residentQuery = $conn->prepare("
        SELECT user_id FROM residents WHERE resident_id = (
            SELECT complainant_id FROM blotter_records WHERE blotter_id = ?
        )
    ");
    $residentQuery->bind_param("i", $blotter_id);
    $residentQuery->execute();
    $residentResult = $residentQuery->get_result();
    if ($residentResult->num_rows > 0) {
        $receiver_id = $residentResult->fetch_assoc()['user_id'];
    }
    $residentQuery->close();
}

if (!$receiver_id) {
    echo json_encode(['success'=>false,'message'=>'No receiver found for this blotter.']);
    exit;
}

// Insert message
$insertQuery = $conn->prepare("
    INSERT INTO messages (sender_role, sender_id, receiver_id, blotter_id, content)
    VALUES (?, ?, ?, ?, ?)
");
$insertQuery->bind_param("siiis", $user_role, $user_id, $receiver_id, $blotter_id, $message);

if($insertQuery->execute()){
    echo json_encode(['success'=>true,'message'=>'Message sent']);
} else {
    echo json_encode(['success'=>false,'message'=>'Failed to send message','error'=>$conn->error]);
}

$insertQuery->close();
$conn->close();
?>
