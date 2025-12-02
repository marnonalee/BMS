<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "resident") {
    header("Location: ../index.php");
    exit();
}
include '../../db.php';

$user_id = $_SESSION["user_id"];
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

$user_id = $_SESSION["user_id"];

$residentQuery = $conn->prepare("SELECT resident_id FROM residents WHERE user_id=?");
$residentQuery->bind_param("i", $user_id);
$residentQuery->execute();
$residentResult = $residentQuery->get_result();
if ($residentResult->num_rows == 0) die("Resident not found.");
$resident_id = $residentResult->fetch_assoc()['resident_id'];
$residentQuery->close();

// Handle cancellation
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'], $_POST['cancel_type'])){
    $cancelId = intval($_POST['cancel_id']);
    $type = $_POST['cancel_type'];

    if($type === 'certificate'){
        $stmt = $conn->prepare("UPDATE certificate_requests SET status='Cancelled' WHERE id=? AND resident_id=?");
    } else {
        $stmt = $conn->prepare("UPDATE barangay_id_requests SET status='Cancelled' WHERE id=? AND resident_id=?");
    }
    $stmt->bind_param("ii", $cancelId, $resident_id);
    $stmt->execute();
    $stmt->close();
    header("Location: all_requests.php");
    exit();
}

$requestQuery = $conn->prepare("
    SELECT cr.id, ct.template_name, cr.purpose, cr.status, cr.date_requested
    FROM certificate_requests cr
    JOIN certificate_templates ct ON cr.template_id = ct.id
    WHERE cr.resident_id=?
    ORDER BY cr.date_requested DESC
");
$requestQuery->bind_param("i", $resident_id);
$requestQuery->execute();
$result = $requestQuery->get_result();
$certRequests = $result->fetch_all(MYSQLI_ASSOC);
$requestQuery->close();

$barangayQuery = $conn->prepare("
    SELECT id, id_number, supporting_document, status, date_requested
    FROM barangay_id_requests
    WHERE resident_id=?
    ORDER BY date_requested DESC
");
$barangayQuery->bind_param("i", $resident_id);
$barangayQuery->execute();
$result2 = $barangayQuery->get_result();
$barangayRequests = $result2->fetch_all(MYSQLI_ASSOC);
$barangayQuery->close();

$settingsQuery = $conn->query("SELECT theme_color FROM system_settings LIMIT 1");
$settings = $settingsQuery->fetch_assoc();
$themeColor = $settings['theme_color'] ?? '#3b82f6';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>All Requests</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
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

    <div class="flex items-center space-x-4 md:space-x-6 w-full md:w-auto justify-end mt-2 md:mt-0">
      <a href="../dashboard.php" class="bg-white/20 hover:bg-white/30 text-white font-semibold px-4 py-2 rounded-lg transition duration-200 flex items-center">
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
<main class="container mx-auto mt-10 max-w-6xl px-4">
    <div class="mb-6 flex items-center space-x-2 text-gray-500 text-sm">
        <a href="../dashboard.php" class="hover:underline">Dashboard</a>
        <span class="text-gray-300">/</span>
        <span class="font-semibold text-gray-700">My Document Requests</span>
    </div>

    <div class="bg-white shadow-xl rounded-2xl p-8">
        <section class="mb-10">
            <h2 class="text-2xl font-semibold text-gray-800 border-b border-gray-200 pb-2 mb-6">Certificate Requests</h2>

            <?php if(count($certRequests) === 0): ?>
                <p class="text-gray-400 text-center py-8 italic">You have no certificate requests yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-600 uppercase tracking-wide">Certificate</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-600 uppercase tracking-wide">Purpose</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-600 uppercase tracking-wide">Status</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-600 uppercase tracking-wide">Date Requested</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-600 uppercase tracking-wide">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach($certRequests as $r): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4"><?= htmlspecialchars($r['template_name']) ?></td>
                                    <td class="px-6 py-4"><?= htmlspecialchars($r['purpose']) ?></td>
                                    <td class="px-6 py-4">
                                        <?php
                                        $statusColor = match($r['status']) {
                                            'Pending' => 'bg-yellow-100 text-yellow-800 font-semibold px-3 py-1 rounded-full text-center',
                                            'Approved' => 'bg-green-100 text-green-800 font-semibold px-3 py-1 rounded-full text-center',
                                            'Rejected' => 'bg-red-100 text-red-800 font-semibold px-3 py-1 rounded-full text-center',
                                            'Cancelled' => 'bg-gray-100 text-gray-800 font-semibold px-3 py-1 rounded-full text-center',
                                            default => 'text-gray-500',
                                        };
                                        ?>
                                        <span class="<?= $statusColor ?>"><?= htmlspecialchars($r['status']) ?></span>
                                    </td>
                                    <td class="px-6 py-4"><?= htmlspecialchars(date('M d, Y', strtotime($r['date_requested']))) ?></td>
                                    <td class="px-6 py-4 space-x-2">
                                        <?php if($r['status'] === 'Pending'): ?>
                                            <a href="request_certificate.php?edit_id=<?= $r['id'] ?>" class="px-3 py-1 bg-blue-50 text-blue-600 rounded-md hover:bg-blue-100 transition">Edit</a>
                                            <button onclick="openCancelModal(<?= $r['id'] ?>, 'certificate')" class="px-3 py-1 bg-red-50 text-red-600 rounded-md hover:bg-red-100 transition">Cancel</button>
                                        <?php else: ?>
                                            <span class="text-gray-400">No action</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section>
            <h2 class="text-2xl font-semibold text-gray-800 border-b border-gray-200 pb-2 mb-6">Barangay ID Requests</h2>

            <?php if(count($barangayRequests) === 0): ?>
                <p class="text-gray-400 text-center py-8 italic">You have no Barangay ID requests yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-600 uppercase tracking-wide">ID Number</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-600 uppercase tracking-wide">Supporting Document</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-600 uppercase tracking-wide">Status</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-600 uppercase tracking-wide">Date Requested</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-600 uppercase tracking-wide">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach($barangayRequests as $b): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4"><?= htmlspecialchars($b['id_number'] ?: 'N/A') ?></td>
                                    <td class="px-6 py-4">
                                        <?php
                                        if(!empty($b['supporting_document'])){
                                            $docs = json_decode($b['supporting_document'], true);
                                            if($docs){
                                                foreach($docs as $label => $file){
                                                    echo '<button onclick="openModal(\'../uploads/'.htmlspecialchars($file).'\')" class="px-2 py-1 bg-blue-50 text-blue-600 rounded hover:bg-blue-100 transition mr-2">'.ucfirst($label).' ID</button>';
                                                }
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php
                                        $statusColor = match($b['status']) {
                                            'Pending' => 'bg-yellow-100 text-yellow-800 font-semibold px-3 py-1 rounded-full text-center',
                                            'Approved' => 'bg-green-100 text-green-800 font-semibold px-3 py-1 rounded-full text-center',
                                            'Rejected' => 'bg-red-100 text-red-800 font-semibold px-3 py-1 rounded-full text-center',
                                            'Ready for Pickup' => 'bg-blue-100 text-blue-800 font-semibold px-3 py-1 rounded-full text-center',
                                            'Cancelled' => 'bg-gray-100 text-gray-800 font-semibold px-3 py-1 rounded-full text-center',
                                            default => 'text-gray-500',
                                        };
                                        ?>
                                        <span class="<?= $statusColor ?>"><?= htmlspecialchars($b['status']) ?></span>
                                    </td>
                                    <td class="px-6 py-4"><?= htmlspecialchars(date('M d, Y', strtotime($b['date_requested']))) ?></td>
                                    <td class="px-6 py-4 space-x-2">
                                        <?php if($b['status'] === 'Pending'): ?>
                                            <a href="../request_barangay_id.php?edit_id=<?= $b['id'] ?>" class="px-3 py-1 bg-blue-50 text-blue-600 rounded-md hover:bg-blue-100 transition">Edit</a>
                                            <button onclick="openCancelModal(<?= $b['id'] ?>, 'barangay')" class="px-3 py-1 bg-red-50 text-red-600 rounded-md hover:bg-red-100 transition">Cancel</button>
                                        <?php else: ?>
                                            <span class="text-gray-400">No action</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

    </div>

    <div id="docModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full md:w-1/2 lg:w-1/3 p-4 relative">
            <button onclick="closeModal()" class="absolute top-2 right-2 text-gray-600 hover:text-gray-900 text-2xl font-bold">&times;</button>
            <img id="docImg" src="" alt="Document" class="w-full h-auto rounded" />
        </div>
    </div>

    <div id="cancelModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full md:w-1/2 p-6 relative">
            <button onclick="closeCancelModal()" class="absolute top-2 right-2 text-gray-600 hover:text-gray-900 text-2xl font-bold">&times;</button>
            <h3 class="text-lg font-semibold mb-4 text-gray-800">Are you sure you want to cancel this request?</h3>
            <div class="flex justify-end gap-3 mt-4">
                <button onclick="closeCancelModal()" class="px-4 py-2 rounded bg-gray-200 hover:bg-gray-300 transition">No</button>
                <form id="cancelForm" method="POST">
                    <input type="hidden" name="cancel_id" id="cancelId">
                    <input type="hidden" name="cancel_type" id="cancelType">
                    <button type="submit" class="px-4 py-2 rounded bg-red-600 text-white hover:bg-red-700 transition">Yes, Cancel</button>
                </form>
            </div>
        </div>
    </div>

</main>

<script>
function openModal(filePath){
    document.getElementById('docImg').src = filePath;
    document.getElementById('docModal').classList.remove('hidden');
}
function closeModal(){
    document.getElementById('docImg').src = '';
    document.getElementById('docModal').classList.add('hidden');
}

function openCancelModal(id, type){
    document.getElementById('cancelId').value = id;
    document.getElementById('cancelType').value = type;
    document.getElementById('cancelModal').classList.remove('hidden');
}
function closeCancelModal(){
    document.getElementById('cancelModal').classList.add('hidden');
}

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
