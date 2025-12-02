<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "resident") {
    header("Location: ../index.php");
    exit();

}
include '../../db.php';

$user_id = $_SESSION["user_id"];
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
$success = $error = "";
$disableForm = false;

$approvalQuery = $conn->prepare("SELECT is_approved FROM users WHERE id = ?");
$approvalQuery->bind_param("i", $user_id);
$approvalQuery->execute();
$approvalResult = $approvalQuery->get_result();
if ($approvalResult->num_rows > 0) $is_approved = $approvalResult->fetch_assoc()['is_approved'];
else die("User not found.");
$approvalQuery->close();
if ($is_approved != 1) {
    $error = "You can't request. Your account is not verified yet.";
    $disableForm = true;
}
$residentQuery = $conn->prepare("SELECT resident_id, first_name, middle_name, last_name, resident_address, birthdate, birth_place, civil_status, sex, voter_status, profession_occupation FROM residents WHERE user_id = ?");
$residentQuery->bind_param("i", $user_id);
$residentQuery->execute();
$residentResult = $residentQuery->get_result();
if ($residentResult->num_rows > 0) {
    $resident = $residentResult->fetch_assoc();
    $resident_id = $resident['resident_id'];
    $full_name = $resident['first_name'] . ' ' . $resident['middle_name'] . ' ' . $resident['last_name'];
    $age = !empty($resident['birthdate']) ? (new DateTime($resident['birthdate']))->diff(new DateTime('now'))->y : '';
} else die("No matching resident record found.");
$residentQuery->close();
$editRequest = null;
$editId = null;
if (isset($_GET['edit_id'])) {
    $editId = intval($_GET['edit_id']);
    $stmt = $conn->prepare("SELECT template_id, purpose, supporting_doc, earnings_per_month, child_fullname, child_birthdate, child_birthplace, profession_occupation FROM certificate_requests WHERE id = ? AND resident_id = ?");
    $stmt->bind_param("ii", $editId, $resident_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $editRequest = $result->fetch_assoc();
        $template_id = $editRequest['template_id'];
        $disableForm = false;
    }
    $stmt->close();
}
$template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : ($editRequest['template_id'] ?? 0);
if ($_SERVER["REQUEST_METHOD"] === "POST") $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : $template_id;
$certificate_name = "";
if ($template_id > 0) {
    $stmt = $conn->prepare("SELECT template_name FROM certificate_templates WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $template_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) $certificate_name = $result->fetch_assoc()['template_name'];
    else {
        $error = "Invalid certificate selected.";
        $disableForm = true;
    }
    $stmt->close();
}
if ($template_id > 0) {
    if ($editId) {
        $checkStmt = $conn->prepare("SELECT COUNT(*) as pending_count FROM certificate_requests WHERE resident_id = ? AND template_id = ? AND status = 'Pending' AND id != ?");
        $checkStmt->bind_param("iii", $resident_id, $template_id, $editId);
    } else {
        $checkStmt = $conn->prepare("SELECT COUNT(*) as pending_count FROM certificate_requests WHERE resident_id = ? AND template_id = ? AND status = 'Pending'");
        $checkStmt->bind_param("ii", $resident_id, $template_id);
    }
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $pendingCount = $checkResult->fetch_assoc()['pending_count'];
    $checkStmt->close();
    if ($pendingCount > 0) {
        $error = "You already have a pending request for this certificate.";
        $disableForm = true;
    }
}
if ($_SERVER["REQUEST_METHOD"] === "POST" && !$disableForm) {
    $purpose = trim($_POST['purpose'] ?? '');
    $valid_id = "";
    $profession_occupation = $_POST['profession_occupation'] ?? null;
    $earnings_per_month = $_POST['earnings_per_month'] ?? null;
    $child_fullname = $_POST['child_fullname'] ?? null;
    $child_birthdate = $_POST['child_birthdate'] ?? null;
    $child_birthplace = $_POST['child_birthplace'] ?? null;
    if (!empty($_FILES["valid_id"]["name"])) {
        $target_dir = "../uploads/valid_ids/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $file_name = time() . "_" . basename($_FILES["valid_id"]["name"]);
        $target_file = $target_dir . $file_name;
        if (move_uploaded_file($_FILES["valid_id"]["tmp_name"], $target_file)) $valid_id = $file_name;
        else $error = "Failed to upload valid ID.";
    } elseif ($editRequest) $valid_id = $editRequest['supporting_doc'];
    else $error = "Please upload your valid ID.";
    if (empty($error)) {
        if ($editRequest) {
            $stmt = $conn->prepare("UPDATE certificate_requests SET template_id = ?, purpose = ?, supporting_doc = ?, earnings_per_month = ?, child_fullname = ?, child_birthdate = ?, child_birthplace = ?, status = 'Pending', date_requested = NOW() WHERE id = ? AND resident_id = ?");
            $stmt->bind_param("issssssii", $template_id, $purpose, $valid_id, $earnings_per_month, $child_fullname, $child_birthdate, $child_birthplace, $editId, $resident_id);
            if ($stmt->execute()) $success = "Your certificate request has been updated successfully!";
            else $error = "Something went wrong while updating your request.";
            $stmt->close();
            $u = $conn->prepare("UPDATE residents SET profession_occupation = ? WHERE resident_id = ?");
            $u->bind_param("si", $profession_occupation, $resident_id);
            $u->execute();
            $u->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO certificate_requests (resident_id, template_id, purpose, supporting_doc, earnings_per_month, child_fullname, child_birthdate, child_birthplace, status, date_requested) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())");
            $stmt->bind_param("iissssss", $resident_id, $template_id, $purpose, $valid_id, $earnings_per_month, $child_fullname, $child_birthdate, $child_birthplace);
            if ($stmt->execute()) {
                $success = "Your certificate request has been submitted successfully!";
                $disableForm = true;
            } else $error = "Something went wrong while saving your request.";
            $stmt->close();
            $u = $conn->prepare("UPDATE residents SET profession_occupation = ? WHERE resident_id = ?");
            $u->bind_param("si", $profession_occupation, $resident_id);
            $u->execute();
            $u->close();
        }
    }
}
if (isset($_GET['child_search'])) {
    header("Content-Type: application/json");
    $search = "%".$_GET['child_search']."%";
    $stmt = $conn->prepare("SELECT resident_id AS child_id, first_name, last_name, birthdate, birth_place, TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) AS age FROM residents WHERE (first_name LIKE ? OR last_name LIKE ?) AND TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) < 18 ORDER BY first_name ASC LIMIT 10");
    $stmt->bind_param("ss", $search, $search);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $editRequest ? "Edit Certificate Request" : "Request Certificate" ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen font-sans">
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
        <img src="<?= !empty($settings['system_logo']) ? '../../user/user_manage/uploads/' . htmlspecialchars(basename($settings['system_logo'])) : 'https://cdn-icons-png.flaticon.com/512/149/149071.png' ?>" 
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
      <a href="../dashboard.php" class="bg-white/20 hover:bg-white/30 text-white font-semibold px-4 py-2 rounded-lg transition duration-200 flex items-center">
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
          <a href="../manage_profile.php" class="flex items-center px-4 py-2 hover:bg-gray-100 text-gray-700 font-medium">
            <i class="fas fa-user mr-3"></i> Edit Profile
          </a>
          <a href="../account_settings.php" class="flex items-center px-4 py-2 hover:bg-gray-100 text-gray-700 font-medium">
            <i class="fas fa-cog mr-3"></i> Account Settings
          </a>
          <a href="../../logout.php" class="flex items-center px-4 py-2 hover:bg-gray-100 text-gray-700 font-medium">
            <i class="fas fa-sign-out-alt mr-3"></i> Logout
          </a>
        </div>
      </div>

    </div>
  </div>
</header>
<main class="container mx-auto mt-10 max-w-6xl px-4">

    <div class="mb-6 flex items-center space-x-2 text-gray-500 text-sm">
        <a href="../dashboard.php" class="hover:underline">Dashboard</a>
        <span class="text-gray-300">/</span>
        <span class="font-semibold text-gray-700">Document Request</span>
    </div>

    <div class="bg-white p-10 rounded-3xl shadow-xl">

        <form class="space-y-6" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="resident_id" value="<?= $resident_id ?>">
            <input type="hidden" name="template_id" value="<?= $template_id ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block font-medium mb-2">Certificate</label>
                    <input type="text" value="<?= htmlspecialchars($certificate_name) ?>" readonly class="w-full border p-4 bg-gray-100 rounded-lg">
                </div>
                <div>
                    <label class="block font-medium mb-2">Full Name</label>
                    <input type="text" value="<?= htmlspecialchars($full_name) ?>" readonly class="w-full border p-4 bg-gray-100 rounded-lg">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block font-medium mb-2">Age</label>
                    <input type="text" value="<?= htmlspecialchars($age) ?>" readonly class="w-full border p-4 bg-gray-100 rounded-lg">
                </div>
                <div>
                    <label class="block font-medium mb-2">Civil Status</label>
                    <input type="text" value="<?= htmlspecialchars($resident['civil_status'] ?? '') ?>" readonly class="w-full border p-4 bg-gray-100 rounded-lg">
                </div>
                <div>
                    <label class="block font-medium mb-2">Sex</label>
                    <input type="text" value="<?= htmlspecialchars($resident['sex'] ?? '') ?>" readonly class="w-full border p-4 bg-gray-100 rounded-lg">
                </div>
                <div>
                    <label class="block font-medium mb-2">Voter Status</label>
                    <input type="text" value="<?= htmlspecialchars($resident['voter_status'] ?? '') ?>" readonly class="w-full border p-4 bg-gray-100 rounded-lg">
                </div>
            </div>

            <div>
                <label class="block font-medium mb-2">Resident Address</label>
                <input type="text" value="<?= htmlspecialchars($resident['resident_address']) ?>" readonly class="w-full border p-4 bg-gray-100 rounded-lg">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block font-medium mb-2">Birthdate</label>
                    <input type="text" value="<?= htmlspecialchars($resident['birthdate']) ?>" readonly class="w-full border p-4 bg-gray-100 rounded-lg">
                </div>
                <div>
                    <label class="block font-medium mb-2">Birthplace</label>
                    <input type="text" value="<?= htmlspecialchars($resident['birthplace'] ?? '') ?>" readonly class="w-full border p-4 bg-gray-100 rounded-lg">
                </div>
            </div>

            <div id="purposeDiv">
                <label class="block font-medium mb-2">Purpose</label>
                <textarea name="purpose" class="w-full border p-4 rounded-lg resize-none" rows="3"><?= htmlspecialchars($editRequest['purpose'] ?? '') ?></textarea>
            </div>

            <div>
                <label class="block font-medium mb-2">Upload Valid ID</label>
                <input type="file" name="valid_id" accept=".jpg,.jpeg,.png" class="w-full border p-4 rounded-lg">
                <?php if (!empty($editRequest['supporting_doc'])): ?>
                    <div class="mt-4">
                        <p class="font-medium mb-2">Current Valid ID:</p>
                        <img src="../uploads/valid_ids/<?= htmlspecialchars($editRequest['supporting_doc']) ?>" id="valid_id_preview" class="w-40 h-40 object-cover border rounded-lg">
                    </div>
                <?php else: ?>
                    <img id="valid_id_preview" class="w-40 h-40 object-cover border rounded-lg mt-4 hidden">
                <?php endif; ?>
            </div>

            <div id="professionDiv" class="<?= ($certificate_name === 'Certificate of Attestation') ? '' : 'hidden' ?>">
                <label class="block font-medium mb-2">Profession / Occupation</label>
                <input type="text" name="profession_occupation" class="w-full border p-4 rounded-lg" value="<?= htmlspecialchars($editRequest['profession_occupation'] ?? $resident['profession_occupation'] ?? '') ?>">
            </div>

            <div id="earningsDiv" class="<?= ($certificate_name === 'Certificate of Attestation') ? '' : 'hidden' ?>">
                <label class="block font-medium mb-2">Earnings per Month</label>
                <input type="number" name="earnings_per_month" min="0" step="any" class="w-full border p-4 rounded-lg" value="<?= htmlspecialchars($editRequest['earnings_per_month'] ?? '') ?>">
            </div>

            <div id="guardianSection" class="<?= ($certificate_name === 'Certificate of Guardianship' || $editRequest) ? '' : 'hidden' ?> mt-6">
                <h3 class="text-lg font-semibold mb-4">Child / Ward Information</h3>
                <div class="relative">
                    <label class="block mb-2 font-medium">Child / Ward</label>
                    <input type="text" id="child_search" placeholder="Type child name..." autocomplete="off" class="w-full border p-4 rounded-lg outline-none" value="<?= htmlspecialchars($editRequest['child_fullname'] ?? '') ?>">
                    <input type="hidden" id="child_id" value="">
                    <div id="child_dropdown" class="absolute w-full bg-white border mt-1 rounded shadow-lg z-50"></div>
                </div>

                <div id="child_manual" class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                    <div>
                        <label class="block mb-2 font-medium">Child Full Name</label>
                        <input type="text" name="child_fullname" id="child_fullname" class="w-full border p-4 rounded-lg" value="<?= htmlspecialchars($editRequest['child_fullname'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="block mb-2 font-medium">Child Age</label>
                        <input type="text" id="child_age" class="w-full border p-4 rounded-lg" value="<?= !empty($editRequest['child_birthdate']) ? (new DateTime($editRequest['child_birthdate']))->diff(new DateTime('now'))->y : '' ?>" readonly>
                    </div>
                    <div>
                        <label class="block mb-2 font-medium">Birthdate</label>
                        <input type="date" name="child_birthdate" id="child_birthdate" class="w-full border p-4 rounded-lg" value="<?= htmlspecialchars($editRequest['child_birthdate'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="block mb-2 font-medium">Birthplace</label>
                        <input type="text" name="child_birthplace" id="child_birthplace" class="w-full border p-4 rounded-lg" value="<?= htmlspecialchars($editRequest['child_birthplace'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <?php if ($editRequest): ?>
                <input type="hidden" name="edit_id" value="<?= $editId ?>">
            <?php endif; ?>

            <button type="submit" class="w-full py-4 rounded-2xl font-semibold text-white text-lg mt-6 transition hover:opacity-90" style="background-color: <?= $themeColor ?>;" <?= ($is_approved != 1) ? "disabled" : "" ?>>
                <?= $editRequest ? "Update Request" : "Submit Request" ?>
            </button>
        </form>

    </div>
</main>

<?php if(!empty($success) || !empty($error)): ?>
<div id="messageModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
    <div class="bg-white rounded-xl shadow-xl p-8 w-full max-w-md">
        <?php if(!empty($success)): ?>
            <h2 class="text-xl font-semibold text-green-600 mb-4">Success</h2>
            <p class="mb-6"><?= htmlspecialchars($success) ?></p>
        <?php elseif(!empty($error)): ?>
            <h2 class="text-xl font-semibold text-red-600 mb-4">Warning</h2>
            <p id="errorMessage" class="mb-6"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <button id="closeModalBtn" class="w-full py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">OK</button>
    </div>
</div>
<?php endif; ?>

<script>
const validIdInput = document.querySelector("input[name='valid_id']");
const validIdPreview = document.getElementById("valid_id_preview");
const submitBtn = document.querySelector("button[type='submit']");

validIdInput.addEventListener("change", function() {
    const file = this.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            validIdPreview.src = e.target.result;
            validIdPreview.classList.remove("hidden");
        }
        reader.readAsDataURL(file);
    }
    checkRequiredFields();
});

