<?php
session_start();
include 'db.php';
include 'log_activity.php';

$error = "";
$email = "";

$settings = $conn->query("SELECT system_email, app_password, barangay_name, system_logo FROM system_settings LIMIT 1")->fetch_assoc();

$barangayName = $settings['barangay_name'] ?? 'Barangay Management System';
$systemLogo = $settings['system_logo'] ?? 'default_logo.png';
$loginBgPath = 'img/default_profile.jpg'; 
$systemLogoPath = 'user/' . $systemLogo;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if ((int)$user["email_verified"] === 0) {
            $error = "❌ Your email is not verified. <a href='verify_email.php?email=" . urlencode($email) . "'>Click here to verify your email</a>.";
        } elseif ((int)$user["archived"] === 1) {
            $error = "⛔ Your account has been blocked or deleted. Please contact the administrator.";
        } elseif (password_verify($password, $user["password"])) {

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["first_name"] = $user["first_name"] ?? '';
            $_SESSION["last_name"] = $user["last_name"] ?? '';
            $_SESSION["email"] = $user["email"];
            $_SESSION["role"] = $user["role"];
            $_SESSION["login_success"] = "✅ Welcome back, " . $user["username"] . "!";

            $roleText = ucfirst($user["role"]);
            logActivity($user["id"], $user["username"], "$roleText logged in");

            if ($user["role"] === "resident") {
                header("Location: resident/dashboard.php");
            } else {
                header("Location: user/dashboard.php");
            }
            exit();
        } else {
            $error = "❌ Wrong password. Please try again.";
        }
    } else {
        $error = "❌ No account found with that email.";
        $email = "";
    }

    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login - <?= htmlspecialchars($barangayName) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="font-sans">

<div class="relative min-h-screen bg-cover bg-center" style="background-image: url('<?= htmlspecialchars($loginBgPath) ?>');">
  
  <div class="absolute inset-0 bg-gradient-to-b from-blue-500/40 via-blue-500/30 to-blue-500/50 flex items-center justify-center p-4">

    <div class="bg-white bg-opacity-95 rounded-2xl shadow-lg p-8 w-full max-w-md">
      <div class="text-center mb-6">
        <img src="<?= htmlspecialchars($systemLogoPath) ?>" alt="System Logo" class="w-32 mx-auto mb-4">
        <h2 class="text-xl font-bold">Resident Access Portal</h2>
        <h3 class="text-lg font-semibold mt-1">Welcome back! Please login to your account.</h3>
      </div>

      <?php if (!empty($error)) : ?>
        <div class="bg-red-100 border-l-4 border-red-500 p-3 mb-4">
          <?= $error ?>
        </div>
      <?php endif; ?>

      <form action="" method="POST" class="flex flex-col gap-4">
        <div class="flex flex-col gap-1">
          <label class="font-semibold text-gray-700">Email</label>
          <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required class="border border-gray-300 rounded-md p-2 focus:outline-none focus:border-blue-500">
        </div>

        <div class="flex flex-col gap-1">
          <label class="font-semibold text-gray-700">Password</label>
          <input type="password" name="password" required class="border border-gray-300 rounded-md p-2 focus:outline-none focus:border-blue-500">
        </div>

        <div class="text-right">
          <a href="forgot_password.php" class="text-sm text-blue-600 hover:underline">Forgot Password?</a>
        </div>

        <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-md font-bold">LOGIN</button>
      </form>

      <div class="text-center mt-4 text-sm text-gray-600">
        Don't have an account? <a href="register.php" class="text-blue-600 font-bold hover:underline">Sign Up</a>
      </div>

    </div>
  </div>
</div>

</body>
</html>
