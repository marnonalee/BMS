
 <?php
$servername = "localhost";
$username = "";
$password = "";
$database = "bmsystem";

$conn = new mysqli('localhost', '', '', 'bmsystem');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
