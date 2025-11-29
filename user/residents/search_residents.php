<?php
include '../db.php';

$q = $_GET['q'] ?? '';

$sql = "SELECT * FROM residents 
        WHERE first_name LIKE '%$q%' 
        OR middle_name LIKE '%$q%' 
        OR last_name LIKE '%$q%' 
        OR resident_address LIKE '%$q%' 
         OR sex LIKE '%$q%' 
        ORDER BY last_name ASC";

$result = $conn->query($sql);

if($result->num_rows > 0){
    while($r = $result->fetch_assoc()){
        echo "
        <tr class='hover:bg-gray-100 cursor-pointer'>
            <td class='px-4 py-2'>{$r['first_name']} {$r['middle_name']} {$r['last_name']}</td>
            <td class='px-4 py-2'>{$r['sex']}</td>
            <td class='px-4 py-2'>{$r['age']}</td>
            <td class='px-4 py-2'>{$r['voter_status']}</td>
            <td class='px-4 py-2'>{$r['resident_address']}</td>
            <td class='px-4 py-2'>{$r['street']}</td>
        </tr>";
    }
} else {
    echo "<tr><td colspan='6' class='text-center py-2'>No residents found.</td></tr>";
}
