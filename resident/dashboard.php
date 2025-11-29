<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "resident") {
    header("Location: ../index.php");
    exit();
}
include '../db.php';

$user_id = (int)$_SESSION["user_id"];
$username = $_SESSION["username"];

$settingsQuery = $conn->query("SELECT * FROM system_settings LIMIT 1");
$settings = $settingsQuery ? $settingsQuery->fetch_assoc() : [];
$themeColor = $settings['theme_color'] ?? '#4f46e5';

$residentIdQuery = $conn->prepare("SELECT resident_id, profile_completed, profile_photo FROM residents WHERE user_id = ? LIMIT 1");
$residentIdQuery->bind_param("i", $user_id);
$residentIdQuery->execute();
$residentResult = $residentIdQuery->get_result();
$resident = $residentResult->fetch_assoc();
$residentIdQuery->close();

$resident_id = $resident['resident_id'] ?? 0;
$profile_completed = $resident['profile_completed'] ?? 1;

$notifQuery = $conn->prepare("SELECT * FROM notifications WHERE resident_id = ? ORDER BY date_created DESC LIMIT 20");
$notifQuery->bind_param("i", $resident_id);
$notifQuery->execute();
$notifResult = $notifQuery->get_result();
$notifications = $notifResult->fetch_all(MYSQLI_ASSOC);
$notifQuery->close();

$unreadCount = 0;
foreach ($notifications as $notif) { 
    if ($notif['is_read'] == 0) $unreadCount++; 
}
if ($resident_id > 0) {
    $conn->query("UPDATE notifications SET is_read = 1 WHERE resident_id = $resident_id");
}
// Check if resident is a head of household
$householdQuery = $conn->prepare("SELECT * FROM households WHERE head_resident_id = ? LIMIT 1");
$householdQuery->bind_param("i", $resident_id);
$householdQuery->execute();
$householdResult = $householdQuery->get_result();
$isHeadOfHousehold = $householdResult->num_rows > 0;
$householdQuery->close();

