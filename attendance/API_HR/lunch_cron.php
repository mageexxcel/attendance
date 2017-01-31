<?php

/*
  Cron file to update lunch break detail of employee if not inserted by them.
 */
error_reporting(0);
ini_set('display_errors', 0);
define("weekoff", "Sunday");
require_once ("c-hr.php");

define("weekoff", "Sunday");

$res = HR::getEnabledUsersList();

$array = array();
$current_date = date("Y-m-d");
//$current_date = "2017-01-12";

$prev_workdate = date("Y-m-d", strtotime(getPreviousWorkDate($current_date)));


$cmonth_name = date("F Y");
$current_day = date('l');
$second_sat = date('Y-m-d', strtotime('second sat of ' . $cmonth_name));
$fourth_sat = date('Y-m-d', strtotime('fourth sat of ' . $cmonth_name));

//get holiday date list
$h = "SELECT * FROM holidays WHERE  date like '%$current_date%'";
$qr =  Database::DBrunQuery($h);
$holiday = Database::DBfetchRow($qr);;



if ($current_day != weekoff && $current_date != $second_sat && $current_date != $fourth_sat && sizeof($holiday) == 0 ) {

foreach ($res as $val) {
$userid = $val['user_Id'];
    $status = lunch_status($userid, $prev_workdate);

    if ($status == 0) {

        $d = HR::getUserDayPunchingDetails($userid, $prev_workdate);
        
        $diff = strtotime($d['out_time']) - strtotime($d['in_time']);
        $diff = abs($diff / 60);

       
        if ($diff > 300) {
            $lunch_start = $prev_workdate." 13:15:01";
            $lunch_end = $prev_workdate." 14:00:01";
            
            try{
             $insert = "INSERT INTO lunch_break (user_Id, lunch_start, lunch_end, type) VALUES ($userid, '$lunch_start', '$lunch_end', 1)";    
            echo $insert."<br>";
             $run = Database::DBrunQuery($insert);  
            }
           catch(Exception $e){
               
               echo "Error occured while inserting data";
               
           } 
            
            
        }

    }
}

}

function lunch_status($user_id, $date) {

    $row = array();
    $q = "SELECT * FROM lunch_break where user_Id = $user_id AND lunch_start like '%$date%'";
    $r = Database::DBrunQuery($q);
    $row = Database::DBfetchRow($r);
    if (sizeof($row) > 0) {
        $status = 1;
    } else {
        $status = 0;
    }

    return $status;
}

function getPreviousWorkDate($date) {

    $prev_date = date("m-d-Y", strtotime($date . '-1 day'));
    

    $c = "select * from attendance where timing like '%$prev_date%'";
    $r = Database::DBrunQuery($c);
    $row = Database::DBfetchRows($r);

    if (sizeof($row) > 0) {
        $status = str_replace("-", "/", $prev_date);
        return $status;
    } else {
        $date = date("Y-m-d", strtotime($date . '-1 day'));
        return getPreviousWorkDate($date);
    }
}
