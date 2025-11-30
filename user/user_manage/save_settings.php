<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION["user_id"])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

include '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

// Sanitize POST data
$data = $_POST;
$barangay_name    = $conn->real_escape_string($data['barangay_name'] ?? '');
$municipality     = $conn->real_escape_string($data['municipality'] ?? '');
$province         = $conn->real_escape_string($data['province'] ?? '');
$country          = $conn->real_escape_string($data['country'] ?? '');
$barangay_address = $conn->real_escape_string($data['barangay_address'] ?? '');
$theme_color      = $conn->real_escape_string($data['theme_color'] ?? '#0f6b35');
$system_email     = $conn->real_escape_string($data['system_email'] ?? '');
$contact_number   = $conn->real_escape_string($data['contact_number'] ?? '');
$smtp_host        = $conn->real_escape_string($data['smtp_host'] ?? '');
$smtp_port        = intval($data['smtp_port'] ?? 0);
$smtp_encryption  = $conn->real_escape_string($data['smtp_encryption'] ?? '');
$app_password     = $conn->real_escape_string($data['app_password'] ?? '');
$misyon           = $conn->real_escape_string($data['misyon'] ?? '');
$bisyon           = $conn->real_escape_string($data['bisyon'] ?? '');
$gcash_name       = $conn->real_escape_string($data['gcash_name'] ?? '');
$gcash_number     = $conn->real_escape_string($data['gcash_number'] ?? '');
$system_logo      = '';
$gcash_qr         = '';

// Current settings
$settings = $conn->query("SELECT * FROM system_settings WHERE id=1")->fetch_assoc();

// Detect changes in text fields
$changed = false;
$fields = [
    'barangay_name' => $barangay_name,
    'municipality' => $municipality,
    'province' => $province,
    'country' => $country,
    'barangay_address' => $barangay_address,
    'theme_color' => $theme_color,
    'system_email' => $system_email,
    'contact_number' => $contact_number,
    'smtp_host' => $smtp_host,
    'smtp_port' => $smtp_port,
    'smtp_encryption' => $smtp_encryption,
    'app_password' => $app_password,
    'misyon' => $misyon,
    'bisyon' => $bisyon,
    'gcash_name' => $gcash_name,
    'gcash_number' => $gcash_number
];

foreach ($fields as $key => $val) {
    $db_val = $settings[$key] ?? '';
    if ((string)$db_val !== (string)$val) {
        $changed = true;
        break;
    }
}

// Upload directory
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// Handle system logo upload
if (isset($_FILES['system_logo']) && $_FILES['system_logo']['tmp_name']) {
    $ext = pathinfo($_FILES['system_logo']['name'], PATHINFO_EXTENSION);
    $fname = 'system_logo_' . time() . '.' . $ext;
    $dest = $uploadDir . $fname;
    if (move_uploaded_file($_FILES['system_logo']['tmp_name'], $dest)) {
        $system_logo = 'user/user_manage/uploads/' . $fname;
        $changed = true;
    }
}

// Handle GCash QR upload
if (isset($_FILES['gcash_qr']) && $_FILES['gcash_qr']['tmp_name']) {
    $ext = pathinfo($_FILES['gcash_qr']['name'], PATHINFO_EXTENSION);
    $fname = 'gcash_qr_' . time() . '.' . $ext;
    $dest = $uploadDir . $fname;
    if (move_uploaded_file($_FILES['gcash_qr']['tmp_name'], $dest)) {
        $gcash_qr = 'user/user_manage/uploads/' . $fname;
        $changed = true;
    }
}

// Nothing changed
if (!$changed) {
    echo json_encode(['success' => false, 'error' => 'No changes detected']);
    exit;
}

// Build update query
$updateFields = [];
foreach ($fields as $key => $val) {
    $updateFields[] = "$key='$val'";
}
if ($system_logo) $updateFields[] = "system_logo='$system_logo'";
if ($gcash_qr) $updateFields[] = "gcash_qr='$gcash_qr'";

$updateQuery = "UPDATE system_settings SET " . implode(',', $updateFields) . " WHERE id=1";

// Execute update
if ($conn->query($updateQuery)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}

$conn->close();
?>
