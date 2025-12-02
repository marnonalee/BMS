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

$householdCountQuery = $conn->query("SELECT COUNT(*) AS total FROM households");
$householdCount = $householdCountQuery->fetch_assoc()['total'];

$settingsQuery = $conn->query("SELECT barangay_name, system_logo FROM system_settings LIMIT 1");
$settings = $settingsQuery->fetch_assoc();
$barangayName = $settings['barangay_name'] ?? 'Barangay Name';
$systemLogo = $settings['system_logo'] ?? 'default-logo.png';
$systemLogoPath = '../' . $systemLogo;

$householdsQuery = $conn->query("
    SELECT h.household_id, h.family_id, h.household_address, h.street, h.head_resident_id,
           r.first_name, r.last_name
    FROM households h
    LEFT JOIN residents r ON h.head_resident_id = r.resident_id
    WHERE r.is_archived = 0
    ORDER BY h.household_id ASC
");


?>

<!DOCTYPE html>
<html>
<head>
    <title>Households</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-gray-100 relative">
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

    <a href="../households/household.php"  class="flex items-center px-4 py-3 rounded-md bg-white/10 backdrop-blur-sm transition-all">
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
    <h2 class="text-2xl font-bold text-gray-800">Household List</h2>

    <div class="flex items-center space-x-3">
        <input id="searchInput" type="text" placeholder="Search residents..."
            class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400 text-sm transition w-60">
        <button id="printBtn"
            class="bg-emerald-500 hover:bg-emerald-600 text-white px-5 py-2 rounded-lg shadow font-medium transition">
            Generate Household
        </button>
        <button onclick="showAddHeadModal()"
            class="bg-blue-500 hover:bg-blue-600 text-white px-5 py-2 rounded-lg shadow font-medium transition">
            Add Head of Family
        </button>

    </div>
</header>


<main class="flex-1 overflow-y-auto p-6 bg-gray-50 space-y-6">

  <div class="bg-white shadow-md rounded-lg px-6 py-4 flex items-center justify-between">
    <h1 class="text-2xl font-bold text-gray-800">Total Households</h1>
    <span id="totalHouseholds" class="text-2xl font-semibold text-emerald-600"><?= $householdCount ?></span>
  </div>

  <p id="noResults" class="text-center text-gray-500 hidden">No results found.</p>


<div class="space-y-4">
<?php 
$cardNumber = 1; 
while($household = $householdsQuery->fetch_assoc()):
    $accId = "acc".$household['household_id'];
    $household_id = intval($household['household_id']);
    $headResidentId = isset($household['head_resident_id']) ? (int)$household['head_resident_id'] : 0;

    $head = ['first_name'=>'N/A','last_name'=>'','resident_address'=>'','street'=>''];
    if($headResidentId){
        $headQuery = $conn->query("SELECT first_name, last_name, resident_address, street FROM residents WHERE resident_id = $headResidentId AND is_archived = 0");
        if($headQuery) $head = $headQuery->fetch_assoc();
    }

    $membersQuery = $conn->query("
        SELECT resident_id, first_name, last_name, relationship
        FROM residents
        WHERE household_id = $household_id
        AND resident_id != $headResidentId
        AND is_family_head = 0
        AND is_archived = 0
        ORDER BY last_name ASC
    ");
?>
<div class="household-card bg-white shadow-lg rounded-xl overflow-hidden relative border border-gray-200">
    <button onclick="toggleAccordion('<?= $accId ?>')" class="w-full text-left px-6 py-4 flex justify-between items-center hover:bg-gray-50 transition">
        <div>
            <p class="text-gray-400 font-semibold mb-1">Household #<?= $cardNumber ?></p>
            <h2 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($head['first_name'].' '.$head['last_name']) ?></h2>
            <p class="text-gray-600 text-sm"><?= htmlspecialchars($head['resident_address']) ?><?= $head['street'] ? ', '.htmlspecialchars($head['street']) : '' ?></p>
            <p class="text-gray-600 text-sm"><b>Family ID:</b> <?= htmlspecialchars($household['family_id']) ?: 'N/A' ?></p>
        </div>
        <span id="icon-<?= $accId ?>" class="material-icons text-gray-400 text-3xl transition-transform">expand_more</span>
    </button>
    <div id="<?= $accId ?>" class="hidden px-6 pb-6 bg-gray-50 border-t border-gray-200">
        <p class="text-gray-700 mt-3 font-semibold">Members:</p>
        <div id="members-list-<?= $accId ?>" class="mt-2 space-y-2">
            <?php $count=1; while($member=$membersQuery->fetch_assoc()): ?>
            <div class="flex items-center justify-between bg-white px-3 py-2 rounded shadow-sm hover:bg-gray-50 transition">
                <span><?= $count ?>. <?= htmlspecialchars($member['first_name'].' '.$member['last_name']) ?> 
                    <?= !empty($member['relationship']) ? '('.htmlspecialchars($member['relationship']).')' : '' ?>
                </span>
                <div class="space-x-2">
                    <button onclick="editMember(<?= $member['resident_id'] ?>,'<?= htmlspecialchars($member['first_name']) ?>','<?= htmlspecialchars($member['last_name']) ?>')" class="text-blue-600 hover:underline text-sm">Edit</button>
                    <button onclick="removeMember(<?= $member['resident_id'] ?>,<?= $household_id ?>,'<?= $accId ?>')" class="text-red-600 hover:underline text-sm">Remove</button>
                </div>
            </div>
            <?php $count++; endwhile; ?>
            <?php if($count==1) echo "<p class='text-gray-500'>No members yet.</p>"; ?>
        </div>

        <form onsubmit="addMember(event,<?= $household_id ?>,'<?= $accId ?>')" class="mt-4 flex items-center space-x-2">
            <input id="input-<?= $accId ?>" type="text" placeholder="Type resident name..." autocomplete="off" class="flex-1 border-b-2 border-gray-300 focus:border-emerald-500 outline-none py-2 pl-3 bg-transparent transition">
            <input id="resident-id-<?= $accId ?>" type="hidden">
            
            <select id="relationship-<?= $accId ?>" class="border-b-2 border-gray-300 focus:border-emerald-500 outline-none py-2 px-2 bg-transparent">
                <option value="">Select Relationship (optional)</option>
                <option value="Spouse">Spouse</option>
                <option value="Child">Child</option>
                <option value="Parent">Parent</option>
                <option value="Sibling">Sibling</option>
                <option value="Other">Other</option>
            </select>

            <button type="submit" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 transition">Add</button>
        </form>

    </div>
</div>
<div id="search-<?= $accId ?>" class="absolute bg-white border border-gray-200 rounded shadow-lg max-h-48 overflow-y-auto hidden z-50"></div>
<?php $cardNumber++; endwhile; ?>
</div>



</main>

</div>
<div id="addHeadModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
  <div class="bg-white rounded-xl p-6 w-11/12 md:w-2/3 relative max-h-[80vh] overflow-y-auto">
    <button onclick="closeAddHeadModal()" class="absolute top-2 right-2 material-icons">close</button>
    <h2 class="text-xl font-bold mb-4">Add Head of Family</h2>
    <form id="addHeadForm">
      <input type="text" id="residentSearch" name="resident_name" placeholder="Search Resident" class="w-full mb-2 border-b py-1 px-2" autocomplete="off">
      <input type="hidden" name="resident_id" id="residentId">
      <div class="overflow-x-auto mt-2">
        <table class="min-w-full divide-y divide-gray-200 hidden" id="resultsTable">
          <thead class="bg-gray-100">
            <tr>
              <th class="px-6 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
              <th class="px-6 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Sex</th>
              <th class="px-6 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Age</th>
              <th class="px-6 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Voter Status</th>
              <th class="px-6 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Resident Address</th>
            </tr>
          </thead>
          <tbody id="resultsBody" class="bg-white divide-y divide-gray-200"></tbody>
        </table>
      </div>
      <button type="submit" class="bg-emerald-500 text-white px-4 py-2 rounded mt-4 w-full">Add Head</button>
    </form>
  </div>
</div>

<div id="messageModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-80 text-center relative">
        <button id="closeModal" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 material-icons">close</button>
        <span id="modalIcon" class="material-icons text-4xl mb-2"></span>
        <p id="modalMessage" class="text-gray-700 font-medium"></p>
        <button id="okModalBtn" class="mt-4 bg-emerald-500 text-white px-4 py-2 rounded hover:bg-emerald-600">OK</button>
    </div>
</div>

<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-96 text-center relative">
        <button id="closeEditModal" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 material-icons">close</button>
        <h2 class="text-xl font-bold mb-4">Edit Member</h2>
        <input id="editFirstName" type="text" placeholder="First Name" class="w-full border-b-2 border-gray-300 py-2 px-3 mb-3 rounded">
        <input id="editLastName" type="text" placeholder="Last Name" class="w-full border-b-2 border-gray-300 py-2 px-3 mb-4 rounded">
        <button id="saveEditBtn" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Save</button>
    </div>
</div>

<div id="confirmModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-80 text-center relative">
        <button id="closeConfirmModal" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 material-icons">close</button>
        <p class="text-gray-700 font-medium mb-4">Are you sure you want to remove this member?</p>
        <button id="confirmYes" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 mr-2">Yes</button>
        <button id="confirmNo" class="bg-gray-300 text-black px-4 py-2 rounded hover:bg-gray-400">No</button>
    </div>
</div>


<iframe id="printFrame" style="display:none;"></iframe>
<script>
function showAddHeadModal(){ document.getElementById('addHeadModal').classList.remove('hidden'); }
function closeAddHeadModal(){ document.getElementById('addHeadModal').classList.add('hidden'); }

const searchInput = document.getElementById('residentSearch');
const resultsTable = document.getElementById('resultsTable');
const resultsBody = document.getElementById('resultsBody');
const residentIdInput = document.getElementById('residentId');

searchInput.addEventListener('input', () => {
    const query = searchInput.value.trim();
    if(query.length < 1){
        resultsTable.classList.add('hidden');
        resultsBody.innerHTML = '';
        return;
    }
    fetch(`get_head_resident.php?query=${encodeURIComponent(query)}`)
    .then(res => res.json())
    .then(data => {
        resultsBody.innerHTML = '';
        if(data.length === 0){
            resultsBody.innerHTML = '<tr><td colspan="6" class="p-2 text-center">No results found</td></tr>';
        } else {
            data.forEach(resident => {
                const tr = document.createElement('tr');
                tr.className = 'cursor-pointer hover:bg-gray-100';
                tr.innerHTML = `
                    <td class="px-6 py-2">${resident.full_name}</td>
                    <td class="px-6 py-2">${resident.sex}</td>
                    <td class="px-6 py-2">${resident.age || 'N/A'}</td>
                    <td class="px-6 py-2">${resident.voter_status || 'N/A'}</td>
                    <td class="px-6 py-2">${resident.resident_address || 'N/A'}</td>
                `;
                tr.addEventListener('click', () => {
                    residentIdInput.value = resident.resident_id;
                    searchInput.value = resident.full_name;
                    resultsTable.classList.add('hidden');
                });
                resultsBody.appendChild(tr);
            });
        }
        resultsTable.classList.remove('hidden');
    })
    .catch(() => {
        resultsBody.innerHTML = '<tr><td colspan="6" class="p-2 text-center text-red-500">Error fetching data</td></tr>';
        resultsTable.classList.remove('hidden');
    });
});

document.getElementById('addHeadForm').onsubmit = function(e){ 
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('is_family_head', 1);
    formData.append('add_resident', 1);
    fetch('residents.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if(res.success){
            showModal("Head of family added successfully!");
            closeAddHeadModal();
            location.reload();
        } else {
            showModal("Error: " + (res.error || 'Unknown error'));
        }
    });
};

