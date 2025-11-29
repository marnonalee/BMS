<?php
session_start();
include '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $official_id = $_POST['official_id'];
    $currentQuery = $conn->query("SELECT * FROM barangay_officials WHERE official_id='$official_id'");
    if(!$currentQuery || $currentQuery->num_rows === 0){
        $_SESSION['success_message'] = ['type'=>'error','text'=>'Official not found.'];
        header('Location: barangay_officials.php');
        exit();
    }

    $current = $currentQuery->fetch_assoc();
    $photo = null;

    if(isset($_FILES['photo']) && $_FILES['photo']['error'] === 0){
        $fileTmpPath = $_FILES['photo']['tmp_name'];
        $fileExt = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $newFileName = 'official_'.$official_id.'_'.time().'.'.$fileExt;
        $uploadDir = '../uploads/';
        if(move_uploaded_file($fileTmpPath, $uploadDir . $newFileName)){
            $photo = $newFileName;
        }
    }

    $fields = [
        'resident_id' => $_POST['resident_id'] ?? $current['resident_id'],
        'position_id' => $_POST['position_id'] ?? $current['position_id'],
        'start_date' => $_POST['start_date'] ?? $current['start_date'],
        'end_date' => $_POST['end_date'] ?? $current['end_date'],
    ];

    if($photo) $fields['photo'] = $photo;

    // Check for archived resident
    $residentCheck = $conn->query("SELECT * FROM residents WHERE resident_id='".$fields['resident_id']."' AND is_archived = 0");
    if($residentCheck->num_rows === 0){
        $_SESSION['success_message'] = ['type'=>'error','text'=>'Cannot assign an archived resident.'];
        header('Location: barangay_officials.php');
        exit();
    }

    $changes = [];
    foreach($fields as $key => $val){
        if($val != $current[$key]){
            $changes[$key] = $conn->real_escape_string($val);
        }
    }

    if(empty($changes)){
        $_SESSION['success_message'] = ['type'=>'warning','text'=>'Nothing changed.'];
    } else {
        $updateParts = [];
        foreach($changes as $k => $v){
            $updateParts[] = "$k='$v'";
        }

        $sql = "UPDATE barangay_officials SET ".implode(", ", $updateParts)." WHERE official_id='$official_id'";
        if($conn->query($sql)){
            $_SESSION['success_message'] = ['type'=>'success','text'=>'Official updated successfully.'];

            $user_id = $_SESSION['user_id'] ?? 0;
            $residentQuery = $conn->query("SELECT first_name, last_name FROM residents WHERE resident_id='".$fields['resident_id']."' AND is_archived = 0");
            $resident = $residentQuery->fetch_assoc();
            $fullName = $resident['first_name'] . ' ' . $resident['last_name'];

            $action = "Update Barangay Official";
            $description = "Updated official for $fullName.";

            $logStmt = $conn->prepare("INSERT INTO log_activity (user_id, action, description) VALUES (?,?,?)");
            $logStmt->bind_param("iss", $user_id, $action, $description);
            $logStmt->execute();
            $logStmt->close();
        } else {
            $_SESSION['success_message'] = ['type'=>'error','text'=>'Failed to update official.'];
        }
    }

    $conn->close();
    header('Location: barangay_officials.php');
    exit();
}
?>
