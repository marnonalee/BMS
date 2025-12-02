<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../../login.php");
    exit();
}

include '../db.php';

$user_id = $_SESSION["user_id"];
$user = $conn->query("SELECT * FROM users WHERE id = '$user_id'")->fetch_assoc();
$role = $user['role']; 
$successMsg = '';

$perPage = $_GET['perPage'] ?? 50;
$page = $_GET['page'] ?? 1;
$start = ($page - 1) * $perPage;

$totalRequests = $conn->query("SELECT COUNT(*) AS cnt FROM barangay_id_requests WHERE status != 'Cancelled'")->fetch_assoc()['cnt'];
$totalPages = ceil($totalRequests / $perPage);

$requestsQuery = $conn->query("
    SELECT b.*, r.first_name, r.middle_name, r.last_name, r.birthdate, r.resident_address, b.request_type
    FROM barangay_id_requests b
    JOIN residents r ON b.resident_id = r.resident_id
    WHERE b.status != 'Cancelled' AND b.is_printed = 0
    ORDER BY b.created_at DESC
    LIMIT $start, $perPage
");

function logActivity($conn, $user_id, $action, $description = null) {
    $stmt = $conn->prepare("INSERT INTO log_activity (user_id, action, description, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $user_id, $action, $description);
    $stmt->execute();
    $stmt->close();
}

function sendNotification($conn, $resident_id, $message, $title = "Barangay ID Request", $from_role = "system", $type = "general", $priority = "normal", $action_type = "updated") {
    $stmt = $conn->prepare("INSERT INTO notifications (resident_id, message, from_role, title, type, priority, action_type, is_read, sent_email, date_created) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, NOW())");
    $stmt->bind_param("issssss", $resident_id, $message, $from_role, $title, $type, $priority, $action_type);
    $stmt->execute();
    $stmt->close();
}

if (isset($_POST['update_status'])) {
    $requestId = intval($_POST['request_id']);
    $newStatus = $_POST['status'];
    $remarks = trim($_POST['remarks'] ?? '') ?: 'No Issue';
    $reviewed_by = $_SESSION['user_id'];
    $date_reviewed = date('Y-m-d H:i:s');

    $residentData = $conn->query("SELECT r.resident_id, r.first_name, r.middle_name, r.last_name FROM barangay_id_requests b JOIN residents r ON b.resident_id = r.resident_id WHERE b.id = $requestId")->fetch_assoc();
    $full_name = $residentData['first_name'] . ' ' . $residentData['middle_name'] . ' ' . $residentData['last_name'];
    $resident_id = $residentData['resident_id'];

    $stmt = $conn->prepare("UPDATE barangay_id_requests SET status=?, remarks=?, date_reviewed=?, reviewed_by=? WHERE id=?");
    $stmt->bind_param("sssii", $newStatus, $remarks, $date_reviewed, $reviewed_by, $requestId);
    $stmt->execute();
    $stmt->close();

    logActivity($conn, $user_id, 'Update Request Status', "Changed status of $full_name to $newStatus");

    if ($newStatus === 'Approved') {
        $message = "Ang iyong Barangay ID request ay na-approve. ID Number: " . $conn->query("SELECT id_number FROM barangay_id_requests WHERE id=$requestId")->fetch_assoc()['id_number'];
        sendNotification($conn, $resident_id, $message, "Barangay ID Approved");
        header("Location: generate_id.php?request_id=$requestId");
        exit;
    } elseif ($newStatus === 'Rejected') {
        $message = "Ang iyong Barangay ID request ay na-reject. Remarks: $remarks";
        sendNotification($conn, $resident_id, $message, "Barangay ID Rejected");
    }

    header("Location: barangay_id_requests.php");
    exit;
}

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
    <title>Barangay ID Requests</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="b.css">
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
      <a href="../id/barangay_id_requests.php" class="flex items-center px-4 py-3 rounded-md bg-white/10 backdrop-blur-sm transition-all">
        <span class="material-icons mr-3">credit_card</span><span class="sidebar-text">Barangay ID Request</span>
      </a>
      <a href="../id/walk_in_request.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
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
          <span class="material-icons mr-3">history</span><span class="sidebar-text">Log Activity</span>
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
    <h2 class="text-2xl font-bold text-gray-800">Barangay ID Requests</h2>
  </header>

<main class="flex-1 overflow-y-auto p-6">
  <div class="bg-white p-6 rounded-2xl shadow-lg overflow-x-auto">

      <div class="flex justify-start mb-4 items-center gap-2">
          <span class="text-gray-600 font-medium">Show</span>
          <select id="perPageSelect" class="border border-gray-300 px-3 py-2 rounded-lg focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400 transition">
            <option value="50" <?= $perPage == 50 ? 'selected' : '' ?>>50</option>
            <option value="100" <?= $perPage == 100 ? 'selected' : '' ?>>100</option>
            <option value="200" <?= $perPage == 200 ? 'selected' : '' ?>>200</option>
          </select>
          <span class="text-gray-600 font-medium">entries</span>
      </div>

      <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Resident</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ID Number</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Request Type</th> <!-- NEW -->
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Requested On</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Payment Proof</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody id="requestsTable" class="bg-white divide-y divide-gray-200">
              <?php if ($requestsQuery->num_rows > 0): ?>
                  <?php while ($req = $requestsQuery->fetch_assoc()): ?>
                  <tr class="hover:bg-gray-50 cursor-pointer transition <?= $req['status'] === 'Pending' ? 'pendingRow' : '' ?>"
                      data-resident="<?= htmlspecialchars($req['first_name'].' '.$req['last_name']) ?>"
                      data-birthdate="<?= htmlspecialchars($req['birthdate']) ?>"
                      data-address="<?= htmlspecialchars($req['resident_address']) ?>">
                    
                    <td class="px-6 py-4"><?= htmlspecialchars($req['first_name'].' '.$req['last_name']) ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($req['id_number'] ?: 'N/A') ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($req['request_type']) ?></td> <!-- NEW -->
                    <td class="px-6 py-4"><?= htmlspecialchars($req['status']) ?></td>
                    <td class="px-6 py-4"><?= date('M d, Y h:i A', strtotime($req['date_requested'])) ?></td>
                    
                    <td class="px-6 py-4">
                        <?php if (!empty($req['payment_proof'])): ?>
                            <button onclick="openModal('<?= '../../resident/uploads/' . $req['payment_proof'] ?>')" 
                                class="text-blue-600 hover:underline">View Proof</button>
                        <?php else: ?>
                            <span class="text-gray-500">No Proof</span>
                        <?php endif; ?>
                    </td>

                    <td class="px-6 py-4">
                      <?php if ($req['status'] === 'Pending'): ?>
                          <!-- Approve/Reject buttons -->
                          <form method="POST" style="display:inline-block;">
                              <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                              <input type="hidden" name="status" value="Approved">
                              <button type="submit" name="update_status" class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600 transition">Approve</button>
                          </form>

                          <form method="POST" style="display:inline-block;">
                              <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                              <input type="hidden" name="status" value="Rejected">
                              <button type="submit" name="update_status" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 transition">Reject</button>
                          </form>

                      <?php elseif ($req['status'] === 'Approved' && $req['is_printed'] == 0): ?>
                          <button onclick="printID('<?= '../'.$req['generated_pdf'] ?>', <?= $req['id'] ?>)" 
                                  class="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600 transition">
                              Print
                          </button>

                      <?php else: ?>
                          <span class="text-gray-500">No actions</span>
                      <?php endif; ?>
                    </td>

                  </tr>
                  <?php endwhile; ?>
              <?php else: ?>
                  <tr>
                      <td colspan="7" class="text-center py-4 text-gray-500">No requests found.</td>
                  </tr>
              <?php endif; ?>
          </tbody>

      </table>

  </div>

<div class="bg-white p-6 rounded-2xl shadow-lg overflow-x-auto mt-10">

    <h3 class="text-xl font-bold mb-4 text-gray-700">List of Printed Barangay IDs</h3>

    <?php
    $printedQuery = $conn->query("
        SELECT b.*, r.first_name, r.middle_name, r.last_name
        FROM barangay_id_requests b
        JOIN residents r ON b.resident_id = r.resident_id
        WHERE b.is_printed = 1
        ORDER BY b.id DESC
    ");
    ?>

    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-100">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Resident Name</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ID Number</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Request Type</th> <!-- NEW -->
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">PDF</th>
            </tr>
        </thead>

        <tbody class="bg-white divide-y divide-gray-200">
            <?php if ($printedQuery->num_rows > 0): ?>
                <?php while ($p = $printedQuery->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4"><?= htmlspecialchars($p['first_name'].' '.$p['last_name']) ?></td>
                        <td class="px-6 py-4"><?= htmlspecialchars($p['id_number']) ?></td>
                        <td class="px-6 py-4"><?= htmlspecialchars($p['request_type']) ?></td> <!-- NEW -->
                        <td class="px-6 py-4">
                            <?php if (!empty($p['generated_pdf'])): ?>
                                <a href="<?= '../'.$p['generated_pdf'] ?>" target="_blank" 
                                  class="text-blue-600 hover:underline">View PDF</a>
                            <?php else: ?>
                                <span class="text-gray-500">No File</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" class="text-center py-4 text-gray-500">No printed IDs found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</div>

</main>

</div>
</div>
<div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="flex flex-col items-center">
        <div class="w-16 h-16 border-4 border-white border-t-transparent rounded-full animate-spin mb-4"></div>
        <div class="text-white text-lg font-semibold">Generating ID, please wait...</div>
    </div>
</div>
<!-- Success Modal -->
<div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg p-6 shadow-lg max-w-sm w-full text-center">
        <h3 class="text-xl font-bold mb-4">Success!</h3>
        <p class="mb-4">The Barangay ID has been generated successfully.</p>
        <button onclick="closeSuccessModal()" class="bg-emerald-500 text-white px-4 py-2 rounded hover:bg-emerald-600 transition">OK</button>
    </div>
</div>


<iframe id="printFrame" style="display:none;"></iframe>

<div id="paymentModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="bg-white rounded-lg overflow-hidden shadow-lg relative w-full max-w-md md:max-w-lg lg:max-w-xl">
        <button onclick="closeModal()" 
                class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        <div class="max-h-[80vh] overflow-auto">
            <img id="paymentModalImg" src="" alt="Payment Proof" 
                 class="w-full h-auto object-contain rounded-b-lg max-h-[80vh] mx-auto">
        </div>
    </div>
</div>

<div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg p-6 shadow-lg max-w-sm w-full text-center">
        <h3 class="text-xl font-bold mb-4">Success!</h3>
        <p class="mb-4">The Barangay ID has been generated successfully.</p>
        <button onclick="closeSuccessModal()" class="bg-emerald-500 text-white px-4 py-2 rounded hover:bg-emerald-600 transition">OK</button>
    </div>
</div>




<script>


function printID(pdfUrl, requestId) {
    const iframe = document.getElementById('printFrame');
    const loader = document.getElementById('loadingOverlay');

    loader.classList.remove('hidden');
    iframe.src = pdfUrl;

    iframe.onload = function() {
        loader.classList.add('hidden');

        // Open print dialog
        iframe.contentWindow.focus();
        iframe.contentWindow.print();

        // Give some delay before marking as printed (fallback for onafterprint)
        setTimeout(() => {
            fetch('mark_printed.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'request_id=' + requestId
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const btn = document.querySelector(`button[onclick="printID('${pdfUrl}', ${requestId})"]`);
                    if (btn) btn.style.display = 'none';
                } else {
                    console.error('Failed to mark printed:', data.message);
                }
            })
            .catch(err => console.error(err));
        }, 1000); // 1 second delay
    };
}


function openModal(src) {
    document.getElementById('paymentModalImg').src = src;
    document.getElementById('paymentModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('paymentModal').classList.add('hidden');
}
const toggleSidebar = document.getElementById('toggleSidebar');
const sidebar = document.getElementById('sidebar');

toggleSidebar.onclick = () => {
    sidebar.classList.toggle('sidebar-collapsed');
    toggleSidebar.textContent =
        toggleSidebar.textContent === 'chevron_left'
            ? 'chevron_right'
            : 'chevron_left';
};

document.addEventListener("DOMContentLoaded", () => {
    const forms = document.querySelectorAll("form[name='update_status_form']");

    forms.forEach(form => {
        form.addEventListener("submit", (e) => {
            const loader = document.getElementById("loadingOverlay");
            loader.classList.remove("hidden");
        });
    });
});


document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if(urlParams.get('email_sent') === '1') {
        document.getElementById('successModal').classList.remove('hidden');
    }
});

function closeSuccessModal() {
    document.getElementById('successModal').classList.add('hidden');
}
</script>

</body>
</html>
