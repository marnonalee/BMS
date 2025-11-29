<?php
session_start();
include 'db.php';

$error = '';
$success = isset($_SESSION['reset_success']) ? $_SESSION['reset_success'] : '';
unset($_SESSION['reset_success']);
$email = $_SESSION['reset_email'] ?? '';
$code = $_SESSION['reset_code'] ?? '';

if(isset($_POST['verify_code'])) {
    $code_input = trim($_POST['verification_code']);

    if(empty($code_input)) {
        $error = "❌ Missing verification code. (Walang inilagay na code)";
    }
    elseif($code_input != $code) {
        $error = "❌ Invalid reset code. (Maling code)";
    } else {
        unset($_SESSION['reset_code']);
        header("Location: reset_password.php");
        exit;
    }
}

function maskEmail($email) {
    if (empty($email)) return "";
    $parts = explode("@", $email);
    $name = $parts[0];
    $domain = $parts[1] ?? '';
    if(strlen($name) <= 2){
        $masked = substr($name,0,1) . str_repeat("*", strlen($name)-1);
    } else {
        $masked = substr($name,0,1) . str_repeat("*", strlen($name)-2) . substr($name,-1);
    }
    return $masked . "@" . $domain;
}
$settings = $conn->query("SELECT system_email, app_password, barangay_name, login_bg, system_logo FROM system_settings LIMIT 1")->fetch_assoc();


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
<title>Verify Reset Code - <?= htmlspecialchars($barangayName) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="font-sans">

<div class="relative min-h-screen bg-cover bg-center" style="background-image: url('<?= htmlspecialchars($loginBgPath) ?>');">
  <div class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4">

    <div class="bg-white bg-opacity-95 rounded-2xl shadow-lg p-8 w-full max-w-md">

      <div class="text-center mb-6">
        <img src="<?= htmlspecialchars($systemLogoPath) ?>" alt="System Logo" class="w-32 mx-auto mb-4">
        <h2 class="text-xl font-bold">Resident Access Portal</h2>
        <h3 class="text-lg font-semibold mt-1">📩 Verify Reset Code</h3>
      </div>

      <?php if(!empty($success)) : ?>
        <div class="bg-green-100 border-l-4 border-green-500 p-3 mb-4">
          <?= htmlspecialchars($success) ?>
        </div>
      <?php endif; ?>

      <?php if(!empty($error)) : ?>
        <div class="bg-red-100 border-l-4 border-red-500 p-3 mb-4">
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <p class="text-center mb-6 text-gray-700">
        A 6-digit reset code has been sent to: <b><?= htmlspecialchars(maskEmail($email)) ?></b><br>
        Please check your inbox or spam folder.<br>
        (Pakisuri ang iyong inbox o ang <b>spam/junk folder</b> kung hindi mo ito makita.)
      </p>

      <form method="POST" class="flex flex-col gap-4">

        <div class="flex flex-col gap-1">
          <label class="font-semibold text-gray-700">Enter 6-digit Code</label>
          <input 
            type="text" 
            name="verification_code" 
            placeholder="Enter 6-digit code" 
            required 
            maxlength="6" 
            pattern="[0-9]{6}" 
            inputmode="numeric"
            oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0,6)" 
            class="border border-gray-300 rounded-md p-2 focus:outline-none focus:border-blue-500"
          >
        </div>

        <button type="submit" name="verify_code" class="w-full bg-blue-600 text-white py-3 rounded-md font-bold">Verify Code</button>

      </form>

      <div class="text-center mt-4 text-sm text-gray-600">
        <a href="login.php" class="text-blue-600 font-bold hover:underline">Back to Login</a>
      </div>

    </div>

  </div>
</div>

</body>
</html>
