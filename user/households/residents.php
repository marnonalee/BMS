<?php
include '../db.php';

if(isset($_POST['add_resident'])){
    $id = (int)$_POST['resident_id'];
    $is_family_head = isset($_POST['is_family_head']) ? 1 : 0;

    $resident = $conn->query("SELECT * FROM residents WHERE resident_id=$id")->fetch_assoc();
    if(!$resident) exit(json_encode(['success'=>false, 'error'=>'Resident not found']));

    if($is_family_head){
        $conn->query("UPDATE residents SET household_id=NULL WHERE resident_id=$id AND is_family_head=0");

        $barangayCode = '7-61';
        $lastHousehold = $conn->query("SELECT family_id FROM households WHERE family_id LIKE '$barangayCode-%' ORDER BY family_id DESC LIMIT 1")->fetch_assoc();
        $newNumber = ($lastHousehold && !empty($lastHousehold['family_id'])) ? str_pad((int)substr($lastHousehold['family_id'],-4)+1, 4, '0', STR_PAD_LEFT) : '0001';
        $familyId = $barangayCode . '-' . $newNumber;

        $check = $conn->query("SELECT * FROM households WHERE head_resident_id=$id");
        if($check->num_rows > 0){
            $household_id = $check->fetch_assoc()['household_id'];
            $conn->query("UPDATE households SET family_id='$familyId', household_address='{$resident['resident_address']}', street='{$resident['street']}' WHERE head_resident_id=$id");
        } else {
            $conn->query("INSERT INTO households (family_id, head_resident_id, household_address, street, date_created) VALUES ('$familyId',$id,'{$resident['resident_address']}','{$resident['street']}',NOW())");
            $household_id = $conn->insert_id;
        }

        $conn->query("UPDATE residents SET household_id=$household_id, is_family_head=1 WHERE resident_id=$id");

        $conn->query("UPDATE residents SET household_id=NULL WHERE is_family_head=0 AND resident_id=$id");
    }

    echo json_encode(['success'=>true]);
}
?>
