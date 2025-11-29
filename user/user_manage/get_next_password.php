<?php
include '../db.php';

$prefix = "PALICOIV@";
$lastUser = $conn->query("SELECT password FROM users WHERE password LIKE '$prefix%' ORDER BY id DESC LIMIT 1")->fetch_assoc();
if($lastUser){
    preg_match('/(\d+)$/', $lastUser['password'], $matches);
    $nextNumber = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
} else {
    $nextNumber = 1;
}
echo $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
?>
