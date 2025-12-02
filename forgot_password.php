<?php
session_start();
include 'db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$error = '';
$showModal = false;

$settingsQuery = $conn->query("SELECT system_email, app_password, barangay_name, system_logo  FROM system_settings LIMIT 1");
$settings = $settingsQuery->fetch_assoc();
$systemEmail = $settings['system_email'] ?? '';
$appPassword = $settings['app_password'] ?? '';
$barangayName = $settings['barangay_name'] ?? 'Barangay Management System';

if (isset($_POST['send_code'])) {
    $email = trim($_POST['email']);

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $error = "❌ No account found with this email.";
    } else {
        $code = rand(100000, 999999);

        $_SESSION['reset_email'] = $email;
        $_SESSION['reset_code'] = $code;

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $systemEmail;
            $mail->Password   = $appPassword;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom($systemEmail, $barangayName);
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Code';
            $mail->Body = "
                <p>Your password reset code:</p>
                <h2 style='font-size:28px;letter-spacing:2px;'>$code</h2>
                <p>This code expires in <b>24 hours</b>.</p>
            ";

            $mail->send();
            $showModal = true;

        } catch (Exception $e) {
            $error = "❌ Failed to send email: " . $mail->ErrorInfo;
        }
    }
}

$barangayName = $settings['barangay_name'] ?? 'Barangay Management System';
$loginBg = $settings['login_bg'] ?? 'default_bg.jpg';
$systemLogo = $settings['system_logo'] ?? 'default_logo.png';
$loginBgPath = 'img/default_profile.jpg'; 
$systemLogoPath = 'user/' . $systemLogo;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Forgot Password - <?= htmlspecialchars($barangayName) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="font-sans">
<div class="relative min-h-screen bg-cover bg-center" style="background-image: url('<?= htmlspecialchars($loginBgPath) ?>');">
 
  <div class="absolute inset-0 bg-gradient-to-b from-blue-500/40 via-blue-500/30 to-blue-500/50 flex items-center justify-center p-4">

    <div class="bg-white bg-opacity-95 rounded-2xl shadow-lg p-8 w-full max-w-md">

      <div class="text-center mb-6">
        <img src="<?= htmlspecialchars($systemLogoPath) ?>" alt="System Logo" class="w-32 mx-auto mb-4">
        <h2 class="text-xl font-bold"><?= htmlspecialchars($barangayName) ?></h2>
        <h3 class="text-lg font-semibold mt-1">Forgot Password</h3>
      </div>

      <?php if (!empty($error)) : ?>
        <div class="bg-red-100 border-l-4 border-red-500 p-3 mb-4">
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <p class="text-center mb-6 text-gray-700">
        Enter your registered email address and we’ll send you a code to reset your password.
      </p>

      <form method="POST" class="flex flex-col gap-4">
        <div class="flex flex-col gap-1">
          <label class="font-semibold text-gray-700">Email</label>
          <input type="email" name="email" placeholder="Enter your email" required
            class="border border-gray-300 rounded-md p-2 focus:outline-none focus:border-blue-500">
        </div>

        <button type="submit" name="send_code" class="w-full bg-blue-600 text-white py-3 rounded-md font-bold">
          Send Reset Code
        </button>
      </form>

      <div class="text-center mt-4 text-sm text-gray-600">
        Remembered your password? 
        <a href="login.php" class="text-blue-600 font-bold hover:underline">Back to Login</a>
      </div>

    </div>

  </div>
</div>

<div id="modal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50" style="display:none;">
  <div class="bg-white rounded-lg shadow-lg p-6 text-center w-full max-w-sm">
    <p class="text-green-600 font-semibold">✅ Reset code is being sent! Please wait...</p>
    <p class="text-gray-700 mt-2">You will be redirected shortly.</p>
  </div>
</div>

<?php if ($showModal): ?>
<script>
  document.getElementById("modal").style.display = "flex";
  setTimeout(() => {
      window.location.href = "send_code.php";
  }, 3000);
</script>
<?php endif; ?>

</body>
</html>
