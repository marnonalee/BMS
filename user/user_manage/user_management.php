<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../../login.php");
    exit();
}
include '../db.php';
include 'send_user_email.php';

$user_id = $_SESSION["user_id"];
$userQuery = $conn->query("SELECT * FROM users WHERE id = '$user_id'");
$user = $userQuery->fetch_assoc();
$role = $user['role'];

$successMsg = '';
$errorMsg = '';

if(isset($_POST['add_user'])){
    $username = $_POST['username'];
    $email = $_POST['email'];
    $role_new = $_POST['role'];
    $resident_id = !empty($_POST['resident_id']) ? $_POST['resident_id'] : null;

    $prefix = "PALICOIV@";
    $lastUser = $conn->query("SELECT password FROM users WHERE password LIKE '$prefix%' ORDER BY id DESC LIMIT 1")->fetch_assoc();
    if($lastUser){
        preg_match('/(\d+)$/', $lastUser['password'], $matches);
        $nextNumber = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
    } else {
        $nextNumber = 1;
    }
    $rawPassword = $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    $password = password_hash($rawPassword, PASSWORD_DEFAULT);

    $valid_id_path = null;
    if(isset($_FILES['valid_id']) && $_FILES['valid_id']['error'] == 0){
        $targetDir = "../uploads/valid_ids/";
        if(!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $filename = time() . "_" . basename($_FILES["valid_id"]["name"]);
        $targetFile = $targetDir . $filename;
        if(move_uploaded_file($_FILES["valid_id"]["tmp_name"], $targetFile)){
            $valid_id_path = $filename;
        }
    }

    $is_approved = 1;
    $status = 'Inactive';
    $email_verified = 1;

    $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $checkStmt->bind_param("ss", $username, $email);
    $checkStmt->execute();
    $checkStmt->store_result();

    if($checkStmt->num_rows > 0){
        $errorMsg = "Username or email already exists!";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (resident_id, username, email, password, role, status, email_verified, is_approved, valid_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssssss", $resident_id, $username, $email, $password, $role_new, $status, $email_verified, $is_approved, $valid_id_path);
        if($stmt->execute()){
            $new_user_id = $stmt->insert_id;
            $stmt->close();
            $successMsg = "User added successfully!";
            if($resident_id){
                $updateResident = $conn->prepare("UPDATE residents SET user_id = ? WHERE resident_id = ? AND (user_id IS NULL OR user_id = 0)");
                $updateResident->bind_param("ii", $new_user_id, $resident_id);
                $updateResident->execute();
                $updateResident->close();
            }
            $emailResult = sendUserEmail($email, $username, $rawPassword);
            if($emailResult !== true){
                $errorMsg = "User added but email could not be sent: $emailResult";
            }
        }
    }
    $checkStmt->close();
}

if(isset($_POST['update_user'])){
    $user_id_edit = $_POST['user_id'];
    $role_new = $_POST['role'];
    $resident_id = !empty($_POST['resident_id']) ? $_POST['resident_id'] : null;

    $valid_id_path = null;
    if(isset($_FILES['valid_id']) && $_FILES['valid_id']['error'] == 0){
        $targetDir = "../uploads/valid_ids/";
        if(!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $filename = time() . "_" . basename($_FILES["valid_id"]["name"]);
        $targetFile = $targetDir . $filename;
        if(move_uploaded_file($_FILES["valid_id"]["tmp_name"], $targetFile)){
            $valid_id_path = $filename;
        }
    }

    if($valid_id_path){
        $stmt = $conn->prepare("UPDATE users SET role=?, resident_id=?, valid_id=? WHERE id=?");
        $stmt->bind_param("sisi", $role_new, $resident_id, $valid_id_path, $user_id_edit);
    } else {
        $stmt = $conn->prepare("UPDATE users SET role=?, resident_id=? WHERE id=?");
        $stmt->bind_param("sii", $role_new, $resident_id, $user_id_edit);
    }

    $stmt->execute();
    $stmt->close();

    if($resident_id){
        $updateResident = $conn->prepare("UPDATE residents SET user_id = ? WHERE resident_id = ? AND (user_id IS NULL OR user_id = 0)");
        $updateResident->bind_param("ii", $user_id_edit, $resident_id);
        $updateResident->execute();
        $updateResident->close();
    }

    $successMsg = "User updated successfully!";
}

if(isset($_POST['approve_user_id'])){
    $approve_id = $_POST['approve_user_id'];
    $stmt = $conn->prepare("UPDATE users SET is_approved = 1, status = 'Active' WHERE id = ?");
    $stmt->bind_param("i", $approve_id);
    if($stmt->execute()){
        $successMsg = "User approved successfully!";
    } else {
        $errorMsg = "Failed to approve user!";
    }
    $stmt->close();
}

if(isset($_POST['delete_user_id'])){
    $delete_id = $_POST['delete_user_id'];
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if($stmt->execute()){
        $successMsg = "User deleted successfully!";
    } else {
        $errorMsg = "Failed to delete user!";
    }
    $stmt->close();
}

if(isset($_POST['reject_user_id'])){
    $reject_id = $_POST['reject_user_id'];
    $stmt = $conn->prepare("UPDATE users SET is_approved = 0, status = 'Inactive', rejected = 1 WHERE id = ?");
    $stmt->bind_param("i", $reject_id);
    if($stmt->execute()){
        $successMsg = "User rejected successfully!";
    } else {
        $errorMsg = "Failed to reject user!";
    }
    $stmt->close();
}

$perPage = isset($_GET['perPage']) ? (int)$_GET['perPage'] : 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page-1)*$perPage;

$totalUsers = $conn->query("SELECT COUNT(*) AS cnt FROM users WHERE id != '$user_id' AND rejected = 0")->fetch_assoc()['cnt'];

$totalPages = ceil($totalUsers/$perPage);

$usersQuery = $conn->query("SELECT * FROM users WHERE id != '$user_id' AND rejected = 0 ORDER BY created_at DESC LIMIT $start,$perPage");
$settingsQuery = $conn->query("SELECT barangay_name, system_logo FROM system_settings LIMIT 1");
$settings = $settingsQuery->fetch_assoc();
$barangayName = $settings['barangay_name'] ?? 'Barangay Name';
$systemLogo = $settings['system_logo'] ?? 'default-logo.png';
$systemLogoPath = '../' . $systemLogo;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>User Management</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link rel="stylesheet" href="use.css">

</head>
<body class="bg-gray-50 font-sans" data-success="<?= htmlspecialchars($successMsg) ?>" data-error="<?= htmlspecialchars($errorMsg) ?>">

<div class="flex h-screen">
<aside id="sidebar" class="w-64 bg-gradient-to-b from-blue-500 to-blue-700 text-white flex flex-col shadow-xl transition-all duration-300 h-screen">
    <div class="flex items-center justify-between p-4 border-b border-white/20">
        <div class="flex items-center space-x-3"><img src="<?= htmlspecialchars($systemLogoPath) ?>"
     alt="Barangay Logo"
     class="w-16 h-16 rounded-full object-cover shadow-sm border-2 border-white bg-white p-1 transition-all">

            <span class="font-semibold text-lg sidebar-text"><?= htmlspecialchars($barangayName) ?></span>
        </div>
        <button id="toggleSidebar" class="material-icons cursor-pointer text-2xl">chevron_left</button>
    </div>

  <nav class="flex-1 overflow-y-auto px-2 py-5 space-y-2">
    <a href="../dashboard.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 transition-colors">
      <span class="material-icons mr-3">dashboard</span><span class="sidebar-text">Dashboard</span>
    </a>

    <a href="../officials/barangay_officials.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 transition-colors">
      <span class="material-icons mr-3">groups</span><span class="sidebar-text">Barangay Officials</span>
    </a>

    <a href="../residents/resident.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 transition-colors">
      <span class="material-icons mr-3">people</span><span class="sidebar-text">Residents</span>
    </a>

    <a href="../households/household.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
      <span class="material-icons mr-3">home</span><span class="sidebar-text">Household</span>
    </a>
          <?php if($role === 'admin'): ?>
        <div class="pt-4">
            <span class="px-4 py-2 text-white/70 uppercase text-xs tracking-wider sidebar-text">Requests</span>
            <a href="requests/household_member_requests.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
                <span class="material-icons mr-3">group_add</span><span class="sidebar-text">Household Member Requests</span>
            </a>
            <a href="requests/request_profile_update.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
                <span class="material-icons mr-3">pending_actions</span><span class="sidebar-text">Profile Update Requests</span>
            </a>
        </div>
        <?php endif; ?>

    <?php if($role === 'admin'): ?>
      <div class="pt-4">
        <span class="px-4 py-2 text-white/70 uppercase text-xs tracking-wider sidebar-text">Community</span>
        <a href="../announcements.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
          <span class="material-icons mr-3">campaign</span><span class="sidebar-text">Announcements</span>
        </a>
        <a href="../news_updates.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
          <span class="material-icons mr-3">article</span><span class="sidebar-text">News & Updates</span>
        </a>
      </div>
    <?php endif; ?>

    <div class="pt-4">
      <span class="px-4 py-2 text-white/70 uppercase text-xs tracking-wider sidebar-text">Certificate Management</span>
      <a href="../certificate/certificate_requests.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
        <span class="material-icons mr-3">assignment</span><span class="sidebar-text">Certificate Requests</span>
      </a>
      <a href="../certificate/walkin_certificates.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
        <span class="material-icons mr-3">how_to_reg</span><span class="sidebar-text">Walk-in Requests</span>
      </a>
    </div>

    <div class="pt-4">
      <span class="px-4 py-2 text-white/70 uppercase text-xs tracking-wider sidebar-text">Blotter</span>
      <a href="../blotter/blotter.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
        <span class="material-icons mr-3">gavel</span><span class="sidebar-text">Blotter Records</span>
      </a>
    </div>

    <a href="../reports/report.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
      <span class="material-icons mr-3">bar_chart</span><span class="sidebar-text">Reports</span>
    </a>

    <div class="pt-4">
      <span class="px-4 py-2 text-white/70 uppercase text-xs tracking-wider sidebar-text">ID Management</span>
      <a href="../id/barangay_id_requests.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
        <span class="material-icons mr-3">credit_card</span><span class="sidebar-text">Barangay ID Request</span>
      </a>
      <a href="../id/walk_in_request.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
        <span class="material-icons mr-3">how_to_reg</span><span class="sidebar-text">Walk-in Request</span>
      </a>
    </div>

    <?php if($role === 'admin'): ?>
      <div class="pt-4">
        <span class="px-4 py-2 text-white/70 uppercase text-xs tracking-wider sidebar-text">User Management</span>
        <a href="../user_manage/user_management.php" class="flex items-center px-4 py-3 rounded-md bg-white/10 backdrop-blur-sm transition-all">
          <span class="material-icons mr-3">admin_panel_settings</span><span class="sidebar-text">System User</span>
        </a>
        <a href="../user_manage/log_activity.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
          <span class="material-icons mr-3">history</span><span class="sidebar-text">Log Activity</span>
        </a>
        <a href="../user_manage/settings.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
          <span class="material-icons mr-3">settings</span><span class="sidebar-text">Settings</span>
        </a>
      </div>
    <?php endif; ?>

    <a href="../../logout.php" class="flex items-center px-4 py-3 rounded bg-red-600 hover:bg-red-700 transition-colors mt-2">
      <span class="material-icons mr-3">logout</span><span class="sidebar-text">Logout</span>
    </a>
  </nav>
</aside>


<div class="flex-1 flex flex-col overflow-hidden bg-gray-50">
  <header class="flex items-center justify-between bg-white shadow-md px-6 py-4 rounded-b-2xl mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Users</h2>
    <div class="flex items-center space-x-3">
      <input type="text" id="searchInput" placeholder="Search users..." 
             class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400 text-sm transition">
      <?php if($role === 'admin'): ?>
      <button id="addUserBtn" 
              class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2 rounded-lg shadow font-medium transition text-sm">
        Add User
      </button>
      <?php endif; ?>
    </div>
  </header>

<main class="flex-1 overflow-y-auto p-6">

  <div class="bg-white p-6 rounded-2xl shadow-lg overflow-x-auto">

    <!-- SHOW ENTRIES -->
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-2">
        <span class="text-sm text-gray-700">Show</span>
        <select id="showEntries" class="border border-gray-300 rounded-lg px-2 py-1 text-sm">
          <option value="5">5</option>
          <option value="10" selected>10</option>
          <option value="25">25</option>
          <option value="50">50</option>
        </select>
        <span class="text-sm text-gray-700">entries</span>
      </div>
    </div>

    <table class="min-w-full divide-y divide-gray-200">

      <thead class="bg-gray-100 text-gray-700 uppercase text-xs font-semibold tracking-wide">
        <tr>
          <th class="px-4 py-3 text-left">Username</th>
          <th class="px-4 py-3 text-left">Email</th>
          <th class="px-4 py-3 text-left">Role</th>
          <th class="px-4 py-3 text-left">Status</th>
          <th class="px-4 py-3 text-left">Approved</th>
          <th class="px-4 py-3 text-left">Valid ID</th>
          <?php if($role === 'admin'): ?>
          <th class="px-4 py-3 text-center">Actions</th>
          <?php endif; ?>
        </tr>
      </thead>

      <tbody id="userTable" class="text-sm">

        <?php while($u = $usersQuery->fetch_assoc()): ?>
        <tr class="border-b hover:bg-gray-50 transition">

          <td class="px-4 py-3 font-medium text-gray-900">
            <?= htmlspecialchars($u['username']) ?>
          </td>

          <td class="px-4 py-3 text-gray-700">
            <?= htmlspecialchars($u['email']) ?>
          </td>

          <td class="px-4 py-3">
            <?= $u['role']=='resident' ? 'Resident User' : htmlspecialchars($u['role']) ?>
          </td>

          <td class="px-4 py-3">
            <span class="inline-flex items-center gap-2">
              <span class="w-3 h-3 rounded-full <?= $u['status']=='Active' ? 'bg-green-500' : 'bg-red-500' ?>"></span>
              <span class="<?= $u['status']=='Active' ? 'text-green-600' : 'text-red-600' ?> font-medium">
                <?= htmlspecialchars($u['status']) ?>
              </span>
            </span>
          </td>

          <td class="px-4 py-3 font-medium <?= $u['is_approved']==1 ? 'text-green-600' : 'text-red-600' ?>">
            <?= $u['is_approved']==1 ? 'Yes' : 'No' ?>
          </td>

          <td class="px-4 py-3">
            <?php if(!empty($u['valid_id'])): ?>
              <button class="viewIdBtn text-blue-600 hover:underline" data-id="<?= htmlspecialchars($u['valid_id']) ?>">View</button>
            <?php else: ?>
              <span class="text-red-500">No ID</span>
            <?php endif; ?>
          </td>

          <?php if($role === 'admin'): ?>
          <td class="px-4 py-3 text-center">
            <div class="flex justify-center gap-2">

              <?php if($u['is_approved'] == 0): ?>
                <form method="POST">
                  <input type="hidden" name="approve_user_id" value="<?= $u['id'] ?>">
                  <button type="submit" class="bg-emerald-600 text-white px-3 py-1.5 rounded-lg hover:bg-emerald-700 text-xs transition">Approve</button>
                </form>

                <form method="POST">
                  <input type="hidden" name="reject_user_id" value="<?= $u['id'] ?>">
                  <button type="button" class="rejectBtn bg-red-600 text-white px-3 py-1.5 rounded-lg hover:bg-red-700 text-xs transition" data-id="<?= $u['id'] ?>">Reject</button>
                </form>

              <?php else: ?>

                <button class="editBtn bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700 text-xs transition" data-user='<?= json_encode($u) ?>'>Edit</button>
                <button class="deleteBtn bg-red-600 text-white px-3 py-1.5 rounded-lg hover:bg-red-700 text-xs transition" data-id="<?= $u['id'] ?>">Delete</button>

              <?php endif; ?>

            </div>
          </td>
          <?php endif; ?>

        </tr>
        <?php endwhile; ?>

      </tbody>
    </table>

    <!-- PAGINATION -->
    <div class="flex items-center justify-between mt-6">
      <p id="paginationInfo" class="text-sm text-gray-600"></p>

      <div class="flex gap-2">
        <button id="prevPage" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 text-sm">Previous</button>
        <button id="nextPage" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 text-sm">Next</button>
      </div>
    </div>

  </div>

</main>



  </div>
</div>

<div id="idModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="bg-white rounded-2xl w-11/12 sm:w-3/4 md:w-1/3 p-6 relative max-h-[85vh] overflow-auto shadow-xl">
    <button id="closeIdModal" class="absolute top-3 right-3 text-gray-500 hover:text-gray-700 material-icons">close</button>
    <h2 class="text-xl font-semibold mb-4 text-center">Valid ID</h2>
    <div class="text-center" id="idContainer"></div>
  </div>
</div>

<div id="userModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="bg-white rounded-2xl w-11/12 sm:w-3/4 md:w-1/3 p-6 relative max-h-[90vh] overflow-auto shadow-xl">
    <button id="closeUserModal" class="absolute top-3 right-3 text-gray-500 hover:text-gray-700 material-icons">close</button>
    <h2 class="text-xl font-semibold mb-4 text-center" id="userModalTitle">Add User</h2>
    <form id="userForm" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="user_id" id="user_id">
      <input type="hidden" name="resident_id" id="resident_id">
      <input type="hidden" name="password" id="password">
      <div class="mb-3">
        <label class="block text-gray-700 font-medium mb-1">Username (Resident)</label>
        <input type="text" name="username" id="username" class="w-full border px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400" placeholder="Start typing resident name...">
      </div>
      <div class="mb-3">
        <label class="block text-gray-700 font-medium mb-1">Email</label>
        <input type="email" name="email" id="email" class="w-full border px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400">
      </div>
      <div class="mb-3">
        <label class="block text-gray-700 font-medium mb-1">Role</label>
        <select name="role" id="role" class="w-full border px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400">
          <option value="admin">Admin</option>
          <option value="staff">Staff</option>
          <option value="resident">Resident User</option>
        </select>
      </div>
      <div class="flex justify-center gap-3">
        <button type="submit" name="add_user" id="saveBtn" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">Save</button>
        <button type="button" id="cancelUserBtn" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400 transition">Cancel</button>
      </div>
    </form>
  </div>
</div>

<div id="deleteConfirmModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
  <div class="bg-white rounded-xl p-6 w-80 text-center">
    <h2 class="text-lg font-semibold mb-4">Delete User?</h2>
    <p class="mb-6 text-sm">Are you sure you want to delete this user?</p>
    <div class="flex justify-center space-x-3">
      <button id="cancelDelete" class="bg-gray-400 text-white px-4 py-2 rounded">Cancel</button>
      <form method="POST">
        <input type="hidden" name="delete_user_id" id="delete_user_id">
        <button type="submit" name="delete_user" class="bg-red-500 text-white px-4 py-2 rounded">Delete</button>
      </form>
    </div>
  </div>
</div>

<div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
  <div class="bg-white rounded-xl p-6 w-80 text-center relative">
    <button id="closeSuccessModal" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 material-icons">close</button>
    <span class="material-icons text-green-500 text-4xl mb-2">check_circle</span>
    <p id="successMessage" class="text-gray-700 font-medium mb-4"></p>
    <button id="okSuccessBtn" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">OK</button>
  </div>
</div>


<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
$(document).ready(function(){

    function generateNextPassword(callback){
        $.ajax({
            url: 'get_next_password.php',
            method: 'GET',
            success: function(data){
                callback(data);
            }
        });
    }

    $('#addUserBtn').on('click', function(){
        $('#userModalTitle').text('Add User');
        $('#userForm').attr('action', '');
        $('#saveBtn').attr('name','add_user').text('Add');
        $('#user_id, #resident_id').val('');
        $('#username, #email').val('').prop('readonly', false);
        $('#role').val('resident');
        $('#passwordDiv').show();
        generateNextPassword(function(nextPass){
            $('#password').val(nextPass);
        });
        $('#userModal').removeClass('hidden').addClass('flex');
    });

    $('.editBtn').on('click', function(){
        var user = $(this).data('user');
        $('#userModalTitle').text('Edit User');
        $('#userForm').attr('action', '');
        $('#saveBtn').attr('name','update_user').text('Update');
        $('#passwordDiv').hide();
        $('#user_id').val(user.id);
        $('#resident_id').val(user.resident_id ?? '');
        $('#username').val(user.username);
        $('#email').val(user.email);
        $('#role').val(user.role);
        $('#username, #email').prop('readonly', true);
        $('#userModal').removeClass('hidden').addClass('flex');
    });

    $('#closeUserModal, #cancelUserBtn').on('click', function(){
        $('#userModal').addClass('hidden').removeClass('flex');
        $('#userForm')[0].reset();
        $('#resident_id').val('');
    });

    $('.deleteBtn').on('click', function(){
        var userId = $(this).data('id');
        $('#delete_user_id').val(userId);
        $('#deleteConfirmModal').removeClass('hidden').addClass('flex');
    });

    $('#cancelDelete').on('click', function(){
        $('#deleteConfirmModal').addClass('hidden').removeClass('flex');
        $('#delete_user_id').val('');
    });

    $('#closeSuccessModal, #okSuccessBtn').on('click', function(){
        $('#successModal').addClass('hidden').removeClass('flex');
    });

    <?php if(!empty($successMsg)): ?>
        $('#successMessage').text("<?= htmlspecialchars($successMsg) ?>");
        $('#successModal').removeClass('hidden').addClass('flex');
    <?php endif; ?>

    $("#username").autocomplete({
        source: function(request, response) {
            $.ajax({
                url: 'search_residents.php',
                type: 'GET',
                dataType: 'json',
                data: { term: request.term },
                success: function(data) {
                    response(data);
                }
            });
        },
        minLength: 2,
        select: function(event, ui) {
            $("#username").val(ui.item.label);
            $("#resident_id").val(ui.item.value);
            $("#email").val(ui.item.email);
            return false;
        }
    });

    $('.viewIdBtn').on('click', function(){
        var filename = $(this).data('id');
        var imgSrc = '../../' + filename;
        $('#idContainer').html('<img src="'+imgSrc+'" class="mx-auto max-h-[60vh] rounded-lg shadow" alt="Valid ID">');
        $('#idModal').removeClass('hidden').addClass('flex');
    });

    $('#closeIdModal').on('click', function(){
        $('#idModal').addClass('hidden').removeClass('flex');
        $('#idContainer').html('');
    });

});
const sidebar = document.getElementById('sidebar');
const toggleBtn = document.getElementById('toggleSidebar');
toggleBtn.onclick = () => {
    sidebar.classList.toggle('sidebar-collapsed');
    let icon = toggleBtn.textContent.trim();
    toggleBtn.textContent = icon === 'chevron_left' ? 'chevron_right' : 'chevron_left';
};

document.getElementById("searchInput").addEventListener("keyup", function () {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll("#userTable tr");

    rows.forEach(row => {
        let text = row.innerText.toLowerCase();
        row.style.display = text.includes(filter) ? "" : "none";
    });
});

let tableRows = Array.from(document.querySelectorAll("#userTable tr"));
let currentPage = 1;
let entries = 10;

function updateTable() {
    let start = (currentPage - 1) * entries;
    let end = start + entries;

    tableRows.forEach((row, i) => {
        row.style.display = (i >= start && i < end) ? "" : "none";
    });

    document.getElementById("paginationInfo").innerText =
        `Showing ${Math.min(end, tableRows.length)} of ${tableRows.length} entries`;
}

document.getElementById("showEntries").addEventListener("change", function () {
    entries = parseInt(this.value);
    currentPage = 1;
    updateTable();
});

document.getElementById("nextPage").addEventListener("click", function () {
    if (currentPage * entries < tableRows.length) {
        currentPage++;
        updateTable();
    }
});

document.getElementById("prevPage").addEventListener("click", function () {
    if (currentPage > 1) {
        currentPage--;
        updateTable();
    }
});

updateTable();
</script>



</body>
</html>