$certQuery = $conn->query("SELECT * FROM certificate_templates WHERE template_for = 'certificate'");
$certificates = $certQuery ? $certQuery->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Resident Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<style>
:root { --theme-color: <?= htmlspecialchars($themeColor) ?>; }
.bg-theme { background-color: var(--theme-color); }
.text-theme { color: var(--theme-color); }
.bg-theme-gradient { background: linear-gradient(to right, var(--theme-color), #6366f1); }
</style>
</head>
<body class="bg-gray-100 font-sans">
<header class="bg-theme-gradient text-white shadow-md sticky top-0 z-50">
  <div class="container mx-auto flex flex-col md:flex-row justify-between items-center p-4 space-y-2 md:space-y-0">
    <!-- Logo & Barangay Info -->
    <div class="flex items-center w-full md:w-auto justify-between md:justify-start space-x-4">
      <div class="flex items-center space-x-4">
        <img src="<?= !empty($settings['system_logo']) ? '../user/user_manage/uploads/' . htmlspecialchars(basename($settings['system_logo'])) : 'https://cdn-icons-png.flaticon.com/512/149/149071.png' ?>" 
             alt="Logo" 
             class="h-12 w-12 rounded-full border-2 border-white shadow-sm">
        <div class="text-left">
          <h3 class="font-bold text-lg sm:text-xl tracking-wide"><?= htmlspecialchars($settings['barangay_name'] ?? 'Barangay Name') ?></h3>
          <p class="text-xs sm:text-sm text-white/80"><?= htmlspecialchars($settings['municipality'] ?? 'Municipality') ?>, <?= htmlspecialchars($settings['province'] ?? 'Province') ?></p>
        </div>
      </div>

      <!-- Language Toggle -->
      <div class="flex items-center space-x-2 mt-2 md:mt-0">
        <span class="text-white font-semibold">🌐</span>
        <button id="lang-en" class="px-2 py-1 rounded hover:bg-white/30 text-white text-xs sm:text-sm">English</button>
        <span class="text-white">|</span>
        <button id="lang-tl" class="px-2 py-1 rounded hover:bg-white/30 text-white text-xs sm:text-sm">Filipino</button>
      </div>
    </div>

    <!-- Notifications & Profile -->
    <div class="flex items-center space-x-4 md:space-x-6 w-full md:w-auto justify-end mt-2 md:mt-0">
      <!-- Notifications -->
      <div class="relative">
        <button id="notifBell" class="relative p-2 rounded-full hover:bg-white/20">
          <i class="fas fa-bell text-xl sm:text-2xl"></i>
          <?php if($unreadCount > 0): ?>
            <span class="absolute -top-1 -right-1 inline-flex items-center justify-center px-2 py-1 text-xs font-bold text-white bg-red-500 rounded-full"><?= $unreadCount ?></span>
          <?php endif; ?>
        </button>
        <div id="notifDropdown" class="hidden absolute right-0 sm:right-0 mt-2 w-full sm:w-80 bg-white shadow-xl rounded-xl overflow-hidden z-50 max-h-96 overflow-y-auto">
          <?php if(count($notifications) > 0): ?>
            <ul>
              <?php foreach($notifications as $notif): ?>
                <li class="px-4 py-3 border-b <?= $notif['is_read'] ? 'bg-white' : 'bg-blue-50' ?>">
                  <span class="text-xs text-gray-400"><?= date("M d, Y H:i", strtotime($notif['date_created'])) ?></span>
                  <p class="text-sm text-gray-700 mt-1"><?= htmlspecialchars($notif['message']) ?></p>
                </li>
              <?php endforeach; ?>
            </ul>
            <a href="all_notifications.php" class="block text-center text-theme p-2 font-medium" data-i18n="view_all_notif">View All Notifications</a>
          <?php else: ?>
            <p class="p-4 text-gray-500 text-center" data-i18n="no_notif">No notifications available</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Profile Dropdown -->
      <div class="relative">
        <button id="profileBtn" class="flex items-center space-x-2 p-2 rounded-full hover:bg-white/20">
          <img src="<?= (!empty($resident['profile_pic']) && $resident['profile_pic'] != 'uploads/default.png') ? '../uploads/' . htmlspecialchars(basename($resident['profile_pic'])) : 'https://cdn-icons-png.flaticon.com/512/149/149071.png' ?>" 
               class="h-10 w-10 rounded-full object-cover border-2 border-white shadow-sm">
          <i class="fas fa-caret-down"></i>
        </button>
        <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-full sm:w-52 bg-white shadow-xl rounded-xl overflow-hidden z-50">
          <a href="manage_profile.php" class="flex items-center px-4 py-2 hover:bg-gray-100 text-gray-700" data-i18n="edit_profile">
            <i class="fas fa-user mr-3"></i> Edit Profile
          </a>
          <a href="account_settings.php" class="flex items-center px-4 py-2 hover:bg-gray-100 text-gray-700" data-i18n="account_settings">
            <i class="fas fa-cog mr-3"></i> Account Settings
          </a>
          <a href="../logout.php" class="flex items-center px-4 py-2 hover:bg-gray-100 text-gray-700" data-i18n="logout">
            <i class="fas fa-sign-out-alt mr-3"></i> Logout
          </a>
        </div>
      </div>
    </div>
  </div>
</header>

<main class="flex justify-center mt-10 px-4">
  <div class="bg-white rounded-3xl shadow-2xl p-6 sm:p-10 w-full max-w-6xl text-center relative overflow-hidden">

    <h2 class="text-2xl sm:text-3xl font-bold mb-4 text-gray-800" data-i18n="welcome">Welcome, <?= htmlspecialchars($username) ?>!</h2>
    <p class="text-gray-600 mb-10 text-sm sm:text-base" data-i18n="dashboard_intro">Your resident dashboard. Request certificates, file complaints, and manage your profile easily.</p>

  <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">

  <div class="bg-gradient-to-tr from-blue-50 to-white rounded-xl p-4 sm:p-6 shadow-lg hover:-translate-y-1 transition text-center">
    <i class="fas fa-file-signature text-3xl sm:text-4xl text-blue-500 mb-3"></i>
    <h3 class="font-semibold text-gray-800 mb-2 text-sm sm:text-base" data-i18n="request_doc">Request Document</h3>
    <p class="text-gray-600 text-xs sm:text-sm mb-3" data-i18n="request_doc_desc">Request certificates or your Barangay ID.</p>
    <button id="requestCertificateBtn" class="mt-2 sm:mt-3 px-4 py-2 w-full sm:w-auto bg-theme-gradient text-white rounded-lg" data-i18n="request_now">Request Now</button>
  </div>

  <div class="bg-gradient-to-tr from-green-50 to-white rounded-xl p-4 sm:p-6 shadow-lg hover:-translate-y-1 transition text-center">
    <i class="fas fa-exclamation-circle text-3xl sm:text-4xl text-green-500 mb-3"></i>
    <h3 class="font-semibold text-gray-800 mb-2 text-sm sm:text-base" data-i18n="file_complaint">File Complaint</h3>
    <p class="text-gray-600 text-xs sm:text-sm mb-3" data-i18n="file_complaint_desc">File your complaint and track it.</p>
    <a href="complaint/complaint_blotter.php" class="mt-2 sm:mt-3 px-4 py-2 w-full sm:w-auto bg-green-500 text-white rounded-lg" data-i18n="file_now">File Now</a>
  </div>
<div class="bg-gradient-to-tr from-purple-50 to-white rounded-xl p-4 sm:p-6 shadow-lg hover:-translate-y-1 transition text-center">
  <i class="fas fa-users text-3xl sm:text-4xl text-purple-500 mb-3"></i>
  <h3 class="font-semibold text-gray-800 mb-2 text-sm sm:text-base" data-i18n="add_household_member">Add Household Member</h3>
  <p class="text-gray-600 text-xs sm:text-sm mb-3" data-i18n="add_household_member_desc">Add a new member to your household.</p>
  
  <?php if($isHeadOfHousehold): ?>
    <a href="add_family_member_request.php" class="mt-2 sm:mt-3 px-4 py-2 w-full sm:w-auto bg-purple-500 text-white rounded-lg" data-i18n="add_member_btn">Add Member</a>
  <?php else: ?>
    <button class="mt-2 sm:mt-3 px-4 py-2 w-full sm:w-auto bg-gray-300 text-white rounded-lg cursor-not-allowed" disabled data-i18n="add_member_btn_disabled">Only household head can add</button>
  <?php endif; ?>
</div>

</div>


    <div class="mt-8 sm:mt-12 text-left">
      <h2 class="text-xl sm:text-2xl font-bold mb-4 text-gray-800" data-i18n="my_requests">My Requests</h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">

        <a href="certificate/all_requests.php" class="block bg-gradient-to-tr from-purple-50 to-white p-4 sm:p-6 rounded-xl shadow-lg hover:-translate-y-1 transition text-center">
          <i class="fas fa-file-alt text-3xl sm:text-4xl text-purple-500 mb-3"></i>
          <h3 class="font-semibold text-gray-800 mb-2 text-sm sm:text-base" data-i18n="certificate_requests">Certificate Requests</h3>
          <p class="text-gray-600 text-xs sm:text-sm" data-i18n="certificate_requests_desc">View your certificate requests.</p>
          <span class="mt-2 sm:mt-3 inline-block px-4 py-2 w-full sm:w-auto bg-purple-500 text-white rounded-lg" data-i18n="view_requests">View Requests</span>
        </a>

        <a href="complaint/complaint_list.php" class="block bg-gradient-to-tr from-yellow-50 to-white p-4 sm:p-6 rounded-xl shadow-lg hover:-translate-y-1 transition text-center">
          <i class="fas fa-exclamation-circle text-3xl sm:text-4xl text-yellow-500 mb-3"></i>
          <h3 class="font-semibold text-gray-800 mb-2 text-sm sm:text-base" data-i18n="complaint_history">Complaint History</h3>
          <p class="text-gray-600 text-xs sm:text-sm" data-i18n="complaint_history_desc">Track your complaint updates.</p>
          <span class="mt-2 sm:mt-3 inline-block px-4 py-2 w-full sm:w-auto bg-yellow-400 text-white rounded-lg" data-i18n="view_complaints">View Complaints</span>
        </a>

      </div>
    </div>

  </div>
</main>


<!-- Certificate Modal -->
<div id="certificateModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
  <div class="bg-white rounded-3xl shadow-2xl w-full max-w-6xl relative">
    <div class="flex justify-between items-center border-b px-6 py-4">
      <h2 class="text-2xl font-bold text-gray-800" data-i18n="request_doc_modal">Request Document</h2>
      <button id="closeModal" class="text-gray-500 hover:text-gray-800 text-3xl font-bold">&times;</button>
    </div>
    <div class="p-6 max-h-[70vh] overflow-y-auto">
      <h3 class="text-lg font-semibold text-gray-700 mb-4" data-i18n="certificates">Certificates</h3>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <?php foreach($certificates as $cert): ?>
            <div class="bg-white rounded-xl shadow p-5 text-center border">
              <i class="fas fa-award text-4xl text-yellow-500 mb-3"></i>
              <h4 class="text-gray-800 font-semibold mb-2"><?= htmlspecialchars($cert['template_name']) ?></h4>
              <p class="text-gray-600 text-sm mb-4" data-i18n="cert_desc">Request this certificate easily.</p>
              <button 
                class="px-4 py-2 bg-theme-gradient text-white rounded-lg requestAction"
                data-url="certificate/request_certificate.php?template_id=<?= $cert['id'] ?>"
                data-name="<?= htmlspecialchars($cert['template_name']) ?>"
                data-i18n="request_now_modal">
                Request Now
              </button>
            </div>
        <?php endforeach; ?>
      </div>

      <h3 class="text-lg font-semibold text-gray-700 mb-4" data-i18n="barangay_id">Barangay ID</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="bg-white rounded-xl shadow p-5 text-center border">
            <i class="fas fa-id-card text-4xl text-red-500 mb-3"></i>
            <h4 class="text-gray-800 font-semibold mb-2" data-i18n="request_barangay_id">Request Barangay ID</h4>
            <p class="text-gray-600 text-sm mb-4" data-i18n="request_barangay_id_desc">Apply for your official Barangay ID.</p>
            <button 
              class="px-4 py-2 bg-red-500 text-white rounded-lg requestAction"
              data-url="request_barangay_id.php"
              data-name="Barangay ID"
              data-i18n="request_now_modal">
              Request Now
            </button>
          </div>
      </div>

    </div>
  </div>
</div>

<!-- Confirm Modal -->
<div id="confirmModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-[9999] p-4">
  <div class="bg-white w-full max-w-md rounded-2xl shadow-xl p-6 text-center">
    <h2 class="text-xl font-bold text-gray-800" id="confirmTitle"></h2>
    <p class="text-gray-600 mt-2 mb-6" data-i18n="confirm_modal_text">Do you want to continue?</p>
    <div class="flex justify-center gap-4">
        <button id="cancelConfirm" class="px-5 py-2 bg-gray-300 rounded-lg hover:bg-gray-400" data-i18n="cancel_btn">Cancel</button>
        <button id="confirmProceed" class="px-5 py-2 bg-theme-gradient text-white rounded-lg" data-i18n="confirm_btn">Confirm</button>
    </div>
  </div>
</div>

<script>
// Notifications & Profile dropdown
const modal = document.getElementById('certificateModal');
const btn = document.getElementById('requestCertificateBtn');
const closeBtn = document.getElementById('closeModal');

btn.addEventListener('click', () => { modal.classList.remove('hidden'); modal.classList.add('flex'); });
closeBtn.addEventListener('click', () => { modal.classList.add('hidden'); modal.classList.remove('flex'); });
window.addEventListener('click', e => { if(e.target === modal){ modal.classList.add('hidden'); modal.classList.remove('flex'); } });

const notifBell = document.getElementById('notifBell');
const notifDropdown = document.getElementById('notifDropdown');
notifBell.addEventListener('click', e => { e.stopPropagation(); notifDropdown.classList.toggle('hidden'); });

const profileBtn = document.getElementById('profileBtn');
const profileDropdown = document.getElementById('profileDropdown');
profileBtn.addEventListener('click', e => { e.stopPropagation(); profileDropdown.classList.toggle('hidden'); });

document.addEventListener('click', e => {
    if(!profileDropdown.contains(e.target) && e.target !== profileBtn){ profileDropdown.classList.add('hidden'); }
    if(!notifDropdown.contains(e.target) && e.target !== notifBell){ notifDropdown.classList.add('hidden'); }
});

// Certificate request confirm
let selectedUrl = "";
const confirmModal = document.getElementById("confirmModal");
const confirmTitle = document.getElementById("confirmTitle");
const cancelConfirm = document.getElementById("cancelConfirm");
const confirmProceed = document.getElementById("confirmProceed");

document.querySelectorAll(".requestAction").forEach(btn=>{
    btn.addEventListener("click", ()=>{
        selectedUrl = btn.dataset.url;
        confirmTitle.textContent = "Request " + btn.dataset.name;
        confirmModal.classList.remove("hidden");
        confirmModal.classList.add("flex");
    });
});

cancelConfirm.addEventListener("click", ()=>{
    confirmModal.classList.add("hidden");
    confirmModal.classList.remove("flex");
});

confirmProceed.addEventListener("click", ()=>{
    window.location.href = selectedUrl;
});

const translations = {
  "en": {
    "welcome": "Welcome, <?= htmlspecialchars($username) ?>!",
    "dashboard_intro": "Your resident dashboard. Request certificates, file complaints, and manage your profile easily.",
    "request_doc": "Request Document",
    "request_doc_desc": "Request certificates or your Barangay ID.",
    "request_now": "Request Now",
    "file_complaint": "File Complaint",
    "file_complaint_desc": "File your complaint and track it.",
    "file_now": "File Now",
    "manage_profile": "Manage Profile",
    "manage_profile_desc": "Update your personal details.",
    "edit_profile_btn": "Edit Profile",
    "my_requests": "My Requests",
    "certificate_requests": "Certificate Requests",
    "certificate_requests_desc": "View your certificate requests.",
    "view_requests": "View Requests",
    "complaint_history": "Complaint History",
    "complaint_history_desc": "Track your complaint updates.",
    "view_complaints": "View Complaints",
    "request_doc_modal": "Request Document",
    "certificates": "Certificates",
    "cert_desc": "Request this certificate easily.",
    "barangay_id": "Barangay ID",
    "request_barangay_id": "Request Barangay ID",
    "request_barangay_id_desc": "Apply for your official Barangay ID.",
    "confirm_modal_text": "Do you want to continue?",
    "cancel_btn": "Cancel",
    "confirm_btn": "Confirm",
    "view_all_notif": "View All Notifications",
    "no_notif": "No notifications available",
    "edit_profile": "Edit Profile",
    "account_settings": "Account Settings",
    "logout": "Logout"
  },
  "tl": {
    "welcome": "Kamusta, <?= htmlspecialchars($username) ?>!",
    "dashboard_intro": "Dito mo makikita ang iyong dashboard. Madali kang makakakuha ng sertipiko, makapaghain ng reklamo, at maayos ang iyong profile.",
    "request_doc": "Humiling ng Dokumento",
    "request_doc_desc": "Humiling ng sertipiko o Barangay ID nang mabilis at madali.",
    "request_now": "Humiling Ngayon",
    "file_complaint": "Maghain ng Reklamo",
    "file_complaint_desc": "Maghain ng reklamo at subaybayan ang progreso nito.",
    "file_now": "Ihain Ngayon",
    "manage_profile": "Ayusin ang Profile",
    "manage_profile_desc": "I-update ang iyong personal na impormasyon.",
    "edit_profile_btn": "I-edit ang Profile",
    "my_requests": "Aking Mga Kahilingan",
    "certificate_requests": "Mga Kahilingan ng Sertipiko",
    "certificate_requests_desc": "Tingnan ang mga sertipikong iyong hiningi.",
    "view_requests": "Tingnan",
    "complaint_history": "Kasaysayan ng Reklamo",
    "complaint_history_desc": "Subaybayan ang progreso ng iyong reklamo.",
    "view_complaints": "Tingnan",
    "request_doc_modal": "Humiling ng Dokumento",
    "certificates": "Mga Sertipiko",
    "cert_desc": "Madali kang makakakuha ng sertipikong ito.",
    "barangay_id": "Barangay ID",
    "request_barangay_id": "Humiling ng Barangay ID",
    "request_barangay_id_desc": "Mag-apply para sa iyong opisyal na Barangay ID.",
    "confirm_modal_text": "Sigurado ka ba na gusto mong magpatuloy?",
    "cancel_btn": "Kanselahin",
    "confirm_btn": "Kumpirmahin",
    "view_all_notif": "Tingnan Lahat ng Notipikasyon",
    "no_notif": "Wala pang notipikasyon",
    "edit_profile": "I-edit ang Profile",
    "account_settings": "Mga Setting ng Account",
    "logout": "Mag-logout"
  }
};

function translate(lang){
  document.querySelectorAll('[data-i18n]').forEach(el=>{
    const key = el.dataset.i18n;
    if(translations[lang][key]) el.textContent = translations[lang][key];
  });
}

document.getElementById('lang-en').addEventListener('click', ()=>translate('en'));
document.getElementById('lang-tl').addEventListener('click', ()=>translate('tl'));
</script>

</body>
</html>
