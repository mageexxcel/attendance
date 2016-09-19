<?php

//$link = "https://drive.google.com/file/d/0Bw7RILovH7OLQnJtbHk2cFBoakU4WnBHNVJvUEZXYnFMTTE4/view?usp=sharing"; 
//
//$a = explode("/",$link);
//echo "<pre>";
//print_r($a);

$ins = array(
            'user_Id' => 212,
            'document_type' => "PAN Card",
            'link_1' => "https://drive.google.com/file/d/0Bw7RILovH7OLQnJtbHk2cFBoakU4WnBHNVJvUEZXYnFMTTE4/view?usp=sharing",
            'link_2' => "https://docs.google.com/document/d/1pJ1798WjRxpnYXFNouBMAxUW2wkiNLH_zGEk5WRE5r8/edit?usp=sharing",
            'link_3' => ""
        );
echo "<pre>";
print_r($ins);

foreach($ins as $k => $v){
   if(strpos($v, 'https://') !== false){
        echo $k."---".$v;
        
    }
}
die;


require_once ("../connection.php");
error_reporting(E_ALL & ~E_NOTICE);
date_default_timezone_set('UTC');
$de = date("m-d-Y");
$userid = '383';
$year = "2016";
$month = "08";
$e = "amit@excellencetechnologies.in";
$list = array();
$q = "SELECT * FROM attendance Where user_id = $userid";
$runQuery = mysqli_query($link, $q) or die();
$rows = array();
while ($r = mysqli_fetch_assoc($runQuery)) {
    $rows[] = $r;
}
//    echo "<pre>";
//    print_r($rows);
//    die;
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
//echo "<pre>";
//print_r($allMonthAttendance);
//echo "<hr>";
foreach ($allMonthAttendance as $pp_key => $pp) {
    $daySummary = _beautyDaySummary($pp);
    $list[$pp_key] = $daySummary;
}
echo "<pre>";
array_pop($list);
print_r($list);
die;
foreach ($list as $value) {
    $pdate = $value['date'];
    $a = $value['in_time'];
    $b = $value['out_time'];
    if($a == $b){
        $b=0;
    }
    
    $q1 = mysqli_query($link, "SELECT * FROM `hr_data` WHERE `email` = '$e' AND `date` = '$pdate'");
    if (mysqli_num_rows($q1) <= 0) {
        $ins3 = "INSERT INTO hr_data (user_id, email, entry_time, exit_time, date) VALUES ('$userid', '$e', '$a', '$b','$pdate')";

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
    $r_day = date('l', $TIMESTAMP);


    $r_total_time = $r_extra_time_status = $r_extra_time = '';

    $r_total_time = (int) $outTimeKey - (int) $inTimeKey;

    $r_extra_time = (int) $r_total_time - (int) ( 9 * 60 * 60 );

    if ($r_extra_time < 0) { // not completed minimum hours
        $r_extra_time_status = "-";
        $r_extra_time = $r_extra_time * -1;
    } else if ($r_extra_time > 0) {
        $r_extra_time_status = "+";
    }

    $return = array();
    $return['in_time'] = $inTime;
    $return['out_time'] = $outTime;
    $return['total_time'] = $r_total_time;
    $return['extra_time_status'] = $r_extra_time_status;
    $return['extra_time'] = $r_extra_time;
    $return['date'] = $rf_date;
    return $return;
}

//
//list($hrs, $mins) = explode(":", $working_hour);
//            $mins += $hrs * 60;
//            $endresult = $mins / 2;
//            $half_time = gmdate("H:i", ($endresult * 60));