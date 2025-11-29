<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "resident") {
    header("Location: ../index.php");
    exit();
}
include '../db.php';
$user_id = $_SESSION["user_id"];

$residentQuery = $conn->prepare("SELECT * FROM residents WHERE user_id = ?");
$residentQuery->bind_param("i", $user_id);
$residentQuery->execute();
$resident = $residentQuery->get_result()->fetch_assoc();
$residentQuery->close();
if (!$resident) die("Hindi mahanap ang profile ng residente.");

$userQuery = $conn->prepare("SELECT email FROM users WHERE id = ?");
$userQuery->bind_param("i", $user_id);
$userQuery->execute();
$userData = $userQuery->get_result()->fetch_assoc();
$userQuery->close();
$email = $userData['email'] ?? '';

$successMsg = '';
$errorMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $alias = $_POST['alias'] ?? '';
    $suffix = $_POST['suffix'] ?? '';
    $resident_address = $_POST['resident_address'] ?? '';
    $birth_place = $_POST['birth_place'] ?? '';
    $street = $_POST['street'] ?? '';
    $citizenship = $_POST['citizenship'] ?? '';
    $voter_status = $_POST['voter_status'] ?? '';
    $employment_status = $_POST['employment_status'] ?? '';
    $contact_number = $_POST['contact_number'] ?? '';
    $religion = $_POST['religion'] ?? '';
    $profession_occupation = $_POST['profession_occupation'] ?? '';
    $educational_attainment = $_POST['educational_attainment'] ?? '';
    $education_details = $_POST['education_details'] ?? '';
    $is_family_head = isset($_POST['is_family_head']) ? 1 : 0;
    $philsys_card_no = $_POST['philsys_card_no'] ?? '';
    $is_senior = isset($_POST['is_senior']) ? 1 : 0;
    $is_pwd = isset($_POST['is_pwd']) ? 1 : 0;
    $is_4ps = isset($_POST['is_4ps']) ? 1 : 0;
    $is_solo_parent = isset($_POST['is_solo_parent']) ? 1 : 0;

    $checkQuery = $conn->prepare("
        SELECT COUNT(*) as request_count 
        FROM profile_update_requests 
        WHERE user_id = ? 
          AND status IN ('Pending','Approved') 
          AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $checkQuery->bind_param("i", $user_id);
    $checkQuery->execute();
    $checkResult = $checkQuery->get_result()->fetch_assoc();
    $checkQuery->close();

    if ($checkResult['request_count'] >= 2) {
        $errorMsg = "Nakapag-submit ka na ng 2 request sa nakalipas na 7 araw. Subukan muli mamaya.";
    } else {
        if (isset($_FILES['resident_id']) && $_FILES['resident_id']['error'] === 0) {
            $allowed = ['jpg','jpeg','png','pdf'];
            $fileName = $_FILES['resident_id']['name'];
            $fileTmp = $_FILES['resident_id']['tmp_name'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (in_array($fileExt, $allowed)) {
                $newFileName = 'id_'.$user_id.'_'.time().'.'.$fileExt;
                $uploadDir = 'uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                if (move_uploaded_file($fileTmp, $uploadDir.$newFileName)) {
                    $resident_id_file = $newFileName;
                } else {
                    $errorMsg .= "Nabigo ang pag-upload ng ID. ";
                    $resident_id_file = null;
                }
            } else {
                $errorMsg .= "Di-tamang file type. JPG, PNG, o PDF lang ang puwede. ";
                $resident_id_file = null;
            }
        } else {
            $resident_id_file = $resident['resident_id_file'] ?? null;
        }

        $insertQuery = $conn->prepare("
            INSERT INTO profile_update_requests (
                user_id, resident_id, alias, suffix, resident_address, birth_place, street, citizenship,
                voter_status, employment_status, contact_number, religion, profession_occupation,
                educational_attainment, education_details, is_family_head, philsys_card_no,
                is_senior, is_pwd, is_4ps, is_solo_parent, resident_id_file, status
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Pending')
        ");
        $insertQuery->bind_param(
            "iisssssssssssiissiiiss",
            $user_id, $resident['resident_id'], $alias, $suffix, $resident_address, $birth_place, $street, $citizenship,
            $voter_status, $employment_status, $contact_number, $religion, $profession_occupation,
            $educational_attainment, $education_details, $is_family_head, $philsys_card_no,
            $is_senior, $is_pwd, $is_4ps, $is_solo_parent, $resident_id_file
        );

        if ($insertQuery->execute()) {
            $successMsg = "Na-submit na ang iyong request at nakabinbin para sa approval.";
        } else {
            $errorMsg .= "Nabigo ang pag-submit ng request. Subukan muli sa susunod na linggo.";
        }
        $insertQuery->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Resident Profile | Barangay System</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body class="bg-gray-100">

<header class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-md p-4 rounded-b-lg">
    <div class="container mx-auto flex justify-between items-center">
        <h1 class="text-xl font-bold">Manage Profile</h1>
        <a href="dashboard.php" class="flex items-center gap-2 px-4 py-2 rounded-lg font-medium bg-white text-blue-600 hover:bg-gray-100 transition">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
</header>

<main class="container mx-auto mt-6 bg-white rounded-xl shadow p-6">
    <?php if ($successMsg): ?><div class="bg-green-100 text-green-800 p-3 rounded mb-4"><?= $successMsg ?></div><?php endif; ?>
    <?php if ($errorMsg): ?><div class="bg-red-100 text-red-800 p-3 rounded mb-4"><?= $errorMsg ?></div><?php endif; ?>
    <h2 class="text-xl font-semibold mb-6">Manage Your Profile</h2>
    <form method="POST" enctype="multipart/form-data">
        <div class="border-b mb-4">
            <nav class="-mb-px flex space-x-4" id="pageTabs">
                <button type="button" class="py-2 px-4 border-b-2 border-green-500 font-medium" data-tab="personalTab">Personal Info</button>
                <button type="button" class="py-2 px-4 border-b-2 border-transparent font-medium hover:border-gray-300" data-tab="otherTab">Other Info</button>
            </nav>
        </div>

        <section id="personalTab">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div><label>First Name *</label><input type="text" value="<?= htmlspecialchars($resident['first_name']) ?>" class="w-full border-b-2 py-1.5" readonly></div>
                <div><label>Middle Name</label><input type="text" value="<?= htmlspecialchars($resident['middle_name']) ?>" class="w-full border-b-2 py-1.5" readonly></div>
                <div><label>Last Name *</label><input type="text" value="<?= htmlspecialchars($resident['last_name']) ?>" class="w-full border-b-2 py-1.5" readonly></div>
                <div><label>Alias</label><input type="text" name="alias" value="<?= htmlspecialchars($resident['alias'] ?? '') ?>" class="w-full border-b-2 py-1.5"></div>
                <div><label>Suffix</label><input type="text" name="suffix" value="<?= htmlspecialchars($resident['suffix'] ?? '') ?>" class="w-full border-b-2 py-1.5"></div>
                <div><label>Birthdate *</label><input type="date" value="<?= htmlspecialchars($resident['birthdate']) ?>" class="w-full border-b-2 py-1.5" readonly></div>
                <div><label>Age</label><input type="number" value="<?= htmlspecialchars($resident['age']) ?>" readonly class="w-full border-b-2 py-1.5"></div>
                <div><label>Gender *</label><select class="w-full border-b-2 py-1.5" disabled><option value="Male" <?= $resident['sex']=='Male'?'selected':'' ?>>Male</option><option value="Female" <?= $resident['sex']=='Female'?'selected':'' ?>>Female</option></select></div>
                <div><label>Civil Status *</label><select class="w-full border-b-2 py-1.5" disabled><?php foreach(['Single','Married','Widowed','Separated','Divorced'] as $status): ?><option value="<?= $status ?>" <?= $resident['civil_status']==$status?'selected':'' ?>><?= $status ?></option><?php endforeach; ?></select></div>
                <div><label>Resident Address *</label><input type="text" name="resident_address" value="<?= htmlspecialchars($resident['resident_address']) ?>" class="w-full border-b-2 py-1.5" required></div>
                <div><label>Birth Place</label><input type="text" name="birth_place" value="<?= htmlspecialchars($resident['birth_place']) ?>" class="w-full border-b-2 py-1.5"></div>
                <div><label>Street</label><input type="text" name="street" value="<?= htmlspecialchars($resident['street']) ?>" class="w-full border-b-2 py-1.5"></div>
                <div><label>Citizenship *</label><select name="citizenship" class="w-full border-b-2 py-1.5" required><option value="">Select</option><option value="Filipino" <?= $resident['citizenship']=='Filipino'?'selected':'' ?>>Filipino</option><option value="Non-Filipino" <?= $resident['citizenship']=='Non-Filipino'?'selected':'' ?>>Non-Filipino</option></select></div>
                <div><label>Voter Status *</label><select name="voter_status" class="w-full border-b-2 py-1.5" required><option value="Registered" <?= $resident['voter_status']=='Registered'?'selected':'' ?>>Registered</option><option value="Unregistered" <?= $resident['voter_status']=='Unregistered'?'selected':'' ?>>Unregistered</option></select></div>
            </div>
        </section>

        <section id="otherTab" class="hidden mt-3">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div><label>Employment Status</label><select name="employment_status" class="w-full border-b-2 py-1.5"><option value="">Select</option><option value="Employed" <?= $resident['employment_status']=='Employed'?'selected':'' ?>>Employed</option><option value="Unemployed" <?= $resident['employment_status']=='Unemployed'?'selected':'' ?>>Unemployed</option><option value="OFW" <?= $resident['employment_status']=='OFW'?'selected':'' ?>>OFW</option></select></div>
                <div><label>Contact No.</label><input type="text" name="contact_number" value="<?= htmlspecialchars($resident['contact_number']) ?>" class="w-full border-b-2 py-1.5" maxlength="11"></div>
                <div><label>Email Address</label><input type="email" value="<?= htmlspecialchars($email) ?>" class="w-full border-b-2 py-1.5" readonly></div>
                <div><label>Religion</label><input type="text" name="religion" value="<?= htmlspecialchars($resident['religion']) ?>" class="w-full border-b-2 py-1.5"></div>
                <div><label>Occupation</label><input type="text" name="profession_occupation" value="<?= htmlspecialchars($resident['profession_occupation']) ?>" class="w-full border-b-2 py-1.5"></div>
                <div><label>Educational Attainment</label><select name="educational_attainment" class="w-full border-b-2 py-1.5"><option value="">Select Level</option><?php foreach(['Elementary','High School','College','Post Grad','Vocational'] as $level): ?><option value="<?= $level ?>" <?= $resident['educational_attainment']==$level?'selected':'' ?>><?= $level ?></option><?php endforeach; ?></select></div>
                <div><label>Status / Details</label><select name="education_details" class="w-full border-b-2 py-1.5"><option value="">Select</option><option value="Undergraduate" <?= $resident['education_details']=='Undergraduate'?'selected':'' ?>>Undergraduate</option><option value="Graduate" <?= $resident['education_details']=='Graduate'?'selected':'' ?>>Graduate</option></select></div>
                <div class="flex items-center space-x-2 mt-2"><label class="font-medium">Head of the Family:</label><input type="checkbox" name="is_family_head" <?= $resident['is_family_head']?'checked':'' ?>></div>
                <div><label>PhilSys Card No.</label><input type="text" name="philsys_card_no" value="<?= htmlspecialchars($resident['philsys_card_no']) ?>" maxlength="12" class="w-full border-b-2 py-1.5"></div>
                <div class="flex gap-3 mt-1 md:col-span-2"><label><input type="checkbox" name="is_senior" <?= $resident['is_senior']?'checked':'' ?>> Senior</label><label><input type="checkbox" name="is_pwd" <?= $resident['is_pwd']?'checked':'' ?>> PWD</label><label><input type="checkbox" name="is_4ps" <?= $resident['is_4ps']?'checked':'' ?>> 4Ps</label><label><input type="checkbox" name="is_solo_parent" <?= $resident['is_solo_parent']?'checked':'' ?>> Solo Parent</label></div>
            </div>
        </section>

        <div class="mt-6 flex justify-end"><button type="submit" class="bg-green-500 text-white px-4 py-2 rounded">Save Changes</button></div>
    </form>
</main>

<script>
const tabs = document.querySelectorAll('#pageTabs button');
const personalTab = document.getElementById('personalTab');
const otherTab = document.getElementById('otherTab');
tabs.forEach(tab=>{tab.addEventListener('click',()=>{tabs.forEach(t=>t.classList.remove('border-green-500'));tab.classList.add('border-green-500');if(tab.dataset.tab==='personalTab'){personalTab.classList.remove('hidden');otherTab.classList.add('hidden');}else{personalTab.classList.add('hidden');otherTab.classList.remove('hidden');}});});
</script>

</body>
</html>
