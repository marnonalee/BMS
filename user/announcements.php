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

$showModal = false;
$modalMessage = "";
$modalType = "success";

function uploadFiles($files, $announcement_id, $conn){
    if(isset($files['name']) && is_array($files['name'])){
        foreach ($files['name'] as $key => $name) {
            $tmp_name = $files['tmp_name'][$key] ?? null;
            if($name && $tmp_name){
                $fileName = time().'_'.$name;
                $filePath = 'uploads/'.$fileName;
                if(!move_uploaded_file($tmp_name, $filePath)){
                    continue;
                }
                $stmt = $conn->prepare("INSERT INTO announcement_images (announcement_id, image_path) VALUES (?, ?)");
                $stmt->bind_param("is", $announcement_id, $filePath);
                $stmt->execute();
            }
        }
    }
}

function sendAnnouncementNotification($conn, $title, $content, $from_role = "admin") {
    $res = $conn->query("SELECT resident_id FROM residents");
    while ($row = $res->fetch_assoc()) {
        $resident_id = $row['resident_id'];
        $stmt = $conn->prepare("INSERT INTO notifications (resident_id, message, from_role, title, type, priority, action_type) VALUES (?, ?, ?, ?, 'announcement', 'normal', 'created')");
        $stmt->bind_param("isss", $resident_id, $content, $from_role, $title);
        $stmt->execute();
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])){
    $title = $_POST["title"] ?? '';
    $content = $_POST["content"] ?? '';
    $content = preg_replace('/\s?data-(start|end)="\d+"/', '', $content);

    if($_POST['action'] === 'create'){
        $stmt = $conn->prepare("INSERT INTO announcements (user_id, title, content) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $title, $content);
        if($stmt->execute()){
            $announcement_id = $stmt->insert_id;

            if(isset($_FILES['images']) && !empty($_FILES['images']['name'][0])){
                uploadFiles($_FILES['images'], $announcement_id, $conn);
            }

            sendAnnouncementNotification($conn, $title, $content, $role);

            $showModal = true;
            $modalMessage = "Announcement posted!";
            $modalType = "success";
        } else {
            $showModal = true;
            $modalMessage = "Failed to post announcement!";
            $modalType = "error";
        }
    } elseif($_POST['action'] === 'edit'){
        $edit_id = $_POST['edit_id'];

        if(isset($_POST['delete_images'])){
            foreach($_POST['delete_images'] as $img_id){
                $stmtDel = $conn->prepare("DELETE FROM announcement_images WHERE id=?");
                $stmtDel->bind_param("i", $img_id);
                $stmtDel->execute();
            }
        }

        $stmt = $conn->prepare("UPDATE announcements SET title=?, content=? WHERE id=?");
        $stmt->bind_param("ssi", $title, $content, $edit_id);
        if($stmt->execute()){
            if(isset($_FILES['images']) && !empty($_FILES['images']['name'][0])){
                uploadFiles($_FILES['images'], $edit_id, $conn);
            }
            $showModal = true;
            $modalMessage = "Announcement updated!";
            $modalType = "success";
        } else {
            $showModal = true;
            $modalMessage = "Failed to update announcement!";
            $modalType = "error";
        }
    }
}

if(isset($_GET['delete_id'])){
    $delete_id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM announcements WHERE id=?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $showModal = true;
    $modalMessage = $stmt->affected_rows ? "Announcement deleted!" : "Failed to delete announcement!";
    $modalType = $stmt->affected_rows ? "success" : "error";
}

$announcements = $conn->query("SELECT a.*, u.username FROM announcements a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.id DESC");

$settings = $conn->query("SELECT system_logo, barangay_name FROM system_settings WHERE id=1")->fetch_assoc();
$systemLogo = ($settings['system_logo'] ?? 'default_logo.png');
$barangayName = $settings['barangay_name'] ?? 'Barangay';
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Announcements</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<script src="assets/tinymce/js/tinymce/tinymce.min.js"></script>
<link rel="stylesheet" href="a.css">
</head>
<body class="bg-gray-100">

<div class="flex h-screen">
<aside id="sidebar" class="w-64 bg-gradient-to-b from-blue-500 to-blue-700 text-white flex flex-col shadow-xl transition-all duration-300 h-screen">
    <div class="flex items-center justify-between p-4 border-b border-white/20">
        <div class="flex items-center space-x-3"> <img src="<?= htmlspecialchars($systemLogo) ?>"
         alt="Barangay Logo"
         class="w-16 h-16 rounded-full object-cover shadow-sm border-2 border-white bg-white p-1 transition-all">
            <span class="font-semibold text-lg sidebar-text"><?= htmlspecialchars($barangayName) ?></span>
        </div>
        <button id="toggleSidebar" class="material-icons cursor-pointer text-2xl">chevron_left</button>
    </div>

  <nav class="flex-1 overflow-y-auto px-2 py-5 space-y-2">
    <a href="dashboard.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 transition-colors">
      <span class="material-icons mr-3">dashboard</span><span class="sidebar-text">Dashboard</span>
    </a>

    <a href="officials/barangay_officials.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 transition-colors">
      <span class="material-icons mr-3">groups</span><span class="sidebar-text">Barangay Officials</span>
    </a>

    <a href="residents/resident.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 transition-colors">
      <span class="material-icons mr-3">people</span><span class="sidebar-text">Residents</span>
    </a>

    <a href="households/household.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
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
        <a href="announcements.php"  class="flex items-center px-4 py-3 rounded-md bg-white/10 backdrop-blur-sm transition-all">
          <span class="material-icons mr-3">campaign</span><span class="sidebar-text">Announcements</span>
        </a>
        <a href="news_updates.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
          <span class="material-icons mr-3">article</span><span class="sidebar-text">News & Updates</span>
        </a>
      </div>
    <?php endif; ?>

    <div class="pt-4">
      <span class="px-4 py-2 text-white/70 uppercase text-xs tracking-wider sidebar-text">Certificate Management</span>
      <a href="certificate/certificate_requests.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
        <span class="material-icons mr-3">assignment</span><span class="sidebar-text">Certificate Requests</span>
      </a>
      <a href="certificate/walkin_certificates.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
        <span class="material-icons mr-3">how_to_reg</span><span class="sidebar-text">Walk-in Requests</span>
      </a>
    </div>

    <div class="pt-4">
      <span class="px-4 py-2 text-white/70 uppercase text-xs tracking-wider sidebar-text">Blotter</span>
      <a href="blotter/blotter.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
        <span class="material-icons mr-3">gavel</span><span class="sidebar-text">Blotter Records</span>
      </a>
    </div>

    <a href="reports/report.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
      <span class="material-icons mr-3">bar_chart</span><span class="sidebar-text">Reports</span>
    </a>

    <div class="pt-4">
      <span class="px-4 py-2 text-white/70 uppercase text-xs tracking-wider sidebar-text">ID Management</span>
      <a href="id/barangay_id_requests.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
        <span class="material-icons mr-3">credit_card</span><span class="sidebar-text">Barangay ID Request</span>
      </a>
      <a href="id/walk_in_request.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
        <span class="material-icons mr-3">how_to_reg</span><span class="sidebar-text">Walk-in Request</span>
      </a>
    </div>

    <?php if($role === 'admin'): ?>
      <div class="pt-4">
        <span class="px-4 py-2 text-white/70 uppercase text-xs tracking-wider sidebar-text">User Management</span>
        <a href="user_manage/user_management.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
          <span class="material-icons mr-3">admin_panel_settings</span><span class="sidebar-text">System User</span>
        </a>
        <a href="user_manage/log_activity.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
          <span class="material-icons mr-3">history</span><span class="sidebar-text">Activity Logs</span>
        </a>
        <a href="user_manage/settings.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
          <span class="material-icons mr-3">settings</span><span class="sidebar-text">Settings</span>
        </a>
      </div>
    <?php endif; ?>

    <a href="../logout.php" class="flex items-center px-4 py-3 rounded bg-red-600 hover:bg-red-700 transition-colors mt-2">
      <span class="material-icons mr-3">logout</span><span class="sidebar-text">Logout</span>
    </a>
  </nav>
</aside>
<div class="flex-1 flex flex-col bg-gray-50">
  <header class="flex items-center justify-between bg-white shadow-md px-6 py-4 rounded-b-2xl mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Announcements</h2>
  </header>
<main class="flex-1 overflow-y-auto p-6 bg-gray-50 space-y-8">

  <!-- Announcement Form -->
  <div class="bg-white rounded-xl shadow-md p-6">
    <?php if(isset($_SESSION['success'])): ?>
      <div class="bg-green-100 text-green-800 p-3 rounded mb-4">
        <?= $_SESSION['success'] ?>
      </div>
      <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <form id="postForm" action="" method="POST" enctype="multipart/form-data" class="space-y-4">
      <input type="hidden" name="action" value="create">
      <input type="hidden" name="edit_id" id="edit_id" value="">

      <div class="space-y-1">
        <label for="titleInput" class="block font-semibold text-gray-700">Title</label>
        <input type="text" name="title" id="titleInput" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-400 transition">
      </div>

      <div class="space-y-1">
        <label for="contentInput" class="block font-semibold text-gray-700">Content</label>
        <textarea name="content" id="contentInput" rows="8" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-400 transition"><?= isset($content) ? htmlspecialchars($content) : '' ?></textarea>
      </div>

      <div class="space-y-1">
        <label for="fileInput" class="block font-semibold text-gray-700">Image / Media (optional)</label>
        <input type="file" name="images[]" id="fileInput" class="block w-full" multiple>
        <div id="previewContainer" class="flex flex-wrap gap-2 mt-2"></div>
      </div>

      <button type="submit" name="submit" id="submitBtn" class="bg-emerald-600 text-white px-5 py-2 rounded-lg hover:bg-emerald-700 transition font-semibold">
        Publish
      </button>
    </form>
  </div>

  <section>
    <h2 class="text-2xl font-bold text-gray-800 mb-4">All Announcements</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php while($row = $announcements->fetch_assoc()): ?>
        <div class="bg-white rounded-xl shadow-md border-l-4 border-emerald-500 overflow-hidden flex flex-col transition hover:shadow-xl">
          
          <div class="flex items-center p-4 bg-emerald-50 border-b border-emerald-100">
            <img src="<?= $systemLogo ?>" alt="Logo" class="w-12 h-12 rounded-full object-cover border-2 border-emerald-500">
            <div class="ml-3 flex-1">
              <p class="font-bold text-gray-800"><?= htmlspecialchars($barangayName) ?></p>
              <p class="text-sm text-gray-500"><?= date("M d, Y h:i A", strtotime($row['created_at'])) ?></p>
            </div>

            <?php if($row['user_id'] == $user_id || $role === 'admin'): ?>
              <div class="flex space-x-2">
                <button type="button" onclick='editPost(<?= $row["id"] ?>, <?= json_encode($row["title"]) ?>, <?= json_encode($row["content"]) ?>)' class="text-yellow-500 hover:text-yellow-600 material-icons">edit</button>
                <button type="button" onclick='showDeleteModal(<?= $row["id"] ?>, String(<?= json_encode($row["title"], JSON_HEX_APOS | JSON_HEX_QUOT) ?>))' class="text-red-500 hover:text-red-600 material-icons">delete</button>
              </div>
            <?php endif; ?>
          </div>

          <div class="p-4 flex-1 flex flex-col space-y-3">
            <h3 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($row['title']) ?></h3>

            <?php
              $previewLength = 200;
              $cleanContent = preg_replace('/\s?data-(start|end)="\d+"/', '', $row['content']);
              $fullContent = $cleanContent;
              $shortContent = strlen(strip_tags($cleanContent)) > $previewLength 
                  ? substr(strip_tags($cleanContent), 0, $previewLength) . '...' 
                  : strip_tags($cleanContent);
            ?>
            <p class="text-gray-700 announcement-content" data-full="<?= htmlspecialchars($fullContent) ?>" data-short="<?= htmlspecialchars($shortContent) ?>">
              <?= $shortContent ?>
            </p>
            <?php if(strlen(strip_tags($cleanContent)) > $previewLength): ?>
              <button class="text-blue-600 hover:underline toggle-content-btn text-sm">View More</button>
            <?php endif; ?>

            <?php
              $imgQuery = $conn->prepare("SELECT id, image_path FROM announcement_images WHERE announcement_id=?");
              $imgQuery->bind_param("i",$row['id']);
              $imgQuery->execute();
              $imgResult = $imgQuery->get_result();
              $mediaFiles = $imgResult->fetch_all(MYSQLI_ASSOC);
              if(count($mediaFiles) > 0): ?>
              <div class="grid grid-cols-2 gap-2 mt-2">
                <?php foreach($mediaFiles as $media):
                  $ext = pathinfo($media['image_path'], PATHINFO_EXTENSION);
                  if(in_array(strtolower($ext), ['mp4','webm','ogg'])): ?>
                    <video controls class="w-full h-40 object-cover rounded"><?= $media['image_path'] ?></video>
                  <?php else: ?>
                    <img src="<?= $media['image_path'] ?>" class="w-full h-40 object-cover rounded" alt="announcement image">
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

          </div>
        </div>
      <?php endwhile; ?>
    </div>
  </section>

</main>

</div>

</div>

<div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-80 text-center relative">
        <button id="closeSuccessModal" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 material-icons">close</button>
        <span id="modalIcon" class="material-icons text-4xl mb-2"></span>
        <p id="successMessage" class="text-gray-700 font-medium"></p>
        <button id="okSuccessBtn" class="mt-4 bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">OK</button>
    </div>
</div>
<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-96 text-center relative">
        <span class="material-icons text-yellow-500 text-4xl mb-2">warning_amber</span>
        <p id="deleteMessage" class="text-gray-800 font-medium mb-4">Do you really want to delete this post?</p>
        <div class="flex justify-center gap-4">
            <button id="cancelDeleteBtn" class="bg-gray-300 px-4 py-2 rounded hover:bg-gray-400">Cancel</button>
            <button id="confirmDeleteBtn" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">Delete</button>
        </div>
    </div>
</div>
<script src="announcements.js"></script>
<script>
tinymce.init({
    selector: '#contentInput',
    height: 300,
    menubar: false,
    plugins: 'link code lists',
    toolbar: 'undo redo | bold italic | bullist numlist | link | code',
    license_key: 'gpl'
});

document.getElementById("fileInput").addEventListener("change", function() {
    const preview = document.getElementById("previewContainer");
    preview.innerHTML = "";

    Array.from(this.files).forEach(file => {
        const reader = new FileReader();
        reader.onload = e => {
            const img = document.createElement("img");
            img.src = e.target.result;
            img.className = "w-24 h-24 object-cover rounded border";
            preview.appendChild(img);
        };
        reader.readAsDataURL(file);
    });
});

// LOAD EXISTING IMAGES WHEN EDITING
function editPost(id, title, content) {
    document.querySelector("input[name='action']").value = "edit";
    document.getElementById("edit_id").value = id;
    document.getElementById("titleInput").value = title;

    tinymce.get("contentInput").setContent(content);

    document.getElementById("submitBtn").textContent = "Update Announcement";

    fetch("get_announcement_images.php?id=" + id)
        .then(res => res.json())
        .then(data => {
            const preview = document.getElementById("previewContainer");
            preview.innerHTML = "";

            data.forEach(img => {
                const wrapper = document.createElement("div");
                wrapper.className = "relative";

                wrapper.innerHTML = `
                    <img src="${img.image_path}" class="w-24 h-24 object-cover rounded border">

                    <button class="absolute top-0 right-0 bg-red-600 text-white text-xs p-1 rounded delete-existing"
                        data-id="${img.id}">
                        ✕
                    </button>

                    <input type="checkbox" name="delete_images[]" value="${img.id}" class="hidden delete-checkbox">
                `;

                preview.appendChild(wrapper);
            });

        });
}


<?php if ($showModal): ?>
document.getElementById("successMessage").innerText = "<?= $modalMessage ?>";
document.getElementById("modalIcon").innerText = "<?= $modalType === 'success' ? 'check_circle' : 'error' ?>";
document.getElementById("successModal").classList.remove("hidden");
<?php endif; ?>

document.getElementById("closeSuccessModal").onclick = function(){
    document.getElementById("successModal").classList.add("hidden");
};

document.getElementById("okSuccessBtn").onclick = function(){
    document.getElementById("successModal").classList.add("hidden");
};
</script>



</body>
</html>





