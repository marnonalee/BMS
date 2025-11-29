<?php
include 'residents_functions.php';

$filter = $_GET['filter'] ?? '';
$search = $_GET['search'] ?? '';

// Base query
$where = "is_archived = 0";

if ($filter !== '') {
    switch($filter) {
        case '0-17':
            $where .= " AND age BETWEEN 0 AND 17";
            break;
        case '18-35':
            $where .= " AND age BETWEEN 18 AND 35";
            break;
        case '36-50':
            $where .= " AND age BETWEEN 36 AND 50";
            break;
        case '51+':
            $where .= " AND age >= 51";
            break;
    }
}

if($search !== '') {
    $searchEscaped = $conn->real_escape_string($search);
    $where .= " AND (first_name LIKE '%$searchEscaped%' OR last_name LIKE '%$searchEscaped%' OR middle_name LIKE '%$searchEscaped%')";
}

$residentsQuery = $conn->query("SELECT * FROM residents WHERE $where ORDER BY last_name ASC");
$archivedQuery = $conn->query("SELECT * FROM residents WHERE is_archived=1 ORDER BY last_name ASC");

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
<title>Residents - Barangay Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link rel="stylesheet" href="resi.css">

</head>
<body class="bg-gray-100 font-sans" data-success="<?= htmlspecialchars($successMsg) ?>">
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

    <a href="../residents/resident.php" class="flex items-center px-4 py-3 rounded-md bg-white/10 backdrop-blur-sm transition-all">
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

<header class="flex items-center justify-between bg-white shadow-md px-6 py-4 flex-shrink-0 rounded-b-2xl mb-6">
  <h2 class="text-2xl font-bold text-gray-800">Residents</h2>
  
  <?php if($role === 'admin'): ?>
    <div class="flex items-center space-x-3">
      <input type="text" id="searchInput" placeholder="Search residents..."
             class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400 text-sm transition">

      <button id="addResidentBtn"
              class="bg-emerald-500 hover:bg-emerald-600 text-white px-5 py-2 rounded-lg shadow font-medium transition">
        Add Resident
      </button>

      <button id="toggleArchivedBtn"
              class="bg-red-500 hover:bg-red-600 text-white px-5 py-2 rounded-lg shadow font-medium transition">
        Show Archived Residents
      </button>
    </div>
  <?php endif; ?>
</header>


  <main class="flex-1 overflow-y-auto p-6">

    <div class="flex flex-wrap gap-4 mb-6">
    <div id="card_total" class="flex items-center bg-emerald-100 text-emerald-700 px-4 py-2 rounded-lg cursor-pointer hover:bg-emerald-200 transition">
        <span class="material-icons mr-2">people_alt</span>
        <span>Total Residents: <strong><?= $totalResidents ?? 0 ?></strong></span>
    </div>

    <div id="card_voters" class="flex items-center bg-blue-100 text-blue-700 px-4 py-2 rounded-lg cursor-pointer hover:bg-blue-200 transition">
        <span class="material-icons mr-2">how_to_vote</span>
        <span>Registered Voters: <strong><?= $totalVoters ?? 0 ?></strong></span>
    </div>

    <div id="card_unvoters" class="flex items-center bg-red-100 text-red-700 px-4 py-2 rounded-lg cursor-pointer hover:bg-red-200 transition">
        <span class="material-icons mr-2">how_to_vote</span>
        <span>Unregistered: <strong><?= $totalUnregisteredVoters ?? 0 ?></strong></span>
    </div>

    <div id="card_senior" class="flex items-center bg-yellow-100 text-yellow-700 px-4 py-2 rounded-lg cursor-pointer hover:bg-yellow-200 transition">
        <span class="material-icons mr-2">elderly</span>
        <span>Senior Citizens: <strong><?= $totalSenior ?? 0 ?></strong></span>
    </div>

    <div id="card_pwd" class="flex items-center bg-purple-100 text-purple-700 px-4 py-2 rounded-lg cursor-pointer hover:bg-purple-200 transition">
        <span class="material-icons mr-2">accessible</span>
        <span>PWD: <strong><?= $totalPWD ?? 0 ?></strong></span>
    </div>

    <div id="card_4ps" class="flex items-center bg-indigo-100 text-indigo-700 px-4 py-2 rounded-lg cursor-pointer hover:bg-indigo-200 transition">
        <span class="material-icons mr-2">family_restroom</span>
        <span>4Ps: <strong><?= $total4Ps ?? 0 ?></strong></span>
    </div>

    <div id="card_solo" class="flex items-center bg-pink-100 text-pink-700 px-4 py-2 rounded-lg cursor-pointer hover:bg-pink-200 transition">
        <span class="material-icons mr-2">child_friendly</span>
        <span>Solo Parents: <strong><?= $totalSoloParent ?? 0 ?></strong></span>
    </div>
