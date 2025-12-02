<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../../login.php");
    exit();
}
include '../db.php';

$user_id = $_SESSION["user_id"]; 

$user_id = $_SESSION["user_id"];
$userQuery = $conn->prepare("SELECT first_name, last_name FROM residents WHERE user_id = ?");
$userQuery->bind_param("i", $user_id);
$userQuery->execute();
$userResult = $userQuery->get_result()->fetch_assoc();
$user_name = $userResult ? $userResult['first_name'] . ' ' . $userResult['last_name'] : 'Unknown User';

$action = "Print Household Masterlist";
$description = "$user_name printed the Household Masterlist";
$logStmt = $conn->prepare("INSERT INTO log_activity (user_id, action, description) VALUES (?,?,?)");
$logStmt->bind_param("iss", $user_id, $action, $description);
$logStmt->execute();


$date_covered = date("F Y");
$date_printed = date("F j, Y");

$settingsQuery = $conn->query("SELECT barangay_name FROM system_settings LIMIT 1");
$settings = $settingsQuery->fetch_assoc();
$barangay = $settings['barangay_name'] ?? '';

$householdsQuery = $conn->query("
    SELECT h.household_id, h.family_id, h.household_address, h.street, h.head_resident_id,
           r.first_name, r.middle_name, r.last_name, r.resident_address
    FROM households h
    LEFT JOIN residents r ON h.head_resident_id = r.resident_id
    ORDER BY h.household_id ASC
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Printable Household Masterlist</title>
    <style>
        @page { margin: 0.5in; }
        body { font-family: Arial, sans-serif; margin: 0.5in; font-size: 10pt; }
        .header { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .left { text-align: left; }
        .right { text-align: right; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        thead tr th { border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 5px; text-align: left; background-color: #f9f9f9; }
        tbody tr td { padding: 5px; text-align: left; }
        tbody tr:last-child td { border-bottom: 1px solid #000; }
        h1 { text-align: center; margin-bottom: 10px; }
        button { margin-bottom: 10px; }
        @media print { button { display: none; } }
    </style>
</head>
<body>
<h1>Lingap Imuseño Household Masterlist</h1>
<div class="header">
    <div class="left">
        Covered Month: <?= $date_covered ?><br>
        Barangay: <?= htmlspecialchars($barangay) ?>
    </div>
    <div class="right">
        Date Printed: <?= $date_printed ?>
    </div>
</div>
<button onclick="window.print()">Print</button>

<?php while ($head = $householdsQuery->fetch_assoc()): 
    $household_id = intval($head['household_id']);
    $headResidentId = $head['head_resident_id'];

    // Get other household members
    $membersQuery = $conn->query("
        SELECT resident_id, first_name, middle_name, last_name
        FROM residents
        WHERE household_id = $household_id
        AND resident_id != $headResidentId
        AND is_archived = 0
        ORDER BY last_name ASC
    ");
?>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Family ID</th>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Middle Name</th>
                <th>Address</th>
            </tr>
        </thead>
        <tbody>
            <tr style="font-weight:bold;">
                <td>1</td>
                <td><?= htmlspecialchars($head['family_id'] ?: $head['household_id']) ?></td>
                <td><?= htmlspecialchars($head['first_name']) ?></td>
                <td><?= htmlspecialchars($head['last_name']) ?></td>
                <td><?= htmlspecialchars($head['middle_name']) ?></td>
                <td><?= htmlspecialchars($head['resident_address'] ?: $head['household_address']) ?></td>
            </tr>
            <?php 
            $count = 2;
            while ($member = $membersQuery->fetch_assoc()): ?>
                <tr>
                    <td><?= $count ?></td>
                    <td><?= htmlspecialchars($head['family_id'] ?: $head['household_id']) ?></td>
                    <td><?= htmlspecialchars($member['first_name']) ?></td>
                    <td><?= htmlspecialchars($member['last_name']) ?></td>
                    <td><?= htmlspecialchars($member['middle_name']) ?></td>
                    <td></td>
                </tr>
            <?php 
            $count++;
            endwhile; 
            ?>
        </tbody>
    </table>
<?php endwhile; ?>
</body>
</html>
