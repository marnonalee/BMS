<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("HTTP/1.1 403 Forbidden");
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
if($senior != 'all') $where[] = "is_senior=" . ($senior == '1' ? 1 : 0);
if($pwd != 'all') $where[] = "is_pwd=" . ($pwd == '1' ? 1 : 0);
if($solo != 'all') $where[] = "is_solo_parent=" . ($solo == '1' ? 1 : 0);
if($fourps != 'all') $where[] = "is_4ps=" . ($fourps == '1' ? 1 : 0);
if($employment != 'all') $where[] = "LOWER(TRIM(employment_status)) = LOWER('" . $conn->real_escape_string($employment) . "')";
if($citizenship != 'all') $where[] = "LOWER(TRIM(citizenship)) = LOWER('" . $conn->real_escape_string($citizenship) . "')";

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

$query = $conn->query("
    SELECT 
        SUM(CASE WHEN sex='Male' THEN 1 ELSE 0 END) AS male,
        SUM(CASE WHEN sex='Female' THEN 1 ELSE 0 END) AS female,
        SUM(is_senior) AS seniors,
        SUM(is_pwd) AS pwds,
        SUM(is_solo_parent) AS solo_parents,
        SUM(is_4ps) AS four_ps,
        SUM(CASE WHEN voter_status='Registered' THEN 1 ELSE 0 END) AS voters,
        SUM(CASE WHEN citizenship='Filipino' THEN 1 ELSE 0 END) AS filipino,
        SUM(CASE WHEN citizenship!='Filipino' THEN 1 ELSE 0 END) AS non_filipino,
        SUM(CASE WHEN employment_status='Employed' THEN 1 ELSE 0 END) AS employed,
        SUM(CASE WHEN employment_status='Unemployed' THEN 1 ELSE 0 END) AS unemployed,
        SUM(CASE WHEN employment_status='Student' THEN 1 ELSE 0 END) AS student,
        SUM(CASE WHEN employment_status='Self-Employed' THEN 1 ELSE 0 END) AS self_employed,
        SUM(CASE WHEN employment_status='Retired' THEN 1 ELSE 0 END) AS retired,
        SUM(CASE WHEN employment_status='OFW' THEN 1 ELSE 0 END) AS ofw,
        SUM(CASE WHEN employment_status='IP' THEN 1 ELSE 0 END) AS ip,
        SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 0 AND 4 THEN 1 ELSE 0 END) AS under5,
        SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 5 AND 9 THEN 1 ELSE 0 END) AS age5_9,
        SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 10 AND 14 THEN 1 ELSE 0 END) AS age10_14,
        SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 15 AND 19 THEN 1 ELSE 0 END) AS age15_19,
        SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 20 AND 24 THEN 1 ELSE 0 END) AS age20_24,
        SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 25 AND 29 THEN 1 ELSE 0 END) AS age25_29,
        SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 30 AND 34 THEN 1 ELSE 0 END) AS age30_34,
        SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 35 AND 39 THEN 1 ELSE 0 END) AS age35_39,
        SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 40 AND 44 THEN 1 ELSE 0 END) AS age40_44,
        SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 45 AND 49 THEN 1 ELSE 0 END) AS age45_49,
        SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 50 AND 54 THEN 1 ELSE 0 END) AS age50_54,
        SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 55 AND 59 THEN 1 ELSE 0 END) AS age55_59,
        SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 60 AND 64 THEN 1 ELSE 0 END) AS age60_64,
        SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 65 AND 69 THEN 1 ELSE 0 END) AS age65_69,
        SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 70 AND 74 THEN 1 ELSE 0 END) AS age70_74,
        SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 75 AND 79 THEN 1 ELSE 0 END) AS age75_79,
        SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) >= 80 THEN 1 ELSE 0 END) AS age80_over
    FROM residents
    WHERE $whereSql
");

$data = $query->fetch_assoc();

$ageBrackets = [
    'under5' => intval($data['under5']),
    'age5_9' => intval($data['age5_9']),
    'age10_14' => intval($data['age10_14']),
    'age15_19' => intval($data['age15_19']),
    'age20_24' => intval($data['age20_24']),
    'age25_29' => intval($data['age25_29']),
    'age30_34' => intval($data['age30_34']),
    'age35_39' => intval($data['age35_39']),
    'age40_44' => intval($data['age40_44']),
    'age45_49' => intval($data['age45_49']),
    'age50_54' => intval($data['age50_54']),
    'age55_59' => intval($data['age55_59']),
    'age60_64' => intval($data['age60_64']),
    'age65_69' => intval($data['age65_69']),
    'age70_74' => intval($data['age70_74']),
    'age75_79' => intval($data['age75_79']),
    'age80_over' => intval($data['age80_over'])
];

echo json_encode([
    'male' => intval($data['male']),
    'female' => intval($data['female']),
    'seniors' => intval($data['seniors']),
    'pwds' => intval($data['pwds']),
    'solo_parents' => intval($data['solo_parents']),
    'four_ps' => intval($data['four_ps']),
    'voters' => intval($data['voters']),
    'filipino' => intval($data['filipino']),
    'non_filipino' => intval($data['non_filipino']),
    'employed' => intval($data['employed']),
    'unemployed' => intval($data['unemployed']),
    'student' => intval($data['student']),
    'self_employed' => intval($data['self_employed']),
    'retired' => intval($data['retired']),
    'ofw' => intval($data['ofw']), // <-- new
    'ip' => intval($data['ip']),   // <-- new
    'ageBrackets' => $ageBrackets
]);
