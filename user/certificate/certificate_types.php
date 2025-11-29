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


$successMsg = '';
$errorMsg = '';

if(isset($_POST['add_certificate'])){
    $template_for = $_POST['template_for'];
    $template_name = $_POST['template_name'];
    $file_path = null;

    if(isset($_FILES['template_file']) && $_FILES['template_file']['error'] === 0){
        $uploadDir = '/templates/';
        if(!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $fileName = basename($_FILES['template_file']['name']);
        $targetFile = $uploadDir . $fileName;

        $checkStmt = $conn->prepare("SELECT id FROM certificate_templates WHERE file_path = ?");
        $checkPath = 'templates/'.$fileName;
        $checkStmt->bind_param("s", $checkPath);
        $checkStmt->execute();
        $checkStmt->store_result();

        if($checkStmt->num_rows > 0){
            $errorMsg = "File with the same name already exists!";
        } else {
            if(move_uploaded_file($_FILES['template_file']['tmp_name'], $targetFile)){
                $file_path = 'templates/'.$fileName;
                $stmt = $conn->prepare("INSERT INTO certificate_templates (template_for, template_name, file_path) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $template_for, $template_name, $file_path);
                $stmt->execute();
                $stmt->close();
                $successMsg = "Certificate type added successfully!";
            } else {
                $errorMsg = "Failed to upload file.";
            }
        }
        $checkStmt->close();
    } else {
        $errorMsg = "Please select a PDF file.";
    }
}

if(isset($_POST['update_certificate'])){
    $template_for = $_POST['template_for'];
    $template_name = $_POST['template_name'];
    $certificate_id = $_POST['certificate_id'];

    $oldFilePath = $conn->query("SELECT file_path FROM certificate_templates WHERE id = $certificate_id")->fetch_assoc()['file_path'];
    $file_path = $oldFilePath;

    if(isset($_FILES['template_file']) && $_FILES['template_file']['error'] === 0){
        $uploadDir = '../templates/';
        if(!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $fileName = basename($_FILES['template_file']['name']);
        $targetFile = $uploadDir . $fileName;

        $checkStmt = $conn->prepare("SELECT id FROM certificate_templates WHERE file_path = ? AND id != ?");
        $checkPath = 'templates/'.$fileName;
        $checkStmt->bind_param("si", $checkPath, $certificate_id);
        $checkStmt->execute();
        $checkStmt->store_result();

        if($checkStmt->num_rows > 0){
            $errorMsg = "File with the same name already exists!";
        } else {
            if(move_uploaded_file($_FILES['template_file']['tmp_name'], $targetFile)){
                $file_path = 'templates/'.$fileName;
                if($oldFilePath && file_exists('../'.$oldFilePath) && $oldFilePath !== $file_path){
                    unlink('../'.$oldFilePath);
                }
            }
            $stmt = $conn->prepare("UPDATE certificate_templates SET template_for=?, template_name=?, file_path=? WHERE id=?");
            $stmt->bind_param("sssi", $template_for, $template_name, $file_path, $certificate_id);
            $stmt->execute();
            $stmt->close();
            $successMsg = "Certificate type updated successfully!";
        }
        $checkStmt->close();
    } else {
        $stmt = $conn->prepare("UPDATE certificate_templates SET template_for=?, template_name=? WHERE id=?");
        $stmt->bind_param("ssi", $template_for, $template_name, $certificate_id);
        $stmt->execute();
        $stmt->close();
        $successMsg = "Certificate type updated successfully!";
    }
}

$perPage = isset($_GET['perPage']) ? (int)$_GET['perPage'] : 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page-1)*$perPage;

$totalCertificates = $conn->query("SELECT COUNT(*) AS cnt FROM certificate_templates")->fetch_assoc()['cnt'];
$totalPages = ceil($totalCertificates/$perPage);

$certificatesQuery = $conn->query("SELECT * FROM certificate_templates ORDER BY created_at DESC LIMIT $start,$perPage");
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Certificate Types</title>
<link rel="stylesheet" href="cer.css">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body class=" font-sans" data-success="<?= htmlspecialchars($successMsg) ?>">
<div class="flex h-screen">
  <style>
    #sidebar nav::-webkit-scrollbar {
        width: 6px;
    }
    #sidebar nav::-webkit-scrollbar-track {
        background: transparent;
    }
    #sidebar nav::-webkit-scrollbar-thumb {
        background-color: #15803d;
        border-radius: 10px;
    }
    #sidebar nav::-webkit-scrollbar-thumb:hover {
        background-color: #166534;
    }
  </style>

