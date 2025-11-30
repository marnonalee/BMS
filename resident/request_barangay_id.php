<?php
session_start();
include 'resident_header.php';
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "resident") {
    header("Location: ../../index.php");
    exit();
}
include '../db.php';

$settingsQuery = $conn->query("SELECT theme_color FROM system_settings LIMIT 1");
$settings = $settingsQuery->fetch_assoc();
$themeColor = $settings['theme_color'] ?? '#1D4ED8';

$user_id = $_SESSION["user_id"];
$success = $error = "";

$approvalQuery = $conn->prepare("SELECT is_approved FROM users WHERE id = ?");
$approvalQuery->bind_param("i", $user_id);
$approvalQuery->execute();
$approvalResult = $approvalQuery->get_result();
if ($approvalResult->num_rows > 0) $is_approved = $approvalResult->fetch_assoc()['is_approved'];
else die("User not found.");
$approvalQuery->close();
if ($is_approved != 1) $error = "You can't request. Your account is not verified yet.";

$residentQuery = $conn->prepare("SELECT resident_id, first_name, middle_name, last_name, birthdate, resident_address, birth_place, sex, contact_number FROM residents WHERE user_id = ?");
$residentQuery->bind_param("i", $user_id);
$residentQuery->execute();
$residentResult = $residentQuery->get_result();
if ($residentResult->num_rows > 0) {
    $residentRow = $residentResult->fetch_assoc();
    $resident_id = $residentRow['resident_id'];
    $first_name = $residentRow['first_name'];
    $middle_name = $residentRow['middle_name'];
    $last_name = $residentRow['last_name'];
    $birthdate = $residentRow['birthdate'];
    $resident_address = $residentRow['resident_address'];
    $birth_place = $residentRow['birth_place'];
    $sex = $residentRow['sex'];
    $contact_number = $residentRow['contact_number'];
} else die("No matching resident record found for this user.");
$residentQuery->close();

$edit_id = $_GET['edit_id'] ?? null;
$supporting_document_existing = $picture_existing = $signature_existing = $payment_proof_existing = '';
if ($edit_id) {
    $editQuery = $conn->prepare("SELECT * FROM barangay_id_requests WHERE id = ? AND resident_id = ? AND status='Pending'");
    $editQuery->bind_param("ii", $edit_id, $resident_id);
    $editQuery->execute();
    $editResult = $editQuery->get_result();
    if ($editResult->num_rows > 0) {
        $editData = $editResult->fetch_assoc();
        $birthdate = $editData['birthdate'];
        $resident_address = $editData['resident_address'];
        $birth_place = $editData['birth_place'];
        $sex = $editData['sex'];
        $nature_of_residency = $editData['nature_of_residency'];
        $emergency_name = $editData['emergency_name'];
        $emergency_contact = $editData['emergency_contact'];
        $supporting_document_existing = $editData['supporting_document'];
        $picture_existing = $editData['picture'];
        $signature_existing = $editData['signature'];
        $payment_proof_existing = $editData['payment_proof'] ?? '';
    } else {
        $error = "You can't edit this request.";
        $edit_id = null;
    }
    $editQuery->close();
}
$gcashQuery = $conn->query("SELECT gcash_name, gcash_number, gcash_qr FROM system_settings LIMIT 1");
$gcashData = $gcashQuery->fetch_assoc();
$gcashName = $gcashData['gcash_name'] ?? '';
$gcashNumber = $gcashData['gcash_number'] ?? '';
$gcashQR = $gcashData['gcash_qr'] ?? '';
function maskName($name) {
    $parts = explode(' ', $name);
    $maskedParts = [];

    foreach ($parts as $part) {
        $len = strlen($part);
        if ($len <= 2) {
            $maskedParts[] = str_repeat('*', $len); 
        } else {
            $first = $part[0];
            $last = $part[$len-1];
            $middle = str_repeat('*', $len-2);
            $maskedParts[] = $first . $middle . $last;
        }
    }

    return implode(' ', $maskedParts);
}

