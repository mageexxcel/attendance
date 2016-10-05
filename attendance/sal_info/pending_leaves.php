<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once ("c-salary.php");

$arr = Salary::getEnabledUsersList();
echo "<pre>";
foreach($arr as $k=>$v){
    $userid = $v['user_Id'];
    $ss = getleaves($userid);
    
    $aug = getmonthLeave($userid, '2016', '08');
    $sep = getmonthLeave($userid, '2016', '09');
    
    print_r($ss);
    $july = $ss['final_leave_balance'];
    
    echo "july=".$july."  Aug= ".$aug ."  Sept=". $sep; 
    echo "<br>";
    echo "final balance leave:". ($july + $aug + $sep);
     echo "<br>";
}

function getleaves($userid){
    $q = "select * from payslip where user_Id = $userid ORDER by id DESC LIMIT 1";

        $runQuery = Salary::DBrunQuery($q);
        $row = Salary::DBfetchRow($runQuery);
        return $row;
        
}

function getmonthLeave($userid, $year, $month){
    $arr = Salary::getUserMonthLeaves($userid, $year, $month);
    
    $current_month_leave = 0;
        if (sizeof($arr) > 0) {
            foreach ($arr as $v) {
                if ($v['status'] == "Approved" || $v['status'] == "approved") {
                    if ($v['no_of_days'] < 1) {
                        $current_month_leave = $current_month_leave + $v['no_of_days'];
                    } else {
                        $current_month_leave = $current_month_leave + 1;
                    }
                }
            }
        }
   $leave = 1.25 - $current_month_leave;
   return $leave;
    
    
}

