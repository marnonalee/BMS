<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "resident") {
    header("Location: ../index.php");
    exit();
}
include '../db.php';
$user_id = $_SESSION["user_id"];
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

$userQuery = $conn->prepare("SELECT email, password, profile_pic, is_approved FROM users WHERE id = ?");
$userQuery->bind_param("i", $user_id);
$userQuery->execute();
$userData = $userQuery->get_result()->fetch_assoc();
$userQuery->close();

$email = $userData['email'] ?? '';
$profile_pic = $userData['profile_pic'] ?? 'uploads/default.png';
$is_approved = $userData['is_approved'] ?? 0;

$successMsg = '';
$errorMsg = '';

if ($is_approved != 1) {
    $errorMsg = "Your account is not verified yet. You cannot edit your account settings.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_approved == 1) {
    $new_email = $_POST['email'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Handle profile pic upload
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
        $allowed = ['jpg','jpeg','png'];
        $fileName = $_FILES['profile_pic']['name'];
        $fileTmp = $_FILES['profile_pic']['tmp_name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (in_array($fileExt, $allowed)) {
            $newFileName = 'profile_'.$user_id.'_'.time().'.'.$fileExt;
            $uploadDir = '../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            if (move_uploaded_file($fileTmp, $uploadDir.$newFileName)) {
                $profile_pic = 'uploads/'.$newFileName;
            } else {
                $errorMsg .= "Failed to upload profile picture. ";
            }
        } else {
            $errorMsg .= "Invalid profile picture type. Only JPG, PNG allowed. ";
        }
    }

    if ($current_password && password_verify($current_password, $userData['password'])) {
        if ($new_password && $new_password !== $confirm_password) {
            $errorMsg .= "New password and confirmation do not match.";
        } else {
            $hashed_password = $new_password ? password_hash($new_password, PASSWORD_DEFAULT) : $userData['password'];
            $updateQuery = $conn->prepare("UPDATE users SET email=?, password=?, profile_pic=? WHERE id=?");
            $updateQuery->bind_param("sssi", $new_email, $hashed_password, $profile_pic, $user_id);
            if ($updateQuery->execute()) {
                $successMsg = "Account settings updated successfully.";
                $email = $new_email;
            } else {
                $errorMsg .= "Failed to update account settings.";
            }
            $updateQuery->close();
        }
    } else {
        $errorMsg .= "Current password is incorrect.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Account Settings | Barangay System</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen font-sans">
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
<main class="container mx-auto mt-8 max-w-4xl">

    <div class="mb-6 flex items-center space-x-2 text-gray-600 text-sm">
        <a href="dashboard.php" class="hover:underline">Dashboard</a>
        <span class="text-gray-400"> | </span>
        <span class="font-semibold text-gray-800">Account Settings</span>
    </div>

    <?php if ($successMsg): ?>
        <div class="bg-green-100 text-green-800 p-4 rounded-lg mb-6 shadow-sm"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="bg-red-100 text-red-800 p-4 rounded-lg mb-6 shadow-sm"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow-lg p-6">
        <form method="POST" id="accountForm" enctype="multipart/form-data" class="space-y-5">
            <div>
                <label class="font-semibold block mb-1">Email Address</label>
                <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required class="w-full border-b-2 border-gray-300 py-2 px-1 focus:outline-none focus:border-blue-500 transition" <?= $is_approved != 1 ? 'disabled' : '' ?>>
            </div>

            <div>
                <label class="font-semibold block mb-1">Current Password</label>
                <input type="password" name="current_password" placeholder="Enter current password" required class="w-full border-b-2 border-gray-300 py-2 px-1 focus:outline-none focus:border-blue-500 transition" <?= $is_approved != 1 ? 'disabled' : '' ?>>
            </div>

            <div>
                <label class="font-semibold block mb-1">New Password</label>
                <input type="password" name="new_password" placeholder="Enter new password (optional)" class="w-full border-b-2 border-gray-300 py-2 px-1 focus:outline-none focus:border-blue-500 transition" <?= $is_approved != 1 ? 'disabled' : '' ?>>
            </div>

            <div>
                <label class="font-semibold block mb-1">Confirm New Password</label>
                <input type="password" name="confirm_password" placeholder="Confirm new password" class="w-full border-b-2 border-gray-300 py-2 px-1 focus:outline-none focus:border-blue-500 transition" <?= $is_approved != 1 ? 'disabled' : '' ?>>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-green-500 text-white px-6 py-2 rounded-lg hover:bg-green-600 transition font-semibold" <?= $is_approved != 1 ? 'disabled class="bg-gray-400 cursor-not-allowed"' : '' ?>>
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</main>
<script>
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