<aside id="sidebar" class="w-64 bg-green-500 text-white flex flex-col shadow-lg transition-all duration-300 h-screen">
  <div class="flex items-center justify-between p-6 border-b border-green-600">
    <a href="../manage_profile.php" class="flex items-center space-x-2">
      <span class="material-icons text-3xl">account_circle</span>
      <span class="font-bold text-xl sidebar-text"><?= htmlspecialchars($user['username'] ?? 'User') ?></span>
    </a>
    <button id="toggleSidebar" class="material-icons cursor-pointer">chevron_left</button>
  </div>

  <nav class="flex-1 overflow-y-auto px-2 py-6 space-y-2">
    <a href="../dashboard.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 transition-colors">
      <span class="material-icons">dashboard</span><span class="ml-3 sidebar-text">Dashboard</span>
    </a>
    <a href="../officials/barangay_officials.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 transition-colors">
      <span class="material-icons mr-3">people</span><span class="sidebar-text">Barangay Officials</span>
    </a>
    <a href="../residents/resident.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 transition-colors">
      <span class="material-icons mr-3">people</span><span class="sidebar-text">Residents</span>
    </a>
    <a href="../households/household.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 transition-colors">
      <span class="material-icons mr-3">home</span><span class="sidebar-text">Household</span>
    </a>

    <?php if($role === 'admin'): ?>
      <div class="mt-4">
        <span class="px-4 py-2 text-gray-200 uppercase text-xs tracking-wide sidebar-text">Community</span>
        <a href="../announcements.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 mt-1 transition-colors">
          <span class="material-icons mr-3">campaign</span><span class="sidebar-text">Announcements</span>
        </a>
        <a href="../news_updates.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 mt-1 transition-colors">
          <span class="material-icons mr-3">article</span><span class="sidebar-text">News & Updates</span>
        </a>
      </div>
    <?php endif; ?>

    <div class="mt-4">
      <span class="px-4 py-2 text-gray-200 uppercase text-xs tracking-wide sidebar-text">Certificate Management</span>
      <?php if($role === 'admin'): ?>
        <a href="../certificate/certificate_types.php" class="flex items-center px-4 py-3 rounded bg-green-600 mt-1 transition-colors">
          <span class="material-icons mr-3">badge</span><span class="sidebar-text">Certificate Types</span>
        </a>
      <?php endif; ?>
      <a href="../certificate/certificate_requests.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 mt-1 transition-colors">
        <span class="material-icons mr-3">assignment</span><span class="sidebar-text">Certificate Requests</span>
      </a>
      <a href="../certificate/walkin_certificates.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 mt-1 transition-colors">
        <span class="material-icons mr-3">person_add</span><span class="sidebar-text">Walk-in Requests</span>
      </a>
    </div>

    <div class="mt-4">
      <span class="px-4 py-2 text-gray-200 uppercase text-xs tracking-wide sidebar-text">Blotter</span>
      <a href="../blotter/blotter.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 mt-1 transition-colors">
        <span class="material-icons mr-3">gavel</span><span class="sidebar-text">Blotter Records</span>
      </a>
    </div>

    <a href="../reports/report.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 transition-colors">
      <span class="material-icons mr-3">bar_chart</span><span class="sidebar-text">Reports</span>
    </a>

    <div class="mt-4">
      <span class="px-4 py-2 text-gray-200 uppercase text-xs tracking-wide sidebar-text">ID Management</span>
      <a href="#" class="flex items-center px-4 py-3 rounded hover:bg-green-500 mt-1 transition-colors">
        <span class="material-icons mr-3">credit_card</span><span class="sidebar-text">ID Requests</span>
      </a>
      <a href="#" class="flex items-center px-4 py-3 rounded hover:bg-green-500 mt-1 transition-colors">
        <span class="material-icons mr-3">elderly</span><span class="sidebar-text">Senior / PWD / Solo Parent</span>
      </a>
    </div>

    <?php if($role === 'admin'): ?>
      <div class="mt-4">
        <span class="px-4 py-2 text-gray-200 uppercase text-xs tracking-wide sidebar-text">User Management</span>
        <a href="../user_manage/user_management.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 mt-1 transition-colors">
          <span class="material-icons mr-3">admin_panel_settings</span><span class="sidebar-text">System User</span>
        </a>
        <a href="../user_manage/log_activity.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 mt-1 transition-colors">
          <span class="material-icons mr-3">history</span><span class="sidebar-text">Log Activity</span>
        </a>
        <a href="../user_manage/settings.php" class="flex items-center px-4 py-3 rounded hover:bg-green-500 mt-1 transition-colors">
        <span class="material-icons mr-3">settings</span><span class="sidebar-text">Settings</span>
        </a>

      </div>
    <?php endif; ?>

    <a href="../../logout.php" class="flex items-center px-4 py-3 rounded hover:bg-red-600 transition-colors mt-1">
      <span class="material-icons mr-3">logout</span><span class="sidebar-text">Logout</span>
    </a>
  </nav>
