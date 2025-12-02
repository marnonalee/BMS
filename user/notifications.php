<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

include '../db.php';
$user_id = $_SESSION["user_id"];

$stmtUser = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc();

$rolesAllowed = "'resident','staff','system'";
$notificationsQuery = $conn->prepare("
    SELECT * 
    FROM notifications 
    WHERE from_role IN ($rolesAllowed)
    ORDER BY date_created DESC
");
$notificationsQuery->execute();
$notifications = $notificationsQuery->get_result()->fetch_all(MYSQLI_ASSOC);

$markRead = $conn->prepare("UPDATE notifications SET is_read=1, read_at=NOW() WHERE is_read=0 AND resident_id=?");
$markRead->bind_param("i", $user_id);
$markRead->execute();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Notifications</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body class="bg-gray-50 font-sans">

<div class="max-w-4xl mx-auto py-10">
    <h1 class="text-2xl font-bold mb-6">Notifications</h1>

    <div class="mb-4">
        <a href="dashboard.php" class="text-blue-600 hover:underline flex items-center gap-1">
            <span class="material-icons">arrow_back</span> Back to Dashboard
        </a>
    </div>

    <?php if(empty($notifications)): ?>
        <div class="p-4 bg-white rounded shadow text-center text-gray-500">No notifications</div>
    <?php else: ?>
        <div class="bg-white rounded shadow overflow-hidden divide-y divide-gray-200">
            <?php foreach($notifications as $note): ?>
                <a href="<?php
                    switch($note['type']){
                        case 'blotter':
                            echo "blotter/blotter.php?id={$note['resident_id']}&note={$note['notification_id']}";
                            break;
                        case 'certificate':
                            echo "certificate/certificate_requests.php?id={$note['resident_id']}&note={$note['notification_id']}";
                            break;
                        default:
                            echo "#";
                    }
                ?>" 
                class="block px-4 py-3 hover:bg-gray-100 transition <?= $note['is_read'] == 0 ? 'font-semibold bg-gray-50' : '' ?>">
                    <p class="text-gray-700"><?= htmlspecialchars($note['message']) ?></p>
                    <span class="text-gray-400 text-xs"><?= date('M d, Y H:i', strtotime($note['date_created'])) ?></span>
                    <span class="text-gray-500 text-xs italic">From: <?= htmlspecialchars($note['from_role']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