</div>

      <!-- Controls -->
      <div class="flex flex-wrap justify-between items-center mb-4 gap-4">
        <div class="flex items-center space-x-2">
          <span class="text-gray-600 font-medium">Show</span>
          <select id="perPageSelect" class="border border-gray-300 px-3 py-2 rounded-lg focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400 transition">
            <option value="100" <?= $perPage==100?'selected':'' ?>>100</option>
            <option value="500" <?= $perPage==500?'selected':'' ?>>500</option>
            <option value="1000" <?= $perPage==1000?'selected':'' ?>>1000</option>
          </select>
          <span class="text-gray-600 font-medium">entries</span>
        </div>

        <div class="flex items-center space-x-2">
          <span class="text-gray-600 font-medium">Sort:</span>
          <select id="sortSelect" class="border border-gray-300 px-3 py-2 rounded-lg focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400 transition">
            <option value="asc" <?= ($sort=='ASC'?'selected':'') ?>>A - Z</option>
            <option value="desc" <?= ($sort=='DESC'?'selected':'') ?>>Z - A</option>
          </select>
        </div>
      </div>
      

      <!-- Residents Table -->
      <div class="bg-white p-6 rounded-2xl shadow-lg overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200" id="residentsTable">
          <thead class="bg-gray-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Sex</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Age</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Voter Status</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Resident Address</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Street</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <?php if($residentsQuery->num_rows > 0): ?>
              <?php while($resident = $residentsQuery->fetch_assoc()): ?>
                <tr class="hover:bg-gray-50 cursor-pointer transition" data-resident='<?= json_encode($resident) ?>'>
                  <td class="px-6 py-4"><?= htmlspecialchars($resident['first_name'].' '.$resident['middle_name'].' '.$resident['last_name']) ?></td>
                  <td class="px-6 py-4"><?= htmlspecialchars($resident['sex']) ?></td>
                  <td class="px-6 py-4"><?= htmlspecialchars($resident['age']) ?></td>
                  <td class="px-6 py-4"><?= htmlspecialchars($resident['voter_status']) ?></td>
                  <td class="px-6 py-4"><?= htmlspecialchars($resident['resident_address']) ?></td>
                  <td class="px-6 py-4"><?= htmlspecialchars($resident['street']) ?></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="6" class="px-6 py-4 text-center text-gray-400">No residents found.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>

        <!-- Pagination -->
        <?php if($totalPages > 1 && $search === ''): ?>
          <div class="flex justify-center mt-6 space-x-2">
            <?php if($page > 1): ?>
              <a href="?page=<?= $page-1 ?>&perPage=<?= $perPage ?>&search=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 transition">Prev</a>
            <?php endif; ?>
            <?php for($i = 1; $i <= $totalPages; $i++): ?>
              <a href="?page=<?= $i ?>&perPage=<?= $perPage ?>&search=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>" class="px-4 py-2 rounded-lg <?= ($i == $page) ? 'bg-emerald-500 text-white' : 'bg-gray-200 hover:bg-gray-300' ?> transition"><?= $i ?></a>
            <?php endfor; ?>
            <?php if($page < $totalPages): ?>
              <a href="?page=<?= $page+1 ?>&perPage=<?= $perPage ?>&search=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 transition">Next</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Archived Residents Table -->
     <div id="archivedContainer" class="hidden bg-white p-6 rounded-2xl shadow-lg mt-4 overflow-x-auto">
        <h3 class="text-lg font-semibold mb-4 text-red-600">Archived Residents</h3>

        <div class="flex items-center mb-4 space-x-2">
          <span class="text-gray-600 font-medium">Show</span>
          <select id="archivedPerPageSelect" class="border border-gray-300 px-3 py-2 rounded-lg focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400 transition">
            <option value="100" <?= $perPage==100?'selected':'' ?>>100</option>
            <option value="500" <?= $perPage==500?'selected':'' ?>>500</option>
            <option value="1000" <?= $perPage==1000?'selected':'' ?>>1000</option>
          </select>
          <span class="text-gray-600 font-medium">entries</span>
        </div>

