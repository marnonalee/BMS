<?php
include '../db.php';

$query = isset($_GET['query']) ? $conn->real_escape_string($_GET['query']) : '';

// Fetch residents not assigned to any family and not archived
$sql = "SELECT resident_id, first_name, middle_name, last_name, birthdate, sex, voter_status, resident_address, street
        FROM residents 
        WHERE (household_id IS NULL OR household_id = 0)
        AND is_archived = 0
        AND (first_name LIKE '%$query%' OR middle_name LIKE '%$query%' OR last_name LIKE '%$query%')
        ORDER BY last_name ASC
        LIMIT 10";

$result = $conn->query($sql);
$residents = [];

while($row = $result->fetch_assoc()) {
    $row['full_name'] = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']);
    $row['age'] = $row['birthdate'] ? floor((time() - strtotime($row['birthdate'])) / (365.25*24*60*60)) : null;
    $residents[] = $row;
}

header('Content-Type: application/json');
echo json_encode($residents);
?>
