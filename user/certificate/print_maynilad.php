<?php
session_start();
include '../db.php';
include 'send_certificate_email.php';

$user_id = $_SESSION['user_id'] ?? 0;
if (!isset($_GET['id'])) exit('No ID.');
$id = intval($_GET['id']);

$stmt = $conn->prepare("
    SELECT cr.*, r.first_name AS resident_first, r.last_name AS resident_last,
           r.email_address, cr.template_id, cr.request_type
    FROM certificate_requests cr
    JOIN residents r ON cr.resident_id = r.resident_id
    WHERE cr.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$d = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$d) exit("Not found"); 

require_once(__DIR__ . '/../../fpdf/fpdf.php');
require_once(__DIR__ . '/../../fpdi/src/autoload.php');

$pdf = new \setasign\Fpdi\Fpdi();
$pdf->AddPage();

$page = $pdf->setSourceFile(__DIR__."/../templates/maynilad.pdf");
$tpl = $pdf->importPage(1);
$pdf->useTemplate($tpl, 0, 0, 210);
$pdf->SetFont("Times", "", 11);

$full_name = trim($d['resident_first'] . " " . $d['resident_last']);
$issued_day = date("d");
$issued_month = date("F");

$pdf->Text(90, 113, $full_name);
$pdf->Text(43, 182, $issued_day);
$pdf->Text(60, 182, " $issued_month");

$saveDir = __DIR__ . "/../generated_certificates/";
if (!is_dir($saveDir)) mkdir($saveDir, 0777, true);

$filename = 'maynilad_' . $id . '.pdf';
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
if (($d['request_type'] ?? '') === 'online' && !empty($residentEmail)) {
    sendCertificateEmail($residentEmail, $full_name, '', "$issued_month $issued_day", $filepath, $filename);
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.$filename.'"');
$pdf->Output();
exit;
?>
