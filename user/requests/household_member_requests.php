<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../../login.php");
    exit();
}

include '../db.php';
$user_id = $_SESSION["user_id"];

// Fetch logged-in user
$stmtUser = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();
$role = $user['role'];

// System settings
$settingsQuery = $conn->query("SELECT barangay_name, system_logo FROM system_settings LIMIT 1");
$settings = $settingsQuery->fetch_assoc();
$barangayName = $settings['barangay_name'] ?? 'Barangay Name';
$systemLogo = $settings['system_logo'] ?? 'default-logo.png';
$systemLogoPath = '../' . $systemLogo;

// Helper functions
function logActivity($conn, $user_id, $action, $description = null) {
    $stmt = $conn->prepare("INSERT INTO log_activity (user_id, action, description, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $user_id, $action, $description);
    $stmt->execute();
    $stmt->close();
}

function sendNotification($conn, $resident_id, $message, $title = "Family Member Request", $from_role = "system", $type = "general", $priority = "normal", $action_type = "updated") {
    $stmt = $conn->prepare("INSERT INTO notifications (resident_id, message, from_role, title, type, priority, action_type, is_read, sent_email, date_created) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, NOW())");
    $stmt->bind_param("issssss", $resident_id, $message, $from_role, $title, $type, $priority, $action_type);
    $stmt->execute();
    $stmt->close();
}

// Approve request
if (isset($_POST['approve_request'])) {
    $request_id = $_POST['request_id'];

    $stmt = $conn->prepare("
        SELECT fmr.member_resident_id, fmr.household_head_id, fmr.relationship,
               CONCAT(r.first_name,' ',r.last_name) AS member_name,
               CONCAT(hh.first_name,' ',hh.last_name) AS head_name
        FROM family_member_requests fmr
        JOIN residents r ON fmr.member_resident_id = r.resident_id
        JOIN residents hh ON fmr.household_head_id = hh.resident_id
        WHERE fmr.request_id=? AND r.is_archived=0 AND hh.is_archived=0
    ");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $req = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($req) {
        $stmtHouse = $conn->prepare("SELECT household_id FROM households WHERE head_resident_id=?");
        $stmtHouse->bind_param("i", $req['household_head_id']);
        $stmtHouse->execute();
        $household_id = $stmtHouse->get_result()->fetch_assoc()['household_id'] ?? 0;
        $stmtHouse->close();

        if ($household_id) {
            $stmtUpd = $conn->prepare("UPDATE residents SET household_id=? WHERE resident_id=?");
            $stmtUpd->bind_param("ii", $household_id, $req['member_resident_id']);
            $stmtUpd->execute();
            $stmtUpd->close();

            $stmtReq = $conn->prepare("UPDATE family_member_requests SET status='approved' WHERE request_id=?");
            $stmtReq->bind_param("i", $request_id);
            $stmtReq->execute();
            $stmtReq->close();

            logActivity($conn, $user_id, 'Approve Family Member Request', "Approved family member request for ".$req['member_name']);
            sendNotification($conn, $req['member_resident_id'], "Ang iyong request na maging miyembro ng household ni ".$req['head_name']." ay na-approve.", "Family Member Request Approved");

            $_SESSION['success'] = "Family member request approved!";
        }
    }
    header("Location: household_member_requests.php");
    exit();
}

// Reject request
if (isset($_POST['reject_request'])) {
    $request_id = $_POST['request_id'];

    $stmt = $conn->prepare("
        SELECT fmr.member_resident_id, CONCAT(r.first_name,' ',r.last_name) AS member_name
        FROM family_member_requests fmr
        JOIN residents r ON fmr.member_resident_id = r.resident_id
        WHERE fmr.request_id=? AND r.is_archived=0
    ");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $req = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($req) {
        $stmtUpd = $conn->prepare("UPDATE family_member_requests SET status='rejected' WHERE request_id=?");
        $stmtUpd->bind_param("i", $request_id);
        $stmtUpd->execute();
        $stmtUpd->close();

        logActivity($conn, $user_id, 'Reject Family Member Request', "Rejected family member request for ".$req['member_name']);
        sendNotification($conn, $req['member_resident_id'], "Ang iyong request na maging miyembro ng household ay na-reject.", "Family Member Request Rejected");

        $_SESSION['success'] = "Family member request rejected!";
    }
    header("Location: household_member_requests.php");
    exit();
}

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$totalQuery = $conn->query("SELECT COUNT(*) as total FROM family_member_requests WHERE status='pending'");
$totalRows = $totalQuery->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

$stmtReqs = $conn->prepare("
    SELECT fmr.*, 
           r.first_name AS member_first_name, r.last_name AS member_last_name,
           hh.first_name AS head_first_name, hh.last_name AS head_last_name,
           hh.resident_address AS head_address
    FROM family_member_requests fmr
    LEFT JOIN residents r ON fmr.member_resident_id = r.resident_id
    LEFT JOIN residents hh ON fmr.household_head_id = hh.resident_id
    WHERE fmr.status='pending' AND r.is_archived=0 AND hh.is_archived=0
    ORDER BY fmr.date_created DESC
    LIMIT ?, ?
");

$stmtReqs->bind_param("ii", $offset, $limit);
$stmtReqs->execute();
$requestsQuery = $stmtReqs->get_result();
$stmtReqs->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Household Member Requests</title>
<link rel="stylesheet" href="a.css">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans">

<div class="flex h-screen">
<aside id="sidebar" class="w-64 bg-gradient-to-b from-blue-500 to-blue-700 text-white flex flex-col shadow-xl transition-all duration-300 h-screen">
    <div class="flex items-center justify-between p-4 border-b border-white/20">
        <div class="flex items-center space-x-3"><img src="<?= htmlspecialchars($systemLogoPath) ?>" alt="Barangay Logo" class="w-16 h-16 rounded-full object-cover shadow-sm border-2 border-white transition-all">
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
            <a href="../requests/household_member_requests.php" class="flex items-center px-4 py-3 rounded-md bg-white/10 backdrop-blur-sm transition-all">
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
<div class="flex-1 flex flex-col overflow-hidden bg-gray-50">
<header class="flex items-center justify-between bg-white shadow-md px-6 py-4 rounded-b-2xl flex-shrink-0 mb-6">
    <h2 class="text-2xl font-bold text-gray-700">Household Member Requests</h2>
</header>

<main class="flex-1 overflow-y-auto p-6">
  <div class="max-w-7xl mx-auto">
    <div class="bg-white p-6 rounded-2xl shadow-lg overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200" id="householdMembersTable">
          <thead class="bg-gray-100">
              <tr>
                  <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Member Name</th>
                  <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Relationship</th>
                  <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Birthdate</th>
                  <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Requested By</th>
                  <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Head Address</th>
                  <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Request Date</th>
                  <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
              <?php if($requestsQuery->num_rows > 0): ?>
                  <?php while($req = $requestsQuery->fetch_assoc()): ?>
                  <tr class="hover:bg-gray-50 transition cursor-pointer">
                      <td class="px-6 py-4"><?= htmlspecialchars($req['member_first_name'].' '.$req['member_last_name']) ?></td>
                      <td class="px-6 py-4"><?= htmlspecialchars($req['relationship']) ?></td>
                      <td class="px-6 py-4"><?= date('F j, Y', strtotime($req['birthdate'])) ?></td>
                      <td class="px-6 py-4"><?= htmlspecialchars($req['head_first_name'].' '.$req['head_last_name']) ?></td>
                      <td class="px-6 py-4"><?= htmlspecialchars($req['head_address']) ?></td>
                      <td class="px-6 py-4"><?= date('F j, Y', strtotime($req['date_created'])) ?></td>
                      <td class="px-6 py-4 text-center flex justify-center gap-2">
                          <form method="POST" class="inline">
                              <input type="hidden" name="request_id" value="<?= $req['request_id'] ?>">
                              <button type="submit" name="approve_request" class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 transition text-sm">Approve</button>
                          </form>
                          <form method="POST" class="inline">
                              <input type="hidden" name="request_id" value="<?= $req['request_id'] ?>">
                              <button type="submit" name="reject_request" class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 transition text-sm">Reject</button>
                          </form>
                      </td>
                  </tr>
                  <?php endwhile; ?>
              <?php else: ?>
                  <tr>
                      <td colspan="7" class="px-6 py-4 text-center text-gray-400">No pending household member requests.</td>
                  </tr>
              <?php endif; ?>
          </tbody>
      </table>


      <?php if($totalPages > 1): ?>
        <div class="flex justify-center mt-6 space-x-2">
          <?php if($page > 1): ?>
            <a href="?page=<?= $page-1 ?>" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 transition">Prev</a>
          <?php endif; ?>
          <?php for($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?>" class="px-4 py-2 rounded-lg <?= ($i == $page) ? 'bg-emerald-500 text-white' : 'bg-gray-200 hover:bg-gray-300' ?> transition"><?= $i ?></a>
          <?php endfor; ?>
          <?php if($page < $totalPages): ?>
            <a href="?page=<?= $page+1 ?>" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 transition">Next</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>

</div>
</div>

<div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
  <div class="bg-white rounded-xl p-6 w-80 text-center relative">
    <button id="closeSuccessModal" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 material-icons">close</button>
    <span class="material-icons text-4xl text-green-500">check_circle</span>
    <p class="mt-2"><?= isset($_SESSION['success']) ? htmlspecialchars($_SESSION['success']) : '' ?></p>
    <button id="okSuccessModal" class="mt-3 bg-emerald-500 text-white px-4 py-2 rounded">OK</button>
  </div>
</div>

<script>
const toggleSidebar = document.getElementById('toggleSidebar');
const sidebar = document.getElementById('sidebar');
toggleSidebar.addEventListener('click', () => {
    sidebar.classList.toggle('sidebar-collapsed');
    toggleSidebar.textContent = toggleSidebar.textContent === 'chevron_left' ? 'chevron_right' : 'chevron_left';
});

<?php if(isset($_SESSION['success'])): ?>
document.getElementById('successModal').classList.remove('hidden');
<?php unset($_SESSION['success']); endif; ?>

document.getElementById('closeSuccessModal').addEventListener('click', () => document.getElementById('successModal').classList.add('hidden'));
document.getElementById('okSuccessModal').addEventListener('click', () => document.getElementById('successModal').classList.add('hidden'));
</script>
</body>
</html>
