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

/**
 * Convert any image to PNG with transparency
 */
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
if(!$requestId) die("Di wastong Request ID");

$req = $conn->query("
    SELECT r.first_name,r.middle_name,r.last_name,r.birthdate,r.resident_address,
           r.birth_place,r.sex,r.email_address,r.resident_id,b.id_number,b.status,
           b.request_type,b.nature_of_residency,b.emergency_name,b.emergency_contact,
           b.picture,b.signature
    FROM barangay_id_requests b
    JOIN residents r ON b.resident_id=r.resident_id
    WHERE b.id=$requestId
")->fetch_assoc();

$templatePath = __DIR__ . '/../templates/Barangay_id.pdf';
$generatedDir = __DIR__ . '/../generated_ids/';
if(!file_exists($generatedDir)) mkdir($generatedDir,0777,true);

$generatedFile = $generatedDir . "Barangay_ID_{$requestId}.pdf";

if($req['status'] === 'Approved') {
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

    $pdf->Output($generatedFile,'F');

    $conn->query("
        UPDATE barangay_id_requests
        SET generated_pdf='generated_ids/Barangay_ID_{$requestId}.pdf'
        WHERE id=$requestId
    ");
}

if(!empty($req['email_address'])) {
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
        $mail->isHTML(true);

        if($req['status'] === 'Approved'){
            $mail->Subject = 'Approved Barangay ID Request';
            $mail->Body    = "
                Kamusta {$req['first_name']} {$req['last_name']},<br><br>
                Ang iyong request para sa Barangay ID ay <strong>approved</strong> na.<br>
                <strong>ID Number:</strong> {$req['id_number']}<br><br>
                Maraming salamat,<br>{$barangayName}
            ";
        } elseif($req['status'] === 'Rejected'){
            $mail->Subject = 'Hindi Approved Barangay ID Request';
            $mail->Body    = "
                Kamusta {$req['first_name']} {$req['last_name']},<br><br>
                Paumanhin, ang iyong request para sa Barangay ID ay <strong>hindi approved</strong>.<br>
                Mangyaring bumisita sa tanggapan ng Barangay para sa karagdagang impormasyon.<br><br>
                Maraming salamat,<br>{$barangayName}
            ";
        }

        $mail->send();
        $successFlag = 1;
    } catch (Exception $e){
        $successFlag = 0;
    }
}

$message = ($req['status'] === 'Approved') ? 
    "Ang iyong Barangay ID request ay approved." : 
    "Paumanhin, ang iyong Barangay ID request ay hindi approved.";
$title = ($req['status'] === 'Approved') ? "Aprubadong Request" : "Rejected Request";

$fromRole = $_SESSION['role'] ?? 'system'; 

$stmt = $conn->prepare("
    INSERT INTO notifications 
(resident_id, message, from_role, title, type, priority, action_type, is_read, sent_email, date_created) 
VALUES (?, ?, ?, 'general', 'normal', 'updated', 0, 0,0, NOW())

");
$stmt->bind_param("iss", $resident_id, $message, $fromRole);
$stmt->execute();

$stmt->bind_param("iss", $req['resident_id'], $message, $title);
$stmt->execute();

header("Location: barangay_id_requests.php?email_sent={$successFlag}");
exit;
?>