<table id="archivedResidentsTable" class="min-w-full divide-y divide-gray-200">
  <thead class="bg-gray-100">
    <tr>
      <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
        <input type="checkbox" id="selectAllArchived">
      </th>
      <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
      <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Sex</th>
      <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Age</th>
      <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Voter Status</th>
      <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Resident Address</th>
      <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Street</th>
    </tr>
  </thead>

  <tbody class="bg-white divide-y divide-gray-200">
    <?php if($archivedQuery->num_rows > 0): ?>
      <?php while($arch = $archivedQuery->fetch_assoc()): ?>
        <tr class="hover:bg-gray-50 cursor-pointer transition" data-resident='<?= json_encode($arch) ?>'>
          <td class="px-6 py-4">
            <input type="checkbox" class="selectArchived" value="<?= $arch['resident_id'] ?>">
          </td>
          <td class="px-6 py-4"><?= htmlspecialchars($arch['first_name'].' '.$arch['middle_name'].' '.$arch['last_name']) ?></td>
          <td class="px-6 py-4"><?= htmlspecialchars($arch['sex']) ?></td>
          <td class="px-6 py-4"><?= htmlspecialchars($arch['age']) ?></td>
          <td class="px-6 py-4"><?= htmlspecialchars($arch['voter_status']) ?></td>
          <td class="px-6 py-4"><?= htmlspecialchars($arch['resident_address']) ?></td>
          <td class="px-6 py-4"><?= htmlspecialchars($arch['street']) ?></td>
        </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr>
        <td colspan="7" class="px-6 py-4 text-center text-gray-400">No archived residents.</td>
      </tr>
    <?php endif; ?>
  </tbody>
</table>

<button id="deleteSelectedArchived" class="mt-3 bg-red-500 text-white px-4 py-2 rounded">Delete Selected</button>

      </div>

    </main>

  </div>
</div>
<style>
  #sidebar nav::-webkit-scrollbar {
      width: 6px;
  }
  #sidebar nav::-webkit-scrollbar-track {
      background: transparent;
  }
  #sidebar nav::-webkit-scrollbar-thumb {
      background-color: #15803d;
      border-radius: 10px;
  }
  #sidebar nav::-webkit-scrollbar-thumb:hover {
      background-color: #166534;
  }
