<?php
/*
Get employee salary details, holding details and 
payslip history of an employee
 */
error_reporting(0);
ini_set('display_errors', 0);
require_once ("c-salary.php");


$result = array(
    'data' => array(),
    'error' => array(),
);

if (isset($_GET['token']) && $_GET['token'] != "") {

    $token = $_GET['token'];
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


    $result['data']['salary_details'] = array();
    $tuserid = Salary::getIdUsingToken($_GET['token']);
    $userinfo = Salary::getUserDetail($tuserid);
    if (sizeof($userinfo) <= 0) {
        $result['error'][] = 'The given user id member not found';
    } else {
        if ($userinfo['type'] == "admin") {
            if (isset($_GET['user_id']) && $_GET['user_id'] != "") {
                $userid = $_GET['user_id'];
                $userinfo = Salary::getUserDetail($userid);
                if (sizeof($userinfo) <= 0) {
                    $result['error'][] = 'The given user id member not found';
                } else {
                    $result['data'] = $userinfo;
                    $res3 = Salary::getHoldingDetail($userid);
                    $res = Salary::getSalaryInfo($userid);
                    $res4 = Salary::getUserPayslipInfo($userid);
                    $i = 0;
                    foreach ($res as $val) {
                        $res2 = Salary::getSalaryDetail($val['id']);
                        $res2['test'] = $val;
                        $res2['date'] = $val['applicable_from'];
                        $result['data']['salary_details'][] = $res2;
                        $i++;
                    }
                    $result['data']['holding_details'] = $res3;
                    $result['data']['payslip_history'] = $res4;
                }
            } else {
                $result['error'][] = 'The given user id number';
            }
        } else {

            $result['data'] = $userinfo;
            $res = Salary::getSalaryInfo($tuserid);
            $res3 = Salary::getHoldingDetail($tuserid);
            $res4 = Salary::getUserPayslipInfo($tuserid);
            $i = 0;
            foreach ($res as $val) {

                $res2 = Salary::getSalaryDetail($val['id']);

                $res2['test'] = $val;
                $res2['date'] = $val['applicable_from'];
                $result['data']['salary_details'][] = $res2;

                $i++;
            }
            $result['data']['holding_details'] = $res3;
            $result['data']['payslip_history'] = $res4;
        }
    }
}
if (!isset($_GET['token'])) {
    $result['error'][] = "Please add token in URL";
}
if (isset($_GET['token']) && $_GET['token'] == "") {
    $result['error'][] = "Please insert a valid token in URL";
}

echo json_encode($result);
?>
