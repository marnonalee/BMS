<?php
session_start();
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["role"], ['staff', 'admin'])) {
    header("Location: ../../index.php");
    exit();
}

include '../db.php';
require_once '../../vendor/autoload.php';
use setasign\Fpdi\Fpdi;

if (!isset($_GET['id'])) {
    header("Location: blotter_view.php");
    exit;
}

$blotterId = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT * FROM blotter_records WHERE blotter_id = ?");
$stmt->bind_param("i", $blotterId);
$stmt->execute();
$blotter = $stmt->get_result()->fetch_assoc();

if (!$blotter) {
    echo "Hindi matagpuan ang blotter record.";
    exit;
}

$error = '';
$success = '';
$pdfFileName = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $suspect_name     = $_POST['suspect_name']     ?? '';
    $suspect_address  = $_POST['suspect_address']  ?? '';
    $suspect_contact  = $_POST['suspect_contact']  ?? '';
    $complaint_reason = $_POST['complaint_reason'] ?? '';
    $request_details  = $_POST['request_details']  ?? '';

    if (!$suspect_name || !$request_details) {

        $error = "Punan ang lahat ng kinakailangang field.";
        $stmtLog = $conn->prepare("INSERT INTO log_activity (user_id, action, description) VALUES (?, ?, ?)");
        $uid = $_SESSION['user_id'];
        $act = "Failed Blotter Update";
        $desc = "Attempted to update blotter ID $blotterId without required fields.";
        $stmtLog->bind_param("iss", $uid, $act, $desc);
        $stmtLog->execute();

    } else {

        $stmt = $conn->prepare("
            UPDATE blotter_records 
            SET suspect_name=?, suspect_address=?, suspect_contact=?, incident_nature=?, request_details=? 
            WHERE blotter_id=?
        ");
        $stmt->bind_param("sssssi", $suspect_name, $suspect_address, $suspect_contact, $complaint_reason, $request_details, $blotterId);

        if ($stmt->execute()) {

            $template = $conn->query("SELECT file_path FROM certificate_templates WHERE template_for='Blotter' LIMIT 1")
                             ->fetch_assoc()['file_path'];

            $templateFile = __DIR__ . '/../' . $template;

            $pdf = new Fpdi();
            $pdf->AddPage();
            $pdf->setSourceFile($templateFile);
            $tpl = $pdf->importPage(1);
            $pdf->useTemplate($tpl);
            $pdf->SetFont('Times','',12);

            $dateToday           = date('F j, Y');
            $complainant_name    = $blotter['complainant_name'];
            $complainant_address = $blotter['complainant_address'];
            $complainant_contact = $blotter['complainant_contact'];

            $pdf->SetXY(30, 77);  $pdf->Write(0, $dateToday);
            $pdf->SetXY(60, 91);  $pdf->Write(0, $complainant_name);
            $pdf->SetXY(26, 97);  $pdf->Write(0, $complainant_address);
            $pdf->SetXY(50, 104); $pdf->Write(0, $complainant_contact);

            $pdf->SetXY(60, 118); $pdf->Write(0, $suspect_name);
            $pdf->SetXY(26, 124); $pdf->Write(0, $suspect_address);
            $pdf->SetXY(50, 131); $pdf->Write(0, $suspect_contact);

            $pdf->SetXY(20, 163); $pdf->MultiCell(170, 6, $complaint_reason, 0, 'L');
            $pdf->SetXY(20, 195); $pdf->MultiCell(170, 6, $request_details, 0, 'L');

            $pdf->SetXY(20, 218); $pdf->Write(0, $complainant_name);

            $outputDir = __DIR__ . '/../generated_blotter_cert/';
            if (!is_dir($outputDir)) mkdir($outputDir, 0777, true);

            $pdfFileName = "Blotter_{$blotterId}_" . time() . ".pdf";
            $pdf->Output('F', $outputDir . $pdfFileName);

           $filed_by  = $blotter['complainant_id']; // resident_id
$filed_role = $_SESSION['role']; // staff/admin role

$conn->query("
    INSERT INTO blotter_certificates 
    (blotter_id, request_details, filed_by, filed_role, file_path, certificate_type)
    VALUES ($blotterId, '$request_details', $filed_by, '$filed_role', '$pdfFileName', 'Blotter')
");


            $success = "Blotter record na-update na. PDF ay na-generate na.";

            $stmtLog = $conn->prepare("INSERT INTO log_activity (user_id, action, description) VALUES (?, ?, ?)");
            $uid = $_SESSION['user_id'];
            $act = "Updated Blotter & Generated PDF";
            $desc = "Blotter ID $blotterId updated and PDF $pdfFileName generated.";
            $stmtLog->bind_param("iss", $uid, $act, $desc);
            $stmtLog->execute();

        } else {

            $error = "Nabigong isumite ang blotter.";

            $stmtLog = $conn->prepare("INSERT INTO log_activity (user_id, action, description) VALUES (?, ?, ?)");
            $uid = $_SESSION['user_id'];
            $act = "Failed Blotter Update";
            $desc = "Failed to update blotter ID $blotterId due to DB execution error.";
            $stmtLog->bind_param("iss", $uid, $act, $desc);
            $stmtLog->execute();
        }
    }
}
?>


<!DOCTYPE html>
<html lang="tl">
<head>
<meta charset="UTF-8">
<title>Mag-file ng Blotter</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans flex justify-center p-6">

<div class="bg-white shadow rounded p-6 w-full max-w-xl">
    <a href="blotter_view.php?id=<?= $blotterId ?>" class="inline-flex items-center text-blue-600 hover:text-blue-800 mb-4">
        <span class="material-icons mr-1">arrow_back</span>
        Bumalik sa Blotter Details
    </a>

    <h2 class="text-xl font-semibold mb-4">Mag-file ng Blotter</h2>

    <?php if($error): ?>
        <p class="text-red-500 mb-2"><?= $error ?></p>
    <?php endif; ?>

    <form method="POST">
        <label class="block mt-2">Pangalan ng Nagrereklamo:</label>
        <input type="text" name="complainant_name" value="<?= htmlspecialchars($blotter['complainant_name']) ?>" readonly class="w-full p-2 border rounded">

        <label class="block mt-2">Address ng Nagrereklamo:</label>
        <input type="text" name="complainant_address" value="<?= htmlspecialchars($blotter['complainant_address']) ?>" readonly class="w-full p-2 border rounded">

        <label class="block mt-2">Numero ng Telepono ng Nagrereklamo:</label>
        <input type="text" name="complainant_contact" value="<?= htmlspecialchars($blotter['complainant_contact']) ?>" readonly class="w-full p-2 border rounded">

        <label class="block mt-2">Pangalan ng Inerereklamo:</label>
        <input type="text" name="suspect_name" value="<?= htmlspecialchars($blotter['suspect_name']) ?>" class="w-full p-2 border rounded" required>

        <label class="block mt-2">Address ng Inerereklamo:</label>
        <input type="text" name="suspect_address" value="<?= htmlspecialchars($blotter['suspect_address']) ?>" class="w-full p-2 border rounded">

        <label class="block mt-2">Numero ng Telepono ng Inerereklamo:</label>
        <input type="text" name="suspect_contact" value="<?= htmlspecialchars($blotter['suspect_contact']) ?>" class="w-full p-2 border rounded">

        <label class="block mt-2">Sanhi ng Reklamo:</label>
        <textarea name="complaint_reason" class="w-full p-2 border rounded"><?= htmlspecialchars($blotter['incident_nature']) ?></textarea>

        <label class="block mt-2">Mga Hinihiling na Aksyon:</label>
        <textarea name="request_details" class="w-full p-2 border rounded"><?= htmlspecialchars($blotter['request_details']) ?></textarea>

        <button type="submit" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Isumite</button>
    </form>
</div>

<!-- SUCCESS MODAL -->
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
        okBtn.addEventListener('click', () => {
            window.location.href = 'blotter_view.php?id=<?= $blotterId ?>';
        });

        const closeBtn = document.getElementById('closeSuccessModal');
        closeBtn.addEventListener('click', () => {
            window.location.href = 'blotter_view.php?id=<?= $blotterId ?>';
        });

        window.open('../generated_blotter_cert/<?= $pdfFileName ?>', '_blank');
    <?php endif; ?>
});
</script>

</body>
</html>
