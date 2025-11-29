<?php 
session_start();
include '../db.php';
$user_id = $_SESSION['user_id'] ?? 0;

if(!isset($_GET['id'])) exit('No ID.');
$id = intval($_GET['id']);

$q = $conn->prepare("
    SELECT r.*, r.email_address, cr.purpose, cr.template_id, cr.request_type
    FROM certificate_requests cr
    JOIN residents r ON cr.resident_id = r.resident_id
    WHERE cr.id = ?
");
$q->bind_param("i", $id);
$q->execute();
$d = $q->get_result()->fetch_assoc();
if(!$d) exit("Not found");

$settingsQuery = $conn->query("SELECT barangay_name FROM system_settings LIMIT 1");
$settings = $settingsQuery->fetch_assoc();
$issued_at = $settings['barangay_name'] ?? '';

require_once(__DIR__ . '/../../fpdf/fpdf.php');
require_once(__DIR__ . '/../../fpdi/src/autoload.php');
require_once('send_certificate_email.php');

$pdf = new \setasign\Fpdi\Fpdi();
$pdf->AddPage();
$page = $pdf->setSourceFile(__DIR__."/../templates/barangay_cert.pdf");
$tpl = $pdf->importPage(1);
$pdf->useTemplate($tpl, 0, 0, 210);
$pdf->SetFont("Times","",12);

$name = $d['first_name']." ".$d['last_name'];
$status = $d['civil_status'];
$address = $d['resident_address'];
$purpose = $d['purpose'];
$birthdate = $d['birthdate'];
$sex = $d['sex'];
$birth_place = $d['birth_place'];
$month = date("F");
$day = date("d");
$date_issued = date("F d, Y");

$pdf->Text(57, 107, $name);
$pdf->Text(40, 121, $status);
$pdf->Text(20, 114, $address);
$pdf->Text(30, 158, $purpose);
$pdf->Text(100, 128, $birthdate);
$pdf->Text(85, 121, $sex);
$pdf->Text(40, 128, $birth_place);
$pdf->Text(84, 173, $month);
$pdf->Text(58, 173, $day);
$pdf->Text(28, 267, $name); 
$pdf->Text(30, 240, $issued_at); 
$pdf->Text(36, 246, $date_issued); 

$saveDir = __DIR__ . "/../generated_certificates/";
if(!is_dir($saveDir)) mkdir($saveDir, 0777, true);
$filename = 'barangay_cert_' . $id . '.pdf';
$filepath = $saveDir . $filename;
$pdf->Output('F', $filepath);

$stmt = $conn->prepare("
    INSERT INTO generated_certificates 
    (request_id, resident_id, template_id, generated_file, generated_by, remarks, status) 
    VALUES (?, ?, ?, ?, ?, ?, 'Generated')
");
$resident_id = $d['resident_id'];
$template_id = $d['template_id'] ?? 0;
$remarks = 'Generated automatically';
$stmt->bind_param("iiiisi", $id, $resident_id, $template_id, $filename, $user_id, $remarks);
$stmt->execute();
$stmt->close();

// Send email only if request is online
$residentEmail = $d['email_address'] ?? '';
$fullname = $name;
$issuedDate = $date_issued;
if (($d['request_type'] ?? '') === 'online' && !empty($residentEmail)) {
    sendCertificateEmail($residentEmail, $fullname, $purpose, $issuedDate, $filepath, $filename);
}

// Output PDF to browser
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.$filename.'"');
$pdf->Output();
exit;