function showModal(message,type='success',callback=null){
    const modal=document.getElementById('messageModal');
    const icon=document.getElementById('modalIcon');
    const msg=document.getElementById('modalMessage');
    const okBtn=document.getElementById('okModalBtn');
    msg.textContent=message;
    if(type==='success'){icon.textContent='check_circle'; okBtn.className='mt-4 bg-emerald-500 text-white px-4 py-2 rounded hover:bg-emerald-600';}
    else{icon.textContent='error'; okBtn.className='mt-4 bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600';}
    modal.classList.remove('hidden');
    okBtn.onclick=()=>{modal.classList.add('hidden'); if(callback)callback();};
    document.getElementById('closeModal').onclick=()=>{modal.classList.add('hidden'); if(callback)callback();};
}

function toggleAccordion(id){
    document.querySelectorAll("[id^='acc']").forEach(acc => {
        if (acc.id !== id) {
            acc.classList.add("hidden");
            let otherIcon = document.getElementById("icon-" + acc.id);
            if (otherIcon) otherIcon.textContent = "expand_more";
        }
    });
    let content = document.getElementById(id);
    let icon = document.getElementById("icon-" + id);
    let isHidden = content.classList.contains("hidden");
    content.classList.toggle("hidden");
    icon.textContent = isHidden ? "expand_less" : "expand_more";
}

