<?php 
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "resident") {
    header("Location: ../../index.php");
    exit();
}
include '../../db.php';
$settingsQuery = $conn->query("SELECT theme_color FROM system_settings LIMIT 1");
$settings = $settingsQuery->fetch_assoc();
$themeColor = $settings['theme_color'] ?? '#1D4ED8';
$user_id = $_SESSION["user_id"];
$username = $_SESSION["username"];
$success = $error = "";
$approvalQuery = $conn->prepare("SELECT is_approved FROM users WHERE id = ?");
$approvalQuery->bind_param("i", $user_id);
$approvalQuery->execute();
$approvalResult = $approvalQuery->get_result();
if ($approvalResult->num_rows > 0) {
    $is_approved = $approvalResult->fetch_assoc()['is_approved'];
} else {
    die("User not found.");
}
$approvalQuery->close();
if ($is_approved != 1) {
    $error = "You can't request. Your account is not verified yet.";
}
$residentQuery = $conn->prepare("SELECT resident_id, CONCAT(first_name, ' ', COALESCE(middle_name,''), ' ', last_name) AS full_name, contact_number, resident_address FROM residents WHERE user_id = ?");
$residentQuery->bind_param("i", $user_id);
$residentQuery->execute();
$residentResult = $residentQuery->get_result();
if ($residentResult->num_rows > 0) {
    $residentRow = $residentResult->fetch_assoc();
    $resident_id = $residentRow['resident_id'];
    $resident_name = trim($residentRow['full_name']);
    $resident_contact = $residentRow['contact_number'];
    $resident_address = $residentRow['resident_address'];
} else {
    die("No matching resident record found for this user.");
}
$residentQuery->close();
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $victim_name = trim($_POST["victim_name"]);
    $suspect_name = trim($_POST["suspect_name"]);
    $incident_location = trim($_POST["incident_location"]);
    $incident_datetime = trim($_POST["incident_datetime"]);
    $incident_nature = trim($_POST["incident_nature"]);
    $victim_statement = trim($_POST["victim_statement"]);
    $suspect_description = trim($_POST["suspect_description"]);
    $complainant_relation = trim($_POST["complainant_relation"]);
    $suspect_address = trim($_POST["suspect_address"]);
    if (empty($victim_name) || empty($incident_nature) || empty($incident_datetime)) {
        $error = "Please fill in all required fields.";
    } else {
        $stmt = $conn->prepare("INSERT INTO blotter_records (complainant_id, complainant_name, complainant_contact, complainant_address, complainant_relation, victim_name, victim_statement, suspect_name, suspect_description, suspect_address, incident_datetime, incident_location, incident_nature, reporting_officer, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())");
        $reporting_officer = "Online Submission";
        $stmt->bind_param("isssssssssssss", $resident_id, $resident_name, $resident_contact, $resident_address, $complainant_relation, $victim_name, $victim_statement, $suspect_name, $suspect_description, $suspect_address, $incident_datetime, $incident_location, $incident_nature, $reporting_officer);
        if ($stmt->execute()) {
            $blotter_id = $stmt->insert_id;
            $success = "Your complaint has been submitted successfully! The barangay will review it soon.";
            $staffQuery = $conn->query("SELECT id FROM users WHERE role = 'staff'");
            while($staff = $staffQuery->fetch_assoc()) {
                $staff_id = $staff['id'];
                $message = "New blotter submitted by {$resident_name}";
                $notifStmt = $conn->prepare("INSERT INTO staff_notifications (staff_id, blotter_id, message) VALUES (?, ?, ?)");
                $notifStmt->bind_param("iis", $staff_id, $blotter_id, $message);
                $notifStmt->execute();
                $notifStmt->close();
            }
        } else {
            $error = "Something went wrong while submitting your complaint.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>File Complaint</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">

<header class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-md p-4 rounded-b-lg">
     <div class="container mx-auto flex justify-between items-center">
        <h1 class="text-xl font-bold">File Complaint</h1>
        <a href="../dashboard.php" class="flex items-center gap-2 px-4 py-2 rounded-lg font-medium bg-white text-blue-600 hover:bg-gray-100 transition">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
</header>

<main class="flex justify-center mt-10 px-6 mb-10">
    <div class="w-full max-w-6xl">
        <?php if(!empty($success)): ?>
            <div class="bg-green-100 text-green-800 px-6 py-4 rounded-2xl mb-6 flex items-center gap-3 shadow-sm">
                <i class="fas fa-check-circle text-lg"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php elseif(!empty($error)): ?>
            <div class="bg-red-100 text-red-800 px-6 py-4 rounded-2xl mb-6 flex items-center gap-3 shadow-sm">
                <i class="fas fa-exclamation-circle text-lg"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="bg-white p-10 rounded-2xl shadow-lg space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block font-semibold mb-2">Pangalan ng Nagrereklamo</label>
                    <input type="text" value="<?= htmlspecialchars($resident_name) ?>" readonly class="w-full border p-4 rounded-lg bg-gray-100">
                </div>
                <div>
                    <label class="block font-semibold mb-2">Contact ng Nagrereklamo</label>
                    <input type="text" value="<?= htmlspecialchars($resident_contact) ?>" readonly class="w-full border p-4 rounded-lg bg-gray-100">
                </div>
                <div class="md:col-span-2">
                    <label class="block font-semibold mb-2">Address ng Nagrereklamo</label>
                    <input type="text" value="<?= htmlspecialchars($resident_address) ?>" readonly class="w-full border p-4 rounded-lg bg-gray-100">
                </div>
                <div>
                    <label class="block font-semibold mb-2 required">Relasyon sa Biktima</label>
                    <input type="text" name="complainant_relation" placeholder="Sarili, Kamag-anak, Kapitbahay" required class="w-full border p-4 rounded-lg">
                </div>
                <div>
                    <label class="block font-semibold mb-2 required">Pangalan ng Biktima</label>
                    <input type="text" name="victim_name" placeholder="Ilagay ang pangalan ng biktima" required class="w-full border p-4 rounded-lg">
                </div>
                <div class="md:col-span-2">
                    <label class="block font-semibold mb-2 required">Pahayag ng Biktima / Buod ng Insidente</label>
                    <textarea name="victim_statement" placeholder="Ilarawan ang insidente nang maikli..." required class="w-full border p-4 rounded-lg h-36 resize-none"></textarea>
                </div>
                <div>
                    <label class="block font-semibold mb-2">Pangalan ng Suspek</label>
                    <input type="text" name="suspect_name" placeholder="Ilagay ang pangalan ng suspek" class="w-full border p-4 rounded-lg">
                </div>
                <div>
                    <label class="block font-semibold mb-2">Paglalarawan ng Suspek</label>
                    <textarea name="suspect_description" placeholder="Mga nakikilalang katangian" class="w-full border p-4 rounded-lg h-28 resize-none"></textarea>
                </div>
                <div>
                    <label class="block font-semibold mb-2">Address ng Suspek</label>
                    <input type="text" name="suspect_address" placeholder="Ilagay ang address ng suspek" class="w-full border p-4 rounded-lg">
                </div>
                <div>
                    <label class="block font-semibold mb-2 required">Lugar ng Insidente</label>
                    <input type="text" name="incident_location" placeholder="Saan naganap ang insidente?" required class="w-full border p-4 rounded-lg">
                </div>
                <div>
                    <label class="block font-semibold mb-2 required">Petsa at Oras ng Insidente</label>
                    <input type="datetime-local" name="incident_datetime" required class="w-full border p-4 rounded-lg">
                </div>
                <div class="md:col-span-2">
                    <label class="block font-semibold mb-2 required">Uri ng Insidente</label>
                    <input type="text" name="incident_nature" placeholder="hal. Pagnanakaw, Pananakot" required class="w-full border p-4 rounded-lg">
                </div>
            </div>
            <button type="submit" class="w-full py-4 rounded-2xl font-semibold flex justify-center items-center gap-3 text-white" style="background-color: <?= $themeColor ?>;">
                <i class="fas fa-paper-plane"></i> Isumite ang Reklamo
            </button>
        </form>
    </div>
</main>

</body>
</html>
