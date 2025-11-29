<?php
session_start();
include 'db.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$error = "";
$success = false;

$first_name = "";
$last_name = "";
$address = "";
$email = "";
$phone = "";
$birthdate = "";
$gender = "";
$password = "";
$confirm_password = "";

$settings = $conn->query("SELECT system_email, app_password, barangay_name, system_logo FROM system_settings LIMIT 1")->fetch_assoc();
$systemEmail = $settings['system_email'] ?? '';
$appPassword = $settings['app_password'] ?? '';
$barangayName = $settings['barangay_name'] ?? 'Barangay Management System';
$systemLogo = $settings['system_logo'] ?? 'default_logo.png';
$loginBgPath = 'img/default_profile.jpg'; 
$systemLogoPath = 'user/' . $systemLogo;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $first_name = trim($_POST["first_name"] ?? '');
    $last_name = trim($_POST["last_name"] ?? '');
    $address = trim($_POST["resident_address"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $phone = trim($_POST["phone"] ?? '');
    $birthdate = trim($_POST["birthdate"] ?? '');
    $gender = trim($_POST["gender"] ?? '');
    $password = $_POST["password"] ?? '';
    $confirm_password = $_POST["confirm_password"] ?? '';

    if (empty($first_name) || empty($last_name) || empty($address) || empty($email) || empty($phone) || empty($birthdate) || empty($gender) || empty($password) || empty($confirm_password)) {
        $error = "Please fill out all fields.";
    } elseif (!preg_match("/^[A-Za-z\s]+$/", $first_name) || !preg_match("/^[A-Za-z\s]+$/", $last_name)) {
        $error = "First name and last name cannot contain numbers.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } elseif (!preg_match("/^09[0-9]{9}$/", $phone)) {
        $error = "Phone must start with 09 and be 11 digits.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
        $password = "";
        $confirm_password = "";
    } elseif (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $password)) {
        $error = "Weak password format.";
    } elseif (!isset($_FILES['valid_id']) || $_FILES['valid_id']['error'] !== UPLOAD_ERR_OK) {
        $error = "Please upload a valid ID.";
    } else {
        $check = $conn->prepare("SELECT resident_id FROM residents WHERE LOWER(TRIM(first_name))=LOWER(?) AND LOWER(TRIM(last_name))=LOWER(?) AND birthdate=? AND LOWER(TRIM(resident_address))=LOWER(?)");
        $check->bind_param("ssss", $first_name, $last_name, $birthdate, $address);
        $check->execute();
        $check->store_result();

        if ($check->num_rows === 0) {
            $error = "You are not registered as a resident.";
        } else {
            $check->bind_result($resident_id);
            $check->fetch();

            $residentAccountCheck = $conn->prepare("SELECT user_id FROM residents WHERE resident_id=? AND user_id IS NOT NULL");
            $residentAccountCheck->bind_param("i", $resident_id);
            $residentAccountCheck->execute();
            $residentAccountCheck->store_result();

            if ($residentAccountCheck->num_rows > 0) {
                $error = "You already have an account.";
            } else {
                $emailCheck = $conn->prepare("SELECT id FROM users WHERE email=?");
                $emailCheck->bind_param("s", $email);
                $emailCheck->execute();
                $emailCheck->store_result();

                if ($emailCheck->num_rows > 0) {
                    $error = "Email already exists.";
                } else {
                    $fileTmp = $_FILES['valid_id']['tmp_name'];
                    $fileName = $_FILES['valid_id']['name'];
                    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $allowed = ['jpg','jpeg','png','pdf'];

                    if(!in_array($ext, $allowed)) $error = "Invalid file type.";
                    elseif($_FILES['valid_id']['size'] > 2*1024*1024) $error = "File exceeds 2MB.";
                    else {
                        $newName = uniqid('id_', true).'.'.$ext;
                        $dir = 'uploads/ids/';
                        if(!is_dir($dir)) mkdir($dir,0777,true);
                        $path = $dir.$newName;
                        move_uploaded_file($fileTmp, $path);

                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $role = 'resident';
                        $username = strtolower($first_name.'.'.$last_name);

                        $stmt = $conn->prepare("INSERT INTO users (resident_id, username, email, password, role, valid_id, email_verified, is_approved) VALUES (?, ?, ?, ?, ?, ?, 0, 0)");
                        $stmt->bind_param("isssss", $resident_id, $username, $email, $hash, $role, $path);

                        if ($stmt->execute()) {
                            $user_id = $stmt->insert_id;
                            $conn->query("UPDATE residents SET user_id='$user_id' WHERE resident_id='$resident_id'");

                            $otp = rand(100000,999999);
                            $_SESSION['otp']=$otp;
                            $_SESSION['email']=$email;
                            $_SESSION['username']=$username;
                            $_SESSION['otp_expiry']=time()+(5*60);

                            $mail = new PHPMailer(true);
                            try {
                                $mail->isSMTP();
                                $mail->Host = 'smtp.gmail.com';
                                $mail->SMTPAuth = true;
                                $mail->Username = $systemEmail;
                                $mail->Password = $appPassword;
                                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                                $mail->Port = 587;
                                $mail->setFrom($systemEmail, $barangayName);
                                $mail->addAddress($email, $username);
                                $mail->Subject = 'Verification Code';
                                $mail->isHTML(true);
                                $mail->Body = "<h3>Your Verification Code:</h3><h2>$otp</h2><p>Valid for 5 minutes.</p>";
                                $mail->send();
                                $success = true;
                            } catch (Exception $e) {
                                $error = "Email failed: ".$mail->ErrorInfo;
                            }
                        } else $error = "Registration failed.";
                    }
                }
            }
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register - Barangay Palico 2</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="font-sans">

<div class="relative min-h-screen bg-cover bg-center" style="background-image: url('<?= htmlspecialchars($loginBgPath) ?>');">
 
  <div class="absolute inset-0 bg-gradient-to-b from-blue-500/40 via-blue-500/30 to-blue-500/50 flex items-center justify-center p-4">

<div class="bg-white bg-opacity-95 rounded-2xl shadow-lg w-full max-w-3xl p-6 md:p-6">
  <div class="text-center mb-4">
    <img src="<?= htmlspecialchars($systemLogoPath) ?>" alt="System Logo" class="w-28 mx-auto mb-2">
    <h2 class="text-lg font-bold">Create Your Account</h2>
    <h3 class="text-sm font-semibold mt-1">Join <?= htmlspecialchars($barangayName) ?></h3>
  </div>

  <?php if(!empty($error)): ?>
  <div class="bg-red-100 border-l-4 border-red-500 p-2 mb-3 text-sm">
    <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <form action="register.php" method="POST" id="signupForm" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-2">
    <div class="flex flex-col gap-1">
      <label class="font-semibold text-gray-700 text-sm">First Name</label>
      <input type="text" name="first_name" required value="<?= htmlspecialchars($first_name) ?>" class="border border-gray-300 rounded-md p-1.5 text-sm focus:outline-none focus:border-blue-500">
    </div>
    <div class="flex flex-col gap-1">
      <label class="font-semibold text-gray-700 text-sm">Last Name</label>
      <input type="text" name="last_name" required value="<?= htmlspecialchars($last_name) ?>" class="border border-gray-300 rounded-md p-1.5 text-sm focus:outline-none focus:border-blue-500">
    </div>
    <div class="flex flex-col gap-1 md:col-span-2">
      <label class="font-semibold text-gray-700 text-sm">Household Address</label>
      <input type="text" name="resident_address" required value="<?= htmlspecialchars($address) ?>" class="border border-gray-300 rounded-md p-1.5 text-sm focus:outline-none focus:border-blue-500">
    </div>
    <div class="flex flex-col gap-1">
      <label class="font-semibold text-gray-700 text-sm">Email</label>
      <input type="email" name="email" required value="<?= htmlspecialchars($email) ?>" class="border border-gray-300 rounded-md p-1.5 text-sm focus:outline-none focus:border-blue-500">
    </div>
    <div class="flex flex-col gap-1">
      <label class="font-semibold text-gray-700 text-sm">Phone Number</label>
      <input type="text" name="phone" id="phone" required value="<?= htmlspecialchars($phone) ?>" class="border border-gray-300 rounded-md p-1.5 text-sm focus:outline-none focus:border-blue-500">
    </div>
    <div class="flex flex-col gap-1">
      <label class="font-semibold text-gray-700 text-sm">Birthdate</label>
      <input type="date" name="birthdate" required value="<?= htmlspecialchars($birthdate) ?>" class="border border-gray-300 rounded-md p-1.5 text-sm focus:outline-none focus:border-blue-500">
    </div>
    <div class="flex flex-col gap-1">
      <label class="font-semibold text-gray-700 text-sm">Gender</label>
      <select name="gender" required class="border border-gray-300 rounded-md p-1.5 text-sm focus:outline-none focus:border-blue-500">
        <option value="">Select Gender</option>
        <option <?= ($gender==='Male') ? 'selected' : '' ?>>Male</option>
        <option <?= ($gender==='Female') ? 'selected' : '' ?>>Female</option>
      </select>
    </div>
    <div class="flex flex-col gap-1 md:col-span-2">
      <label class="font-semibold text-gray-700 text-sm">Valid ID (Upload)</label>
      <div class="flex items-center gap-2">
        <label for="valid_id" class="px-2 py-1.5 bg-blue-600 text-white rounded-md cursor-pointer font-bold text-xs">Choose File</label>
        <span id="file-name" class="text-gray-500 text-xs">No file chosen</span>
        <input type="file" name="valid_id" id="valid_id" accept="image/*,.pdf" required class="hidden">
      </div>
      <small class="text-gray-500 text-xs">Accepted: JPG, PNG, PDF (Max 2MB)</small>
    </div>
    <div class="flex flex-col gap-1">
      <label class="font-semibold text-gray-700 text-sm">Password</label>
      <input type="password" name="password" id="password" required value="<?= htmlspecialchars($password) ?>" class="border border-gray-300 rounded-md p-1.5 text-sm focus:outline-none focus:border-blue-500">
      <div id="password-requirements" class="bg-gray-100 p-1.5 rounded-md mt-1 hidden text-xs">
        <p>Password must contain:</p>
        <ul class="ml-3 mt-1">
          <li id="length" class="text-red-500">• At least 8 characters</li>
          <li id="uppercase" class="text-red-500">• An uppercase letter</li>
          <li id="lowercase" class="text-red-500">• A lowercase letter</li>
          <li id="number" class="text-red-500">• A number</li>
          <li id="special" class="text-red-500">• A special character</li>
        </ul>
      </div>
    </div>
    <div class="flex flex-col gap-1">
      <label class="font-semibold text-gray-700 text-sm">Confirm Password</label>
      <input type="password" name="confirm_password" id="confirm_password" required value="<?= htmlspecialchars($confirm_password) ?>" class="border border-gray-300 rounded-md p-1.5 text-sm focus:outline-none focus:border-blue-500">
    </div>
    <div class="md:col-span-2">
      <button type="submit" class="w-full bg-blue-600 text-white py-2.5 rounded-md font-bold text-sm">SIGN UP</button>
    </div>
    <div class="md:col-span-2 text-center mt-1 text-xs text-gray-600">
      Already have an account? <a href="login.php" class="text-blue-600 font-bold hover:underline">Login</a>
    </div>
  </form>
</div>

  </div>
</div>

<div id="loadingOverlay" class="fixed inset-0 bg-white bg-opacity-80 hidden flex justify-center items-center z-50">
  <div class="w-12 h-12 border-4 border-gray-200 border-t-blue-600 rounded-full animate-spin"></div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
const passwordInput=document.getElementById("password");
const confirmPasswordInput=document.getElementById("confirm_password");
const requirementBox=document.getElementById("password-requirements");
const length=document.getElementById("length");
const uppercase=document.getElementById("uppercase");
const lowercase=document.getElementById("lowercase");
const number=document.getElementById("number");
const special=document.getElementById("special");
const firstNameInput=document.querySelector('input[name="first_name"]');
const lastNameInput=document.querySelector('input[name="last_name"]');
const phoneInput=document.getElementById("phone");
const fileInput=document.getElementById("valid_id");
const fileNameSpan=document.getElementById("file-name");
const form=document.getElementById("signupForm");
const submitBtn=form.querySelector('button[type="submit"]');
confirmPasswordInput.disabled=true;
confirmPasswordInput.style.background="#eee";
confirmPasswordInput.placeholder="Complete password requirements first";
function checkPasswordRequirements(value){
let valid=true;
if(value.length>=8) length.className="text-green-600"; else {length.className="text-red-500"; valid=false;}
if(/[A-Z]/.test(value)) uppercase.className="text-green-600"; else {uppercase.className="text-red-500"; valid=false;}
if(/[a-z]/.test(value)) lowercase.className="text-green-600"; else {lowercase.className="text-red-500"; valid=false;}
if(/\d/.test(value)) number.className="text-green-600"; else {number.className="text-red-500"; valid=false;}
if(/[^A-Za-z0-9]/.test(value)) special.className="text-green-600"; else {special.className="text-red-500"; valid=false;}
return valid;
}
passwordInput.addEventListener("focus",()=>requirementBox.classList.remove("hidden"));
passwordInput.addEventListener("blur",()=>{if(!checkPasswordRequirements(passwordInput.value)){requirementBox.classList.remove("hidden");}});
passwordInput.addEventListener("input",()=>{
const allValid=checkPasswordRequirements(passwordInput.value);
if(allValid){
confirmPasswordInput.disabled=false;
confirmPasswordInput.style.background="#fff";
confirmPasswordInput.placeholder="Confirm Password";
requirementBox.classList.add("hidden");
}else{
confirmPasswordInput.disabled=true;
confirmPasswordInput.value="";
confirmPasswordInput.style.background="#eee";
confirmPasswordInput.placeholder="Complete password requirements first";
requirementBox.classList.remove("hidden");
}});
[firstNameInput,lastNameInput].forEach(input=>{input.addEventListener("input",()=>{input.value=input.value.replace(/[0-9]/g,'');});});
phoneInput.addEventListener("input",()=>{phoneInput.value=phoneInput.value.replace(/\D/g,'').slice(0,11);});
fileInput.addEventListener("change",()=>{if(fileInput.files.length>0){fileNameSpan.textContent=fileInput.files[0].name;}else{fileNameSpan.textContent="No file chosen";}});
const requiredFields=form.querySelectorAll("input[required], select[required]");
function checkAllFields(){let allFilled=true;requiredFields.forEach(field=>{if(field.type==="file"){if(!field.files||field.files.length===0) allFilled=false;}else{if(!field.value.trim()) allFilled=false;}});submitBtn.disabled=!allFilled;}
requiredFields.forEach(field=>{field.addEventListener("input",checkAllFields);field.addEventListener("change",checkAllFields);});
checkAllFields();
form.addEventListener("submit",()=>{document.getElementById("loadingOverlay").classList.remove("hidden");});
});
</script>

<?php if($success): ?>
<script>window.location.href="verify_email.php";</script>
<?php endif; ?>

</body>
</html>