</aside>


  <div class="flex-1 flex flex-col overflow-hidden">
    <header class="flex items-center justify-between bg-white shadow p-4 flex-shrink-0">
      <h2 class="text-xl font-semibold text-gray-700">Certificate Types</h2>
      <div class="flex items-center space-x-4">
        <input type="text" id="searchInput" placeholder="Search certificate types..." class="px-4 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-green-400">
        <button id="addCertificateBtn" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">Add Certificate</button>
      </div>
    </header>

    <main class="flex-1 overflow-y-auto p-6">
      <div class="bg-white p-5 rounded-xl shadow overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Template For</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Template Name</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while($cert = $certificatesQuery->fetch_assoc()): ?>
            <tr class="hover:bg-gray-100 cursor-pointer" data-certificate='<?= json_encode($cert) ?>' data-file="<?= htmlspecialchars(basename($cert['file_path'])) ?>">
              <td class="px-4 py-2"><?= htmlspecialchars($cert['template_for']) ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($cert['template_name']) ?></td>
              <td class="px-4 py-2 flex gap-2">
                <button class="editBtn text-blue-500 flex items-center gap-1">
                  <span class="material-icons">edit</span>Edit
                </button>
                <button class="deleteBtn text-red-500 flex items-center gap-1" data-id="<?= $cert['id'] ?>" data-name="<?= htmlspecialchars($cert['template_name']) ?>">
                  <span class="material-icons">delete</span>Delete
                </button>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>

  <style>
    #sidebar nav::-webkit-scrollbar {
      width: 6px;
    }
    #sidebar nav::-webkit-scrollbar-track {
      background: transparent;
    }
    #sidebar nav::-webkit-scrollbar-thumb {
      background-color: #15803d;
      border-radius: 10px;
    }
    #sidebar nav::-webkit-scrollbar-thumb:hover {
      background-color: #166534;
    }
  </style>
</div>

<!-- Modal -->
<div id="certificateModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-xl w-11/12 md:w-1/2 p-6 relative">
        <button id="closeModal" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 material-icons">close</button>
        <h2 class="text-xl font-semibold mb-6 text-center" id="modalTitle">Add Certificate Type</h2>

        <form method="POST" id="certificateForm" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="certificate_id" id="certificate_id">

            <div class="flex flex-col">
                <label class="mb-1 font-medium">Template For</label>
                <input type="text" name="template_for" id="template_for" class="border px-3 py-2 rounded w-full" required>
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-medium">Template Name</label>
                <input type="text" name="template_name" id="template_name" class="border px-3 py-2 rounded w-full" required>
            </div>

           <div class="flex flex-col">
                <label class="mb-1 font-medium">Upload Template (PDF)</label>
                <input type="file" name="template_file" id="template_file" accept="application/pdf" class="border px-3 py-2 rounded w-full">
                <div id="pdfPreview" class="mt-2 text-sm"></div>
                <p id="pdfError" class="text-red-500 text-sm mt-1"></p>
            </div>

            <div class="flex justify-end">
                <button type="submit" id="submitBtn" class="bg-green-500 text-white px-5 py-2 rounded hover:bg-green-600">Save</button>
            </div>
        </form>
    </div>
</div>

<div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-80 text-center relative">
        <button id="closeSuccessModal" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 material-icons">close</button>
        <span class="material-icons text-green-500 text-4xl mb-2">check_circle</span>
        <p id="successMessage" class="text-gray-700 font-medium"></p>
        <button id="okSuccessBtn" class="mt-4 bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">OK</button>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="certificate_te.js"></script>



</body>
</html>
