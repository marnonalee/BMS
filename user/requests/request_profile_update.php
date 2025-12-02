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
$role = $user['role'];

$settingsQuery = $conn->query("SELECT barangay_name, system_logo FROM system_settings LIMIT 1");
$settings = $settingsQuery->fetch_assoc();
$barangayName = $settings['barangay_name'] ?? 'Barangay Name';
$systemLogo = $settings['system_logo'] ?? 'default-logo.png';
$systemLogoPath = '../' . $systemLogo;
$requestsQuery = $conn->query("
    SELECT pur.*, r.first_name, r.last_name, r.resident_address
    FROM profile_update_requests pur
    LEFT JOIN residents r ON pur.resident_id = r.resident_id
    WHERE pur.status = 'Pending' AND r.is_archived = 0
    ORDER BY pur.created_at DESC
");

// New query to fetch approved requests
$approvedRequestsQuery = $conn->query("
    SELECT pur.*, r.first_name, r.last_name, r.resident_address
    FROM profile_update_requests pur
    LEFT JOIN residents r ON pur.resident_id = r.resident_id
    WHERE pur.status = 'Approved' AND r.is_archived = 0
    ORDER BY pur.created_at DESC
");

function logActivity($conn, $user_id, $action, $description = null) {
    $stmt = $conn->prepare("INSERT INTO log_activity (user_id, action, description, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $user_id, $action, $description);
    $stmt->execute();
    $stmt->close();
}

function sendNotification($conn, $resident_id, $message, $title = "Profile Update Request", $from_role = "system", $type = "general", $priority = "normal", $action_type = "updated") {
    $stmt = $conn->prepare("INSERT INTO notifications (resident_id, message, from_role, title, type, priority, action_type, is_read, sent_email, date_created) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, NOW())");
    $stmt->bind_param("issssss", $resident_id, $message, $from_role, $title, $type, $priority, $action_type);
    $stmt->execute();
    $stmt->close();
}

if (isset($_POST['approve_request'])) {
    $request_id = $_POST['request_id'];
    $reqQuery = $conn->prepare("SELECT * FROM profile_update_requests WHERE request_id=?");
    $reqQuery->bind_param("i", $request_id);
    $reqQuery->execute();
    $req = $reqQuery->get_result()->fetch_assoc() ?? [];

    if ($req) {
        $resident_id = $req['resident_id'];
        $full_name = $conn->query("SELECT CONCAT(first_name, ' ', last_name) AS name FROM residents WHERE resident_id=$resident_id")->fetch_assoc()['name'];

        // Handle family head / household logic (existing code)...
        if ($req['is_family_head']) {
            $barangayCode = '7-61';
            $lastHousehold = $conn->query("SELECT family_id FROM households WHERE family_id LIKE '$barangayCode-%' ORDER BY family_id DESC LIMIT 1")->fetch_assoc();
            $newNumber = ($lastHousehold && !empty($lastHousehold['family_id'])) ? str_pad((int)substr($lastHousehold['family_id'], -4) + 1, 4, '0', STR_PAD_LEFT) : '0001';
            $familyId = $barangayCode . '-' . $newNumber;

            $check = $conn->query("SELECT * FROM households WHERE head_resident_id=$resident_id");
            $household_address = $req['resident_address'] ?? '';
            $street = $req['street'] ?? '';
            if ($check->num_rows > 0) {
                $conn->query("UPDATE households SET family_id='$familyId', household_address='$household_address', street='$street' WHERE head_resident_id=$resident_id");
                $household_id = $conn->query("SELECT household_id FROM households WHERE head_resident_id=$resident_id")->fetch_assoc()['household_id'] ?? 0;
            } else {
                $conn->query("INSERT INTO households (family_id, head_resident_id, household_address, street, date_created) VALUES ('$familyId', $resident_id, '$household_address', '$street', NOW())");
                $household_id = $conn->insert_id ?? 0;
            }
            $req['household_id'] = $household_id;
        }

        // Update resident profile (existing logic)...
        $residentQuery = $conn->prepare("SELECT * FROM residents WHERE resident_id = ?");
        $residentQuery->bind_param("i", $resident_id);
        $residentQuery->execute();
        $current = $residentQuery->get_result()->fetch_assoc() ?? [];

        $updateFields = [];
        $types = '';
        $values = [];
        $booleanFields = ['age','is_senior','is_pwd','is_4ps','is_solo_parent','profile_completed','is_archived','is_family_head','household_id'];

        foreach ($req as $key => $value) {
            if (in_array($key, ['request_id','resident_id','status','created_at'])) continue;
            $currentVal = $current[$key] ?? null;
            if (in_array($key, $booleanFields) && $value === null) $value = $currentVal;
            if ($currentVal != $value) {
                $updateFields[] = "$key=?";
                $types .= in_array($key, $booleanFields) ? 'i' : 's';
                $values[] = $value;
            }
        }

        if (count($updateFields) > 0) {
            $values[] = $resident_id;
            $types .= 'i';
            $sql = "UPDATE residents SET " . implode(',', $updateFields) . " WHERE resident_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$values);
            $stmt->execute();
        }

        $conn->query("UPDATE profile_update_requests 
              SET status='Approved', updated_at=NOW() 
              WHERE request_id='$request_id'");


        logActivity($conn, $user_id, 'Approve Profile Update', "Approved profile update request for $full_name");
        sendNotification($conn, $resident_id, "Ang iyong profile update request ay na-approve.", "Profile Update Approved");

        $_SESSION['success'] = "Request approved and resident profile updated!";
    }

    header("Location: request_profile_update.php");
    exit();
}

if (isset($_POST['reject_request'])) {
    $request_id = $_POST['request_id'];
    $resident_id = $conn->query("SELECT resident_id FROM profile_update_requests WHERE request_id=$request_id")->fetch_assoc()['resident_id'];
    $full_name = $conn->query("SELECT CONCAT(first_name, ' ', last_name) AS name FROM residents WHERE resident_id=$resident_id")->fetch_assoc()['name'];

    $conn->query("UPDATE profile_update_requests SET status='Rejected' WHERE request_id='$request_id'");

    logActivity($conn, $user_id, 'Reject Profile Update', "Rejected profile update request for $full_name");
    sendNotification($conn, $resident_id, "Ang iyong profile update request ay na-reject.", "Profile Update Rejected");

    $_SESSION['success'] = "Request rejected successfully!";
    header("Location: request_profile_update.php");
    exit();
}
$requestsQuery = $conn->query("
    SELECT pur.*, r.first_name, r.last_name, r.resident_address
    FROM profile_update_requests pur
    LEFT JOIN residents r ON pur.resident_id = r.resident_id
    WHERE pur.status = 'Pending' AND r.is_archived = 0
    ORDER BY pur.created_at DESC
");

?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Profile Update Requests</title>
<link rel="stylesheet" href="a.css">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans">

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
        <a href="../residents/resident.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
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
            <a href="../requests/request_profile_update.php" class="flex items-center px-4 py-3 rounded-md bg-white/10 backdrop-blur-sm transition-all">
                <span class="material-icons mr-3">pending_actions</span><span class="sidebar-text">Profile Update Requests</span>
            </a>
        </div>
        <?php endif; ?>

        <!-- Community -->
        <?php if($role === 'admin'): ?>
        <div class="pt-4">
            <span class="px-4 py-2 text-white/70 uppercase text-xs tracking-wider sidebar-text">Community</span>
            <a href="../announcements.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
                <span class="material-icons mr-3">campaign</span><span class="sidebar-text">Announcements</span>
            </a>
            <a href="../news_updates.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 transition-colors">
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

<div class="flex-1 flex flex-col">

 <header class="flex items-center justify-between bg-white shadow-md px-6 py-4 rounded-b-2xl flex-shrink-0 mb-6">
    <h2 class="text-2xl font-bold text-gray-700">Profile Update Request</h2>
</header>
<main class="flex-1 overflow-y-auto p-6 bg-gray-50">
    <div class="max-w-7xl mx-auto space-y-8">

        <!-- Pending Requests -->
        <?php if($requestsQuery->num_rows > 0): ?>
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Pending Profile Update Requests</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php while($req = $requestsQuery->fetch_assoc()): ?>
                    <?php
                    $residentQuery = $conn->prepare("SELECT * FROM residents WHERE resident_id = ?");
                    $residentQuery->bind_param("i", $req['resident_id']);
                    $residentQuery->execute();
                    $current = $residentQuery->get_result()->fetch_assoc();

                    $fields = [
                        'alias'=>'Alias',
                        'suffix'=>'Suffix',
                        'resident_address'=>'Address',
                        'birth_place'=>'Birth Place',
                        'street'=>'Street',
                        'citizenship'=>'Citizenship',
                        'voter_status'=>'Voter Status',
                        'employment_status'=>'Employment Status',
                        'contact_number'=>'Contact Number',
                        'religion'=>'Religion',
                        'profession_occupation'=>'Profession/Occupation',
                        'educational_attainment'=>'Education',
                        'education_details'=>'Education Details',
                        'is_family_head'=>'Family Head'
                    ];

                    $changes = [];
                    foreach($fields as $key=>$label){
                        $currentVal = $current[$key] ?? '';
                        $newVal = $req[$key] ?? '';
                        if($currentVal !== $newVal){
                            $changes[] = ['label'=>$label,'current'=>$currentVal,'new'=>$newVal];
                        }
                    }
                    ?>
                    <div class="bg-white p-6 rounded-2xl shadow-md hover:shadow-lg transition flex flex-col justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($req['first_name'].' '.$req['last_name']) ?></h3>
                            <p class="text-gray-500 mt-1"><?= htmlspecialchars($req['resident_address']) ?></p>
                            <p class="text-gray-400 text-sm mt-1">Requested: <?= date('F j, Y', strtotime($req['created_at'])) ?></p>
                        </div>

                        <?php if(count($changes) > 0 || !empty($req['resident_id_file'])): ?>
                            <button onclick="openModal('modal<?= $req['request_id'] ?>')" class="mt-4 w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">View Changes</button>
                        <?php endif; ?>

                        <!-- Modal -->
                        <div id="modal<?= $req['request_id'] ?>" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
                            <div class="bg-white w-11/12 md:w-2/3 p-6 rounded-2xl max-h-[85vh] overflow-y-auto relative shadow-2xl flex flex-col gap-4">
                                <button onclick="closeModal('modal<?= $req['request_id'] ?>')" class="absolute top-4 right-4 text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
                                <h3 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($req['first_name'].' '.$req['last_name']) ?></h3>
                                <p class="text-gray-500"><?= htmlspecialchars($req['resident_address']) ?></p>
                                <p class="text-gray-400 text-sm mb-4">Requested on: <?= date('F j, Y', strtotime($req['created_at'])) ?></p>

                                <div class="flex flex-col gap-3">
                                    <?php if(count($changes) > 0): ?>
                                        <?php foreach($changes as $change): ?>
                                            <div class="border rounded-lg p-4 bg-red-50 border-red-300">
                                                <p class="text-gray-500 text-sm mb-1 font-medium"><?= $change['label'] ?>:</p>
                                                <p class="text-gray-700 text-sm">Current: <span class="font-medium"><?= htmlspecialchars($change['current']) ?: '-' ?></span></p>
                                                <p class="text-red-600 font-semibold">Requested: <?= htmlspecialchars($change['new']) ?: '-' ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-gray-600 text-center py-4">No changes detected.</p>
                                    <?php endif; ?>

                                    <?php if(!empty($req['resident_id_file'])): ?>
                                        <div>
                                            <p class="text-gray-500 text-sm mb-1 font-medium">Proof / Uploaded ID:</p>
                                            <img src="../../resident/uploads/<?= htmlspecialchars($req['resident_id_file']) ?>" alt="Proof File" class="w-full max-h-96 object-contain border rounded-lg shadow-sm">
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mt-4 flex flex-col md:flex-row gap-2">
                                    <form method="POST" class="flex-1">
                                        <input type="hidden" name="request_id" value="<?= $req['request_id'] ?>">
                                        <button type="submit" name="approve_request" class="w-full px-3 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">Approve</button>
                                    </form>
                                    <form method="POST" class="flex-1">
                                        <input type="hidden" name="request_id" value="<?= $req['request_id'] ?>">
                                        <button type="submit" name="reject_request" class="w-full px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">Reject</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p class="text-gray-500 text-center py-12 text-lg">No pending profile update requests.</p>
        <?php endif; ?>

        <!-- Approved Requests Table -->
        <div class="bg-white p-6 rounded-2xl shadow-lg overflow-x-auto">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Approved Profile Update Requests</h2>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-gray-700 font-medium">Resident Name</th>
                        <th class="px-4 py-3 text-left text-gray-700 font-medium">Address</th>
                        <th class="px-4 py-3 text-left text-gray-700 font-medium">Requested Changes</th>
                        <th class="px-4 py-3 text-left text-gray-700 font-medium">Requested On</th>
                        <th class="px-4 py-3 text-left text-gray-700 font-medium">Approved On</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if($approvedRequestsQuery->num_rows > 0): ?>
                        <?php while($req = $approvedRequestsQuery->fetch_assoc()): ?>
                            <tr>
                                <td class="px-4 py-3"><?= htmlspecialchars($req['first_name'].' '.$req['last_name']) ?></td>
                                <td class="px-4 py-3"><?= htmlspecialchars($req['resident_address']) ?></td>
                                <td class="px-4 py-3">
                                    <?php
                                    $residentQuery = $conn->prepare("SELECT * FROM residents WHERE resident_id = ?");
                                    $residentQuery->bind_param("i", $req['resident_id']);
                                    $residentQuery->execute();
                                    $current = $residentQuery->get_result()->fetch_assoc();

                                    $fields = [
                                        'alias'=>'Alias',
                                        'suffix'=>'Suffix',
                                        'resident_address'=>'Address',
                                        'birth_place'=>'Birth Place',
                                        'street'=>'Street',
                                        'citizenship'=>'Citizenship',
                                        'voter_status'=>'Voter Status',
                                        'employment_status'=>'Employment Status',
                                        'contact_number'=>'Contact Number',
                                        'religion'=>'Religion',
                                        'profession_occupation'=>'Profession/Occupation',
                                        'educational_attainment'=>'Education',
                                        'education_details'=>'Education Details',
                                        'is_family_head'=>'Family Head'
                                    ];

                                    $changes = [];
                                    foreach($fields as $key=>$label){
                                        $currentVal = $current[$key] ?? '';
                                        $newVal = $req[$key] ?? '';
                                        if($currentVal !== $newVal){
                                            $changes[] = $label;
                                        }
                                    }

                                    echo $changes ? implode(', ', $changes) : '-';
                                    ?>
                                </td>
                                <td class="px-4 py-3"><?= date('F j, Y', strtotime($req['created_at'])) ?></td>
                                <td class="px-4 py-3"><?= date('F j, Y', strtotime($req['updated_at'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td class="px-4 py-3 text-center text-gray-500" colspan="5">No approved profile update requests yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</main>



</div>
</div>

<div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
  <div class="bg-white rounded-xl p-6 w-80 text-center relative">
    <button id="closeSuccessModal" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 material-icons">close</button>
    <span id="successIcon" class="material-icons text-4xl text-green-500">check_circle</span>
    <p id="successMessage" class="mt-2"><?= isset($_SESSION['success']) ? htmlspecialchars($_SESSION['success']) : '' ?></p>
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

function openModal(id){
    document.getElementById(id).classList.remove('hidden');
    document.getElementById(id).classList.add('flex');
}
function closeModal(id){
    document.getElementById(id).classList.remove('flex');
    document.getElementById(id).classList.add('hidden');
}

// Show success modal if session success exists
<?php if(isset($_SESSION['success'])): ?>
openModal('successModal');
<?php unset($_SESSION['success']); endif; ?>

document.getElementById('closeSuccessModal').addEventListener('click', () => closeModal('successModal'));
document.getElementById('okSuccessModal').addEventListener('click', () => closeModal('successModal'));
</script>
</body>
</html>
