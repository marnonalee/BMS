<?php 
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "resident") {
    header("Location: ../../index.php");
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

if ($resident_id > 0) {
    $conn->query("UPDATE notifications SET is_read = 1 WHERE resident_id = $resident_id");
}
$user_id = $_SESSION["user_id"];
$username = $_SESSION["username"];

$residentQuery = $conn->prepare("SELECT resident_id, CONCAT(first_name, ' ', COALESCE(middle_name,''), ' ', last_name) AS full_name FROM residents WHERE user_id = ?");
$residentQuery->bind_param("i", $user_id);
$residentQuery->execute();
$residentResult = $residentQuery->get_result();

if ($residentResult->num_rows > 0) {
    $residentRow = $residentResult->fetch_assoc();
    $resident_id = $residentRow['resident_id'];
    $resident_name = trim($residentRow['full_name']);
} else {
    die("No matching resident record found for this user.");
}
$residentQuery->close();

$blotterQuery = $conn->prepare(
    "SELECT * FROM blotter_records 
     WHERE complainant_id = ? 
       AND TRIM(LOWER(status)) IN ('pending','open','investigating','approved','rejected','closed','cancelled')
     ORDER BY created_at DESC"
);
$blotterQuery->bind_param("i", $resident_id);
$blotterQuery->execute();
$blotterResult = $blotterQuery->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Complaint List</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">
    
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
    #residentChatMessages .message {
    max-width: 75%;
    padding: 8px 12px;
    border-radius: 15px;
    display: inline-block;
    word-wrap: break-word;
    }

    #residentChatMessages .message.staff {
    background-color: #e0f2fe;
    color: #0369a1;
    align-self: flex-start;
    border-top-left-radius: 0;
    }

    #residentChatMessages .message.resident {
    background-color: #3b82f6;
    color: white;
    align-self: flex-end;
    border-top-right-radius: 0;
    }

    .message-time {
    display: block;
    font-size: 0.65rem;
    margin-top: 2px;
    opacity: 0.7;
    text-align: right;
    }
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

<main class="container mx-auto mt-8 max-w-6xl px-4">

  <div class="mb-6 flex items-center space-x-2 text-gray-500 text-sm">
    <a href="../dashboard.php" class="hover:underline">Dashboard</a>
    <span class="text-gray-300">/</span>
    <span class="font-semibold text-gray-700">My Complaints</span>
  </div>

  <?php if($blotterResult->num_rows > 0): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <?php $count = 1; ?>
      <?php while($row = $blotterResult->fetch_assoc()): ?>
        <div class="bg-white rounded-2xl shadow-md overflow-hidden hover:shadow-xl transition">
          <div class="bg-gradient-to-r from-indigo-500 to-purple-600 text-white px-5 py-3 flex justify-between items-center">
            <h2 class="text-md font-semibold">Complaint #<?= $count ?></h2>
          <span class="px-2 py-1 rounded-full text-xs font-semibold 
        <?php 
            $status = strtolower($row['status']);
            switch($status) {
                case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                case 'open': echo 'bg-blue-100 text-blue-800'; break;
                case 'investigating': echo 'bg-purple-100 text-purple-800'; break;
                case 'approved': echo 'bg-green-100 text-green-800'; break;
                case 'rejected': echo 'bg-red-100 text-red-800'; break;
                case 'closed': echo 'bg-gray-200 text-gray-800'; break;
                case 'cancelled': echo 'bg-gray-300 text-gray-700'; break;
                default: echo 'bg-gray-100 text-gray-800'; break;
            }
        ?>">
        <?= ucfirst($row['status']) ?>
    </span>


          </div>
          <div class="p-4 space-y-2 text-gray-700 text-sm">
            <p><span class="font-semibold">Complainant:</span> <?= htmlspecialchars($resident_name) ?></p>
            <p><span class="font-semibold">Victim:</span> <?= htmlspecialchars($row['victim_name'] ?: 'N/A') ?></p>
            <p><span class="font-semibold">Nature:</span> <?= htmlspecialchars($row['incident_nature']) ?></p>
            <p><span class="font-semibold">Location:</span> <?= htmlspecialchars($row['incident_location']) ?></p>
            <p><span class="font-semibold">Incident Date & Time:</span> <?= date("F j, Y, g:i A", strtotime($row['incident_datetime'])) ?></p>
            <p><span class="font-semibold">Date Filed:</span> <?= date("F j, Y, g:i A", strtotime($row['created_at'])) ?></p>
          </div>
          <div class="px-4 py-3 border-t flex flex-wrap gap-2 justify-end">
            <button class="bg-blue-600 text-white px-3 py-1 rounded-lg hover:bg-blue-700 text-sm flex items-center gap-1"
                onclick="openResidentChat(<?= $row['blotter_id'] ?>, <?= $resident_id ?>)">
              <i class="fa-solid fa-message"></i> Message
            </button>
            <button class="bg-red-600 text-white px-3 py-1 rounded-lg hover:bg-red-700 text-sm flex items-center gap-1"
                onclick="cancelComplaint(<?= $row['blotter_id'] ?>)">
              <i class="fa-solid fa-xmark"></i> Cancel
            </button>
          </div>
        </div>
        <?php $count++; ?>
      <?php endwhile; ?>
    </div>
  <?php else: ?>
    <p class="text-center text-gray-500 mt-16 text-lg">You haven't submitted any complaints yet.</p>
  <?php endif; ?>

