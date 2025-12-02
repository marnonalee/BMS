<?php
session_start();
include '../db.php';
$user_id = $_SESSION['user_id'] ?? 0;

if (!isset($_GET['id'])) exit('No ID.');
$id = intval($_GET['id']);

$q = $conn->prepare("
    SELECT r.*, cr.purpose, cr.template_id, cr.request_type
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
$page = $pdf->setSourceFile(__DIR__."/../templates/goodmoral_cert.pdf");
$tpl = $pdf->importPage(1);
$pdf->useTemplate($tpl, 0, 0, 210);
$pdf->SetFont("Times", "", 11);

$fullname = trim($d['first_name'].' '.$d['last_name']);
$birthdate = $d['birthdate'];
$age = date_diff(date_create($birthdate), date_create('today'))->y;
$address = $d['resident_address'] ?? '';
$purpose = $d['purpose'] ?? '';

$day = date("d");
$month = date("F");
$year = date("y");

$pdf->Text(84, 121, $fullname);
$pdf->Text(136, 121, $age);
$pdf->Text(25, 129, $address);
$pdf->Text(25, 170, $purpose);
$pdf->Text(84, 192, $day);
$pdf->Text(51, 192, $month);
$pdf->Text(105, 192, $year);

$saveDir = __DIR__ . "/../generated_certificates/";
if(!is_dir($saveDir)) mkdir($saveDir, 0777, true);
$filename = 'goodmoral_' . $id . '.pdf';
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

$residentEmail = $d['email_address'] ?? '';
$issuedDate = $month." ".$day.", ".$year;
if (($d['request_type'] ?? '') === 'online' && !empty($residentEmail)) {
    sendCertificateEmail($residentEmail, $fullname, $purpose, $issuedDate, $filepath, $filename);
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.$filename.'"');
$pdf->Output();
exit;
?>
