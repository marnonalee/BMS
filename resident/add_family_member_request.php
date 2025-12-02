<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "resident") {
    header("Location: ../index.php");
    exit();
}
include '../db.php';

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


$residentQuery = $conn->prepare("SELECT resident_id, household_id, is_family_head FROM residents WHERE user_id = ? LIMIT 1");
$residentQuery->bind_param("i", $user_id);
$residentQuery->execute();
$resident = $residentQuery->get_result()->fetch_assoc();
$residentQuery->close();

$resident_id = $resident['resident_id'] ?? 0;
$household_id = $resident['household_id'] ?? 0;
$is_family_head = $resident['is_family_head'] ?? 0;

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

$error = '';
$success = '';
$is_blocked = false;

if ($is_approved != 1) {
    $error = "You can't request. Your account is not verified yet.";
    $is_blocked = true;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($is_blocked) {
        $error = "You can't request. Your account is not verified yet.";
    } else {

        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error = "Paki-pili ang tamang pangalan mula sa listahan ng suggestions bago isumite.";
        } else {
            $full_name = trim($_POST['full_name']);
            $relationship = trim($_POST['relationship']);
            $birthdate = trim($_POST['birthdate']);
            $sex = trim($_POST['sex']);
            $civil_status = trim($_POST['civil_status']);
            $resident_address = trim($_POST['resident_address']);
            $voter_status = trim($_POST['voter_status']);
            $member_resident_id = intval($_POST['member_resident_id'] ?? 0);
            $request_id = intval($_POST['request_id'] ?? 0);

            if (!$is_family_head) {
                $error = "Ang tanging household head lang ang puwedeng magdagdag o mag-edit ng miyembro.";
            }
            elseif ($full_name && $member_resident_id === 0) {
                $error = "Paki-pili ang pangalan mula sa listahan ng suggestions.";
            }
            elseif (!$full_name || !$relationship || !$birthdate || !$sex || !$civil_status || !$resident_address || !$voter_status) {
                $error = "Paki-fill lahat ng required na fields.";
            }
            else {
                if ($request_id) {

                    $stmt = $conn->prepare("
                        UPDATE family_member_requests
                        SET full_name = ?, relationship = ?, birthdate = ?, sex = ?, civil_status = ?, resident_address = ?, voter_status = ?, status = 'Pending'
                        WHERE request_id = ? AND household_head_id = ?
                    ");

                    $stmt->bind_param(
                        "sssssssii",
                        $full_name, $relationship, $birthdate, $sex, $civil_status,
                        $resident_address, $voter_status, $request_id, $resident_id
                    );

                    if ($stmt->execute())
                        $success = "Matagumpay na na-update ang request.";
                    else
                        $error = "Nabigo ang pag-update ng request.";

                    $stmt->close();
                }

                else {

                    if ($member_resident_id > 0) {
                        $checkStmt = $conn->prepare("
                            SELECT household_id, is_family_head, is_archived, sex, civil_status, resident_address, voter_status, birthdate 
                            FROM residents 
                            WHERE resident_id = ? AND is_archived = 0
                        ");
                        $checkStmt->bind_param("i", $member_resident_id);
                        $checkStmt->execute();
                        $member = $checkStmt->get_result()->fetch_assoc();
                        $checkStmt->close();

                        if (!$member) {
                            $error = "Hindi matagpuan ang residente o na-archive na.";
                        } elseif ($member['is_family_head']) {
                            $error = "Hindi puwedeng idagdag ang household head.";
                        } elseif ($member['household_id'] != 0) {
                            $error = "Kasali na ang residente sa ibang household.";
                        } else {
                            $sex = $member['sex'];
                            $civil_status = $member['civil_status'];
                            $resident_address = $member['resident_address'];
                            $voter_status = $member['voter_status'];
                            $birthdate = $member['birthdate'];
                        }
                    }

                    if ($member_resident_id > 0) {
                        $stmt = $conn->prepare("
                            INSERT INTO family_member_requests
                            (household_head_id, member_resident_id, full_name, relationship, birthdate, sex, civil_status, resident_address, voter_status, status, date_created)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())
                        ");

                        $stmt->bind_param(
                            "iisssssss",
                            $resident_id, $member_resident_id, $full_name, $relationship,
                            $birthdate, $sex, $civil_status, $resident_address, $voter_status
                        );

                    } else {

                        $stmt = $conn->prepare("
                            INSERT INTO family_member_requests
                            (household_head_id, full_name, relationship, birthdate, sex, civil_status, resident_address, voter_status, status, date_created)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())
                        ");

                        $stmt->bind_param(
                            "isssssss",
                            $resident_id, $full_name, $relationship, $birthdate,
                            $sex, $civil_status, $resident_address, $voter_status
                        );
                    }

                    if (!$error) {
                        if ($stmt->execute())
                            $success = "Matagumpay na naisumite ang request.";
                        else
                            $error = "Nabigo ang pagsusumite ng request.";
                    }

                    $stmt->close();
                }
            }
        }
    }
}

if (isset($_GET['delete']) && $is_family_head) {

    if ($is_blocked) {
        $error = "You can't request. Your account is not verified yet.";
    } else {
        $del_id = intval($_GET['delete']);
        $delStmt = $conn->prepare("
            DELETE FROM family_member_requests 
            WHERE request_id = ? AND household_head_id = ? AND status = 'Pending'
        ");
        $delStmt->bind_param("ii", $del_id, $resident_id);
        $delStmt->execute();

        if ($delStmt->affected_rows)
            $success = "Request deleted.";
        else
            $error = "Cannot delete request.";

        $delStmt->close();
    }
}

$requestQuery = $conn->prepare("
    SELECT 
        fmr.*,
        r.resident_id AS member_resident_id,
        CONCAT(r.first_name, ' ', IFNULL(r.middle_name,''), ' ', r.last_name) AS member_name,
        r.birthdate AS res_birthdate,
        r.sex AS res_sex,
        r.civil_status AS res_civil_status,
        r.resident_address AS res_address,
        r.voter_status AS res_voter_status
    FROM family_member_requests fmr
    LEFT JOIN residents r ON fmr.member_resident_id = r.resident_id
    WHERE fmr.household_head_id = ?
    ORDER BY fmr.date_created DESC
");
$requestQuery->bind_param("i", $resident_id);
$requestQuery->execute();
$requests = $requestQuery->get_result()->fetch_all(MYSQLI_ASSOC);
$requestQuery->close();

$settingsQuery = $conn->query("SELECT facebook_link FROM system_settings LIMIT 1");
$settings = $settingsQuery->fetch_assoc();
$facebookLink = $settings['facebook_link'] ?? '#';

?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Family Member Requests</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
<main class="container mx-auto mt-10 max-w-6xl px-4">
    <div class="mb-6 flex items-center space-x-2 text-gray-500 text-sm">
        <a href="dashboard.php" class="hover:underline">Dashboard</a>
        <span class="text-gray-300">/</span>
        <span class="font-semibold text-gray-700">Family Member Requests</span>
    </div>
    <div class="flex flex-col lg:flex-row gap-8">
        <div class="flex-1 p-8 bg-white rounded-3xl shadow-xl">
            <?php if($success): ?>
                <p class="bg-green-100 text-green-700 p-3 rounded mb-4"><?= htmlspecialchars($success) ?></p>
            <?php endif; ?>
            <?php if($error): ?>
                <p class="bg-red-100 text-red-700 p-3 rounded mb-4"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <p class="bg-yellow-50 border-l-4 border-yellow-500 text-yellow-800 p-3 rounded mb-6">
                Paalala: Ang maaari lamang idagdag bilang miyembro ng pamilya ay yung nasa <strong>listahan ng residente</strong>.
                Kung wala, makipag-ugnayan sa barangay sa kanilang 
                <a href="<?= htmlspecialchars($facebookLink) ?>" target="_blank" class="text-blue-600 underline">Facebook page</a>.
            </p>
          <form method="POST" class="space-y-5" id="memberForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <input type="hidden" name="request_id" id="request_id">
                <input type="hidden" name="member_resident_id" id="member_resident_id">

                <div class="relative">
                    <label class="block font-semibold text-gray-700">Full Name</label>
                    <input type="text" id="full_name" name="full_name" autocomplete="off" 
                        class="w-full mt-2 p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    <ul id="suggestions" 
                        class="absolute z-50 w-full bg-white border rounded-lg mt-1 max-h-48 overflow-y-auto hidden shadow-md"></ul>
                </div>

                <div>
                    <label class="block font-semibold text-gray-700">Relationship</label>
                    <input type="text" id="relationship" name="relationship" 
                        class="w-full mt-2 p-3 border rounded-lg focus:ring-2 focus:ring-blue-500" required>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-semibold text-gray-700">Birthdate</label>
                        <input type="date" id="birthdate" name="birthdate" 
                            class="w-full mt-2 p-3 border rounded-lg bg-gray-50" readonly>
                    </div>
                    <div>
                        <label class="block font-semibold text-gray-700">Sex</label>
                        <input type="text" id="sex" name="sex" 
                            class="w-full mt-2 p-3 border rounded-lg bg-gray-50" readonly>
                    </div>
                    <div>
                        <label class="block font-semibold text-gray-700">Civil Status</label>
                        <input type="text" id="civil_status" name="civil_status" 
                            class="w-full mt-2 p-3 border rounded-lg bg-gray-50" readonly>
                    </div>
                    <div>
                        <label class="block font-semibold text-gray-700">Voter Status</label>
                        <input type="text" id="voter_status" name="voter_status" 
                            class="w-full mt-2 p-3 border rounded-lg bg-gray-50" readonly>
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-gray-700">Address</label>
                    <input type="text" id="resident_address" name="resident_address" 
                        class="w-full mt-2 p-3 border rounded-lg bg-gray-50" readonly>
                </div>

                <div class="flex justify-end">
                    <button type="submit" 
                            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        Submit
                    </button>
                </div>

            </form>

        </div>
        <div class="w-full lg:w-1/3 p-6 bg-white rounded-3xl shadow-xl h-fit">
            <h2 class="text-lg font-bold mb-4 text-gray-800">Your Family Member Requests</h2>
            <?php if(count($requests) > 0): ?>
                <ul class="space-y-3 max-h-[500px] overflow-y-auto">
                    <?php foreach($requests as $req): ?>
                        <li class="p-4 border rounded-lg bg-gray-50 flex justify-between items-start hover:bg-gray-100 transition">
                            <div class="flex-1">
                                <p class="font-semibold text-gray-800"><?= htmlspecialchars($req['member_name'] ?? $req['full_name']) ?></p>
                                <p class="text-gray-700 text-sm">Relationship: <?= htmlspecialchars($req['relationship']) ?></p>
                                <p class="text-gray-700 text-sm">Birthdate: <?= htmlspecialchars($req['birthdate']) ?></p>
                                <p class="text-gray-400 text-xs mt-1">Requested on: <?= date("M d, Y", strtotime($req['date_created'])) ?></p>
                                <p class="text-sm font-medium <?= $req['status']==='Approved'?'text-green-600':'text-yellow-600' ?>">Status: <?= htmlspecialchars($req['status']) ?></p>
                            </div>
                            <div class="flex flex-col gap-2 ml-3">
                                <button onclick="editRequest(
                                    <?= $req['request_id'] ?>,
                                    '<?= addslashes($req['member_name'] ?? $req['full_name']) ?>',
                                    '<?= addslashes($req['relationship']) ?>',
                                    '<?= $req['res_birthdate'] ?: $req['birthdate'] ?>',
                                    '<?= addslashes($req['res_sex'] ?: $req['sex']) ?>',
                                    '<?= addslashes($req['res_civil_status'] ?: $req['civil_status']) ?>',
                                    '<?= addslashes($req['res_address'] ?: $req['resident_address']) ?>',
                                    '<?= addslashes($req['res_voter_status'] ?: $req['voter_status']) ?>',
                                    <?= $req['member_resident_id'] ?: '0' ?>
                                )" class="px-3 py-1 bg-yellow-400 text-white rounded hover:bg-yellow-500 text-sm">Edit</button>
                                <a <?= $req['status']==='Pending' ? 'href="?delete='.$req['request_id'].'" onclick="return confirm(\'Are you sure?\')" class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600 text-sm text-center"' : 'class="px-3 py-1 bg-gray-400 text-white rounded text-sm cursor-not-allowed"' ?>>Remove</a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-gray-500">No requests yet.</p>
            <?php endif; ?>
        </div>
    </div>
</main>


<script>
const input = document.getElementById('full_name');
const suggestions = document.getElementById('suggestions');

const fields = {
    resident_id: document.getElementById('member_resident_id'),
    birthdate: document.getElementById('birthdate'),
    sex: document.getElementById('sex'),
    civil_status: document.getElementById('civil_status'),
    address: document.getElementById('resident_address'),
    voter_status: document.getElementById('voter_status'),
};

input.addEventListener('input', () => {
    const query = input.value.trim();
    if (query.length < 2) return suggestions.classList.add('hidden');

    fetch(`get_residents_search.php?query=${encodeURIComponent(query)}`)
        .then(res => res.json())
        .then(data => {
            suggestions.innerHTML = '';
            if (data.length === 0) return suggestions.classList.add('hidden');

            data.forEach(res => {
                const name = `${res.first_name} ${res.middle_name ?? ''} ${res.last_name}`.trim();
                const li = document.createElement('li');
                li.className = 'p-2 hover:bg-blue-100 cursor-pointer';
                li.textContent = name;

                li.onclick = () => {
                    input.value = name;
                    fields.resident_id.value = res.resident_id;
                    fields.birthdate.value = res.birthdate;
                    fields.sex.value = res.sex ?? '';
                    fields.civil_status.value = res.civil_status ?? '';
                    fields.address.value = res.resident_address ?? '';
                    fields.voter_status.value = res.voter_status ?? '';
                    suggestions.classList.add('hidden');
                };
                suggestions.appendChild(li);
            });
            suggestions.classList.remove('hidden');
        });
});

document.addEventListener('click', e => {
    if (!input.contains(e.target) && !suggestions.contains(e.target)) {
        suggestions.classList.add('hidden');
    }
});
function editRequest(id, name, relationship, birthdate, sex, civil_status, address, voter_status, memberId) {
    document.getElementById('request_id').value = id;
    input.value = name;
    document.getElementById('relationship').value = relationship;

    fields.birthdate.value = birthdate;
    fields.sex.value = sex;
    fields.civil_status.value = civil_status;
    fields.address.value = address;
    fields.voter_status.value = voter_status;

    fields.resident_id.value = memberId;

    window.scrollTo({ top: 0, behavior: 'smooth' });
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