document.getElementById('printBtn').addEventListener('click', function() {
    const iframe = document.getElementById('printFrame');
    iframe.src = 'print_household.php';
    iframe.onload = function() { iframe.contentWindow.focus(); iframe.contentWindow.print(); };
});

function setupLiveSearch(inputId, searchId){
    const input=document.getElementById(inputId);
    const search=document.getElementById(searchId);
    const hiddenInput=document.getElementById('resident-id-' + inputId.split('-')[1]);
    input.addEventListener('input', function(){
        const query=this.value.trim();
        hiddenInput.value='';
        if(query.length===0){search.classList.add('hidden'); search.innerHTML=''; return;}
        fetch(`get_residents_search.php?query=${encodeURIComponent(query)}`)
        .then(res=>res.json())
        .then(data=>{
            search.innerHTML='';
            if(data.length===0){
                const noRes=document.createElement('div');
                noRes.className='px-4 py-2 text-gray-500';
                noRes.textContent='No residents found';
                search.appendChild(noRes);
            } else {
                data.forEach(resident=>{
                    const div=document.createElement('div');
                    div.className='px-4 py-2 hover:bg-gray-100 cursor-pointer';
                    div.textContent=resident.first_name + ' ' + resident.last_name;
                    div.dataset.residentId = resident.resident_id;
                    div.addEventListener('click', ()=>{
                        input.value=div.textContent;
                        hiddenInput.value=div.dataset.residentId;
                        search.classList.add('hidden');
                    });
                    search.appendChild(div);
                });
            }
            const rect=input.getBoundingClientRect();
            search.style.top=rect.bottom + window.scrollY + 'px';
            search.style.left=rect.left + window.scrollX + 'px';
            search.style.width=rect.width + 'px';
            search.classList.remove('hidden');
        });
    });
    document.addEventListener('click', function(e){ if(e.target!==input && !search.contains(e.target)) search.classList.add('hidden'); });
}

