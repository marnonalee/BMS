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

$query = "
    SELECT bo.*, r.first_name, r.last_name, p.position_name, p.id as position_id
    FROM barangay_officials bo
    LEFT JOIN residents r ON bo.resident_id = r.resident_id
    LEFT JOIN positions p ON bo.position_id = p.id
    ORDER BY bo.position_id ASC
";
$result = $conn->query($query);

$residentsQuery = $conn->query("SELECT resident_id, first_name, last_name FROM residents ORDER BY first_name ASC");
$residentsList = [];
while($r = $residentsQuery->fetch_assoc()){
    $residentsList[] = $r;
}

$positionsQuery = $conn->query("SELECT id, position_name, `limit` FROM positions WHERE status='Active' ORDER BY id ASC");
$positionsList = [];
while($p = $positionsQuery->fetch_assoc()){
    $positionsList[] = $p;
}

$positionCounts = [];
$countQuery = $conn->query("SELECT position_id, COUNT(*) as cnt FROM barangay_officials GROUP BY position_id");
while($row = $countQuery->fetch_assoc()){
    $positionCounts[$row['position_id']] = $row['cnt'];
}

$assignedResidentsIds = [];
$assignedQuery = $conn->query("SELECT resident_id FROM barangay_officials");
while($row = $assignedQuery->fetch_assoc()){
    $assignedResidentsIds[] = $row['resident_id'];
}
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
<title>Barangay Officials</title>
<link rel="stylesheet" href="b.css">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

</head>
<body class=" font-sans">
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

    <a href="../officials/barangay_officials.php" class="flex items-center px-4 py-3 rounded-md bg-white/10 backdrop-blur-sm transition-all">
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

    <a href="../reports/report.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
      <span class="material-icons mr-3">bar_chart</span><span class="sidebar-text">Reports</span>
    </a>

    <div class="pt-4">
      <span class="px-4 py-2 text-white/70 uppercase text-xs tracking-wider sidebar-text">ID Management</span>
      <a href="../id/barangay_id_requests.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 transition-colors">
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
  <div class="flex-1 flex flex-col overflow-hidden">
   <header class="flex items-center justify-between bg-white shadow-md px-6 py-4 flex-shrink-0 rounded-b-2xl">
  <h2 class="text-2xl font-bold text-gray-800">Barangay Officials</h2>
  <div class="flex items-center space-x-4">
    <?php if($role === 'admin'): ?>
      <button onclick="openAddPanel()" 
              class="bg-emerald-500 hover:bg-emerald-600 text-white px-5 py-2 rounded-lg shadow font-medium transition">
        Add Official
      </button>
    <?php endif; ?>
  </div>
</header>

