<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "resident") {
    header("Location: ../index.php");
    exit();
}

include '../db.php';
$user_id = (int)$_SESSION["user_id"];

$residentQuery = $conn->prepare("SELECT resident_id, household_id, is_family_head FROM residents WHERE user_id = ? LIMIT 1");
$residentQuery->bind_param("i", $user_id);
$residentQuery->execute();
$residentResult = $residentQuery->get_result();
$resident = $residentResult->fetch_assoc();
$residentQuery->close();

$resident_id = $resident['resident_id'] ?? 0;
$household_id = $resident['household_id'] ?? 0;
$is_family_head = $resident['is_family_head'] ?? 0;

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name = trim($_POST['full_name']);
    $relationship = trim($_POST['relationship']);
    $birthdate = trim($_POST['birthdate']);
    $sex = trim($_POST['sex']); // changed from gender
    $civil_status = trim($_POST['civil_status']);
    $resident_address = trim($_POST['resident_address']);
    $voter_status = trim($_POST['voter_status']);
    $age = trim($_POST['age']);
    $member_resident_id = intval($_POST['member_resident_id'] ?? 0);
    $request_id = intval($_POST['request_id'] ?? 0);

    if (!$is_family_head) {
        $error = "Only the household head can add/edit family members.";
    } elseif (!$full_name || !$relationship || !$birthdate || !$sex || !$civil_status || !$resident_address || !$voter_status) {
        $error = "All required fields must be filled.";
    } else {

        if ($request_id) {

            $stmt = $conn->prepare("
                UPDATE family_member_requests
                SET full_name = ?, relationship = ?, birthdate = ?, sex = ?, civil_status = ?, resident_address = ?, voter_status = ?, age = ?, status = 'Pending'
                WHERE request_id = ? AND household_head_id = ?
            ");
            $stmt->bind_param("ssssssiiii",
                $full_name,
                $relationship,
                $birthdate,
                $sex, // changed
                $civil_status,
                $resident_address,
                $voter_status,
                $age,
                $request_id,
                $resident_id
            );

            if ($stmt->execute()) $success = "Request updated successfully.";
            else $error = "Failed to update request.";
            $stmt->close();

        } else {

            if ($member_resident_id > 0) {
                $checkStmt = $conn->prepare("SELECT household_id, is_family_head, is_archived FROM residents WHERE resident_id = ? AND is_archived = 0");
                $checkStmt->bind_param("i", $member_resident_id);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $member = $checkResult->fetch_assoc();
                $checkStmt->close();

                if (!$member) $error = "Resident not found or archived.";
                elseif ($member['is_family_head']) $error = "Cannot add the family head.";
                elseif ($member['household_id'] != 0) $error = "This resident is already part of a household.";
            }

            if (!$error) {

                if ($member_resident_id > 0) {
                    $stmt = $conn->prepare("
                        INSERT INTO family_member_requests
                        (household_head_id, member_resident_id, full_name, relationship, birthdate, sex, civil_status, resident_address, voter_status, age, status, date_created)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())
                    ");
                    $stmt->bind_param("iissssssss",
                        $resident_id,
                        $member_resident_id,
                        $full_name,
                        $relationship,
                        $birthdate,
                        $sex, // changed
                        $civil_status,
                        $resident_address,
                        $voter_status,
                        $age
                    );
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO family_member_requests
                        (household_head_id, full_name, relationship, birthdate, sex, civil_status, resident_address, voter_status, age, status, date_created)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())
                    ");
                    $stmt->bind_param("isssssssss",
                        $resident_id,
                        $full_name,
                        $relationship,
                        $birthdate,
                        $sex, // changed
                        $civil_status,
                        $resident_address,
                        $voter_status,
                        $age
                    );
                }

                if ($stmt->execute()) $success = "Family member request submitted.";
                else $error = "Failed to submit request.";
                $stmt->close();
            }
        }
    }
}

// DELETE logic remains the same
if (isset($_GET['delete']) && $is_family_head) {
    $del_id = intval($_GET['delete']);
    $delStmt = $conn->prepare("DELETE FROM family_member_requests WHERE request_id = ? AND household_head_id = ? AND status = 'Pending'");
    $delStmt->bind_param("ii", $del_id, $resident_id);
    $delStmt->execute();
    if ($delStmt->affected_rows) $success = "Request deleted.";
    else $error = "Cannot delete request.";
    $delStmt->close();
}

