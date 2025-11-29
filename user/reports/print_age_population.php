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
if($sex != 'all') $where[] = "sex='" . $conn->real_escape_string($sex) . "'";
if($voter != 'all') $where[] = "voter_status='" . $conn->real_escape_string($voter) . "'";
if($senior != 'all') $where[] = "is_senior=" . ($senior=='1'?1:0);
if($pwd != 'all') $where[] = "is_pwd=" . ($pwd=='1'?1:0);
if($solo != 'all') $where[] = "is_solo_parent=" . ($solo=='1'?1:0);
if($fourps != 'all') $where[] = "is_4ps=" . ($fourps=='1'?1:0);
if($citizenship != 'all') $where[] = "LOWER(TRIM(citizenship)) = LOWER('" . $conn->real_escape_string($citizenship) . "')";
if($employment != 'all') $where[] = "LOWER(TRIM(employment_status)) = LOWER('" . $conn->real_escape_string($employment) . "')";
$whereSql = $where ? implode(' AND ', $where) : '1';

$ageRanges = [
    'Under 5 years old' => [0,4],
    '5-9 years old' => [5,9],
    '10-14 years old' => [10,14],
    '15-19 years old' => [15,19],
    '20-24 years old' => [20,24],
    '25-29 years old' => [25,29],
    '30-34 years old' => [30,34],
    '35-39 years old' => [35,39],
    '40-44 years old' => [40,44],
    '45-49 years old' => [45,49],
    '50-54 years old' => [50,54],
    '55-59 years old' => [55,59],
    '60-64 years old' => [60,64],
    '65-69 years old' => [65,69],
    '70-74 years old' => [70,74],
    '75-79 years old' => [75,79],
    '80 years old and over' => [80,150]
];

$ageData = [];
foreach($ageRanges as $label => $range){
    $male = $conn->query("SELECT COUNT(*) FROM residents WHERE $whereSql AND sex='Male' AND TIMESTAMPDIFF(YEAR,birthdate,CURDATE()) BETWEEN {$range[0]} AND {$range[1]}")->fetch_row()[0];
    $female = $conn->query("SELECT COUNT(*) FROM residents WHERE $whereSql AND sex='Female' AND TIMESTAMPDIFF(YEAR,birthdate,CURDATE()) BETWEEN {$range[0]} AND {$range[1]}")->fetch_row()[0];
    $ageData[] = ['label'=>$label,'male'=>$male,'female'=>$female,'total'=>$male+$female];
}

$populationSections = [
    'Special Sectors' => [
        'Seniors' => 'is_senior',
        'PWD' => 'is_pwd',
        'Solo Parents' => 'is_solo_parent',
        '4Ps Beneficiaries' => 'is_4ps'
    ],
    'Employment Status / Student' => [
        'Employed' => "employment_status='Employed'",
        'Unemployed' => "employment_status='Unemployed'",
        'OSC' => "school_status='osc'",
        'OSY' => "school_status='osy'",
        'Currently Enrolled' => "school_status='enrolled'",
        'Self-Employed' => "employment_status='Self-Employed'",
        'Retired' => "employment_status='Retired'",
        'OFW' => "employment_status='OFW'",
        'IP' => "employment_status='IP'"
    ],
    'Civil Status' => [
        'Single' => "civil_status='Single'",
        'Married' => "civil_status='Married'",
        'Widowed' => "civil_status='Widowed'",
        'Separated' => "civil_status='Separated'"
    ],
    'Citizenship' => [
        'Filipino' => "LOWER(TRIM(citizenship))='filipino'",
        'Non-Filipino' => "LOWER(TRIM(citizenship))!='filipino'"
    ]
];

$populationData = [];
foreach($populationSections as $sectionLabel => $items){
    $section = ['label'=>$sectionLabel,'rows'=>[]];
    foreach($items as $label => $condition){
        $male = $conn->query("SELECT COUNT(*) FROM residents WHERE $whereSql AND sex='Male' AND $condition")->fetch_row()[0];
        $female = $conn->query("SELECT COUNT(*) FROM residents WHERE $whereSql AND sex='Female' AND $condition")->fetch_row()[0];
        $section['rows'][] = ['label'=>$label,'male'=>$male,'female'=>$female,'total'=>$male+$female];
    }
    $populationData[] = $section;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Population Report</title>
<style>
body{font-family:Arial,sans-serif;margin:20px;}
table{border-collapse:collapse;width:100%;margin-bottom:30px;}
th,td{border:1px solid #000;padding:6px;text-align:center;font-size:10px;}
th{background:#d4edda;}
.group-header{background:#c3e6cb;text-align:left;font-size:10px;}
h1,h2,h3{margin-bottom:10px;}
@media print {
    th,.group-header{-webkit-print-color-adjust:exact;print-color-adjust:exact;}
}
</style>
</head>
<body>
<h3> Monitoring Report</h3>
<h4>Date: <?= date('F d, Y') ?></h4>
<table>
<thead>
<tr><th>Category</th><th>Male</th><th>Female</th><th>Total</th></tr>
</thead>
<tbody>
<tr><th colspan="4" class="group-header">Population by Age Bracket</th></tr>
<?php foreach($ageData as $row): ?>
<tr>
<td><?= htmlspecialchars($row['label']) ?></td>
<td><?= $row['male'] ?></td>
<td><?= $row['female'] ?></td>
<td><?= $row['total'] ?></td>
</tr>
<?php endforeach; ?>

<?php foreach($populationData as $section): ?>
<tr><th colspan="4" class="group-header"><?= $section['label'] ?></th></tr>
<?php foreach($section['rows'] as $row): ?>
<tr>
<td><?= htmlspecialchars($row['label']) ?></td>
<td><?= $row['male'] ?></td>
<td><?= $row['female'] ?></td>
<td><?= $row['total'] ?></td>
</tr>
<?php endforeach; ?>
<?php endforeach; ?>
</tbody>
</table>
<script>window.print();</script>
</body>
</html>