.main-bg {
    min-height: 100vh;
    background-image: url("../../img/1763469985_c793c88b-8142-4b45-acb9-ebd4c2fef79e - Copy.jpg");
    background-size: cover;       
    background-position: center;
    background-repeat: no-repeat;
}
</style>
<?php $readonly = ($role !== 'admin') ? 'readonly disabled' : ''; ?>
<div id="residentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
  <div class="bg-white rounded-xl w-11/12 md:w-2/3 p-6 relative overflow-y-auto max-h-[90vh]">
    <button id="closeModal" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 material-icons">close</button>
    <h2 class="text-xl font-semibold mb-3">Resident Details</h2>
    <form method="POST" id="residentForm">
      <input type="hidden" name="resident_id" id="resident_id">

      <div class="border-b mb-3">
        <nav class="-mb-px flex space-x-4" id="modalTabs">
          <button type="button" class="py-2 px-4 border-b-2 border-emerald-500 font-medium" data-tab="personal">Personal Info</button>
          <button type="button" class="py-2 px-4 border-b-2 border-transparent font-medium hover:border-gray-300" data-tab="other">Other Info</button>
        </nav>
      </div>

      <div id="personalTab">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div><label>First Name</label><input type="text" name="first_name" id="first_name" class="w-full border-b-2 py-1.5" required <?= $readonly ?>></div>
          <div><label>Middle Name</label><input type="text" name="middle_name" id="middle_name" class="w-full border-b-2 py-1.5" <?= $readonly ?>></div>
          <div><label>Last Name</label><input type="text" name="last_name" id="last_name" class="w-full border-b-2 py-1.5" required <?= $readonly ?>></div>
          <div><label>Alias</label><input type="text" name="alias" id="alias" class="w-full border-b-2 py-1.5" <?= $readonly ?>></div>
          <div><label>Suffix</label><input type="text" name="suffix" id="suffix" class="w-full border-b-2 py-1.5" <?= $readonly ?>></div>
          <div><label>Birthdate</label><input type="date" name="birthdate" id="birthdate" class="w-full border-b-2 py-1.5" required <?= $readonly ?>></div>
          <div><label>Age</label><input type="number" name="age" id="age" class="w-full border-b-2 py-1.5" readonly></div>
          <div>
            <label>Gender</label>
            <select name="sex" id="sex" class="w-full border-b-2 py-1.5" required <?= $readonly ?>>
              <option value="">Select</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
            </select>
          </div>
          <div>
            <label>Civil Status</label>
            <select name="civil_status" id="civil_status" class="w-full border-b-2 py-1.5" required <?= $readonly ?>>
              <option value="">Select</option>
              <option>Single</option>
              <option>Married</option>
              <option>Widowed</option>
              <option>Separated</option>
              <option>Divorced</option>
            </select>
          </div>
          <div><label>Resident Address</label><input type="text" name="resident_address" id="resident_address" class="w-full border-b-2 py-1.5" required <?= $readonly ?>></div>
          <div><label>Birth Place</label><input type="text" name="birth_place" id="birth_place" class="w-full border-b-2 py-1.5" <?= $readonly ?>></div>
          <div><label>Street</label><input type="text" name="street" id="street" class="w-full border-b-2 py-1.5" <?= $readonly ?>></div>
          <div>
            <label>Citizenship</label>
            <select name="citizenship" id="citizenship" class="w-full border-b-2 py-1.5" required <?= $readonly ?>>
              <option value="">Select</option>
              <option value="Filipino">Filipino</option>
              <option value="Non-Filipino">Non-Filipino</option>
            </select>
          </div>
           <div>
            <label>Voter Status</label>
            <select name="voter_status" id="voter_status" class="w-full border-b-2 py-1.5" required <?= $readonly ?>>
              <option value="">Select</option>
              <option value="Registered">Registered</option>
              <option value="Unregistered">Unregistered</option>
            </select>
          </div>
        </div>
      </div>

      <div id="otherTab" class="hidden mt-3">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
    
          <div>
            <label>Employment Status</label>
            <select name="employment_status" id="employment_status" class="w-full border-b-2 py-1.5"  <?= $readonly ?>>
              <option value="">Select</option>
              <option>Employed</option>
              <option>Unemployed</option>
              <option value="OFW">Overseas Filipino Worker (OFW)</option>
            </select>
          </div>
          <div><label>Contact No.</label><input type="text" name="contact_number" id="contact_number" class="w-full border-b-2 py-1.5" maxlength="11" pattern="\d{11}" <?= $readonly ?>></div>
          <div><label>Email Address</label><input type="email" name="email_address" id="email_address" class="w-full border-b-2 py-1.5" <?= $readonly ?>></div>
          <div><label>Religion</label><input type="text" name="religion" id="religion" class="w-full border-b-2 py-1.5" <?= $readonly ?>></div>
          <div><label>Occupation</label><input type="text" name="profession_occupation" id="profession_occupation" class="w-full border-b-2 py-1.5" <?= $readonly ?>></div>
          <div>
            <label>Educational Attainment</label>
            <select name="educational_attainment" id="educational_attainment" class="w-full border-b-2 py-1.5" <?= $readonly ?>>
              <option value="">Select Level</option>
              <?php
              $levels = ['Elementary', 'High School', 'College', 'Post Grad', 'Vocational'];
              foreach($levels as $level){ echo "<option value=\"$level\">$level</option>"; }
              ?>
            </select>
          </div>
          <div>
            <label>Status / Details</label>
            <select name="education_details" id="education_details" class="w-full border-b-2 py-1.5" <?= $readonly ?>>
              <option value="">Select</option>
              <?php
              $statuses = ['Undergraduate', 'Graduate'];
              foreach($statuses as $status){ echo "<option value=\"$status\">$status</option>"; }
              ?>
            </select>
          </div>
          <div>
            <label>Out of School Status</label>
            <select name="school_status" id="school_status" class="w-full border-b-2 py-1.5" <?= $readonly ?>>
              <option value="">Select</option>
              <option value="osc">Out of School Children (OSC)</option>
              <option value="osy">Out of School Youth (OSY)</option>
              <option value="enrolled">Currently Enrolled</option>
            </select>
          </div>
          <div class="flex items-center space-x-2 mt-2">
            <label class="font-medium">Head of the Family:</label>
            <input type="checkbox" name="is_family_head" id="is_family_head" <?= ($role !== 'admin' ? 'disabled' : '') ?>>
          </div>
          <div><label>PhilSys Card No.</label><input type="text" name="philsys_card_no" id="philsys_card_no" class="w-full border-b-2 py-1.5" maxlength="12" <?= $readonly ?>></div>
          <div class="flex gap-3 mt-1 md:col-span-2">
            <label><input type="checkbox" name="is_senior" id="is_senior" <?= ($role !== 'admin' ? 'disabled' : '') ?>> Senior</label>
            <label><input type="checkbox" name="is_pwd" id="is_pwd" <?= ($role !== 'admin' ? 'disabled' : '') ?>> PWD</label>
            <label><input type="checkbox" name="is_4ps" id="is_4ps" <?= ($role !== 'admin' ? 'disabled' : '') ?>> 4Ps</label>
            <label><input type="checkbox" name="is_solo_parent" id="is_solo_parent" <?= ($role !== 'admin' ? 'disabled' : '') ?>> Solo Parent</label>
          </div>
        </div>
      </div>

      <?php if($role === 'admin'): ?>
        <div class="mt-3 flex justify-end space-x-2">
          <button type="submit" name="update_resident" class="bg-emerald-500 text-white px-4 py-2 rounded">Save Changes</button>
          <button type="button" id="deleteResident" class="bg-red-500 text-white px-4 py-2 rounded">Delete</button>
        </div>
      <?php endif; ?>

    </form>
  </div>
