<?php
session_start();
include '../db.php';

if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["role"], ['staff', 'admin'])) {
    header("HTTP/1.0 403 Forbidden");
    echo "Access denied.";
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: blotter.php");
    exit;
}

$blotterId = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT file_path FROM agreement_certificates WHERE blotter_id = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("i", $blotterId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "Agreement not found.";
    exit;
}

$file = $result->fetch_assoc()['file_path'];
$baseDir = realpath(__DIR__ . '/../generated_agreement_cert');

if (!$baseDir) {
    echo "Generated folder not found.";
    exit;
}

$filePath = $baseDir . '/' . basename($file);

if (!file_exists($filePath)) {
    echo "File not found.";
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
