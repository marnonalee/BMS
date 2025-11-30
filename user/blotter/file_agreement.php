<?php
session_start();
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["role"], ['staff','admin'])) {
    header("Location: ../../index.php");
    exit();
}

include '../../db.php';
require_once 'generate_agreement_pdf.php';
require '../../vendor/autoload.php';
$settingsQuery = $conn->query("SELECT system_email, app_password, barangay_name FROM system_settings LIMIT 1");
$settings = $settingsQuery->fetch_assoc();
$systemEmail = $settings['system_email'];
$appPassword = $settings['app_password'] ?? '';
$barangayName = $settings['barangay_name'] ?? 'Certificate System';

function sendCertificateEmail($residentEmail, $fullname, $purpose, $issuedDate, $pdfPath, $filename) {
    global $systemEmail, $appPassword, $barangayName;
    if (empty($residentEmail) || !file_exists($pdfPath)) return false;
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $systemEmail;   
        $mail->Password   = $appPassword;    
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->setFrom($systemEmail, $barangayName); 
        $mail->addAddress($residentEmail, $fullname);
        $mail->addAttachment($pdfPath, $filename);
        $mail->isHTML(true);
        $mail->Subject = $purpose;
        $mail->Body    = "Magandang araw, <br><br>Na-file na ang iyong kasunduan sa blotter noong <b>$issuedDate</b>. Pakitingnan ang naka-attach na PDF.<br><br>Salamat.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

if (!isset($_GET['id'])) {
    header("Location: blotter_view.php");
    exit;
}

$blotterId = (int)$_GET['id'];
$stmt = $conn->prepare("SELECT b.*, r.age AS complainant_age FROM blotter_records b LEFT JOIN residents r ON b.complainant_id = r.resident_id WHERE b.blotter_id = ?");
$stmt->bind_param("i", $blotterId);
$stmt->execute();
$blotterData = $stmt->get_result()->fetch_assoc();
if (!$blotterData) die("Blotter record not found.");

$suspectData = null;
if (!empty($blotterData['suspect_id'])) {
    $stmt2 = $conn->prepare("SELECT resident_address, email_address, CONCAT(first_name,' ',last_name) AS fullname FROM residents WHERE resident_id = ?");
    $stmt2->bind_param("i", $blotterData['suspect_id']);
    $stmt2->execute();
    $suspectData = $stmt2->get_result()->fetch_assoc();
}

$error = '';
$success = '';
$pdfFileName = '';
$suspectAddress = isset($suspectData['resident_address']) ? ucwords(strtolower(trim($suspectData['resident_address']))) : 'N/A';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $agreement_terms = $_POST['agreement_terms'] ?? '';
    if (!$agreement_terms) {
        $error = "Punan ang kasunduan.";
    } else {
        try {
            // --- Generate PDF ---
            $pdfFileName = generateAgreementPDF($blotterData, $suspectData, $agreement_terms);

            // --- Insert into DB ---
            $stmtInsert = $conn->prepare("INSERT INTO agreement_certificates (blotter_id, file_path) VALUES (?, ?)");
            $stmtInsert->bind_param("is", $blotterId, $pdfFileName);
            $stmtInsert->execute();

            // --- Email & Notification for complainant ---
            $stmtEmail = $conn->prepare("SELECT email_address, CONCAT(first_name, ' ', last_name) AS fullname FROM residents WHERE resident_id = ?");
            $stmtEmail->bind_param("i", $blotterData['complainant_id']);
            $stmtEmail->execute();
            $residentData = $stmtEmail->get_result()->fetch_assoc();

            $sentEmail = 0;
            if ($residentData && !empty($residentData['email_address'])) {
                $filename = basename($pdfFileName);
                $sentEmail = sendCertificateEmail(
                    $residentData['email_address'],
                    $residentData['fullname'],
                    "Kasunduan Blotter",
                    date("F j, Y"),
                    $pdfFileName,
                    $filename
                ) ? 1 : 0;
            }

            $message = "Matagumpay na na-file ang iyong kasunduan sa blotter. Pakitingnan ang PDF na naka-attach sa email.";
            $stmtNotify = $conn->prepare("INSERT INTO notifications (resident_id, message, from_role, title, type, priority, action_type, sent_email) VALUES (?, ?, 'system', ?, 'agreement', 'normal', 'created', ?)");
            $title = "Kasunduan Blotter Filed";
            $stmtNotify->bind_param("issi", $blotterData['complainant_id'], $message, $title, $sentEmail);
            $stmtNotify->execute();

            // --- Email & Notification for suspect if email exists ---
            if ($suspectData && !empty($suspectData['email_address'])) {
                $sentEmailSuspect = sendCertificateEmail(
                    $suspectData['email_address'],
                    $suspectData['fullname'],
                    "Kasunduan Blotter Filed Against You",
                    date("F j, Y"),
                    $pdfFileName,
                    basename($pdfFileName)
                ) ? 1 : 0;

                $messageSuspect = "May na-file na kasunduan sa blotter laban sa iyo. Pakitingnan ang PDF na naka-attach sa email kung mayroon.";
                $stmtNotifySuspect = $conn->prepare("INSERT INTO notifications (resident_id, message, from_role, title, type, priority, action_type, sent_email) VALUES (?, ?, 'system', ?, 'agreement', 'normal', 'created', ?)");
                $stmtNotifySuspect->bind_param("issi", $blotterData['suspect_id'], $messageSuspect, $title, $sentEmailSuspect);
                $stmtNotifySuspect->execute();
            }

            $success = "Matagumpay na na-generate ang kasunduan, na-email, at na-notify ang resident at suspect kung mayroon.";
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>


<!DOCTYPE html>
<html lang="tl">
<head>
<meta charset="UTF-8">
<title>File Kasunduan</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body class="bg-gray-100 flex justify-center p-6">
<div class="bg-white p-6 rounded shadow w-full max-w-xl">
    <a href="blotter_view.php?id=<?= $blotterId ?>" class="inline-flex items-center text-blue-600 hover:text-blue-800 mb-4">
        <span class="material-icons mr-1">arrow_back</span>
        Bumalik sa Blotter Details
    </a>
    <h2 class="text-xl font-semibold mb-4">Kasunduan</h2>
    <?php if($error): ?><p class="text-red-500 mb-2"><?= $error ?></p><?php endif; ?>
    <form method="POST">
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Nagrereklamo</label>
                <input type="text" class="w-full border rounded p-2 bg-gray-100" value="<?= htmlspecialchars($blotterData['complainant_name']) ?>" readonly>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Edad</label>
                <input type="text" class="w-full border rounded p-2 bg-gray-100" value="<?= htmlspecialchars($blotterData['complainant_age'] ?? '') ?>" readonly>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Inirereklamo</label>
                <input type="text" class="w-full border rounded p-2 bg-gray-100" value="<?= htmlspecialchars($blotterData['suspect_name']) ?>" readonly>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Address ng Nagrereklamo</label>
                <input type="text" class="w-full border rounded p-2 bg-gray-100" value="<?= htmlspecialchars($blotterData['complainant_address']) ?>" readonly>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Address ng Inirereklamo</label>
                <input type="text" class="w-full border rounded p-2 bg-gray-100" value="<?= htmlspecialchars($suspectAddress) ?>" readonly>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Petsa at Oras ng Insidente</label>
                <input type="text" class="w-full border rounded p-2 bg-gray-100" value="<?= htmlspecialchars($blotterData['incident_datetime'] ?? '') ?>" readonly>
            </div>
            <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700">Lugar ng Insidente</label>
                <input type="text" class="w-full border rounded p-2 bg-gray-100" value="<?= htmlspecialchars($blotterData['incident_location'] ?? '') ?>" readonly>
            </div>
            <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700">Dahilan ng Alitan</label>
                <textarea class="w-full border rounded p-2 bg-gray-100" readonly><?= htmlspecialchars($blotterData['incident_nature'] ?? '') ?></textarea>
            </div>
        </div>
        <label class="block mt-2">Ilahad ang Kasunduan:</label>
        <textarea name="agreement_terms" class="w-full p-2 border rounded" required><?= htmlspecialchars($_POST['agreement_terms'] ?? '') ?></textarea>
        <button type="submit" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">I-Submit at Gumawa ng PDF</button>
    </form>
</div>

<div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-80 text-center relative">
        <button id="closeSuccessModal" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 material-icons">close</button>
        <span class="material-icons text-green-500 text-4xl mb-2">check_circle</span>
        <p id="successMessage" class="text-gray-700 font-medium"><?= $success ?></p>
        <button id="okSuccessBtn" class="mt-4 bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">OK</button>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    <?php if($success): ?>
        const successModal = document.getElementById('successModal');
        successModal.classList.remove('hidden');
        successModal.classList.add('flex');
        const okBtn = document.getElementById('okSuccessBtn');
        okBtn.addEventListener('click', () => { window.location.href = 'blotter_view.php?id=<?= $blotterId ?>'; });
        const closeBtn = document.getElementById('closeSuccessModal');
        closeBtn.addEventListener('click', () => { window.location.href = 'blotter_view.php?id=<?= $blotterId ?>'; });
        window.open('view_agreement_pdf.php?id=<?= $blotterId ?>', '_blank');
    <?php endif; ?>
});
</script>
</body>
</html>
