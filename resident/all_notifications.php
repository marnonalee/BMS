<?php 
session_start();
include 'resident_header.php';
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "resident") {
    header("Location: ../index.php");
    exit();
}

include '../db.php';

$user_id = (int)$_SESSION["user_id"];
$username = $_SESSION["username"];

// Get resident info
$residentQuery = $conn->prepare("SELECT resident_id, profile_photo FROM residents WHERE user_id = ? LIMIT 1");
$residentQuery->bind_param("i", $user_id);
$residentQuery->execute();
$residentResult = $residentQuery->get_result();
$resident = $residentResult->fetch_assoc();
$residentQuery->close();

$resident_id = $resident['resident_id'] ?? 0;

// Fetch all notifications
$notifQuery = $conn->prepare("SELECT * FROM notifications WHERE resident_id = ? ORDER BY date_created DESC");
$notifQuery->bind_param("i", $resident_id);
$notifQuery->execute();
$notifResult = $notifQuery->get_result();
$notifications = $notifResult->fetch_all(MYSQLI_ASSOC);
$notifQuery->close();

// Optional: mark all as read
if ($resident_id > 0) {
    $conn->query("UPDATE notifications SET is_read = 1 WHERE resident_id = $resident_id");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>All Notifications | Resident Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body { background-color: #f3f4f6; }
.notification-item { transition: background-color 0.2s; }
.notification-item:hover { background-color: #e0e7ff; }
</style>
</head>
<body class="font-sans">


<main class="container mx-auto mt-8 mb-16 max-w-4xl">
    <div class="bg-white rounded-3xl shadow-xl p-6">
        <h2 class="text-2xl font-bold mb-6">All Notifications</h2>

        <?php if(count($notifications) > 0): ?>
            <ul class="space-y-4 max-h-[70vh] overflow-y-auto">
                <?php foreach($notifications as $notif): ?>
                    <li class="notification-item p-4 rounded-xl border-l-4 <?= $notif['is_read'] ? 'border-gray-300 bg-white' : 'border-indigo-500 bg-indigo-50' ?>">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-gray-500 text-xs"><?= date("M d, Y H:i", strtotime($notif['date_created'])) ?></span>
                            <?php if(!$notif['is_read']): ?>
                                <span class="text-indigo-600 text-xs font-semibold">Unread</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-gray-700"><?= htmlspecialchars($notif['message']) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-gray-500 text-center py-10">No notifications available</p>
        <?php endif; ?>
    </div>
</main>

</body>
</html>
