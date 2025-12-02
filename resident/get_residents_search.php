<?php
include '../db.php';

$query = isset($_GET['query']) ? $conn->real_escape_string($_GET['query']) : '';

// Fetch residents excluding those already in a household or with pending/approved requests
$sql = "
SELECT 
    r.resident_id, 
    r.first_name, 
    r.middle_name, 
    r.last_name, 
    r.birthdate, 
    r.sex, 
    r.civil_status,
    r.voter_status, 
    r.resident_address, 
    r.street,
    r.household_id,
    r.is_archived
FROM residents r
LEFT JOIN family_member_requests fmr 
    ON r.resident_id = fmr.member_resident_id 
    AND fmr.status IN ('Pending','Approved')
WHERE (r.household_id IS NULL OR r.household_id = 0)
AND r.is_archived = 0
AND fmr.request_id IS NULL
AND (
        r.first_name LIKE '%$query%' 
        OR r.middle_name LIKE '%$query%' 
        OR r.last_name LIKE '%$query%'
    )
ORDER BY r.last_name ASC
LIMIT 10
";

$result = $conn->query($sql);
$residents = [];

while ($row = $result->fetch_assoc()) {

    $full_name = trim(
        $row['first_name'] . ' ' .
        ($row['middle_name'] ? $row['middle_name'] . ' ' : '') .
        $row['last_name']
    );

    $full_address = trim($row['resident_address'] . ' ' . $row['street']);

    $residents[] = [
        'resident_id' => $row['resident_id'],
        'first_name' => $row['first_name'],
        'middle_name' => $row['middle_name'],
        'last_name' => $row['last_name'],
        'full_name' => $full_name,
        'birthdate' => $row['birthdate'],
        'sex' => $row['sex'],                   
        'civil_status' => $row['civil_status'], 
        'voter_status' => $row['voter_status'], 
        'resident_address' => $row['resident_address'],
        'street' => $row['street'],
        'full_address' => $full_address
    ];
}

header('Content-Type: application/json');
echo json_encode($residents);
