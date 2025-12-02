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
$successMsg = '';

$settingsQuery = $conn->query("SELECT barangay_name, system_logo, theme_color FROM system_settings LIMIT 1");
$settings = $settingsQuery->fetch_assoc();
$barangayName = $settings['barangay_name'] ?? 'Barangay Name';
$systemLogo = $settings['system_logo'] ?? 'default-logo.png';
$themeColor = $settings['theme_color'] ?? '#1D4ED8';
$systemLogoPath = '../' . $systemLogo;

$success = $error = '';
$residents = $conn->query("SELECT resident_id, first_name, middle_name, last_name, birthdate, resident_address, birth_place, sex FROM residents WHERE is_archived = 0 ORDER BY last_name ASC")->fetch_all(MYSQLI_ASSOC);

function logActivity($conn, $user_id, $action, $description = null) {
    $stmt = $conn->prepare("INSERT INTO log_activity (user_id, action, description, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $user_id, $action, $description);
    $stmt->execute();
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $resident_id = !empty($_POST['resident_id']) ? (int)$_POST['resident_id'] : null;
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name = trim($_POST['last_name']);
    $full_name = $first_name . ' ' . $middle_name . ' ' . $last_name;
    $birthdate = trim($_POST['birthdate']);
    $resident_address = trim($_POST['resident_address']);
    $birth_place = trim($_POST['birth_place']);
    $sex = trim($_POST['sex']);
    $nature_of_residency = trim($_POST['nature_of_residency']);
    $emergency_name = trim($_POST['person_to_contact']);
    $emergency_contact = trim($_POST['contact_number_person']);
    $request_type = 'Walk-in';

    $uploadBase = __DIR__ . '/../../resident/uploads/';
    if (!is_dir($uploadBase)) mkdir($uploadBase, 0755, true);
    $imageDir = $uploadBase . 'image/';
    if (!is_dir($imageDir)) mkdir($imageDir, 0755, true);
    $signatureDir = $uploadBase . 'signature/';
    if (!is_dir($signatureDir)) mkdir($signatureDir, 0755, true);

    $picture_db = '';
    $signature_db = '';
    $allowedExts = ['jpg','jpeg','png'];

    foreach (['picture','signature'] as $fileField) {
        if (!empty($_FILES[$fileField]['name'])) {
            $file = $_FILES[$fileField];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExts)) $error = "Invalid file type for $fileField.";
            else {
                $fileName = uniqid() . "_$fileField.$ext";
                $targetDir = $fileField === 'picture' ? $imageDir : $signatureDir;
                move_uploaded_file($file['tmp_name'], $targetDir . $fileName);
                ${$fileField . '_db'} = ($fileField === 'picture' ? 'image/' : 'signature/') . $fileName;
            }
        }
    }

    if (!$error && $resident_id) {
        $stmtUpdateResident = $conn->prepare("UPDATE residents SET first_name=?, middle_name=?, last_name=?, birthdate=?, resident_address=?, birth_place=?, sex=? WHERE resident_id=?");
        $stmtUpdateResident->bind_param("sssssssi", $first_name, $middle_name, $last_name, $birthdate, $resident_address, $birth_place, $sex, $resident_id);
        $stmtUpdateResident->execute();
        $stmtUpdateResident->close();
        logActivity($conn, $user_id, 'Update Resident', "Updated resident: $full_name");

        $year = date('Y');
        $prefix = "P-IV-$year";
        $likePattern = $prefix . '-%';
        $lastQuery = $conn->prepare("SELECT id_number FROM barangay_id_requests WHERE id_number LIKE ? ORDER BY id_number DESC LIMIT 1");
        $lastQuery->bind_param("s", $likePattern);
        $lastQuery->execute();
        $lastResult = $lastQuery->get_result();
        if ($lastResult->num_rows > 0) {
            $row = $lastResult->fetch_assoc();
            $parts = explode('-', $row['id_number']);
            $lastNum = intval(end($parts));
            $newNum = str_pad($lastNum + 1, 5, '0', STR_PAD_LEFT);
        } else $newNum = '00001';

        $newIdNumber = $prefix . '-' . $newNum;

        $stmtID = $conn->prepare("INSERT INTO barangay_id_requests 
            (resident_id, request_type, id_number, status, nature_of_residency, picture, signature, emergency_name, emergency_contact, birthdate, resident_address, birth_place, sex, created_at)
            VALUES (?, ?, ?, 'Approved', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

        $stmtID->bind_param(
            "isssssssssss",
            $resident_id,
            $request_type,
            $newIdNumber,
            $nature_of_residency,
            $picture_db,
            $signature_db,
            $emergency_name,
            $emergency_contact,
            $birthdate,
            $resident_address,
            $birth_place,
            $sex
        );

        if ($stmtID->execute()) {
            $requestId = $stmtID->insert_id;
            logActivity($conn, $user_id, 'Walk-in Request', "Submitted walk-in request for: $full_name");
            header("Location: generate_id.php?request_id=$requestId");
            exit;
        } else $error = "Failed to submit ID request: " . $stmtID->error;

        $stmtID->close();
    } else $error = "Please select a valid resident.";
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Walk-in Barangay ID Request</title>
    <link rel="stylesheet" href="b.css">
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
      <a href="../id/barangay_id_requests.php"class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
        <span class="material-icons mr-3">credit_card</span><span class="sidebar-text">Barangay ID Request</span>
      </a>
      <a href="../id/walk_in_request.php" class="flex items-center px-4 py-3 rounded-md bg-white/10 backdrop-blur-sm transition-all">
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
    <h2 class="text-2xl font-bold text-gray-800">Walk-in Barangay ID Request</h2>
  </header>
    <main class="flex-1 overflow-y-auto p-6">
        <?php if($error): ?><div class="bg-red-100 text-red-800 px-4 py-3 rounded mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if($success): ?><div class="bg-green-100 text-green-800 px-4 py-3 rounded mb-4"><?= htmlspecialchars($success) ?></div><?php endif; ?>

      <form method="POST" enctype="multipart/form-data" id="walkinForm" class="bg-white rounded-xl shadow-md w-full max-w-[95%] mx-auto p-6 space-y-6">
          <div class="relative">
              <label for="residentSearch" class="block mb-1 font-semibold text-gray-700">Resident</label>
              <input type="text" id="residentSearch" placeholder="Type resident name..." class="w-full border-b-2 border-gray-300 focus:border-blue-500 outline-none py-2 pl-3 rounded bg-transparent autocomplete-off">
              <input type="hidden" name="resident_id" id="resident_id">
              <ul id="residentList" class="bg-white shadow rounded max-w-md mt-1 hidden absolute z-50"></ul>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
              <div><label class="block mb-1 font-medium text-gray-700">Last Name</label><input type="text" name="last_name" id="last_name" class="w-full border-b-2 border-gray-300 focus:border-blue-500 outline-none py-2 rounded bg-transparent"></div>
              <div><label class="block mb-1 font-medium text-gray-700">First Name</label><input type="text" name="first_name" id="first_name" class="w-full border-b-2 border-gray-300 focus:border-blue-500 outline-none py-2 rounded bg-transparent"></div>
              <div><label class="block mb-1 font-medium text-gray-700">Middle Name</label><input type="text" name="middle_name" id="middle_name" class="w-full border-b-2 border-gray-300 focus:border-blue-500 outline-none py-2 rounded bg-transparent"></div>
              <div><label class="block mb-1 font-medium text-gray-700">Birthdate</label><input type="date" name="birthdate" id="birthdate" class="w-full border-b-2 border-gray-300 focus:border-blue-500 outline-none py-2 rounded bg-transparent"></div>
              <div><label class="block mb-1 font-medium text-gray-700">Resident Address</label><input type="text" name="resident_address" id="resident_address" class="w-full border-b-2 border-gray-300 focus:border-blue-500 outline-none py-2 rounded bg-transparent"></div>
              <div><label class="block mb-1 font-medium text-gray-700">Birthplace</label><input type="text" name="birth_place" id="birth_place" class="w-full border-b-2 border-gray-300 focus:border-blue-500 outline-none py-2 rounded bg-transparent"></div>
              <div><label class="block mb-1 font-medium text-gray-700">Sex</label><input type="text" name="sex" id="sex" class="w-full border-b-2 border-gray-300 focus:border-blue-500 outline-none py-2 rounded bg-transparent"></div>
              <div><label class="block mb-1 font-medium text-gray-700">Nature of Residency</label><select name="nature_of_residency" required class="w-full border-b-2 border-gray-300 focus:border-blue-500 outline-none py-2 rounded bg-transparent"><option value="">--Select Residency--</option><option value="Resident">Resident</option><option value="Tenant">Tenant</option></select></div>
              <div><label class="block mb-1 font-medium text-gray-700">Emergency Contact Person</label><input type="text" name="person_to_contact" required class="w-full border-b-2 border-gray-300 focus:border-blue-500 outline-none py-2 rounded bg-transparent"></div>
             <div>
              <label class="block mb-1 font-medium text-gray-700">Emergency Contact Number</label>
              <input type="text" name="contact_number_person" required
                    class="w-full border-b-2 border-gray-300 focus:border-blue-500 outline-none py-2 rounded bg-transparent"
                    placeholder="09XXXXXXXXX"
                    maxlength="11"
                    pattern="09[0-9]{9}"
                    title="Enter a valid Philippine mobile number starting with 09"
                    oninput="this.value=this.value.replace(/[^0-9]/g,'')">
            </div>

              <div><label class="block mb-1 font-medium text-gray-700">Picture</label><input type="file" name="picture" accept=".jpg,.jpeg,.png" class="w-full"></div>
              <div><label class="block mb-1 font-medium text-gray-700">Signature</label><input type="file" name="signature" accept=".jpg,.jpeg,.png" class="w-full"></div>
          </div>
          <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg shadow hover:bg-blue-700 transition font-semibold">Submit Walk-in Request</button>
      </form>
    </main>
</div>
</div>


<div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="flex flex-col items-center">
        <div class="w-16 h-16 border-4 border-white border-t-transparent rounded-full animate-spin mb-4"></div>
        <div class="text-white text-lg font-semibold">Generating ID, please wait...</div>
    </div>
</div>


<div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
  <div class="bg-white rounded-xl p-6 w-80 text-center relative">
    <button id="closeSuccessModal" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 material-icons">close</button>
    <span id="successIcon" class="material-icons text-4xl text-green-500">check_circle</span>
    <p id="successMessage" class="mt-2"></p>
    <button id="okSuccessModal" class="mt-3 bg-emerald-500 text-white px-4 py-2 rounded">OK</button>
  </div>
</div>

<script>
const toggleSidebar=document.getElementById('toggleSidebar');
const sidebar=document.getElementById('sidebar');
toggleSidebar.onclick=()=>{sidebar.classList.toggle('sidebar-collapsed');toggleSidebar.textContent=toggleSidebar.textContent==='chevron_left'?'chevron_right':'chevron_left';};

const residents=<?= json_encode($residents) ?>;
const searchInput=document.getElementById('residentSearch');
const residentList=document.getElementById('residentList');
const residentIdInput=document.getElementById('resident_id');

searchInput.addEventListener('input',()=>{
    const value=searchInput.value.toLowerCase();
    residentList.innerHTML='';
    if(!value){residentList.classList.add('hidden');return;}
    const matches=residents.filter(r=>`${r.first_name} ${r.middle_name} ${r.last_name}`.toLowerCase().includes(value));
    matches.forEach(r=>{
        const li=document.createElement('li');
        li.textContent=`${r.last_name}, ${r.first_name} ${r.middle_name}`;
        li.className='px-3 py-2 hover:bg-gray-200 cursor-pointer';
        li.onclick=()=>{
            document.getElementById('first_name').value=r.first_name;
            document.getElementById('middle_name').value=r.middle_name;
            document.getElementById('last_name').value=r.last_name;
            document.getElementById('birthdate').value=r.birthdate;
            document.getElementById('resident_address').value=r.resident_address;
            document.getElementById('birth_place').value=r.birth_place;
            document.getElementById('sex').value=r.sex;
            residentIdInput.value = r.resident_id;
            residentList.classList.add('hidden');
        };
        residentList.appendChild(li);
    });
    residentList.classList.remove('hidden');
});

const walkinForm = document.getElementById('walkinForm');
const loadingOverlay = document.getElementById('loadingOverlay');

walkinForm.addEventListener('submit', () => {
    loadingOverlay.classList.remove('hidden');
});

const successModal = document.getElementById('successModal');
const closeSuccessModal = document.getElementById('closeSuccessModal');
const okSuccessModal = document.getElementById('okSuccessModal');
const successMessage = document.getElementById('successMessage');

<?php if(!empty($successMsg)): ?>
successMessage.textContent = <?= json_encode($successMsg) ?>;
successModal.classList.remove('hidden');
<?php endif; ?>

closeSuccessModal.onclick = () => successModal.classList.add('hidden');
okSuccessModal.onclick = () => successModal.classList.add('hidden');
</script>

</body>
</html>
