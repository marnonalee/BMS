<?php 
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

include 'db.php';

$loginSuccessMsg = $_SESSION["login_success"] ?? "";
unset($_SESSION["login_success"]);

$user_id = $_SESSION["user_id"];
$user = $conn->query("SELECT * FROM users WHERE id = '$user_id'")->fetch_assoc();
$role = $user['role'];

$statsQuery = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM residents) AS total_residents,
        (SELECT SUM(CASE WHEN sex='Male' THEN 1 ELSE 0 END) FROM residents) AS male_residents,
        (SELECT SUM(CASE WHEN sex='Female' THEN 1 ELSE 0 END) FROM residents) AS female_residents,
        (SELECT SUM(is_senior) FROM residents) AS seniors,
        (SELECT SUM(is_pwd) FROM residents) AS pwds,
        (SELECT SUM(is_solo_parent) FROM residents) AS solo_parents,
        (SELECT SUM(CASE WHEN voter_status='Registered' THEN 1 ELSE 0 END) FROM residents) AS voters,
        (SELECT COUNT(*) FROM households) AS total_households,
        (SELECT COUNT(*) FROM certificate_requests) AS total_certificates,
        (SELECT SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) FROM certificate_requests) AS pending_certificates,
        (SELECT SUM(CASE WHEN status='Approved' THEN 1 ELSE 0 END) FROM certificate_requests) AS approved_certificates,
        (SELECT SUM(CASE WHEN status='Ready for Pickup' THEN 1 ELSE 0 END) FROM certificate_requests) AS ready_certificates,
        (SELECT SUM(CASE WHEN status='Cancelled' THEN 1 ELSE 0 END) FROM certificate_requests) AS cancelled_certificates,
        (SELECT COUNT(*) FROM blotter_records WHERE archived=0) AS blotter_count
");
$stats = $statsQuery->fetch_assoc();

$totalResidents = $stats['total_residents'];
$maleResidents = $stats['male_residents'];
$femaleResidents = $stats['female_residents'];
$seniorCount = $stats['seniors'];
$pwdCount = $stats['pwds'];
$soloParentCount = $stats['solo_parents'];
$voterCount = $stats['voters'];
$totalHouseholds = $stats['total_households'];
$totalCertificates = $stats['total_certificates'];
$pendingCertificates = $stats['pending_certificates'];
$approvedCertificates = $stats['approved_certificates'];
$readyCertificates = $stats['ready_certificates'];
$cancelledCertificates = $stats['cancelled_certificates'];
$blotterCount = $stats['blotter_count'];

