<?php

error_reporting(0);
ini_set('display_errors', 0);
require_once ("c-salary.php");

if (isset($_POST['submit'])) {


    $data = $_POST['user_id'];

    $token = $_POST['token'];

    $user_id = Salary::getIdUsingToken($token);
    $userinfo = Salary::getUserDetail($user_id);
    if ($userinfo['type'] != "admin" && $userinfo['type'] != "hr") {
        echo "You are not authorise to access this page";
        die;
    }



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
   echo "Login Token Expired please login again";
    die;
}
    header("Content-type: text/plain");
    header("Content-Disposition: attachment; filename=Employee_detail.txt");

    $year = date('Y', strtotime(date('Y-m') . " -1 month"));
    $month = date('m', strtotime(date('Y-m') . " -1 month"));
    $ar = array();
    $ar[] = "Account No\tCurrent Salary\tName";
    if (sizeof($data) > 0) {
        foreach ($data as $val) {
            $s = array();
            $r1 = Salary::getUserBankDetail($val);
            $r2 = Salary::getUserManagePayslip($val, $year, $month);


            $s[] = $r1['bank_account_no'];
            $s[] = $r2['data']['user_data_for_payslip']['net_salary'];
            $s[] = $r2['data']['user_data_for_payslip']['name'];

            $ar[] = implode("\t", $s);
        }
    }

    foreach ($ar as $v) {
        echo $v;
        echo "\n";
    }
} else {
    echo "Invalid file access";
}