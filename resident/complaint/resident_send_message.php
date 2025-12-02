<?php
session_start();
header('Content-Type: application/json');
include '../../db.php';

if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

$input = json_decode(file_get_contents('php://input'), true);
$blotter_id = isset($input['blotter_id']) ? intval($input['blotter_id']) : 0;
$message = isset($input['message']) ? trim($input['message']) : '';

if (!$blotter_id || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$receiver_id = null;

if ($user_role === 'resident') {
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
        $staffQuery = $conn->prepare("SELECT id FROM users WHERE role IN ('staff','admin') ORDER BY id ASC LIMIT 1");
        $staffQuery->execute();
        $staffResult = $staffQuery->get_result();
        if ($staffResult->num_rows > 0) {
            $receiver_id = $staffResult->fetch_assoc()['id'];
        }
        $staffQuery->close();
    }
    $receiverQuery->close();

} else {
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

$insertQuery = $conn->prepare("
    INSERT INTO messages (sender_role, sender_id, receiver_id, blotter_id, content)
    VALUES (?, ?, ?, ?, ?)
");
$insertQuery->bind_param("siiis", $user_role, $user_id, $receiver_id, $blotter_id, $message);
$inserted = $insertQuery->execute();
$insertQuery->close();

if (!$inserted) {
    echo json_encode(['success'=>false,'message'=>'Failed to send message','error'=>$conn->error]);
    exit;
}

$notif_title = "New Message in Blotter #$blotter_id";
$notif_message = $message;
$notif_from_role = $user_role;
$notif_type = "blotter_message";
$notif_priority = "normal";
$notif_action = "commented";

$notifQuery = $conn->prepare("
    INSERT INTO notifications 
    (resident_id, message, from_role, title, type, priority, action_type, is_read, sent_email, date_created)
    VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, NOW())
");
$notifQuery->bind_param(
    "issssss",
    $receiver_id,
    $notif_message,
    $notif_from_role,
    $notif_title,
    $notif_type,
    $notif_priority,
    $notif_action
);
$notifQuery->execute();
$notifQuery->close();

echo json_encode(['success'=>true,'message'=>'Message sent']);
$conn->close();
?>
