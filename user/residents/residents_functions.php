<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../../login.php");
    exit();
}

include '../db.php';

$user_id = $_SESSION["user_id"];
$userQuery = $conn->query("SELECT * FROM users WHERE id = '$user_id'");
$user = $userQuery->fetch_assoc();
$role = $user['role'];
$successMsg = '';

function checkboxValue($key) {
    return isset($_POST[$key]) ? 1 : 0;
}

function logActivity($conn, $user_id, $action, $description = '') {
    $stmt = $conn->prepare("INSERT INTO log_activity (user_id, action, description) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $action, $description);
    $stmt->execute();
    $stmt->close();
}

function requiredFieldsFilled($data, $required) {
    foreach($required as $field) {
        if(!isset($data[$field]) || trim($data[$field]) === '') return false;
    }
    return true;
}
if(isset($_POST['update_resident'])){
    $id = (int)$_POST['resident_id'];
    $current = $conn->query("SELECT * FROM residents WHERE resident_id=$id")->fetch_assoc();

    $submitted = [
        'first_name' => $_POST['first_name'] ?? '',
        'middle_name' => $_POST['middle_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'alias' => $_POST['alias'] ?? '',
        'suffix' => $_POST['suffix'] ?? '',
        'birthdate' => $_POST['birthdate'] ?? '',
        'age' => !empty($_POST['birthdate']) ? date_diff(date_create($_POST['birthdate']), date_create('today'))->y : 0,
        'sex' => $_POST['sex'] ?? '',
        'civil_status' => $_POST['civil_status'] ?? 'Single',
        'resident_address' => $_POST['resident_address'] ?? '',
        'street' => $_POST['street'] ?? '',
        'voter_status' => $_POST['voter_status'] ?? 'Unregistered',
        'employment_status' => $_POST['employment_status'] ?? 'Unemployed',
        'contact_number' => $_POST['contact_number'] ?? '',
        'email_address' => $_POST['email_address'] ?? '',
        'religion' => $_POST['religion'] ?? '',
        'profession_occupation' => $_POST['profession_occupation'] ?? '',
        'educational_attainment' => $_POST['educational_attainment'] ?? '',
        'education_details' => $_POST['education_details'] ?? '',
        'school_status' => $_POST['school_status'] ?? '',
        'philsys_card_no' => $_POST['philsys_card_no'] ?? '',
        'birth_place' => $_POST['birth_place'] ?? '',
        'citizenship' => $_POST['citizenship'] ?? '',
        'is_senior' => checkboxValue('is_senior'),
        'is_pwd' => checkboxValue('is_pwd'),
        'is_4ps' => checkboxValue('is_4ps'),
        'is_solo_parent' => checkboxValue('is_solo_parent'),
        'is_family_head' => checkboxValue('is_family_head')
    ];

    $changed = false;
    foreach($submitted as $key => $value){
        if(isset($current[$key]) && $current[$key] != $value){
            $changed = true;
            break;
        }
    }

    if(!$changed){
        $successMsg = "Nothing changed.";
    } else {
        $stmt = $conn->prepare("
            UPDATE residents SET
                first_name=?, middle_name=?, last_name=?, alias=?, suffix=?,
                birthdate=?, age=?, sex=?, civil_status=?, resident_address=?,
                street=?, voter_status=?, employment_status=?, contact_number=?, email_address=?,
                religion=?, profession_occupation=?, educational_attainment=?, education_details=?, school_status=?,
                philsys_card_no=?, birth_place=?, citizenship=?, is_senior=?, is_pwd=?, is_4ps=?, is_solo_parent=?, is_family_head=?
            WHERE resident_id=?
        ");
        $stmt->bind_param(
            "ssssssissssssssssssssssiiiiii",
            $submitted['first_name'], $submitted['middle_name'], $submitted['last_name'], $submitted['alias'], $submitted['suffix'],
            $submitted['birthdate'], $submitted['age'], $submitted['sex'], $submitted['civil_status'], $submitted['resident_address'],
            $submitted['street'], $submitted['voter_status'], $submitted['employment_status'], $submitted['contact_number'], $submitted['email_address'],
            $submitted['religion'], $submitted['profession_occupation'], $submitted['educational_attainment'], $submitted['education_details'], $submitted['school_status'],
            $submitted['philsys_card_no'], $submitted['birth_place'], $submitted['citizenship'], $submitted['is_senior'], $submitted['is_pwd'], $submitted['is_4ps'], $submitted['is_solo_parent'], $submitted['is_family_head'],
            $id
        );
        $stmt->execute();
        $stmt->close();

      if($submitted['is_family_head']){
            $barangayCode = '7-61';
            $lastHousehold = $conn->query("SELECT family_id FROM households WHERE family_id LIKE '$barangayCode-%' ORDER BY family_id DESC LIMIT 1")->fetch_assoc();
            $newNumber = ($lastHousehold && !empty($lastHousehold['family_id'])) ? str_pad((int)substr($lastHousehold['family_id'],-4)+1, 4, '0', STR_PAD_LEFT) : '0001';
            $familyId = $barangayCode . '-' . $newNumber;

            $check = $conn->query("SELECT * FROM households WHERE head_resident_id=$id");
            if($check->num_rows>0){
                $conn->query("UPDATE households SET family_id='$familyId', household_address='{$submitted['resident_address']}', street='{$submitted['street']}' WHERE head_resident_id=$id");
                // Fetch updated family_id properly
                $household_id = $conn->query("SELECT family_id FROM households WHERE head_resident_id=$id")->fetch_assoc()['family_id'];
            } else {
                $conn->query("INSERT INTO households (family_id, head_resident_id, household_address, street, date_created) VALUES ('$familyId',$id,'{$submitted['resident_address']}','{$submitted['street']}',NOW())");
                $household_id = $conn->insert_id;
            }
            $conn->query("UPDATE residents SET household_id=$household_id WHERE resident_id=$id");
        }


        $successMsg = "Resident updated successfully!";
        logActivity($conn, $user_id, "Update Resident", "Updated resident ID: $id ({$submitted['first_name']} {$submitted['last_name']})");
    }
}


if(isset($_POST['add_resident'])){
    $first_name = $_POST['first_name'] ?? '';
    $middle_name = $_POST['middle_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $birthdate = $_POST['birthdate'] ?? '';
    $resident_address = $_POST['resident_address'] ?? '';

    $duplicateCheck = $conn->prepare("
        SELECT resident_id 
        FROM residents 
        WHERE first_name=? 
          AND middle_name=? 
          AND last_name=? 
          AND birthdate=? 
          AND resident_address=? 
          AND is_archived=0
    ");
    $duplicateCheck->bind_param("sssss", $first_name, $middle_name, $last_name, $birthdate, $resident_address);
    $duplicateCheck->execute();
    $result = $duplicateCheck->get_result();

    if($result->num_rows > 0){
        $successMsg = "Duplicate resident found! Same name, birthdate, and address already exists.";
          echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            const addModal = document.getElementById('addResidentModal');
            if(addModal) addModal.classList.remove('hidden');
            showResidentModalMessage('{$successMsg}', 'warning');
        });
    </script>";
    } else {
        $alias = $_POST['alias'] ?? '';
        $suffix = $_POST['suffix'] ?? '';
        $age = !empty($birthdate) ? date_diff(date_create($birthdate), date_create('today'))->y : 0;
        $sex = $_POST['sex'] ?? '';
        $civil_status = $_POST['civil_status'] ?? 'Single';
        $street = $_POST['street'] ?? '';
        $voter_status = $_POST['voter_status'] ?? 'Unregistered';
        $employment_status = $_POST['employment_status'] ?? 'Unemployed';
        $contact_number = $_POST['contact_number'] ?? '';
        $email_address = $_POST['email_address'] ?? '';
        $religion = $_POST['religion'] ?? '';
        $profession_occupation = $_POST['profession_occupation'] ?? '';
        $educational_attainment = $_POST['educational_attainment'] ?? '';
        $education_details = $_POST['education_details'] ?? '';
        $school_status = $_POST['school_status'] ?? '';
        $philsys_card_no = $_POST['philsys_card_no'] ?? '';
        $birth_place = $_POST['birth_place'] ?? '';
        $citizenship = $_POST['citizenship'] ?? '';
        $is_senior = checkboxValue('is_senior');
        $is_pwd = checkboxValue('is_pwd');
        $is_4ps = checkboxValue('is_4ps');
        $is_solo_parent = checkboxValue('is_solo_parent');
        $is_family_head = checkboxValue('is_family_head');

        $stmt = $conn->prepare("
            INSERT INTO residents (
                first_name,middle_name,last_name,alias,suffix,
                birthdate,age,sex,civil_status,resident_address,
                street,voter_status,employment_status,contact_number,email_address,
                religion,profession_occupation,educational_attainment,education_details,school_status,
                philsys_card_no,birth_place,citizenship,is_senior,is_pwd,is_4ps,is_solo_parent,is_family_head
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->bind_param(
            "ssssssissssssssssssssssiiiii",
            $first_name,$middle_name,$last_name,$alias,$suffix,
            $birthdate,$age,$sex,$civil_status,$resident_address,
            $street,$voter_status,$employment_status,$contact_number,$email_address,
            $religion,$profession_occupation,$educational_attainment,$education_details,$school_status,
            $philsys_card_no,$birth_place,$citizenship,$is_senior,$is_pwd,$is_4ps,$is_solo_parent,$is_family_head
        );
        $stmt->execute();
        $last_id = $conn->insert_id;
        $stmt->close();

        if($is_family_head){
            $barangayCode = '7-61';
            $lastHousehold = $conn->query("SELECT family_id FROM households WHERE family_id LIKE '$barangayCode-%' ORDER BY family_id DESC LIMIT 1")->fetch_assoc();
            $newNumber = ($lastHousehold && !empty($lastHousehold['family_id'])) ? str_pad((int)substr($lastHousehold['family_id'],-4)+1,4,'0',STR_PAD_LEFT) : '0001';
            $familyId = $barangayCode . '-' . $newNumber;

            $conn->query("INSERT INTO households (family_id, head_resident_id, household_address, street, date_created) VALUES ('$familyId',$last_id,'$resident_address','$street',NOW())");
            $household_id = $conn->insert_id;
            $conn->query("UPDATE residents SET household_id=$household_id WHERE resident_id=$last_id");
        }

        $successMsg = "Resident added successfully!";
        logActivity($conn, $user_id, "Add Resident", "Added {$first_name} {$middle_name} {$last_name}");
    }
}



// ---- RESIDENT LIST, FILTER, SEARCH, PAGINATION ----
$perPage = isset($_GET['perPage']) ? (int)$_GET['perPage'] : 100;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page-1)*$perPage;

$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$sort = ($_GET['sort'] ?? 'asc')==='desc' ? 'DESC':'ASC';

$where = "is_archived=0";
switch($filter){
    case 'senior': $where.=" AND TIMESTAMPDIFF(YEAR,birthdate,CURDATE())>=60"; break;
    case 'pwd': $where.=" AND is_pwd=1"; break;
    case '4ps': $where.=" AND is_4ps=1"; break;
    case 'solo_parent': $where.=" AND is_solo_parent=1"; break;
    case 'voters': $where.=" AND voter_status='Registered'"; break;
    case 'unregistered_voter': $where.=" AND voter_status='Unregistered'"; break;
}

if($search!==""){
    $s = $conn->real_escape_string($search);
    $where.=" AND (first_name LIKE '%$s%' OR middle_name LIKE '%$s%' OR last_name LIKE '%$s%' OR alias LIKE '%$s%' OR suffix LIKE '%$s%' OR resident_address LIKE '%$s%' OR street LIKE '%$s%' OR contact_number LIKE '%$s%' OR email_address LIKE '%$s%')";
}

$totalResidents = $conn->query("SELECT COUNT(*) AS cnt FROM residents WHERE $where")->fetch_assoc()['cnt'];
$totalPages = ceil($totalResidents/$perPage);

$residentsQuery = $conn->query("SELECT * FROM residents WHERE $where ORDER BY first_name $sort LIMIT $start,$perPage");

$totalVoters = $conn->query("SELECT COUNT(*) AS cnt FROM residents WHERE voter_status='Registered'")->fetch_assoc()['cnt'];
$totalUnregisteredVoters = $conn->query("SELECT COUNT(*) AS cnt FROM residents WHERE voter_status='Unregistered'")->fetch_assoc()['cnt'];
$totalSenior = $conn->query("SELECT COUNT(*) AS cnt FROM residents WHERE TIMESTAMPDIFF(YEAR,birthdate,CURDATE())>=60")->fetch_assoc()['cnt'];
$totalPWD = $conn->query("SELECT COUNT(*) AS cnt FROM residents WHERE is_pwd=1")->fetch_assoc()['cnt'];
$total4Ps = $conn->query("SELECT COUNT(*) AS cnt FROM residents WHERE is_4ps=1")->fetch_assoc()['cnt'];
$totalSoloParent = $conn->query("SELECT COUNT(*) AS cnt FROM residents WHERE is_solo_parent=1")->fetch_assoc()['cnt'];
$archivedQuery = $conn->query("SELECT * FROM residents WHERE is_archived=1 ORDER BY last_name ASC");
?>
