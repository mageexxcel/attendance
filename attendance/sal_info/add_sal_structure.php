<?php
/*
Add Salary and salary structure of employee.
 */


error_reporting(0);
ini_set('display_errors', 0);
require_once ("c-salary.php");
$result = array(
    'data' => array(),
    'error' => array()
);
$request_body = file_get_contents('php://input');
$PARAMS = json_decode($request_body, true);
//$PARAMS = $_GET;


if (!isset($PARAMS['token'])) {
    $result['error'][] = "Please add token ";
}
if (isset($PARAMS['token']) && $PARAMS['token'] == "") {
    $result['error'][] = "Please insert a valid token ";
}
if (!isset($PARAMS['user_id'])) {
    $result['error'][] = "Please add user id ";
}
if (isset($PARAMS['user_id']) && $PARAMS['user_id'] === "") {
    $result['error'][] = "Please insert a valid user id ";
}
if (!isset($PARAMS['total_salary'])) {
    $result['error'][] = "Please add total_salary ";
}
if (isset($PARAMS['total_salary']) && $PARAMS['total_salary'] === "") {
    $result['error'][] = "Please insert a valid total salary amount ";
}
if (!isset($PARAMS['applicable_from'])) {
    $result['error'][] = "Please add applicable_from ";
}
if (isset($PARAMS['applicable_from']) && $PARAMS['applicable_from'] == "") {
    $result['error'][] = "Please insert a valid applicable_from date";
}
if (!isset($PARAMS['applicable_till'])) {
    $result['error'][] = "Please add applicable_till ";
}
if (isset($PARAMS['applicable_till']) && $PARAMS['applicable_till'] == "") {
    $result['error'][] = "Please insert a valid Applicable till date ";
}
if (!isset($PARAMS['leave'])) {
    $result['error'][] = "Please add leave ";
}
if (isset($PARAMS['leave']) && $PARAMS['leave'] == "") {
    $result['error'][] = "Please insert a valid leave ";
}
if (!isset($PARAMS['special_allowance'])) {
    $result['error'][] = "Please add special_allowance ";
}
if (isset($PARAMS['special_allowance']) && $PARAMS['special_allowance'] === "") {
    $result['error'][] = "Please insert a valid special_allowance ";
}
if (!isset($PARAMS['medical_allowance'])) {
    $result['error'][] = "Please add medical_allowance ";
}
if (isset($PARAMS['medical_allowance']) && $PARAMS['medical_allowance'] === "") {
    $result['error'][] = "Please insert a valid medical_allowance ";
}
if (!isset($PARAMS['conveyance'])) {
    $result['error'][] = "Please add conveyance ";
}
if (isset($PARAMS['conveyance']) && $PARAMS['conveyance'] === "") {
    $result['error'][] = "Please insert a valid conveyance ";
}
if (!isset($PARAMS['hra'])) {
    $result['error'][] = "Please add hra ";
}
if (isset($PARAMS['hra']) && $PARAMS['hra'] === "") {
    $result['error'][] = "Please insert a valid hra ";
}
if (!isset($PARAMS['basic'])) {
    $result['error'][] = "Please add basic ";
}
if (isset($PARAMS['basic']) && $PARAMS['basic'] === "") {
    $result['error'][] = "Please insert a valid basic ";
}
if (!isset($PARAMS['tds'])) {
    $result['error'][] = "Please add tds ";
}
if (isset($PARAMS['tds']) && $PARAMS['tds'] === "") {
    $result['error'][] = "Please insert a valid tds ";
}
if (!isset($PARAMS['misc_deduction'])) {
    $result['error'][] = "Please add Misc_deduction ";
}
if (isset($PARAMS['misc_deduction']) && $PARAMS['misc_deduction'] === "") {
    $result['error'][] = "Please insert a valid Misc_deduction ";
}
if (!isset($PARAMS['advance'])) {
    $result['error'][] = "Please add advance ";
}
if (isset($PARAMS['advance']) && $PARAMS['advance'] === "") {
    $result['error'][] = "Please insert a valid advance ";
}
if (!isset($PARAMS['loan'])) {
    $result['error'][] = "Please add loan ";
}
if (isset($PARAMS['loan']) && $PARAMS['loan'] === "") {
    $result['error'][] = "Please insert a valid loan ";
}
if (!isset($PARAMS['epf'])) {
    $result['error'][] = "Please add epf ";
}
if (isset($PARAMS['epf']) && $PARAMS['epf'] === "") {
    $result['error'][] = "Please insert a valid epf ";
}
if (!isset($PARAMS['arrear'])) {
    $result['error'][] = "Please add arrear ";
}
if (isset($PARAMS['arrear']) && $PARAMS['arrear'] === "") {
    $result['error'][] = "Please insert a valid arrear ";
}

if (sizeof($result['error']) <= 0) {
    foreach ($PARAMS as $key => $val) {
        if ($key != 'token' && $key != 'applicable_from' && $key != 'applicable_till' && $key != 'submit') {
            if (!is_numeric($val)) {
                $result['error'][] = "Please insert a valid $key number";
            }
        }
        if ($key == 'applicable_from' && !preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $val)) {
            $result['error'][] = "Please insert a valid $key date";
        }
    }
}

if (isset($PARAMS['token']) && $PARAMS['token'] != "") {

    $token = $PARAMS['token'];
    $validateToken = Salary::validateToken($token);

    if ($validateToken != false) {

        //start -- check for token expiry
        $tokenInfo = JWT::decode($token, Salary::JWT_SECRET_KEY);
        $tokenInfo = json_decode(json_encode($tokenInfo), true);

        if (is_array($tokenInfo) && isset($tokenInfo['login_time']) && $tokenInfo['login_time'] != "") {
            $token_start_time = $tokenInfo['login_time'];
            $current_time = time();
            $time_diff = $current_time - $token_start_time;
            $mins = $time_diff / 60;

            if ($mins > 60) { //if 60 mins more
                $validateToken = false;
            }
        } else {
            $validateToken = false;
        }
        //end -- check for token expiry
    }
    if ($validateToken == false) {
        header("HTTP/1.1 401 Unauthorized");
        exit;
    }

    $tuserid = Salary::getIdUsingToken($PARAMS['token']); // get userid through login token.
    $userinfo = Salary::getUserDetail($tuserid);
    if ($userinfo['type'] != "admin") {
        $result['error'][] = "You are not authorise to update salary information";
    }
    if (sizeof($result['error']) <= 0) {
        $re = Salary::updateSalary($PARAMS);
        if ($re == "Successfully Salary Updated") {
            $result['data'] = $re;
        } else {
            $result['error'][] = $re;
        }
    }
}
echo json_encode($result);
?>