const toggleSidebar = document.getElementById('toggleSidebar');
const sidebar = document.getElementById('sidebar');
toggleSidebar.onclick = () => {
    sidebar.classList.toggle('sidebar-collapsed');
    toggleSidebar.textContent =
        toggleSidebar.textContent === 'chevron_left'
            ? 'chevron_right'
            : 'chevron_left';
};

function addMember(event,familyId,accId){
    event.preventDefault();
    const input=document.getElementById('input-'+accId);
    const residentId=document.getElementById('resident-id-'+accId).value;
    const relationship=document.getElementById('relationship-'+accId).value; 
    
    if(!residentId){showModal("Select a resident from the dropdown.",'error'); return;}
    
    fetch('add_member.php',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({
            household_id:familyId,
            resident_id:residentId,
            relationship: relationship 
        })
    }).then(res=>res.json())
    .then(res=>{
        showModal(res.message,res.success?'success':'error',()=>{if(res.success) refreshMembers(familyId,accId);});
        input.value=''; 
        document.getElementById('resident-id-'+accId).value='';
        document.getElementById('relationship-'+accId).value=''; 
    });
}


let editResidentId = null;
let removeResidentData = {};

function editMember(residentId,firstName,lastName){
    editResidentId=residentId;
    document.getElementById('editFirstName').value=firstName;
    document.getElementById('editLastName').value=lastName;
    document.getElementById('editModal').classList.remove('hidden');
}

