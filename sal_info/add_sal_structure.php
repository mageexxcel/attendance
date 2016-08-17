<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once ("c-salary.php");
$result = array(
    'data' => array(),
    'error' => array()
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
if (!isset($_POST['total_salary'])) {
    $result['error'][] = "Please add total_salary ";
}
if (isset($_POST['total_salary']) && $_POST['total_salary'] == "") {
    $result['error'][] = "Please insert a valid total salary amount ";
}
if (!isset($_POST['applicable_from'])) {
    $result['error'][] = "Please add applicable_from ";
}
if (isset($_POST['applicable_from']) && $_POST['applicable_from'] == "") {
    $result['error'][] = "Please insert a valid Applicable amount ";
}
if (!isset($_POST['leave'])) {
    $result['error'][] = "Please add leave ";
}
if (isset($_POST['leave']) && $_POST['leave'] == "") {
    $result['error'][] = "Please insert a valid leave ";
}
if (!isset($_POST['special_allowance'])) {
    $result['error'][] = "Please add special_allowance ";
}
if (isset($_POST['special_allowance']) && $_POST['special_allowance'] == "") {
    $result['error'][] = "Please insert a valid special_allowance ";
}
if (!isset($_POST['medical_allowance'])) {
    $result['error'][] = "Please add medical_allowance ";
}
if (isset($_POST['medical_allowance']) && $_POST['medical_allowance'] == "") {
    $result['error'][] = "Please insert a valid medical_allowance ";
}
if (!isset($_POST['conveyance'])) {
    $result['error'][] = "Please add conveyance ";
}
if (isset($_POST['conveyance']) && $_POST['conveyance'] == "") {
    $result['error'][] = "Please insert a valid conveyance ";
}
if (!isset($_POST['hra'])) {
    $result['error'][] = "Please add hra ";
}
if (isset($_POST['hra']) && $_POST['hra'] == "") {
    $result['error'][] = "Please insert a valid hra ";
}
if (!isset($_POST['basic'])) {
    $result['error'][] = "Please add basic ";
}
if (isset($_POST['basic']) && $_POST['basic'] == "") {
    $result['error'][] = "Please insert a valid basic ";
}
if (!isset($_POST['tds'])) {
    $result['error'][] = "Please add tds ";
}
if (isset($_POST['tds']) && $_POST['tds'] == "") {
    $result['error'][] = "Please insert a valid tds ";
}
if (!isset($_POST['Misc_deduction'])) {
    $result['error'][] = "Please add Misc_deduction ";
}
if (isset($_POST['Misc_deduction']) && $_POST['Misc_deduction'] == "") {
    $result['error'][] = "Please insert a valid Misc_deduction ";
}
if (!isset($_POST['advance'])) {
    $result['error'][] = "Please add advance ";
}
if (isset($_POST['advance']) && $_POST['advance'] == "") {
    $result['error'][] = "Please insert a valid advance ";
}
if (!isset($_POST['loan'])) {
    $result['error'][] = "Please add loan ";
}
if (isset($_POST['loan']) && $_POST['loan'] == "") {
    $result['error'][] = "Please insert a valid loan ";
}
if (!isset($_POST['epf'])) {
    $result['error'][] = "Please add epf ";
}
if (isset($_POST['epf']) && $_POST['epf'] == "") {
    $result['error'][] = "Please insert a valid epf ";
}
if (!isset($_POST['arrear'])) {
    $result['error'][] = "Please add arrear ";
}
if (isset($_POST['arrear']) && $_POST['arrear'] == "") {
    $result['error'][] = "Please insert a valid arrear ";
}

if (sizeof($result['error']) <= 0) {
    foreach ($_POST as $key => $val) {
        if ($key != 'token' && $key != 'applicable_from' && $key != 'submit') {
            if (!is_numeric($val)) {
                $result['error'][] = "Please insert a valid $key number";
            }
        }
        if($key == 'applicable_from' && !preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",$val)){
            $result['error'][] = "Please insert a valid $key date"; 
        }
        
    }
}

if (isset($_POST['token']) && $_POST['token'] != "") {

    if (sizeof($result['error']) <= 0) {
        $re = Salary::updateSalary($_POST);
        if ($re == "Successfully Salary Updated") {
            $result['data'] = $re;
        } else {
            $result['error'][] = $re;
        }
    }
}
echo json_encode($result);
?>
