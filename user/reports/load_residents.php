<?php
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
$schoolStatus = $_GET['schoolStatus'] ?? 'all'; 
$perPage = intval($_GET['perPage'] ?? 50);
$page = intval($_GET['page'] ?? 1);

$where = ["is_archived = 0"];

if($sex != 'all') $where[] = "sex='" . $conn->real_escape_string($sex) . "'";
if($voter != 'all') $where[] = "voter_status='" . $conn->real_escape_string($voter) . "'";
if($senior != 'all') $where[] = "is_senior=" . ($senior == '1' ? 1 : 0);
if($pwd != 'all') $where[] = "is_pwd=" . ($pwd == '1' ? 1 : 0);
if($solo != 'all') $where[] = "is_solo_parent=" . ($solo == '1' ? 1 : 0);
if($fourps != 'all') $where[] = "is_4ps=" . ($fourps == '1' ? 1 : 0);
if($employment != 'all') $where[] = "LOWER(TRIM(employment_status)) = LOWER('" . $conn->real_escape_string($employment) . "')";
if($citizenship != 'all') $where[] = "LOWER(TRIM(citizenship)) = LOWER('" . $conn->real_escape_string($citizenship) . "')";

if($schoolStatus != 'all') {
    if($schoolStatus === 'student') {
        $where[] = "school_status IN ('osc','osy','enrolled')";
    } else {
        $where[] = "school_status='" . $conn->real_escape_string($schoolStatus) . "'";
    }
}

if($ageCategory != 'all') {
    if($ageCategory === 'children') $where[] = "TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) <= 17";
    elseif($ageCategory === 'adult') $where[] = "TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 18 AND 59";
    elseif($ageCategory === 'senior') $where[] = "TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) >= 60";
    elseif($ageCategory === '80+') $where[] = "TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) >= 80";
    elseif(preg_match('/^(\d+)-(\d+)$/', $ageCategory, $matches)) {
        $min = intval($matches[1]);
        $max = intval($matches[2]);
        $where[] = "TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN $min AND $max";
    }
}

$whereSql = $where ? implode(' AND ', $where) : '1';

$totalQuery = $conn->query("SELECT COUNT(*) AS total FROM residents WHERE $whereSql");
$total = $totalQuery->fetch_assoc()['total'];

$offset = ($page - 1) * $perPage;

$resQuery = $conn->query("
    SELECT first_name, last_name, sex, birthdate, is_senior, is_pwd, is_solo_parent, is_4ps, voter_status, resident_address, citizenship, employment_status, school_status
    FROM residents
    WHERE $whereSql
    ORDER BY first_name ASC
    LIMIT $perPage OFFSET $offset
");

$rows = '';
while($row = $resQuery->fetch_assoc()) {
    $age = date_diff(date_create($row['birthdate']), date_create('today'))->y;
    $categories = [];
    if($row['is_senior']) $categories[] = "Senior";
    if($row['is_pwd']) $categories[] = "PWD";
    if($row['is_solo_parent']) $categories[] = "Solo Parent";
    if($row['is_4ps']) $categories[] = "4Ps";
    $categories[] = $row['voter_status'];
    if(in_array($row['school_status'], ['osc','osy','enrolled'])) $categories[] = "Student";
    $rows .= "<tr>
        <td>{$row['first_name']} {$row['last_name']}</td>
        <td>{$row['sex']}</td>
        <td>{$age}</td>
        <td>".implode(", ", $categories)."</td>
        <td>{$row['resident_address']}</td>
        <td>{$row['citizenship']}</td>
        <td>{$row['employment_status']}</td>
    </tr>";
}

function countCategory($conn, $column, $value = null) {
    $sql = "SELECT COUNT(*) FROM residents WHERE is_archived = 0";
    if($value !== null) {
        if($column === 'school_status' && $value === 'student') {
            $sql .= " AND school_status IN ('osc','osy','enrolled')";
        } else {
            $sql .= " AND LOWER(TRIM($column)) = LOWER('" . $conn->real_escape_string($value) . "')";
        }
    }
    return $conn->query($sql)->fetch_row()[0];
}

$chartData = [
    'seniors' => countCategory($conn, 'is_senior', '1'),
    'pwds' => countCategory($conn, 'is_pwd', '1'),
    'solo_parents' => countCategory($conn, 'is_solo_parent', '1'),
    'four_ps' => countCategory($conn, 'is_4ps', '1'),
    'voters' => countCategory($conn, 'voter_status', 'Registered'),
    'filipino' => countCategory($conn, 'citizenship', 'Filipino'),
    'non_filipino' => countCategory($conn, 'citizenship', 'Non-Filipino'),
    'employed' => countCategory($conn, 'employment_status', 'Employed'),
    'unemployed' => countCategory($conn, 'employment_status', 'Unemployed'),
    'student' => countCategory($conn, 'school_status', 'student'), // UPDATED
    'self_employed' => countCategory($conn, 'employment_status', 'Self-Employed'),
    'retired' => countCategory($conn, 'employment_status', 'Retired'),
    'ofw' => countCategory($conn, 'employment_status', 'OFW'),
    'ip' => countCategory($conn, 'employment_status', 'IP')
];

$totalPages = ceil($total / $perPage);

echo json_encode(array_merge([
    'rows' => $rows,
    'total' => $total,
    'page' => $page,
    'totalPages' => $totalPages
], $chartData));
?>