$growthQuery = $conn->query("
    SELECT YEAR(date_registered) AS year, WEEK(date_registered, 1) AS week, COUNT(*) AS total
    FROM residents
    GROUP BY YEAR(date_registered), WEEK(date_registered, 1)
    ORDER BY YEAR(date_registered), WEEK(date_registered, 1)
");
$weeks = [];
$residentsPerWeek = [];
while($row = $growthQuery->fetch_assoc()){
    $weeks[] = 'Y'.$row['year'].'-W'.$row['week'];
    $residentsPerWeek[] = (int)$row['total'];
}

$lastWeekTotal = $residentsPerWeek[count($residentsPerWeek) - 2] ?? 0;
$currentWeekTotal = $residentsPerWeek[count($residentsPerWeek) - 1] ?? 0;

if ($lastWeekTotal > 0) {
    $growthPercent = (($currentWeekTotal - $lastWeekTotal) / $lastWeekTotal) * 100;
    if ($growthPercent > 10) {
        $message = "Resident population increased by " . round($growthPercent, 1) . "% compared to last week.";
        $title = "Population Alert";
        $type = "system";
        $priority = "high";
        $date_created = date('Y-m-d H:i:s');
        $checkQuery = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE message=? AND from_role='system'");
        $checkQuery->bind_param("s", $message);
        $checkQuery->execute();
        $result = $checkQuery->get_result()->fetch_assoc();
        if ($result['cnt']==0) {
            $insertQuery = $conn->prepare("INSERT INTO notifications (resident_id,message,from_role,title,type,priority,is_read,date_created) VALUES (NULL,?,?,?,?,?,0,?)");
            $insertQuery->bind_param("ssssss",$message,$title,$type,$priority,$date_created,$date_created);
            $insertQuery->execute();
        }
    }
}

$pendingThreshold = 10;
if ($pendingCertificates >= $pendingThreshold) {
    $message = "High number of pending certificates: $pendingCertificates requests awaiting approval.";
    $title = "Pending Requests Alert";
    $type = "system";
    $priority = "high";
    $date_created = date('Y-m-d H:i:s');
    $checkQuery = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE message=? AND from_role='system'");
    $checkQuery->bind_param("s",$message);
    $checkQuery->execute();
    $result = $checkQuery->get_result()->fetch_assoc();
    if ($result['cnt']==0) {
        $insertQuery = $conn->prepare("INSERT INTO notifications (resident_id,message,from_role,title,type,priority,is_read,date_created) VALUES (NULL,?,?,?,?,?,0,?)");
        $insertQuery->bind_param("ssssss",$message,$title,$type,$priority,$date_created,$date_created);
        $insertQuery->execute();
    }
}

$ageData = $conn->query("
    SELECT
        SUM(CASE WHEN age BETWEEN 0 AND 17 THEN 1 ELSE 0 END) AS '0-17',
        SUM(CASE WHEN age BETWEEN 18 AND 35 THEN 1 ELSE 0 END) AS '18-35',
        SUM(CASE WHEN age BETWEEN 36 AND 50 THEN 1 ELSE 0 END) AS '36-50',
        SUM(CASE WHEN age >= 51 THEN 1 ELSE 0 END) AS '51+'
    FROM residents
")->fetch_assoc();

$recentTransactionsQuery = $conn->query("
    SELECT * FROM (
        SELECT r.first_name, r.last_name, 'Certificate' AS type, status, date_requested AS date
        FROM certificate_requests cr
        JOIN residents r ON cr.resident_id = r.resident_id
        UNION ALL
        SELECT COALESCE(r.first_name, br.complainant_name) AS first_name,
               COALESCE(r.last_name,'') AS last_name,
               'Blotter' AS type, br.status, br.created_at AS date
        FROM blotter_records br
        LEFT JOIN residents r ON br.complainant_id = r.resident_id
    ) t
    ORDER BY date DESC
    LIMIT 5
");
$recentTransactions = [];
while($row = $recentTransactionsQuery->fetch_assoc()){
    $recentTransactions[] = [
        'name'=>trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? ''))?:'Unknown',
        'type'=>$row['type'],
        'status'=>ucfirst($row['status']),
        'date'=>date('Y-m-d',strtotime($row['date']))
    ];
}

$notificationsQuery = $conn->query(" 
    SELECT * FROM notifications
    WHERE from_role = 'resident'
    ORDER BY date_created DESC
    LIMIT 5
");

$notifications = [];
while ($row = $notificationsQuery->fetch_assoc()) {
    $notifications[] = $row;
}

$unreadCount = count(array_filter($notifications, fn($n) => $n['is_read'] == 0));

$permitTypeQuery = $conn->query("
    SELECT ct.template_name AS certificate_name, COUNT(cr.id) AS total_requests
    FROM certificate_templates ct
    LEFT JOIN certificate_requests cr ON cr.template_id = ct.id
    WHERE ct.template_for='Certificate'
    GROUP BY ct.id
");
$permitLabels=[];$permitCounts=[];
while($row=$permitTypeQuery->fetch_assoc()){
    $permitLabels[]=$row['certificate_name'];
    $permitCounts[]=(int)$row['total_requests'];
}

$avgHouseholdSize=$totalHouseholds>0?$totalResidents/$totalHouseholds:0;
$growthRate=$lastWeekTotal>0?($currentWeekTotal-$lastWeekTotal)/$lastWeekTotal:0;
$newHouseholds=round($totalHouseholds*$growthRate);
$projectedResidents=round($newHouseholds*$avgHouseholdSize);
$growthPercent=round($growthRate*100,1);

$settings=$conn->query("SELECT barangay_name,system_logo FROM system_settings LIMIT 1")->fetch_assoc();
$barangayName=$settings['barangay_name']??'Barangay Name';
$systemLogo=$settings['system_logo']??'default-logo.png';
$systemLogoPath=$systemLogo;

function linear_regression_forecast($x,$y,$next_point){
    $n=count($x);
    if($n<2) return $y[0]??0;
    $sum_x=array_sum($x);
    $sum_y=array_sum($y);
    $sum_xy=$sum_xx=0;
    for($i=0;$i<$n;$i++){
        $sum_xy+=$x[$i]*$y[$i];
        $sum_xx+=$x[$i]*$x[$i];
    }
    $denominator=$n*$sum_xx-$sum_x*$sum_x;
    if($denominator==0) return $y[$n-1];
    $slope=($n*$sum_xy-$sum_x*$sum_y)/$denominator;
    $intercept=($sum_y-$slope*$sum_x)/$n;
    return $intercept+$slope*$next_point;
}

$weeksWithNext=$weeks;$weeksWithNext[]='Next Week';
$residentsPerWeekWithNull=$residentsPerWeek;$residentsPerWeekWithNull[]=null;
$weeksNumeric=range(1,count($residentsPerWeek));
$nextWeek=count($residentsPerWeek)+1;
$predictedPopulationNextWeek=round(linear_regression_forecast($weeksNumeric,$residentsPerWeek,$nextWeek));

$certGrowthQuery=$conn->query("
    SELECT YEAR(date_requested) AS year, WEEK(date_requested,1) AS week, COUNT(*) AS total
    FROM certificate_requests
    GROUP BY YEAR(date_requested), WEEK(date_requested,1)
    ORDER BY YEAR(date_requested), WEEK(date_requested,1)
");
$certPerWeek=[];$certWeeksNumeric=[];$counter=1;
while($row=$certGrowthQuery->fetch_assoc()){$certWeeksNumeric[]=$counter++;$certPerWeek[]=(int)$row['total'];}
$nextCertWeek=$counter;
$predictedCertNextWeek=round(linear_regression_forecast($certWeeksNumeric,$certPerWeek,$nextCertWeek));
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Barangay Admin Dashboard</title>
<link rel="stylesheet" href="a.css">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
</head>
<body class="font-sans main-bg">
<div class=" flex h-screen">
<aside id="sidebar" class="w-64 bg-gradient-to-b from-blue-500 to-blue-700 text-white flex flex-col shadow-xl transition-all duration-300 h-screen">
    <div class="flex items-center justify-between p-4 border-b border-white/20">
        <div class="flex items-center space-x-3"><img src="<?= htmlspecialchars($systemLogo) ?>" alt="Barangay Logo" class="w-16 h-16 rounded-full object-cover shadow-sm border-2 border-white transition-all">
            <span class="font-semibold text-lg sidebar-text"><?= htmlspecialchars($barangayName) ?></span>
        </div>
        <button id="toggleSidebar" class="material-icons cursor-pointer text-2xl">chevron_left</button>
    </div>

  <nav class="flex-1 overflow-y-auto px-2 py-5 space-y-2">
    <a href="dashboard.php" class="flex items-center px-4 py-3 rounded-md bg-white/10 backdrop-blur-sm transition-all">
      <span class="material-icons mr-3">dashboard</span><span class="sidebar-text">Dashboard</span>
    </a>

    <a href="officials/barangay_officials.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 transition-colors">
      <span class="material-icons mr-3">groups</span><span class="sidebar-text">Barangay Officials</span>
    </a>

    <a href="residents/resident.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 transition-colors">
      <span class="material-icons mr-3">people</span><span class="sidebar-text">Residents</span>
    </a>

    <a href="households/household.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
      <span class="material-icons mr-3">home</span><span class="sidebar-text">Household</span>
    </a>
          <?php if($role === 'admin'): ?>
        <div class="pt-4">
            <span class="px-4 py-2 text-white/70 uppercase text-xs tracking-wider sidebar-text">Requests</span>
            <a href="requests/household_member_requests.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
                <span class="material-icons mr-3">group_add</span><span class="sidebar-text">Household Member Requests</span>
            </a>
            <a href="requests/request_profile_update.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
                <span class="material-icons mr-3">pending_actions</span><span class="sidebar-text">Profile Update Requests</span>
            </a>
        </div>
        <?php endif; ?>

    <?php if($role === 'admin'): ?>
      <div class="pt-4">
        <span class="px-4 py-2 text-white/70 uppercase text-xs tracking-wider sidebar-text">Community</span>
        <a href="announcements.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
          <span class="material-icons mr-3">campaign</span><span class="sidebar-text">Announcements</span>
        </a>
        <a href="news_updates.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 transition-colors">
          <span class="material-icons mr-3">article</span><span class="sidebar-text">News & Updates</span>
        </a>
      </div>
    <?php endif; ?>

    <div class="pt-4">
      <span class="px-4 py-2 text-white/70 uppercase text-xs tracking-wider sidebar-text">Certificate Management</span>
      <a href="certificate/certificate_requests.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
        <span class="material-icons mr-3">assignment</span><span class="sidebar-text">Certificate Requests</span>
      </a>
      <a href="certificate/walkin_certificates.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
        <span class="material-icons mr-3">how_to_reg</span><span class="sidebar-text">Walk-in Requests</span>
      </a>
    </div>

    <div class="pt-4">
      <span class="px-4 py-2 text-white/70 uppercase text-xs tracking-wider sidebar-text">Blotter</span>
      <a href="blotter/blotter.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
        <span class="material-icons mr-3">gavel</span><span class="sidebar-text">Blotter Records</span>
      </a>
    </div>

    <a href="reports/report.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
      <span class="material-icons mr-3">bar_chart</span><span class="sidebar-text">Reports</span>
    </a>

    <div class="pt-4">
      <span class="px-4 py-2 text-white/70 uppercase text-xs tracking-wider sidebar-text">ID Management</span>
      <a href="id/barangay_id_requests.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
        <span class="material-icons mr-3">credit_card</span><span class="sidebar-text">Barangay ID Request</span>
      </a>
      <a href="id/walk_in_request.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
        <span class="material-icons mr-3">how_to_reg</span><span class="sidebar-text">Walk-in Request</span>
      </a>
    </div>

    <?php if($role === 'admin'): ?>
      <div class="pt-4">
        <span class="px-4 py-2 text-white/70 uppercase text-xs tracking-wider sidebar-text">User Management</span>
        <a href="user_manage/user_management.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
          <span class="material-icons mr-3">admin_panel_settings</span><span class="sidebar-text">System User</span>
        </a>
        <a href="user_manage/log_activity.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
          <span class="material-icons mr-3">history</span><span class="sidebar-text">Log Activity</span>
        </a>
        <a href="user_manage/settings.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
          <span class="material-icons mr-3">settings</span><span class="sidebar-text">Settings</span>
        </a>
      </div>
    <?php endif; ?>

    <a href="../logout.php" class="flex items-center px-4 py-3 rounded bg-red-600 hover:bg-red-700 transition-colors mt-2">
      <span class="material-icons mr-3">logout</span><span class="sidebar-text">Logout</span>
    </a>
  </nav>
</aside>
<div class="main-bg flex-1 flex flex-col bg-gray-50">

  <header class="flex items-center justify-between bg-white shadow-md px-6 py-4 rounded-b-2xl flex-shrink-0 mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Dashboard</h2>
    <div class="flex items-center space-x-4">

      <!-- Notifications -->
      <div class="relative" id="notificationWrapper">
        <button id="notificationBtn" class="relative p-2 rounded-full hover:bg-gray-100 transition">
          <?php if($unreadCount > 0): ?>
            <span id="notificationCount" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full px-1">
              <?= $unreadCount ?>
            </span>
          <?php endif; ?>
          <span class="material-icons text-gray-600">notifications</span>
        </button>

        <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white shadow-lg rounded-xl z-50 overflow-hidden">
          <ul class="divide-y divide-gray-200 max-h-96 overflow-y-auto">
            <?php if(empty($notifications)): ?>
              <li class="p-3 text-gray-500 text-sm text-center">No notifications</li>
            <?php else: ?>
              <?php foreach($notifications as $note): ?>
                <li class="px-4 py-3 hover:bg-gray-100 cursor-pointer notification-item <?= $note['is_read'] == 0 ? 'font-semibold' : '' ?>"
                    data-id="<?= $note['notification_id'] ?>"
                    data-resident="<?= $note['resident_id'] ?>">
                  <p class="text-gray-700 text-sm"><?= htmlspecialchars($note['message']) ?></p>
                  <span class="text-gray-400 text-xs"><?= date('M d, Y H:i', strtotime($note['date_created'])) ?></span>
                </li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </div>
      </div>

      <!-- User Dropdown -->
      <div class="relative">
        <button id="userBtn" class="flex items-center space-x-2 cursor-pointer focus:outline-none p-2 rounded-lg hover:bg-gray-100 transition">
          <img src="https://img.icons8.com/color/48/000000/user.png" class="w-8 h-8 rounded-full"/>
          <div class="text-left">
            <div class="font-semibold text-gray-700 text-sm"><?= htmlspecialchars($user['username'] ?? 'User') ?></div>
            <div class="text-gray-400 text-xs">(<?= ucfirst($user['role']) ?>)</div>
          </div>
          <span class="material-icons text-gray-500 ml-1">arrow_drop_down</span>
        </button>

        <div id="userDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white shadow-lg rounded-xl z-50 overflow-hidden">
          <ul class="divide-y divide-gray-200">
            <li>
              <a href="manage_profile.php" class="block px-4 py-2 hover:bg-gray-100 text-gray-700 text-sm">
                Account Settings
              </a>
            </li>
            <li>
              <a href="../logout.php" class="block px-4 py-2 hover:bg-gray-100 text-gray-700 text-sm">
                Logout
              </a>
            </li>
          </ul>
        </div>
      </div>

    </div>
  </header>

<main class="flex-1 overflow-y-auto p-6 space-y-6">
  <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
    <?php if($role === 'admin'): ?>
      <div onclick="window.location.href='residents/resident.php?openAdd=true'" 
           class="flex items-center justify-center gap-2 p-3 bg-green-100 rounded-lg shadow hover:shadow-md cursor-pointer transition transform hover:-translate-y-1">
        <span class="text-green-600 text-xl">🧑‍🤝‍🧑</span>
        <span class="font-semibold text-green-700">Add Resident</span>
      </div>
    <?php endif; ?>

    <div onclick="window.location.href='certificate/walkin_certificates.php?openAdd=true'" 
         class="flex items-center justify-center gap-2 p-3 bg-blue-100 rounded-lg shadow hover:shadow-md cursor-pointer transition transform hover:-translate-y-1">
      <span class="text-blue-600 text-xl">📄</span>
      <span class="font-semibold text-blue-700">Generate Certificate</span>
    </div>

    <div class="flex items-center justify-center gap-2 p-3 bg-yellow-100 rounded-lg shadow hover:shadow-md cursor-pointer transition transform hover:-translate-y-1">
      <span class="text-yellow-600 text-xl">📊</span>
      <span class="font-semibold text-yellow-700">Generate Report</span>
    </div>
  </div>

  <div class="bg-white p-5 rounded-xl shadow">
      <h3 class="text-gray-700 font-semibold mb-4">Population Scenario Analysis</h3>
      <div class="bg-blue-50 p-4 rounded-lg border-l-4 border-blue-400">
          <?php 
          $populationChangeText = $projectedResidents >= 0 ? 'increase' : 'decrease';
          $populationChangeAbs = abs($projectedResidents);
          $newHouseholdsAbs = abs($newHouseholds);
          ?>
          <p class="text-blue-800 font-semibold">
              Estimated population <?= $populationChangeText ?>: <span class="text-xl"><?= $populationChangeAbs ?></span> residents.
          </p>
          <p class="text-blue-700 text-sm">
              (Based on average household size of <?= number_format($avgHouseholdSize, 2) ?> residents per household and projected <?= $newHouseholdsAbs ?> new households.)
          </p>
      </div>
  </div>


  <div class="bg-white p-5 rounded-xl shadow">
    <h3 class="text-gray-700 font-semibold mb-4">Analytics & Decision Support</h3>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div onclick="window.location.href='residents/resident.php?filter=solo_parent'" class="bg-pink-100 p-4 rounded-lg flex flex-col justify-between cursor-pointer hover:scale-105 transform transition duration-300">
        <h4 class="text-pink-800 font-semibold">Solo Parents</h4>
        <p class="text-2xl font-bold text-pink-900 mt-2"><?= $soloParentCount ?></p>
        <span class="text-sm text-pink-700">Total solo parents</span>
      </div>
      <div onclick="window.location.href='blotter/blotter.php'" class="bg-indigo-100 p-4 rounded-lg flex flex-col justify-between cursor-pointer hover:scale-105 transform transition duration-300">
        <h4 class="text-indigo-800 font-semibold">Blotter Cases</h4>
        <p class="text-2xl font-bold text-indigo-900 mt-2"><?= $blotterCount ?></p>
        <span class="text-sm text-indigo-700">Total blotter cases</span>
      </div>
      <div onclick="window.location.href='households/household.php'" class="bg-green-100 p-4 rounded-lg flex flex-col justify-between cursor-pointer hover:scale-105 transform transition duration-300">
        <h4 class="text-green-800 font-semibold">Households</h4>
        <p class="text-2xl font-bold text-green-900 mt-2"><?= number_format($totalHouseholds) ?></p>
        <span class="text-sm text-green-700">Total households</span>
      </div>
      <div class="bg-blue-100 p-4 rounded-lg flex flex-col justify-between">
        <h4 class="text-blue-800 font-semibold">Weekly Resident Growth</h4>
        <p class="text-2xl font-bold text-blue-900"><?= array_sum($residentsPerWeek) ?></p>
        <span class="text-sm text-blue-700">Total residents added this year</span>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
      <div class="bg-yellow-100 p-4 rounded-lg flex flex-col justify-between">
        <h4 class="text-yellow-800 font-semibold">Senior Citizens vs PWDs</h4>
        <p class="text-2xl font-bold text-yellow-900"><?= $seniorCount ?> / <?= $pwdCount ?></p>
        <span class="text-sm text-yellow-700">Ratio of seniors to PWDs</span>
      </div>
      <div class="bg-green-100 p-4 rounded-lg flex flex-col justify-between">
        <h4 class="text-green-800 font-semibold">Voter Participation</h4>
        <p class="text-2xl font-bold text-green-900"><?= $voterCount ?>/<?= $totalResidents ?></p>
        <span class="text-sm text-green-700">Registered voters vs total population</span>
      </div>
      <div class="bg-gray-100 p-4 rounded-lg">
        <h4 class="text-gray-700 font-semibold mb-2">Population Insights Chart</h4>
        <canvas id="decisionSupportChart" class="w-full h-64"></canvas>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="bg-white p-6 rounded-xl shadow-md col-span-1">
        <h2 class="text-lg font-semibold mb-2">Predicted Population (Next Week)</h2>
        <p class="text-3xl font-bold text-blue-600 mb-4"><?= $predictedPopulationNextWeek ?></p>
        <canvas id="populationChart" class="w-full h-48"></canvas>
      </div>
      <div class="bg-white p-6 rounded-xl shadow-md col-span-1">
        <h2 class="text-lg font-semibold mb-2">Predicted Certificate Requests (Next Week)</h2>
        <p class="text-3xl font-bold text-green-600 mb-4"><?= $predictedCertNextWeek ?></p>
        <canvas id="certificateChart" class="w-full h-48"></canvas>
      </div>
      <div class="bg-white p-5 rounded-xl shadow col-span-1">
        <h3 class="text-gray-700 font-semibold mb-4">Age Distribution</h3>
        <canvas id="ageDistributionChart" class="w-full h-48"></canvas>
      </div>
    </div>
  </div>

  <div class="bg-white p-5 rounded-xl shadow overflow-x-auto">
    <h3 class="text-gray-700 font-semibold mb-4">Recent Transactions</h3>
    <input type="text" id="searchTable" placeholder="Search..." class="mb-2 px-3 py-2 border rounded w-full sm:w-1/3 focus:outline-none focus:ring-2 focus:ring-blue-400">
    <table class="min-w-full divide-y divide-gray-200" id="transactionsTable">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($recentTransactions as $transaction): 
          $statusColor = match(strtolower($transaction['status'])) {
            'pending' => 'text-yellow-500',
            'approved', 'ready for pickup', 'done', 'completed' => 'text-green-500',
            'rejected', 'cancelled' => 'text-red-500',
            default => 'text-gray-500'
          };
        ?>
        <tr class="hover:bg-gray-100 cursor-pointer">
          <td class="px-4 py-2"><?= htmlspecialchars($transaction['name']) ?></td>
          <td class="px-4 py-2"><?= htmlspecialchars($transaction['type']) ?></td>
          <td class="px-4 py-2 <?= $statusColor ?> font-semibold"><?= htmlspecialchars($transaction['status']) ?></td>
          <td class="px-4 py-2"><?= htmlspecialchars($transaction['date']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>


    </div>
</div>
<div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-80 text-center relative">
        <button id="closeSuccessModal" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 material-icons">close</button>
        <span class="material-icons text-green-500 text-4xl mb-2">check_circle</span>
        <p id="successMessage" class="text-gray-700 font-medium"></p>
        <button id="okSuccessBtn" class="mt-4 bg-emerald-500 text-white px-4 py-2 rounded hover:bg-green-600">OK</button>
    </div>
</div>
<script>
const COLORS = {
    blue:'#3B82F6', lightBlue:'#60A5FA', pink:'#EC4899', green:'#10B981',
    yellow:'#FACC15', red:'#EF4444', purple:'#A855F7', cyan:'#06B6D4', gray:'#4B5563'
};

// Animate counters slowly & smoothly
function animateCounter(id, value){
    const elem = document.getElementById(id);
    if(!elem) return;
    const duration = 2000; // 2 seconds
    const startTime = performance.now();

    function update(currentTime){
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const currentValue = Math.floor(progress * value);
        elem.innerText = id==='monthlyRevenue' ? '₱'+currentValue.toLocaleString() : currentValue;
        if(progress < 1) requestAnimationFrame(update);
        else elem.innerText = id==='monthlyRevenue' ? '₱'+value.toLocaleString() : value;
    }

    requestAnimationFrame(update);
}

// Initialize all charts
function initCharts(){
    // Population Chart
    const populationCtx = document.getElementById('populationChart').getContext('2d');
    const gradient = populationCtx.createLinearGradient(0,0,0,200);
    gradient.addColorStop(0,'rgba(16,185,129,0.2)');
    gradient.addColorStop(1,'rgba(16,185,129,0)');

    new Chart(populationCtx,{
        type:'line',
        data:{
            labels: <?= json_encode($weeksWithNext) ?>,
            datasets:[
                {
                    label:'Residents per Week',
                    data: <?= json_encode($residentsPerWeekWithNull) ?>,
                    borderColor: COLORS.blue,
                    backgroundColor:'rgba(59,130,246,0.3)',
                    fill:true,
                    tension:0.3,
                    pointBackgroundColor: COLORS.blue
                },
                {
                    label:'Forecast Next Week',
                    data: Array(<?= count($residentsPerWeek) ?>).fill(null).concat([<?= $predictedPopulationNextWeek ?>]),
                    borderColor: COLORS.green,
                    borderDash:[5,5],
                    fill: true,
                    backgroundColor: gradient,
                    tension:0.3,
                    pointBackgroundColor: COLORS.pink,
                    pointRadius: 6
                }
            ]
        },
        options:{
            responsive:true,
            plugins:{
                legend:{position:'top'},
                tooltip:{
                    callbacks:{
                        label: function(context){
                            if(context.dataIndex === <?= count($residentsPerWeek) ?>) return 'Forecast: ' + context.formattedValue;
                            return 'Actual: ' + context.formattedValue;
                        }
                    }
                }
            },
            scales:{y:{beginAtZero:true}},
            animation:{duration:2000, easing:'easeOutCubic'}
        }
    });

    // Certificate Chart
    const certificateCtx = document.getElementById('certificateChart').getContext('2d');
    new Chart(certificateCtx,{
        type:'bar',
        data:{
            labels: <?= json_encode($certWeeksNumeric) ?>,
            datasets:[
                {
                    label:'Certificates per Week',
                    data: <?= json_encode($certPerWeek) ?>,
                    backgroundColor:'rgba(16,185,129,0.6)',
                    borderColor: COLORS.green,
                    borderWidth:1
                },
                {
                    label:'Predicted Next Week',
                    data: <?= json_encode($certPerWeek) ?>.concat([<?= $predictedCertNextWeek ?>]),
                    type:'line',
                    borderColor: COLORS.red,
                    borderDash:[5,5],
                    fill:false,
                    tension:0.3
                }
            ]
        },
        options:{
            responsive:true,
            plugins:{legend:{position:'top'}},
            scales:{y:{beginAtZero:true}},
            animation:{duration:2000, easing:'easeOutCubic'}
        }
    });

    // Decision Support Chart
    const ctxDecision = document.getElementById('decisionSupportChart').getContext('2d');
    new Chart(ctxDecision,{
        type:'bar',
        data:{
            labels:['Total Residents','Seniors','PWDs','Solo Parents','Voters','Households','Pending Certificates','Blotter Cases'],
            datasets:[{
                label:'Count',
                data:[<?= $totalResidents ?>,<?= $seniorCount ?>,<?= $pwdCount ?>,<?= $soloParentCount ?>,<?= $voterCount ?>,<?= $totalHouseholds ?>,<?= $pendingCertificates ?>,<?= $blotterCount ?>],
                backgroundColor:[COLORS.blue,COLORS.yellow,COLORS.purple,COLORS.pink,COLORS.cyan,COLORS.green,COLORS.red,COLORS.gray]
            }]
        },
        options:{
            responsive:true,
            plugins:{legend:{display:false},title:{display:true,text:'Decision Support Overview',font:{size:16,weight:'bold'}}},
            scales:{y:{beginAtZero:true,grid:{color:'rgba(0,0,0,0.05)'}},x:{grid:{color:'rgba(0,0,0,0.05)'}}},
            animation:{duration:2000, easing:'easeOutCubic'}
        }
    });

    // Age Distribution Pie Chart
    const ctxAgeDistribution = document.getElementById('ageDistributionChart').getContext('2d');
    new Chart(ctxAgeDistribution,{
        type:'pie',
        data:{
            labels:['0-17 yrs old','18-35 yrs old','36-50 yrs old','51+ yrs old'],
            datasets:[{
                data:[<?= $ageData['0-17'] ?>,<?= $ageData['18-35'] ?>,<?= $ageData['36-50'] ?>,<?= $ageData['51+'] ?>],
                backgroundColor:[COLORS.blue,COLORS.cyan,COLORS.purple,COLORS.pink],
                borderWidth:1
            }]
        },
        plugins:[ChartDataLabels],
        options:{
            responsive:true,
            layout:{padding:20},
            plugins:{
                legend:{display:false},
                datalabels:{
                    color:'#000',
                    formatter:(value,ctx)=>ctx.chart.data.labels[ctx.dataIndex]+" ("+value+")",
                    anchor:'end',
                    align:'end',
                    offset:10
                }
            },
            cutout:'0%',
            radius:'70%',
            animation:{animateRotate:true,duration:2000,easing:'easeOutCubic'},
            onClick:function(evt,activeEls){
                if(activeEls.length>0){
                    const index=activeEls[0].index;
                    const label=this.data.labels[index];
                    let ageFilter='';
                    if(label.includes('0-17')) ageFilter='0-17';
                    else if(label.includes('18-35')) ageFilter='18-35';
                    else if(label.includes('36-50')) ageFilter='36-50';
                    else if(label.includes('51+')) ageFilter='51+';
                    window.location.href='residents/resident.php?filter='+encodeURIComponent(ageFilter);
                }
            }
        }
    });
}

// DOMContentLoaded
document.addEventListener('DOMContentLoaded',()=>{
    const loginSuccessMsg=<?= json_encode($loginSuccessMsg) ?>;
    const modal=document.getElementById('successModal');
    const successMessage=document.getElementById('successMessage');
    const closeBtn=document.getElementById('closeSuccessModal');
    const okBtn=document.getElementById('okSuccessBtn');

    if(loginSuccessMsg && loginSuccessMsg.length>0){
        successMessage.innerText = loginSuccessMsg;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    closeBtn.addEventListener('click',()=>{modal.classList.add('hidden'); modal.classList.remove('flex');});
    okBtn.addEventListener('click',()=>{modal.classList.add('hidden'); modal.classList.remove('flex');});

    initCharts();

    // Animate counters on scroll
    const stats = {
        totalResidents: <?= $totalResidents ?>,
        activePermits: <?= $activePermits??0 ?>,
        pendingRequests: <?= $pendingCertificates ?>,
        seniorCitizens: <?= $seniorCount ?>,
        blotterCases: <?= $blotterCount ?>,
        voters: <?= $voterCount ?>,
        households: <?= $totalHouseholds??0 ?>
    };

    const observer = new IntersectionObserver(entries=>{
        entries.forEach(entry=>{
            if(entry.isIntersecting){
                const id = entry.target.id;
                if(stats[id]) animateCounter(id, stats[id]);
                entry.target.classList.add('fadeInUp');
                observer.unobserve(entry.target);
            }
        });
    }, {threshold:0.3});

    document.querySelectorAll('.stat-counter,.chart-card').forEach(el=>observer.observe(el));
});

// Table row navigation
document.querySelectorAll('#transactionsTable tbody tr').forEach(row=>{
    row.addEventListener('click',e=>{
        if(e.target.tagName==='BUTTON'||e.target.tagName==='INPUT') return;
        const type=row.dataset.type?.toLowerCase();
        const id=row.dataset.id;
        if(type==='certificate') window.location.href=`certificate/certificate_requests.php?id=${id}`;
        else if(type==='blotter') window.location.href=`blotter/blotter.php?id=${id}`;
    });
});

// Search filter
document.getElementById('searchTable').addEventListener('input',function(){
    const filter=this.value.toLowerCase();
    document.querySelectorAll('#transactionsTable tbody tr').forEach(row=>row.style.display=row.innerText.toLowerCase().includes(filter)?'':'none');
});

// Notification dropdown
const notificationBtn=document.getElementById('notificationBtn');
const notificationDropdown=document.getElementById('notificationDropdown');
const notificationWrapper=document.getElementById('notificationWrapper');

notificationBtn.addEventListener('click',()=>notificationDropdown.classList.toggle('hidden'));
document.addEventListener('click',e=>{
    if(!notificationWrapper.contains(e.target)) notificationDropdown.classList.add('hidden');
});

document.querySelectorAll('.notification-item').forEach(item=>{
    item.addEventListener('click',()=>{
        const notificationId=item.dataset.id;
        const residentId=item.dataset.resident;
        fetch(`mark_notification_read.php?id=${notificationId}`).then(()=>{
            item.classList.remove('font-bold');
            const countBadge=document.getElementById('notificationCount');
            if(countBadge){
                let count=parseInt(countBadge.innerText)-1;
                if(count<=0) countBadge.remove();
                else countBadge.innerText=count;
            }
        });
        window.location.href=`residents/resident.php?resident_id=${residentId}`;
    });
});

// User dropdown
const userBtn=document.getElementById('userBtn');
const userDropdown=document.getElementById('userDropdown');
userBtn.addEventListener('click',()=>userDropdown.classList.toggle('hidden'));
document.addEventListener('click',e=>{
    if(!userBtn.contains(e.target)&&!userDropdown.contains(e.target)) userDropdown.classList.add('hidden');
});

// Sidebar toggle
const toggleSidebar=document.getElementById('toggleSidebar');
const sidebar=document.getElementById('sidebar');
toggleSidebar.addEventListener('click',()=>{
    sidebar.classList.toggle('sidebar-collapsed');
    toggleSidebar.textContent = toggleSidebar.textContent==='chevron_left'?'chevron_right':'chevron_left';
});
</script>

<style>
.fadeInUp{
    opacity:0;
    transform:translateY(50px);
    animation:fadeUp 1s forwards;
}
@keyframes fadeUp{
    to{opacity:1;transform:translateY(0);}
}
.chart-card, .stat-counter{
    transition:all 0.8s ease-out;
}
</style>


</body>
</html>
