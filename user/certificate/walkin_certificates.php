<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../../login.php");
    exit();
}
include '../db.php';
require('../../fpdf/fpdf.php');

$user_id = $_SESSION["user_id"];
$userQuery = $conn->query("SELECT * FROM users WHERE id = '$user_id'");
$user = $userQuery->fetch_assoc();
$role = $user['role'];

if(isset($_GET['search'])){
    $search = '%'.$_GET['search'].'%';
    $stmt = $conn->prepare("
        SELECT resident_id, first_name, last_name, civil_status, resident_address, age, 
               birthdate, sex, birth_place, profession_occupation, voter_status
        FROM residents 
        WHERE first_name LIKE ? OR last_name LIKE ? 
        ORDER BY first_name ASC 
        LIMIT 10
    ");
    $stmt->bind_param("ss", $search, $search);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    exit;
}

if(isset($_GET['child_search'])){
    $search = '%'.$_GET['child_search'].'%';
    $stmt = $conn->prepare("
        SELECT resident_id AS child_id, first_name, last_name, age, birthdate, birth_place, resident_address
        FROM residents
        WHERE (first_name LIKE ? OR last_name LIKE ?)
          AND age < 18
        ORDER BY first_name ASC
        LIMIT 10
    ");
    $stmt->bind_param("ss", $search, $search);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    exit;
}

if(isset($_POST['action']) && $_POST['action'] === 'generate_certificate'){
    $resident_id = intval($_POST['resident_id']);
    $template_id = intval($_POST['template_id']);
    $purpose = trim($_POST['purpose']);
    $birth_place = trim($_POST['birth_place']);
    $earnings = trim($_POST['earnings_per_month']);
    $child_id = !empty($_POST['child_id']) ? intval($_POST['child_id']) : NULL;
    $child_fullname = trim($_POST['child_fullname'] ?? '');
    $child_birthdate = trim($_POST['child_birthdate'] ?? NULL);
    $child_birthplace = trim($_POST['child_birthplace'] ?? '');
    $date_requested = date('Y-m-d H:i:s');

    $stmt1 = $conn->prepare("UPDATE residents SET birth_place = ? WHERE resident_id = ?");
    $stmt1->bind_param("si", $birth_place, $resident_id);
    $stmt1->execute();

    $stmt = $conn->prepare("
        INSERT INTO certificate_requests 
        (resident_id, template_id, purpose, child_id, child_fullname, child_birthdate, child_birthplace, request_type, earnings_per_month, status, date_requested) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Walk-in', ?, 'Done', ?)
    ");
    $stmt->bind_param("iisssssis", $resident_id, $template_id, $purpose, $child_id, $child_fullname, $child_birthdate, $child_birthplace, $earnings, $date_requested);

    if($stmt->execute()){
        $request_id = $stmt->insert_id;
        $resQuery = $conn->query("SELECT first_name, last_name FROM residents WHERE resident_id = $resident_id");
        $resident = $resQuery->fetch_assoc();

        $userFolder = "../user/generated_certificates/";
        if(!is_dir($userFolder)) mkdir($userFolder, 0777, true);

        $gcFolder = "../generated_certificates/";
        if(!is_dir($gcFolder)) mkdir($gcFolder, 0777, true);

        $fileName = "certificate_{$request_id}.pdf";
        $userFilePath = $userFolder . $fileName;
        $gcFilePath = $gcFolder . $fileName;

        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial','B',16);
        $pdf->Cell(0,10,'Certificate',0,1,'C');
        $pdf->Ln(10);
        $pdf->SetFont('Arial','',12);
        $pdf->MultiCell(0,8,"This is to certify that {$resident['first_name']} {$resident['last_name']} has requested a certificate for the purpose of: $purpose.");
        if(!empty($child_fullname)){
            $pdf->Ln(5);
            $pdf->MultiCell(0,8,"Child/Ward: $child_fullname, Birthdate: $child_birthdate, Birthplace: $child_birthplace");
        }
        $pdf->Ln(10);
        $pdf->Cell(0,8,"Date: ".date('F j, Y'),0,1,'L');
        $pdf->Output('F', $userFilePath);
        copy($userFilePath, $gcFilePath);

        $stmt2 = $conn->prepare("UPDATE certificate_requests SET generated_file = ? WHERE id = ?");
        $stmt2->bind_param("si", $fileName, $request_id);
        $stmt2->execute();

        $stmt3 = $conn->prepare("
            INSERT INTO generated_certificates 
            (request_id, resident_id, template_id, generated_file, generated_by) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt3->bind_param("iiiii", $request_id, $resident_id, $template_id, $fileName, $user_id);
        $stmt3->execute();

        $logAction = "Generate Certificate";
        $logDescription = "Generated certificate ID $request_id for resident ID $resident_id (Template ID: $template_id, Purpose: $purpose)";
        $logStmt = $conn->prepare("INSERT INTO log_activity (user_id, action, description) VALUES (?, ?, ?)");
        $logStmt->bind_param("iss", $user_id, $logAction, $logDescription);
        $logStmt->execute();

        echo json_encode(['success'=>true,'request_id'=>$request_id,'file'=>$fileName]);
    }else{
        echo json_encode(['success'=>false,'message'=>$stmt->error]);
    }
    exit;
}

$templates = $conn->query("SELECT id, template_name FROM certificate_templates WHERE template_for = 'Certificate' ORDER BY template_name ASC");
$recentRequests = $conn->query("SELECT cr.id, r.first_name, r.last_name, t.template_name, cr.purpose, cr.status, cr.date_requested, cr.generated_file
                                FROM certificate_requests cr 
                                JOIN residents r ON cr.resident_id = r.resident_id 
                                JOIN certificate_templates t ON cr.template_id = t.id 
                                WHERE cr.request_type = 'Walk-in' 
                                ORDER BY cr.date_requested DESC LIMIT 10");
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
<title>Walk-in Certificate</title>
<link rel="stylesheet" href="cert.css">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body class=" font-sans">
<div class="flex h-screen overflow-hidden">
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
            <a href="../requests/household_member_requests.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
                <span class="material-icons mr-3">group_add</span><span class="sidebar-text">Household Member Requests</span>
            </a>
            <a href="../requests/request_profile_update.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
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
      <a href="../certificate/walkin_certificates.php" class="flex items-center px-4 py-3 rounded-md bg-white/10 backdrop-blur-sm transition-all">
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
      <a href="../id/barangay_id_request.php" class="flex items-center px-4 py-3 rounded hover:bg-white/10 mt-1 transition-colors">
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
          <span class="material-icons mr-3">history</span><span class="sidebar-text">Activity Logs</span>
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

<div class="flex-1 flex flex-col bg-gray-50 overflow-hidden">

  <header class="flex-shrink-0 flex items-center justify-between bg-white shadow-md px-6 py-4 rounded-b-2xl mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Walk-in Certificates</h2>
  </header>

<main class="flex-1 overflow-y-auto p-6 bg-gray-50 space-y-8">
  <div class="bg-white rounded-xl shadow-md w-full max-w-[95%] mx-auto p-6 space-y-6">
    <div class="relative">
      <label for="resident_search" class="block mb-1 font-semibold text-gray-700">Resident</label>
      <input type="text" id="resident_search" placeholder="Type resident name..." autocomplete="off" class="w-full border-b-2 border-gray-300 focus:border-emerald-500 outline-none py-2 pl-10 rounded transition bg-transparent">
      <input type="hidden" id="resident_id">
      <div id="resident_dropdown" class="absolute w-full bg-white border mt-1 rounded shadow-lg z-50"></div>
    </div>
    <div id="resident_card" class="hidden bg-emerald-50 p-4 rounded-lg shadow flex items-center space-x-4 mt-2">
      <span class="material-icons text-emerald-600 text-3xl">person</span>
      <div>
        <p class="font-semibold" id="card_name"></p>
        <p class="text-sm text-gray-600" id="card_age_status"></p>
        <p class="text-sm text-gray-500" id="card_address"></p>
      </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div>
        <label class="block mb-1 font-medium">Full Name</label>
        <input type="text" id="resident_fullname" required class="w-full border-b-2 border-gray-300 focus:border-emerald-500 outline-none py-2 rounded bg-transparent">
      </div>
      <div>
        <label class="block mb-1 font-medium">Age</label>
        <input type="number" id="resident_age" readonly class="w-full border-b-2 border-gray-300 focus:border-emerald-500 outline-none py-2 rounded bg-transparent">
      </div>
      <div>
        <label class="block mb-1 font-medium">Civil Status</label>
        <input type="text" id="resident_status" required class="w-full border-b-2 border-gray-300 focus:border-emerald-500 outline-none py-2 rounded bg-transparent">
      </div>
      <div>
        <label class="block mb-1 font-medium">Resident Address</label>
        <input type="text" id="resident_address" required class="w-full border-b-2 border-gray-300 focus:border-emerald-500 outline-none py-2 rounded bg-transparent">
      </div>
      <div>
        <label class="block mb-1 font-medium">Birthplace</label>
        <input type="text" id="resident_birthplace" required class="w-full border-b-2 border-gray-300 focus:border-emerald-500 outline-none py-2 rounded bg-transparent">
      </div>
      <div>
        <label class="block mb-1 font-medium">Birthdate</label>
        <input type="date" id="resident_birthdate" required class="w-full border-b-2 border-gray-300 focus:border-emerald-500 outline-none py-2 rounded bg-transparent">
      </div>
      <div>
        <label class="block mb-1 font-medium">Sex</label>
        <input type="text" id="resident_sex" required class="w-full border-b-2 border-gray-300 focus:border-emerald-500 outline-none py-2 rounded bg-transparent">
      </div>
      <div>
        <label class="block mb-1 font-medium">Voter Status</label>
        <input type="text" id="resident_voter_status" required class="w-full border-b-2 border-gray-300 focus:border-emerald-500 outline-none py-2 rounded bg-transparent">
      </div>
    </div>
    <div>
      <label class="block mb-1 font-medium">Certificate Type</label>
      <select id="template_id" class="w-full border-b-2 border-gray-300 focus:border-emerald-500 outline-none py-2 rounded bg-transparent">
        <option value="">-- Select Certificate --</option>
        <?php while($t = $templates->fetch_assoc()): ?>
        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['template_name']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>
    <div>
      <label class="block mb-1 font-medium">Purpose</label>
      <input type="text" id="purpose" class="w-full border-b-2 border-gray-300 focus:border-emerald-500 outline-none py-2 rounded bg-transparent">
    </div>
    <div id="earningsDiv" class="hidden">
      <label class="block mb-1 font-medium">Earnings per Month</label>
      <input type="number" id="earnings_per_month" min="0" step="any" class="w-full border-b-2 border-gray-300 focus:border-emerald-500 outline-none py-2 rounded bg-transparent">
    </div>
    <div id="professionDiv" class="hidden">
      <label class="block mb-1 font-medium">Profession / Occupation</label>
      <input type="text" id="resident_profession" required class="w-full border-b-2 border-gray-300 focus:border-emerald-500 outline-none py-2 rounded bg-transparent">
    </div>
    <div id="guardianSection" class="hidden mt-6 space-y-4">
      <h3 class="font-semibold text-gray-800">Child / Ward Information</h3>
      <div class="relative">
        <label class="block mb-1 font-medium">Child / Ward</label>
        <input type="text" id="child_search" placeholder="Type child name..." autocomplete="off" class="w-full border-b-2 border-gray-300 focus:border-emerald-500 outline-none py-2 pl-10 rounded bg-transparent">
        <input type="hidden" id="child_id">
        <div id="child_dropdown" class="absolute w-full bg-white border mt-1 rounded shadow-lg z-50"></div>
      </div>
      <div id="child_card" class="hidden bg-emerald-50 p-4 rounded-lg shadow flex flex-col space-y-1 mt-2">
        <div class="flex items-center space-x-4">
          <span class="material-icons text-emerald-600 text-3xl">child_care</span>
          <div>
            <p class="font-semibold" id="child_name"></p>
            <p class="text-sm text-gray-600" id="child_age_status"></p>
          </div>
        </div>
        <p class="text-sm text-gray-500"><strong>Birthdate:</strong> <span id="child_birthdate_card"></span></p>
        <p class="text-sm text-gray-500"><strong>Birthplace:</strong> <span id="child_birthplace_card"></span></p>
      </div>
      <div id="child_manual" class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
        <div>
          <label class="block mb-1 font-medium">Child Full Name</label>
          <input type="text" id="child_fullname" required class="w-full border-b-2 border-gray-300 focus:border-emerald-500 outline-none py-2 rounded bg-transparent">
        </div>
        <div>
          <label class="block mb-1 font-medium">Child Age</label>
          <input type="text" id="child_age" required class="w-full border-b-2 border-gray-300 focus:border-emerald-500 outline-none py-2 rounded bg-transparent">
        </div>
        <div>
          <label class="block mb-1 font-medium">Birthdate</label>
          <input type="date" id="child_birthdate" required class="w-full border-b-2 border-gray-300 focus:border-emerald-500 outline-none py-2 rounded bg-transparent">
        </div>
        <div>
          <label class="block mb-1 font-medium">Birthplace</label>
          <input type="text" id="child_birthplace" required class="w-full border-b-2 border-gray-300 focus:border-emerald-500 outline-none py-2 rounded bg-transparent">
        </div>
      </div>
    </div>
    <button id="generateCertificate" class="bg-emerald-600 text-white px-6 py-2 rounded-lg shadow hover:bg-emerald-700 transition font-semibold">Generate Certificate</button>
  </div>
  <div class="bg-white rounded-xl shadow-md w-full max-w-[95%] mx-auto p-6">
    <h3 class="text-lg font-semibold mb-4 text-gray-800">Recent Walk-in Certificates</h3>
    <div class="overflow-x-auto">
      <table class="min-w-full bg-white divide-y divide-gray-200">
        <thead class="bg-emerald-100">
          <tr>
            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Resident</th>
            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Certificate</th>
            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Purpose</th>
            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Status</th>
            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Date Requested</th>
            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <?php if($recentRequests->num_rows > 0): ?>
          <?php while($r = $recentRequests->fetch_assoc()): ?>
          <tr class="hover:bg-gray-50 transition">
            <td class="px-4 py-2 text-sm"><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></td>
            <td class="px-4 py-2 text-sm"><?= htmlspecialchars($r['template_name']) ?></td>
            <td class="px-4 py-2 text-sm"><?= htmlspecialchars($r['purpose']) ?></td>
            <td class="px-4 py-2 text-sm status-<?= $r['status'] ?>"><?= $r['status'] ?></td>
            <td class="px-4 py-2 text-sm"><?= $r['date_requested'] ?></td>
            <td class="px-4 py-2 text-sm">
              <button class="bg-emerald-600 text-white px-3 py-1 rounded hover:bg-emerald-700 transition" onclick="printCertificate('<?= $r['id'] ?>', '<?= htmlspecialchars($r['template_name']) ?>')">Generate</button>
            </td>
          </tr>
          <?php endwhile; ?>
          <?php else: ?>
          <tr>
            <td colspan="6" class="px-4 py-4 text-center text-gray-500">No walk-in certificates yet.</td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

  </div>
</div>

<iframe id="printFrame" style="display:none;"></iframe>

<div id="messageModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-80 text-center relative">
        <button id="closeMessageModal" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 material-icons">close</button>
        <span id="modalIcon" class="material-icons text-4xl mb-2"></span>
        <p id="modalMessage" class="text-gray-700 font-medium"></p>
        <button id="okMessageBtn" class="mt-4 bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">OK</button>
    </div>
</div>

<div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-40 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl p-6 flex flex-col items-center">
        <div class="loader border-4 border-green-500 border-t-transparent rounded-full w-12 h-12 mb-4"></div>
        <p class="text-gray-700 font-medium">Loading, please wait...</p>
    </div>
</div>

<style>
.loader {
    border-width: 4px;
    border-style: solid;
    border-radius: 50%;
    border-top-color: transparent;
    animation: spin 1s linear infinite;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="walkin_certificat.js"></script>
<script>
    $('#generateCertificateTop').on('click', function() {
    $('#generateCertificate').click(); 
});

function calculateAge(birthdate) {
    if(!birthdate) return '';
    const today = new Date();
    const birth = new Date(birthdate);
    let age = today.getFullYear() - birth.getFullYear();
    const m = today.getMonth() - birth.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) {
        age--;
    }
    return age;
}

$(document).ready(function() {
    $('#resident_age, #child_age').prop('readonly', true);

    $('#resident_birthdate').on('change', function() {
        $('#resident_age').val(calculateAge($(this).val()));
    });

    $('#child_birthdate').on('change', function() {
        $('#child_age').val(calculateAge($(this).val()));
    });
});

function printCertificate(requestId, templateName) {
    let printFile = "";
    switch(templateName) {
        case "Barangay Certification":
            printFile = "print_certificate_barangay.php";
            break;
        case "Certificate of Attestation":
            printFile = "print_attestation.php";
            break;
        case "Certificate of Guardianship":
            printFile = "print_guardianship.php";
            break;
        case "Construction Permit":
            printFile = "print_construction.php";
            break;
        case "Maynilad Application":
            printFile = "print_maynilad.php";
            break;
        case "Good Moral Certificate":
            printFile = "print_moral.php";
            break;
        case "Certificate of Indigency":
            printFile = "print_indigency.php";
            break;
        case "Residency Certificate":
            printFile = "print_residency.php";
            break;
        default:
            alert("Unknown certificate type.");
            return;
    }

    window.open(printFile + "?id=" + requestId, "_blank");
}



    const iframe = document.getElementById('printFrame');
    const loading = document.getElementById('loadingOverlay');

    loading.classList.remove('hidden');
    iframe.src = 'about:blank';
    setTimeout(() => {
        iframe.src = `${printFile}?id=${requestId}`;
    }, 50);

    iframe.onload = function() {
        try {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
        } catch(e) {
            console.error("Print error:", e);
        }
        loading.classList.add('hidden');
    };
}


</script>



</body>
</html>
