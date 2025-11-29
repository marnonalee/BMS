<?php
session_start();
include '../db.php';
include 'send_certificate_email.php';
$user_id = $_SESSION['user_id'] ?? 0;

if(!isset($_GET['id'])) exit('No ID.');
$id = intval($_GET['id']);

$stmt = $conn->prepare("
    SELECT cr.*, r.first_name AS guardian_first, r.last_name AS guardian_last,
           r.age AS guardian_age, r.resident_address AS guardian_address, r.civil_status AS guardian_status,
           COALESCE(c.first_name, cr.child_fullname) AS child_first,
           COALESCE(c.last_name, '') AS child_last,
           COALESCE(c.age, TIMESTAMPDIFF(YEAR, cr.child_birthdate, CURDATE())) AS child_age,
           cr.child_birthdate, cr.child_birthplace, c.resident_address AS child_address, cr.template_id,
           r.email_address, cr.request_type
    FROM certificate_requests cr
    JOIN residents r ON cr.resident_id = r.resident_id
    LEFT JOIN residents c ON cr.child_id = c.resident_id
    WHERE cr.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$d = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$d) exit("Not found");

require_once(__DIR__ . '/../../fpdf/fpdf.php');
require_once(__DIR__ . '/../../fpdi/src/autoload.php');

$pdf = new \setasign\Fpdi\Fpdi();
$pdf->AddPage();
$page = $pdf->setSourceFile(__DIR__."/../templates/guardianship.pdf");
$tpl = $pdf->importPage(1);
$pdf->useTemplate($tpl, 0, 0, 210);
$pdf->SetFont("Times", "", 11);

$guardian_name = $d['guardian_first'] . " " . $d['guardian_last'];
$guardian_address = $d['guardian_address'];
$child_name = trim($d['child_first'] . " " . $d['child_last']);
$child_age = $d['child_age'];
$child_birthdate = $d['child_birthdate'];
$child_birth_place = $d['child_birthplace'];
$purpose = $d['purpose'] ?? '';
$month = date("F");
$day = date("d");

$pdf->Text(80, 126, $guardian_name);
$pdf->Text(150, 126, $guardian_address);
$pdf->Text(55, 154, $child_name);
$pdf->Text(110, 154, $child_age);
$pdf->Text(25, 160, $child_birth_place);
$pdf->Text(65, 160, $child_birthdate);
$pdf->Text(27, 183, $purpose);
$pdf->Text(45, 202, $day);
$pdf->Text(70, 202, $month);

$saveDir = __DIR__ . "/../generated_certificates/";
if(!is_dir($saveDir)) mkdir($saveDir, 0777, true);
$filename = 'guardianship_' . $id . '.pdf';
$filepath = $saveDir . $filename;
$pdf->Output('F', $filepath);

$stmt2 = $conn->prepare("
    INSERT INTO generated_certificates 
    (request_id, resident_id, template_id, generated_file, generated_by, remarks, status) 
    VALUES (?, ?, ?, ?, ?, ?, 'Generated')
");
$resident_id = $d['resident_id'];
$template_id = $d['template_id'] ?? 0;
$remarks = 'Generated automatically';
$stmt2->bind_param("iiiisi", $id, $resident_id, $template_id, $filename, $user_id, $remarks);
$stmt2->execute();
$stmt2->close();

$residentEmail = $d['email_address'] ?? '';
$issuedDate = date("F d, Y");
if (($d['request_type'] ?? '') === 'online' && !empty($residentEmail)) {
    sendCertificateEmail($residentEmail, $guardian_name, $purpose, $issuedDate, $filepath, $filename);
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.$filename.'"');
$pdf->Output();
exit;