const certificateName = "<?= $certificate_name ?>";
const earningsDiv = document.getElementById('earningsDiv');
const guardianSection = document.getElementById('guardianSection');
const purposeDiv = document.getElementById('purposeDiv');
const professionDiv = document.getElementById('professionDiv');

earningsDiv.classList.add('hidden');
guardianSection.classList.add('hidden');
purposeDiv.classList.remove('hidden');
professionDiv.classList.add('hidden');

document.getElementById("child_fullname").required = false;
document.getElementById("child_birthdate").required = false;
document.getElementById("child_birthplace").required = false;

if (certificateName === "Certificate of Attestation") {
    earningsDiv.classList.remove('hidden');
    professionDiv.classList.remove('hidden');
    purposeDiv.classList.add('hidden');
    document.querySelector("input[name='profession_occupation']").required = true;
    document.querySelector("input[name='earnings_per_month']").required = true;
}

if (certificateName === "Certificate of Guardianship") {
    guardianSection.classList.remove('hidden');
    document.getElementById("child_fullname").required = true;
    document.getElementById("child_birthdate").required = true;
    document.getElementById("child_birthplace").required = true;
}

document.getElementById("child_search").addEventListener("keyup", function () {
    let query = this.value.trim();
    if (query.length < 2) {
        document.getElementById("child_dropdown").innerHTML = "";
        return;
    }
    fetch("<?= basename(__FILE__) ?>?child_search=" + encodeURIComponent(query))
        .then(res => res.json())
        .then(data => {
            let dropdown = document.getElementById("child_dropdown");
            dropdown.innerHTML = "";
            if (data.length === 0) {
                dropdown.innerHTML = "<div class='p-2 text-gray-500'>No results found</div>";
                return;
            }
            data.forEach(child => {
                let item = document.createElement("div");
                item.classList = "p-2 hover:bg-gray-100 cursor-pointer";
                item.textContent = child.first_name + " " + child.last_name;
                item.addEventListener("click", () => {
                    document.getElementById("child_search").value = child.first_name + " " + child.last_name;
                    document.getElementById("child_id").value = child.child_id;
                    document.getElementById("child_manual").classList.remove("hidden");
                    document.getElementById("child_fullname").value = child.first_name + " " + child.last_name;
                    document.getElementById("child_age").value = child.age;
                    document.getElementById("child_birthdate").value = child.birthdate;
                    document.getElementById("child_birthplace").value = child.birth_place;
                    dropdown.innerHTML = "";
                    checkRequiredFields();
                });
                dropdown.appendChild(item);
            });
        });
});

function checkRequiredFields() {
    const requiredFields = document.querySelectorAll("input[required], textarea[required]");
    let allFilled = true;
    requiredFields.forEach(field => {
        if (field.type === "file") {
            if (!field.files || field.files.length === 0) allFilled = false;
        } else {
            if (!field.value.trim()) allFilled = false;
        }
    });
    submitBtn.disabled = !allFilled;
}

document.querySelectorAll("input, textarea").forEach(field => {
    field.addEventListener("input", checkRequiredFields);
    field.addEventListener("change", checkRequiredFields);
});

checkRequiredFields();

const closeModalBtn = document.getElementById("closeModalBtn");
const messageModal = document.getElementById("messageModal");
const errorMessage = document.getElementById("errorMessage");

if (closeModalBtn) {
    closeModalBtn.addEventListener("click", () => {
        messageModal.style.display = "none";
        if (errorMessage && errorMessage.textContent.includes("pending request")) {
            window.location.href = "../dashboard.php";
        }
    });
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
