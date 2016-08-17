<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once ("c-salary.php");
$result = array(
    'data' => array(),
    'error' => array(),
);
if (!isset($_POST['token'])) {
    $result['error'][] = "Please add token ";
}
if (isset($_POST['token']) && $_POST['token'] == "") {
    $result['error'][] = "Please insert a valid token ";
}
if (!isset($_POST['user_id'])) {
    $result['error'][] = "Please add user_id ";
}
if (isset($_POST['user_id']) && $_POST['user_id'] == "") {
    $result['error'][] = "Please insert a valid user_id ";
}
if (!isset($_POST['id_proof'])) {
    $result['error'][] = "Please add id_proof ";
}

if (!isset($_POST['address_proof'])) {
    $result['error'][] = "Please add address_proof ";
}

if (!isset($_POST['passport_photo'])) {
    $result['error'][] = "Please add passport_photo ";
}

if (!isset($_POST['certificate'])) {
    $result['error'][] = "Please add certificate ";
}

if (!isset($_POST['pancard'])) {
    $result['error'][] = "Please add pancard ";
}

if (!isset($_POST['uid_for_bank'])) {
    $result['error'][] = "Please add uid_for_bank ";
}

if (!isset($_POST['previous_comp_doc'])) {
    $result['error'][] = "Please add previous_comp_doc ";
}

if (isset($_POST['token']) && $_POST['token'] != "") {
    $userid = Salary::getIdUsingToken($_POST['token']);
    if ($userid != false) {
        $_POST['user_id']= $userid;
        if (sizeof($result['error']) <= 0) {
            $re = Salary::UserDocumentInfo($_POST);
            if ($re == "Successfully Inserted into table") {
                $result['data'] = $re;
            } else {
                $result['error'][] = 'Some error occured';
            }
        }
    }
    else {
       $result['error'][] = 'Invalid token'; 
    }
    
}
echo json_encode($result);