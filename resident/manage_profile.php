<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "resident") {
    header("Location: ../index.php");
    exit();
}

include '../db.php';
$user_id = $_SESSION["user_id"];

$approvalQuery = $conn->prepare("SELECT is_approved FROM users WHERE id = ?");
$approvalQuery->bind_param("i", $user_id);
$approvalQuery->execute();
$approvalResult = $approvalQuery->get_result();
if ($approvalResult->num_rows > 0) {
    $is_approved = $approvalResult->fetch_assoc()['is_approved'];
} else {
    die("User not found.");
}
$approvalQuery->close();

if ($is_approved != 1) {
    $errorMsg = "Your account is not verified yet. You cannot edit your profile.";
}
$settingsQuery = $conn->query("SELECT * FROM system_settings LIMIT 1");
$settings = $settingsQuery ? $settingsQuery->fetch_assoc() : [];
$themeColor = $settings['theme_color'] ?? '#4f46e5';

$residentIdQuery = $conn->prepare("SELECT resident_id, profile_completed FROM residents WHERE user_id = ? LIMIT 1");
$residentIdQuery->bind_param("i", $user_id);
$residentIdQuery->execute();
$resident = $residentIdQuery->get_result()->fetch_assoc();
$residentIdQuery->close();

$resident_id = $resident['resident_id'] ?? 0;

$notifQuery = $conn->prepare("SELECT * FROM notifications WHERE resident_id = ? ORDER BY date_created DESC LIMIT 20");
$notifQuery->bind_param("i", $resident_id);
$notifQuery->execute();
$notifications = $notifQuery->get_result()->fetch_all(MYSQLI_ASSOC);
$notifQuery->close();

$unreadCount = 0;
foreach ($notifications as $notif) {
    if ($notif['is_read'] == 0) $unreadCount++;
}

if ($resident_id > 0) {
    $conn->query("UPDATE notifications SET is_read = 1 WHERE resident_id = $resident_id");
}

$residentQuery = $conn->prepare("SELECT * FROM residents WHERE user_id = ?");
$residentQuery->bind_param("i", $user_id);
$residentQuery->execute();
$resident = $residentQuery->get_result()->fetch_assoc();
$residentQuery->close();
if (!$resident) die("Hindi mahanap ang profile ng residente.");

$userQuery = $conn->prepare("SELECT email FROM users WHERE id = ?");
$userQuery->bind_param("i", $user_id);
$userQuery->execute();
$userData = $userQuery->get_result()->fetch_assoc();
$userQuery->close();
$email = $userData['email'] ?? '';

