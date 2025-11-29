<?php
session_start();
include '../db.php';

$term = isset($_GET['term']) ? $_GET['term'] : '';

if (!$term) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("
    SELECT resident_id, CONCAT(first_name, ' ', IFNULL(middle_name,''), ' ', last_name) AS full_name, email_address
    FROM residents
    WHERE (first_name LIKE ? OR middle_name LIKE ? OR last_name LIKE ?)
    AND resident_id NOT IN (SELECT resident_id FROM users WHERE resident_id IS NOT NULL)
    AND email_address NOT IN (SELECT email FROM users WHERE email IS NOT NULL)
    LIMIT 10
");

$likeTerm = "%$term%";
$stmt->bind_param("sss", $likeTerm, $likeTerm, $likeTerm);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'label' => trim($row['full_name']), // Only show the name
        'value' => $row['resident_id'],
        'email' => $row['email_address'] // Keep email in data, but not shown
    ];
}

echo json_encode($data);
?>
