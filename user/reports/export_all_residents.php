<?php
session_start();
include '../db.php';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=All_Residents_Report.xls");

// Fetch all residents
$query = $conn->query("SELECT first_name, middle_name, last_name, sex, birthdate, 
    TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) AS age,
    voter_status, is_senior, is_pwd, is_solo_parent, is_4ps,
    citizenship, employment_status, resident_address
    FROM residents WHERE is_archived = 0");

echo "<table border='1'>";
echo "<tr>
        <th>Name</th>
        <th>Sex</th>
        <th>Age</th>
        <th>Voter Status</th>
        <th>Senior</th>
        <th>PWD</th>
        <th>Solo Parent</th>
        <th>4Ps</th>
        <th>Citizenship</th>
        <th>Employment Status</th>
        <th>Address</th>
      </tr>";

while($row = $query->fetch_assoc()){
    $name = $row['first_name'].' '.$row['middle_name'].' '.$row['last_name'];
    echo "<tr>
        <td>{$name}</td>
        <td>{$row['sex']}</td>
        <td>{$row['age']}</td>
        <td>{$row['voter_status']}</td>
        <td>".($row['is_senior'] ? 'Yes' : 'No')."</td>
        <td>".($row['is_pwd'] ? 'Yes' : 'No')."</td>
        <td>".($row['is_solo_parent'] ? 'Yes' : 'No')."</td>
        <td>".($row['is_4ps'] ? 'Yes' : 'No')."</td>
        <td>{$row['citizenship']}</td>
        <td>{$row['employment_status']}</td>
        <td>{$row['resident_address']}</td>
    </tr>";
}
echo "</table>";
?>
