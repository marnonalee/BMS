<?php
session_start();
include '../db.php';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $resident_id = $_POST['resident_id'];
    $position_id = $_POST['position_id'];
    $department = $_POST['department'] ?? '';
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    $checkResident = $conn->query("SELECT * FROM barangay_officials bo 
                                   JOIN residents r ON bo.resident_id = r.resident_id 
                                   WHERE bo.resident_id='$resident_id' AND r.is_archived = 0");
    if($checkResident->num_rows > 0){
        $_SESSION['success_message'] = ['type'=>'error','text'=>'This resident already holds a position.'];
        header('Location: barangay_officials.php');
        exit();
    }

    $posQuery = $conn->query("SELECT `limit` FROM positions WHERE id='$position_id'");
    if($posQuery->num_rows == 0){
        $_SESSION['success_message'] = ['type'=>'error','text'=>'Invalid position selected.'];
        header('Location: barangay_officials.php');
        exit();
    }

    $pos = $posQuery->fetch_assoc();
    $countQuery = $conn->query("SELECT COUNT(*) as cnt FROM barangay_officials bo 
                                JOIN residents r ON bo.resident_id = r.resident_id 
                                WHERE bo.position_id='$position_id' AND r.is_archived = 0");
    $count = $countQuery->fetch_assoc()['cnt'];
    if($count >= $pos['limit']){
        $_SESSION['success_message'] = ['type'=>'error','text'=>'This position has reached its maximum limit.'];
        header('Location: barangay_officials.php');
        exit();
    }

    $photo = '';
    if(isset($_FILES['photo']) && $_FILES['photo']['error'] === 0){
        $fileTmp = $_FILES['photo']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif'];
        if(in_array($ext, $allowed)){
            $photo = 'official_'.time().'.'.$ext;
            move_uploaded_file($fileTmp, '../uploads/'.$photo);
        }
    }

    $stmt = $conn->prepare("INSERT INTO barangay_officials (resident_id, position_id, department, start_date, end_date, photo) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissss", $resident_id, $position_id, $department, $start_date, $end_date, $photo);

    if($stmt->execute()){
        $_SESSION['success_message'] = ['type'=>'success','text'=>'Official added successfully!'];

        $user_id = $_SESSION['user_id'] ?? 0;
        $residentQuery = $conn->query("SELECT first_name, last_name FROM residents WHERE resident_id='$resident_id' AND is_archived = 0");
        $resident = $residentQuery->fetch_assoc();
        $fullName = $resident['first_name'] . ' ' . $resident['last_name'];

        $action = "Add Barangay Official";
        $description = "Added $fullName as official.";

        $logStmt = $conn->prepare("INSERT INTO log_activity (user_id, action, description) VALUES (?,?,?)");
        $logStmt->bind_param("iss", $user_id, $action, $description);
        $logStmt->execute();
        $logStmt->close();
    } else {
        $_SESSION['success_message'] = ['type'=>'error','text'=>'Failed to add official.'];
    }

    $stmt->close();
    $conn->close();
    header('Location: barangay_officials.php');
    exit();
}
?>
