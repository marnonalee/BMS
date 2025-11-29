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

$modalType = '';
$modalMessage = '';
if ($role !== 'admin') {
    $modalType = 'error';
    $modalMessage = 'Access denied.';
}

$settings = $conn->query("SELECT * FROM system_settings WHERE id=1")->fetch_assoc();
$heroImages = [];
$heroQ = $conn->query("SELECT id, image_path, display_order FROM landing_hero_images ORDER BY display_order ASC, created_at DESC");
while ($r = $heroQ->fetch_assoc()) $heroImages[] = $r;

function urlFor($path) {
    if (!$path) return '';
    return '../' . ltrim($path,'/');
}
$barangayName = $settings['barangay_name'] ?? 'Barangay Name';
$systemLogo = $settings['system_logo'] ?? 'default-logo.png';
$systemLogoPath = '../' . $systemLogo;

?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>System Settings</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="use.css">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<style>
.input{width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;}

.dragging { opacity:.5; }
.hero-thumb { width:120px;height:70px;object-fit:cover;border-radius:8px; }
.grid-hero { display:flex;gap:12px;flex-wrap:wrap; }
.hero-item { display:flex;gap:8px;align-items:center;padding:8px;background:#fff;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,.06); }
.handle { cursor:grab;padding:6px; }
.accordion-body { transition: all 0.3s ease; }
.rotate-180 { transform: rotate(180deg); }
</style>
</head>
<body class="bg-gray-100 font-sans">
<div class="flex h-screen">
<aside id="sidebar" class="w-64 bg-gradient-to-b from-blue-500 to-blue-700 text-white flex flex-col shadow-xl transition-all duration-300 h-screen">
    <div class="flex items-center justify-between p-4 border-b border-white/20">
        <div class="flex items-center space-x-3"><img src="<?= htmlspecialchars($systemLogoPath) ?>" alt="Barangay Logo" class="w-16 h-16 rounded-full object-cover shadow-sm border-2 border-white transition-all">
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
        <a href="../user_manage/user_management.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
          <span class="material-icons mr-3">admin_panel_settings</span><span class="sidebar-text">System User</span>
        </a>
        <a href="../user_manage/log_activity.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
          <span class="material-icons mr-3">history</span><span class="sidebar-text">Log Activity</span>
        </a>
        <a href="../user_manage/settings.php" class="flex items-center px-4 py-3 rounded-md bg-white/10 backdrop-blur-sm transition-all">
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
    <h2 class="text-2xl font-bold text-gray-800">System Settings</h2>
  </header>

<main class="flex-1 overflow-y-auto p-6 bg-gray-50">
  <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-lg space-y-6">
      <h1 class="text-2xl font-bold text-gray-800">Barangay Branding & Personalization</h1>

     <form id="settingsForm" method="POST" action="save_settings.php" enctype="multipart/form-data" class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block mb-1 font-semibold text-gray-700">Barangay Name</label>
        <input type="text" name="barangay_name" value="<?= htmlspecialchars($settings['barangay_name']) ?>" class="p-3 border rounded w-full focus:outline-none focus:ring-2 focus:ring-emerald-400">
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
      <div>
        <label class="block mb-1 font-semibold text-gray-700">Municipality</label>
        <input type="text" name="municipality" value="<?= htmlspecialchars($settings['municipality']) ?>" class="p-3 border rounded w-full focus:outline-none focus:ring-2 focus:ring-emerald-400">
      </div>
      <div>
        <label class="block mb-1 font-semibold text-gray-700">Province</label>
        <input type="text" name="province" value="<?= htmlspecialchars($settings['province']) ?>" class="p-3 border rounded w-full focus:outline-none focus:ring-2 focus:ring-emerald-400">
      </div>
      <div>
        <label class="block mb-1 font-semibold text-gray-700">Country</label>
        <input type="text" name="country" value="<?= htmlspecialchars($settings['country']) ?>" class="p-3 border rounded w-full focus:outline-none focus:ring-2 focus:ring-emerald-400">
      </div>
    </div>

    <div class="mt-4">
      <label class="block mb-1 font-semibold text-gray-700">Barangay Address</label>
      <input type="text" name="barangay_address" value="<?= htmlspecialchars($settings['barangay_address']) ?>" class="w-full p-3 border rounded focus:outline-none focus:ring-2 focus:ring-emerald-400">
    </div>

   <div class="mt-4">
      <label class="block mb-1 font-semibold text-gray-700">Misyon</label>
      <textarea name="misyon" class="w-full p-3 border rounded focus:outline-none focus:ring-2 focus:ring-emerald-400" rows="3"><?= htmlspecialchars($settings['misyon'] ?? '') ?></textarea>
    </div>

    <div class="mt-4">
      <label class="block mb-1 font-semibold text-gray-700">Bisyon</label>
      <textarea name="bisyon" class="w-full p-3 border rounded focus:outline-none focus:ring-2 focus:ring-emerald-400" rows="3"><?= htmlspecialchars($settings['bisyon'] ?? '') ?></textarea>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4 items-end">
      <div>
        <label class="block text-sm font-semibold mb-1 text-gray-700">Logo</label>
        <input type="file" name="system_logo" class="w-full">
        <?php if(!empty($settings['system_logo'])): ?>
          <img src="<?= urlFor($settings['system_logo']) ?>" class="mt-2 h-16 rounded shadow-sm">
        <?php endif; ?>
      </div>
      <div>
        <label class="block text-sm font-semibold mb-1 text-gray-700">Theme Color</label>
        <input type="color" name="theme_color" value="<?= htmlspecialchars($settings['theme_color'] ?? '#0f6b35') ?>" class="w-24 h-12 border rounded focus:outline-none">
      </div>
    </div>

    <div class="bg-gray-50 border rounded-lg overflow-hidden mt-4">
      <button type="button" class="w-full flex justify-between items-center px-4 py-3 bg-white font-semibold text-left accordion-btn focus:outline-none">
        Email & SMTP Settings
        <i class="fa-solid fa-chevron-down transition-all"></i>
      </button>
      <div class="accordion-body hidden px-4 pb-4 pt-2">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
          <input type="email" name="system_email" placeholder="System Email" value="<?= htmlspecialchars($settings['system_email']) ?>" class="p-3 border rounded focus:outline-none focus:ring-2 focus:ring-emerald-400">
          <input type="text" name="contact_number" placeholder="Contact Number" value="<?= htmlspecialchars($settings['contact_number']) ?>" class="p-3 border rounded focus:outline-none focus:ring-2 focus:ring-emerald-400">
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
          <input type="text" name="smtp_host" placeholder="SMTP Host" value="<?= htmlspecialchars($settings['smtp_host']) ?>" class="p-3 border rounded focus:outline-none focus:ring-2 focus:ring-emerald-400">
          <input type="number" name="smtp_port" placeholder="SMTP Port" value="<?= htmlspecialchars($settings['smtp_port']) ?>" class="p-3 border rounded focus:outline-none focus:ring-2 focus:ring-emerald-400">
        </div>
        <input type="password" name="app_password" placeholder="App Password" value="<?= htmlspecialchars($settings['app_password']) ?>" class="p-3 border rounded w-full mt-3 focus:outline-none focus:ring-2 focus:ring-emerald-400">
      </div>
    </div>

    <button class="bg-emerald-600 text-white px-6 py-2 rounded-lg hover:bg-emerald-700 transition mt-4" type="submit">Save Settings</button>
  </form>

  <hr class="my-6 border-gray-300">

  <h2 class="text-lg font-semibold mb-3 text-gray-800">Hero Images Manager</h2>
  <div class="flex flex-col md:flex-row gap-6 items-start">
    <div class="flex-1">
      <input id="heroUpload" type="file" accept="image/*" multiple class="w-full">
      <div class="mt-3 grid grid-cols-2 md:grid-cols-3 gap-4" id="heroList">
        <?php foreach($heroImages as $img): ?>
          <div class="hero-item bg-gray-50 p-2 rounded shadow flex flex-col items-center" data-id="<?= $img['id'] ?>" draggable="true">
            <div class="handle mb-2 cursor-move"><i class="fas fa-grip-lines text-gray-500"></i></div>
            <img src="<?= urlFor($img['image_path']) ?>" class="hero-thumb rounded shadow-sm mb-2">
            <div class="flex flex-col items-center gap-1">
              <button class="btn-delete text-red-600" data-id="<?= $img['id'] ?>">Delete</button>
              <small class="text-gray-500">Order: <?= intval($img['display_order']) ?></small>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<div class="bg-white p-6 rounded-xl shadow-lg w-full space-y-4">
  <h2 class="text-lg font-bold text-gray-800">Live Preview</h2>
  <iframe id="previewFrame" src="../../index.php" class="w-full h-[1200px] border rounded shadow-sm"></iframe>
</div>

     
    </div>
  </div>
</main>

</div>
</div>

<div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center w-full h-full">
        <div class="bg-white rounded-xl p-6 w-80 text-center relative">
            <button id="closeSuccessModal" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 material-icons">close</button>
            <span id="successIcon" class="material-icons text-4xl mb-2"></span>
            <p id="successMessage" class="text-gray-700 font-medium"></p>
            <button id="okSuccessBtn" class="mt-4 bg-emerald-500 text-white px-4 py-2 rounded hover:bg-emerald-600">OK</button>
        </div>
    </div>
</div>

<script>
const phpModalType = '<?= $modalType ?>';
const phpModalMessage = '<?= addslashes($modalMessage) ?>';

document.querySelectorAll(".accordion-btn").forEach(btn=>{
  btn.addEventListener("click",()=>{
    const body = btn.nextElementSibling;
    const icon = btn.querySelector("i");
    body.classList.toggle("hidden");
    icon.classList.toggle("rotate-180");
  });
});

const successModal = document.getElementById('successModal');
const successMessageEl = document.getElementById('successMessage');
const successIcon = document.getElementById('successIcon');
const closeSuccessModal = document.getElementById('closeSuccessModal');
const okSuccessBtn = document.getElementById('okSuccessBtn');

const settingsForm = document.getElementById('settingsForm');

const initialFormData = new FormData(settingsForm);
function formDataEquals(fd1, fd2) {
  if([...fd1].length !== [...fd2].length) return false;
  for (let [k,v] of fd1) if(fd2.get(k)!==v) return false;
  return true;
}

function showModal(message,type){
  successMessageEl.textContent = message;
  successIcon.textContent = type==='success'?'check_circle':type==='error'?'error':'info';
  successIcon.classList.remove('text-emerald-500','text-red-500','text-gray-500');
  successIcon.classList.add(type==='success'?'text-emerald-500':type==='error'?'text-red-500':'text-gray-500');
  successModal.classList.remove('hidden');
}

settingsForm.addEventListener('submit', ev=>{
  ev.preventDefault();
  const currentData = new FormData(settingsForm);
  if(formDataEquals(initialFormData,currentData)){
    showModal('No changes detected','info');
    return;
  }
  fetch('save_settings.php',{method:'POST',body:currentData})
    .then(r=>r.json())
    .then(res=>{
      if(res.success){
        showModal('Settings saved successfully!','success');
        for(let [k,v] of currentData) initialFormData.set(k,v);
      }else showModal(res.error||'Save failed','error');
    });
});

closeSuccessModal.addEventListener('click',()=>successModal.classList.add('hidden'));
okSuccessBtn.addEventListener('click',()=>successModal.classList.add('hidden'));

document.getElementById('heroUpload').addEventListener('change', e=>{
  const files=e.target.files;
  if(!files.length) return;
  const fd=new FormData();
  for(let f of files) fd.append('files[]',f);
  fd.append('action','upload');
  fetch('hero_actions.php',{method:'POST',body:fd}).then(r=>r.json()).then(res=>res.success?location.reload():showModal(res.error||'Upload failed','error'));
});

document.addEventListener('click', e=>{
  if(e.target.matches('.btn-delete')){
    const id=e.target.dataset.id;
    if(!confirm('Delete this hero image?')) return;
    const fd=new FormData();
    fd.append('action','delete');
    fd.append('id',id);
    fetch('hero_actions.php',{method:'POST',body:fd}).then(r=>r.json()).then(res=>res.success?location.reload():showModal(res.error||'Delete failed','error'));
  }
});

const heroList=document.getElementById('heroList');
let dragEl=null;
heroList.addEventListener('dragstart', e=>{dragEl=e.target;e.target.classList.add('dragging');});
heroList.addEventListener('dragend', e=>{e.target.classList.remove('dragging');saveOrder();});
heroList.addEventListener('dragover', e=>{
  e.preventDefault();
  const after=getDragAfterElement(heroList,e.clientX,e.clientY);
  const dragging=document.querySelector('.dragging');
  after?heroList.insertBefore(dragging,after):heroList.appendChild(dragging);
});

function getDragAfterElement(container,x,y){
  return [...container.querySelectorAll('.hero-item:not(.dragging)')]
    .reduce((closest,child)=>{
      const box=child.getBoundingClientRect();
      const offset=y-box.top-box.height/2;
      return (offset<0&&offset>closest.offset)?{offset:offset,element:child}:closest;
    },{offset:Number.NEGATIVE_INFINITY}).element;
}

function saveOrder(){
  const data=[...heroList.querySelectorAll('.hero-item')].map((it,idx)=>({id:it.dataset.id,order:idx}));
  const fd=new FormData();
  fd.append('action','reorder');
  fd.append('data',JSON.stringify(data));
  fetch('hero_actions.php',{method:'POST',body:fd}).then(r=>r.json()).then(res=>res.success?location.reload():showModal(res.error||'Failed saving order','error'));
}

document.getElementById('applyPreview').addEventListener('click',()=>{
  const speed=document.getElementById('sliderSpeed').value;
  const opacity=document.getElementById('overlayOpacity').value;
  const src=new URL(previewFrame.src,location.href);
  src.searchParams.set('hero_speed',speed);
  src.searchParams.set('hero_opacity',opacity);
  previewFrame.src=src.toString();
});

document.getElementById('savePreviewSettings').addEventListener('click',()=>{
  const fd=new FormData(document.getElementById('settingsForm'));
  fd.append('save_preview','1');
  fetch('save_settings.php',{method:'POST',body:fd}).then(r=>r.json()).then(res=>res.success?location.reload():showModal(res.error||'Save failed','error'));
});
</script>

</body>
</html>
