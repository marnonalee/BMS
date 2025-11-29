<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../vendor/autoload.php';
include '../db.php'; 

$settingsQuery = $conn->query("SELECT system_email, app_password, barangay_name FROM system_settings LIMIT 1");
$settings = $settingsQuery->fetch_assoc();
$systemEmail = $settings['system_email'] ;
$appPassword = $settings['app_password'] ?? '';
$barangayName = $settings['barangay_name'] ?? 'Certificate System';

function sendCertificateEmail($residentEmail, $fullname, $purpose, $issuedDate, $pdfPath, $filename) {
    global $systemEmail, $appPassword, $barangayName;

    if (empty($residentEmail) || !file_exists($pdfPath)) return false;

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
        $mail->addAddress($residentEmail, $fullname);
        $mail->addAttachment($pdfPath, $filename);
        $mail->isHTML(true);
        $mail->Subject = "Your Certificate is Ready";
        $mail->Body    = "
            <p>Dear {$fullname},</p>
            <p>Your requested certificate has been generated and is attached to this email.</p>
            <p><strong>Purpose:</strong> {$purpose}<br>
               <strong>Date Issued:</strong> {$issuedDate}</p>
            <p>Thank you,<br>{$barangayName}</p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Certificate email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>
