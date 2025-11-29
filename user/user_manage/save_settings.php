<?php 
session_start();
if (!isset($_SESSION["user_id"])) {
    echo json_encode(['success'=>false,'error'=>'Not logged in']);
    exit;
}
include '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST;

    $barangay_name = $conn->real_escape_string($data['barangay_name'] ?? '');
    $municipality = $conn->real_escape_string($data['municipality'] ?? '');
    $province = $conn->real_escape_string($data['province'] ?? '');
    $country = $conn->real_escape_string($data['country'] ?? '');
    $barangay_address = $conn->real_escape_string($data['barangay_address'] ?? '');
    $theme_color = $conn->real_escape_string($data['theme_color'] ?? '#0f6b35');
    $system_email = $conn->real_escape_string($data['system_email'] ?? '');
    $contact_number = $conn->real_escape_string($data['contact_number'] ?? '');
    $smtp_host = $conn->real_escape_string($data['smtp_host'] ?? '');
    $smtp_port = intval($data['smtp_port'] ?? 0);
    $smtp_encryption = $conn->real_escape_string($data['smtp_encryption'] ?? '');
    $app_password = $conn->real_escape_string($data['app_password'] ?? '');
    $misyon = $conn->real_escape_string($data['misyon'] ?? '');
    $bisyon = $conn->real_escape_string($data['bisyon'] ?? '');

    $settings = $conn->query("SELECT * FROM system_settings WHERE id=1")->fetch_assoc();

    // Check if any value changed
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
        'bisyon' => $bisyon
    ];

    foreach($fields as $key => $val){
        if(isset($settings[$key]) && $settings[$key] != $val){
            $changed = true;
            break;
        }
    }

    // Handle logo upload
    $system_logo = '';
    if (isset($_FILES['system_logo']) && $_FILES['system_logo']['tmp_name']) {
        $ext = pathinfo($_FILES['system_logo']['name'], PATHINFO_EXTENSION);
        $fname = 'system_logo_'.time().'.'.$ext;
        $dest = __DIR__.'/user_manage/'.$fname;
        if (move_uploaded_file($_FILES['system_logo']['tmp_name'],$dest)) {
            $system_logo = 'user_manage/'.$fname;
            $changed = true;
        }
    }

    if(!$changed){
        echo json_encode(['success'=>false,'error'=>'No changes detected']);
        exit;
    }

    $update = "UPDATE system_settings SET 
        barangay_name='$barangay_name',
        municipality='$municipality',
        province='$province',
        country='$country',
        barangay_address='$barangay_address',
        theme_color='$theme_color',
        system_email='$system_email',
        contact_number='$contact_number',
        smtp_host='$smtp_host',
        smtp_port=$smtp_port,
        smtp_encryption='$smtp_encryption',
        app_password='$app_password',
        misyon='$misyon',
        bisyon='$bisyon'";

    if($system_logo) $update .= ", system_logo='$system_logo'";

    $update .= " WHERE id=1";

    if($conn->query($update)){
        echo json_encode(['success'=>true]);
    } else {
        echo json_encode(['success'=>false,'error'=>$conn->error]);
    }
} else {
    echo json_encode(['success'=>false,'error'=>'Invalid request']);
}

$conn->close();

?>
