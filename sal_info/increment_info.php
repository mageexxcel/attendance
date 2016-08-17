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
    $result['error'][] = "Please add user id ";
}
if (isset($_POST['user_id']) && $_POST['user_id'] == "") {
    $result['error'][] = "Please insert a valid user id ";
}
if (!isset($_POST['increment_start_date'])) {
    $result['error'][] = "Please add Increment start date ";
}
if (isset($_POST['increment_start_date']) && $_POST['increment_start_date'] == "") {
    $result['error'][] = "Please insert a valid Increment start date ";
}
if (!isset($_POST['increment_end_date'])) {
    $result['error'][] = "Please add Increment end date ";
}
if (isset($_POST['increment_end_date']) && $_POST['increment_end_date'] == "") {
    $result['error'][] = "Please insert a Increment end date";
}
if (!isset($_POST['holding_amt_start'])) {
    $result['error'][] = "Please add holding_amt_start ";
}
if (isset($_POST['holding_amt_start']) && $_POST['holding_amt_start'] == "") {
    $result['error'][] = "Please insert a Holding amount start date";
}
if (!isset($_POST['holding_amt_stop'])) {
    $result['error'][] = "Please add total_salary ";
}
if (isset($_POST['holding_amt_stop']) && $_POST['holding_amt_stop'] == "") {
    $result['error'][] = "Please insert a Holding amount stop date";
}
if (!isset($_POST['holding_amt'])) {
    $result['error'][] = "Please add Holding amount ";
}
if (isset($_POST['holding_amt']) && $_POST['holding_amt'] == "") {
    $result['error'][] = "Please insert a Holding amount";
}

if (sizeof($result['error']) <= 0) {
    foreach ($_POST as $key => $val) {
        if ($key != 'token' && $key != 'user_id' && $key != 'holding_amt') {
            if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",$val)) {
                $result['error'][] = "Please insert a valid $key date";
            }
        }
        if($key == 'holding_amt' || $key == 'user_id'){
           if (!is_numeric($val)) {
                $result['error'][] = "Please insert a valid $key number";
            } 
        }
        
    }
}

if (isset($_POST['token']) && $_POST['token'] != "") {
    $userid = Salary::getIdUsingToken($_POST['token']);
    if ($userid != false) {
        if (sizeof($result['error']) <= 0) {
            $re = Salary::insertIncrementInfo($_POST);
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
