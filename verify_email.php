<?php  
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

include 'db.php';
session_start();

$error = "";
$success = false;

$settingsQuery = $conn->query("SELECT system_email, app_password, barangay_name, system_logo FROM system_settings LIMIT 1");
$settings = $settingsQuery->fetch_assoc();
$systemEmail = $settings['system_email'] ?? '';
$appPassword  = $settings['app_password'] ?? '';
$barangayName = $settings['barangay_name'] ?? 'Barangay Management System';

function maskEmail($email) {
    if (empty($email)) return "";
    $parts = explode("@", $email);
    $name = $parts[0];
    $domain = $parts[1] ?? '';
    if (strlen($name) <= 2) {
        $masked = substr($name, 0, 1) . str_repeat("*", strlen($name) - 1);
    } else {
        $masked = substr($name, 0, 1) . str_repeat("*", strlen($name) - 2) . substr($name, -1);
    }
    return $masked . "@" . $domain;
}

$email = $_SESSION["email"] ?? ($_GET['email'] ?? null);
if (!$email) {
    $error = "❌ No email found. Please go back and login again.";
}

$otpExpired = !isset($_SESSION['otp']) || time() > ($_SESSION['otp_expiry'] ?? 0);

if (isset($_POST['resend_otp']) || $otpExpired) {
    $otp = rand(100000, 999999);
    $_SESSION['otp'] = $otp;
    $_SESSION['email'] = $email;
    $_SESSION['otp_expiry'] = time() + (5 * 60); 

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
        $mail->Subject = 'Verification Code';
        $mail->isHTML(true);
        $mail->Body = "
            <p>To verify your account, please enter this code on the verification page:</p>
            <h2 style='color:#000; font-size:28px; letter-spacing:3px;'>{$otp}</h2>
            <p><b>⚠️ Note:</b> This code is valid for <u>5 minutes</u>.</p>
            <p style='color:#d9534f; font-size:14px;'><b>Do not share this code with anyone.</b></p>
        ";

        $mail->send();
        $error = "✅ A new OTP has been sent to your email.";
        $otpExpired = false; 
    } catch (Exception $e) {
        $error = "❌ OTP could not be sent. Mailer Error: " . $mail->ErrorInfo;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['verify_otp'])) {
    $code = trim($_POST["verification_code"]);
    $otp = $_SESSION['otp'] ?? null;
    $otp_expiry = $_SESSION['otp_expiry'] ?? 0;

    if (empty($code)) {
        $error = "❌ Missing verification code.";
    } elseif (!$otp || time() > $otp_expiry) {
        $error = "❌ Your OTP code is expired. Please click 'Resend OTP'.";
        unset($_SESSION['otp']);
        unset($_SESSION['otp_expiry']);
        $otpExpired = true; 
    } elseif ($code != $otp) {
        $error = "❌ Invalid verification code.";
    } else {
        $update = $conn->prepare("UPDATE users SET email_verified=1 WHERE email=?");
        $update->bind_param("s", $email);
        if ($update->execute()) {
            $success = true;
            $stmt = $conn->prepare("SELECT * FROM users WHERE email=?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows == 1) {
                $row = $result->fetch_assoc();
                $_SESSION["user_id"] = $row["id"];
                $_SESSION["email"]   = $row["email"];
                $_SESSION["role"]    = $row["role"];
                $_SESSION["username"]= $row["username"];
                unset($_SESSION['otp']);
                unset($_SESSION['otp_expiry']);
            }
        } else {
            $error = "❌ Something went wrong. Please try again.";
        }
        $update->close();
    }
}

$conn->close();
$remainingTime = isset($_SESSION['otp_expiry']) ? $_SESSION['otp_expiry'] - time() : 0;


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
<title>Verify Code - <?= htmlspecialchars($barangayName) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="font-sans">


<div class="relative min-h-screen bg-cover bg-center" style="background-image: url('<?= htmlspecialchars($loginBgPath) ?>');">
 
  <div class="absolute inset-0 bg-gradient-to-b from-blue-500/40 via-blue-500/30 to-blue-500/50 flex items-center justify-center p-4">

    <div class="bg-white bg-opacity-95 rounded-2xl shadow-lg p-8 w-full max-w-md">

      <div class="text-center mb-6">
        <img src="<?= htmlspecialchars($systemLogoPath) ?>" alt="System Logo" class="w-32 mx-auto mb-4">
        <h3 class="text-lg font-semibold mt-1">📩 Verify Your Email</h3>
      </div>

      <?php if(!empty($error)) : ?>
        <div class="bg-red-100 border-l-4 border-red-500 p-3 mb-4">
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <?php if(!$success): ?>
        <p class="text-center mb-6 text-gray-700">
          We’ve sent a 6-digit verification code to: <b><?= htmlspecialchars(maskEmail($email)) ?></b><br>
          Please enter it below to continue.
        </p>

        <form method="POST" class="flex flex-col gap-4">

          <div class="flex flex-col gap-1">
            <label class="font-semibold text-gray-700">Enter OTP Code</label>
            <input 
              type="text" 
              name="verification_code" 
              maxlength="6" 
              placeholder="Enter 6-digit code" 
              required 
              pattern="[0-9]{6}" 
              inputmode="numeric"
              oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,6)"
              class="border border-gray-300 rounded-md p-2 focus:outline-none focus:border-blue-500"
            >
          </div>

          <button type="submit" name="verify_otp" class="w-full bg-blue-600 text-white py-3 rounded-md font-bold">Verify Code</button>

        </form>

        <div class="text-center mt-4 text-sm text-gray-600">
          <?php if ($otpExpired): ?>
            <form method="POST">
              <button type="submit" name="resend_otp" class="text-blue-600 font-bold hover:underline">Resend Code</button>
            </form>
          <?php else: ?>
            ⌛ You can request a new OTP in <span id="timer"><?= $remainingTime ?></span> seconds.
          <?php endif; ?>
        </div>

        <div class="text-center mt-4 text-sm text-gray-600">
          <a href="login.php" class="text-blue-600 font-bold hover:underline">Back to Login</a>
        </div>

      <?php else: ?>
        <div class="bg-green-100 border-l-4 border-green-500 p-3 mb-4 text-center">
          🎉 Your email has been verified successfully!<br>
          Redirecting to your dashboard...
        </div>
        <script>
        setTimeout(() => {
            let role = "<?= $_SESSION['role'] ?>";
            if(role === "resident") window.location.href = "resident/dashboard.php";
            else if(role === "staff") window.location.href = "user/dashboard.php";
            else if(role === "admin") window.location.href = "admin/dashboard.php";
            else window.location.href = "index.php";
        }, 2000);
        </script>
      <?php endif; ?>

    </div>

  </div>
</div>

<script>
let timerElement = document.getElementById('timer');
if(timerElement){
    let time = parseInt(timerElement.textContent);
    let interval = setInterval(() => {
        time--;
        if(time <= 0){
            clearInterval(interval);
            document.querySelector('.text-center.mt-4.text-sm.text-gray-600').innerHTML = `
                ❌ Your OTP has expired. <form method="POST" style="margin-top:10px;">
                <button type="submit" name="resend_otp" class="text-blue-600 font-bold hover:underline">Resend Code</button></form>`;
        } else {
            timerElement.textContent = time;
        }
    }, 1000);
}
</script>

</body>
</html>
