<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../../login.php");
    exit();
}
include '../db.php';

$user_id = $_SESSION["user_id"];
$userQuery = $conn->query("SELECT * FROM users WHERE id = '$user_id'");
$user = $userQuery->fetch_assoc();
$role = $user['role'];

$residentsQuery = $conn->query("
    SELECT 
        COUNT(*) AS total,
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
        SUM(
            CASE 
                WHEN employment_status='Student' 
                    OR school_status IN ('osc','osy','enrolled') 
                THEN 1 
                ELSE 0 
            END
        ) AS student,

        SUM(CASE WHEN employment_status='Self-Employed' THEN 1 ELSE 0 END) AS self_employed,
        SUM(CASE WHEN employment_status='Retired' THEN 1 ELSE 0 END) AS retired,
        SUM(CASE WHEN employment_status='OFW' THEN 1 ELSE 0 END) AS ofw,
        SUM(CASE WHEN employment_status='IP' THEN 1 ELSE 0 END) AS ip
    FROM residents
    WHERE is_archived = 0
");

$residents = $residentsQuery->fetch_assoc();

$ageBracketQuery = $conn->query("
    SELECT 
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
    WHERE is_archived = 0
");
$ageBrackets = $ageBracketQuery->fetch_assoc();

$blotterQuery = $conn->query("SELECT COUNT(*) AS total FROM blotter_records WHERE archived = 0");
$blotter = $blotterQuery->fetch_assoc();
$settingsQuery = $conn->query("SELECT barangay_name, system_logo FROM system_settings LIMIT 1");
$settings = $settingsQuery->fetch_assoc();
$barangayName = $settings['barangay_name'] ?? 'Barangay Name';
$systemLogo = $settings['system_logo'] ?? 'default-logo.png';
$systemLogoPath = '../' . $systemLogo;
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Barangay Reports</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="report.css">
</head>
<body class="font-sans">
<div class="flex h-screen">
<aside id="sidebar" class="w-64 bg-gradient-to-b from-blue-500 to-blue-700 text-white flex flex-col shadow-xl transition-all duration-300 h-screen">
    <div class="flex items-center justify-between p-4 border-b border-white/20">
        <div class="flex items-center space-x-3"><img src="<?= htmlspecialchars($systemLogoPath) ?>"
     alt="Barangay Logo"
     class="w-16 h-16 rounded-full object-cover shadow-sm border-2 border-white bg-white p-1 transition-all">

            <span class="font-semibold text-lg sidebar-text"><?= htmlspecialchars($barangayName) ?></span>
        </div>
        <button id="toggleSidebar" class="material-icons cursor-pointer text-2xl">chevron_left</button>
    </div>

  <nav class="flex-1 overflow-y-auto px-2 py-5 space-y-2">
    <a href="../dashboard.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 transition-colors">
      <span class="material-icons mr-3">dashboard</span><span class="sidebar-text">Dashboard</span>
    </a>

    <a href="../officials/barangay_officials.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 transition-colors">
      <span class="material-icons mr-3">groups</span><span class="sidebar-text">Barangay Officials</span>
    </a>

    <a href="../residents/resident.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 transition-colors">
      <span class="material-icons mr-3">people</span><span class="sidebar-text">Residents</span>
    </a>

    <a href="../households/household.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 transition-colors">
      <span class="material-icons mr-3">home</span><span class="sidebar-text">Household</span>
    </a>
       <?php if($role === 'admin'): ?>
        <div class="pt-4">
            <span class="px-4 py-2 text-white/70 uppercase text-xs tracking-wider sidebar-text">Requests</span>
            <a href="../requests/household_member_requests.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
                <span class="material-icons mr-3">group_add</span><span class="sidebar-text">Household Member Requests</span>
            </a>
            <a href="../requests/request_profile_update.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
                <span class="material-icons mr-3">pending_actions</span><span class="sidebar-text">Profile Update Requests</span>
            </a>
        </div>
        <?php endif; ?>

    <?php if($role === 'admin'): ?>
      <div class="pt-4">
        <span class="px-4 py-2 text-white/70 uppercase text-xs tracking-wider sidebar-text">Community</span>
        <a href="../announcements.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
          <span class="material-icons mr-3">campaign</span><span class="sidebar-text">Announcements</span>
        </a>
        <a href="../news_updates.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
          <span class="material-icons mr-3">article</span><span class="sidebar-text">News & Updates</span>
        </a>
      </div>
    <?php endif; ?>

    <div class="pt-4">
      <span class="px-4 py-2 text-white/70 uppercase text-xs tracking-wider sidebar-text">Certificate Management</span>
      <a href="../certificate/certificate_requests.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
        <span class="material-icons mr-3">assignment</span><span class="sidebar-text">Certificate Requests</span>
      </a>
      <a href="../certificate/walkin_certificates.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
        <span class="material-icons mr-3">how_to_reg</span><span class="sidebar-text">Walk-in Requests</span>
      </a>
    </div>

    <div class="pt-4">
      <span class="px-4 py-2 text-white/70 uppercase text-xs tracking-wider sidebar-text">Blotter</span>
      <a href="../blotter/blotter.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
        <span class="material-icons mr-3">gavel</span><span class="sidebar-text">Blotter Records</span>
      </a>
    </div>

    <a href="../reports/report.php" class="flex items-center px-4 py-3 rounded-md bg-white/10 backdrop-blur-sm transition-all">
      <span class="material-icons mr-3">bar_chart</span><span class="sidebar-text">Reports</span>
    </a>

    <div class="pt-4">
      <span class="px-4 py-2 text-white/70 uppercase text-xs tracking-wider sidebar-text">ID Management</span>
      <a href="../id/barangay_id_requests.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
        <span class="material-icons mr-3">credit_card</span><span class="sidebar-text">Barangay ID Request</span>
      </a>
      <a href="../id/walk_in_request.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
        <span class="material-icons mr-3">how_to_reg</span><span class="sidebar-text">Walk-in Request</span>
      </a>
    </div>

    <?php if($role === 'admin'): ?>
      <div class="pt-4">
        <span class="px-4 py-2 text-white/70 uppercase text-xs tracking-wider sidebar-text">User Management</span>
        <a href="../user_manage/user_management.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
          <span class="material-icons mr-3">admin_panel_settings</span><span class="sidebar-text">System User</span>
        </a>
        <a href="../user_manage/log_activity.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
          <span class="material-icons mr-3">history</span><span class="sidebar-text">Activity Logs</span>
        </a>
        <a href="../user_manage/settings.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
          <span class="material-icons mr-3">settings</span><span class="sidebar-text">Settings</span>
        </a>
      </div>
    <?php endif; ?>

    <a href="../../logout.php" class="flex items-center px-4 py-3 rounded bg-red-600 hover:bg-red-700 transition-colors mt-2">
      <span class="material-icons mr-3">logout</span><span class="sidebar-text">Logout</span>
    </a>
  </nav>
</aside>
<div class="flex-1 flex flex-col overflow-hidden bg-gray-50">
  <header class="flex-shrink-0 flex items-center justify-between bg-white shadow-md px-6 py-4 rounded-b-2xl mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Resident Reports</h2>
    <button id="exportExcelBtn" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded shadow flex items-center gap-2">
    <span class="material-icons">file_download</span> Export Excel
</button>

  </header>

  <main class="flex-1 overflow-y-auto p-6">

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
  <div class="bg-white p-5 rounded-xl shadow-lg">
    <h2 class="text-lg font-semibold mb-2">Residents by Sex</h2>
    <canvas id="residentsSexChart" class="w-full h-64"></canvas>
  </div>

  <div class="bg-white p-5 rounded-xl shadow-lg">
    <h2 class="text-lg font-semibold mb-2">Population by Sector</h2>
    <canvas id="categoriesChart" class="w-full h-64"></canvas>
  </div>

  <div class="bg-white p-5 rounded-xl shadow-lg">
      <h2 class="text-lg font-semibold mb-2">Population by Age Bracket</h2>
      <canvas id="ageChart" class="w-full h-64"></canvas>
  </div>

</div>

<div class="bg-white p-4 rounded-lg shadow mb-4 flex flex-wrap gap-4 items-center">

  <div>
    <label class="font-semibold text-gray-700 mr-2">Sex:</label>
    <select id="sexFilter" class="border border-gray-300 rounded px-2 py-1">
      <option value="all">All</option>
      <option value="Male">Male</option>
      <option value="Female">Female</option>
    </select>
  </div>

  <div>
    <label class="font-semibold text-gray-700 mr-2">Voter Status:</label>
    <select id="voterFilter" class="border border-gray-300 rounded px-2 py-1">
      <option value="all">All</option>
      <option value="Registered">Registered</option>
      <option value="Unregistered">Unregistered</option>
    </select>
  </div>
<div>
    <label class="font-semibold text-gray-700 mr-2">Age Category:</label>
    <select id="ageCategoryFilter" class="border border-gray-300 rounded px-2 py-1">
        <option value="all">All</option>
        <option value="0-4">Under 5</option>
        <option value="5-9">5-9</option>
        <option value="10-14">10-14</option>
        <option value="15-19">15-19</option>
        <option value="20-24">20-24</option>
        <option value="25-29">25-29</option>
        <option value="30-34">30-34</option>
        <option value="35-39">35-39</option>
        <option value="40-44">40-44</option>
        <option value="45-49">45-49</option>
        <option value="50-54">50-54</option>
        <option value="55-59">55-59</option>
        <option value="60-64">60-64</option>
        <option value="65-69">65-69</option>
        <option value="70-74">70-74</option>
        <option value="75-79">75-79</option>
        <option value="80+">80 and above</option>
    </select>
</div>

  <div>
    <label class="font-semibold text-gray-700 mr-2">Senior:</label>
    <select id="seniorFilter" class="border border-gray-300 rounded px-2 py-1">
      <option value="all">All</option>
      <option value="1">Yes</option>
      <option value="0">No</option>
    </select>
  </div>

  <div>
    <label class="font-semibold text-gray-700 mr-2">PWD:</label>
    <select id="pwdFilter" class="border border-gray-300 rounded px-2 py-1">
      <option value="all">All</option>
      <option value="1">Yes</option>
      <option value="0">No</option>
    </select>
  </div>

  <div>
    <label class="font-semibold text-gray-700 mr-2">Solo Parent:</label>
    <select id="soloFilter" class="border border-gray-300 rounded px-2 py-1">
      <option value="all">All</option>
      <option value="1">Yes</option>
      <option value="0">No</option>
    </select>
  </div>

  <div>
    <label class="font-semibold text-gray-700 mr-2">4Ps:</label>
    <select id="fourpsFilter" class="border border-gray-300 rounded px-2 py-1">
      <option value="all">All</option>
      <option value="1">Yes</option>
      <option value="0">No</option>
    </select>
  </div>
  <div>
    <label class="font-semibold text-gray-700 mr-2">Citizenship:</label>
    <select id="citizenshipFilter" class="border border-gray-300 rounded px-2 py-1">
      <option value="all">All</option>
      <option value="Filipino">Filipino</option>
      <option value="Non-Filipino">Non-Filipino</option>
    </select>
</div>

<div>
    <label class="font-semibold text-gray-700 mr-2">Employment Status:</label>
    <select id="employmentFilter" class="border border-gray-300 rounded px-2 py-1">
        <option value="all">All</option>
        <option value="Employed">Employed</option>
        <option value="Unemployed">Unemployed</option>
        <option value="Student">Student</option>
        <option value="Self-Employed">Self-Employed</option>
        <option value="Retired">Retired</option>
        <option value="OFW">Overseas Filipino Worker (OFW)</option>
        <option value="IP">Indigenous Peoples (IP)</option>
    </select>
</div>


  <div class="ml-auto flex items-center gap-2">
    <label class="font-semibold text-gray-700">Show:</label>
    <select id="perPageSelect" class="border border-gray-300 rounded px-2 py-1">
      <option value="50" selected>50</option>
      <option value="100">100</option>
      <option value="200">200</option>
    </select>
    <button id="printAgeBtn" class="bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2 rounded shadow flex items-center gap-2">
        <span class="material-icons">print</span> Generate Age & Sector Population
    </button>
    <button id="printBtn" class="bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2 rounded shadow flex items-center gap-2">
        <span class="material-icons">print</span> Generate Residents Report
    </button>

  </div>

</div>


<div id="filteredTotals" class="bg-emerald-50 border-l-4 border-emerald-400 p-4 rounded-lg mb-4 text-emerald-700 font-semibold shadow-sm">
  Total: 0
</div>

<div class="bg-white p-6 rounded-2xl shadow-lg overflow-x-auto">

  <table id="residentsTable" class="min-w-full divide-y divide-gray-200">
    <thead class="bg-gray-100 sticky top-0 z-10">
      <tr>
        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Sex</th>
        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Age</th>
        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Category</th>
        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Address</th>
        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Citizenship</th>
        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Employment Status</th>
      </tr>
    </thead>
    <tbody id="residentsBody" class="divide-y divide-gray-100 hover:divide-gray-200">
    </tbody>
  </table>

</div>
<iframe id="printFrame" style="display:none;"></iframe>

<div id="paginationWrapper"></div>

</main>
</div>
</div>

<script>
document.getElementById('exportExcelBtn').addEventListener('click', () => {
    window.location.href = 'export_all_residents.php';
});


const residentsSexCtx = document.getElementById('residentsSexChart').getContext('2d');
const ageCtx = document.getElementById('ageChart').getContext('2d');
const categoriesCtx = document.getElementById('categoriesChart').getContext('2d');

const residentsSexChart = new Chart(residentsSexCtx, {
    type: 'pie',
    data: {
        labels: ['Male', 'Female'],
        datasets: [{
            data: [<?= $residents['male'] ?>, <?= $residents['female'] ?>],
            backgroundColor: ['#22d3ee', '#8b5cf6']
        }]
    },
    options: {
        onClick: (evt, item) => {
            if(item.length) {
                const index = item[0].index;
                $('#sexFilter').val(['Male','Female'][index]).trigger('change');
            }
        }
    }
});

const categoriesChart = new Chart(categoriesCtx, {
    type: 'bar',
    data: {
        labels: [
            'Seniors', 'PWDs', 'Solo Parents', '4Ps Beneficiaries', 
            'Registered Voters', 'Filipino', 'Non-Filipino', 
            'Employed', 'Unemployed', 'Student', 'Self-Employed', 'Retired',
            'OFW', 'IP'
        ],
        datasets: [{
            data: [
                <?= $residents['seniors'] ?>,
                <?= $residents['pwds'] ?>,
                <?= $residents['solo_parents'] ?>,
                <?= $residents['four_ps'] ?>,
                <?= $residents['voters'] ?>,
                <?= $residents['filipino'] ?>,
                <?= $residents['non_filipino'] ?>,
                <?= $residents['employed'] ?>,
                <?= $residents['unemployed'] ?>,
                <?= $residents['student'] ?>,
                <?= $residents['self_employed'] ?>,
                <?= $residents['retired'] ?>,
                <?= $residents['ofw'] ?>,
                <?= $residents['ip'] ?>
            ],
            backgroundColor: [
                '#22d3ee','#8b5cf6','#84cc16','#facc15','#fb923c','#f472b6',
                '#38bdf8','#10b981','#f97316','#a78bfa','#14b8a6','#e879f9',
                '#0ea5e9','#4ade80'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } },
        onClick: (evt, item) => {
            if(item.length) {
                const index = item[0].index;
                const categoryMap = {
                    0: { filterId: 'seniorFilter', value: '1' },
                    1: { filterId: 'pwdFilter', value: '1' },
                    2: { filterId: 'soloFilter', value: '1' },
                    3: { filterId: 'fourpsFilter', value: '1' },
                    4: { filterId: 'voterFilter', value: 'Registered' },
                    5: { filterId: 'citizenshipFilter', value: 'Filipino' },
                    6: { filterId: 'citizenshipFilter', value: 'Non-Filipino' },
                    7: { filterId: 'employmentFilter', value: 'Employed' },
                    8: { filterId: 'employmentFilter', value: 'Unemployed' },
                    9: { filterId: 'employmentFilter', value: 'Student' },
                    10: { filterId: 'employmentFilter', value: 'Self-Employed' },
                    11: { filterId: 'employmentFilter', value: 'Retired' },
                    12: { filterId: 'employmentFilter', value: 'OFW' },
                    13: { filterId: 'employmentFilter', value: 'IP' }
                };
                const filter = categoryMap[index];
                if(filter) {
                    $(`#${filter.filterId}`).val(filter.value).trigger('change');
                }
            }
        }
    }
});

const ageChart = new Chart(ageCtx, {
    type: 'bar',
    data: {
        labels: [
            'Under 5', '5-9', '10-14', '15-19', '20-24', '25-29', 
            '30-34', '35-39', '40-44', '45-49', '50-54', '55-59', 
            '60-64', '65-69', '70-74', '75-79', '80+'
        ],
        datasets: [{
            data: [
                <?= $ageBrackets['under5'] ?>,
                <?= $ageBrackets['age5_9'] ?>,
                <?= $ageBrackets['age10_14'] ?>,
                <?= $ageBrackets['age15_19'] ?>,
                <?= $ageBrackets['age20_24'] ?>,
                <?= $ageBrackets['age25_29'] ?>,
                <?= $ageBrackets['age30_34'] ?>,
                <?= $ageBrackets['age35_39'] ?>,
                <?= $ageBrackets['age40_44'] ?>,
                <?= $ageBrackets['age45_49'] ?>,
                <?= $ageBrackets['age50_54'] ?>,
                <?= $ageBrackets['age55_59'] ?>,
                <?= $ageBrackets['age60_64'] ?>,
                <?= $ageBrackets['age65_69'] ?>,
                <?= $ageBrackets['age70_74'] ?>,
                <?= $ageBrackets['age75_79'] ?>,
                <?= $ageBrackets['age80_over'] ?>
            ],
            backgroundColor: '#22c55e'
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true },
            x: { ticks: { autoSkip: false } }
        },
        onClick: (evt, item) => {
            if(item.length) {
                const index = item[0].index;
                const ageMap = ['0-4','5-9','10-14','15-19','20-24','25-29','30-34','35-39','40-44','45-49','50-54','55-59','60-64','65-69','70-74','75-79','80+'];
                $('#ageCategoryFilter').val(ageMap[index]).trigger('change');
            }
        }
    }
});

function printReport(url) {
    const iframe = document.getElementById('printFrame');
    iframe.src = url;
    iframe.onload = function() {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
    };
}

document.getElementById('printBtn').addEventListener('click', () => {
    const params = new URLSearchParams({
        sex: document.getElementById('sexFilter').value,
        voter: document.getElementById('voterFilter').value,
        senior: document.getElementById('seniorFilter').value,
        pwd: document.getElementById('pwdFilter').value,
        solo: document.getElementById('soloFilter').value,
        fourps: document.getElementById('fourpsFilter').value,
        ageCategory: document.getElementById('ageCategoryFilter').value,
        citizenship: document.getElementById('citizenshipFilter').value,
        employment: document.getElementById('employmentFilter').value
    });
    printReport('print_residentS.php?' + params.toString());
});

document.getElementById('printAgeBtn').addEventListener('click', () => {
    const params = new URLSearchParams({
        sex: document.getElementById('sexFilter').value,
        voter: document.getElementById('voterFilter').value,
        senior: document.getElementById('seniorFilter').value,
        pwd: document.getElementById('pwdFilter').value,
        solo: document.getElementById('soloFilter').value,
        fourps: document.getElementById('fourpsFilter').value,
        ageCategory: document.getElementById('ageCategoryFilter').value,
        citizenship: document.getElementById('citizenshipFilter').value,
        employment: document.getElementById('employmentFilter').value
    });
    printReport('print_age_population.php?' + params.toString());
});
</script>


<script src="R.js"></script>
</body>
</html>
