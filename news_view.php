<?php
include 'db.php';
if (!isset($_GET['id'])) {
  header("Location: news.php");
  exit();
}
$id = intval($_GET['id']);

$settings = $conn->query("SELECT * FROM system_settings WHERE id=1")->fetch_assoc();
$themeColor = $settings['theme_color'] ?? '#0f6b35';
$systemLogo = 'user/' . ($settings['system_logo'] ?? 'default_logo.png');

$news_updates = $conn->query("SELECT a.*, u.username FROM news_updates a JOIN users u ON a.user_id=u.id WHERE a.id=$id")->fetch_assoc();

$images = $conn->query("SELECT image_path FROM news_images WHERE news_id=$id");
$newsImages = [];
while ($img = $images->fetch_assoc()) {
  $newsImages[] = 'user/' . $img['image_path'];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= htmlspecialchars($news_updates['title']) ?> — <?= htmlspecialchars($settings['barangay_name']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap">
  <style>
    :root{--brand:<?= htmlspecialchars($themeColor) ?>}
    body{font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial;background:#f8fafc;color:#0f172a}
    .brand{background:var(--brand)}
    .brand-text{color:var(--brand)}
    .hero-img{height:420px;object-fit:cover;border-radius:.5rem}
    .gallery-grid img{height:200px;object-fit:cover;border-radius:.5rem}
    .lightbox{position:fixed;inset:0;background:rgba(0,0,0,.75);display:none;align-items:center;justify-content:center;z-index:60}
    .lightbox img{max-width:95%;max-height:90%;border-radius:12px}
  </style>
</head>
<body class="min-h-screen">

  <header class="brand sticky top-0 z-40">
    <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
      <a href="index.php" class="flex items-center gap-4">
        <img src="<?= htmlspecialchars($systemLogo) ?>" alt="logo" class="w-12 h-12 rounded-md object-cover border-2 border-white bg-white p-1 shadow-sm">
        <div class="text-white">
          <div class="text-lg font-bold"><?= htmlspecialchars($settings['barangay_name']) ?></div>
          <div class="text-sm opacity-90">Announcements</div>
        </div>
      </a>
      <nav class="flex gap-6">
        <a href="index.php" class="text-white">Home</a>
        <a href="news.php" class="text-white">News</a>
      </nav>
    </div>
  </header>

  <main class="max-w-4xl mx-auto px-6 py-12">
    <a href="news.php" class="inline-flex items-center gap-3 px-4 py-2 rounded-lg text-white" style="background:var(--brand)">← Back to News</a>

    <article class="bg-white rounded-2xl shadow-lg p-8 mt-6" data-aos="fade-up">
      <header class="mb-6">
        <h1 class="text-3xl font-extrabold text-gray-900 mb-2"><?= htmlspecialchars($news_updates['title']) ?></h1>
        <div class="text-sm text-gray-500">Published <?= date("F d, Y", strtotime($news_updates['created_at'])) ?> • <?= htmlspecialchars($news_updates['username']) ?></div>
      </header>

      <?php if(count($newsImages) > 0): ?>
        <?php if(count($newsImages) === 1): ?>
          <img src="<?= htmlspecialchars($newsImages[0]) ?>" alt="" class="w-full hero-img mb-6 rounded-lg shadow-sm">
        <?php else: ?>
          <div class="grid grid-cols-2 gap-4 gallery-grid mb-6">
            <?php foreach($newsImages as $img): ?>
              <button data-img="<?= htmlspecialchars($img) ?>" class="block overflow-hidden rounded-lg">
                <img src="<?= htmlspecialchars($img) ?>" alt="" class="w-full h-48 object-cover transition-transform hover:scale-105">
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <div class="prose max-w-none"><?= $news_updates['content'] ?></div>

      <div class="mt-8 flex items-center justify-between">
        <div class="text-sm text-gray-500">© <?= date('Y') ?> <?= htmlspecialchars($settings['barangay_name']) ?></div>
    
      </div>
    </article>
  </main>

  <div id="lightbox" class="lightbox" aria-hidden="true"><img src="" alt=""></div>

  <footer class="mt-16">
    <div class="text-center py-8 text-white brand">© <?= date('Y') ?> <?= htmlspecialchars($settings['barangay_name']) ?> — All rights reserved.</div>
  </footer>

  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
  <script>
    AOS.init({duration:700,once:true});
    const lb=document.getElementById('lightbox');
    const lbImg=lb.querySelector('img');
    document.querySelectorAll('button[data-img]').forEach(btn=>{
      btn.addEventListener('click',()=>{lbImg.src=btn.getAttribute('data-img');lb.style.display='flex';lb.setAttribute('aria-hidden','false');});
    });
    lb.addEventListener('click',()=>{lb.style.display='none';lb.setAttribute('aria-hidden','true');lbImg.src='';});
  </script>
</body>
</html>
<?php $conn->close(); ?>