<main class="flex-1 overflow-y-auto p-6">

    <!-- Add Official Modal -->
    <div id="addPanelBackdrop" class="fixed inset-0 bg-black bg-opacity-50 hidden z-40"></div>
    <div id="addPanel" class="hidden fixed inset-0 z-50 flex items-center justify-center">
        <div class="bg-white shadow-lg p-6 rounded-2xl max-w-2xl w-full relative transform scale-90 transition-transform duration-200">
            <button onclick="closeAddPanel()" class="absolute top-3 right-3 material-icons text-gray-600 hover:text-gray-800 text-2xl cursor-pointer">close</button>
            <h2 class="text-2xl font-bold mb-6 text-gray-800">Add Barangay Official</h2>
            <form action="add_official.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2 flex flex-col items-center">
                    <label class="block font-semibold mb-2 text-gray-700">Photo</label>
                    <div class="h-32 w-32 rounded-full overflow-hidden mb-2 border border-gray-300 shadow-sm">
                        <img id="addPhotoPreview" class="object-cover h-full w-full rounded-full" src="../uploads/default-avatar.jpg">
                    </div>
                    <input type="file" name="photo" id="addPhotoInput" class="w-full text-sm text-gray-700 px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-400" accept="image/*">
                </div>
                <div>
                    <label class="block font-semibold mb-2 text-gray-700">Resident</label>
                    <input type="text" name="resident" id="residentInput" placeholder="Type resident name..." required class="w-full border px-3 py-2 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400">
                    <ul id="residentList" class="border mt-1 max-h-40 overflow-y-auto hidden bg-white absolute w-full z-10 shadow rounded-lg"></ul>
                </div>
                <div>
                    <label class="block font-semibold mb-2 text-gray-700">Position</label>
                    <select name="position_id" required class="w-full border px-3 py-2 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400">
                        <?php foreach($positionsList as $p):
                            $count = $positionCounts[$p['id']] ?? 0;
                            $disabled = ($count >= $p['limit']) ? 'disabled' : '';
                        ?>
                            <option value="<?= $p['id'] ?>" <?= $disabled ? 'disabled' : '' ?>>
                                <?= htmlspecialchars($p['position_name']) ?><?= $disabled ? ' (Full)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold mb-2 text-gray-700">Start Date</label>
                    <input type="date" name="start_date" required class="w-full border px-3 py-2 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400">
                </div>
                <div>
                    <label class="block font-semibold mb-2 text-gray-700">End Date</label>
                    <input type="date" name="end_date" required class="w-full border px-3 py-2 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400">
                </div>
                <div class="md:col-span-2 flex justify-end gap-3 mt-4">
                    <button type="submit" class="bg-emerald-500 text-white px-6 py-2 rounded-lg hover:bg-emerald-600 shadow font-medium text-sm">Add</button>
                    <button type="button" onclick="closeAddPanel()" class="bg-gray-400 text-white px-6 py-2 rounded-lg hover:bg-gray-500 shadow text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Official Modal -->
    <div id="editPanelBackdrop" class="fixed inset-0 bg-black bg-opacity-50 hidden z-40"></div>
    <div id="editPanel" class="hidden fixed inset-0 z-50 flex items-center justify-center">
        <div class="bg-white shadow-lg p-6 rounded-2xl max-w-2xl w-full relative transform scale-90 transition-transform duration-200">
            <button onclick="closeEditPanel()" class="absolute top-3 right-3 material-icons text-gray-600 hover:text-gray-800 text-2xl cursor-pointer">close</button>
            <h2 class="text-2xl font-bold mb-6 text-gray-800">Edit Barangay Official</h2>
            <form id="editForm" action="update_official.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <input type="hidden" name="official_id" id="editOfficialId">
                <div class="md:col-span-2 flex flex-col items-center">
                    <label class="block font-semibold mb-2 text-gray-700">Photo</label>
                    <div class="mb-2 border rounded-full overflow-hidden w-32 h-32 shadow-sm">
                        <img id="editPhotoPreview" class="object-cover w-32 h-32 rounded-full">
                    </div>
                    <input type="file" name="photo" id="editPhotoInput" class="w-full text-sm text-gray-700 px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-400">
                </div>
                <div>
                    <label class="block font-semibold mb-2 text-gray-700">Resident</label>
                    <select name="resident_id" id="editResident" class="w-full border px-3 py-2 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400">
                        <?php foreach($residentsList as $res):
                            $isAssigned = in_array($res['resident_id'], $assignedResidentsIds);
                        ?>
                            <option value="<?= $res['resident_id'] ?>" <?= $isAssigned ? 'disabled' : '' ?>>
                                <?= htmlspecialchars($res['first_name'].' '.$res['last_name']) ?><?= $isAssigned ? ' (Already in Position)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold mb-2 text-gray-700">Position</label>
                    <select name="position_id" id="editPosition" class="w-full border px-3 py-2 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400">
                        <?php foreach($positionsList as $p):
                            $count = $positionCounts[$p['id']] ?? 0;
                            $disabled = ($count >= $p['limit']) ? 'disabled' : '';
                        ?>
                            <option value="<?= $p['id'] ?>" <?= $disabled ? 'disabled' : '' ?>>
                                <?= htmlspecialchars($p['position_name']) ?><?= $disabled ? ' (Full)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold mb-2 text-gray-700">Start Date</label>
                    <input type="date" name="start_date" id="editStart" class="w-full border px-3 py-2 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400" required>
                </div>
                <div>
                    <label class="block font-semibold mb-2 text-gray-700">End Date</label>
                    <input type="date" name="end_date" id="editEnd" class="w-full border px-3 py-2 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400" required>
                </div>
                <div class="md:col-span-2 flex justify-end gap-3 mt-4">
                    <button type="submit" class="bg-emerald-500 text-white px-6 py-2 rounded-lg hover:bg-emerald-600 shadow font-medium text-sm">Update</button>
                    <button type="button" onclick="closeEditPanel()" class="bg-gray-400 text-white px-6 py-2 rounded-lg hover:bg-gray-500 shadow text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Official Modal -->
    <div id="officialModalBackdrop" class="fixed inset-0 bg-black bg-opacity-50 hidden z-40"></div>
    <div id="officialModal" class="fixed inset-0 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white shadow-2xl w-full max-w-2xl p-6 rounded-2xl relative overflow-hidden">
            <button onclick="closeModal()" class="absolute top-4 right-4 material-icons text-gray-600 hover:text-gray-800 cursor-pointer text-3xl transition">close</button>

            <div id="viewContent" class="space-y-6">
                <div class="flex flex-col md:flex-row items-center md:items-start gap-6">
                    <div id="modalPhoto" class="h-40 w-40 md:w-40 md:h-40 bg-gray-200 rounded-xl overflow-hidden flex-shrink-0 shadow-inner">
                        <img id="modalPhotoImg" class="w-full h-full object-cover" src="" alt="Official Photo">
                    </div>
                    <div class="flex-1 text-center md:text-left">
                        <h2 id="modalName" class="text-2xl font-bold text-gray-800 mb-2"></h2>
                        <p id="modalPosition" class="text-lg text-gray-700 mb-1"></p>
                        <p id="modalDept" class="text-base text-gray-600 mb-1"></p>
                        <p id="modalTerm" class="text-sm italic text-gray-500"></p>
                    </div>
                </div>
                <?php if($role === 'admin'): ?>
                <div class="flex justify-center md:justify-end">
                    <button onclick="switchToEdit()" class="bg-yellow-400 hover:bg-yellow-500 text-white px-5 py-2 rounded-lg shadow font-medium text-sm transition">Edit</button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Officials Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 mb-6 auto-rows-fr">
        <?php if ($result->num_rows > 0): ?>
            <?php while($o = $result->fetch_assoc()): ?>
            <div class="bg-white shadow-md rounded-2xl overflow-hidden cursor-pointer hover:shadow-xl transition flex flex-col items-center" onclick='openModal(<?= json_encode($o) ?>)'>
                <div class="w-full h-60 bg-gray-100 flex items-center justify-center overflow-hidden">
                    <img src="../uploads/<?= $o['photo'] && trim($o['photo']) !== '' ? htmlspecialchars($o['photo']) : 'official.jpg' ?>" class="h-full w-auto object-cover">
                </div>
                <div class="p-3 text-center w-full">
                    <h2 class="text-sm font-semibold truncate text-gray-800"><?= htmlspecialchars($o['first_name'].' '.$o['last_name']) ?></h2>
                    <p class="text-xs text-gray-600 truncate"><strong>Position:</strong> <?= htmlspecialchars($o['position_name']) ?></p>
                    <?php if(!empty($o['department'])): ?>
                    <p class="text-xs text-gray-500 truncate"><strong>Department:</strong> <?= htmlspecialchars($o['department']) ?></p>
                    <?php endif; ?>
                    <p class="text-xs text-gray-500 mt-1"><?= date('M d, Y', strtotime($o['start_date'])) ?> – <?= date('M d, Y', strtotime($o['end_date'])) ?></p>
                </div>
            </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>

