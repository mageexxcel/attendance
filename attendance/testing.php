<?php

require_once ("../../connection.php");
$year = date("Y");
    $month = '08';
  $email = "deepak@excellencetechnologies.in";  
  $userid = 288;
    //$c_day = date("d");
    //$c_day = date("05");
    $list = array();
    $q = "SELECT * FROM attendance Where user_id = 288";
    $runQuery = mysqli_query($link, $q) or die();
    $rows = array();
    while ($r = mysqli_fetch_assoc($runQuery)) {
        $rows[] = $r;
    }
    $allMonthAttendance = array();
    foreach ($rows as $key => $d) {
        $d_timing = $d['timing'];
        $d_timing = str_replace("-", "/", $d_timing);
        $d_full_date = date("Y-m-d", strtotime($d_timing));
        $d_timestamp = strtotime($d_timing);
        $d_month = date("m", $d_timestamp);
        $d_year = date("Y", $d_timestamp);
        $d_date = date("d", $d_timestamp);
        //$d_date = (int)$d_date;
        if ($d_year == $year && $d_month == $month) {
            $d['timestamp'] = $d_timestamp;
            $allMonthAttendance[$d_date][] = $d;
        }
    }
    foreach ($allMonthAttendance as $pp_key => $pp) {
        $daySummary = _beautyDaySummary($pp);
        $list[$pp_key] = $daySummary;
    }
//    if (array_key_exists($c_day, $list)) {
//        unset($list[$c_day]);
//    }
    echo "<pre>";
    print_r($list);
   // die;
    
    foreach ($list as $value) {
        $pdate = $value['date'];
        $a = $value['in_time'];
        $b = $value['out_time'];
        if ($a == $b) {
            if (strtotime($b) <= strtotime("04:30 PM")) {
                $b = 0;
            } else {
                $a = 0;
            }
        }
        $q1 = mysqli_query($link, "SELECT * FROM `hr_data` WHERE `email` = '$email' AND `date` = '$pdate'");
        if (mysqli_num_rows($q1) <= 0) {
            $ins3 = "INSERT INTO hr_data (user_id, email, entry_time, exit_time, date) VALUES ('$userid', '$email', '$a', '$b','$pdate')";
            
            echo $ins3 . "<br>";
            
            mysqli_query($link, $ins3) or die(mysqli_error($link));
        }
    }
    
    function _beautyDaySummary($dayRaw) {
    $TIMESTAMP = '';
    $numberOfPunch = sizeof($dayRaw);
    $timeStampWise = array();
    foreach ($dayRaw as $pp) {
        $TIMESTAMP = $pp['timestamp'];
        $timeStampWise[$pp['timestamp']] = $pp;
    }
    ksort($timeStampWise);
    $inTimeKey = key($timeStampWise);
    end($timeStampWise);
    $outTimeKey = key($timeStampWise);
    $inTime = date('h:i A', $inTimeKey);
    $outTime = date('h:i A', $outTimeKey);
    $r_date = (int) date('d', $TIMESTAMP);
    $rf_date = date('d-m-Y', $TIMESTAMP);
    $return = array();
    $return['in_time'] = $inTime;
    $return['out_time'] = $outTime;
    $return['date'] = $rf_date;
    return $return;
}