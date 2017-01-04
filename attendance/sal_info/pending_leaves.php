<?php

error_reporting(0);
ini_set('display_errors', 0);
require_once ("c-salary.php");

$arr = Salary::getEnabledUsersList();
echo "<pre>";
foreach ($arr as $k => $v) {
    $userid = $v['user_Id'];
    echo $v['name']; 
    $ss = getleaves($userid);

    $aug = getmonthLeave($userid, '2016', '08');
    $sep = getmonthLeave($userid, '2016', '09');
    $oct = getmonthLeave($userid, '2016', '10');
    $nov = getmonthLeave($userid, '2016', '11');
    $dec = getmonthLeave($userid, '2016', '12');
 
    print_r($ss);

    $july = $ss['final_leave_balance'];
    $total1 = $july + $aug + $sep + $oct + $nov;
    $total = $july + $aug + $sep + $oct + $nov + $dec;
    echo "july=" . $july . "  Aug= " . $aug . "  Sept=" . $sep . "  Oct=" . $oct ." Nov=" . $nov ." Dec=" . $dec  ;
    echo "<br>";
    echo "final balance leave till nov payslip:" . $total1;
    echo "<br>";
    echo "final balance leave till dec payslip:" . $total;
    echo "<hr>";

    //$q = "select * from payslips where user_Id = $userid AND year='2016' AND month = '09'";
    //$runQuery = Salary::DBrunQuery($q);
    //$row = Salary::DBfetchRow($runQuery);
    //$no = Salary::DBnumRows($runQuery);
   // if ($no > 0) {
  //      $q2 = "UPDATE payslips SET final_leave_balance = $total where id =" . $row['id'];
   //     $runQuery2 = Salary::DBrunQuery($q2);
  //  }
}

function getleaves($userid) {
    $q = "select * from payslip where user_Id = $userid ORDER by id DESC LIMIT 1";

    $runQuery = Salary::DBrunQuery($q);
    $row = Salary::DBfetchRow($runQuery);
    return $row;
}

function getmonthLeave($userid, $year, $month) {
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
