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
$userRole = $user['role'];

// Get resident info if exists
$residentQuery = $conn->prepare("SELECT first_name, last_name, user_id FROM residents WHERE resident_id = ?");
$residentQuery->bind_param("i", $user_id);
$residentQuery->execute();
$residentResult = $residentQuery->get_result();

if ($residentResult->num_rows > 0) {
    $residentData = $residentResult->fetch_assoc();
    $userName = $residentData['first_name'] . ' ' . $residentData['last_name'];
    $residentUserId = $residentData['user_id'] ?? $user_id;
} else {
    $userName = $user['username'] ?? 'Unknown User';
    $residentUserId = $user_id;
}

// Check blotter ID
if (!isset($_GET['id'])) {
    header("Location: blotter.php");
    exit;
}
$blotterId = (int)$_GET['id'];

// ----------- UPDATE STATUS (STAFF/ADMIN) -----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status']) && in_array($userRole, ['staff','admin'])) {
    $newStatus = $_POST['update_status'];
    $allowedStatuses = ['open','investigating','closed','cancelled'];
    if (in_array($newStatus, $allowedStatuses)) {
        $stmtUpdate = $conn->prepare("UPDATE blotter_records SET status = ? WHERE blotter_id = ?");
        $stmtUpdate->bind_param("si", $newStatus, $blotterId);
        $stmtUpdate->execute();

        $stmtRes = $conn->prepare("SELECT incident_nature, complainant_id, victim_id, suspect_id FROM blotter_records WHERE blotter_id = ?");
        $stmtRes->bind_param("i", $blotterId);
        $stmtRes->execute();
        $resRecord = $stmtRes->get_result()->fetch_assoc();

        // Log activity
        $action = "Update Blotter Status";
        $description = "Blotter '{$resRecord['incident_nature']}' status changed to '$newStatus' by $userName.";
        $stmtLog = $conn->prepare("INSERT INTO log_activity (user_id, action, description) VALUES (?, ?, ?)");
        $stmtLog->bind_param("iss", $user_id, $action, $description);
        $stmtLog->execute();

        // Send notification to involved residents
        $involved = array_filter([$resRecord['complainant_id'], $resRecord['victim_id'], $resRecord['suspect_id']]);
        $message = "Ang status ng iyong blotter report tungkol sa '{$resRecord['incident_nature']}' ay na-update sa '{$newStatus}'.";
        $stmtNotif = $conn->prepare("INSERT INTO notifications (resident_id, message, from_role, title, type, priority, action_type) VALUES (?, ?, 'staff', 'Blotter Status Update', 'blotter', 'normal', 'updated')");
        foreach ($involved as $residentId) {
            $check = $conn->prepare("SELECT resident_id FROM residents WHERE resident_id = ? LIMIT 1");
            $check->bind_param("i", $residentId);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $stmtNotif->bind_param("is", $residentId, $message);
                $stmtNotif->execute();
            }
            $check->close();
        }

        header("Location: blotter_view.php?id=$blotterId");
        exit;
    }
}

