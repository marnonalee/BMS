<?php 
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "resident") {
    header("Location: ../../index.php");
    exit();
}

include '../../db.php';

$settingsQuery = $conn->query("SELECT * FROM system_settings");
$settings = $settingsQuery->fetch_assoc();

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
       AND TRIM(LOWER(status)) != 'cancelled'
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

<header class="bg-gradient-to-r from-blue-500 to-indigo-600 text-white shadow-md">
    <div class="container mx-auto flex justify-between items-center p-4">
        <h1 class="text-xl font-bold">Complaint Records</h1>
        <a href="../dashboard.php" class="flex items-center gap-2 bg-white text-blue-600 px-4 py-2 rounded-lg hover:bg-gray-100"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</header>

<main class="container mx-auto px-4 py-6">
    <?php if($blotterResult->num_rows > 0): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php $count = 1; ?>
            <?php while($row = $blotterResult->fetch_assoc()): ?>
                <div class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition-shadow duration-300 overflow-hidden flex flex-col">
                    <div class="bg-gradient-to-r from-blue-500 to-indigo-600 text-white p-4 flex justify-between items-center">
                        <h2 class="text-lg font-semibold">Complaint #<?= $count ?></h2>
                        <span class="px-3 py-1 rounded-full text-sm font-semibold
                            <?php 
                                $statusClass = strtolower($row['status']);
                                echo $statusClass === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                     ($statusClass === 'approved' ? 'bg-green-100 text-green-800' : 
                                     ($statusClass === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')); 
                            ?>">
                            <?= htmlspecialchars($row['status']) ?>
                        </span>
                    </div>
                    <div class="p-5 flex-1 flex flex-col justify-between">
                        <div class="space-y-2 text-gray-700">
                            <p><strong>Complainant:</strong> <?= htmlspecialchars($resident_name) ?></p>
                            <p><strong>Victim:</strong> <?= htmlspecialchars($row['victim_name'] ?: 'N/A') ?></p>
                            <p><strong>Nature:</strong> <?= htmlspecialchars($row['incident_nature']) ?></p>
                            <p><strong>Location:</strong> <?= htmlspecialchars($row['incident_location']) ?></p>
                            <p><strong>Incident Date & Time:</strong> <?= date("F j, Y, g:i A", strtotime($row['incident_datetime'])) ?></p>
                            <p><strong>Date Filed:</strong> <?= date("F j, Y, g:i A", strtotime($row['created_at'])) ?></p>
                        </div>
                        <div class="mt-4 flex gap-2">
                            <button class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-xl hover:bg-blue-700 flex items-center justify-center gap-2"
                                onclick="openResidentChat(<?= $row['blotter_id'] ?>, <?= $resident_id ?>)">
                                <i class="fa-solid fa-message"></i> Message
                            </button>
                            <button class="flex-1 bg-red-600 text-white px-4 py-2 rounded-xl hover:bg-red-700 flex items-center justify-center gap-2"
                                onclick="cancelComplaint(<?= $row['blotter_id'] ?>)">
                                <i class="fa-solid fa-xmark"></i> Cancel
                            </button>
                        </div>
                    </div>
                </div>
                <?php $count++; ?>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p class="text-center text-gray-500 mt-12 text-lg">You haven't submitted any complaints yet.</p>
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

<style>
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

</script>

</body>
</html>
