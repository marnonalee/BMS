<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION["user_id"])) { echo json_encode(['error'=>'Not authenticated']); exit; }
include '../db.php';

$action = $_POST['action'] ?? null;

if ($action === 'upload') {
    if (empty($_FILES['files'])) { echo json_encode(['error'=>'No files']); exit; }
    $uploaddir = __DIR__ . '/../uploads/';
    if (!is_dir($uploaddir)) mkdir($uploaddir,0755,true);
    $added = [];
    foreach ($_FILES['files']['tmp_name'] as $idx => $tmp) {
        if ($_FILES['files']['error'][$idx] !== 0) continue;
        $name = time().'_'.preg_replace('/[^a-zA-Z0-9._-]/','_', basename($_FILES['files']['name'][$idx]));
        $dest = $uploaddir.$name;
        if (move_uploaded_file($tmp, $dest)) {
            $res = $conn->query("SELECT COALESCE(MAX(display_order),-1) AS mx FROM landing_hero_images");
            $mx = ($res) ? intval($res->fetch_assoc()['mx']) : -1;
            $order = $mx + 1;
            $stmt = $conn->prepare("INSERT INTO landing_hero_images (image_path, display_order) VALUES (?,?)");
            $path = '/uploads/'.$name;
            $stmt->bind_param("si", $path, $order);
            $stmt->execute();
            $added[] = $path;
        }
    }
    echo json_encode(['success'=>true,'added'=>$added]);
    exit;
}

if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['error'=>'Bad id']); exit; }
    $r = $conn->query("SELECT image_path FROM landing_hero_images WHERE id=$id")->fetch_assoc();
    if ($r) {
        $p = __DIR__ . '/../' . $r['image_path'];
        if (file_exists($p)) @unlink($p);
    }
    $conn->query("DELETE FROM landing_hero_images WHERE id=$id");
    echo json_encode(['success'=>true]);
    exit;
}

if ($action === 'reorder') {
    $data = json_decode($_POST['data'] ?? '[]', true);
    if (!is_array($data)) { echo json_encode(['error'=>'Bad data']); exit; }
    $stmt = $conn->prepare("UPDATE landing_hero_images SET display_order=? WHERE id=?");
    foreach ($data as $row) {
        $id = intval($row['id']);
        $order = intval($row['order']);
        $stmt->bind_param("ii",$order,$id);
        $stmt->execute();
    }
    echo json_encode(['success'=>true]);
    exit;
}

echo json_encode(['error'=>'Invalid action']);