</main>



<div id="residentChatModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="bg-white w-full max-w-2xl rounded-2xl p-4 flex flex-col h-[600px] relative">
    <div class="flex justify-between items-center border-b pb-2 mb-2">
      <h3 class="text-xl font-semibold">Messages</h3>
      <button onclick="closeResidentChatModal()" class="text-gray-600 hover:text-gray-800 text-2xl">&times;</button>
    </div>

    <div id="complaintDetails" class="mb-2 text-gray-700 space-y-1 text-sm px-2">
      <p><strong>Complainant:</strong> <span id="cdComplainant">-</span></p>
      <p><strong>Victim:</strong> <span id="cdVictim">-</span></p>
      <p><strong>Nature:</strong> <span id="cdNature">-</span></p>
      <p><strong>Location:</strong> <span id="cdLocation">-</span></p>
      <p><strong>Date & Time:</strong> <span id="cdDateTime">-</span></p>
      <hr class="my-2">
    </div>

    <div id="residentChatMessages" class="flex-1 overflow-y-auto px-3 py-2 bg-gray-50 rounded-xl space-y-2 flex flex-col">
    </div>

    <div class="flex gap-2 mt-2 border-t pt-2">
      <textarea id="residentChatInput" placeholder="Type a message..." rows="2" class="flex-1 p-2 border rounded-xl resize-none"></textarea>
      <button class="bg-blue-600 text-white px-4 py-2 rounded-xl hover:bg-blue-700 flex items-center gap-2"
              onclick="sendResidentMessage()">
        <i class="fa-solid fa-paper-plane"></i> Send
      </button>
    </div>
  </div>
</div>



<div id="universalModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="bg-white rounded-xl p-6 w-full max-w-md text-center">
    <div id="modalIcon" class="text-3xl mb-2"></div>
    <h2 id="modalTitle" class="text-lg font-semibold mb-2"></h2>
    <p id="modalMessage" class="mb-4"></p>
    <div class="flex justify-center gap-3">
        <button id="modalConfirm" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Yes</button>
        <button id="modalCancel" class="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500">No</button>
        <button id="modalOk" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">OK</button>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const hamburger = document.getElementById("hamburgerBtn");
    const sidebar = document.getElementById("sidebar");
    if (hamburger && sidebar) {
        hamburger.addEventListener("click", () => sidebar.classList.toggle("active"));
    }
});

let currentBlotterId = null;
let currentResidentId = null;

