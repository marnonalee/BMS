<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../../login.php");
    exit();
}

include '../db.php';
$user_id = $_SESSION["user_id"];
$userQuery = $conn->query("SELECT * FROM users WHERE id = '$user_id'");
$user = $userQuery->fetch_assoc();
$role = $user['role'];

// --- Accept ID request ---
$receiptLink = null;
if(isset($_POST['accept_id_request'])){
    $requestId = intval($_POST['request_id']);

    $conn->query("UPDATE barangay_id_requests SET status='Approved', date_reviewed=NOW(), reviewed_by='$user_id' WHERE id='$requestId'");

    // Fetch details to generate receipt
   $req = $conn->query("
    SELECT r.*, res.first_name, res.last_name, res.resident_address, r.date_requested
    FROM barangay_id_requests r
    LEFT JOIN residents res ON r.resident_id = res.resident_id
    WHERE r.id='$requestId'
")->fetch_assoc();

    if(!is_dir("../receipts")){
        mkdir("../receipts", 0755, true);
    }

    $timestamp = time();
    $receiptFile = "../receipts/receipt_".$req['id_number']."_".$timestamp.".html";
    $receiptHtml = "
    <html>
    <head>
        <title>Barangay ID Request Receipt</title>
        <style>
            body { font-family: sans-serif; padding: 20px; }
            h2 { color: #2f855a; }
            p { font-size: 14px; margin: 5px 0; }
            .badge { display:inline-block; padding: 5px 10px; background-color:#48bb78; color:#fff; border-radius:4px; font-weight:bold; }
        </style>
    </head>
    <body>
        <h2>Barangay ID Request Receipt</h2>
        <p><strong>Name:</strong> ".htmlspecialchars($req['first_name'].' '.$req['last_name'])."</p>
        <p><strong>ID Number:</strong> ".htmlspecialchars($req['id_number'])."</p>
        <p><strong>Address:</strong> ".htmlspecialchars($req['resident_address'])."</p>
        <p><strong>Status:</strong> <span class='badge'>Approved</span></p>
        <p><strong>Date Requested:</strong> ".date('M d, Y', strtotime($req['date_requested']))."</p>
        <p><strong>Date Approved:</strong> ".date('M d, Y H:i')."</p>
        <p>Thank you. This receipt confirms the ID request has been approved.</p>
    </body>
    </html>
    ";

    file_put_contents($receiptFile, $receiptHtml);
    $receiptLink = $receiptFile;
}

// --- Fetch ID requests ---
$idRequestsQuery = $conn->query("
    SELECT r.*, 
           res.first_name, res.last_name, res.profile_image, res.resident_address, res.birthdate
    FROM barangay_id_requests r
    LEFT JOIN residents res ON r.resident_id = res.resident_id
    ORDER BY r.date_requested DESC
");

$idRequests = [];
while ($row = $idRequestsQuery->fetch_assoc()) {
    $idRequests[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>ID Request Cards</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<style>
.id-card { width: 340px; height: 200px; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.3); background: linear-gradient(to right, #f9fafb, #e6f4ea); display:flex; border: 2px solid #2f855a; font-family: sans-serif; position:relative; }
.id-card-left { width: 110px; background: #c6f6d5; display:flex; align-items:center; justify-content:center; }
.id-card-left img { width: 90px; height: 90px; object-fit:cover; border-radius:6px; border:1px solid #2f855a; }
.id-card-right { flex:1; padding:10px 12px; display:flex; flex-direction:column; justify-content:space-between; position:relative; }
.id-header { display:flex; justify-content:space-between; align-items:center; }
.id-header img { width:40px; height:40px; }
.id-info h3 { font-size:16px; font-weight:bold; color:#1a202c; }
.id-info p { font-size:12px; margin:2px 0; color:#2d3748; }
.id-footer { display:flex; justify-content:space-between; align-items:center; }
.status-badge { padding:2px 6px; border-radius:4px; color:#fff; font-size:12px; font-weight:bold; }
.status-Pending { background-color:#ecc94b; }
.status-Approved { background-color:#48bb78; }
.status-Rejected { background-color:#f56565; }
.status-Ready\ for\ Pickup { background-color:#4299e1; }
.accept-btn { background-color:#4299e1; color:white; padding:5px 8px; font-size:12px; border-radius:4px; cursor:pointer; }
</style>
</head>
<body class="bg-gray-100 font-sans">

<div class="flex h-screen">
<aside id="sidebar" class="w-64 bg-green-400 text-white flex flex-col shadow-lg transition-all duration-300">
  <div class="flex items-center justify-between p-6 border-b border-green-500 flex-shrink-0">
    <span class="font-bold text-xl sidebar-text">
        <?= htmlspecialchars($user['username'] ?? 'User') ?>
    </span>
    <button id="toggleSidebar" class="material-icons cursor-pointer">chevron_left</button>
  </div>
  <nav class="flex-1 px-2 py-6 space-y-2">
    <a href="../dashboard.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 transition-colors">
      <span class="material-icons">dashboard</span>
      <span class="ml-3 sidebar-text">Dashboard</span>
    </a>
    <a href="residents/resident.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 transition-colors">
        <span class="material-icons">people</span>
        <span class="ml-3 sidebar-text">Residents</span>
    </a>
    <div class="mt-2">
        <span class="px-4 py-2 text-gray-200 uppercase text-xs tracking-wide sidebar-text">Certificate Management</span>
        <a href="../certificate/certificate_types.php"  class="flex items-center px-4 py-3 rounded hover:bg-green-500 transition-colors">
            <span class="material-icons mr-3">badge</span>
            <span class="sidebar-text">Certificate Types</span>
        </a>
        <a href="../certificate/certificate_requests.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 mt-1 transition-colors">
            <span class="material-icons mr-3">assignment</span>
            <span class="sidebar-text">Certificate Requests</span>
        </a>
        <a href="../certificate/walkin_certificates.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 mt-1 transition-colors">
            <span class="material-icons mr-3">person_add</span>
            <span class="sidebar-text">Walk-in Requests</span>
        </a>
    </div>
    <div class="mt-6">
        <span class="px-4 py-2 text-gray-200 uppercase text-xs tracking-wide sidebar-text">Blotter</span>
        <a href="../blotter/blotter.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 mt-1 transition-colors">
            <span class="material-icons mr-3">gavel</span>
            <span class="sidebar-text">Blotter Records</span>
        </a>
    </div>
        <div class="mt-2">
            <span class="px-4 py-2 text-gray-200 uppercase text-xs tracking-wide">ID Management</span>
            <a href="id_requests.php" class="flex items-center px-4 py-3 rounded bg-green-600 mt-1 transition-colors">
                <span class="material-icons mr-3">credit_card</span> ID Requests
            </a>
        </div>
    </nav>
</aside>

<div class="flex-1 flex flex-col">
<header class="flex items-center justify-between bg-white shadow p-4">
<h2 class="text-xl font-semibold text-gray-700">ID Requests</h2>
</header>

<main class="p-6 flex-1 overflow-y-auto">
<?php if($receiptLink): ?>
    <div class="mb-4 p-4 bg-green-100 text-green-700 rounded">
        Receipt generated: <a href="<?= $receiptLink ?>" target="_blank" class="underline">Download Receipt</a>
    </div>
<?php endif; ?>

<div class="flex flex-wrap gap-6">
<?php if(count($idRequests) > 0): ?>
<?php foreach($idRequests as $req): ?>
    <?php $profileImg = $req['profile_image'] ?: 'default_profile.png'; ?>
    <div class="id-card">
        <div class="id-card-left">
            <img src="../uploads/residents/<?= $profileImg ?>" alt="Resident Photo">
        </div>
        <div class="id-card-right">
            <div class="id-header">
                <h4 class="text-sm font-bold text-green-800">Barangay Example</h4>
                <img src="../uploads/logo.png" alt="Barangay Logo">
            </div>
            <div class="id-info mt-2">
                <h3><?= htmlspecialchars($req['first_name'] . ' ' . $req['last_name']) ?></h3>
                <p><span class="font-semibold">ID No:</span> <?= htmlspecialchars($req['id_number']) ?></p>
                <p><span class="font-semibold">Address:</span> <?= htmlspecialchars($req['resident_address']) ?></p>
                <p><span class="font-semibold">Birthdate:</span> <?= date('M d, Y', strtotime($req['birthdate'])) ?></p>
            </div>
            <div class="id-footer mt-2">
                <span class="status-badge status-<?= str_replace(' ', '\ ', $req['status']) ?>"><?= htmlspecialchars($req['status']) ?></span>
                <div class="flex gap-2">
                    <?php if($req['status'] === 'Pending'): ?>
                    <form method="POST" class="inline">
                        <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                        <button type="submit" name="accept_id_request" class="accept-btn">Approve</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?php else: ?>
<p class="text-gray-500 text-center w-full">No ID requests found.</p>
<?php endif; ?>
</div>
</main>
</div>
</div>

<script>
const toggleSidebar = document.getElementById('toggleSidebar');
toggleSidebar.onclick = () => {
    document.getElementById('sidebar').classList.toggle('sidebar-collapsed');
};
</script>
</body>
</html>