</div>

<div id="addResidentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
  <div class="bg-white rounded-xl w-11/12 md:w-2/3 p-6 relative overflow-y-auto max-h-[90vh]">

    <button id="closeAddModal" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 material-icons">close</button>
    <h2 class="text-xl font-semibold mb-3">Add New Resident</h2>

    <form method="POST" id="addResidentForm">

      <input type="hidden" name="resident_id">

      <div class="border-b mb-3">
        <nav class="-mb-px flex space-x-4" id="addModalTabs">
          <button type="button" class="py-2 px-4 border-b-2 border-emerald-500 font-medium" data-tab="personal">Personal Info</button>
          <button type="button" class="py-2 px-4 border-b-2 border-transparent font-medium hover:border-gray-300" data-tab="other">Other Info</button>
        </nav>
      </div>

      <div id="addPersonalTab">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">

          <div><label>First Name <span class="text-red-500">*</span></label><input type="text" name="first_name" class="w-full border-b-2 py-1.5" required></div>
          <div><label>Middle Name</label><input type="text" name="middle_name" class="w-full border-b-2 py-1.5"></div>
          <div><label>Last Name <span class="text-red-500">*</span></label><input type="text" name="last_name" class="w-full border-b-2 py-1.5" required></div>
          <div><label>Alias</label><input type="text" name="alias" class="w-full border-b-2 py-1.5"></div>
          <div><label>Suffix</label><input type="text" name="suffix" class="w-full border-b-2 py-1.5"></div>

          <div><label>Birthdate <span class="text-red-500">*</span></label><input type="date" name="birthdate" id="add_birthdate" class="w-full border-b-2 py-1.5" required></div>
          <div><label>Age</label><input type="number" name="age" id="add_age" readonly class="w-full border-b-2 py-1.5"></div>

          <div>
            <label>Gender <span class="text-red-500">*</span></label>
            <select name="sex" class="w-full border-b-2 py-1.5" required>
              <option value="">Select</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
            </select>
          </div>

          <div>
            <label>Civil Status <span class="text-red-500">*</span></label>
            <select name="civil_status" class="w-full border-b-2 py-1.5" required>
              <option value="">Select</option>
              <option>Single</option>
              <option>Married</option>
              <option>Widowed</option>
              <option>Separated</option>
              <option>Divorced</option>
            </select>
          </div>

          <div><label>Resident Address <span class="text-red-500">*</span></label><input type="text" name="resident_address" class="w-full border-b-2 py-1.5" required></div>
          <div><label>Birth Place</label><input type="text" name="birth_place" class="w-full border-b-2 py-1.5"></div>
          <div><label>Street</label><input type="text" name="street" class="w-full border-b-2 py-1.5"></div>

          <div>
            <label>Citizenship <span class="text-red-500">*</span></label>
            <select name="citizenship" class="w-full border-b-2 py-1.5" required>
              <option value="">Select</option>
              <option value="Filipino">Filipino</option>
              <option value="Non-Filipino">Non-Filipino</option>
            </select>
          </div>

            <div>
            <label>Voter Status <span class="text-red-500">*</span></label>
            <select name="voter_status" class="w-full border-b-2 py-1.5" required>
              <option value="">Select</option>
              <option value="Registered">Registered</option>
              <option value="Unregistered">Unregistered</option>
            </select>
          </div>
        </div>
      </div>

      <div id="addOtherTab" class="hidden mt-3">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">

          <div>
            <label>Employment Status</label>
            <select name="employment_status" class="w-full border-b-2 py-1.5" >
              <option value="">Select</option>
              <option>Employed</option>
              <option>Unemployed</option>
              <option value="OFW">Overseas Filipino Worker (OFW)</option>
            </select>
          </div>

          <div><label>Contact No.</label><input type="text" name="contact_number" class="w-full border-b-2 py-1.5" maxlength="11" pattern="\d{11}"></div>
          <div><label>Email Address</label><input type="email" name="email_address" class="w-full border-b-2 py-1.5"></div>
          <div><label>Religion</label><input type="text" name="religion" class="w-full border-b-2 py-1.5"></div>
          <div><label>Occupation</label><input type="text" name="profession_occupation" class="w-full border-b-2 py-1.5"></div>

          <div>
            <label>Educational Attainment</label>
            <select name="educational_attainment" class="w-full border-b-2 py-1.5">
              <option value="">Select Level</option>
              <?php
              $levels = ['Elementary', 'High School', 'College', 'Post Grad', 'Vocational'];
              foreach($levels as $level){ echo "<option value=\"$level\">$level</option>"; }
              ?>
            </select>
          </div>

          <div>
            <label>Status / Details</label>
            <select name="education_details" class="w-full border-b-2 py-1.5">
              <option value="">Select</option>
              <?php
              $statuses = ['Undergraduate', 'Graduate'];
              foreach($statuses as $status){ echo "<option value=\"$status\">$status</option>"; }
              ?>
            </select>
          </div>

          <div>
            <label>Out of School Status</label>
            <select name="school_status" class="w-full border-b-2 py-1.5">
              <option value="">Select</option>
              <option value="osc">Out of School Children (OSC)</option>
              <option value="osy">Out of School Youth (OSY)</option>
              <option value="enrolled">Currently Enrolled</option>
            </select>
          </div>

          <div class="flex items-center space-x-2 mt-2">
            <label class="font-medium">Head of the Family:</label>
            <input type="checkbox" name="is_family_head">
          </div>

          <div><label>PhilSys Card No.</label><input type="text" name="philsys_card_no" class="w-full border-b-2 py-1.5" maxlength="12"></div>

          <div class="flex gap-3 mt-1 md:col-span-2">
            <label><input type="checkbox" name="is_senior"> Senior</label>
            <label><input type="checkbox" name="is_pwd"> PWD</label>
            <label><input type="checkbox" name="is_4ps"> 4Ps</label>
            <label><input type="checkbox" name="is_solo_parent"> Solo Parent</label>
          </div>

        </div>
      </div>

      <div class="mt-3 flex justify-end">
        <button type="submit" name="add_resident" class="bg-emerald-500 text-white px-4 py-2 rounded">Add Resident</button>
      </div>

    </form>
  </div>
