<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../../login.php");
    exit();
}
include '../db.php';
include 'send_certificate_email.php';

$user_id = $_SESSION["user_id"];
$userQuery = $conn->query("SELECT * FROM users WHERE id = '$user_id'");
$user = $userQuery->fetch_assoc();
$role = $user['role'];

$perPage = isset($_GET['perPage']) ? (int)$_GET['perPage'] : 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $perPage;

$conn->query("UPDATE generated_certificates SET is_archived = 1 WHERE is_archived = 0 AND date_generated <= DATE_SUB(NOW(), INTERVAL 7 DAY)");

$totalRequests = $conn->query("SELECT COUNT(*) AS cnt FROM certificate_requests")->fetch_assoc()['cnt'];
$totalPages = ceil($totalRequests / $perPage);

$requestsQuery = $conn->query("
    SELECT cr.*, r.first_name, r.last_name, r.birthdate, r.resident_address, r.email_address, cr.supporting_doc, ct.template_name
    FROM certificate_requests cr
    JOIN residents r ON cr.resident_id = r.resident_id
    JOIN certificate_templates ct ON cr.template_id = ct.id
    ORDER BY cr.date_requested DESC
    LIMIT $start, $perPage
");

// Helper functions
function logActivity($conn, $user_id, $action, $description = null) {
    $stmt = $conn->prepare("INSERT INTO log_activity (user_id, action, description, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $user_id, $action, $description);
    $stmt->execute();
    $stmt->close();
}

function sendNotification($conn, $resident_id, $message, $title = "Certificate Request", $from_role = "system", $type = "certificate", $priority = "normal", $action_type = "updated") {
    $stmt = $conn->prepare("INSERT INTO notifications (resident_id, message, from_role, title, type, priority, action_type, is_read, sent_email, date_created) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, NOW())");
    $stmt->bind_param("issssss", $resident_id, $message, $from_role, $title, $type, $priority, $action_type);
    $stmt->execute();
    $stmt->close();
}

if (isset($_POST['update_status'])) {
    $requestId = intval($_POST['request_id']);
    $statusInput = $_POST['status'] ?? '';
    $newStatus = $statusInput === 'Approved' ? 'Ready for Pickup' : $statusInput;
    $remarks = trim($_POST['remarks'] ?? '');
    if ($remarks === '') $remarks = 'No Issue';

    $reviewed_by = $_SESSION['user_id'] ?? 0;
    $date_reviewed = date('Y-m-d H:i:s');

    // Update request status
    $stmt = $conn->prepare("
        UPDATE certificate_requests 
        SET status = ?, remarks = ?, date_reviewed = ?, reviewed_by = ? 
        WHERE id = ?
    ");
    $stmt->bind_param("sssii", $newStatus, $remarks, $date_reviewed, $reviewed_by, $requestId);
    $stmt->execute();
    $stmt->close();

    // Log activity
    $action = $newStatus === 'Ready for Pickup' ? 'Approved Certificate Request' : 'Rejected Certificate Request';
    $description = "Request ID $requestId was $newStatus. Remarks: $remarks";
    logActivity($conn, $reviewed_by, $action, $description);

    // Get resident info
    $reqData = $conn->query("SELECT resident_id, purpose FROM certificate_requests WHERE id = $requestId")->fetch_assoc();
    $residentId = $reqData['resident_id'];
    $purpose = $reqData['purpose'] ?? 'General';

    $residentData = $conn->query("SELECT email_address, first_name, last_name FROM residents WHERE resident_id = $residentId")->fetch_assoc();
    $fullName = $residentData['first_name'] . ' ' . $residentData['last_name'];
    $issuedDate = date("F d, Y");

    // Prepare PDF path (optional: pass null if you generate later)
    $pdfPath = null;
    $filename = $newStatus === 'Ready for Pickup' ? 'Certificate_Ready.pdf' : 'Certificate_Rejected.pdf';

    // Send email via PHPMailer
    sendCertificateEmail(
        $residentData['email_address'],
        $fullName,
        $purpose,
        $issuedDate,
        $pdfPath,
        $filename,
        $residentId
    );

    // Send notification in system
    if ($newStatus === 'Ready for Pickup') {
        $notificationMessage = "Ang iyong certificate request ay na-approve at handa na para sa pickup.";
        $notificationTitle = "Certificate Request Approved";
    } else {
        $notificationMessage = "Ang iyong certificate request ay na-reject. Remarks: $remarks";
        $notificationTitle = "Certificate Request Rejected";
    }

    sendNotification(
        $conn,
        $residentId,
        $notificationMessage,
        $notificationTitle
    );

    header("Location: certificate_requests.php");
    exit;
}

// System settings
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
<title>Certificate Requests</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link rel="stylesheet" href="cert.css">

</head>
<body class=" font-sans">
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

    <a href="../households/household.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
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
      <a href="../certificate/certificate_requests.php" class="flex items-center px-4 py-3 rounded-md bg-white/10 backdrop-blur-sm transition-all">
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
      <a href="../id/barangay_id_requests.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
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

<div class="flex-1 flex flex-col bg-gray-50 overflow-hidden">

  <header class="flex-shrink-0 flex items-center justify-between bg-white shadow-md px-6 py-4 rounded-b-2xl mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Certificate Requests</h2>
     <div class="flex items-center space-x-3">
        <input type="text" id="searchInput" placeholder="Search requests..."
               class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400 text-sm transition">
      </div>
  </header>

<main class="flex-1 overflow-y-auto p-6">

      <div class="bg-white p-6 rounded-2xl shadow-lg overflow-x-auto">
        <div class="flex justify-start items-center mb-4 space-x-2">
          <span class="text-gray-600 font-medium">Show</span>
          <select id="perPageSelect" class="border border-gray-300 px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400">
            <option value="50" <?= $perPage==50?'selected':'' ?>>50</option>
            <option value="100" <?= $perPage==100?'selected':'' ?>>100</option>
            <option value="200" <?= $perPage==200?'selected':'' ?>>200</option>
          </select>
          <span class="text-gray-600 font-medium">entries</span>
        </div>

        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Resident</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Certificate</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Purpose</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Request Type</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Requested On</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Supporting Document</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody id="requestsTable" class="bg-white divide-y divide-gray-200">
              <?php if($requestsQuery->num_rows > 0): ?>
                  <?php while($req = $requestsQuery->fetch_assoc()): ?>
                  <tr class="hover:bg-gray-50 transition-colors duration-200 cursor-pointer 
                    <?php if($req['status'] === 'Pending') echo 'bg-yellow-50 pendingRow'; ?>"
                    data-resident="<?= htmlspecialchars($req['first_name'] . ' ' . $req['last_name']) ?>"
                    data-birthdate="<?= htmlspecialchars($req['birthdate']) ?>"
                    data-address="<?= htmlspecialchars($req['resident_address']) ?>"
                    data-doc="<?= $req['supporting_doc'] ? '../../resident/uploads/valid_ids/' . $req['supporting_doc'] : '' ?>">

                    <td class="px-6 py-4"><?= htmlspecialchars($req['first_name'] . ' ' . $req['last_name']) ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($req['template_name']) ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($req['purpose']) ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($req['request_type']) ?></td>
                    <td class="px-6 py-4 font-medium text-gray-700"><?= htmlspecialchars($req['status']) ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($req['date_requested']) ?></td>
                    <td class="px-6 py-4">
                        <?php if($req['supporting_doc']): ?>
                            <span class="view-doc text-blue-500 hover:underline cursor-pointer"
                                  data-doc="<?= '../../resident/uploads/valid_ids/' . $req['supporting_doc'] ?>">
                                  Click to View
                            </span>
                        <?php else: ?>
                            <span class="text-gray-400">No document</span>
                        <?php endif; ?>
                    </td>

                    <td class="px-6 py-4">
                      <?php if($req['status'] === 'Pending'): ?>
                        <div class="flex flex-wrap gap-2">
                          <form method="POST" class="flex gap-2">
                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                            <input type="hidden" name="status" value="Approved">
                            <input type="text" name="remarks" placeholder="Issue (optional)" class="border rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-green-400">
                            <button type="submit" name="update_status" class="bg-green-500 text-white px-3 py-1 rounded-lg hover:bg-green-600 transition duration-200">Approve</button>
                          </form>
                          <form method="POST" class="flex gap-2">
                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                            <input type="hidden" name="status" value="Rejected">
                            <input type="text" name="remarks" placeholder="Reason (optional)" class="border rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-red-400">
                            <button type="submit" name="update_status" class="bg-red-500 text-white px-3 py-1 rounded-lg hover:bg-red-600 transition duration-200">Disapprove</button>
                          </form>
                        </div>
                      <?php elseif(in_array($req['status'], ['Approved', 'Ready for Pickup', 'Done'])): ?>
                        <button class="bg-emerald-500 text-white px-4 py-2 rounded-lg hover:bg-emerald-600 transition duration-200"
                                onclick="printCertificate(<?= $req['id'] ?>, '<?= htmlspecialchars($req['template_name']) ?>')">Generate</button>
                      <?php else: ?>
                        <span class="text-gray-400"><?= htmlspecialchars($req['status']) ?></span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endwhile; ?>
              <?php else: ?>
                  <tr class="text-center text-gray-400">
                      <td colspan="7">No certificate requests found.</td>
                  </tr>
              <?php endif; ?>
          </tbody>

        </table>

        <div class="flex justify-center mt-6 space-x-2">
          <?php if($page>1): ?>
            <a href="?page=<?= $page-1 ?>&perPage=<?= $perPage ?>" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 transition">Prev</a>
          <?php endif; ?>
          <?php for($i=1; $i<=$totalPages; $i++): ?>
            <a href="?page=<?= $i ?>&perPage=<?= $perPage ?>" class="px-4 py-2 rounded-lg <?= $i==$page?'bg-green-500 text-white':'bg-gray-200 hover:bg-gray-300' ?> transition"><?= $i ?></a>
          <?php endfor; ?>
          <?php if($page<$totalPages): ?>
            <a href="?page=<?= $page+1 ?>&perPage=<?= $perPage ?>" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 transition">Next</a>
          <?php endif; ?>
        </div>
      </div>
</main>


  </div>
</div>
<iframe id="printFrame" style="display:none;"></iframe>

<div id="requestModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-lg w-11/12 max-w-lg p-6 relative overflow-y-auto max-h-[90vh]">
        <button id="closeModal" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 text-xl">&times;</button>
        <h2 class="text-xl font-semibold mb-4">Resident Details</h2>
        <div id="modalContent" class="space-y-2">
            <p><strong>Name:</strong> <span id="modalResident"></span></p>
            <p><strong>Birthdate:</strong> <span id="modalBirthdate"></span></p>
            <p><strong>Household Address:</strong> <span id="modalAddress"></span></p>
            <p><strong>Supporting Document:</strong></p>
            <div id="modalDocPreview" class="border p-2 rounded bg-gray-50">
                <span id="noDocText" class="text-gray-400">No document uploaded.</span>
            </div>
        </div>
    </div>
</div>
<div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="flex flex-col items-center">
        <div class="w-16 h-16 border-4 border-white border-t-transparent rounded-full animate-spin mb-4"></div>
        <div class="text-white text-lg font-semibold">Generating Certificate, please wait...</div>
    </div>
</div>


<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="cert.js"></script>
<script>

document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('requestModal');
    const closeModalBtn = document.getElementById('closeModal');

    const modalDocPreview = document.getElementById('modalDocPreview');
    const noDocText = document.getElementById('noDocText');

    // Close button
    closeModalBtn.addEventListener('click', () => {
        modal.classList.add('hidden');
    });

    // Close when clicking outside modal content
    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.add('hidden');
    });

    // View doc click
    document.querySelectorAll('.view-doc').forEach(el => {
        el.addEventListener('click', () => {
            const docUrl = el.dataset.doc;
            modalDocPreview.innerHTML = '';

            if(docUrl) {
                const ext = docUrl.split('.').pop().toLowerCase();
                if(['jpg','jpeg','png','gif'].includes(ext)) {
                    const img = document.createElement('img');
                    img.src = docUrl;
                    img.alt = "Supporting Document";
                    img.className = "max-w-full max-h-60 rounded";
                    modalDocPreview.appendChild(img);
                } else if(ext === 'pdf') {
                    const embed = document.createElement('embed');
                    embed.src = docUrl;
                    embed.type = 'application/pdf';
                    embed.className = "w-full h-60";
                    modalDocPreview.appendChild(embed);
                } else {
                    const link = document.createElement('a');
                    link.href = docUrl;
                    link.target = "_blank";
                    link.textContent = "Open Document";
                    link.className = "text-blue-500 hover:underline";
                    modalDocPreview.appendChild(link);
                }
            } else {
                modalDocPreview.appendChild(noDocText);
            }

            modal.classList.remove('hidden');
        });
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const overlay = document.getElementById('loadingOverlay');
    const statusForms = document.querySelectorAll('form input[name="update_status"]');

    statusForms.forEach(button => {
        button.closest('form').addEventListener('submit', () => {
            overlay.classList.remove('hidden');
        });
    });
});


</script>
</body>
</html>
