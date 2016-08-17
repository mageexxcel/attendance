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
if (!isset($_POST['name'])) {
    $result['error'][] = "Please add name ";
}
if (isset($_POST['name']) && $_POST['name'] == "") {
    $result['error'][] = "Please insert a valid name ";
}
if (!isset($_POST['jobtitle'])) {
    $result['error'][] = "Please add jobtitle ";
}
if (isset($_POST['jobtitle']) && $_POST['jobtitle'] == "") {
    $result['error'][] = "Please insert a valid jobtitle ";
}
if (!isset($_POST['dateofjoin'])) {
    $result['error'][] = "Please add dateofjoin ";
}
if (isset($_POST['dateofjoin']) && $_POST['dateofjoin'] == "") {
    $result['error'][] = "Please insert a valid jobtitle ";
}
if (!isset($_POST['dob'])) {
    $result['error'][] = "Please add date of birth ";
}

if (!isset($_POST['gender'])) {
    $result['error'][] = "Please add gender ";
}
if (isset($_POST['gender']) && $_POST['gender'] == "") {
    $result['error'][] = "Please insert a valid gender ";
}
if (!isset($_POST['marital_status'])) {
    $result['error'][] = "Please add marital status ";
}
if (isset($_POST['marital_status']) && $_POST['marital_status'] == "") {
    $result['error'][] = "Please insert a valid marital status ";
}
if (!isset($_POST['address1'])) {
    $result['error'][] = "Please add address1 ";
}
if (isset($_POST['address1']) && $_POST['address1'] == "") {
    $result['error'][] = "Please insert a valid address1 ";
}
if (!isset($_POST['address2'])) {
    $result['error'][] = "Please add address2 ";
}

if (!isset($_POST['city'])) {
    $result['error'][] = "Please add city ";
}
if (isset($_POST['city']) && $_POST['city'] == "") {
    $result['error'][] = "Please insert a valid city ";
}
if (!isset($_POST['state'])) {
    $result['error'][] = "Please add state ";
}
if (isset($_POST['state']) && $_POST['state'] == "") {
    $result['error'][] = "Please insert a valid state ";
}
if (!isset($_POST['zipcode'])) {
    $result['error'][] = "Please add zipcode ";
}
if (isset($_POST['zipcode']) && $_POST['zipcode'] == "") {
    $result['error'][] = "Please insert a valid zipcode ";
}
if (!isset($_POST['country'])) {
    $result['error'][] = "Please add country ";
}
if (isset($_POST['country']) && $_POST['country'] == "") {
    $result['error'][] = "Please insert a valid country ";
}
if (!isset($_POST['home_phone'])) {
    $result['error'][] = "Please add home_phone ";
}

if (!isset($_POST['mobile_phone'])) {
    $result['error'][] = "Please add mobile_phone ";
}
if (isset($_POST['mobile_phone']) && $_POST['mobile_phone'] == "") {
    $result['error'][] = "Please insert a valid mobile phone no. ";
}
if (!isset($_POST['work_email'])) {
    $result['error'][] = "Please add work_email ";
}
if (isset($_POST['work_email']) && $_POST['work_email'] == "") {
    $result['error'][] = "Please insert a valid work email ";
}
if (!isset($_POST['other_email'])) {
    $result['error'][] = "Please add other_email ";
}
if (!isset($_POST['image'])) {
    $result['error'][] = "Please add image ";
}

if (isset($_POST['token']) && $_POST['token'] != "") {
    $userid = Salary::getIdUsingToken($_POST['token']);
    if ($userid != false) {
        $_POST['user_id']= $userid;
        if (sizeof($result['error']) <= 0) {
            $re = Salary::UpdateUserInfo($_POST);
            if ($re == "Successfully Updated into table") {
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