</main>


<div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50"> 
    <div class="bg-white rounded-xl p-6 w-80 text-center relative">
        <button id="closeSuccessModal" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 material-icons">close</button>
        <span id="modalIcon" class="material-icons text-4xl mb-2"></span>
        <p id="successMessage" class="text-gray-700 font-medium"></p>
        <button id="okSuccessBtn" class="mt-4 bg-emerald-500 text-white px-4 py-2 rounded hover:bg-emerald-600">OK</button>
    </div>
</div>
<script>
window.residentsList = <?= json_encode($residentsList) ?>;
window.assignedResidents = <?= json_encode($assignedResidentsIds) ?>;
window.positionsList = <?= json_encode($positionsList) ?>;
window.positionCounts = <?= json_encode($positionCounts) ?>;

<?php if(isset($_SESSION['success_message'])): ?>
window.sessionMessage = <?= json_encode($_SESSION['success_message']) ?>;
<?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

let currentOfficial = {};
const residentsList = window.residentsList || [];
const assignedResidents = window.assignedResidents || [];

// Sidebar toggle
const sidebar = document.getElementById('sidebar');
const toggleBtn = document.getElementById('toggleSidebar');
toggleBtn.onclick = () => {
    sidebar.classList.toggle('sidebar-collapsed');
    let icon = toggleBtn.textContent.trim();
    toggleBtn.textContent = icon === 'chevron_left' ? 'chevron_right' : 'chevron_left';
};

