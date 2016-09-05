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
if (!isset($_POST['bank_name'])) {
    $result['error'][] = "Please add bank_name ";
}
if (isset($_POST['bank_name']) && $_POST['bank_name'] == "") {
    $result['error'][] = "Please insert a valid bank_name ";
}
if (!isset($_POST['address'])) {
    $result['error'][] = "Please add address ";
}
if (isset($_POST['address']) && $_POST['address'] == "") {
    $result['error'][] = "Please insert a valid address ";
}
if (!isset($_POST['account_no'])) {
    $result['error'][] = "Please add account_no ";
}
if (isset($_POST['account_no']) && $_POST['account_no'] == "") {
    $result['error'][] = "Please insert a valid account_no ";
}
if (!isset($_POST['ifsc'])) {
    $result['error'][] = "Please add ifsc ";
}
if (isset($_POST['ifsc']) && $_POST['ifsc'] == "") {
    $result['error'][] = "Please insert a valid ifsc ";
}

if (sizeof($result['error']) <= 0) {
    foreach ($_POST as $key => $val) {
        if($key == 'account_no'){
           if (!is_numeric($val)) {
                $result['error'][] = "Please insert a valid $key number";
            } 
        }
        
    }
}
if (isset($_POST['token']) && $_POST['token'] != "") {
    $userid = Salary::getIdUsingToken($_POST['token']);
    if ($userid != false) {
        $_POST['user_id']= $userid;
        if (sizeof($result['error']) <= 0) {
            $re = Salary::insertUserBankInfo($_POST);
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

