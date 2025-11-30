<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../vendor/autoload.php';
include '../db.php'; 

$settingsQuery = $conn->query("SELECT system_email, app_password, barangay_name, contact_number FROM system_settings LIMIT 1");
$settings = $settingsQuery->fetch_assoc();
$systemEmail = $settings['system_email'];
$appPassword = $settings['app_password'] ?? '';
$barangayName = $settings['barangay_name'] ?? 'Certificate System';
$contactNumber = $settings['contact_number'] ?? 'N/A';

function sendCertificateEmail($residentEmail, $fullname, $purpose, $issuedDate, $resident_id = null) {
    global $systemEmail, $appPassword, $barangayName, $conn, $contactNumber;

    if (empty($residentEmail)) return false;

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

        $mail->isHTML(true);
        $mail->Subject = "Certificate Request Update";
        $mail->Body    = "
            <p>Dear {$fullname},</p>
            <p>Ang iyong certificate request ay na-approve at handa na para sa pickup.</p>
            <p><strong>Purpose:</strong> {$purpose}<br>
               <strong>Date Issued:</strong> {$issuedDate}</p>
            <p>Kung mayroon kayong katanungan, kontakin kami sa {$contactNumber}.</p>
            <p>Salamat,<br>{$barangayName}</p>
        ";

        $mail->send();

        if ($resident_id) {
            $message = "Ang iyong certificate request ay na-approve. Purpose: {$purpose}";
            $stmt = $conn->prepare("INSERT INTO notifications (resident_id, message, from_role, title, type, priority, action_type, is_read, sent_email, date_created) VALUES (?, ?, 'system', 'Certificate Approved', 'general', 'normal', 'updated', 0, 1, NOW())");
            $stmt->bind_param("is", $resident_id, $message);
            $stmt->execute();
            $stmt->close();
        }

        return true;
    } catch (Exception $e) {
        error_log("Certificate email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>