// Session message modal
document.addEventListener('DOMContentLoaded', function() {
    if (window.sessionMessage) {
        const modal = document.getElementById('successModal');
        const modalMessage = document.getElementById('successMessage');
        const modalIcon = document.getElementById('modalIcon');

        let type = 'success', text = '';
        if(typeof window.sessionMessage === 'string'){
            text = window.sessionMessage;
        } else {
            type = window.sessionMessage.type || 'success';
            text = window.sessionMessage.text || '';
        }

        modalMessage.textContent = text;
        if(type === 'success'){
            modalIcon.textContent = 'check_circle';
            modalIcon.className = 'material-icons text-green-500 text-4xl mb-2';
        } else if(type === 'error'){
            modalIcon.textContent = 'error';
            modalIcon.className = 'material-icons text-red-500 text-4xl mb-2';
        } else if(type === 'warning'){
            modalIcon.textContent = 'warning';
            modalIcon.className = 'material-icons text-yellow-500 text-4xl mb-2';
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');

        document.getElementById('closeSuccessModal').addEventListener('click', ()=> modal.classList.add('hidden'));
        document.getElementById('okSuccessBtn').addEventListener('click', ()=> modal.classList.add('hidden'));
    }
});

// Resident autocomplete for Add form
const input = document.getElementById('residentInput');
const list = document.getElementById('residentList');

if(input && list){
    input.addEventListener('input', function(){
        const query = this.value.toLowerCase().trim();
        list.innerHTML = '';
        if(query === '') { list.classList.add('hidden'); return; }

        const matches = residentsList.filter(res => !assignedResidents.includes(res.resident_id) &&
            (res.first_name + ' ' + res.last_name).toLowerCase().includes(query));

        if(matches.length === 0){
            list.innerHTML = '<li class="px-2 py-1 text-gray-500">No resident found</li>';
        } else {
            matches.forEach(res => {
                const li = document.createElement('li');
                li.textContent = res.first_name + ' ' + res.last_name;
                li.className = 'px-2 py-1 hover:bg-emerald-200 cursor-pointer';
                li.addEventListener('click', function(){
                    input.value = li.textContent;
                    input.dataset.residentId = res.resident_id;
                    list.classList.add('hidden');
                });
                list.appendChild(li);
            });
        }
        list.classList.remove('hidden');
    });

    document.addEventListener('click', function(e){
        if(!list.contains(e.target) && e.target !== input){ list.classList.add('hidden'); }
    });

    document.querySelector('form').addEventListener('submit', function(e){
        const residentId = input.dataset.residentId || document.getElementById('editResident')?.value;
        if(!residentId){
            e.preventDefault();
            alert('Please select a resident from the list.');
            return;
        }

        const isAssigned = assignedResidents.includes(parseInt(residentId));
        const editingResident = currentOfficial.resident_id == residentId;

        if(isAssigned && !editingResident){
            e.preventDefault();
            if(!confirm("This resident is already assigned to another position. Do you want to overwrite?")){
                return;
            }
        }

        if(this.id !== "editForm"){
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'resident_id';
            hidden.value = residentId;
            this.appendChild(hidden);
            input.removeAttribute('name');
        }
    });
}

// Panel modals
function openAddPanel() {
    closeEditPanel();
    document.getElementById('addPanel').classList.remove('hidden');
    document.getElementById('addPanelBackdrop').classList.remove('hidden');
}
function closeAddPanel() {
    document.getElementById('addPanel').classList.add('hidden');
    document.getElementById('addPanelBackdrop').classList.add('hidden');
}
function openEditPanel() {
    closeAddPanel();
    document.getElementById('editPanel').classList.remove('hidden');
    document.getElementById('editPanelBackdrop').classList.remove('hidden');
}
function closeEditPanel() {
    document.getElementById('editPanel').classList.add('hidden');
    document.getElementById('editPanelBackdrop').classList.add('hidden');
}

// Format date
function formatDate(dateStr){
    const d = new Date(dateStr);
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return d.toLocaleDateString('en-US', options);
}

// View modal
function openModal(o) {
    currentOfficial = o;
    document.getElementById("modalPhoto").innerHTML = `<img src="../uploads/${o.photo && o.photo.trim() !== '' ? o.photo : 'official.jpg'}" class="object-cover h-full w-full rounded">`;
    document.getElementById("modalName").textContent = o.first_name + ' ' + o.last_name;
    document.getElementById("modalPosition").textContent = "Position: " + o.position_name;
    const start = formatDate(o.start_date);
    const end = formatDate(o.end_date);
    document.getElementById("modalTerm").textContent = `Term: ${start} – ${end}`;
    document.getElementById("officialModal").classList.remove("hidden");
    document.getElementById("officialModal").classList.add("flex");
    document.getElementById("officialModalBackdrop").classList.remove("hidden");
}
function closeModal() {
    document.getElementById("officialModal").classList.add("hidden");
    document.getElementById("officialModal").classList.remove("flex");
    document.getElementById("officialModalBackdrop").classList.add("hidden");
}

// Switch view → edit
function switchToEdit() {
    closeModal();
    openEditPanel();

    document.getElementById("editOfficialId").value = currentOfficial.id || currentOfficial.official_id;
    document.getElementById("editPhotoPreview").src = currentOfficial.photo ? `../uploads/${currentOfficial.photo}` : '';
    document.getElementById("editStart").value = currentOfficial.start_date;
    document.getElementById("editEnd").value = currentOfficial.end_date;

    const selectResident = document.getElementById("editResident");
    selectResident.innerHTML = "";
    residentsList.forEach(res => {
        const isAssigned = assignedResidents.includes(res.resident_id) && res.resident_id != currentOfficial.resident_id;
        const option = document.createElement("option");
        option.value = res.resident_id;
        option.text = res.first_name + " " + res.last_name + (isAssigned ? " (Already in Position)" : "");
        option.disabled = isAssigned;
        if(res.resident_id == currentOfficial.resident_id) option.selected = true;
        selectResident.appendChild(option);
    });

    const selectPosition = document.getElementById("editPosition");
    selectPosition.innerHTML = "";
    window.positionsList.forEach(p => {
        const count = window.positionCounts[p.id] || 0;
        const disabled = (count >= p.limit);
        const opt = document.createElement("option");
        opt.value = p.id;
        opt.text = p.position_name + (disabled ? " (Full)" : "");
        opt.disabled = disabled;
        if(opt.value == currentOfficial.position_id) opt.selected = true;
        selectPosition.appendChild(opt);
    });
}

// Photo previews
document.getElementById("editPhotoInput")?.addEventListener("change", function() {
    const file = this.files[0];
    const preview = document.getElementById("editPhotoPreview");
    if(file){
        const reader = new FileReader();
        reader.onload = e => preview.src = e.target.result;
        reader.readAsDataURL(file);
    } else {
        preview.src = currentOfficial.photo ? `../uploads/${currentOfficial.photo}` : '';
    }
});
document.getElementById("addPhotoInput")?.addEventListener("change", function() {
    const file = this.files[0];
    const preview = document.getElementById("addPhotoPreview");
    if(file){
        const reader = new FileReader();
        reader.onload = e => preview.src = e.target.result;
        reader.readAsDataURL(file);
    } else {
        preview.src = '../uploads/default-avatar.jpg';
    }
});
</script>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html>
