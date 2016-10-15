<?php
/*
Generates a text file name Employee_detail.txt
containing info employee name , bank account no and salary amount.

 */

error_reporting(0);
ini_set('display_errors', 0);
require_once ("c-salary.php");
if (isset($_POST['submit'])) {
    $data = $_POST['user_id'];
    $token = $_POST['token'];
    $user_id = Salary::getIdUsingToken($token);
    $userinfo = Salary::getUserDetail($user_id);
    $userInfos = array('admin','hr');
    if(!in_array($userinfo['type'],$userInfos)){
    //if ($userinfo['type'] != "admin" && $userinfo['type'] != "hr") {
        echo "You are not authorise to access this page";
        die;
    }
    $validateToken = Salary::validateToken($token);
    
    if ($validateToken == false) {
   echo "Login Token Expired please login again";
    die;
}
// will be outputting  a text file. 
    header("Content-type: text/plain");
    header("Content-Disposition: attachment; filename=Employee_detail.txt"); // download the output file.
    $year = date('Y', strtotime(date('Y-m') . " -1 month"));
    $month = date('m', strtotime(date('Y-m') . " -1 month"));
    $ar = array();
    
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