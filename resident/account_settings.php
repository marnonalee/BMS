<?php
include 'resident_header.php';
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "resident") {
    header("Location: ../index.php");
    exit();
}

include '../db.php';
$user_id = $_SESSION["user_id"];

// Get user data + approval status
$userQuery = $conn->prepare("SELECT email, password, profile_pic, is_approved FROM users WHERE id = ?");
$userQuery->bind_param("i", $user_id);
$userQuery->execute();
$userData = $userQuery->get_result()->fetch_assoc();
$userQuery->close();

$email = $userData['email'] ?? '';
$profile_pic = $userData['profile_pic'] ?? 'uploads/default.png';
$is_approved = $userData['is_approved'] ?? 0;

$successMsg = '';
$errorMsg = '';

if ($is_approved != 1) {
    $errorMsg = "Your account is not verified yet. You cannot edit your account settings.";
}

// Only allow POST if approved
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_approved == 1) {
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
<main class="container mx-auto mt-8 max-w-4xl">

    <div class="mb-6 flex items-center space-x-2 text-gray-600 text-sm">
        <a href="dashboard.php" class="hover:underline">Dashboard</a>
        <span class="text-gray-400"> | </span>
        <span class="font-semibold text-gray-800">Account Settings</span>
    </div>

    <?php if ($successMsg): ?>
        <div class="bg-green-100 text-green-800 p-4 rounded-lg mb-6 shadow-sm"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="bg-red-100 text-red-800 p-4 rounded-lg mb-6 shadow-sm"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow-lg p-6">
        <form method="POST" id="accountForm" enctype="multipart/form-data" class="space-y-5">
            <div>
                <label class="font-semibold block mb-1">Email Address</label>
                <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required class="w-full border-b-2 border-gray-300 py-2 px-1 focus:outline-none focus:border-blue-500 transition" <?= $is_approved != 1 ? 'disabled' : '' ?>>
            </div>

            <div>
                <label class="font-semibold block mb-1">Current Password</label>
                <input type="password" name="current_password" placeholder="Enter current password" required class="w-full border-b-2 border-gray-300 py-2 px-1 focus:outline-none focus:border-blue-500 transition" <?= $is_approved != 1 ? 'disabled' : '' ?>>
            </div>

            <div>
                <label class="font-semibold block mb-1">New Password</label>
                <input type="password" name="new_password" placeholder="Enter new password (optional)" class="w-full border-b-2 border-gray-300 py-2 px-1 focus:outline-none focus:border-blue-500 transition" <?= $is_approved != 1 ? 'disabled' : '' ?>>
            </div>

            <div>
                <label class="font-semibold block mb-1">Confirm New Password</label>
                <input type="password" name="confirm_password" placeholder="Confirm new password" class="w-full border-b-2 border-gray-300 py-2 px-1 focus:outline-none focus:border-blue-500 transition" <?= $is_approved != 1 ? 'disabled' : '' ?>>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-green-500 text-white px-6 py-2 rounded-lg hover:bg-green-600 transition font-semibold" <?= $is_approved != 1 ? 'disabled class="bg-gray-400 cursor-not-allowed"' : '' ?>>
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</main>
</body>
</html>
