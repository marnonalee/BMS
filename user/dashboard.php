<?php 
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

include 'db.php';

// ------------------ Login Success Message ------------------
$loginSuccessMsg = "";
if (isset($_SESSION["login_success"])) {
    $loginSuccessMsg = $_SESSION["login_success"];
    unset($_SESSION["login_success"]);
}

// ------------------ User Info ------------------
$user_id = $_SESSION["user_id"];
$userQuery = $conn->query("SELECT * FROM users WHERE id = '$user_id'");
$user = $userQuery->fetch_assoc();
$role = $user['role'];

// ------------------ Residents Statistics ------------------
$residentsQuery = $conn->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN sex='Male' THEN 1 ELSE 0 END) AS male,
        SUM(CASE WHEN sex='Female' THEN 1 ELSE 0 END) AS female,
        SUM(is_senior) AS seniors,
        SUM(is_pwd) AS pwds,
        SUM(is_solo_parent) AS solo_parents,
        SUM(CASE WHEN voter_status='Registered' THEN 1 ELSE 0 END) AS voters
    FROM residents
");
$residents = $residentsQuery->fetch_assoc();

$totalResidents = $residents['total'];
$maleResidents = $residents['male'];
$femaleResidents = $residents['female'];
$seniorCount = $residents['seniors'];
$pwdCount = $residents['pwds'];
$soloParentCount = $residents['solo_parents'];
$voterCount = $residents['voters'];

// ------------------ Total Households ------------------
$householdQuery = $conn->query("SELECT COUNT(*) AS total_households FROM households");
$householdData = $householdQuery->fetch_assoc();
$totalHouseholds = $householdData['total_households'];

// ------------------ Certificates Statistics ------------------
$certQuery = $conn->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status='Approved' THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN status='Ready for Pickup' THEN 1 ELSE 0 END) AS ready,
        SUM(CASE WHEN status='Cancelled' THEN 1 ELSE 0 END) AS cancelled
    FROM certificate_requests
");
$certificates = $certQuery->fetch_assoc();

$totalCertificates = $certificates['total'];
$pendingCertificates = $certificates['pending'];
$approvedCertificates = $certificates['approved'];
$readyCertificates = $certificates['ready'];
$cancelledCertificates = $certificates['cancelled'];

// ------------------ Blotter Count ------------------
$blotterQuery = $conn->query("SELECT COUNT(*) AS total FROM blotter_records WHERE archived = 0");
$blotter = $blotterQuery->fetch_assoc();
$blotterCount = $blotter['total'];

// ------------------ Weekly Residents Growth ------------------
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

// ------------------- Population Growth Notification -------------------
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
        if ($result['cnt'] == 0) {
            $insertQuery = $conn->prepare("INSERT INTO notifications (resident_id, message, from_role, title, type, priority, is_read, date_created) VALUES (NULL, ?, 'system', ?, ?, ?, 0, ?)");
            $insertQuery->bind_param("ssssss", $message, $title, $type, $priority, $date_created);
            $insertQuery->execute();
        }
    }
}

// ------------------- Pending Certificates Alert -------------------
$pendingThreshold = 10;
if ($pendingCertificates >= $pendingThreshold) {
    $message = "High number of pending certificates: $pendingCertificates requests awaiting approval.";
    $title = "Pending Requests Alert";
    $type = "system";
    $priority = "high";
    $date_created = date('Y-m-d H:i:s');

    $checkQuery = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE message=? AND from_role='system'");
    $checkQuery->bind_param("s", $message);
    $checkQuery->execute();
    $result = $checkQuery->get_result()->fetch_assoc();
    if ($result['cnt'] == 0) {
        $insertQuery = $conn->prepare("INSERT INTO notifications (resident_id, message, from_role, title, type, priority, is_read, date_created) VALUES (NULL, ?, 'system', ?, ?, ?, 0, ?)");
        $insertQuery->bind_param("ssssss", $message, $title, $type, $priority, $date_created);
        $insertQuery->execute();
    }
}

