<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "resident") {
    header("Location: ../index.php");
    exit();
}
include '../../db.php';

if (!isset($_GET['cancel_id'])) {
    header("Location: all_requests.php");
    exit();
}

$cancel_id = intval($_GET['cancel_id']);
$user_id = $_SESSION["user_id"];

$residentQuery = $conn->prepare("SELECT resident_id FROM residents WHERE user_id=?");
$residentQuery->bind_param("i", $user_id);
$residentQuery->execute();
$residentResult = $residentQuery->get_result();
if ($residentResult->num_rows == 0) die("Resident not found.");
$resident_id = $residentResult->fetch_assoc()['resident_id'];
$residentQuery->close();

$checkQuery = $conn->prepare("SELECT status FROM certificate_requests WHERE id=? AND resident_id=?");
$checkQuery->bind_param("ii", $cancel_id, $resident_id);
$checkQuery->execute();
$checkResult = $checkQuery->get_result();
if ($checkResult->num_rows == 0) die("Request not found.");
$request = $checkResult->fetch_assoc();
if ($request['status'] !== 'Pending') die("Cannot cancel this request.");
$checkQuery->close();

$updateQuery = $conn->prepare("UPDATE certificate_requests SET status='Cancelled' WHERE id=? AND resident_id=?");
$updateQuery->bind_param("ii", $cancel_id, $resident_id);
$updateQuery->execute();
$updateQuery->close();

header("Location: all_requests.php");
exit();
?>
