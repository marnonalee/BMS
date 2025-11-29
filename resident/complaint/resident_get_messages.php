<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'resident') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

include '../../db.php';

$user_id = $_SESSION['user_id'];

// Get resident_id and full name
$residentQuery = $conn->prepare("
    SELECT resident_id, CONCAT(first_name, ' ', COALESCE(middle_name,''), ' ', last_name) AS full_name 
    FROM residents 
    WHERE user_id = ?
");
$residentQuery->bind_param("i", $user_id);
$residentQuery->execute();
$residentResult = $residentQuery->get_result();
if ($residentResult->num_rows === 0) {
    echo json_encode(['error' => 'Resident not found']);
    exit;
}
$resident = $residentResult->fetch_assoc();
$resident_id = $resident['resident_id'];

$blotter_id = isset($_GET['blotter_id']) ? intval($_GET['blotter_id']) : 0;
if (!$blotter_id) {
    echo json_encode(['error' => 'Invalid blotter ID']);
    exit;
}

// Get blotter details
$blotterStmt = $conn->prepare("SELECT * FROM blotter_records WHERE blotter_id = ? LIMIT 1");
$blotterStmt->bind_param("i", $blotter_id);
$blotterStmt->execute();
$blotterResult = $blotterStmt->get_result();
$blotterDetails = $blotterResult->fetch_assoc();

// Get messages for this blotter
$msgStmt = $conn->prepare("
    SELECT m.*, u.role AS user_role
    FROM messages m
    LEFT JOIN users u ON u.id = m.sender_id
    WHERE m.blotter_id = ?
    ORDER BY m.date_sent ASC
");
$msgStmt->bind_param("i", $blotter_id);
$msgStmt->execute();
$msgResult = $msgStmt->get_result();

$messages = [];
while ($row = $msgResult->fetch_assoc()) {
    $sender_role = '';

    // Determine sender_role
    if(!empty($row['sender_role'])){
        $sender_role = $row['sender_role'];
    } else {
        // fallback: compare sender_id with current resident
        $sender_role = ($row['sender_id'] == $resident_id) ? 'resident' : 'staff';
    }

    $messages[] = [
        'message_id' => $row['message_id'],
        'sender_id' => $row['sender_id'],
        'sender_role' => $sender_role,
        'content' => $row['content'],
        'date_sent' => $row['date_sent']
    ];
}

// Return JSON response
echo json_encode([
    'details' => [
        'complainant_name' => $blotterDetails['complainant_name'] ?? '',
        'victim_name' => $blotterDetails['victim_name'] ?? '',
        'incident_nature' => $blotterDetails['incident_nature'] ?? '',
        'incident_location' => $blotterDetails['incident_location'] ?? '',
        'incident_datetime' => $blotterDetails['incident_datetime'] ?? ''
    ],
    'messages' => $messages
]);
?>
