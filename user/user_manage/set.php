<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../../login.php");
    exit();
}
include '../db.php';

$settings = $conn->query("SELECT * FROM system_settings WHERE id=1")->fetch_assoc();

$heroImages = [];
$heroQ = $conn->query("SELECT id, image_path, display_order FROM landing_hero_images ORDER BY display_order ASC, created_at DESC");
while ($r = $heroQ->fetch_assoc()) $heroImages[] = $r;

function urlFor($path) {
    if (!$path) return '';
    return '../' . ltrim($path,'/');
}


?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>System Settings</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
.dragging { opacity:.5; }
.hero-thumb { width:120px;height:70px;object-fit:cover;border-radius:8px; }
.grid-hero { display:flex;gap:12px;flex-wrap:wrap; }
.hero-item { display:flex;gap:8px;align-items:center;padding:8px;background:#fff;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,.06); }
.handle { cursor:grab;padding:6px; }
.accordion-body { transition: all 0.3s ease; }
.rotate-180 { transform: rotate(180deg); }
</style>
</head>
<body class="bg-gray-100 p-6">
    
<div class="max-w-7xl mx-auto grid grid-cols-3 gap-6">
  <div class="col-span-2 bg-white p-6 rounded-xl shadow-lg">
    <h1 class="text-2xl font-bold mb-4">Barangay Branding & Personalization</h1>

    <form id="settingsForm" method="POST" action="save_settings.php" enctype="multipart/form-data" class="space-y-4">
      <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block mb-1 font-semibold">Barangay Name</label>
                <input type="text" name="barangay_name" value="<?= htmlspecialchars($settings['barangay_name']) ?>" class="p-3 border rounded w-full">
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4 mt-4">
            <div>
                <label class="block mb-1 font-semibold">Municipality</label>
                <input type="text" name="municipality" value="<?= htmlspecialchars($settings['municipality']) ?>" class="p-3 border rounded w-full">
            </div>

            <div>
                <label class="block mb-1 font-semibold">Province</label>
                <input type="text" name="province" value="<?= htmlspecialchars($settings['province']) ?>" class="p-3 border rounded w-full">
            </div>

            <div>
                <label class="block mb-1 font-semibold">Country</label>
                <input type="text" name="country" value="<?= htmlspecialchars($settings['country']) ?>" class="p-3 border rounded w-full">
            </div>
        </div>

        <div class="mt-4">
            <label class="block mb-1 font-semibold">Barangay Address</label>
            <input type="text" name="barangay_address" value="<?= htmlspecialchars($settings['barangay_address']) ?>" class="w-full p-3 border rounded">
        </div>

      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-semibold mb-1">Logo</label>
          <input type="file" name="system_logo">
          <?php if(!empty($settings['system_logo'])): ?>
            <img src="<?= urlFor($settings['system_logo']) ?>" class="mt-2 h-16">
          <?php endif; ?>
        </div>
        <div>
          <label class="block text-sm font-semibold mb-1">Theme Color</label>
          <input type="color" name="theme_color" value="<?= htmlspecialchars($settings['theme_color'] ?? '#0f6b35') ?>" class="w-24 h-12 p-1 border rounded">
        </div>
      </div>

      <div class="bg-gray-50 border rounded-lg overflow-hidden">
        <button type="button" class="w-full flex justify-between items-center px-4 py-3 bg-white font-semibold text-left accordion-btn">
          Email & SMTP Settings
          <i class="fa-solid fa-chevron-down transition-all"></i>
        </button>
        <div class="accordion-body hidden px-4 pb-4 pt-2">
          <div class="grid grid-cols-2 gap-4 mt-2">
            <input type="email" name="system_email" placeholder="System Email" value="<?= htmlspecialchars($settings['system_email']) ?>" class="p-3 border rounded">
            <input type="text" name="contact_number" placeholder="Contact Number" value="<?= htmlspecialchars($settings['contact_number']) ?>" class="p-3 border rounded">
          </div>
          <div class="grid grid-cols-3 gap-4 mt-3">
            <input type="text" name="smtp_host" placeholder="SMTP Host" value="<?= htmlspecialchars($settings['smtp_host']) ?>" class="p-3 border rounded">
            <input type="number" name="smtp_port" placeholder="SMTP Port" value="<?= htmlspecialchars($settings['smtp_port']) ?>" class="p-3 border rounded">
            <select name="smtp_encryption" class="p-3 border rounded">
              <option value="tls" <?= ($settings['smtp_encryption']=='tls')?'selected':'' ?>>TLS</option>
              <option value="ssl" <?= ($settings['smtp_encryption']=='ssl')?'selected':'' ?>>SSL</option>
            </select>
          </div>
          <input type="password" name="app_password" placeholder="App Password" value="<?= htmlspecialchars($settings['app_password']) ?>" class="p-3 border rounded w-full mt-3">
        </div>
      </div>

      <button class="bg-green-600 text-white px-6 py-2 rounded" type="submit">Save Settings</button>
    </form>

    <hr class="my-6">

    <h2 class="text-lg font-semibold mb-3">Hero Images Manager</h2>
    <div class="flex gap-4 items-start">
      <div>
        <input id="heroUpload" type="file" accept="image/*" multiple>
        <div class="mt-3 grid-hero" id="heroList">
          <?php foreach($heroImages as $img): ?>
            <div class="hero-item" data-id="<?= $img['id'] ?>" draggable="true">
              <div class="handle"><i class="fas fa-grip-lines"></i></div>
              <img src="<?= urlFor($img['image_path']) ?>" class="hero-thumb">
              <div style="display:flex;flex-direction:column;gap:6px">
                <button class="btn-delete text-red-600" data-id="<?= $img['id'] ?>">Delete</button>
                <small class="text-gray-500">Order: <?= intval($img['display_order']) ?></small>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="bg-white p-4 rounded-xl shadow-lg">
    <h2 class="text-lg font-bold mb-3">Live Preview</h2>
    <iframe id="previewFrame" src="../../index.php" class="w-full h-[720px] border rounded"></iframe>
    <div class="mt-4">
      <label class="block text-sm font-semibold mb-1">Hero slider speed (ms)</label>
      <input id="sliderSpeed" type="number" min="1000" value="5000" class="p-2 border rounded w-full">
      <label class="block text-sm font-semibold mt-3 mb-1">Hero overlay opacity (0-1)</label>
      <input id="overlayOpacity" type="number" step="0.1" min="0" max="1" value="0.35" class="p-2 border rounded w-full">
      <div class="flex gap-2 mt-3">
        <button id="applyPreview" class="bg-blue-600 text-white px-3 py-2 rounded">Apply to Preview</button>
        <button id="savePreviewSettings" class="bg-green-600 text-white px-3 py-2 rounded">Save preview settings</button>
      </div>
    </div>
  </div>
</div>

<script>
// Accordion
document.querySelectorAll(".accordion-btn").forEach(btn=>{
  btn.addEventListener("click",()=>{
    const body = btn.nextElementSibling;
    const icon = btn.querySelector("i");
    body.classList.toggle("hidden");
    icon.classList.toggle("rotate-180");
  });
});

// Form submit
document.getElementById('settingsForm').addEventListener('submit', ev=>{
  ev.preventDefault();
  const fd = new FormData(ev.target);
  fetch('save_settings.php',{method:'POST',body:fd})
    .then(r=>r.json())
    .then(res=>res.success?location.reload():alert(res.error||'Save failed'));
});

// Hero upload
document.getElementById('heroUpload').addEventListener('change', e=>{
  const files=e.target.files; if(!files.length) return;
  const fd=new FormData(); for(let f of files) fd.append('files[]',f); fd.append('action','upload');
  fetch('hero_actions.php',{method:'POST',body:fd}).then(r=>r.json()).then(res=>res.success?location.reload():alert(res.error||'Upload failed'));
});

// Hero delete
document.addEventListener('click', e=>{
  if(e.target.matches('.btn-delete')){
    const id=e.target.dataset.id; if(!confirm('Delete this hero image?')) return;
    const fd=new FormData(); fd.append('action','delete'); fd.append('id',id);
    fetch('hero_actions.php',{method:'POST',body:fd}).then(r=>r.json()).then(res=>res.success?location.reload():alert(res.error||'Delete failed'));
  }
});

// Drag & drop reorder
const heroList=document.getElementById('heroList'); let dragEl=null;
heroList.addEventListener('dragstart', e=>{ dragEl=e.target; e.target.classList.add('dragging'); });
heroList.addEventListener('dragend', e=>{ e.target.classList.remove('dragging'); saveOrder(); });
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
      return (offset<0 && offset>closest.offset)?{offset:offset,element:child}:closest;
    },{offset:Number.NEGATIVE_INFINITY}).element;
}
function saveOrder(){
  const data=[...heroList.querySelectorAll('.hero-item')].map((it,idx)=>({id:it.dataset.id,order:idx}));
  const fd=new FormData(); fd.append('action','reorder'); fd.append('data',JSON.stringify(data));
  fetch('hero_actions.php',{method:'POST',body:fd}).then(r=>r.json()).then(res=>{ if(!res.success) alert(res.error||'Failed saving order'); else location.reload(); });
}

// Preview apply
document.getElementById('applyPreview').addEventListener('click', ()=>{
  const speed=document.getElementById('sliderSpeed').value;
  const opacity=document.getElementById('overlayOpacity').value;
  const src=new URL(previewFrame.src,location.href);
  src.searchParams.set('hero_speed',speed); src.searchParams.set('hero_opacity',opacity);
  previewFrame.src=src.toString();
});

// Save preview settings
document.getElementById('savePreviewSettings').addEventListener('click', ()=>{
  const fd=new FormData(document.getElementById('settingsForm')); fd.append('save_preview','1');
  fetch('save_settings.php',{method:'POST',body:fd}).then(r=>r.json()).then(res=>res.success?location.reload():alert(res.error||'Save failed'));
});
</script>
</body>
</html>
