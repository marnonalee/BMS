<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../../login.php");
    exit();
}

include '../db.php';
require_once(__DIR__ . '/../../fpdf/fpdf.php');
require_once(__DIR__ . '/../../fpdi/src/autoload.php');
use setasign\Fpdi\Fpdi;

require_once __DIR__ . '/../../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../../PHPMailer/src/Exception.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function toPngTransparent($src){
    if(!file_exists($src) || filesize($src) < 100) return false;
    $data = file_get_contents($src);
    if(!$data) return false;
    $img = @imagecreatefromstring($data);
    if(!$img) return false;
    $out = preg_replace('/\.(png|jpg|jpeg|gif|webp)$/i', '.png', $src);
    $w = imagesx($img);
    $h = imagesy($img);
    $bg = imagecreatetruecolor($w, $h);
    imagesavealpha($bg, true);
    $transparency = imagecolorallocatealpha($bg, 0, 0, 0, 127);
    imagefill($bg, 0, 0, $transparency);
    imagecopy($bg, $img, 0, 0, 0, 0, $w, $h);
    imagepng($bg, $out);
    imagedestroy($img);
    imagedestroy($bg);
    return $out;
}

$requestId = intval($_GET['request_id'] ?? 0);
if(!$requestId) die("Invalid Request ID");

$req = $conn->query("
    SELECT r.first_name,r.middle_name,r.last_name,r.birthdate,r.resident_address,
           r.birth_place,r.sex,r.email_address,b.id_number,
           b.request_type,b.nature_of_residency,b.emergency_name,b.emergency_contact,
           b.picture,b.signature
    FROM barangay_id_requests b
    JOIN residents r ON b.resident_id=r.resident_id
    WHERE b.id=$requestId
")->fetch_assoc();

$templatePath = __DIR__ . '/../templates/Barangay_id.pdf';
$generatedDir = __DIR__ . '/../generated_ids/';
if(!file_exists($generatedDir)) mkdir($generatedDir,0777,true);

$pdf = new FPDI();
$pdf->AddPage();
$pageId = $pdf->setSourceFile($templatePath);
$page1 = $pdf->importPage(1);
$pdf->useTemplate($page1);

$pdf->SetFont('Helvetica','',6);
$pdf->SetXY(67,39.5);  $pdf->Write(0, $req['last_name']);
$pdf->SetXY(67,45.5);  $pdf->Write(0, $req['first_name']);
$pdf->SetXY(67,51.4);  $pdf->Write(0, $req['middle_name']);
$pdf->SetXY(67,57);    $pdf->Write(0, $req['birthdate']);
$pdf->SetXY(115,29.3); $pdf->Write(0, $req['resident_address']);
$pdf->SetXY(115,34.5); $pdf->Write(0, $req['birth_place']);
$pdf->SetXY(154,34.5); $pdf->Write(0, $req['sex']);
$pdf->SetXY(154,40);   $pdf->Write(0, $req['nature_of_residency']);
$pdf->SetXY(115,51.8); $pdf->Write(0, $req['emergency_name']);
$pdf->SetXY(154,51.8); $pdf->Write(0, $req['emergency_contact']);
$pdf->SetXY(115,40);   $pdf->Write(0, date('m/d/Y', strtotime('+1 year')));

$base = realpath(__DIR__ . '/../../resident/uploads/') . '/';
$pic = toPngTransparent($base.$req['picture']);
$sig = toPngTransparent($base.$req['signature']);

if($pic && file_exists($pic)) $pdf->Image($pic,34,40,20,20);
if($sig && file_exists($sig)) $pdf->Image($sig,67,61,30,4);

$pdf->SetXY(34, 62);
$pdf->SetFont('Helvetica', 'B', 7);
$pdf->Write(0,($req['id_number'] ?? 'N/A'));

$generatedFile = $generatedDir . "Barangay_ID_{$requestId}.pdf";
$pdf->Output($generatedFile,'F');

$conn->query("
    UPDATE barangay_id_requests
    SET generated_pdf='generated_ids/Barangay_ID_{$requestId}.pdf'
    WHERE id=$requestId
");
$successFlag = 0;

if($req['request_type'] === 'Walk-in') {
    $successFlag = 1;
} elseif(!empty($req['email_address']) && file_exists($generatedFile)) {
    $settingsQuery = $conn->query("SELECT system_email, app_password, barangay_name FROM system_settings LIMIT 1");
    $settings = $settingsQuery->fetch_assoc();
    $systemEmail = $settings['system_email'];
    $appPassword = $settings['app_password'] ?? '';
    $barangayName = $settings['barangay_name'] ?? 'Barangay Certificate System';

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $systemEmail;
        $mail->Password   = $appPassword;
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom($systemEmail, $barangayName);
        $mail->addAddress($req['email_address'], $req['first_name'].' '.$req['last_name']);
        $mail->addAttachment($generatedFile, "Barangay_ID.pdf");

        $mail->isHTML(true);
        $mail->Subject = 'Your Barangay ID is Ready';
        $mail->Body    = "Hello {$req['first_name']} {$req['last_name']},<br><br>Your Barangay ID has been approved and is attached.<br>ID Number: <strong>{$req['id_number']}</strong><br><br>Thank you,<br>{$barangayName}";
        $mail->send();

        $successFlag = 1;
    } catch (Exception $e){}
}

header("Location: barangay_id_requests.php?email_sent={$successFlag}");
exit;
?>