document.getElementById('closeEditModal').onclick=()=>{document.getElementById('editModal').classList.add('hidden');};
document.getElementById('saveEditBtn').onclick=()=>{
    const newFirst=document.getElementById('editFirstName').value.trim();
    const newLast=document.getElementById('editLastName').value.trim();
    fetch('update_member.php',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({resident_id:editResidentId,first_name:newFirst,last_name:newLast})
    }).then(res=>res.json())
    .then(res=>{
        document.getElementById('editModal').classList.add('hidden');
        showModal(res.message,res.success?'success':'error',()=>{if(res.success) location.reload();});
    });
};

function removeMember(residentId,familyId,accId){
    removeResidentData={residentId,familyId,accId};
    document.getElementById('confirmModal').classList.remove('hidden');
}

document.getElementById('closeConfirmModal').onclick=()=>{document.getElementById('confirmModal').classList.add('hidden');};
document.getElementById('confirmNo').onclick=()=>{document.getElementById('confirmModal').classList.add('hidden');};
document.getElementById('confirmYes').onclick=()=>{
    const {residentId,familyId,accId}=removeResidentData;
    fetch('remove_member.php',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({resident_id:residentId})
    }).then(res=>res.json())
    .then(res=>{
        document.getElementById('confirmModal').classList.add('hidden');
        showModal(res.message,res.success?'success':'error',()=>{if(res.success) refreshMembers(familyId,accId);});
    });
};

function refreshMembers(familyId, accId){
    fetch(`get_members.php?household_id=${familyId}`)
    .then(r => r.json())
    .then(data => {
        const listDiv = document.getElementById('members-list-' + accId);
        listDiv.innerHTML = '';
        let input = document.getElementById('input-' + accId);
        let hidden = document.getElementById('resident-id-' + accId);
        hidden.value = '';
        input.value = '';

        if(data.length === 0){
            listDiv.innerHTML = '<p class="text-gray-500">No members yet.</p>';
            return;
        }

        let count = 1;
        data.forEach(m => {
            const memberDiv = document.createElement('div');
            memberDiv.className = 'flex items-center justify-between bg-white px-3 py-2 rounded shadow-sm hover:bg-gray-50 transition';
            memberDiv.innerHTML = `
                <span>${count}. ${m.first_name} ${m.last_name} ${m.relationship ? '(' + m.relationship + ')' : ''}</span>
                <div class="space-x-2">
                    <button onclick="editMember(${m.resident_id},'${m.first_name}','${m.last_name}')" class="text-blue-600 hover:underline text-sm">Edit</button>
                    <button onclick="removeMember(${m.resident_id}, ${familyId}, '${accId}')" class="text-red-600 hover:underline text-sm">Remove</button>
                </div>
            `;
            listDiv.appendChild(memberDiv);
            count++;
        });
    })
    .catch(err => console.error('Error refreshing members:', err));
}


document.getElementById("searchInput").addEventListener("input", function () {
    let query = this.value.toLowerCase();
    let cards = document.querySelectorAll(".household-card");
    let total = 0;
    cards.forEach(card => {
        let name = card.querySelector("h2").textContent.toLowerCase();
        let address = card.querySelector("p").textContent.toLowerCase();
        if (name.includes(query) || address.includes(query)) {
            card.style.display = "block";
            total++;
        } else {
            card.style.display = "none";
        }
    });
    document.getElementById("noResults").classList.toggle("hidden", total !== 0);
    document.getElementById("totalHouseholds").textContent = total;
});

<?php
$householdsQuery->data_seek(0);
while($household = $householdsQuery->fetch_assoc()):
    $headResidentId = $household['head_resident_id'] ?? 0;
    if(!$headResidentId) continue;
    $accId = "acc".$household['household_id'];
    echo "setupLiveSearch('input-$accId','search-$accId');\n";
endwhile;
?>
</script>

</body>
</html>
