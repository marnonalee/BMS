<?php
$servername = "sql302.infinityfree.com";  // example host from InfinityFree
$username = "if0_40551104";        // your DB username
$password = "XPQ3z0t4KxFjvy";        // your DB password
$database = "if0_40551104_bmsystem";    // your DB name

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
