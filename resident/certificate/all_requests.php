<?php
session_start();
include 'resident_header.php';
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "resident") {
    header("Location: ../index.php");
    exit();
}
include '../../db.php';

$user_id = $_SESSION["user_id"];

// Get resident ID
$residentQuery = $conn->prepare("SELECT resident_id FROM residents WHERE user_id=?");
$residentQuery->bind_param("i", $user_id);
$residentQuery->execute();
$residentResult = $residentQuery->get_result();
if ($residentResult->num_rows == 0) die("Resident not found.");
$resident_id = $residentResult->fetch_assoc()['resident_id'];
$residentQuery->close();

// Handle cancellation
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'], $_POST['cancel_type'])){
    $cancelId = intval($_POST['cancel_id']);
    $type = $_POST['cancel_type'];

    if($type === 'certificate'){
        $stmt = $conn->prepare("UPDATE certificate_requests SET status='Cancelled' WHERE id=? AND resident_id=?");
    } else {
        $stmt = $conn->prepare("UPDATE barangay_id_requests SET status='Cancelled' WHERE id=? AND resident_id=?");
    }
    $stmt->bind_param("ii", $cancelId, $resident_id);
    $stmt->execute();
    $stmt->close();
    header("Location: all_requests.php");
    exit();
}

// Fetch certificate requests
$requestQuery = $conn->prepare("
    SELECT cr.id, ct.template_name, cr.purpose, cr.status, cr.date_requested
    FROM certificate_requests cr
    JOIN certificate_templates ct ON cr.template_id = ct.id
    WHERE cr.resident_id=?
    ORDER BY cr.date_requested DESC
");
$requestQuery->bind_param("i", $resident_id);
$requestQuery->execute();
$result = $requestQuery->get_result();
$certRequests = $result->fetch_all(MYSQLI_ASSOC);
$requestQuery->close();

// Fetch Barangay ID requests
$barangayQuery = $conn->prepare("
    SELECT id, id_number, supporting_document, status, date_requested
    FROM barangay_id_requests
    WHERE resident_id=?
    ORDER BY date_requested DESC
");
$barangayQuery->bind_param("i", $resident_id);
$barangayQuery->execute();
$result2 = $barangayQuery->get_result();
$barangayRequests = $result2->fetch_all(MYSQLI_ASSOC);
$barangayQuery->close();

// Theme color
$settingsQuery = $conn->query("SELECT theme_color FROM system_settings LIMIT 1");
$settings = $settingsQuery->fetch_assoc();
$themeColor = $settings['theme_color'] ?? '#3b82f6';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>All Requests</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
<main class="container mx-auto mt-10 max-w-6xl px-4">

    <!-- Breadcrumb -->
    <div class="mb-6 flex items-center space-x-2 text-gray-500 text-sm">
        <a href="../dashboard.php" class="hover:underline">Dashboard</a>
        <span class="text-gray-300">/</span>
        <span class="font-semibold text-gray-700">My Document Requests</span>
    </div>

    <!-- Content Card -->
    <div class="bg-white shadow-xl rounded-2xl p-8">

        <!-- Certificate Requests -->
        <section class="mb-10">
            <h2 class="text-2xl font-semibold text-gray-800 border-b border-gray-200 pb-2 mb-6">Certificate Requests</h2>

            <?php if(count($certRequests) === 0): ?>
                <p class="text-gray-400 text-center py-8 italic">You have no certificate requests yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-600 uppercase tracking-wide">Certificate</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-600 uppercase tracking-wide">Purpose</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-600 uppercase tracking-wide">Status</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-600 uppercase tracking-wide">Date Requested</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-600 uppercase tracking-wide">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach($certRequests as $r): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4"><?= htmlspecialchars($r['template_name']) ?></td>
                                    <td class="px-6 py-4"><?= htmlspecialchars($r['purpose']) ?></td>
                                    <td class="px-6 py-4">
                                        <?php
                                        $statusColor = match($r['status']) {
                                            'Pending' => 'bg-yellow-100 text-yellow-800 font-semibold px-3 py-1 rounded-full text-center',
                                            'Approved' => 'bg-green-100 text-green-800 font-semibold px-3 py-1 rounded-full text-center',
                                            'Rejected' => 'bg-red-100 text-red-800 font-semibold px-3 py-1 rounded-full text-center',
                                            'Cancelled' => 'bg-gray-100 text-gray-800 font-semibold px-3 py-1 rounded-full text-center',
                                            default => 'text-gray-500',
                                        };
                                        ?>
                                        <span class="<?= $statusColor ?>"><?= htmlspecialchars($r['status']) ?></span>
                                    </td>
                                    <td class="px-6 py-4"><?= htmlspecialchars(date('M d, Y', strtotime($r['date_requested']))) ?></td>
                                    <td class="px-6 py-4 space-x-2">
                                        <?php if($r['status'] === 'Pending'): ?>
                                            <a href="request_certificate.php?edit_id=<?= $r['id'] ?>" class="px-3 py-1 bg-blue-50 text-blue-600 rounded-md hover:bg-blue-100 transition">Edit</a>
                                            <button onclick="openCancelModal(<?= $r['id'] ?>, 'certificate')" class="px-3 py-1 bg-red-50 text-red-600 rounded-md hover:bg-red-100 transition">Cancel</button>
                                        <?php else: ?>
                                            <span class="text-gray-400">No action</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- Barangay ID Requests -->
        <section>
            <h2 class="text-2xl font-semibold text-gray-800 border-b border-gray-200 pb-2 mb-6">Barangay ID Requests</h2>

            <?php if(count($barangayRequests) === 0): ?>
                <p class="text-gray-400 text-center py-8 italic">You have no Barangay ID requests yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-600 uppercase tracking-wide">ID Number</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-600 uppercase tracking-wide">Supporting Document</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-600 uppercase tracking-wide">Status</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-600 uppercase tracking-wide">Date Requested</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-600 uppercase tracking-wide">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach($barangayRequests as $b): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4"><?= htmlspecialchars($b['id_number'] ?: 'N/A') ?></td>
                                    <td class="px-6 py-4">
                                        <?php
                                        if(!empty($b['supporting_document'])){
                                            $docs = json_decode($b['supporting_document'], true);
                                            if($docs){
                                                foreach($docs as $label => $file){
                                                    echo '<button onclick="openModal(\'../uploads/'.htmlspecialchars($file).'\')" class="px-2 py-1 bg-blue-50 text-blue-600 rounded hover:bg-blue-100 transition mr-2">'.ucfirst($label).' ID</button>';
                                                }
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php
                                        $statusColor = match($b['status']) {
                                            'Pending' => 'bg-yellow-100 text-yellow-800 font-semibold px-3 py-1 rounded-full text-center',
                                            'Approved' => 'bg-green-100 text-green-800 font-semibold px-3 py-1 rounded-full text-center',
                                            'Rejected' => 'bg-red-100 text-red-800 font-semibold px-3 py-1 rounded-full text-center',
                                            'Ready for Pickup' => 'bg-blue-100 text-blue-800 font-semibold px-3 py-1 rounded-full text-center',
                                            'Cancelled' => 'bg-gray-100 text-gray-800 font-semibold px-3 py-1 rounded-full text-center',
                                            default => 'text-gray-500',
                                        };
                                        ?>
                                        <span class="<?= $statusColor ?>"><?= htmlspecialchars($b['status']) ?></span>
                                    </td>
                                    <td class="px-6 py-4"><?= htmlspecialchars(date('M d, Y', strtotime($b['date_requested']))) ?></td>
                                    <td class="px-6 py-4 space-x-2">
                                        <?php if($b['status'] === 'Pending'): ?>
                                            <a href="../request_barangay_id.php?edit_id=<?= $b['id'] ?>" class="px-3 py-1 bg-blue-50 text-blue-600 rounded-md hover:bg-blue-100 transition">Edit</a>
                                            <button onclick="openCancelModal(<?= $b['id'] ?>, 'barangay')" class="px-3 py-1 bg-red-50 text-red-600 rounded-md hover:bg-red-100 transition">Cancel</button>
                                        <?php else: ?>
                                            <span class="text-gray-400">No action</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

    </div>

    <!-- Modals (Document & Cancel) -->
    <div id="docModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full md:w-1/2 lg:w-1/3 p-4 relative">
            <button onclick="closeModal()" class="absolute top-2 right-2 text-gray-600 hover:text-gray-900 text-2xl font-bold">&times;</button>
            <img id="docImg" src="" alt="Document" class="w-full h-auto rounded" />
        </div>
    </div>

    <div id="cancelModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full md:w-1/2 p-6 relative">
            <button onclick="closeCancelModal()" class="absolute top-2 right-2 text-gray-600 hover:text-gray-900 text-2xl font-bold">&times;</button>
            <h3 class="text-lg font-semibold mb-4 text-gray-800">Are you sure you want to cancel this request?</h3>
            <div class="flex justify-end gap-3 mt-4">
                <button onclick="closeCancelModal()" class="px-4 py-2 rounded bg-gray-200 hover:bg-gray-300 transition">No</button>
                <form id="cancelForm" method="POST">
                    <input type="hidden" name="cancel_id" id="cancelId">
                    <input type="hidden" name="cancel_type" id="cancelType">
                    <button type="submit" class="px-4 py-2 rounded bg-red-600 text-white hover:bg-red-700 transition">Yes, Cancel</button>
                </form>
            </div>
        </div>
    </div>

</main>

<script>
function openModal(filePath){
    document.getElementById('docImg').src = filePath;
    document.getElementById('docModal').classList.remove('hidden');
}
function closeModal(){
    document.getElementById('docImg').src = '';
    document.getElementById('docModal').classList.add('hidden');
}

function openCancelModal(id, type){
    document.getElementById('cancelId').value = id;
    document.getElementById('cancelType').value = type;
    document.getElementById('cancelModal').classList.remove('hidden');
}
function closeCancelModal(){
    document.getElementById('cancelModal').classList.add('hidden');
}
</script>

</body>
</html>
