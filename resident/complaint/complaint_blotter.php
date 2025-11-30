<?php
session_start();
include 'resident_header.php';
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "resident") {
    header("Location: ../../index.php");
    exit();
}

include '../../db.php';

/* ================================
   FETCH SYSTEM SETTINGS (THEME)
==================================*/
$settingsQuery = $conn->query("SELECT theme_color FROM system_settings LIMIT 1");
$settings = $settingsQuery->fetch_assoc();
$themeColor = $settings['theme_color'] ?? '#1D4ED8';

$user_id = $_SESSION["user_id"];
$username = $_SESSION["username"];

$success = "";
$error = "";

/* ================================
   CHECK IF RESIDENT IS APPROVED
==================================*/
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

/* ❗ BLOCK PAGE FUNCTIONS IF ACCOUNT NOT APPROVED */
$is_blocked = false;
if ($is_approved != 1) {
    $error = "Your account is not verified yet. You cannot submit a complaint.";
    $is_blocked = true;
}

/* ================================
   FETCH RESIDENT INFORMATION
==================================*/
$residentQuery = $conn->prepare("
    SELECT resident_id,
           CONCAT(first_name, ' ', COALESCE(middle_name,''), ' ', last_name) AS full_name,
           contact_number,
           resident_address
    FROM residents
    WHERE user_id = ?
");
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

/* ================================
   HANDLE FORM SUBMISSION
==================================*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && !$is_blocked) {

    // Sanitize inputs
    $victim_name = trim($_POST["victim_name"]);
    $suspect_name = trim($_POST["suspect_name"]);
    $incident_location = trim($_POST["incident_location"]);
    $incident_datetime = trim($_POST["incident_datetime"]);
    $incident_nature = trim($_POST["incident_nature"]);
    $victim_statement = trim($_POST["victim_statement"]);
    $suspect_description = trim($_POST["suspect_description"]);
    $complainant_relation = trim($_POST["complainant_relation"]);
    $suspect_address = trim($_POST["suspect_address"]);

    // Required fields
    if (empty($victim_name) || empty($incident_nature) || empty($incident_datetime)) {
        $error = "Please fill in all required fields.";
    } else {

        /* ================================
           INSERT COMPLAINT INTO DATABASE
        =================================*/
        $stmt = $conn->prepare("
            INSERT INTO blotter_records 
                (complainant_id, complainant_name, complainant_contact, complainant_address,
                 complainant_relation, victim_name, victim_statement, suspect_name, 
                 suspect_description, suspect_address, incident_datetime, incident_location, 
                 incident_nature, reporting_officer, status, created_at) 
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())
        ");

        $reporting_officer = "Online Submission";

        $stmt->bind_param(
            "isssssssssssss",
            $resident_id, $resident_name, $resident_contact, $resident_address,
            $complainant_relation, $victim_name, $victim_statement, $suspect_name,
            $suspect_description, $suspect_address, $incident_datetime,
            $incident_location, $incident_nature, $reporting_officer
        );

        if ($stmt->execute()) {

            $blotter_id = $stmt->insert_id;

            $success = "Your complaint has been submitted successfully!";

            /* ================================
               SEND NOTIFICATION TO STAFF
            =================================*/
            $staffQuery = $conn->query("SELECT id FROM users WHERE role = 'staff'");

            while ($staff = $staffQuery->fetch_assoc()) {
                $staff_id = $staff['id'];
                $message = "New blotter submitted by {$resident_name}";

                $notifStmt = $conn->prepare("
                    INSERT INTO staff_notifications (staff_id, blotter_id, message)
                    VALUES (?, ?, ?)
                ");

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
<main class="container mx-auto mt-8 max-w-4xl">

    <div class="mb-6 flex items-center space-x-2 text-gray-600 text-sm">
        <a href="../dashboard.php" class="hover:underline">Dashboard</a>
        <span class="text-gray-400"> | </span>
        <span class="font-semibold text-gray-800">File Complaint</span>
    </div>

    <?php if(!empty($success)): ?>
        <div class="bg-green-100 text-green-800 p-4 rounded-lg mb-6 shadow-sm"><?= htmlspecialchars($success) ?></div>
    <?php elseif(!empty($error)): ?>
        <div class="bg-red-100 text-red-800 p-4 rounded-lg mb-6 shadow-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow-lg p-6">

        <form method="POST" class="space-y-5">
            <div>
                <label class="font-semibold block mb-1">Pangalan ng Nagrereklamo</label>
                <input type="text" value="<?= htmlspecialchars($resident_name) ?>" readonly class="w-full border p-4 rounded-lg bg-gray-100">
            </div>

            <div>
                <label class="font-semibold block mb-1">Contact ng Nagrereklamo</label>
                <input type="text" value="<?= htmlspecialchars($resident_contact) ?>" readonly class="w-full border p-4 rounded-lg bg-gray-100">
            </div>

            <div>
                <label class="font-semibold block mb-1">Address ng Nagrereklamo</label>
                <input type="text" value="<?= htmlspecialchars($resident_address) ?>" readonly class="w-full border p-4 rounded-lg bg-gray-100">
            </div>

            <div>
                <label class="font-semibold block mb-1 required">Relasyon sa Biktima</label>
                <input type="text" name="complainant_relation" placeholder="Sarili, Kamag-anak, Kapitbahay" required class="w-full border p-4 rounded-lg">
            </div>

            <div>
                <label class="font-semibold block mb-1 required">Pangalan ng Biktima</label>
                <input type="text" name="victim_name" placeholder="Ilagay ang pangalan ng biktima" required class="w-full border p-4 rounded-lg">
            </div>

            <div>
                <label class="font-semibold block mb-1 required">Pahayag ng Biktima / Buod ng Insidente</label>
                <textarea name="victim_statement" placeholder="Ilarawan ang insidente nang maikli..." required class="w-full border p-4 rounded-lg h-36 resize-none"></textarea>
            </div>

            <div>
                <label class="font-semibold block mb-1">Pangalan ng Suspek</label>
                <input type="text" name="suspect_name" placeholder="Ilagay ang pangalan ng suspek" class="w-full border p-4 rounded-lg">
            </div>

            <div>
                <label class="font-semibold block mb-1">Paglalarawan ng Suspek</label>
                <textarea name="suspect_description" placeholder="Mga nakikilalang katangian" class="w-full border p-4 rounded-lg h-28 resize-none"></textarea>
            </div>

            <div>
                <label class="font-semibold block mb-1">Address ng Suspek</label>
                <input type="text" name="suspect_address" placeholder="Ilagay ang address ng suspek" class="w-full border p-4 rounded-lg">
            </div>

            <div>
                <label class="font-semibold block mb-1 required">Lugar ng Insidente</label>
                <input type="text" name="incident_location" placeholder="Saan naganap ang insidente?" required class="w-full border p-4 rounded-lg">
            </div>

            <div>
                <label class="font-semibold block mb-1 required">Petsa at Oras ng Insidente</label>
                <input type="datetime-local" name="incident_datetime" required class="w-full border p-4 rounded-lg">
            </div>

            <div>
                <label class="font-semibold block mb-1 required">Uri ng Insidente</label>
                <input type="text" name="incident_nature" placeholder="hal. Pagnanakaw, Pananakot" required class="w-full border p-4 rounded-lg">
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition font-semibold">
                    <i class="fas fa-paper-plane mr-2"></i> Isumite ang Reklamo
                </button>
            </div>

        </form>

    </div>
</main>


</body>
</html>
