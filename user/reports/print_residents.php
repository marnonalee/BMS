<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../../login.php");
    exit();
}
include '../db.php';

$sex = $_GET['sex'] ?? 'all';
$voter = $_GET['voter'] ?? 'all';
$senior = $_GET['senior'] ?? 'all';
$pwd = $_GET['pwd'] ?? 'all';
$solo = $_GET['solo'] ?? 'all';
$fourps = $_GET['fourps'] ?? 'all';
$ageCategory = $_GET['ageCategory'] ?? 'all';
$citizenship = $_GET['citizenship'] ?? 'all';
$employment = $_GET['employment'] ?? 'all';

$where = [];
if ($sex != 'all') $where[] = "sex='" . $conn->real_escape_string($sex) . "'";
if ($voter != 'all') $where[] = "voter_status='" . $conn->real_escape_string($voter) . "'";
if ($senior != 'all') $where[] = "is_senior=" . ($senior == '1' ? 1 : 0);
if ($pwd != 'all') $where[] = "is_pwd=" . ($pwd == '1' ? 1 : 0);
if ($solo != 'all') $where[] = "is_solo_parent=" . ($solo == '1' ? 1 : 0);
if ($fourps != 'all') $where[] = "is_4ps=" . ($fourps == '1' ? 1 : 0);
if ($employment != 'all') $where[] = "(LOWER(TRIM(employment_status)) = LOWER('" . $conn->real_escape_string($employment) . "') OR (employment_status='Student' AND school_status IN ('osc','osy','enrolled')))";
if ($citizenship != 'all') $where[] = "LOWER(TRIM(citizenship)) = LOWER('" . $conn->real_escape_string($citizenship) . "')";

if ($ageCategory != 'all') {
    if ($ageCategory === 'children') $where[] = "TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) <= 17";
    elseif ($ageCategory === 'adult') $where[] = "TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 18 AND 59";
    elseif ($ageCategory === 'senior') $where[] = "TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) >= 60";
    elseif ($ageCategory === '80+') $where[] = "TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) >= 80";
    elseif (preg_match('/^(\d+)-(\d+)$/', $ageCategory, $matches)) {
        $min = intval($matches[1]);
        $max = intval($matches[2]);
        $where[] = "TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN $min AND $max";
    }
}

$whereSql = $where ? implode(' AND ', $where) : '1';
$resQuery = $conn->query("SELECT * FROM residents WHERE $whereSql ORDER BY last_name, first_name ASC");
$residents = $resQuery->fetch_all(MYSQLI_ASSOC);

$totals = [
    'total' => 0,
    'male' => 0,
    'female' => 0,
    'seniors' => 0,
    'pwds' => 0,
    'solo_parents' => 0,
    'four_ps' => 0,
    'osc' => 0,
    'osy' => 0,
    'enrolled' => 0,
    'registered' => 0,
    'unregistered' => 0
];

foreach ($residents as $r) {
    $totals['total']++;
    if ($r['sex'] == 'Male') $totals['male']++;
    if ($r['sex'] == 'Female') $totals['female']++;
    if ($r['is_senior']) $totals['seniors']++;
    if ($r['is_pwd']) $totals['pwds']++;
    if ($r['is_solo_parent']) $totals['solo_parents']++;
    if ($r['is_4ps']) $totals['four_ps']++;
    if ($r['school_status'] == 'osc') $totals['osc']++;
    if ($r['school_status'] == 'osy') $totals['osy']++;
    if ($r['school_status'] == 'enrolled') $totals['enrolled']++;
    $voter = strtolower(trim($r['voter_status']));
    if ($voter == 'registered') $totals['registered']++;
    elseif ($voter == 'unregistered') $totals['unregistered']++;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Barangay Residents Report</title>
<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1 { text-align: center; margin-bottom: 10px; }
table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
th, td { border: 1px solid #333; padding: 6px; font-size: 12px; text-align: left; }
th { background-color: #d4edda; color: #000; }
.summary-table { page-break-before: always; width: 100%; border-collapse: collapse; }
.summary-table td { border: 1px solid #333; padding: 8px; font-size: 12px; font-weight: bold; background-color: #d9edf7; }
@media print { th, td, .summary-table td { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
<script>
window.onload = function() { window.print(); };
</script>
</head>
<body>
<h1>Barangay Residents Report</h1>
<p>Date: <?= date('F d, Y') ?></p>


<table>
<thead>
<tr>
<th>#</th>
<th>Full Name</th>
<th>Sex</th>
<th>Age</th>
<th>Category</th>
<th>Voter Status</th>
<th>Address</th>
<th>Citizenship</th>
<th>Employment Status</th>
<th>Student Status</th>
</tr>
</thead>
<tbody>
<?php foreach($residents as $i=>$r): 
$age = date_diff(date_create($r['birthdate']), date_create('today'))->y;
$categories = [];
if($r['is_senior']) $categories[] = 'Senior';
if($r['is_pwd']) $categories[] = 'PWD';
if($r['is_solo_parent']) $categories[] = 'Solo Parent';
if($r['is_4ps']) $categories[] = '4Ps';
$voterStatus = ucfirst(strtolower($r['voter_status']));
$studentStatus = in_array($r['school_status'], ['osc','osy','enrolled']) ? strtoupper($r['school_status']) : '';
?>
<tr>
<td><?= $i+1 ?></td>
<td><?= htmlspecialchars($r['first_name'].' '.$r['middle_name'].' '.$r['last_name']) ?></td>
<td><?= $r['sex'] ?></td>
<td><?= $age ?></td>
<td><?= implode(', ', $categories) ?></td>
<td><?= $voterStatus ?></td>
<td><?= $r['resident_address'] ?></td>
<td><?= $r['citizenship'] ?></td>
<td><?= $r['employment_status'] ?></td>
<td><?= $studentStatus ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<table class="summary-table">
<tr>
<td>Male: <?= $totals['male'] ?>, Female: <?= $totals['female'] ?></td>
<td>Seniors: <?= $totals['seniors'] ?>, PWDs: <?= $totals['pwds'] ?>, Solo: <?= $totals['solo_parents'] ?>, 4Ps: <?= $totals['four_ps'] ?></td>
<td>Registered: <?= $totals['registered'] ?>, Unregistered: <?= $totals['unregistered'] ?></td>
<td>Total Residents: <?= $totals['total'] ?></td>
<td>OSC: <?= $totals['osc'] ?>, OSY: <?= $totals['osy'] ?>, Enrolled: <?= $totals['enrolled'] ?></td>
</tr>
</table>

</body>
</html>
