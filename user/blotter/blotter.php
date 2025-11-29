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


$blotterQuery = $conn->query("
    SELECT b.*, 
           r1.first_name AS complainant_first, r1.last_name AS complainant_last,
           r2.first_name AS victim_first, r2.last_name AS victim_last,
           r3.first_name AS suspect_first, r3.last_name AS suspect_last
    FROM blotter_records b
    LEFT JOIN residents r1 ON b.complainant_id = r1.resident_id
    LEFT JOIN residents r2 ON b.victim_id = r2.resident_id
    LEFT JOIN residents r3 ON b.suspect_id = r3.resident_id
    WHERE b.archived = 0
    ORDER BY b.created_at DESC
");

$blotterRecords = [];
while($row = $blotterQuery->fetch_assoc()) {
    $blotterRecords[] = $row;
}

function logActivity($conn, $user_id, $action, $description = '') {
    $stmt = $conn->prepare("INSERT INTO log_activity (user_id, action, description) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $action, $description);
    $stmt->execute();
    $stmt->close();
}

$blotterStatsQuery = $conn->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status='open' THEN 1 ELSE 0 END) AS open_cases,
        SUM(CASE WHEN status='investigating' THEN 1 ELSE 0 END) AS investigating,
        SUM(CASE WHEN status='closed' THEN 1 ELSE 0 END) AS closed,
        SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelled
    FROM blotter_records
    WHERE archived = 0
");
$blotterStats = $blotterStatsQuery->fetch_assoc();
logActivity($conn, $user_id, 'View blotter', 'Viewed the blotter records page');
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
<title>Blotter Records</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link rel="stylesheet" href="b.css">
</head>
<body class="font-sans">
<div class="flex h-screen">
<aside id="sidebar" class="w-64 bg-gradient-to-b from-blue-500 to-blue-700 text-white flex flex-col shadow-xl transition-all duration-300 h-screen">
<div class="flex items-center justify-between p-4 border-b border-white/20">
        <div class="flex items-center space-x-3"><img src="<?= htmlspecialchars($systemLogoPath) ?>" alt="Barangay Logo" class="w-16 h-16 rounded-full object-cover shadow-sm border-2 border-white transition-all">
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
      <a href="../blotter/blotter.php" class="flex items-center px-4 py-3 rounded-md bg-white/10 backdrop-blur-sm transition-all">
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
        <a href="../user_manage/log_activity.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
          <span class="material-icons mr-3">history</span><span class="sidebar-text">Log Activity</span>
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
    <h2 class="text-2xl font-bold text-gray-800">Blotter Records</h2>
  </header>
<main class="flex-1 overflow-y-auto p-6 bg-gray-50 space-y-8">
  <div class="mb-6">
    <input type="text" id="searchBlotter" placeholder="Search by resident or case..." class="w-full md:w-1/3 px-4 py-2 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-yellow-400 transition">
  </div>
  <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-6 mb-6">
    <div class="bg-gray-100 text-gray-800 p-5 rounded-xl shadow-lg hover:scale-105 transition-transform cursor-pointer" id="filterAll">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="font-semibold text-lg">Total Cases</h2>
          <p class="text-3xl font-bold mt-2"><?= $blotterStats['total'] ?></p>
        </div>
        <span class="material-icons text-4xl opacity-70">folder</span>
      </div>
    </div>
    <div class="bg-emerald-600 text-white p-5 rounded-xl shadow-lg hover:scale-105 transition-transform cursor-pointer" id="filterOpen">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="font-semibold text-lg">Open</h2>
          <p class="text-3xl font-bold mt-2"><?= $blotterStats['open_cases'] ?></p>
        </div>
        <span class="material-icons text-4xl opacity-70">warning</span>
      </div>
    </div>
    <div class="bg-blue-600 text-white p-5 rounded-xl shadow-lg hover:scale-105 transition-transform cursor-pointer" id="filterInvestigating">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="font-semibold text-lg">Investigating</h2>
          <p class="text-3xl font-bold mt-2"><?= $blotterStats['investigating'] ?></p>
        </div>
        <span class="material-icons text-4xl opacity-70">search</span>
      </div>
    </div>
    <div class="bg-green-700 text-white p-5 rounded-xl shadow-lg hover:scale-105 transition-transform cursor-pointer" id="filterClosed">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="font-semibold text-lg">Closed</h2>
          <p class="text-3xl font-bold mt-2"><?= $blotterStats['closed'] ?></p>
        </div>
        <span class="material-icons text-4xl opacity-70">check_circle</span>
      </div>
    </div>
    <div class="bg-red-600 text-white p-5 rounded-xl shadow-lg hover:scale-105 transition-transform cursor-pointer" id="filterCancelled">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="font-semibold text-lg">Cancelled</h2>
          <p class="text-3xl font-bold mt-2"><?= $blotterStats['cancelled'] ?></p>
        </div>
        <span class="material-icons text-4xl opacity-70">cancel</span>
      </div>
    </div>
  </div>
  <div id="blotterCards" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php if(count($blotterRecords) > 0): ?>
      <?php foreach($blotterRecords as $record): ?>
        <?php
          $complainant = $record['complainant_name'] ?: trim($record['complainant_first'] . ' ' . $record['complainant_last']);
          $victim = $record['victim_name'] ?: trim($record['victim_first'] . ' ' . $record['victim_last']);
          $suspect = $record['suspect_name'] ?: trim($record['suspect_first'] . ' ' . $record['suspect_last']);
          $statusColor = match($record['status']) {
            'open' => 'yellow-500',
            'investigating' => 'blue-500',
            'closed' => 'green-600',
            'cancelled' => 'red-600',
            default => 'gray-400'
          };
        ?>
        <div class="bg-white shadow-lg rounded-xl p-5 border-l-4 border-<?= $statusColor ?> flex flex-col justify-between hover:shadow-xl transition">
          <div class="blotter-text">
            <h3 class="text-lg font-bold text-gray-800 mb-2"><?= $record['incident_nature'] ?></h3>
            <p class="text-sm text-gray-600 mb-1"><span class="font-semibold">Date:</span> <?= date('M d, Y H:i', strtotime($record['incident_datetime'])) ?></p>
            <p class="text-sm text-gray-600 mb-1"><span class="font-semibold">Complainant:</span> <?= $complainant ?></p>
            <p class="text-sm text-gray-600 mb-1"><span class="font-semibold">Victim:</span> <?= $victim ?></p>
            <p class="text-sm text-gray-600 mb-1"><span class="font-semibold">Suspect:</span> <?= $suspect ?></p>
          </div>
          <div class="mt-4 flex items-center justify-between">
            <span class="px-2 py-1 rounded text-white bg-<?= $statusColor ?> font-semibold"><?= ucfirst($record['status']) ?></span>
            <a href="blotter_view.php?id=<?= $record['blotter_id'] ?>" class="text-blue-500 hover:underline text-sm font-medium">View</a>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="col-span-full text-gray-500 text-center">No blotter records found.</p>
    <?php endif; ?>
  </div>
</main>

  </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
document.getElementById('searchBlotter').addEventListener('input', function(){
    const filter = this.value.toLowerCase();
    const cards = document.querySelectorAll('#blotterCards > div');

    cards.forEach(card => {
        const text = card.querySelector('.blotter-text').innerText.toLowerCase();
        card.style.display = text.includes(filter) ? 'block' : 'none';
    });
});

const sidebar = document.getElementById('sidebar');
const toggleBtn = document.getElementById('toggleSidebar');

toggleBtn.onclick = () => {
    sidebar.classList.toggle('sidebar-collapsed');

    let icon = toggleBtn.textContent.trim();
    toggleBtn.textContent = icon === 'chevron_left' ? 'chevron_right' : 'chevron_left';
};

document.addEventListener('DOMContentLoaded', () => {
    const blotterCards = document.querySelectorAll('#blotterCards > div');

    function filterByStatus(status) {
        blotterCards.forEach(card => {
            const cardStatus = card.dataset.status; // get the status
            if (status === 'all' || cardStatus === status) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }

    document.getElementById('filterAll').addEventListener('click', () => filterByStatus('all'));
    document.getElementById('filterOpen').addEventListener('click', () => filterByStatus('open'));
    document.getElementById('filterInvestigating').addEventListener('click', () => filterByStatus('investigating'));
    document.getElementById('filterClosed').addEventListener('click', () => filterByStatus('closed'));
    document.getElementById('filterCancelled').addEventListener('click', () => filterByStatus('cancelled'));
});

</script>

</body>
</html>
