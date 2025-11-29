<?php
include 'db.php';
$settings = $conn->query("SELECT * FROM system_settings WHERE id=1")->fetch_assoc();
$themeColor = $settings['theme_color'] ?? '#0f6b35';
$systemLogo = 'user/' . ($settings['system_logo'] ?? 'default_logo.png');

$news = $conn->query("
  SELECT n.id, n.title, n.content, n.created_at, u.username
  FROM news_updates n
  JOIN users u ON n.user_id = u.id
  ORDER BY n.created_at DESC
");

$newsImages = [];
$imgQuery = $conn->query("SELECT * FROM news_images");
while ($img = $imgQuery->fetch_assoc()) {
  $newsImages[$img['news_id']][] = $img['image_path'];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>News & Updates — <?= htmlspecialchars($settings['barangay_name']) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap">
<style>
:root{--brand:<?= htmlspecialchars($themeColor) ?>}
body{font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial}
.brand{background:var(--brand)}
.brand-text{color:var(--brand)}
.accent{border-left-color:var(--brand)}
.card-media{height:240px;object-fit:cover}
.clamp-3{display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.lightbox{position:fixed;inset:0;background:rgba(0,0,0,.7);display:none;align-items:center;justify-content:center;z-index:60}
.lightbox img{max-width:95%;max-height:90%;border-radius:.5rem}
.no-results{background:#fff;padding:3rem;text-align:center;border-radius:1rem;color:#555;font-size:1.125rem;box-shadow:0 4px 6px rgba(0,0,0,.1);}
</style>
</head>
<body class="bg-gray-50 min-h-screen text-gray-800">
<header class="brand sticky top-0 z-40">
<div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
<a href="index.php" class="flex items-center gap-4">
<img src="<?= htmlspecialchars($systemLogo) ?>" alt="logo" class="w-12 h-12 rounded-md object-cover border-2 border-white bg-white p-1 shadow-sm">
<div class="text-white">
<div class="text-lg font-bold"><?= htmlspecialchars($settings['barangay_name']) ?></div>
<div class="text-sm opacity-90">Announcements</div>
</div>
</a>
<nav class="flex items-center gap-6">
<a href="index.php" class="text-white font-medium hover:underline">Home</a>
<a href="news.php" class="text-white font-semibold underline">News</a>
</nav>
</div>
</header>

<main class="max-w-7xl mx-auto px-6 py-10">
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
<h1 class="text-3xl font-extrabold text-gray-900">Latest News</h1>
<div class="w-full md:w-1/3">
<input id="searchInput" type="search" placeholder="Search news..." class="w-full px-4 py-2 rounded-lg border focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-[color:var(--brand)]">
</div>
</div>

<section class="grid grid-cols-1 md:grid-cols-3 gap-6">
<div class="md:col-span-2 space-y-6" id="newsContainer">
<?php if($news->num_rows > 0): ?>
  <?php while($row = $news->fetch_assoc()):
    $imgPath = isset($newsImages[$row['id']]) ? 'user/' . $newsImages[$row['id']][0] : null;
  ?>
    <article data-aos="fade-up" class="bg-white rounded-2xl shadow-sm overflow-hidden border-l-4 accent">
      <?php if($imgPath): ?>
        <button data-img="<?= htmlspecialchars($imgPath) ?>" class="w-full block">
          <img src="<?= htmlspecialchars($imgPath) ?>" alt="<?= htmlspecialchars($row['title']) ?>" class="card-media w-full">
        </button>
      <?php else: ?>
        <div class="w-full h-60 bg-gradient-to-b from-gray-200 to-gray-300"></div>
      <?php endif; ?>

      <div class="p-6">
        <div class="flex items-center justify-between mb-2">
          <div class="text-sm text-gray-500"><?= date("M d, Y", strtotime($row['created_at'])) ?></div>
          <div class="text-sm text-gray-500">By <?= htmlspecialchars($row['username']) ?></div>
        </div>
        <h2 class="text-xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($row['title']) ?></h2>
        <div class="text-gray-600 clamp-3"><?= strip_tags(substr($row['content'],0,450), '<p><br><strong><em><ul><ol><li>') ?></div>
        <div class="mt-4 flex items-center justify-between">
          <a href="news_view.php?id=<?= $row['id'] ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-white" style="background:var(--brand)">Read More
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
          </a>
        </div>
      </div>
    </article>
  <?php endwhile; ?>
<?php else: ?>
  <div class="no-results">No news & updates yet.</div>
<?php endif; ?>
</div>

<aside class="space-y-6">
<div class="bg-white rounded-2xl shadow-sm p-5">
<h3 class="text-lg font-semibold mb-3">Recent</h3>
<?php
$recent = $conn->query("SELECT id,title,created_at FROM news_updates ORDER BY created_at DESC LIMIT 6");
while($r = $recent->fetch_assoc()):
$rImg = isset($newsImages[$r['id']]) ? 'user/' . $newsImages[$r['id']][0] : null;
?>
<a href="news_view.php?id=<?= $r['id'] ?>" class="flex items-start gap-3 p-2 rounded hover:bg-gray-50 transition">
<?php if($rImg): ?>
<img src="<?= htmlspecialchars($rImg) ?>" alt="" class="w-16 h-12 object-cover rounded">
<?php else: ?>
<div class="w-16 h-12 bg-gray-200 rounded"></div>
<?php endif; ?>
<div class="flex-1">
<div class="text-sm font-medium text-gray-800"><?= htmlspecialchars($r['title']) ?></div>
<div class="text-xs text-gray-400"><?= date("M d, Y", strtotime($r['created_at'])) ?></div>
</div>
</a>
<?php endwhile; ?>
</div>

<div class="bg-white rounded-2xl shadow-sm p-5">
<h3 class="text-lg font-semibold mb-3">Contact</h3>
<div class="text-sm text-gray-600 space-y-1">
<div><strong><?= htmlspecialchars($settings['barangay_name']) ?></strong></div>
<?php if(!empty($settings['system_email'])): ?><div>Email: <a href="mailto:<?= htmlspecialchars($settings['system_email']) ?>" class="brand-text"><?= htmlspecialchars($settings['system_email']) ?></a></div><?php endif; ?>
<?php if(!empty($settings['contact_number'])): ?><div>Phone: <?= htmlspecialchars($settings['contact_number']) ?></div><?php endif; ?>
</div>
</div>

<div class="bg-white rounded-2xl shadow-sm p-5 text-sm text-gray-500">
<div class="font-semibold text-gray-800 mb-2">About</div>
<div>Official announcements and updates from <?= htmlspecialchars($settings['barangay_name']) ?>.</div>
</div>
</aside>
</section>
</main>

<div id="lightbox" class="lightbox" aria-hidden="true"><img src="" alt="preview"></div>

<footer class="mt-12">
<div class="max-w-7xl mx-auto px-6 py-8 text-center text-gray-500">© <?= date('Y') ?> <?= htmlspecialchars($settings['barangay_name']) ?>. All rights reserved.</div>
</footer>

<script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
<script>
AOS.init({duration:700,once:true});
const lb = document.getElementById('lightbox');
const lbImg = lb.querySelector('img');
document.querySelectorAll('article button[data-img]').forEach(btn=>{
  btn.addEventListener('click',()=>{
    lbImg.src = btn.getAttribute('data-img');
    lb.style.display='flex';
    lb.setAttribute('aria-hidden','false');
  });
});
lb.addEventListener('click',()=>{
  lb.style.display='none';
  lb.setAttribute('aria-hidden','true');
  lbImg.src='';
});
const searchInput = document.getElementById('searchInput');
const newsContainer = document.getElementById('newsContainer');
searchInput.addEventListener('input',()=>{
  const query = searchInput.value.toLowerCase().trim();
  let found = false;
  document.querySelectorAll('article').forEach(article=>{
    const title = article.querySelector('h2')?.innerText.toLowerCase() || '';
    const content = article.querySelector('div.clamp-3')?.innerText.toLowerCase() || '';
    if(title.includes(query) || content.includes(query)){
      article.style.display='';
      found=true;
    }else{
      article.style.display='none';
    }
  });
  if(!found){
    if(!document.getElementById('noResults')){
      const div = document.createElement('div');
      div.id='noResults';
      div.className='no-results';
      div.innerText='No news found';
      newsContainer.appendChild(div);
    }
  }else{
    const noRes = document.getElementById('noResults');
    if(noRes) noRes.remove();
  }
});
</script>
</body>
</html>
