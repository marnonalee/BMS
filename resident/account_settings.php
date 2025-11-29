<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "resident") {
    header("Location: ../index.php");
    exit();
}

include '../db.php';
$user_id = $_SESSION["user_id"];

$userQuery = $conn->prepare("SELECT email, password, profile_pic FROM users WHERE id = ?");
$userQuery->bind_param("i", $user_id);
$userQuery->execute();
$userData = $userQuery->get_result()->fetch_assoc();
$userQuery->close();

$email = $userData['email'] ?? '';
$profile_pic = $userData['profile_pic'] ?? 'uploads/default.png';
$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_email = $_POST['email'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Handle profile pic upload
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
        $allowed = ['jpg','jpeg','png'];
        $fileName = $_FILES['profile_pic']['name'];
        $fileTmp = $_FILES['profile_pic']['tmp_name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (in_array($fileExt, $allowed)) {
            $newFileName = 'profile_'.$user_id.'_'.time().'.'.$fileExt;
            $uploadDir = '../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            if (move_uploaded_file($fileTmp, $uploadDir.$newFileName)) {
                $profile_pic = 'uploads/'.$newFileName;
            } else {
                $errorMsg .= "Failed to upload profile picture. ";
            }
        } else {
            $errorMsg .= "Invalid profile picture type. Only JPG, PNG allowed. ";
        }
    }

    if ($current_password && password_verify($current_password, $userData['password'])) {
        if ($new_password && $new_password !== $confirm_password) {
            $errorMsg .= "New password and confirmation do not match.";
        } else {
            $hashed_password = $new_password ? password_hash($new_password, PASSWORD_DEFAULT) : $userData['password'];
            $updateQuery = $conn->prepare("UPDATE users SET email=?, password=?, profile_pic=? WHERE id=?");
            $updateQuery->bind_param("sssi", $new_email, $hashed_password, $profile_pic, $user_id);
            if ($updateQuery->execute()) {
                $successMsg = "Account settings updated successfully.";
                $email = $new_email;
            } else {
                $errorMsg .= "Failed to update account settings.";
            }
            $updateQuery->close();
        }
    } else {
        $errorMsg .= "Current password is incorrect.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Account Settings | Barangay System</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body class="bg-gray-100 min-h-screen font-sans">

<!-- Header -->
<header class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-md">
    <div class="container mx-auto flex justify-between items-center p-4">
        <h1 class="text-2xl font-bold">Account Settings</h1>
        <a href="dashboard.php" class="flex items-center gap-2 bg-white text-blue-600 px-4 py-2 rounded-lg hover:bg-gray-100 transition">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
</header>

<!-- Main Content -->
<main class="container mx-auto mt-8 max-w-4xl">
    <?php if ($successMsg): ?>
        <div class="bg-green-100 text-green-800 p-4 rounded-lg mb-6 shadow-sm"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="bg-red-100 text-red-800 p-4 rounded-lg mb-6 shadow-sm"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow-lg p-6 flex flex-col md:flex-row gap-8">
        <!-- Profile Picture -->
        <div class="flex flex-col items-center md:w-1/3">
            <img src="<?= htmlspecialchars($profile_pic) ?>" alt="Profile Picture" class="w-32 h-32 rounded-full object-cover border-2 border-gray-300 mb-4 shadow-sm">
            <label class="cursor-pointer bg-blue-500 text-white px-5 py-2 rounded-lg hover:bg-blue-600 transition flex items-center gap-2">
                <i class="fas fa-upload"></i> Change Picture
                <input type="file" name="profile_pic" form="accountForm" class="hidden">
            </label>
        </div>

        <!-- Account Form -->
        <div class="md:w-2/3">
            <form method="POST" id="accountForm" enctype="multipart/form-data" class="space-y-5">
                <div>
                    <label class="font-semibold block mb-1">Email Address</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required class="w-full border-b-2 border-gray-300 py-2 px-1 focus:outline-none focus:border-blue-500 transition">
                </div>

                <div>
                    <label class="font-semibold block mb-1">Current Password</label>
                    <input type="password" name="current_password" placeholder="Enter current password" required class="w-full border-b-2 border-gray-300 py-2 px-1 focus:outline-none focus:border-blue-500 transition">
                </div>

                <div>
                    <label class="font-semibold block mb-1">New Password</label>
                    <input type="password" name="new_password" placeholder="Enter new password (optional)" class="w-full border-b-2 border-gray-300 py-2 px-1 focus:outline-none focus:border-blue-500 transition">
                </div>

                <div>
                    <label class="font-semibold block mb-1">Confirm New Password</label>
                    <input type="password" name="confirm_password" placeholder="Confirm new password" class="w-full border-b-2 border-gray-300 py-2 px-1 focus:outline-none focus:border-blue-500 transition">
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="bg-green-500 text-white px-6 py-2 rounded-lg hover:bg-green-600 transition font-semibold">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

</body>
</html>
