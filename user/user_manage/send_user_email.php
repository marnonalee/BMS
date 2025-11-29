<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


require '../../vendor/autoload.php';
include '../db.php'; 

function sendUserEmail($recipientEmail, $recipientName, $rawPassword) {
    global $conn;

    $settingsQuery = $conn->query("SELECT system_email, app_password, barangay_name FROM system_settings LIMIT 1");
    $settings = $settingsQuery->fetch_assoc();
    $systemEmail = $settings['system_email'];
    $appPassword = $settings['app_password'];
    $barangayName = $settings['barangay_name'];

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
        $mail->addAddress($recipientEmail, $recipientName);

        $mail->isHTML(true);
        $mail->Subject = 'Your Account has been Created';
        $mail->Body = "Hello $recipientName,<br><br>
                       Your account has been created.<br>
                       Email: $recipientEmail<br>
                       Password: $rawPassword<br><br>
                       Please log in and change your password immediately.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return "Mailer Error: {$mail->ErrorInfo}";
    }
}
?>