$can_request = !$edit_id;
$checkQuery = $conn->prepare("SELECT status, valid_until FROM barangay_id_requests WHERE resident_id = ? ORDER BY created_at DESC LIMIT 1");
$checkQuery->bind_param("i", $resident_id);
$checkQuery->execute();
$checkResult = $checkQuery->get_result();
if ($checkResult->num_rows > 0) {
    $lastRequest = $checkResult->fetch_assoc();
    $status = $lastRequest['status'];
    $valid_until = $lastRequest['valid_until'];
    $today = date('Y-m-d');
    if (!$edit_id && ($status == 'Pending' || ($status == 'Approved' && $valid_until >= $today))) {
        $can_request = false;
        $error = "You already have an active or pending Barangay ID. You can request a new one after your current ID expires.";
    }
}
$checkQuery->close();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $birthdate_input = trim($_POST["birthdate"]);
    $resident_address_input = trim($_POST["resident_address"]);
    $birth_place_input = trim($_POST["birth_place"]);
    $sex_input = trim($_POST["sex"]);
    $nature_of_residency_input = trim($_POST["nature_of_residency"]);
    $emergency_name_input = trim($_POST["person_to_contact"]);
    $emergency_contact_input = trim($_POST["contact_number_person"]);

    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $imageDir = $uploadDir . 'image/';
    if (!is_dir($imageDir)) mkdir($imageDir, 0755, true);
    $signatureDir = $uploadDir . 'signature/';
    if (!is_dir($signatureDir)) mkdir($signatureDir, 0755, true);
    $paymentDir = $uploadDir . 'payment/';
    if (!is_dir($paymentDir)) mkdir($paymentDir, 0755, true);

    $allowedExts = ['jpg','jpeg','png','pdf'];

    if (!empty($_FILES['supporting_document']['name'])) {
        $supportingFile = $_FILES['supporting_document'];
        $supportingExt = strtolower(pathinfo($supportingFile['name'], PATHINFO_EXTENSION));
        if (!in_array($supportingExt, $allowedExts)) $error = "Invalid file type for supporting document.";
        else {
            $supportingFileName = uniqid() . '_support.' . $supportingExt;
            move_uploaded_file($supportingFile['tmp_name'], $uploadDir . $supportingFileName);
            $supporting_document_db = $supportingFileName;
        }
    } else $supporting_document_db = $supporting_document_existing;

    if (!empty($_FILES['picture']['name'])) {
        $pictureFile = $_FILES['picture'];
        $pictureExt = strtolower(pathinfo($pictureFile['name'], PATHINFO_EXTENSION));
        if (!in_array($pictureExt, $allowedExts)) $error = "Invalid file type for picture.";
        else {
            $pictureFileName = uniqid() . '_picture.' . $pictureExt;
            move_uploaded_file($pictureFile['tmp_name'], $imageDir . $pictureFileName);
            $picture_db = 'image/' . $pictureFileName;
        }
    } else $picture_db = $picture_existing;

    if (!empty($_FILES['signature']['name'])) {
        $signatureFile = $_FILES['signature'];
        $signatureExt = strtolower(pathinfo($signatureFile['name'], PATHINFO_EXTENSION));
        if (!in_array($signatureExt, $allowedExts)) $error = "Invalid file type for signature.";
        else {
            $signatureFileName = uniqid() . '_signature.' . $signatureExt;
            move_uploaded_file($signatureFile['tmp_name'], $signatureDir . $signatureFileName);
            $signature_db = 'signature/' . $signatureFileName;
        }
    } else $signature_db = $signature_existing;

    if (!empty($_FILES['payment_proof']['name'])) {
        $proofFile = $_FILES['payment_proof'];
        $proofExt = strtolower(pathinfo($proofFile['name'], PATHINFO_EXTENSION));
        if (!in_array($proofExt, $allowedExts)) $error = "Invalid file type for payment proof.";
        else {
            $proofFileName = uniqid() . '_payment.' . $proofExt;
            move_uploaded_file($proofFile['tmp_name'], $paymentDir . $proofFileName);
            $payment_proof_db = 'payment/' . $proofFileName;
        }
    } else $error = "You must upload proof of payment before submitting.";