</div>


<div id="deleteConfirmModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
  <div class="bg-white rounded-xl p-6 w-80 text-center">
    <h2 class="text-lg font-semibold mb-4">Delete Resident?</h2>
    <p class="mb-6 text-sm">Are you sure you want to delete this resident?</p>
    <div class="flex justify-center space-x-3">
      <button id="cancelDelete" class="bg-gray-400 text-white px-4 py-2 rounded">Cancel</button>
      <form method="POST">
        <input type="hidden" name="delete_resident_id" id="delete_resident_id">
        <button type="submit" name="delete_resident" class="bg-red-500 text-white px-4 py-2 rounded">Delete</button>
      </form>
    </div>
  </div>
</div>
<div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
  <div class="bg-white rounded-xl p-6 w-80 text-center relative">
    <button id="closeSuccessModal" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 material-icons">close</button>
    <span id="successIcon" class="material-icons text-4xl text-green-500">check_circle</span>
    <p id="successMessage" class="mt-2">Success!</p>
    <button id="okSuccessModal" class="mt-3 bg-emerald-500 text-white px-4 py-2 rounded">OK</button>
  </div>
</div>


<div id="archivedResidentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
  <div class="bg-white rounded-xl w-11/12 md:w-2/3 p-6 relative overflow-y-auto max-h-[90vh]">
    <button id="closeArchivedModal" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 material-icons">close</button>
    <h2 class="text-xl font-semibold mb-3 text-red-600">Archived Resident Details</h2>
    <form id="archivedResidentForm">
      <input type="hidden" name="resident_id" id="archived_resident_id">

      <!-- Personal Info -->
      <div class="mb-3">
        <h3 class="font-medium mb-2">Personal Info</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div><label>First Name</label><input type="text" id="arch_first_name" class="w-full border-b-2 py-1.5" readonly></div>
          <div><label>Middle Name</label><input type="text" id="arch_middle_name" class="w-full border-b-2 py-1.5" readonly></div>
          <div><label>Last Name</label><input type="text" id="arch_last_name" class="w-full border-b-2 py-1.5" readonly></div>
          <div><label>Alias</label><input type="text" id="arch_alias" class="w-full border-b-2 py-1.5" readonly></div>
          <div><label>Suffix</label><input type="text" id="arch_suffix" class="w-full border-b-2 py-1.5" readonly></div>
          <div><label>Birthdate</label><input type="date" id="arch_birthdate" class="w-full border-b-2 py-1.5" readonly></div>
          <div><label>Age</label><input type="number" id="arch_age" class="w-full border-b-2 py-1.5" readonly></div>
          <div><label>Gender</label><input type="text" id="arch_sex" class="w-full border-b-2 py-1.5" readonly></div>
          <div><label>Civil Status</label><input type="text" id="arch_civil_status" class="w-full border-b-2 py-1.5" readonly></div>
          <div><label>Resident Address</label><input type="text" id="arch_resident_address" class="w-full border-b-2 py-1.5" readonly></div>
          <div><label>Birth Place</label><input type="text" id="arch_birth_place" class="w-full border-b-2 py-1.5" readonly></div>
          <div><label>Street</label><input type="text" id="arch_street" class="w-full border-b-2 py-1.5" readonly></div>
          <div><label>Citizenship</label><input type="text" id="arch_citizenship" class="w-full border-b-2 py-1.5" readonly></div>
        </div>
      </div>

      <!-- Other Info -->
      <div class="mt-3">
        <h3 class="font-medium mb-2">Other Info</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div><label>Voter Status</label><input type="text" id="arch_voter_status" class="w-full border-b-2 py-1.5" readonly></div>
          <div><label>Employment Status</label><input type="text" id="arch_employment_status" class="w-full border-b-2 py-1.5" readonly></div>
          <div><label>Contact No.</label><input type="text" id="arch_contact_number" class="w-full border-b-2 py-1.5" readonly></div>
          <div><label>Email Address</label><input type="email" id="arch_email_address" class="w-full border-b-2 py-1.5" readonly></div>
          <div><label>Religion</label><input type="text" id="arch_religion" class="w-full border-b-2 py-1.5" readonly></div>
          <div><label>Occupation</label><input type="text" id="arch_profession_occupation" class="w-full border-b-2 py-1.5" readonly></div>
          <div><label>Educational Attainment</label><input type="text" id="arch_educational_attainment" class="w-full border-b-2 py-1.5" readonly></div>
          <div><label>Status / Details</label><input type="text" id="arch_education_details" class="w-full border-b-2 py-1.5" readonly></div>
          <div><label>Out of School Status</label><input type="text" id="arch_school_status" class="w-full border-b-2 py-1.5" readonly></div>
          <div class="flex items-center space-x-2 mt-2">
            <label>Head of the Family:</label>
            <input type="checkbox" id="arch_is_family_head" disabled>
          </div>
          <div><label>PhilSys Card No.</label><input type="text" id="arch_philsys_card_no" class="w-full border-b-2 py-1.5" readonly></div>
          <div class="flex gap-3 mt-1 md:col-span-2">
            <label><input type="checkbox" id="arch_is_senior" disabled> Senior</label>
            <label><input type="checkbox" id="arch_is_pwd" disabled> PWD</label>
            <label><input type="checkbox" id="arch_is_4ps" disabled> 4Ps</label>
            <label><input type="checkbox" id="arch_is_solo_parent" disabled> Solo Parent</label>
          </div>
        </div>
      </div>

      <div class="mt-3 flex justify-end space-x-2">
        <button type="button" id="restoreResident" class="bg-emerald-500 text-white px-4 py-2 rounded">Restore</button>
        <button type="button" id="deleteArchivedResident" class="bg-red-500 text-white px-4 py-2 rounded">Delete</button>
      </div>

    </form>
  </div>