$successMsg = '';
$errorMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $alias = $_POST['alias'] ?? '';
    $suffix = $_POST['suffix'] ?? '';
    $resident_address = $_POST['resident_address'] ?? '';
    $birth_place = $_POST['birth_place'] ?? '';
    $street = $_POST['street'] ?? '';
    $citizenship = $_POST['citizenship'] ?? '';
    $voter_status = $_POST['voter_status'] ?? '';
    $employment_status = $_POST['employment_status'] ?? '';
    $contact_number = $_POST['contact_number'] ?? '';
    $religion = $_POST['religion'] ?? '';
    $profession_occupation = $_POST['profession_occupation'] ?? '';
    $educational_attainment = $_POST['educational_attainment'] ?? '';
    $education_details = $_POST['education_details'] ?? '';
    $is_family_head = isset($_POST['is_family_head']) ? 1 : 0;
    $philsys_card_no = $_POST['philsys_card_no'] ?? '';
    $is_senior = isset($_POST['is_senior']) ? 1 : 0;
    $is_pwd = isset($_POST['is_pwd']) ? 1 : 0;
    $is_4ps = isset($_POST['is_4ps']) ? 1 : 0;
    $is_solo_parent = isset($_POST['is_solo_parent']) ? 1 : 0;

    $checkQuery = $conn->prepare("
        SELECT COUNT(*) as request_count 
        FROM profile_update_requests 
        WHERE user_id = ? 
          AND status IN ('Pending','Approved') 
          AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $checkQuery->bind_param("i", $user_id);
    $checkQuery->execute();
    $checkResult = $checkQuery->get_result()->fetch_assoc();
    $checkQuery->close();

    if ($checkResult['request_count'] >= 2) {
        $errorMsg = "Nakapag-submit ka na ng 2 request sa nakalipas na 7 araw. Subukan muli mamaya.";
    } else {
        if (isset($_FILES['resident_id']) && $_FILES['resident_id']['error'] === 0) {
            $allowed = ['jpg','jpeg','png','pdf'];
            $fileName = $_FILES['resident_id']['name'];
            $fileTmp = $_FILES['resident_id']['tmp_name'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (in_array($fileExt, $allowed)) {
                $newFileName = 'id_'.$user_id.'_'.time().'.'.$fileExt;
                $uploadDir = 'uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                if (move_uploaded_file($fileTmp, $uploadDir.$newFileName)) {
                    $resident_id_file = $newFileName;
                } else {
                    $errorMsg .= "Nabigo ang pag-upload ng ID. ";
                    $resident_id_file = null;
                }
            } else {
                $errorMsg .= "Di-tamang file type. JPG, PNG, o PDF lang ang puwede. ";
                $resident_id_file = null;
            }
        } else {
            $resident_id_file = $resident['resident_id_file'] ?? null;
        }

        $insertQuery = $conn->prepare("
            INSERT INTO profile_update_requests (
                user_id, resident_id, alias, suffix, resident_address, birth_place, street, citizenship,
                voter_status, employment_status, contact_number, religion, profession_occupation,
                educational_attainment, education_details, is_family_head, philsys_card_no,
                is_senior, is_pwd, is_4ps, is_solo_parent, resident_id_file, status
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Pending')
        ");
        $insertQuery->bind_param(
            "iisssssssssssiissiiiss",
            $user_id, $resident['resident_id'], $alias, $suffix, $resident_address, $birth_place, $street, $citizenship,
            $voter_status, $employment_status, $contact_number, $religion, $profession_occupation,
            $educational_attainment, $education_details, $is_family_head, $philsys_card_no,
            $is_senior, $is_pwd, $is_4ps, $is_solo_parent, $resident_id_file
        );

        if ($insertQuery->execute()) {
            $successMsg = "Na-submit na ang iyong request. Hintayin ang pag-apruba ng barangay staff bago ito maging opisyal.";
        } else {
            $errorMsg .= "Nabigo ang pag-submit ng request. Subukan muli sa susunod na linggo.";
        }
        $insertQuery->close();
    }
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Update Your Profile | Barangay System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<style>
:root { --theme-color: <?= htmlspecialchars($themeColor) ?>; }
.bg-theme { background-color: var(--theme-color); }
.text-theme { color: var(--theme-color); }
.bg-theme-gradient { background: linear-gradient(to right, var(--theme-color), #6366f1); }

header { font-family: 'Inter', sans-serif; }
header img { transition: transform 0.2s; }
header img:hover { transform: scale(1.05); }
button:hover { cursor: pointer; }

header .container { max-width: 1280px; }
header .dropdown { transition: all 0.3s ease-in-out; }
header .dropdown a:hover { background-color: #f3f4f6; }

#notifDropdown li:hover { background-color: #eef2ff; }
</style>
<header class="bg-theme-gradient text-white shadow-md sticky top-0 z-50">
  <div class="container mx-auto flex flex-col md:flex-row justify-between items-center p-4 space-y-2 md:space-y-0">
    <div class="flex items-center w-full md:w-auto justify-between md:justify-start space-x-4">
      <div class="flex items-center space-x-4">
        <img src="<?= !empty($settings['system_logo']) ? '../user/user_manage/uploads/' . htmlspecialchars(basename($settings['system_logo'])) : 'https://cdn-icons-png.flaticon.com/512/149/149071.png' ?>" 
     alt="Logo" 
     class="h-14 w-14 rounded-full border-2 border-white shadow-lg bg-white p-1">

        <div class="text-left">
          <h3 class="font-extrabold text-lg sm:text-2xl tracking-wide">
            <?= htmlspecialchars($settings['barangay_name'] ?? 'Barangay Name') ?>
          </h3>
          <p class="text-xs sm:text-sm text-white/80">
            <?= htmlspecialchars($settings['municipality'] ?? 'Municipality') ?>, <?= htmlspecialchars($settings['province'] ?? 'Province') ?>
          </p>
        </div>
      </div>
    </div>
    <div class="flex items-center space-x-4 md:space-x-6 w-full md:w-auto justify-end mt-2 md:mt-0">
      <a href="dashboard.php" class="bg-white/20 hover:bg-white/30 text-white font-semibold px-4 py-2 rounded-lg transition duration-200 flex items-center">
        <i class="fas fa-home mr-1"></i> Dashboard
      </a>
      <div class="relative">
        <button id="notifBell" class="relative p-2 rounded-full hover:bg-white/20 transition duration-200">
          <i class="fas fa-bell text-xl sm:text-2xl"></i>
          <?php if($unreadCount > 0): ?>
            <span class="absolute -top-1 -right-1 inline-flex items-center justify-center px-2 py-1 text-xs font-bold text-white bg-red-500 rounded-full shadow-md"><?= $unreadCount ?></span>
          <?php endif; ?>
        </button>
        <div id="notifDropdown" class="hidden absolute right-0 mt-2 w-full sm:w-80 bg-white shadow-2xl rounded-xl overflow-hidden z-50 max-h-96 overflow-y-auto dropdown">
          <?php if(count($notifications) > 0): ?>
            <ul>
              <?php foreach($notifications as $notif): ?>
                <li class="px-4 py-3 border-b <?= $notif['is_read'] ? 'bg-white' : 'bg-blue-50' ?>">
                  <span class="text-xs text-gray-400"><?= date("M d, Y H:i", strtotime($notif['date_created'])) ?></span>
                  <p class="text-sm text-gray-700 mt-1"><?= htmlspecialchars($notif['message']) ?></p>
                </li>
              <?php endforeach; ?>
            </ul>
            <a href="all_notifications.php" class="block text-center text-theme p-2 font-medium hover:underline">View All Notifications</a>
          <?php else: ?>
            <p class="p-4 text-gray-500 text-center">No notifications available</p>
          <?php endif; ?>
        </div>
      </div>
      <div class="relative">
        <button id="profileBtn" class="flex items-center space-x-2 p-2 rounded-full hover:bg-white/20 transition duration-200">
          <img src="<?= (!empty($resident['profile_pic']) && $resident['profile_pic'] != 'uploads/default.png') ? '../uploads/' . htmlspecialchars(basename($resident['profile_pic'])) : 'https://cdn-icons-png.flaticon.com/512/149/149071.png' ?>" 
               class="h-10 w-10 rounded-full object-cover border-2 border-white shadow-md">
          <i class="fas fa-caret-down"></i>
        </button>
        <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-full sm:w-52 bg-white shadow-2xl rounded-xl overflow-hidden z-50 dropdown">
          <a href="manage_profile.php" class="flex items-center px-4 py-2 hover:bg-gray-100 text-gray-700 font-medium">
            <i class="fas fa-user mr-3"></i> Edit Profile
          </a>
          <a href="account_settings.php" class="flex items-center px-4 py-2 hover:bg-gray-100 text-gray-700 font-medium">
            <i class="fas fa-cog mr-3"></i> Account Settings
          </a>
          <a href="../logout.php" class="flex items-center px-4 py-2 hover:bg-gray-100 text-gray-700 font-medium">
            <i class="fas fa-sign-out-alt mr-3"></i> Logout
          </a>
        </div>
      </div>

    </div>
  </div>
</header>
<main class="container mx-auto mt-6 bg-white rounded-xl shadow p-6">
        <?php if ($successMsg): ?>
            <div class="bg-green-100 text-green-800 p-3 rounded mb-4"><?= $successMsg ?></div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
            <div class="bg-red-100 text-red-800 p-3 rounded mb-4"><?= $errorMsg ?></div>
        <?php endif; ?>

        <h2 class="text-xl font-semibold mb-2">Update Your Profile</h2>

        <?php if ($is_approved != 1): ?>
            <div class="bg-red-100 text-red-800 p-3 rounded mb-4 text-sm">
                Your account is not verified yet. You cannot EDIT YOUR PROFILE.
            </div>
        <?php endif; ?>
        

        <div class="bg-yellow-100 text-yellow-800 p-3 rounded mb-4 text-sm">
            <p><strong>Important:</strong> Ang First Name, Last Name, Birthdate, Age, at Gender ay hindi puwedeng baguhin. 
            Ito ay dahil ang mga impormasyong ito ay ginagamit sa opisyal na records ng barangay at sa legal na dokumento. 
            Pagbabago ng mga ito sa system ay maaaring magdulot ng pagkakaiba sa identity records, maging sanhi ng error sa mga sertipiko, 
            at magresulta sa hindi pagkakatugma sa iba pang government databases. 
            Maaari lamang ang mga authorized personnel ng barangay ang gumawa ng ganitong pagbabago.</p>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <div class="border-b mb-4">
                <nav class="-mb-px flex space-x-4" id="pageTabs">
                    <button type="button" class="py-2 px-4 border-b-2 border-green-500 font-medium" data-tab="personalTab" <?= $is_approved != 1 ? 'disabled class="cursor-not-allowed opacity-50"' : '' ?>>Personal Info</button>
                    <button type="button" class="py-2 px-4 border-b-2 border-transparent font-medium hover:border-gray-300" data-tab="otherTab" <?= $is_approved != 1 ? 'disabled class="cursor-not-allowed opacity-50"' : '' ?>>Other Info</button>
                </nav>
            </div>

        <section id="personalTab">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div><label>First Name *</label><input type="text" value="<?= htmlspecialchars($resident['first_name']) ?>" class="w-full border-b-2 py-1.5" readonly></div>
                <div><label>Middle Name</label><input type="text" value="<?= htmlspecialchars($resident['middle_name']) ?>" class="w-full border-b-2 py-1.5" readonly></div>
                <div><label>Last Name *</label><input type="text" value="<?= htmlspecialchars($resident['last_name']) ?>" class="w-full border-b-2 py-1.5" readonly></div>
                <div><label>Alias</label><input type="text" name="alias" value="<?= htmlspecialchars($resident['alias'] ?? '') ?>" class="w-full border-b-2 py-1.5"></div>
                <div><label>Suffix</label><input type="text" name="suffix" value="<?= htmlspecialchars($resident['suffix'] ?? '') ?>" class="w-full border-b-2 py-1.5"></div>
                <div><label>Birthdate *</label><input type="date" value="<?= htmlspecialchars($resident['birthdate']) ?>" class="w-full border-b-2 py-1.5" readonly></div>
                <div><label>Age</label><input type="number" value="<?= htmlspecialchars($resident['age']) ?>" readonly class="w-full border-b-2 py-1.5"></div>
                <div><label>Gender *</label><select class="w-full border-b-2 py-1.5" disabled><option value="Male" <?= $resident['sex']=='Male'?'selected':'' ?>>Male</option><option value="Female" <?= $resident['sex']=='Female'?'selected':'' ?>>Female</option></select></div>
                <div><label>Civil Status *</label><select class="w-full border-b-2 py-1.5" disabled><?php foreach(['Single','Married','Widowed','Separated','Divorced'] as $status): ?><option value="<?= $status ?>" <?= $resident['civil_status']==$status?'selected':'' ?>><?= $status ?></option><?php endforeach; ?></select></div>
                <div><label>Resident Address *</label><input type="text" name="resident_address" value="<?= htmlspecialchars($resident['resident_address']) ?>" class="w-full border-b-2 py-1.5" required></div>
                <div><label>Birth Place</label><input type="text" name="birth_place" value="<?= htmlspecialchars($resident['birth_place']) ?>" class="w-full border-b-2 py-1.5"></div>
                <div><label>Street</label><input type="text" name="street" value="<?= htmlspecialchars($resident['street']) ?>" class="w-full border-b-2 py-1.5"></div>
                <div><label>Citizenship *</label><select name="citizenship" class="w-full border-b-2 py-1.5" required><option value="">Select</option><option value="Filipino" <?= $resident['citizenship']=='Filipino'?'selected':'' ?>>Filipino</option><option value="Non-Filipino" <?= $resident['citizenship']=='Non-Filipino'?'selected':'' ?>>Non-Filipino</option></select></div>
                <div><label>Voter Status *</label><select name="voter_status" class="w-full border-b-2 py-1.5" required><option value="Registered" <?= $resident['voter_status']=='Registered'?'selected':'' ?>>Registered</option><option value="Unregistered" <?= $resident['voter_status']=='Unregistered'?'selected':'' ?>>Unregistered</option></select></div>
            </div>
        </section>

       <section id="otherTab" class="hidden mt-3">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
            <label>Employment Status</label>
            <select name="employment_status" class="w-full border-b-2 py-1.5">
                <option value="">Select</option>
                <option value="Employed" <?= $resident['employment_status']=='Employed'?'selected':'' ?>>Employed</option>
                <option value="Unemployed" <?= $resident['employment_status']=='Unemployed'?'selected':'' ?>>Unemployed</option>
                <option value="OFW" <?= $resident['employment_status']=='OFW'?'selected':'' ?>>OFW</option>
            </select>
        </div>
        <div>
            <label>Contact No.</label>
            <input type="text" name="contact_number" value="<?= htmlspecialchars($resident['contact_number']) ?>" class="w-full border-b-2 py-1.5" maxlength="11">
        </div>
        <div>
            <label>Email Address</label>
            <input type="email" value="<?= htmlspecialchars($email) ?>" class="w-full border-b-2 py-1.5" readonly>
        </div>
        <div>
            <label>Religion</label>
            <input type="text" name="religion" value="<?= htmlspecialchars($resident['religion']) ?>" class="w-full border-b-2 py-1.5">
        </div>
        <div>
            <label>Occupation</label>
            <input type="text" name="profession_occupation" value="<?= htmlspecialchars($resident['profession_occupation']) ?>" class="w-full border-b-2 py-1.5">
        </div>
        <div>
            <label>Educational Attainment</label>
            <select name="educational_attainment" class="w-full border-b-2 py-1.5">
                <option value="">Select Level</option>
                <?php foreach(['Elementary','High School','College','Post Grad','Vocational'] as $level): ?>
                    <option value="<?= $level ?>" <?= $resident['educational_attainment']==$level?'selected':'' ?>><?= $level ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Status / Details</label>
            <select name="education_details" class="w-full border-b-2 py-1.5">
                <option value="">Select</option>
                <option value="Undergraduate" <?= $resident['education_details']=='Undergraduate'?'selected':'' ?>>Undergraduate</option>
                <option value="Graduate" <?= $resident['education_details']=='Graduate'?'selected':'' ?>>Graduate</option>
            </select>
        </div>
        <div class="flex items-center space-x-2 mt-2">
            <label class="font-medium">Head of the Family:</label>
            <input type="checkbox" name="is_family_head" <?= $resident['is_family_head']?'checked':'' ?>>
        </div>
        <div>
            <label>PhilSys Card No.</label>
            <input type="text" name="philsys_card_no" value="<?= htmlspecialchars($resident['philsys_card_no']) ?>" maxlength="12" class="w-full border-b-2 py-1.5">
        </div>
        <div class="flex gap-3 mt-2 md:col-span-2">
            <label>
                <input type="checkbox" name="is_senior" <?= $resident['is_senior']?'checked':'' ?> <?= ($resident['age'] < 60 ? 'disabled' : '') ?>>
                Senior
            </label>
            <label>
                <input type="checkbox" name="is_pwd" <?= $resident['is_pwd']?'checked':'' ?>>
                PWD
            </label>
            <label>
                <input type="checkbox" name="is_4ps" <?= $resident['is_4ps']?'checked':'' ?>>
                4Ps
            </label>
            <label>
                <input type="checkbox" name="is_solo_parent" <?= $resident['is_solo_parent']?'checked':'' ?>>
                Solo Parent
            </label>
        </div>
    </div>
</section>

        <div class="mt-6 flex justify-end">
            <button type="button" id="openSupportModal" class="bg-gray-400 text-white px-4 py-2 rounded cursor-not-allowed" disabled>Save Changes</button>
        </div>

    </form>
</main>

<div id="supportDocModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="bg-white rounded-xl p-6 w-96 relative shadow-2xl">
    <h3 class="text-xl font-bold mb-4">Upload Supporting Document</h3>
    <p class="text-gray-600 text-sm mb-4">Please upload a supporting document (JPG, PNG, or PDF) to proceed with your profile update request.</p>
    <form id="supportDocForm" enctype="multipart/form-data">
      <input type="file" id="supporting_doc" name="supporting_doc" accept=".jpg,.jpeg,.png,.pdf" class="mb-4 w-full">
      <p id="fileError" class="text-red-500 text-sm mb-2 hidden">Please upload a valid file.</p>
      <div class="flex justify-end gap-2">
        <button type="button" id="cancelUpload" class="px-4 py-2 rounded bg-gray-300 hover:bg-gray-400">Cancel</button>
        <button type="button" id="confirmUpload" class="px-4 py-2 rounded bg-green-500 text-white hover:bg-green-600">Upload & Submit</button>
      </div>
    </form>
  </div>
</div>

<script>
const tabs = document.querySelectorAll('#pageTabs button');
const personalTab = document.getElementById('personalTab');
const otherTab = document.getElementById('otherTab');

tabs.forEach(tab => {
    tab.addEventListener('click', () => {
        tabs.forEach(t => t.classList.remove('border-green-500'));
        tab.classList.add('border-green-500');

        if (tab.dataset.tab === 'personalTab') {
            personalTab.classList.remove('hidden');
            otherTab.classList.add('hidden');
        } else {
            personalTab.classList.add('hidden');
            otherTab.classList.remove('hidden');
        }
    });
});

const form = document.querySelector('form');
const saveBtn = document.getElementById('openSupportModal');
saveBtn.disabled = true;
saveBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
saveBtn.classList.remove('bg-green-500', 'hover:bg-green-600', 'cursor-pointer');

const originalData = {};
form.querySelectorAll('input:not([readonly]):not([disabled]), select:not([disabled])').forEach(input => {
    if (input.type === 'checkbox') {
        originalData[input.name] = input.checked;
    } else {
        originalData[input.name] = input.value;
    }
});

const checkFormChange = () => {
    let changed = false;
    form.querySelectorAll('input:not([readonly]):not([disabled]), select:not([disabled])').forEach(input => {
        if (input.type === 'checkbox') {
            if (input.checked !== originalData[input.name]) changed = true;
        } else if (input.value !== originalData[input.name]) {
            changed = true;
        }
    });

    if (changed) {
        saveBtn.disabled = false;
        saveBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
        saveBtn.classList.add('bg-green-500', 'hover:bg-green-600', 'cursor-pointer');
    } else {
        saveBtn.disabled = true;
        saveBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
        saveBtn.classList.remove('bg-green-500', 'hover:bg-green-600', 'cursor-pointer');
    }
};

form.querySelectorAll('input:not([readonly]):not([disabled]), select:not([disabled])').forEach(input => {
    input.addEventListener('input', checkFormChange);
    input.addEventListener('change', checkFormChange);
});

checkFormChange();

const supportModal = document.getElementById('supportDocModal');
const cancelBtn = document.getElementById('cancelUpload');
const confirmBtn = document.getElementById('confirmUpload');
const fileInput = document.getElementById('supporting_doc');
const fileError = document.getElementById('fileError');

saveBtn.addEventListener('click', () => {
    supportModal.classList.remove('hidden');
    supportModal.classList.add('flex');
});

cancelBtn.addEventListener('click', () => {
    supportModal.classList.remove('flex');
    supportModal.classList.add('hidden');
    fileError.classList.add('hidden');
});

confirmBtn.addEventListener('click', () => {
    const file = fileInput.files[0];
    if (!file) {
        fileError.textContent = 'Please upload a supporting document.';
        fileError.classList.remove('hidden');
        return;
    }

    const allowedTypes = ['image/jpeg','image/png','application/pdf'];
    if (!allowedTypes.includes(file.type)) {
        fileError.textContent = 'Invalid file type. JPG, PNG, or PDF only.';
        fileError.classList.remove('hidden');
        return;
    }

    fileError.classList.add('hidden');
    supportModal.classList.remove('flex');
    supportModal.classList.add('hidden');

    const hiddenFileInput = document.createElement('input');
    hiddenFileInput.type = 'file';
    hiddenFileInput.name = 'resident_id';
    hiddenFileInput.files = fileInput.files;
    hiddenFileInput.style.display = 'none';
    form.appendChild(hiddenFileInput);

    form.submit();
});
document.addEventListener('DOMContentLoaded', () => {
    const notifBell = document.getElementById('notifBell');
    const notifDropdown = document.getElementById('notifDropdown');
    const profileBtn = document.getElementById('profileBtn');
    const profileDropdown = document.getElementById('profileDropdown');

    notifBell?.addEventListener('click', e => { 
        e.stopPropagation(); 
        profileDropdown?.classList.add('hidden'); 
        notifDropdown.classList.toggle('hidden'); 
    });

    profileBtn?.addEventListener('click', e => { 
        e.stopPropagation(); 
        notifDropdown?.classList.add('hidden'); 
        profileDropdown.classList.toggle('hidden'); 
    });

    document.addEventListener('click', e => {
        if(!profileDropdown?.contains(e.target) && e.target !== profileBtn) profileDropdown?.classList.add('hidden');
        if(!notifDropdown?.contains(e.target) && e.target !== notifBell) notifDropdown?.classList.add('hidden');
    });
});
</script>
</body>
</html>
