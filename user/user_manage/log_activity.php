<?php 
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}
include '../db.php';

$user_id = $_SESSION["user_id"];
$userQuery = $conn->query("SELECT * FROM users WHERE id = '$user_id'");
$user = $userQuery->fetch_assoc();
$role = $user['role'];

if ($role === 'admin' && isset($_POST['clear_logs'])) {
    $conn->query("TRUNCATE TABLE log_activity");
    header("Location: log_activity.php?cleared=1");
    exit();
}

$perPage = isset($_GET['perPage']) ? (int)$_GET['perPage'] : 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $perPage;

$totalQuery = $conn->query("SELECT COUNT(*) as total FROM log_activity");
$totalRows = $totalQuery->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $perPage);

$logQuery = $conn->query("
    SELECT l.action, l.description, l.created_at, u.username 
    FROM log_activity l 
    JOIN users u ON l.user_id = u.id 
    ORDER BY l.created_at DESC
    LIMIT $start, $perPage
");

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
<title>Log Activity</title>
<link rel="stylesheet" href="use.css">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
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

    <a href="../households/household.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
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

    <a href="../reports/report.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
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
        <a href="../user_manage/log_activity.php" class="flex items-center px-4 py-3 rounded-md bg-white/10 backdrop-blur-sm transition-all">
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
<header class="flex items-center justify-between bg-white shadow-md px-6 py-4 rounded-b-2xl mb-6">
<h2 class="text-2xl font-bold text-gray-800">Activity Logs</h2>
<div class="flex items-center space-x-3">

<button id="clearLogsBtn" class="bg-red-600 hover:bg-red-700 text-white px-5 py-2 rounded-lg shadow font-medium text-sm">Clear Logs</button>
</div>
</header>

<main class="flex-1 overflow-y-auto p-6">
  <?php if(isset($_GET['cleared'])): ?>
  <div class="mb-4 p-3 bg-emerald-100 text-emerald-800 rounded-lg shadow">
    All logs have been cleared successfully.
  </div>
  <?php endif; ?>

  <div class="bg-white p-6 rounded-2xl shadow-lg overflow-x-auto">
    <!-- Controls: Show entries + Search -->
    <div class="flex flex-col md:flex-row items-center mb-4 gap-2">
      <div class="flex items-center gap-2">
        <span class="text-gray-600 font-medium">Show</span>
        <select id="perPageSelect" class="border border-gray-300 px-3 py-2 rounded-lg focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400 transition" onchange="changePerPage(this.value)">
          <option value="50" <?= $perPage==50?'selected':'' ?>>50</option>
          <option value="100" <?= $perPage==100?'selected':'' ?>>100</option>
          <option value="200" <?= $perPage==200?'selected':'' ?>>200</option>
        </select>
        <span class="text-gray-600 font-medium">entries</span>
      </div>

      <!-- Search bar aligned to the right on md+ screens, full width on small screens -->
      <input type="text" id="searchInput" placeholder="Search..." class="mt-2 md:mt-0 md:ml-auto px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400 text-sm transition w-full md:w-auto">
    </div>

    <!-- Table -->
    <table id="logTable" class="min-w-full divide-y divide-gray-200">
      <thead class="bg-gray-100">
        <tr>
          <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">ID</th>
          <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">User</th>
          <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Action</th>
          <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Description</th>
          <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500 uppercase tracking-wider">Date/Time</th>
        </tr>
      </thead>
      <tbody class="bg-white divide-y divide-gray-200">
        <?php 
        $count = $start + 1;
        $rowsExist = false;
        while($row = $logQuery->fetch_assoc()): 
          $rowsExist = true;
        ?>
        <tr class="hover:bg-gray-50 transition cursor-pointer">
          <td class="px-6 py-4 text-sm"><?= $count ?></td>
          <td class="px-6 py-4 text-sm"><?= htmlspecialchars($row['username']) ?></td>
          <td class="px-6 py-4 text-sm"><?= htmlspecialchars($row['action']) ?></td>
          <td class="px-6 py-4 text-sm"><?= htmlspecialchars($row['description']) ?></td>
          <td class="px-6 py-4 text-sm"><?= date("M j, Y h:i A", strtotime($row['created_at'])) ?></td>
        </tr>
        <?php 
        $count++;
        endwhile; 
        if(!$rowsExist): ?>
        <tr>
          <td colspan="5" class="text-center py-6 text-gray-400">No records found</td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>

</div>
</div>

<div id="clearLogsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
<div class="bg-white rounded-2xl shadow-lg p-6 w-96">
<h3 class="text-lg font-semibold text-gray-800 mb-4">Confirm Action</h3>
<p class="text-gray-600 mb-6">Are you sure you want to clear all logs?</p>
<div class="flex justify-end gap-3">
<button id="cancelClear" class="px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-100 transition">Cancel</button>
<form method="post" class="inline">
<button type="submit" name="clear_logs" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition">Yes, Clear</button>
</form>
</div>
</div>
</div>

<script>
function changePerPage(perPage) {
const urlParams = new URLSearchParams(window.location.search);
urlParams.set('perPage', perPage);
urlParams.set('page', 1);
window.location.search = urlParams.toString();
}

const toggleSidebar = document.getElementById('toggleSidebar');
const sidebar = document.getElementById('sidebar');
toggleSidebar?.addEventListener('click', () => {
sidebar.classList.toggle('sidebar-collapsed');
toggleSidebar.textContent = toggleSidebar.textContent === 'chevron_left' ? 'chevron_right' : 'chevron_left';
});

const searchInput = document.getElementById('searchInput');
const table = document.getElementById('logTable');
const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
searchInput.addEventListener('input', function() {
const filter = searchInput.value.toLowerCase();
let visibleCount = 0;
Array.from(rows).forEach(row => {
const cells = row.getElementsByTagName('td');
let match = false;
Array.from(cells).forEach(cell => {
if(cell.textContent.toLowerCase().includes(filter)) match = true;
});
row.style.display = match ? '' : 'none';
if(match) visibleCount++;
});
if(visibleCount === 0) {
if(!document.getElementById('noDataRow')) {
const tbody = table.getElementsByTagName('tbody')[0];
const noRow = document.createElement('tr');
noRow.id = 'noDataRow';
noRow.innerHTML = '<td colspan="5" class="text-center py-4">No records found</td>';
tbody.appendChild(noRow);
}
} else {
const noRow = document.getElementById('noDataRow');
if(noRow) noRow.remove();
}
});
document.addEventListener('DOMContentLoaded', () => {
  const clearedMsg = document.querySelector('.mb-4.p-3.bg-emerald-100');
  if(clearedMsg){
    setTimeout(() => {
      clearedMsg.classList.add('transition', 'opacity-0');
      setTimeout(() => clearedMsg.remove(), 500);
    }, 3000);
  }
});
const clearLogsBtn = document.getElementById('clearLogsBtn');
const clearLogsModal = document.getElementById('clearLogsModal');
const cancelClear = document.getElementById('cancelClear');
clearLogsBtn.addEventListener('click', () => clearLogsModal.classList.remove('hidden'));
cancelClear.addEventListener('click', () => clearLogsModal.classList.add('hidden'));
</script>

</body>
</html>