if (empty($error)) {

    if ($edit_id) {
        // UPDATE EXISTING REQUEST
        $stmt = $conn->prepare("UPDATE barangay_id_requests 
            SET birthdate=?, resident_address=?, birth_place=?, sex=?, 
                nature_of_residency=?, emergency_name=?, emergency_contact=?, 
                supporting_document=?, picture=?, signature=?, payment_proof=? 
            WHERE id=? AND resident_id=?");

        $stmt->bind_param(
            "sssssssssssii",
            $birthdate_input,
            $resident_address_input,
            $birth_place_input,
            $sex_input,
            $nature_of_residency_input,
            $emergency_name_input,
            $emergency_contact_input,
            $supporting_document_db,
            $picture_db,
            $signature_db,
            $payment_proof_db,
            $edit_id,
            $resident_id
        );

    } else {

        // CREATE NEW REQUEST
        $year = date('Y');
        $prefix = "P-IV-$year";

        $lastQuery = $conn->prepare("SELECT id_number FROM barangay_id_requests 
            WHERE id_number LIKE ? 
            ORDER BY id_number DESC LIMIT 1");

        $likePattern = $prefix . '-%';
        $lastQuery->bind_param("s", $likePattern);
        $lastQuery->execute();

        $lastResult = $lastQuery->get_result();

        $newNum = ($lastResult->num_rows > 0)
            ? str_pad(intval(substr($lastResult->fetch_assoc()['id_number'], -5)) + 1, 5, '0', STR_PAD_LEFT)
            : '00001';

        $newIdNumber = $prefix . '-' . $newNum;

        $stmt = $conn->prepare("INSERT INTO barangay_id_requests 
            (resident_id, id_number, status, nature_of_residency, supporting_document, picture, signature, 
             emergency_name, emergency_contact, payment_proof, birthdate, resident_address, birth_place, sex, created_at) 
            VALUES (?, ?, 'Pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

        $stmt->bind_param(
            "issssssssssss",
            $resident_id,
            $newIdNumber,
            $nature_of_residency_input,
            $supporting_document_db,
            $picture_db,
            $signature_db,
            $emergency_name_input,
            $emergency_contact_input,
            $payment_proof_db,
            $birthdate_input,
            $resident_address_input,
            $birth_place_input,
            $sex_input
        );
    }

    // ---------------- EXECUTE ----------------
    if ($stmt->execute()) {

        // Update resident basic info
        $updateResident = $conn->prepare("UPDATE residents 
            SET birthdate=?, resident_address=?, birth_place=?, sex=? 
            WHERE resident_id=?");

        $updateResident->bind_param(
            "ssssi",
            $birthdate_input,
            $resident_address_input,
            $birth_place_input,
            $sex_input,
            $resident_id
        );

        $updateResident->execute();
        $updateResident->close();
        $stmt->close();

        // ---------------- DIRECT REDIRECT AFTER SUCCESS ----------------
        header("Location: certificate/all_requests.php?success=1");
        exit();

    } else {
        $error = "Something went wrong while saving your request.";
    }
}

}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Request Barangay ID</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">

<main class="flex justify-center mt-10 px-6 mb-10">
    <div class="w-full max-w-6xl">
        <!-- Error Message -->
        <?php if(!empty($error)): ?>
            <div class="bg-red-100 text-red-800 px-6 py-4 rounded-2xl mb-6 flex items-center gap-3 shadow-sm">
                <i class="fas fa-exclamation-circle text-lg"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if($can_request || isset($edit_id)): ?>
              <div class="mb-6 flex items-center space-x-2 text-gray-500 text-sm">
                <a href="dashboard.php" class="hover:underline">Dashboard</a>
                <span class="text-gray-300">/</span>
                <span class="font-semibold text-gray-700"> Barangay ID</span>
            </div>
        <form method="POST" enctype="multipart/form-data" class="bg-white p-10 rounded-2xl shadow-lg space-y-6" id="barangayForm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <div>
                    <label class="block font-semibold mb-2">Apelyido</label>
                    <input type="text" value="<?= htmlspecialchars($last_name) ?>" readonly class="w-full border p-4 rounded-lg bg-gray-100">
                </div>
                <div>
                    <label class="block font-semibold mb-2">Pangalan</label>
                    <input type="text" value="<?= htmlspecialchars($first_name) ?>" readonly class="w-full border p-4 rounded-lg bg-gray-100">
                </div>
                <div>
                    <label class="block font-semibold mb-2">Gitnang Pangalan</label>
                    <input type="text" value="<?= htmlspecialchars($middle_name) ?>" readonly class="w-full border p-4 rounded-lg bg-gray-100">
                </div>
                <div>
                    <label class="block font-semibold mb-2">Petsa ng Kapanganakan</label>
                    <input type="date" name="birthdate" value="<?= htmlspecialchars($birthdate) ?>" readonly class="w-full border p-4 rounded-lg bg-gray-100">
                </div>
                <div class="md:col-span-2">
                    <label class="block font-semibold mb-2">Tirahan</label>
                    <input type="text" name="resident_address" value="<?= htmlspecialchars($resident_address) ?>" required class="w-full border p-4 rounded-lg">
                </div>
                <div>
                    <label class="block font-semibold mb-2">Lugar ng Kapanganakan</label>
                    <input type="text" name="birth_place" value="<?= htmlspecialchars($birth_place) ?>" required class="w-full border p-4 rounded-lg">
                </div>
                <div>
                    <label class="block font-semibold mb-2">Kasarian</label>
                    <input type="text" name="sex" value="<?= htmlspecialchars($sex) ?>" readonly class="w-full border p-4 rounded-lg bg-gray-100">
                </div>
                <div>
                    <label class="block font-semibold mb-2">Uri ng Paninirahan</label>
                    <select name="nature_of_residency" required class="w-full border p-4 rounded-lg">
                        <option value="">-- Piliin --</option>
                        <option value="Resident" <?= (isset($nature_of_residency) && $nature_of_residency=='Resident')?'selected':'' ?>>Resident</option>
                        <option value="Tenant" <?= (isset($nature_of_residency) && $nature_of_residency=='Tenant')?'selected':'' ?>>Tenant</option>
                    </select>
                </div>

                <div>
                    <label class="block font-semibold mb-2">Taong Maaaring Kontakin</label>
                    <input type="text" name="person_to_contact" required value="<?= htmlspecialchars($emergency_name ?? '') ?>" class="w-full border p-4 rounded-lg">
                </div>
                <div>
                    <label class="block font-semibold mb-2">Numero ng Kontak</label>
                    <input type="text" name="contact_number_person" required value="<?= htmlspecialchars($emergency_contact ?? '') ?>" class="w-full border-b-2 border-gray-300 focus:border-blue-500 outline-none py-2 rounded bg-transparent" placeholder="09XXXXXXXXX" maxlength="11" pattern="09[0-9]{9}" title="Enter a valid Philippine mobile number starting with 09" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                </div>

                <div class="md:col-span-2">
                    <label class="block font-semibold mb-2">Suportadong Dokumento</label>
                    <?php if(!empty($supporting_document_existing)): ?>
                        <p class="text-sm text-gray-600 mb-1">Kasulukuyang file: <a href="uploads/<?= htmlspecialchars($supporting_document_existing) ?>" target="_blank"><?= htmlspecialchars($supporting_document_existing) ?></a></p>
                    <?php endif; ?>
                    <input type="file" name="supporting_document" accept=".jpg,.jpeg,.png,.pdf" class="w-full border p-4 rounded-lg">
                </div>

                <div>
                    <label class="block font-semibold mb-2">1x1 na Larawan</label>
                    <?php if(!empty($picture_existing)): ?>
                        <img src="uploads/<?= htmlspecialchars($picture_existing) ?>" alt="Kasulukuyang Larawan" class="w-24 h-24 mb-1 object-cover border rounded">
                    <?php endif; ?>
                    <input type="file" name="picture" accept=".jpg,.jpeg,.png" class="w-full border p-4 rounded-lg">
                </div>

                <div>
                    <label class="block font-semibold mb-2">Pirma</label>
                    <?php if(!empty($signature_existing)): ?>
                        <img src="uploads/<?= htmlspecialchars($signature_existing) ?>" alt="Kasulukuyang Pirma" class="w-24 h-24 mb-1 object-contain border rounded">
                    <?php endif; ?>
                    <input type="file" name="signature" accept=".jpg,.jpeg,.png" class="w-full border p-4 rounded-lg">
                </div>

                <div class="md:col-span-2 bg-gray-50 p-6 rounded-xl">
                    <h3 class="font-semibold mb-2">Bayad (₱120)</h3>
                    <p class="mb-2">I-scan ang GCash QR Code at i-upload ang screenshot bilang patunay ng pagbabayad.</p>

                    <?php
                        $gcashPath = !empty($gcashQR) ? "../" . $gcashQR : '';
                    ?>
                    <?php if(!empty($gcashQR)): ?>
                        <img src="<?= htmlspecialchars($gcashPath) ?>" alt="GCash QR" class="w-32 h-32 mb-2 border rounded cursor-pointer" onclick="openQRModal('<?= htmlspecialchars($gcashPath) ?>')">
                    <?php else: ?>
                        <p class="text-red-500">GCash QR code not available.</p>
                    <?php endif; ?>

                    <?php if(!empty($gcashName) && !empty($gcashNumber)): ?>
                        <p class="text-sm text-gray-700 mb-2">Name: <?= htmlspecialchars(maskName($gcashName)) ?></p>
                        <p class="text-sm text-gray-700 mb-2">Number: <?= htmlspecialchars($gcashNumber) ?></p>
                    <?php endif; ?>


                    <?php if(!empty($payment_proof_existing)): ?>
                        <p class="text-sm text-gray-600 mb-1">Kasulukuyang file: <a href="uploads/<?= htmlspecialchars($payment_proof_existing) ?>" target="_blank"><?= htmlspecialchars($payment_proof_existing) ?></a></p>
                    <?php endif; ?>
                    <input type="file" name="payment_proof" id="payment_proof" accept=".jpg,.jpeg,.png,.pdf" class="w-full border p-4 rounded-lg" <?= isset($edit_id) ? '' : 'required' ?>>
                </div>

                <div class="md:col-span-2 flex gap-4">
                    <button type="button" id="submitBtn" onclick="confirmSubmit()" class="w-full py-4 rounded-2xl font-semibold flex justify-center items-center gap-3 opacity-50 cursor-not-allowed" style="background-color: <?= $themeColor ?>; color: #fff;" disabled>
                        <i class="fas fa-paper-plane"></i> <?= isset($edit_id) ? 'I-update ang Request' : 'Isumite ang Request' ?>
                    </button>
                </div>

            </div>
        </form>

        <?php endif; ?>
    </div>
</main>

<div id="universalModal" class="hidden fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-xl p-6">
        <h2 id="modalTitle" class="text-xl font-bold mb-3"></h2>
        <p id="modalMessage" class="text-gray-700 mb-6"></p>
        <div id="modalButtons" class="flex justify-end gap-3"></div>
    </div>
</div>
<script>
function openQRModal(imgSrc) {
    const modal = document.getElementById("universalModal");
    document.getElementById("modalTitle").innerText = "GCash QR Code";

    // Medium-size QR
    document.getElementById("modalMessage").innerHTML = `
        <div class="flex justify-center">
            <img src="${imgSrc}" class="max-w-xs max-h-xs rounded-lg border">
        </div>
    `;

    document.getElementById("modalButtons").innerHTML = `
        <button onclick="closeModal()" class="px-4 py-2 rounded text-white" style="background-color: <?= $themeColor ?>;">Close</button>
    `;
    modal.classList.remove("hidden");
}

const submitBtn = document.getElementById('submitBtn');
const form = document.getElementById('barangayForm');

// ---------------- FORM VALIDATION ----------------
function validateForm() {
    const requiredFields = form.querySelectorAll('[required]');
    let allFilled = true;

    requiredFields.forEach(field => {
        if (!field.value.trim()) allFilled = false;
    });

    const contactField = form.querySelector('input[name="contact_number_person"]');
    const contactValid = /^\d{11}$/.test(contactField.value);

    const paymentField = document.getElementById('payment_proof');

    if (allFilled && contactValid && paymentField.files.length > 0) {
        submitBtn.disabled = false;
        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    } else {
        submitBtn.disabled = true;
        submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
    }
}

form.addEventListener('input', validateForm);
document.getElementById('payment_proof').addEventListener('change', validateForm);

// ---------------- UNIVERSAL MODAL ----------------
function openModal(title, message, buttons = []) {
    const modal = document.getElementById("universalModal");

    document.getElementById("modalTitle").innerText = title;
    document.getElementById("modalMessage").innerText = message;

    const btnContainer = document.getElementById("modalButtons");
    btnContainer.innerHTML = "";

    buttons.forEach(btn => {
        const b = document.createElement('button');
        b.innerText = btn.text;
        b.onclick = btn.action;
        b.className = 'px-4 py-2 rounded text-white';
        b.style.backgroundColor = '<?= $themeColor ?>';
        btnContainer.appendChild(b);
    });

    modal.classList.remove('hidden');
}

function closeModal() {
    document.getElementById("universalModal").classList.add("hidden");
}

// ---------------- CONFIRM SUBMIT ----------------
function confirmSubmit() {
    if (submitBtn.disabled) return; // Prevent click if disabled

    openModal(
        "Confirm Submission",
        "Are you sure you want to submit this request? Make sure you have uploaded proof of payment.",
        [
            {
                text: "Cancel",
                action: () => closeModal()
            },
            {
                text: "Submit",
                action: () => {
                    closeModal();
                    form.submit();
                }
            }
        ]
    );
}
</script>


</body>
</html>