// ----------- GET BLOTTER RECORD -----------
$stmt = $conn->prepare("
    SELECT b.*, 
        r1.first_name AS complainant_first, r1.last_name AS complainant_last, r1.user_id AS complainant_user_id, 
        r2.first_name AS victim_first, r2.last_name AS victim_last, r2.user_id AS victim_user_id, 
        r3.first_name AS suspect_first, r3.last_name AS suspect_last 
    FROM blotter_records b 
    LEFT JOIN residents r1 ON b.complainant_id = r1.resident_id 
    LEFT JOIN residents r2 ON b.victim_id = r2.resident_id 
    LEFT JOIN residents r3 ON b.suspect_id = r3.resident_id 
    WHERE b.blotter_id = ? 
    LIMIT 1
");
$stmt->bind_param("i", $blotterId);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();

if (!$record) {
    echo "Record not found.";
    exit;
}

$complainant = $record['complainant_name'] ?: ($record['complainant_first'] . ' ' . $record['complainant_last']);
$victim = $record['victim_name'] ?: ($record['victim_first'] . ' ' . $record['victim_last']);
$suspect = $record['suspect_name'] ?: ($record['suspect_first'] . ' ' . $record['suspect_last']);

// ----------- CHECK CERTIFICATES & AGREEMENTS -----------
$certStmt = $conn->prepare("SELECT file_path FROM blotter_certificates WHERE blotter_id = ? LIMIT 1");
$certStmt->bind_param("i", $blotterId);
$certStmt->execute();
$certResult = $certStmt->get_result();
$hasCertificate = $certResult->num_rows > 0;
$certificateFile = $hasCertificate ? $certResult->fetch_assoc()['file_path'] : '';

$agreementStmt = $conn->prepare("SELECT file_path FROM agreement_certificates WHERE blotter_id = ? LIMIT 1");
$agreementStmt->bind_param("i", $blotterId);
$agreementStmt->execute();
$agreementResult = $agreementStmt->get_result();
$hasAgreement = $agreementResult->num_rows > 0;
$agreementFile = $hasAgreement ? $agreementResult->fetch_assoc()['file_path'] : '';

// Auto-close or auto-update status
if ($hasAgreement && $record['status'] !== 'closed') {
    $newStatus = 'closed';
    $stmtUpdate = $conn->prepare("UPDATE blotter_records SET status = ? WHERE blotter_id = ?");
    $stmtUpdate->bind_param("si", $newStatus, $blotterId);
    $stmtUpdate->execute();
    $action = "Auto Close Blotter Status";
    $description = "Blotter '{$record['incident_nature']}' status auto-changed to 'closed' by $userName because an agreement has been filed.";
    $stmtLog = $conn->prepare("INSERT INTO log_activity (user_id, action, description) VALUES (?, ?, ?)");
    $stmtLog->bind_param("iss", $user_id, $action, $description);
    $stmtLog->execute();
    $record['status'] = $newStatus;
}

if ($hasCertificate && $record['status'] === 'open') {
    $newStatus = 'investigating';
    $stmtUpdate = $conn->prepare("UPDATE blotter_records SET status = ? WHERE blotter_id = ?");
    $stmtUpdate->bind_param("si", $newStatus, $blotterId);
    $stmtUpdate->execute();
    $action = "Auto Update Blotter Status";
    $description = "Blotter '{$record['incident_nature']}' status auto-changed to '$newStatus' by $userName because a filed report exists.";
    $stmtLog = $conn->prepare("INSERT INTO log_activity (user_id, action, description) VALUES (?, ?, ?)");
    $stmtLog->bind_param("iss", $user_id, $action, $description);
    $stmtLog->execute();
    $record['status'] = $newStatus;
}

$msgStmt = $conn->prepare("
    SELECT m.*, 
        CASE 
            WHEN m.sender_role = 'resident' THEN CONCAT(r.first_name, ' ', r.last_name)
            ELSE u.username
        END AS sender_name
    FROM messages m 
    LEFT JOIN residents r ON (m.sender_role = 'resident' AND m.sender_id = r.resident_id) 
    LEFT JOIN users u ON (m.sender_role IN ('staff','admin') AND m.sender_id = u.id) 
    WHERE m.blotter_id = ? 
    ORDER BY m.date_sent ASC
");

$msgStmt->bind_param("i", $blotterId);
$msgStmt->execute();
$msgResult = $msgStmt->get_result();
$messages = [];
while ($row = $msgResult->fetch_assoc()) {
    $messages[] = $row;
}

// ----------- SEND MESSAGE -----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $messageContent = trim($_POST['message']);
    if ($messageContent !== '') {
        $senderId = $user_id;
        $senderRole = in_array($userRole, ['staff','admin']) ? $userRole : 'resident';

        // Determine receiver ID
        if (in_array($senderRole, ['staff','admin'])) {
            $residentId = $record['complainant_id'] ?? $record['victim_id'];
            $stmtUser = $conn->prepare("SELECT user_id FROM residents WHERE resident_id = ?");
            $stmtUser->bind_param("i", $residentId);
            $stmtUser->execute();
            $res = $stmtUser->get_result()->fetch_assoc();
            $receiverId = $res['user_id'] ?? null;
        } else {
            $receiverId = $record['assigned_staff_id'] ?? 2;
        }

        if ($receiverId) {
            // Insert message
            $stmtMsg = $conn->prepare("INSERT INTO messages (sender_role, sender_id, receiver_id, blotter_id, content) VALUES (?, ?, ?, ?, ?)");
            $stmtMsg->bind_param("siiis", $senderRole, $senderId, $receiverId, $blotterId, $messageContent);
            $stmtMsg->execute();

            // Log activity
            $action = "Send Message";
            $description = "$userName sent a message in Blotter '{$record['incident_nature']}': '$messageContent'";
            $stmtLog = $conn->prepare("INSERT INTO log_activity (user_id, action, description) VALUES (?, ?, ?)");
            $stmtLog->bind_param("iss", $user_id, $action, $description);
            $stmtLog->execute();

            // Send notification
            $notifMsg = ($senderRole === 'staff' || $senderRole === 'admin') 
                         ? "May bagong mensahe mula sa staff tungkol sa iyong blotter report '{$record['incident_nature']}'." 
                         : "May bagong mensahe mula sa residente tungkol sa blotter report '{$record['incident_nature']}'.";  

            $stmtNotif = $conn->prepare("INSERT INTO notifications (resident_id, message, from_role, title, type, priority, action_type) VALUES (?, ?, ?, 'New Message', 'blotter', 'normal', 'message')");
            $check = $conn->prepare("SELECT resident_id FROM residents WHERE resident_id = ?");
            $check->bind_param("i", $receiverId);
            $check->execute();
            $resCheck = $check->get_result();
            if ($resCheck->num_rows > 0) {
                $stmtNotif->bind_param("iss", $receiverId, $notifMsg, $senderRole);
                $stmtNotif->execute();
            }
            $check->close();
        }

        header("Location: blotter_view.php?id=$blotterId");
        exit;
    }
}

// ----------- SYSTEM SETTINGS -----------
$settingsQuery = $conn->query("SELECT barangay_name, system_logo FROM system_settings LIMIT 1");
$settings = $settingsQuery->fetch_assoc();
$barangayName = $settings['barangay_name'] ?? 'Barangay Name';
$systemLogo = $settings['system_logo'] ?? 'default-logo.png';
$systemLogoPath = '../' . $systemLogo;
?>




<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Blotter Record Details</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link rel="stylesheet" href="b.css">
<style>
    body {
        background: linear-gradient(to right, #f0fdf4, #d1fae5);
    }
    .chat-box {
        height: 300px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 10px;
        padding: 10px;
        background: #f9f9f9;
        border-radius: 8px;
    }
    .chat-message {
        display: flex;
        max-width: 70%;
    }
    .chat-message.staff {
        justify-content: flex-end;
        margin-left: auto;
    }
    .chat-message.resident {
        justify-content: flex-start;
        margin-right: auto;
    }
    .chat-message .content {
        padding: 10px 14px;
        border-radius: 12px;
        word-break: break-word;
    }
    .chat-message.staff .content {
        background: #34d399;
        color: white;
        border-top-right-radius: 0;
    }
    .chat-message.resident .content {
        background: #e5e7eb;
        color: black;
        border-top-left-radius: 0;
    }
    .loader {
        border-width: 4px;
        border-style: solid;
        border-radius: 50%;
        border-top-color: transparent;
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .sidebar-collapsed { width: 80px !important; }
    .sidebar-collapsed .sidebar-text { display: none; }
    .sidebar-collapsed .material-icons { margin: 0 auto !important; }
    
    .sidebar-collapsed img {
        width: 40px !important;
        height: 40px !important;
        margin: 0 auto;
        display: block;
        border: 2px solid white;
    }

    .sidebar-collapsed .flex.items-center.space-x-3 {
        justify-content: center;
        gap: 0;
    }


</style>
</head>
<body class="font-sans">
<div class="flex h-screen overflow-hidden">

<aside id="sidebar" class="w-64 bg-gradient-to-b from-blue-500 to-blue-700 text-white flex flex-col shadow-xl transition-all duration-300 h-screen">
    <div class="flex items-center justify-between p-4 border-b border-white/20">
        <div class="flex items-center space-x-3"><img src="<?= htmlspecialchars($systemLogoPath) ?>" alt="Barangay Logo" class="w-16 h-16 rounded-full object-cover shadow-sm border-2 border-white transition-all">
            <span class="font-semibold text-lg sidebar-text"><?= htmlspecialchars($barangayName) ?></span>
        </div>
        <button id="toggleSidebar" class="material-icons cursor-pointer text-2xl">chevron_left</button>
    </div>

    <nav class="flex-1 overflow-y-auto px-2 py-5 space-y-2">
        <a href="blotter.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 transition-colors">
            <span class="material-icons mr-3">arrow_back</span><span class="sidebar-text">Back to Blotter Records</span>
        </a>

        <?php if($hasCertificate): ?>
            <a href="javascript:void(0);" onclick="viewPDF('view_blotter_pdf.php?id=<?= $blotterId ?>')" class="flex items-center px-4 py-3 rounded hover:bg-white/10 transition-colors">
                <span class="material-icons mr-3">visibility</span><span class="sidebar-text">View Filed Report</span>
            </a>
        <?php else: ?>
            <a href="file_blotter.php?id=<?= $blotterId ?>" class="flex items-center px-4 py-3 rounded hover:bg-white/10 transition-colors">
                <span class="material-icons mr-3">gavel</span><span class="sidebar-text">File Blotter</span>
            </a>
        <?php endif; ?>

        <?php if($hasAgreement): ?>
            <a href="javascript:void(0);" onclick="viewPDF('view_agreement_pdf.php?id=<?= $blotterId ?>')" class="flex items-center px-4 py-3 rounded hover:bg-white/10 transition-colors">
                <span class="material-icons mr-3">visibility</span><span class="sidebar-text">View Agreement</span>
            </a>
        <?php else: ?>
            <a href="file_agreement.php?id=<?= $blotterId ?>" class="flex items-center px-4 py-3 rounded hover:bg-white/10 transition-colors">
                <span class="material-icons mr-3">description</span><span class="sidebar-text">File Agreement</span>
            </a>
        <?php endif; ?>
    </nav>
</aside>


 <div class="flex-1 flex flex-col overflow-hidden bg-gray-50">
  <header class="flex-shrink-0 flex items-center justify-between bg-white shadow-md px-6 py-4 rounded-b-2xl mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Blotter Records</h2>
  </header>

      <main class="flex-1 overflow-y-auto p-6 bg-gray-50 space-y-6">
  <nav class="text-gray-500 text-sm mb-4">
    <a href="blotter.php" class="hover:underline">Blotter</a> / 
    <span class="font-medium"><?= htmlspecialchars($record['incident_nature']) ?></span>
  </nav>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white p-4 rounded-xl shadow hover:shadow-lg transition">
      <span class="text-gray-500">Status</span>
      <span class="text-lg font-bold text-<?= $statusColor ?>-500"><?= ucfirst($record['status']) ?></span>
    </div>
    <div class="bg-white p-4 rounded-xl shadow hover:shadow-lg transition">
      <span class="text-gray-500">Messages</span>
      <span class="text-lg font-bold"><?= count($messages) ?></span>
    </div>
    <div class="bg-white p-4 rounded-xl shadow hover:shadow-lg transition">
      <span class="text-gray-500">Filed On</span>
      <span class="text-lg font-bold"><?= date('M d, Y', strtotime($record['incident_datetime'])) ?></span>
    </div>
  </div>

  <div class="bg-white shadow-lg hover:shadow-2xl rounded-xl p-6 border-l-4 border-<?= $statusColor ?>-500 transition mb-6">
    <div class="mb-4 flex justify-between items-center">
      <h3 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($record['incident_nature']) ?></h3>
      <?php if(in_array($_SESSION['role'], ['staff','admin'])): ?>
      <form method="POST" class="flex items-center gap-2">
        <select name="update_status" class="border rounded px-2 py-1 focus:outline-none focus:ring-2 focus:ring-emerald-400">
          <?php foreach(['open','investigating','closed','cancelled'] as $status): ?>
            <option value="<?= $status ?>" <?= $record['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 transition">Update</button>
      </form>
      <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-gray-700 mt-4">
      <div class="space-y-1">
        <p><span class="font-semibold">Petsa & Oras:</span> <?= date('M d, Y H:i', strtotime($record['incident_datetime'])) ?></p>
        <p><span class="font-semibold">Nagrereklamo:</span> <?= htmlspecialchars($complainant) ?></p>
        <p><span class="font-semibold">Biktima:</span> <?= htmlspecialchars($victim) ?></p>
        <p><span class="font-semibold">Nirereklamo:</span> <?= htmlspecialchars($suspect) ?></p>
      </div>
      <div class="space-y-1">
        <p><span class="font-semibold">Lokasyon:</span> <?= htmlspecialchars($record['incident_location'] ?? '-') ?></p>
        <p><span class="font-semibold">Detalye ng Nangyari:</span></p>
        <p class="text-gray-600"><?= nl2br(htmlspecialchars($record['incident_nature'])) ?></p>
      </div>
    </div>
  </div>

        <h3 class="text-lg font-semibold mb-2">Messages</h3>
            <div class="chat-box mb-4" id="chatBox">
                <?php if(count($messages) > 0): ?>
                   <?php foreach($messages as $msg): ?>
    <?php 
        $senderClass = ($msg['sender_role'] == 'resident') ? 'resident' : 'staff';
    ?>
    <div class="chat-message <?= $senderClass ?>">
        <div class="content">
            <strong><?= htmlspecialchars($msg['sender_name']) ?>:</strong><br>
            <?= nl2br(htmlspecialchars($msg['content'])) ?><br>
            <small class="text-gray-500"><?= date('M d, H:i', strtotime($msg['date_sent'])) ?></small>
        </div>
    </div>
<?php endforeach; ?>

                <?php else: ?>
                    <p class="text-gray-500 text-center">No message yet.</p>
                <?php endif; ?>
            </div>
  <form method="POST" class="flex gap-2">
    <input type="text" name="message" placeholder="Type a message..." class="flex-1 px-4 py-2 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-400" required>
    <button type="submit" class="px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition">Send</button>
  </form>
</main>

    </div>
</div>

<iframe id="printFrame" style="display:none;"></iframe>

<div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-40 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl p-6 flex flex-col items-center">
        <div class="loader border-4 border-emerald-500 border-t-transparent rounded-full w-12 h-12 mb-4"></div>
        <p class="text-gray-700 font-medium">Loading, please wait...</p>
    </div>
</div>

<script>
    const chatBox = document.getElementById('chatBox');
    chatBox.scrollTop = chatBox.scrollHeight;

    async function viewPDF(url) {
        const iframe = document.getElementById('printFrame');
        const loading = document.getElementById('loadingOverlay');
        loading.classList.remove('hidden');

        try {
            const res = await fetch(url, { credentials: 'include' });
            if (!res.ok) throw new Error('Failed to fetch PDF');

            const blob = await res.blob();
            const objectUrl = URL.createObjectURL(blob);
            iframe.src = objectUrl;

            iframe.onload = function() {
                try {
                    iframe.contentWindow.focus();
                    iframe.contentWindow.print();
                } catch(e) {
                    console.error("Print error:", e);
                }
                loading.classList.add('hidden');
                URL.revokeObjectURL(objectUrl);
            };
        } catch(err) {
            console.error(err);
            alert("Unable to load PDF.");
            loading.classList.add('hidden');
        }
    }
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('toggleSidebar');

    toggleBtn.onclick = () => {
        sidebar.classList.toggle('sidebar-collapsed');

        let icon = toggleBtn.textContent.trim();
        toggleBtn.textContent = icon === 'chevron_left' ? 'chevron_right' : 'chevron_left';
    };
</script>
</body>
</html>
