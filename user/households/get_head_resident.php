<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

include '../db.php';
header('Content-Type: application/json');

$query = isset($_GET['query']) ? $conn->real_escape_string($_GET['query']) : '';

$sql = "SELECT resident_id, user_id, first_name, middle_name, last_name, sex, birthdate, voter_status, resident_address, street
        FROM residents
        WHERE (household_id IS NULL OR household_id = 0)
        AND is_archived = 0
        AND (first_name LIKE '%$query%' OR middle_name LIKE '%$query%' OR last_name LIKE '%$query%')
        ORDER BY last_name ASC
        LIMIT 10";

$result = $conn->query($sql);
$residents = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['full_name'] = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']);
        // Calculate age
        if (!empty($row['birthdate'])) {
            $birth = new DateTime($row['birthdate']);
            $now = new DateTime();
            $row['age'] = $birth->diff($now)->y;
        } else {
            $row['age'] = 'N/A';
        }
        $row['voter_status'] = !empty($row['voter_status']) ? $row['voter_status'] : 'N/A';
        $row['resident_address'] = !empty($row['resident_address']) ? $row['resident_address'] : 'N/A';
        $row['street'] = !empty($row['street']) ? $row['street'] : 'N/A';
        $residents[] = $row;
    }
}

echo json_encode($residents);