function openResidentChat(blotterId, residentId) {
    currentBlotterId = blotterId;
    currentResidentId = residentId;

    const chat = document.getElementById('residentChatMessages');
    chat.innerHTML = 'Loading messages...';

    const modal = document.getElementById('residentChatModal');
    modal.style.display = 'flex';

    fetch(`resident_get_messages.php?resident_id=${residentId}&blotter_id=${blotterId}`)
        .then(res => res.json())
        .then(data => {
            chat.innerHTML = '';
            if (data.details) {
                document.getElementById('cdComplainant').textContent = data.details.complainant_name || 'N/A';
                document.getElementById('cdVictim').textContent = data.details.victim_name || 'N/A';
                document.getElementById('cdNature').textContent = data.details.incident_nature || 'N/A';
                document.getElementById('cdLocation').textContent = data.details.incident_location || 'N/A';
                document.getElementById('cdDateTime').textContent = data.details.incident_datetime
                    ? new Date(data.details.incident_datetime).toLocaleString()
                    : 'N/A';
            }

            if (!data.messages || data.messages.length === 0) {
                chat.innerHTML = '<p>No messages yet.</p>';
                return;
            }

            data.messages.forEach(msg => {
                const div = document.createElement('div');
                div.classList.add('message', msg.sender_role);
                div.innerHTML = `
                    <div class="message-content">${msg.content}</div>
                    <small class="message-time">${new Date(msg.date_sent).toLocaleString()}</small>
                `;
                chat.appendChild(div);
            });

            chat.scrollTop = chat.scrollHeight;
        })
        .catch(() => { chat.innerHTML = 'Failed to load messages.'; });
}

function closeResidentChatModal() {
    document.getElementById('residentChatModal').style.display = 'none';
    currentBlotterId = null;
    currentResidentId = null;
}

function sendResidentMessage() {
    const messageInput = document.getElementById('residentChatInput');
    const message = messageInput.value.trim();
    if (!message || !currentResidentId || !currentBlotterId) return;

    fetch('resident_send_message.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ blotter_id: currentBlotterId, message })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const chat = document.getElementById('residentChatMessages');
            const div = document.createElement('div');
            div.classList.add('message', 'resident'); 
            div.innerHTML = `
                <div class="message-content">${message}</div>
                <small class="message-time">${new Date().toLocaleString()}</small>
            `;
            chat.appendChild(div);
            chat.scrollTop = chat.scrollHeight;
            messageInput.value = '';
        } else {
            alert(data.message || 'Failed to send message.');
        }
    })
    .catch(() => alert('Error sending message.'));
}

window.addEventListener('click', e => {
    if (e.target.id === 'residentChatModal') closeResidentChatModal();
});

function cancelComplaint(blotterId) {
    showModal({
        type: 'confirm',
        title: 'Cancel Complaint',
        message: 'Are you sure you want to cancel this complaint?',
        onConfirm: () => {
            fetch(`resident_cancel_complaint.php?blotter_id=${blotterId}`, { method: 'POST' })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showModal({ type: 'success', title: 'Cancelled', message: 'Complaint status updated to cancelled.' });
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        showModal({ type: 'error', title: 'Failed', message: data.message || 'Failed to cancel complaint.' });
                    }
                })
                .catch(() => showModal({ type: 'error', title: 'Error', message: 'An error occurred.' }));
        }
    });
}

const universalModal = document.getElementById('universalModal');
const modalIcon = document.getElementById('modalIcon');
const modalTitle = document.getElementById('modalTitle');
const modalMessage = document.getElementById('modalMessage');
const btnConfirm = document.getElementById('modalConfirm');
const btnCancel = document.getElementById('modalCancel');
const btnOk = document.getElementById('modalOk');

function showModal({ type = 'info', title = '', message = '', onConfirm = null, onCancel = null }) {
    modalIcon.innerHTML = '';
    modalTitle.textContent = title;
    modalMessage.textContent = message;

    btnConfirm.style.display = 'none';
    btnCancel.style.display = 'none';
    btnOk.style.display = 'none';

    switch(type) {
        case 'success': modalIcon.innerHTML = '✅'; btnOk.style.display = 'inline-block'; break;
        case 'error': modalIcon.innerHTML = '❌'; btnOk.style.display = 'inline-block'; break;
        case 'confirm': modalIcon.innerHTML = '⚠️'; btnConfirm.style.display = 'inline-block'; btnCancel.style.display = 'inline-block'; break;
        default: btnOk.style.display = 'inline-block'; break;
    }

    btnConfirm.onclick = () => { if(onConfirm) onConfirm(); closeModal(); };
    btnCancel.onclick = () => { if(onCancel) onCancel(); closeModal(); };
    btnOk.onclick = () => closeModal();

    universalModal.style.display = 'flex';
}

function closeModal() {
    universalModal.style.display = 'none';
    btnConfirm.onclick = null;
    btnCancel.onclick = null;
    btnOk.onclick = null;
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
