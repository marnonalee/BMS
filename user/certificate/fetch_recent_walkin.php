<?php
session_start();
include '../db.php';

$recentRequests = $conn->query("SELECT cr.id, r.first_name, r.last_name, t.template_name, cr.purpose, cr.status, cr.date_requested 
                                FROM certificate_requests cr 
                                JOIN residents r ON cr.resident_id = r.resident_id 
                                JOIN certificate_templates t ON cr.template_id = t.id 
                                WHERE cr.request_type = 'Walk-in' AND r.is_archived = 0
                                ORDER BY cr.date_requested DESC LIMIT 10");

$rows = '';
if($recentRequests->num_rows > 0){
    while($r = $recentRequests->fetch_assoc()){
        $printFile = "";
        switch($r['template_name']){
            case "Barangay Certification": $printFile="print_certificate_barangay.php"; break;
            case "Certificate of Attestation": $printFile="print_attestation.php"; break;
            case "Certificate of Guardianship": $printFile="print_guardianship.php"; break;
            case "Construction Permit": $printFile="print_construction.php"; break;
        }

        $rows .= '<tr>
            <td class="px-4 py-2">'.htmlspecialchars($r['first_name'].' '.$r['last_name']).'</td>
            <td class="px-4 py-2">'.htmlspecialchars($r['template_name']).'</td>
            <td class="px-4 py-2">'.htmlspecialchars($r['purpose']).'</td>
            <td class="px-4 py-2 status-'.$r['status'].'">'.$r['status'].'</td>
            <td class="px-4 py-2">'.$r['date_requested'].'</td>
            <td class="px-4 py-2">
                <a href="'.$printFile.'?id='.$r['id'].'" target="_blank" class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600">Print</a>
            </td>
        </tr>';
    }
} else {
    $rows = '<tr><td colspan="6" class="px-4 py-4 text-center text-gray-500">No walk-in certificates yet.</td></tr>';
}

echo $rows;
?>