// FETCH requests
$requestQuery = $conn->prepare("
    SELECT fmr.*, r.resident_id AS member_resident_id,
    CONCAT(r.first_name, ' ', IFNULL(r.middle_name,''), ' ', r.last_name) AS member_name
    FROM family_member_requests fmr
    LEFT JOIN residents r ON fmr.member_resident_id = r.resident_id
    WHERE fmr.household_head_id = ?
    ORDER BY fmr.date_created DESC
");
$requestQuery->bind_param("i", $resident_id);
$requestQuery->execute();
$requestResult = $requestQuery->get_result();
$requests = $requestResult->fetch_all(MYSQLI_ASSOC);
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

<header class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-md">
    <div class="container mx-auto flex justify-between items-center p-4">
        <h1 class="text-2xl font-bold">Family Member Requests</h1>
        <a href="dashboard.php" class="flex items-center gap-2 bg-white text-blue-600 px-4 py-2 rounded-lg hover:bg-gray-100 transition">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
</header>

<main class="container mx-auto mt-10 flex space-x-6">
    <div class="flex-1 p-6 bg-white rounded-3xl shadow-lg max-w-2xl">
        <?php if($success): ?>
            <p class="bg-green-100 text-green-700 p-3 rounded mb-4"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>
        <?php if($error): ?>
            <p class="bg-red-100 text-red-700 p-3 rounded mb-4"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <p class="bg-yellow-100 text-yellow-800 p-3 rounded mb-4 border-l-4 border-yellow-500">
            Paalala: Ang maaari lamang idagdag bilang miyembro ng pamilya ay yung nasa <strong>listahan ng residente</strong>.
            Kung wala, makipag-ugnayan sa barangay sa kanilang <a href="<?= htmlspecialchars($facebookLink) ?>" target="_blank" class="text-blue-600 underline">Facebook page</a>.
        </p>

        <form method="POST" class="space-y-5" id="memberForm">
            <input type="hidden" name="request_id" id="request_id">
            <input type="hidden" name="member_resident_id" id="member_resident_id">

            <div class="relative">
                <label class="block font-semibold text-gray-700">Full Name</label>
                <input type="text" id="full_name" name="full_name" autocomplete="off"
                    class="w-full mt-2 p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                <ul id="suggestions" class="absolute z-50 w-full bg-white border rounded-lg mt-1 max-h-48 overflow-y-auto hidden"></ul>
            </div>

            <div>
                <label class="block font-semibold">Relationship</label>
                <input type="text" id="relationship" name="relationship" class="w-full mt-2 p-3 border rounded-lg" required>
            </div>

            <div>
                <label class="block font-semibold">Birthdate</label>
                <input type="date" id="birthdate" name="birthdate" class="w-full mt-2 p-3 border rounded-lg" required>
            </div>

            <div>
                <label class="block font-semibold">Sex</label>
                <input type="text" id="sex" name="sex" class="w-full mt-2 p-3 border rounded-lg" readonly>
            </div>

            <div>
                <label class="block font-semibold">Civil Status</label>
                <input type="text" id="civil_status" name="civil_status" class="w-full mt-2 p-3 border rounded-lg" readonly>
            </div>

            <div>
                <label class="block font-semibold">Address</label>
                <input type="text" id="resident_address" name="resident_address" class="w-full mt-2 p-3 border rounded-lg" readonly>
            </div>

            <div>
                <label class="block font-semibold">Voter Status</label>
                <input type="text" id="voter_status" name="voter_status" class="w-full mt-2 p-3 border rounded-lg" readonly>
            </div>

            <div>
                <label class="block font-semibold">Age</label>
                <input type="text" id="age" name="age" class="w-full mt-2 p-3 border rounded-lg" readonly>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">Submit</button>
            </div>
        </form>
    </div>

    <div class="w-1/3 p-6 bg-white rounded-3xl shadow-lg h-fit">
        <h2 class="text-lg font-bold mb-4">Your Family Member Requests</h2>

        <?php if(count($requests) > 0): ?>
            <ul class="space-y-3 max-h-[500px] overflow-y-auto">
            <?php foreach($requests as $req): ?>
                <li class="p-3 border rounded-lg bg-gray-50 flex justify-between items-start">
                    <div>
                        <p class="font-semibold"><?= htmlspecialchars($req['member_name'] ?? $req['full_name']) ?></p>
                        <p>Relationship: <?= htmlspecialchars($req['relationship']) ?></p>
                        <p>Birthdate: <?= htmlspecialchars($req['birthdate']) ?></p>
                        <p class="text-gray-500 text-sm">Requested on: <?= date("M d, Y", strtotime($req['date_created'])) ?></p>
                        <p class="text-sm font-medium <?= $req['status']==='Approved'?'text-green-600':'text-yellow-600' ?>">Status: <?= htmlspecialchars($req['status']) ?></p>
                    </div>

                    <div class="space-y-1 flex flex-col">
                        <button onclick="editRequest(<?= $req['request_id'] ?>,'<?= addslashes($req['member_name'] ?? $req['full_name']) ?>','<?= addslashes($req['relationship']) ?>','<?= $req['birthdate'] ?>',<?= $req['member_resident_id'] ?: '0' ?>)" class="px-3 py-1 bg-yellow-400 text-white rounded hover:bg-yellow-500 text-sm">Edit</button>

                        <a <?= $req['status']==='Pending' ? 'href="?delete='.$req['request_id'].'" onclick="return confirm(\'Are you sure?\')" class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600 text-sm text-center"' : 'class="px-3 py-1 bg-gray-400 text-white rounded text-sm cursor-not-allowed"' ?>>Remove</a>
                    </div>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-gray-500">No requests yet.</p>
        <?php endif; ?>
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
    age: document.getElementById('age'),
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
            li.textContent = `${name} (${res.age} yrs)`;

            li.onclick = () => {
                input.value = name;
                fields.resident_id.value = res.resident_id;
                fields.birthdate.value = res.birthdate;
                fields.sex.value = res.sex ?? '';
                fields.civil_status.value = res.civil_status ?? '';
                fields.address.value = res.resident_address ?? '';
                fields.voter_status.value = res.voter_status ?? '';
                fields.age.value = res.age ?? '';
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

function editRequest(id, name, relationship, birthdate, memberId) {
    document.getElementById('request_id').value = id;
    input.value = name;
    document.getElementById('relationship').value = relationship;
    fields.birthdate.value = birthdate;
    fields.resident_id.value = memberId;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

</body>
</html>
