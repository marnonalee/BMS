<?php
session_start();
include 'resident_header.php';
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "resident") {
    header("Location: ../index.php");
    exit();
}

include '../db.php';
$user_id = (int)$_SESSION["user_id"];

// -----------------------------
// FETCH BASIC RESIDENT DETAILS
// -----------------------------
$residentQuery = $conn->prepare("SELECT resident_id, household_id, is_family_head FROM residents WHERE user_id = ? LIMIT 1");
$residentQuery->bind_param("i", $user_id);
$residentQuery->execute();
$resident = $residentQuery->get_result()->fetch_assoc();
$residentQuery->close();

$resident_id = $resident['resident_id'] ?? 0;
$household_id = $resident['household_id'] ?? 0;
$is_family_head = $resident['is_family_head'] ?? 0;

// -----------------------------
// CHECK IF USER IS APPROVED
// -----------------------------
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

// DEFAULT VARIABLES
$error = '';
$success = '';
$is_blocked = false;

// -----------------------------
// BLOCK SYSTEM IF NOT APPROVED
// -----------------------------
if ($is_approved != 1) {
    $error = "You can't request. Your account is not verified yet.";
    $is_blocked = true;
}

// -----------------------------
// GENERATE CSRF TOKEN
// -----------------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// =================================================================
// HANDLE POST REQUEST (BLOCKED IF NOT VERIFIED)
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($is_blocked) {
        $error = "You can't request. Your account is not verified yet.";
    } else {

        // CSRF CHECK
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error = "Paki-pili ang tamang pangalan mula sa listahan ng suggestions bago isumite.";
        } else {

            // SANITIZE INPUT
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

                // =======================================================
                // UPDATE REQUEST
                // =======================================================
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

                // =======================================================
                // NEW REQUEST
                // =======================================================
                else {

                    // If selected from suggestions, override using DB data
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

// =================================================================
// DELETE REQUEST (BLOCKED IF NOT VERIFIED)
// =================================================================
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

// =================================================================
// FETCH REQUEST LIST
// =================================================================
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

// Fetch system settings
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

</script>

</body>
</html>
