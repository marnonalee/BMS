<?php
session_start();
include 'db.php';

$error = '';
$success = '';
$email = $_SESSION['reset_email'] ?? '';

if (!$email) {
    header("Location: forgot_password.php");
    exit;
}

if (isset($_POST['reset_password'])) {
    $password = trim($_POST['password']);
    $confirm = trim($_POST['confirm_password']);
    $errors = [];

    if (strlen($password) < 8) $errors[] = "At least 8 characters required";
    if (!preg_match('/[A-Z]/', $password)) $errors[] = "Uppercase letter required";
    if (!preg_match('/[a-z]/', $password)) $errors[] = "Lowercase letter required";
    if (!preg_match('/\d/', $password)) $errors[] = "Number required";
    if (!preg_match('/[^A-Za-z0-9]/', $password)) $errors[] = "Special character required";
    if ($password !== $confirm) $errors[] = "Passwords do not match";

    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE email=?");
        $stmt->bind_param("ss", $hashed, $email);
        if ($stmt->execute()) {
            $getUser = $conn->prepare("SELECT id, role FROM users WHERE email=?");
            $getUser->bind_param("s", $email);
            $getUser->execute();
            $result = $getUser->get_result();
            $user = $result->fetch_assoc();
            $getUser->close();

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $email;
            $_SESSION['role'] = $user['role'] ?? 'admin';
            unset($_SESSION['reset_email']);

            $success = "✅ Password has been reset successfully! Redirecting...";
        } else {
            $error = "❌ Failed to reset password. Please try again.";
        }
        $stmt->close();
    } else {
        $error = implode("<br>", $errors);
    }
}
$conn->close();
$settingsQuery = $conn->query("SELECT system_email, app_password, barangay_name, system_logo FROM system_settings LIMIT 1");
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
<title>Reset Password - <?= htmlspecialchars($barangayName) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="font-sans">

<div class="relative min-h-screen bg-cover bg-center" style="background-image: url('<?= htmlspecialchars($loginBgPath) ?>');">
  <div class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4">

    <div class="bg-white bg-opacity-95 rounded-2xl shadow-lg p-8 w-full max-w-md">

      <div class="text-center mb-6">
        <img src="<?= htmlspecialchars($systemLogoPath) ?>" alt="System Logo" class="w-32 mx-auto mb-4">
        <h2 class="text-xl font-bold"><?= htmlspecialchars($barangayName) ?></h2>
        <h3 class="text-lg font-semibold mt-1">🔑 Reset Your Password</h3>
      </div>

      <?php if(!empty($error)) : ?>
        <div class="bg-red-100 border-l-4 border-red-500 p-3 mb-4">
          <?= $error ?>
        </div>
      <?php endif; ?>

      <?php if(!empty($success)) : ?>
        <div class="bg-green-100 border-l-4 border-green-500 p-3 mb-4 text-center">
          <?= $success ?>
        </div>
      <?php endif; ?>

      <form method="POST" id="resetForm" class="flex flex-col gap-4">

        <div class="flex flex-col gap-1">
          <label class="font-semibold text-gray-700">New Password</label>
          <input type="password" id="password" name="password" placeholder="Enter new password" required maxlength="100"
            class="border border-gray-300 rounded-md p-2 focus:outline-none focus:border-blue-500">
          <div id="password-requirements" class="text-sm text-gray-600 mt-1">
            <ul class="list-disc pl-5">
              <li id="length" class="invalid">At least 8 characters</li>
              <li id="uppercase" class="invalid">Uppercase letter</li>
              <li id="lowercase" class="invalid">Lowercase letter</li>
              <li id="number" class="invalid">Number</li>
              <li id="special" class="invalid">Special character (!@#$...)</li>
            </ul>
          </div>
        </div>

        <div class="flex flex-col gap-1">
          <label class="font-semibold text-gray-700">Confirm Password</label>
          <input type="password" id="confirm_password" name="confirm_password" placeholder="Complete password requirements first" required maxlength="100" disabled
            class="border border-gray-300 rounded-md p-2 bg-gray-100 focus:outline-none">
          <div id="match-error" class="text-red-500 text-sm hidden">Passwords do not match.</div>
        </div>

        <button type="submit" name="reset_password" class="w-full bg-blue-600 text-white py-3 rounded-md font-bold">Reset Password</button>

      </form>

      <div class="text-center mt-4 text-sm text-gray-600">
        <a href="index.php" class="text-blue-600 font-bold hover:underline">Back to Login</a>
      </div>

    </div>

  </div>
</div>

<script src="reset-password.js"></script>
</body>
</html>
