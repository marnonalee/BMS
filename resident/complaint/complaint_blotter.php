<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "resident") {
    header("Location: ../../index.php");
    exit();
}

include '../../db.php';

$success = "";
$error = "";
$user_id = (int)$_SESSION["user_id"];
$username = $_SESSION["username"];

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

$is_blocked = false;
if ($is_approved != 1) {
    $error = "Your account is not verified yet. You cannot submit a complaint.";
    $is_blocked = true;
}

$residentQuery = $conn->prepare("
    SELECT resident_id,
           CONCAT(first_name, ' ', COALESCE(middle_name,''), ' ', last_name) AS full_name,
           contact_number,
           resident_address
    FROM residents
    WHERE user_id = ?
");
$residentQuery->bind_param("i", $user_id);
$residentQuery->execute();
$residentResult = $residentQuery->get_result();

if ($residentResult->num_rows > 0) {
    $residentRow = $residentResult->fetch_assoc();
    $resident_id = $residentRow['resident_id'];
    $resident_name = trim($residentRow['full_name']);
    $resident_contact = $residentRow['contact_number'];
    $resident_address = $residentRow['resident_address'];
} else {
    die("No matching resident record found for this user.");
}
$residentQuery->close();

function sendNotification($conn, $resident_id, $message, $title = "Notification", $from_role = "system", $type = "general", $priority = "normal", $action_type = "created") {
    $stmt = $conn->prepare("INSERT INTO notifications (resident_id, message, from_role, title, type, priority, action_type, is_read, sent_email, date_created) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, NOW())");
    $stmt->bind_param("issssss", $resident_id, $message, $from_role, $title, $type, $priority, $action_type);
    $stmt->execute();
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$is_blocked) {

    $victim_name = trim($_POST["victim_name"]);
    $suspect_name = trim($_POST["suspect_name"]);
    $incident_location = trim($_POST["incident_location"]);
    $incident_datetime = trim($_POST["incident_datetime"]);
    $incident_nature = trim($_POST["incident_nature"]);
    $victim_statement = trim($_POST["victim_statement"]);
    $suspect_description = trim($_POST["suspect_description"]);
    $complainant_relation = trim($_POST["complainant_relation"]);
    $suspect_address = trim($_POST["suspect_address"]);

    if (empty($victim_name) || empty($incident_nature) || empty($incident_datetime)) {
        $error = "Please fill in all required fields.";
    } else {

        $stmt = $conn->prepare("
            INSERT INTO blotter_records 
                (complainant_id, complainant_name, complainant_contact, complainant_address,
                 complainant_relation, victim_name, victim_statement, suspect_name, 
                 suspect_description, suspect_address, incident_datetime, incident_location, 
                 incident_nature, reporting_officer, status, created_at) 
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())
        ");

        $reporting_officer = "Online Submission";

        $stmt->bind_param(
            "isssssssssssss",
            $resident_id, $resident_name, $resident_contact, $resident_address,
            $complainant_relation, $victim_name, $victim_statement, $suspect_name,
            $suspect_description, $suspect_address, $incident_datetime,
            $incident_location, $incident_nature, $reporting_officer
        );

        if ($stmt->execute()) {

            $blotter_id = $stmt->insert_id;
            $success = "Your complaint has been submitted successfully!";

            $staffQuery = $conn->query("SELECT id FROM users WHERE role IN ('staff','admin')");

            while ($staff = $staffQuery->fetch_assoc()) {
                $staff_user_id = $staff['id'];
                $message = "New blotter submitted by $resident_name";
                $title = "New Blotter Report";
                $type = "blotter";
                $priority = "high";
                $action_type = "created";
                sendNotification($conn, $staff_user_id, $message, $title, "resident", $type, $priority, $action_type);
            }

        } else {
            $error = "Something went wrong while submitting your complaint.";
        }

        $stmt->close();
    }
}

?>



<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>File Complaint</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>

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
</head>
<body class="bg-gray-100 font-sans">
    <header class="bg-theme-gradient text-white shadow-md sticky top-0 z-50">
  <div class="container mx-auto flex flex-col md:flex-row justify-between items-center p-4 space-y-2 md:space-y-0">

    <!-- Logo & Barangay Info -->
    <div class="flex items-center w-full md:w-auto justify-between md:justify-start space-x-4">
      <div class="flex items-center space-x-4">
        <img src="<?= !empty($settings['system_logo']) ? '../../user/user_manage/uploads/' . htmlspecialchars(basename($settings['system_logo'])) : 'https://cdn-icons-png.flaticon.com/512/149/149071.png' ?>" 
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

    <!-- Navigation & Profile -->
    <div class="flex items-center space-x-4 md:space-x-6 w-full md:w-auto justify-end mt-2 md:mt-0">

      <!-- Dashboard Button -->
      <a href="../dashboard.php" class="bg-white/20 hover:bg-white/30 text-white font-semibold px-4 py-2 rounded-lg transition duration-200 flex items-center">
        <i class="fas fa-home mr-1"></i> Dashboard
      </a>

      <!-- Notifications -->
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

      <!-- Profile Dropdown -->
      <div class="relative">
        <button id="profileBtn" class="flex items-center space-x-2 p-2 rounded-full hover:bg-white/20 transition duration-200">
          <img src="<?= (!empty($resident['profile_pic']) && $resident['profile_pic'] != 'uploads/default.png') ? '../uploads/' . htmlspecialchars(basename($resident['profile_pic'])) : 'https://cdn-icons-png.flaticon.com/512/149/149071.png' ?>" 
               class="h-10 w-10 rounded-full object-cover border-2 border-white shadow-md">
          <i class="fas fa-caret-down"></i>
        </button>
        <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-full sm:w-52 bg-white shadow-2xl rounded-xl overflow-hidden z-50 dropdown">
          <a href="../manage_profile.php" class="flex items-center px-4 py-2 hover:bg-gray-100 text-gray-700 font-medium">
            <i class="fas fa-user mr-3"></i> Edit Profile
          </a>
          <a href="../account_settings.php" class="flex items-center px-4 py-2 hover:bg-gray-100 text-gray-700 font-medium">
            <i class="fas fa-cog mr-3"></i> Account Settings
          </a>
          <a href="../../logout.php" class="flex items-center px-4 py-2 hover:bg-gray-100 text-gray-700 font-medium">
            <i class="fas fa-sign-out-alt mr-3"></i> Logout
          </a>
        </div>
      </div>

    </div>
  </div>
</header>

<main class="container mx-auto mt-8 max-w-4xl">

    <div class="mb-6 flex items-center space-x-2 text-gray-600 text-sm">
        <a href="../dashboard.php" class="hover:underline">Dashboard</a>
        <span class="text-gray-400"> | </span>
        <span class="font-semibold text-gray-800">File Complaint</span>
    </div>

    <?php if(!empty($success)): ?>
        <div class="bg-green-100 text-green-800 p-4 rounded-lg mb-6 shadow-sm"><?= htmlspecialchars($success) ?></div>
    <?php elseif(!empty($error)): ?>
        <div class="bg-red-100 text-red-800 p-4 rounded-lg mb-6 shadow-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow-lg p-6">

        <form method="POST" class="space-y-5">
            <div>
                <label class="font-semibold block mb-1">Pangalan ng Nagrereklamo</label>
                <input type="text" value="<?= htmlspecialchars($resident_name) ?>" readonly class="w-full border p-4 rounded-lg bg-gray-100">
            </div>

            <div>
                <label class="font-semibold block mb-1">Contact ng Nagrereklamo</label>
                <input type="text" value="<?= htmlspecialchars($resident_contact) ?>" readonly class="w-full border p-4 rounded-lg bg-gray-100">
            </div>

            <div>
                <label class="font-semibold block mb-1">Address ng Nagrereklamo</label>
                <input type="text" value="<?= htmlspecialchars($resident_address) ?>" readonly class="w-full border p-4 rounded-lg bg-gray-100">
            </div>

            <div>
                <label class="font-semibold block mb-1 required">Relasyon sa Biktima</label>
                <input type="text" name="complainant_relation" placeholder="Sarili, Kamag-anak, Kapitbahay" required class="w-full border p-4 rounded-lg">
            </div>

            <div>
                <label class="font-semibold block mb-1 required">Pangalan ng Biktima</label>
                <input type="text" name="victim_name" placeholder="Ilagay ang pangalan ng biktima" required class="w-full border p-4 rounded-lg">
            </div>

            <div>
                <label class="font-semibold block mb-1 required">Pahayag ng Biktima / Buod ng Insidente</label>
                <textarea name="victim_statement" placeholder="Ilarawan ang insidente nang maikli..." required class="w-full border p-4 rounded-lg h-36 resize-none"></textarea>
            </div>

            <div>
                <label class="font-semibold block mb-1">Pangalan ng Suspek</label>
                <input type="text" name="suspect_name" placeholder="Ilagay ang pangalan ng suspek" class="w-full border p-4 rounded-lg">
            </div>

            <div>
                <label class="font-semibold block mb-1">Paglalarawan ng Suspek</label>
                <textarea name="suspect_description" placeholder="Mga nakikilalang katangian" class="w-full border p-4 rounded-lg h-28 resize-none"></textarea>
            </div>

            <div>
                <label class="font-semibold block mb-1">Address ng Suspek</label>
                <input type="text" name="suspect_address" placeholder="Ilagay ang address ng suspek" class="w-full border p-4 rounded-lg">
            </div>

            <div>
                <label class="font-semibold block mb-1 required">Lugar ng Insidente</label>
                <input type="text" name="incident_location" placeholder="Saan naganap ang insidente?" required class="w-full border p-4 rounded-lg">
            </div>

            <div>
                <label class="font-semibold block mb-1 required">Petsa at Oras ng Insidente</label>
                <input type="datetime-local" name="incident_datetime" required class="w-full border p-4 rounded-lg">
            </div>

            <div>
                <label class="font-semibold block mb-1 required">Uri ng Insidente</label>
                <input type="text" name="incident_nature" placeholder="hal. Pagnanakaw, Pananakot" required class="w-full border p-4 rounded-lg">
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition font-semibold">
                    <i class="fas fa-paper-plane mr-2"></i> Isumite ang Reklamo
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

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

</body>
</html>
