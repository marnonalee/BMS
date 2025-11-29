<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../db.php';
include 'send_certificate_email.php';

if (!isset($_GET['id'])) exit('No ID.');
$requestId = intval($_GET['id']);
$user_id = $_SESSION['user_id'] ?? 1;
$stmt = $conn->prepare("
    SELECT r.*, cr.purpose, cr.earnings_per_month, cr.template_id, cr.request_type
    FROM certificate_requests cr
    JOIN residents r ON cr.resident_id = r.resident_id
    WHERE cr.id = ?
");
$stmt->bind_param("i", $requestId);
$stmt->execute();
$d = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$d) exit("Request not found");

$resident_id = $d['resident_id'] ?? 0;
if ($resident_id == 0) exit("Resident ID missing for this request.");

$template_id = $d['template_id'] ?? 1; 

$residentName = trim($d['first_name'] . " " . $d['last_name']);
$age = $d['age'] ?? '';
$address = $d['resident_address'] ?? '';
$occupation = $d['profession_occupation'] ?? '';
$earnings = $d['earnings_per_month'] ?? '';
$birth_place = $d['birth_place'] ?? '';
$month = date("F");
$day = date("d");

require_once(__DIR__ . '/../../fpdf/fpdf.php');
require_once(__DIR__ . '/../../fpdi/src/autoload.php');

$pdf = new \setasign\Fpdi\Fpdi();
$pdf->AddPage();
$page = $pdf->setSourceFile(__DIR__ . "/../templates/attestation.pdf");
$tpl = $pdf->importPage(1);
$pdf->useTemplate($tpl, 0, 0, 210);
$pdf->SetFont("Times", "", 11);

$pdf->Text(80, 113, $residentName);
$pdf->Text(157, 113, $age);
$pdf->Text(43, 120, $address);
$pdf->Text(24, 127, $occupation);
$pdf->Text(123, 127, $earnings);
$pdf->Text(50, 158, $birth_place);
$pdf->Text(70, 174, $month);
$pdf->Text(45, 174, $day);

$saveDir = __DIR__ . "/../generated_certificates/";
if (!is_dir($saveDir)) mkdir($saveDir, 0777, true);

$filename = 'attestation_' . $requestId . '.pdf';
$filepath = $saveDir . $filename;
$pdf->Output('F', $filepath);

$stmt2 = $conn->prepare("
    INSERT INTO generated_certificates 
    (request_id, resident_id, template_id, generated_file, generated_by, remarks, status) 
    VALUES (?, ?, ?, ?, ?, ?, 'Generated')
");
$remarks = 'Generated automatically';
$stmt2->bind_param("iiiisi", $requestId, $resident_id, $template_id, $filename, $user_id, $remarks);
$stmt2->execute();
$stmt2->close();

$residentEmail = $d['email_address'] ?? '';
$purpose = $d['purpose'] ?? '';
$issuedDate = date("F d, Y");

if (($d['request_type'] ?? '') === 'online' && !empty($residentEmail)) {
    sendCertificateEmail($residentEmail, $residentName, $purpose, $issuedDate, $filepath, $filename);
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
$pdf->Output();
exit;
