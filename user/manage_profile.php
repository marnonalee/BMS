<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

include 'db.php';

$user_id = $_SESSION["user_id"];
$userQuery = $conn->query("SELECT * FROM users WHERE id = '$user_id'");
$user = $userQuery->fetch_assoc();
$role = $user['role'];

$resQuery = $conn->query("SELECT * FROM residents WHERE user_id = '$user_id'");
$resident = $resQuery->fetch_assoc();

$success = "";
$error = "";

if (isset($_POST['update_profile'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);

    $update = $conn->query("
        UPDATE users SET 
        username='$username',
        email='$email'
        WHERE id='$user_id'
    ");

    if ($update) {
        $success = "Profile updated successfully!";
        $user['username'] = $username;
        $user['email'] = $email;
    } else {
        $error = "Something went wrong!";
    }
}

if (isset($_POST['change_password'])) {
    $old = $_POST['old_password'];
    $new = $_POST['new_password'];

    if (!empty($old) && !empty($new)) {
        if (password_verify($old, $user['password'])) {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $conn->query("UPDATE users SET password='$hashed' WHERE id='$user_id'");
            $success = "Password updated successfully!";
        } else {
            $error = "Old password is incorrect!";
        }
    } else {
        $error = "Please fill out both password fields.";
    }
}
$settingsQuery = $conn->query("SELECT barangay_name, system_logo FROM system_settings LIMIT 1");
$settings = $settingsQuery->fetch_assoc();
$barangayName = $settings['barangay_name'] ?? 'Barangay Name';
$systemLogo = $settings['system_logo'] ?? 'default-logo.png';
$systemLogoPath = '' . $systemLogo;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title> Account Settings</title>
<link rel="stylesheet" href="a.css">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

</head>
<body class=" main-bg font-sans">

<div class="flex h-screen">
<aside id="sidebar" class="w-64 bg-gradient-to-b from-blue-500 to-blue-700 text-white flex flex-col shadow-xl transition-all duration-300 h-screen">
    <div class="flex items-center justify-between p-4 border-b border-white/20">
        <div class="flex items-center space-x-3"> <img src="<?= htmlspecialchars($systemLogo) ?>"
         alt="Barangay Logo"
         class="w-16 h-16 rounded-full object-cover shadow-sm border-2 border-white bg-white p-1 transition-all">
            <span class="font-semibold text-lg sidebar-text"><?= htmlspecialchars($barangayName) ?></span>
        </div>
        <button id="toggleSidebar" class="material-icons cursor-pointer text-2xl">chevron_left</button>
    </div>

  <nav class="flex-1 overflow-y-auto px-2 py-5 space-y-2">
    <a href="dashboard.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 transition-colors">
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
          <span class="material-icons mr-3">history</span><span class="sidebar-text">Activity Logs</span>
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

<div class="flex-1 flex flex-col">

<header class="flex items-center justify-between bg-white shadow p-6">
    <h2 class="text-2xl font-bold text-gray-700">Account Setting</h2>
</header>

<main class="p-8 overflow-y-auto">

<?php
if (isset($_SESSION['success'])) {
    echo '<p class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4">'.$_SESSION['success'].'</p>';
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    echo '<p class="bg-red-100 text-red-800 px-4 py-2 rounded mb-4">'.$_SESSION['error'].'</p>';
    unset($_SESSION['error']);
}
?>

<div class="max-w-6xl mx-auto">

    <div class="bg-white p-6 rounded-xl shadow mb-6 flex items-center space-x-4">
        <span class="material-icons text-6xl text-blue-600">account_circle</span>
        <div>
            <h2 class="text-2xl font-bold"><?= htmlspecialchars($user['username']) ?></h2>
            <p class="text-gray-600"><?= htmlspecialchars($user['email']) ?></p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow">
        <div class="border-b flex">
            <button id="tabProfile" class="flex-1 p-4 text-center font-semibold tab active">Profile Info</button>
            <button id="tabPassword" class="flex-1 p-4 text-center font-semibold tab">Change Password</button>
        </div>

        <div id="profileSection" class="p-6">
            <form action="update_profile.php" method="POST" class="space-y-4">
                <div>
                    <label class="block font-medium">Username</label>
                    <input type="text" id="usernameInput" name="username" value="<?= htmlspecialchars($user['username']) ?>" class="w-full p-3 border rounded-lg">
                    <p id="usernameFeedback" class="mt-1 text-sm"></p>
                </div>

                <div>
                    <label class="block font-medium">Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" class="w-full p-3 border rounded-lg" readonly>
                </div>

                <button 
                    id="saveProfileBtn" 
                    name="update_profile" 
                    class="mt-4 px-6 py-3 rounded-lg bg-blue-600 text-white disabled:bg-gray-400 disabled:cursor-not-allowed"
                    disabled
                >
                    Save Changes
                </button>
            </form>
        </div>

        <div id="passwordSection" class="p-6 hidden">
            <form action="update_password.php" method="POST" class="space-y-4">
                <div>
                    <label class="block font-medium">Current Password</label>
                    <input type="password" name="current_password" class="w-full p-3 border rounded-lg">
                </div>

                <div>
                    <label class="block font-medium">New Password</label>
                    <input type="password" name="new_password" class="w-full p-3 border rounded-lg">
                </div>
                <div id="password-requirements" class="password-requirements hidden">
                    <ul>
                        <li id="length" class="invalid">At least 8 characters</li>
                        <li id="uppercase" class="invalid">1 uppercase letter</li>
                        <li id="lowercase" class="invalid">1 lowercase letter</li>
                        <li id="number" class="invalid">1 number</li>
                        <li id="special" class="invalid">1 special character</li>
                    </ul>
                </div>

                <div>
                    <label class="block font-medium">Confirm Password</label>
                    <input type="password" name="confirm_password" class="w-full p-3 border rounded-lg">
                </div>

                <button 
                    id="updatePasswordBtn"
                    class="mt-4 px-6 py-3 rounded-lg bg-blue-600 text-white disabled:bg-gray-400 disabled:cursor-not-allowed" disabled>
                    Update Password
                </button>

            </form>
        </div>
    </div>

</div>



<script>
const tabProfile = document.getElementById('tabProfile');
const tabPassword = document.getElementById('tabPassword');
const profileSection = document.getElementById('profileSection');
const passwordSection = document.getElementById('passwordSection');

function activateTab(tab) {
    document.querySelectorAll('.tab').forEach(t => 
        t.classList.remove('text-blue-600', 'border-b-4', 'border-blue-600')
    );
    tab.classList.add('text-blue-600', 'border-b-4', 'border-blue-600');
}

tabProfile.onclick = () => {
    activateTab(tabProfile);
    profileSection.classList.remove('hidden');
    passwordSection.classList.add('hidden');
}

tabPassword.onclick = () => {
    activateTab(tabPassword);
    passwordSection.classList.remove('hidden');
    profileSection.classList.add('hidden');
}

activateTab(tabProfile);

</script>



</main>
</div>
</div>

<script>
const toggleSidebar = document.getElementById('toggleSidebar');
const sidebar = document.getElementById('sidebar');

toggleSidebar.addEventListener('click', () => {
    sidebar.classList.toggle('sidebar-collapsed');
    toggleSidebar.textContent = toggleSidebar.textContent === 'chevron_left' ? 'chevron_right' : 'chevron_left';
});

const currentPasswordInput = document.querySelector('input[name="current_password"]');
const newPasswordInput = document.querySelector('input[name="new_password"]');
const confirmPasswordInput = document.querySelector('input[name="confirm_password"]');
const updateButton = document.querySelector('#passwordSection button');
let passwordFeedback = document.createElement('p');
passwordFeedback.classList.add('mt-1','text-sm','transition-opacity','duration-500');
currentPasswordInput.parentNode.appendChild(passwordFeedback);
let fadeTimeout;

newPasswordInput.disabled = true;
confirmPasswordInput.disabled = true;
updateButton.disabled = true;

function checkUpdateButton() {
    if (!newPasswordInput.disabled && newPasswordInput.value && confirmPasswordInput.value && newPasswordInput.value === confirmPasswordInput.value) {
        updateButton.disabled = false;
    } else {
        updateButton.disabled = true;
    }
}

currentPasswordInput.addEventListener('input', () => {
    const password = currentPasswordInput.value.trim();
    if (password.length === 0) {
        passwordFeedback.textContent = '';
        passwordFeedback.style.opacity = 1;
        newPasswordInput.disabled = true;
        confirmPasswordInput.disabled = true;
        updateButton.disabled = true;
        return;
    }
    fetch('check_password.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body:'current_password='+encodeURIComponent(password)
    })
    .then(res=>res.json())
    .then(data=>{
        clearTimeout(fadeTimeout);
        if(data.status==='match'){
            passwordFeedback.textContent='Password is correct ✅';
            passwordFeedback.classList.remove('text-red-600');
            passwordFeedback.classList.add('text-green-600');
            newPasswordInput.disabled=false;
            confirmPasswordInput.disabled=false;
        }else{
            passwordFeedback.textContent='Password does not match ❌';
            passwordFeedback.classList.remove('text-green-600');
            passwordFeedback.classList.add('text-red-600');
            newPasswordInput.disabled=true;
            confirmPasswordInput.disabled=true;
            updateButton.disabled=true;
        }
        passwordFeedback.style.opacity=1;
        fadeTimeout=setTimeout(()=>{passwordFeedback.style.opacity=0;},3000);
    });
});

[newPasswordInput,confirmPasswordInput].forEach(input=>{input.addEventListener('input',checkUpdateButton);});

let requirements=document.getElementById('password-requirements');
let lengthReq=document.getElementById('length');
let uppercaseReq=document.getElementById('uppercase');
let lowercaseReq=document.getElementById('lowercase');
let numberReq=document.getElementById('number');
let specialReq=document.getElementById('special');

newPasswordInput.addEventListener('focus',()=>{requirements.classList.remove('hidden');});
newPasswordInput.addEventListener('blur',()=>{setTimeout(()=>{requirements.classList.add('hidden');},500);});

newPasswordInput.addEventListener('input',()=>{
    const val=newPasswordInput.value;
    lengthReq.classList.toggle('invalid',val.length<8);
    lengthReq.classList.toggle('valid',val.length>=8);
    uppercaseReq.classList.toggle('invalid',!/[A-Z]/.test(val));
    uppercaseReq.classList.toggle('valid',/[A-Z]/.test(val));
    lowercaseReq.classList.toggle('invalid',!/[a-z]/.test(val));
    lowercaseReq.classList.toggle('valid',/[a-z]/.test(val));
    numberReq.classList.toggle('invalid',!/[0-9]/.test(val));
    numberReq.classList.toggle('valid',/[0-9]/.test(val));
    specialReq.classList.toggle('invalid',!/[!@#$%^&*(),.?":{}|<>]/.test(val));
    specialReq.classList.toggle('valid',/[!@#$%^&*(),.?":{}|<>]/.test(val));
    checkUpdateButton();
});

const msg=document.getElementById('message');
if(msg){setTimeout(()=>{msg.style.opacity='0';setTimeout(()=>msg.remove(),1000);},3000);}

const usernameInput=document.getElementById('usernameInput');
const usernameFeedback=document.getElementById('usernameFeedback');
const saveProfileBtn=document.getElementById('saveProfileBtn');
saveProfileBtn.disabled=true;
let usernameTimeout;
const originalUsername=usernameInput.value;

usernameInput.addEventListener('input',()=>{
    const username=usernameInput.value.trim();
    if(username===originalUsername){usernameFeedback.textContent='';saveProfileBtn.disabled=true;return;}
    clearTimeout(usernameTimeout);
    usernameTimeout=setTimeout(()=>{
        fetch('check_username.php',{
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'username='+encodeURIComponent(username)
        })
        .then(res=>res.json())
        .then(data=>{
            if(data.status==='available'){
                usernameFeedback.textContent='Username is available ✅';
                usernameFeedback.classList.remove('text-red-600');
                usernameFeedback.classList.add('text-green-600');
                saveProfileBtn.disabled=false;
            }else{
                usernameFeedback.textContent='Username is already taken ❌';
                usernameFeedback.classList.remove('text-green-600');
                usernameFeedback.classList.add('text-red-600');
                saveProfileBtn.disabled=true;
            }
        });
    },500);
});
</script>




</body>
</html>