</div>



<script src="RESIDENT.js"></script>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
  // ===================== DASHBOARD CARD FILTER =====================
const dashboardCards = [
    { id: 'card_total', key: null, value: null }, // show all
    { id: 'card_voters', key: 'voter_status', value: 'Registered' },
    { id: 'card_unvoters', key: 'voter_status', value: 'Unregistered' },
    { id: 'card_senior', key: 'is_senior', value: '1' },
    { id: 'card_pwd', key: 'is_pwd', value: '1' },
    { id: 'card_4ps', key: 'is_4ps', value: '1' },
    { id: 'card_solo', key: 'is_solo_parent', value: '1' },
];

dashboardCards.forEach(card => {
    const el = document.getElementById(card.id);
    if (!el) return;

    el.addEventListener('click', () => {
        const tableBody = document.querySelector('#residentsTable tbody');
        const rows = tableBody?.querySelectorAll('tr') || [];

        let hasVisible = false;
        rows.forEach(row => {
            const data = row.dataset.resident ? JSON.parse(row.dataset.resident) : {};
            let match = true;
            if (card.key) match = data[card.key] == card.value;
            row.style.display = match ? '' : 'none';
            if (match) hasVisible = true;
        });

        let noFoundRow = tableBody.querySelector('.no-found-row');
        if (!noFoundRow) {
            noFoundRow = document.createElement('tr');
            noFoundRow.classList.add('no-found-row');
            noFoundRow.innerHTML = `<td colspan="6" class="px-4 py-2 text-center text-gray-500">No residents found.</td>`;
            tableBody.appendChild(noFoundRow);
        }
        noFoundRow.style.display = hasVisible ? 'none' : '';
    });
});
  const toggleArchivedBtn = document.getElementById('toggleArchivedBtn');
  const archivedContainer = document.getElementById('archivedContainer');
  const residentsTable = document.getElementById('residentsTable').closest('div'); // wrap div of residents table

  toggleArchivedBtn.addEventListener('click', () => {
    if (archivedContainer.classList.contains('hidden')) {
      // Show archived, hide main residents
      archivedContainer.classList.remove('hidden');
      residentsTable.classList.add('hidden');
      toggleArchivedBtn.textContent = 'Show Active Residents';
      toggleArchivedBtn.classList.remove('bg-red-500');
      toggleArchivedBtn.classList.add('bg-green-500');
    } else {
      // Show main residents, hide archived
      archivedContainer.classList.add('hidden');
      residentsTable.classList.remove('hidden');
      toggleArchivedBtn.textContent = 'Show Archived Residents';
      toggleArchivedBtn.classList.remove('bg-green-500');
      toggleArchivedBtn.classList.add('bg-red-500');
    }
  });

</script>




</body>
</html>
