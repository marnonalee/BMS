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
if (!$d) exit("Not found");

$settingsQuery = $conn->query("SELECT barangay_name FROM system_settings LIMIT 1");
$settings = $settingsQuery->fetch_assoc();
$issued_at = $settings['barangay_name'] ?? '';

require_once(__DIR__ . '/../../fpdf/fpdf.php');
require_once(__DIR__ . '/../../fpdi/src/autoload.php');
require_once('send_certificate_email.php');

$pdf = new \setasign\Fpdi\Fpdi();
$pdf->AddPage();
$page = $pdf->setSourceFile(__DIR__."/../templates/RESIDENCY.pdf");
$tpl = $pdf->importPage(1);
$pdf->useTemplate($tpl, 0, 0, 210);
$pdf->SetFont("Times", "", 11);

$fullname = trim($d['first_name'].' '.$d['last_name']);
$street = $d['street'] ?? '';
$resident_address = $d['resident_address'] ?? '';
$full_address = trim($street.' '.$resident_address);

$years_lived_number = intval($d['years_lived'] ?? 0);
function numberToWord($num){
    $words1 = [0=>"Zero",1=>"One",2=>"Two",3=>"Three",4=>"Four",5=>"Five",6=>"Six",7=>"Seven",8=>"Eight",9=>"Nine",10=>"Ten",11=>"Eleven",12=>"Twelve",13=>"Thirteen",14=>"Fourteen",15=>"Fifteen",16=>"Sixteen",17=>"Seventeen",18=>"Eighteen",19=>"Nineteen"];
    $words2 = [2=>"Twenty",3=>"Thirty",4=>"Forty",5=>"Fifty",6=>"Sixty",7=>"Seventy",8=>"Eighty",9=>"Ninety"];
    if($num < 20) return $words1[$num];
    if($num < 100){
        $tens = intdiv($num,10);
        $ones = $num % 10;
        return $words2[$tens].($ones?'-'.$words1[$ones]:'');
    }
    return (string)$num;
}
$years_lived_word = numberToWord($years_lived_number);

$purpose = $d['purpose'] ?? '';
$issued_day = date("d");
$issued_month_year = date("F Y");

$pdf->Text(90, 120, $fullname);
$pdf->Text(55, 129, $full_address);
$pdf->Text(40, 156, $years_lived_word);
$pdf->Text(85, 150, $fullname);
$pdf->Text(67, 156, "$years_lived_number");
$pdf->Text(30, 180, $purpose);
$pdf->Text(45, 195, $issued_day);
$pdf->Text(75, 195, $issued_month_year);

$saveDir = __DIR__ . "/../generated_certificates/";
if(!is_dir($saveDir)) mkdir($saveDir, 0777, true);
$filename = 'residency_' . $id . '.pdf';
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
$issuedDate = $issued_month_year." ".$issued_day;
if (($d['request_type'] ?? '') === 'online' && !empty($residentEmail)) {
    sendCertificateEmail($residentEmail, $fullname, $purpose, $issuedDate, $filepath, $filename);
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.$filename.'"');
$pdf->Output();
exit;
?>
