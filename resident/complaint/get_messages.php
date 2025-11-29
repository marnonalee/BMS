<?php
session_start();
header('Content-Type: application/json'); // ensure JS treats response as JSON
include '../../db.php';

// Ensure parameters are integers
$resident_id = isset($_GET['resident_id']) ? intval($_GET['resident_id']) : 0;
$staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : 0;

if (!$resident_id || !$staff_id) {
    echo json_encode(['messages' => []]);
    exit;
}

// Fetch messages
$query = $conn->prepare("
    SELECT m.*, 
           CASE 
               WHEN m.sender_role = 'staff' THEN s.first_name 
               WHEN m.sender_role = 'resident' THEN r.first_name 
           END AS sender_name,
           CASE 
               WHEN m.sender_role = 'staff' THEN COALESCE(s.profile_photo, 'default.png')
               WHEN m.sender_role = 'resident' THEN COALESCE(r.profile_photo, 'default.png')
           END AS profile_photo
    FROM messages m
    LEFT JOIN staff s ON m.staff_id = s.staff_id
    LEFT JOIN residents r ON m.resident_id = r.resident_id
    WHERE m.resident_id = ? AND m.staff_id = ?
    ORDER BY m.timestamp ASC
");
$query->bind_param("ii", $resident_id, $staff_id);
$query->execute();
$result = $query->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'from_role' => $row['sender_role'],
        'message' => htmlspecialchars($row['content']),
        'profile_photo' => !empty($row['profile_photo']) 
            ? '../uploads/' . basename($row['profile_photo'])
            : 'https://cdn-icons-png.flaticon.com/512/149/149071.png',
        'sender_name' => htmlspecialchars($row['sender_name']),
        'timestamp' => $row['timestamp']
    ];
}

// Output JSON
echo json_encode(['messages' => $messages]);
exit;
?>
