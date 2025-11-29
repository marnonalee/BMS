<?php
include 'db.php';
$settings = $conn->query("SELECT * FROM system_settings WHERE id=1")->fetch_assoc();
$themeColor = $settings['theme_color'] ?? '#007bff';

$heroImages = [];
$heroQuery = $conn->query("SELECT image_path FROM landing_hero_images ORDER BY created_at DESC");
while ($row = $heroQuery->fetch_assoc()) {
    $path = 'user/'.$row['image_path'];
    if (file_exists(__DIR__.'/'.$path)) $heroImages[] = $path;
}

$sql = "SELECT bo.official_id, r.first_name, r.last_name, bo.photo, p.position_name
        FROM barangay_officials bo
        JOIN residents r ON bo.resident_id=r.resident_id
        JOIN positions p ON bo.position_id=p.id
        ORDER BY bo.position_id ASC";
$result = $conn->query($sql);

$news_updates = $conn->query("
    SELECT a.id, a.content, a.created_at, u.username, a.title
    FROM news_updates a
    JOIN users u ON a.user_id=u.id
    ORDER BY a.created_at DESC
");

$newsImage = [];
$imgQuery = $conn->query("SELECT * FROM news_images");
while ($img = $imgQuery->fetch_assoc()) {
    $newsImage[$img['news_id']][] = 'user/'.$img['image_path'];
}

$announcement = $conn->query("
    SELECT a.id, a.title, a.content, a.created_at, u.username
    FROM announcements a
    JOIN users u ON a.user_id=u.id
    ORDER BY a.created_at DESC
    LIMIT 1
")->fetch_assoc();

$announcementImages = [];
if ($announcement) {
    $imgQuery = $conn->query("SELECT * FROM announcement_images WHERE announcement_id=".$announcement['id']);
    while ($img = $imgQuery->fetch_assoc()) {
        $announcementImages[] = 'user/'.$img['image_path'];
    }
}

$totalResidents = $conn->query("SELECT COUNT(*) AS total FROM residents WHERE is_archived=0")->fetch_assoc()['total'];
$registeredVoters = $conn->query("SELECT COUNT(*) AS total FROM residents WHERE voter_status='Registered' AND is_archived=0")->fetch_assoc()['total'];
$femaleResidents = $conn->query("SELECT COUNT(*) AS total FROM residents WHERE sex='Female' AND is_archived=0")->fetch_assoc()['total'];
$maleResidents = $conn->query("SELECT COUNT(*) AS total FROM residents WHERE sex='Male' AND is_archived=0")->fetch_assoc()['total'];
$familyHeads = $conn->query("SELECT COUNT(*) AS total FROM residents WHERE is_family_head=1 AND is_archived=0")->fetch_assoc()['total'];

$systemLogo = 'user_manage/'.($settings['system_logo'] ?? 'default_logo.png');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($settings['barangay_name']) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
html,body{scroll-behavior:smooth}
.counter{font-variant-numeric:tabular-nums}
#heroSection{position:relative;height:100vh;overflow:hidden;display:flex;align-items:center;justify-content:center;color:white}
.hero-slide{position:absolute;top:0;left:100%;width:100%;height:100%;background-size:cover;background-position:center;opacity:0;transition:1s;transform:scale(1.05);z-index:0}
.hero-slide.active{left:0;opacity:1;transform:scale(1);z-index:2}
.hero-content{position:relative;z-index:2;text-align:center}
.scroll-indicator{position:absolute;bottom:20px;left:50%;transform:translateX(-50%);animation:bounce 1.5s infinite;font-size:2rem;color:white}
@keyframes bounce{0%,100%{transform:translateX(-50%) translateY(0)}50%{transform:translateX(-50%) translateY(15px)}}
#officialsSlider{scroll-behavior:smooth;overflow-x:auto;display:flex;gap:1rem;padding:2rem 0;cursor:grab}
#officialsSlider::-webkit-scrollbar{display:none}
.official-card{flex:none;width:16rem;background:white;border-radius:1rem;overflow:hidden;transition:.3s;cursor:pointer;perspective:1000px}
.official-card:hover{transform:scale(1.05);box-shadow:0 20px 30px rgba(0,0,0,.2)}
.official-card img{width:100%;height:16rem;object-fit:cover;transition:.3s}
.official-card:hover img{transform:scale(1.1)}
.update-card{background:linear-gradient(135deg,#f0f4f8,#e2e8f0);border-radius:1rem;overflow:hidden;transition:.3s;cursor:pointer}
.update-card:hover{transform:scale(1.03);box-shadow:0 15px 25px rgba(0,0,0,.15)}
.update-card img{width:100%;height:18rem;object-fit:cover;transition:.3s}
.update-card:hover img{transform:scale(1.1)}
.fade-in{opacity:0;transform:translateY(20px);transition:.8s}
.fade-in.show{opacity:1;transform:translateY(0)}
</style>
</head>

<body class="font-sans antialiased bg-gray-50">

<section id="heroSection" class="relative">
    <div class="absolute inset-0 bg-black bg-opacity-40 z-10"></div>

    <?php foreach($heroImages as $i=>$img): ?>
        <div class="hero-slide <?= $i===0?'active':'' ?>" style="background-image:url('<?= $img ?>')"></div>
    <?php endforeach; ?>

    <div class="hero-content space-y-6 z-20 relative">
        <h1 class="text-5xl sm:text-6xl md:text-7xl font-extrabold text-white drop-shadow-2xl">
            <?= htmlspecialchars($settings['barangay_name']) ?>
        </h1>
    <div>
        <a href="login.php"
           class="mt-6 px-8 py-4 rounded-full text-white font-semibold transition-transform transform hover:scale-105 hover:shadow-lg"
           style="background-color:<?= $themeColor ?>">
           Register Now
        </a>
    </div>
    </div>

    <div class="scroll-indicator z-20 relative">
        <i class="fas fa-chevron-down text-white"></i>
    </div>
</section>


<section class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6 p-10 fade-in">
<?php
$cards=[
['fa-users',$totalResidents,'Total Residents'],
['fa-vote-yea',$registeredVoters,'Registered Voters'],
['fa-female',$femaleResidents,'Female Residents'],
['fa-male',$maleResidents,'Male Residents'],
['fa-house-user',$familyHeads,'Households']
];
foreach($cards as $c){
echo "
<div class='group bg-gradient-to-br from-white to-gray-100 rounded-2xl shadow-lg p-6 flex flex-col items-center transition-transform transform hover:scale-105 hover:shadow-2xl'>
<i class='fas $c[0] text-3xl mb-2 group-hover:animate-bounce' style='color:$themeColor'></i>
<h2 class='text-2xl font-bold counter' data-target='$c[1]'>0</h2>
<p>$c[2]</p>
</div>
";
}
?>
</section>

<section id="officialsSlider" class="fade-in">
<?php
if($result->num_rows>0){
    while($row=$result->fetch_assoc()){
        $fullName=$row['first_name'].' '.$row['last_name'];
        $photoPath="user/uploads/".$row['photo'];
        $photo=(file_exists($photoPath)&&!empty($row['photo']))?$photoPath:"img/official.jpg";
        echo "
        <div class='official-card text-center p-4'>
            <img src='$photo'>
            <h3 class='font-semibold text-lg mt-3'>$fullName</h3>
            <p class='text-gray-500'>{$row['position_name']}</p>
        </div>";
    }
} else echo "<p class='text-center w-full'>No officials found.</p>";
?>
</section>

<section class="py-20 bg-gray-100 fade-in">
    <div class="max-w-6xl mx-auto grid md:grid-cols-2 gap-16 text-center">
        <div class="bg-white p-10 rounded-2xl shadow-lg">
            <h2 class="text-3xl font-extrabold mb-5" style="color:<?= $themeColor ?>;">Bisyon</h2>
            <p class="text-lg text-gray-700 leading-relaxed"><?= htmlspecialchars($settings['bisyon'] ?? 'Bisyon not set') ?></p>
        </div>
        <div class="bg-white p-10 rounded-2xl shadow-lg">
            <h2 class="text-3xl font-extrabold mb-5" style="color:<?= $themeColor ?>;">Misyon</h2>
            <p class="text-lg text-gray-700 leading-relaxed"><?= htmlspecialchars($settings['misyon'] ?? 'Misyon not set') ?></p>
        </div>
    </div>
</section>

<section class="py-16 bg-white fade-in">
    <h2 class="text-center text-4xl font-extrabold mb-12">News Updates</h2>
    <div class="container mx-auto flex flex-wrap gap-8 justify-center">
        <?php
        $items=array_slice(iterator_to_array($news_updates),0,3);
        foreach($items as $row):
            $img=$newsImage[$row['id']][0]??'';
        ?>
        <div class="update-card flex-1 min-w-[300px] max-w-sm flex flex-col">
            <?php if($img): ?>
                <img src="<?= $img ?>">
            <?php else: ?>
                <div class="h-72 w-full bg-gray-300"></div>
            <?php endif; ?>
            <div class="p-6 flex flex-col justify-between flex-1">
                <div>
                    <p class="text-xl font-bold mb-2 text-gray-800"><?= htmlspecialchars($row['title']) ?></p>
                    <p class="text-gray-500 text-sm mb-4"><?= date("F d, Y",strtotime($row['created_at'])) ?></p>
                </div>
                <a href="news_view.php?id=<?= $row['id'] ?>" class="mt-auto px-4 py-2 text-white rounded font-semibold text-center" style="background-color:<?= $themeColor ?>">Read</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="py-16 bg-gray-100 fade-in">
    <div class="max-w-6xl mx-auto grid md:grid-cols-2 gap-12">
        <div class="w-full h-96 rounded-2xl overflow-hidden shadow-lg">
            <?php $mapQuery = urlencode($settings['barangay_address'].', '.$settings['municipality'].', '.$settings['province'].', '.$settings['country']); ?>
            <iframe src="https://www.google.com/maps?q=<?= $mapQuery ?>&output=embed" class="w-full h-full border-0 rounded-2xl" loading="lazy"></iframe>
        </div>
        <div class="flex flex-col gap-8">
            <div>
                <h3 class="text-2xl font-bold mb-3">Contact Us</h3>
                <p>📍 <?= htmlspecialchars($settings['barangay_address']) ?></p>
                <p>☎️ <?= htmlspecialchars($settings['contact_number']) ?></p>
                <p>📧 <?= htmlspecialchars($settings['system_email']) ?></p>
            </div>
            <div>
                <h3 class="text-2xl font-bold mb-3">Stay Connected</h3>
                <div class="flex gap-4 text-3xl">
                    <?php if(!empty($settings['facebook_link'])): ?>
                    <a href="<?= htmlspecialchars($settings['facebook_link']) ?>" target="_blank" style="color:<?= $themeColor ?>" class="hover:opacity-80">
                        <i class="fab fa-facebook-square"></i>
                    </a>
                    <?php endif;?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if($announcement): ?>
<div id="announcementModal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center hidden z-50">
    <div class="bg-white max-w-4xl w-full rounded-xl shadow-lg p-6 relative">
        <button id="closeModal" class="absolute top-3 right-3 text-gray-500 hover:text-black text-2xl">&times;</button>
        <h2 class="text-[22px] font-bold text-blue-900 mb-4"><?= htmlspecialchars($announcement['title']) ?></h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="text-gray-700 text-[14px]"><?= nl2br(strip_tags($announcement['content'])) ?></div>
            <?php if($announcementImages): ?>
            <div class="flex flex-col gap-2">
                <?php foreach($announcementImages as $i): ?>
                    <img src="<?= $i ?>" class="w-full h-72 object-cover rounded-lg shadow-sm">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="text-right mt-4">
            <button id="closeModal2" class="bg-blue-800 text-white px-4 py-2 rounded text-sm">Close</button>
        </div>
    </div>
</div>
<?php endif; ?>

<footer class="text-white text-center py-6" style="background-color:<?= $themeColor ?>">
    © <?= date('Y') ?> <?= htmlspecialchars($settings['barangay_name']) ?>. All Rights Reserved.
</footer>

<script>
const slides=document.querySelectorAll('.hero-slide');let current=0;
function showSlide(i){slides.forEach((s,x)=>{s.classList.remove('active');if(x===i)s.classList.add('active')});}
function nextSlide(){current=(current+1)%slides.length;showSlide(current);}
showSlide(current);setInterval(nextSlide,5000);

document.querySelectorAll('.counter').forEach(c=>{
    const upd=()=>{const t=+c.dataset.target,n=+c.innerText,i=t/200;if(n<t){c.innerText=Math.ceil(n+i);requestAnimationFrame(upd)}else c.innerText=t;}
    upd();
});

const obs=new IntersectionObserver(e=>{e.forEach(x=>{if(x.isIntersecting){x.target.classList.add('show');obs.unobserve(x.target)}})},{threshold:.2});
document.querySelectorAll('.fade-in').forEach(f=>obs.observe(f));

const slider=document.getElementById('officialsSlider');let down=false,start,scroll;
slider.onmousedown=e=>{down=true;start=e.pageX-slider.offsetLeft;scroll=slider.scrollLeft;slider.classList.add('cursor-grabbing')};
slider.onmouseleave=()=>{down=false;slider.classList.remove('cursor-grabbing')};
slider.onmouseup=()=>{down=false;slider.classList.remove('cursor-grabbing')};
slider.onmousemove=e=>{if(!down)return;e.preventDefault();const x=e.pageX-slider.offsetLeft;slider.scrollLeft=scroll-(x-start)*2};

document.querySelectorAll('.official-card,.update-card').forEach(c=>{
    c.onmousemove=e=>{const r=c.getBoundingClientRect(),x=e.clientX-r.left,y=e.clientY-r.top;c.style.transform=`rotateY(${(x-r.width/2)/15}deg) rotateX(${-(y-r.height/2)/15}deg) scale(1.05)`};
    c.onmouseleave=()=>c.style.transform='rotateY(0) rotateX(0) scale(1)';
});

document.addEventListener('DOMContentLoaded',()=>{
    const m=document.getElementById('announcementModal');if(m){m.classList.remove('hidden');const c=()=>m.classList.add('hidden');document.getElementById('closeModal').onclick=c;document.getElementById('closeModal2').onclick=c;m.onclick=e=>{if(e.target===m)c();}}
});
</script>
</body>
</html>
<?php $conn->close(); ?>