// ------------------ Age Group Distribution ------------------
$ageQuery = $conn->query("
    SELECT
        SUM(CASE WHEN age BETWEEN 0 AND 17 THEN 1 ELSE 0 END) AS '0-17',
        SUM(CASE WHEN age BETWEEN 18 AND 35 THEN 1 ELSE 0 END) AS '18-35',
        SUM(CASE WHEN age BETWEEN 36 AND 50 THEN 1 ELSE 0 END) AS '36-50',
        SUM(CASE WHEN age >= 51 THEN 1 ELSE 0 END) AS '51+'
    FROM residents
");
$ageData = $ageQuery->fetch_assoc();

// ------------------ Recent Transactions ------------------
$certTransactions = $conn->query("
    SELECT r.first_name, r.last_name, 'Certificate' AS type, status, date_requested AS date
    FROM certificate_requests cr
    JOIN residents r ON cr.resident_id = r.resident_id
    ORDER BY date_requested DESC
    LIMIT 5
");

$blotterTransactions = $conn->query("
    SELECT COALESCE(r.first_name, br.complainant_name) AS first_name, 
           COALESCE(r.last_name, '') AS last_name,
           'Blotter' AS type, br.status, br.created_at AS date
    FROM blotter_records br
    LEFT JOIN residents r ON br.complainant_id = r.resident_id
    ORDER BY br.created_at DESC
    LIMIT 5
");

$recentTransactions = [];
while($row = $certTransactions->fetch_assoc()){
    $recentTransactions[] = [
        'name' => $row['first_name'] . ' ' . $row['last_name'],
        'type' => $row['type'],
        'status' => ucfirst($row['status']),
        'date' => date('Y-m-d', strtotime($row['date']))
    ];
}
while($row = $blotterTransactions->fetch_assoc()){
    $recentTransactions[] = [
        'name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: 'Unknown',
        'type' => $row['type'],
        'status' => ucfirst($row['status']),
        'date' => date('Y-m-d', strtotime($row['date']))
    ];
}

usort($recentTransactions, function($a, $b){
    return strtotime($b['date']) - strtotime($a['date']);
});
$recentTransactions = array_slice($recentTransactions, 0, 5);

// ------------------ Notifications ------------------
$notificationsQuery = $conn->query("
    SELECT n.notification_id, n.resident_id, n.message, n.from_role, n.title, n.type, n.priority, n.is_read, n.date_created
    FROM notifications n
    WHERE n.from_role IN ('system', 'resident') OR n.from_role = '$role'
    ORDER BY n.date_created DESC
    LIMIT 5
");

$notifications = [];
while($row = $notificationsQuery->fetch_assoc()){
    $notifications[] = $row;
}

$unreadCount = count(array_filter($notifications, fn($n) => $n['is_read'] == 0));

// ------------------ Certificate Types & Counts ------------------
$permitTypeQuery = $conn->query("
    SELECT ct.template_name AS certificate_name, COUNT(cr.id) AS total_requests
    FROM certificate_templates ct
    LEFT JOIN certificate_requests cr ON cr.template_id = ct.id
    WHERE ct.template_for = 'Certificate'
    GROUP BY ct.id
");

$permitLabels = [];
$permitCounts = [];
while($row = $permitTypeQuery->fetch_assoc()){
    $permitLabels[] = $row['certificate_name'];
    $permitCounts[] = (int)$row['total_requests'];
}

// ------------------ Population Projection Based on Database Growth ------------------
$avgHouseholdSize = $totalHouseholds > 0 ? $totalResidents / $totalHouseholds : 0;
$lastWeekTotal = $residentsPerWeek[count($residentsPerWeek) - 2] ?? 0;
$currentWeekTotal = $residentsPerWeek[count($residentsPerWeek) - 1] ?? 0;
$growthRate = $lastWeekTotal > 0 ? ($currentWeekTotal - $lastWeekTotal) / $lastWeekTotal : 0;
$newHouseholds = round($totalHouseholds * $growthRate);
$projectedResidents = round($newHouseholds * $avgHouseholdSize);
$growthPercent = round($growthRate * 100, 1);

// ------------------ System Settings ------------------
$settingsQuery = $conn->query("SELECT barangay_name, system_logo FROM system_settings LIMIT 1");
$settings = $settingsQuery->fetch_assoc();
$barangayName = $settings['barangay_name'] ?? 'Barangay Name';
$systemLogo = $settings['system_logo'] ?? 'default-logo.png';
$systemLogoPath = $systemLogo;

// ------------------ AI / Predictive Analytics ------------------
function linear_regression_forecast($x, $y, $next_point) {
    $n = count($x);
    if ($n < 2) return $y[0] ?? 0;
    $sum_x = array_sum($x);
    $sum_y = array_sum($y);
    $sum_xy = $sum_xx = 0;
    for ($i = 0; $i < $n; $i++) {
        $sum_xy += $x[$i] * $y[$i];
        $sum_xx += $x[$i] * $x[$i];
    }
    $denominator = $n * $sum_xx - $sum_x * $sum_x;
    if ($denominator == 0) return $y[$n - 1];
    $slope = ($n * $sum_xy - $sum_x * $sum_y) / $denominator;
    $intercept = ($sum_y - $slope * $sum_x) / $n;
    return $intercept + $slope * $next_point;
}
$weeksWithNext = $weeks;
$weeksWithNext[] = 'Next Week';
$residentsPerWeekWithNull = $residentsPerWeek;
$residentsPerWeekWithNull[] = null;
// Predict next week's population
$weeksNumeric = range(1, count($residentsPerWeek));
$nextWeek = count($residentsPerWeek) + 1;
$predictedPopulationNextWeek = round(linear_regression_forecast($weeksNumeric, $residentsPerWeek, $nextWeek));

// Predict certificate demand for next week
$certGrowthQuery = $conn->query("
    SELECT YEAR(date_requested) AS year, WEEK(date_requested,1) AS week, COUNT(*) AS total
    FROM certificate_requests
    GROUP BY YEAR(date_requested), WEEK(date_requested,1)
    ORDER BY YEAR(date_requested), WEEK(date_requested,1)
");

$certPerWeek = [];
$certWeeksNumeric = [];
$counter = 1;
while($row = $certGrowthQuery->fetch_assoc()){
    $certWeeksNumeric[] = $counter++;
    $certPerWeek[] = (int)$row['total'];
}
$nextCertWeek = $counter;
$predictedCertNextWeek = round(linear_regression_forecast($certWeeksNumeric, $certPerWeek, $nextCertWeek));
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

<div class="flex flex-wrap gap-4">
  <?php if($role === 'admin'): ?>
    <button onclick="window.location.href='residents/resident.php?openAdd=true'" class="bg-green-500 text-white px-4 py-2 rounded shadow hover:bg-green-600 transition">
      Add Resident
    </button>
  <?php endif; ?>
  <button onclick="window.location.href='certificate/walkin_certificates.php?openAdd=true'" class="bg-blue-500 text-white px-4 py-2 rounded shadow hover:bg-blue-600 transition">
    Generate Certificate
  </button>
  <button class="bg-yellow-500 text-white px-4 py-2 rounded shadow hover:bg-yellow-600 transition">
    Generate Report
  </button>
</div>




  <div class="bg-white p-5 rounded-xl shadow">
    <h3 class="text-gray-700 font-semibold mb-4">Population Scenario Analysis</h3>
    <div class="bg-blue-50 p-4 rounded-lg border-l-4 border-blue-400">
      <p class="text-blue-800 font-semibold">Estimated population increase: <span class="text-xl"><?= $projectedResidents ?></span> residents.</p>
      <p class="text-blue-700 text-sm">(Based on average household size of <?= number_format($avgHouseholdSize, 2) ?> residents per household and projected <?= $newHouseholds ?> new households.)</p>
    </div>
  </div>
<div class="bg-white p-5 rounded-xl shadow">
  <h3 class="text-gray-700 font-semibold mb-4">Analytics & Decision Support</h3>

  <!-- Top analytics cards -->
  <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <!-- Solo Parents -->
    <div onclick="window.location.href='residents/resident.php?filter=solo_parent'" class="bg-pink-100 p-4 rounded-lg flex flex-col justify-between cursor-pointer hover:scale-105 transform transition duration-300">
      <h4 class="text-pink-800 font-semibold">Solo Parents</h4>
      <p class="text-2xl font-bold text-pink-900 mt-2"><?= $soloParentCount ?></p>
      <span class="text-sm text-pink-700">Total solo parents</span>
    </div>

    <!-- Blotter Cases -->
    <div onclick="window.location.href='blotter/blotter.php'" class="bg-indigo-100 p-4 rounded-lg flex flex-col justify-between cursor-pointer hover:scale-105 transform transition duration-300">
      <h4 class="text-indigo-800 font-semibold">Blotter Cases</h4>
      <p class="text-2xl font-bold text-indigo-900 mt-2"><?= $blotterCount ?></p>
      <span class="text-sm text-indigo-700">Total blotter cases</span>
    </div>

    <!-- Households -->
    <div onclick="window.location.href='households/household.php'" class="bg-green-100 p-4 rounded-lg flex flex-col justify-between cursor-pointer hover:scale-105 transform transition duration-300">
      <h4 class="text-green-800 font-semibold">Households</h4>
      <p class="text-2xl font-bold text-green-900 mt-2"><?= number_format($totalHouseholds) ?></p>
      <span class="text-sm text-green-700">Total households</span>
    </div>

    <!-- Weekly Resident Growth (existing card) -->
    <div class="bg-blue-100 p-4 rounded-lg flex flex-col justify-between">
      <h4 class="text-blue-800 font-semibold">Weekly Resident Growth</h4>
      <p class="text-2xl font-bold text-blue-900"><?= array_sum($residentsPerWeek) ?></p>
      <span class="text-sm text-blue-700">Total residents added this year</span>
    </div>
  </div>

  <!-- Other analytics cards -->
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

  <!-- Bottom charts -->
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
   // -------- Population Chart --------
    const populationCtx = document.getElementById('populationChart').getContext('2d');
    const populationChart = new Chart(populationCtx, {
        type: 'line',
        data: {
           labels: <?= json_encode($weeksWithNext) ?>,
datasets: [{
    label: 'Residents per Week',
    data: <?= json_encode($residentsPerWeekWithNull) ?>,
    borderColor: 'rgba(59, 130, 246, 1)',
    backgroundColor: 'rgba(59, 130, 246, 0.2)',
    fill: true,
    tension: 0.3
}, {
    label: 'Predicted Next Week',
    data: Array(<?= count($residentsPerWeek) ?>).fill(null).concat([<?= $predictedPopulationNextWeek ?>]),
    borderColor: 'rgba(16, 185, 129, 1)',
    borderDash: [5,5],
    fill: false,
    tension: 0.3
}]

        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: { y: { beginAtZero: true } }
        }
    });

    // -------- Certificate Requests Chart --------
    const certificateCtx = document.getElementById('certificateChart').getContext('2d');
    const certificateChart = new Chart(certificateCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($certWeeksNumeric) ?>,
            datasets: [{
                label: 'Certificates per Week',
                data: <?= json_encode($certPerWeek) ?>,
                backgroundColor: 'rgba(34, 197, 94, 0.6)',
                borderColor: 'rgba(34, 197, 94, 1)',
                borderWidth: 1
            },{
                label: 'Predicted Next Week',
                data: <?= json_encode($certPerWeek) ?>.concat([<?= $predictedCertNextWeek ?>]),
                type: 'line',
                borderColor: 'rgba(239, 68, 68, 1)',
                borderDash: [5,5],
                fill: false,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: { y: { beginAtZero: true } }
        }
    });
document.querySelectorAll('#transactionsTable tbody tr').forEach(row=>{
    row.addEventListener('click',e=>{
        if(e.target.tagName==='BUTTON'||e.target.tagName==='INPUT')return;
        const type=row.dataset.type?.toLowerCase();
        const id=row.dataset.id;
        if(type==='certificate')window.location.href=`certificate/certificate_requests.php?id=${id}`;
        else if(type==='blotter')window.location.href=`blotter/blotter.php?id=${id}`;
        else console.log('Unknown type:',type);
    });
});
const ctxDecision = document.getElementById('decisionSupportChart').getContext('2d');
new Chart(ctxDecision, {
    type: 'bar',
    data: {
        labels: ['Total Residents', 'Seniors', 'PWDs', 'Solo Parents', 'Voters', 'Households', 'Pending Certificates', 'Blotter Cases'],
        datasets: [{
            label: 'Count',
            data: [<?= $totalResidents ?>, <?= $seniorCount ?>, <?= $pwdCount ?>, <?= $soloParentCount ?>, <?= $voterCount ?>, <?= $totalHouseholds ?>, <?= $pendingCertificates ?>, <?= $blotterCount ?>],
            backgroundColor: ['#1E40AF','#FACC15','#A855F7','#EC4899','#06B6D4','#10B981','#EF4444','#4B5563']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            title: {
                display: true,
                text: 'Decision Support Overview',
                font: { size: 16, weight: 'bold' }
            }
        },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
            x: { grid: { color: 'rgba(0,0,0,0.05)' } }
        }
    }
});

document.addEventListener('DOMContentLoaded',()=>{
    const loginSuccessMsg=<?= json_encode($loginSuccessMsg) ?>;
    const modal=document.getElementById('successModal');
    const successMessage=document.getElementById('successMessage');
    const closeBtn=document.getElementById('closeSuccessModal');
    const okBtn=document.getElementById('okSuccessBtn');
    if(loginSuccessMsg&&loginSuccessMsg.length>0){
        successMessage.innerText=loginSuccessMsg;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    closeBtn.addEventListener('click',()=>{modal.classList.add('hidden');modal.classList.remove('flex');});
    okBtn.addEventListener('click',()=>{modal.classList.add('hidden');modal.classList.remove('flex');});
});

const toggleSidebar=document.getElementById('toggleSidebar');
const sidebar=document.getElementById('sidebar');
toggleSidebar.addEventListener('click',()=>{
    sidebar.classList.toggle('sidebar-collapsed');
    toggleSidebar.textContent=toggleSidebar.textContent==='chevron_left'?'chevron_right':'chevron_left';
});

const stats={
    totalResidents:<?= $totalResidents ?>,
    activePermits:<?= $activePermits??0 ?>,
    pendingRequests:<?= $pendingCertificates ?>,
    seniorCitizens:<?= $seniorCount ?>,
    blotterCases:<?= $blotterCount ?>,
    voters:<?= $voterCount ?>,
    households:<?= $totalHouseholds??0 ?>
};

for(const key in stats){
    let elem=document.getElementById(key);
    if(!elem)continue;
    let count=0;
    const interval=setInterval(()=>{
        if(count>=stats[key]){
            elem.innerText=key==='monthlyRevenue'?'₱'+stats[key].toLocaleString():stats[key];
            clearInterval(interval);
        }else{
            elem.innerText=key==='monthlyRevenue'?'₱'+count.toLocaleString():count;
            count+=Math.ceil(stats[key]/100);
        }
    },10);
}



const ctxAgeDistribution=document.getElementById('ageDistributionChart').getContext('2d');
new Chart(ctxAgeDistribution,{
    type:'pie',
    data:{
        labels:['0-17 yrs old','18-35 yrs old','36-50 yrs old','51+ yrs old'],
        datasets:[{
            data:[<?= $ageData['0-17'] ?>,<?= $ageData['18-35'] ?>,<?= $ageData['36-50'] ?>,<?= $ageData['51+'] ?>],
            backgroundColor:['#1E40AF','#3B82F6','#2563EB','#60A5FA'],
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
        animation:{
            animateRotate:true,
            duration:1500,
            easing:'easeOutCubic'
        },
        onClick:function(evt,activeEls){
            if(activeEls.length>0){
                const index=activeEls[0].index;
                const label=this.data.labels[index];
                let ageFilter='';
                if(label.includes('0-17'))ageFilter='0-17';
                else if(label.includes('18-35'))ageFilter='18-35';
                else if(label.includes('36-50'))ageFilter='36-50';
                else if(label.includes('51+'))ageFilter='51+';
                window.location.href='residents/resident.php?filter='+encodeURIComponent(ageFilter);
            }
        }
    }
});


document.getElementById('searchTable').addEventListener('input',function(){
    const filter=this.value.toLowerCase();
    document.querySelectorAll('#transactionsTable tbody tr').forEach(row=>row.style.display=row.innerText.toLowerCase().includes(filter)?'':'none');
});

const notificationBtn=document.getElementById('notificationBtn');
const notificationDropdown=document.getElementById('notificationDropdown');
notificationBtn.addEventListener('click',()=>notificationDropdown.classList.toggle('hidden'));
document.addEventListener('click',e=>{if(!notificationWrapper.contains(e.target))notificationDropdown.classList.add('hidden');});
document.querySelectorAll('.notification-item').forEach(item=>{
    item.addEventListener('click',()=>{
        const notificationId=item.dataset.id;
        const residentId=item.dataset.resident;
        fetch(`mark_notification_read.php?id=${notificationId}`).then(()=>{
            item.classList.remove('font-bold');
            const countBadge=document.getElementById('notificationCount');
            if(countBadge){
                let count=parseInt(countBadge.innerText)-1;
                if(count<=0)countBadge.remove();
                else countBadge.innerText=count;
            }
        });
        window.location.href=`residents/resident.php?resident_id=${residentId}`;
    });
});

const userBtn=document.getElementById('userBtn');
const userDropdown=document.getElementById('userDropdown');
userBtn.addEventListener('click',()=>userDropdown.classList.toggle('hidden'));
document.addEventListener('click',e=>{if(!userBtn.contains(e.target)&&!userDropdown.contains(e.target))userDropdown.classList.add('hidden');});
</script>



</body>
</html>
