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
$qr = Database::DBrunQuery($h);
$holiday = Database::DBfetchRow($qr);

if ($current_day != weekoff && $current_date != $second_sat && $current_date != $fourth_sat && sizeof($holiday) == 0) {

    foreach ($res as $val) {
        $userid = $val['user_Id'];
        $name = $val['name'];
        $slack_channel_id = $val['slack_channel_id'];
        $status = lunch_status($userid, $prev_workdate);

        if (sizeof($status) <= 0) {
            
            $d = HR::getUserDayPunchingDetails($userid, $prev_workdate);

            $diff = strtotime($d['out_time']) - strtotime($d['in_time']);
            $diff = abs($diff / 60);

            if ($diff > 300) {
                
                $add = addUserWorkingHours($userid,$prev_workdate);
                
                $lunch_start = $prev_workdate . " 13:25:01";
                $lunch_end = $prev_workdate . " 14:25:01";

                try {
                    $insert = "INSERT INTO lunch_break (user_Id, lunch_start, lunch_end, type) VALUES ($userid, '$lunch_start', '$lunch_end', 1)";
                    $run = Database::DBrunQuery($insert);
                    $hr_msg = "Hi $name ! \n You forgot to put your lunch timing on " . date("jS M ", strtotime($prev_workdate)) . ", so assumed 1 hour \n Added 30 min on your working hours /n In case of any issue contact HR";

                    HR::sendSlackMessageToUser($slack_channel_id, $hr_msg);
                    // HR::sendSlackMessageToUser("hr", $hr_msg);
                } catch (Exception $e) {

                    echo "Error occured while inserting data";
                }
            }
        }
        if (sizeof($status) > 0 && $status['lunch_end'] == "") {
           
            
                $add = addUserWorkingHours($userid,$prev_workdate);
                $lunch_start = $prev_workdate . " 13:25:01";
                $lunch_end = $prev_workdate . " 14:25:01";


                try {
                    $insert = "UPDATE lunch_break SET lunch_start = '$lunch_start',lunch_end = '$lunch_end', type='1' WHERE id =" . $status['id'];
                    $run = Database::DBrunQuery($insert);
                    $hr_msg = "Hi $name ! \n You forgot to put your lunch_exit timing on " . date("jS M ", strtotime($prev_workdate)) . ", so assumed 1 hour \n Added 30 min on your working hours \n In case of any issue contact HR";

                    HR::sendSlackMessageToUser($slack_channel_id, $hr_msg);
                    // HR::sendSlackMessageToUser("hr", $hr_msg);
                } catch (Exception $e) {

                    echo "Error occured while inserting data";
                }
           
        }
    }
}

function lunch_status($user_id, $date) {

    $row = array();
    $q = "SELECT * FROM lunch_break where user_Id = $user_id AND lunch_start like '%$date%'";
    $r = Database::DBrunQuery($q);
    $row = Database::DBfetchRow($r);
    return $row;
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

function addUserWorkingHours($userid,$date) {
    $result = 0;
    $extra  = 30;
    $q3 = "select * from user_working_hours where date = '$date' AND user_Id = $userid";
    
    $run3 = Database::DBrunQuery($q3);
    $row3 = Database::DBfetchRow($run3);
    if (empty($row3)) {
         $increase_time = date("H:i", strtotime('09:00 + '.$extra.' minute'));
        $ins2 = array(
            'user_Id' => $userid,
            'date' => $date,
            'working_hours' => $increase_time,
            'reason' => 'lunch time exceed'
        );
        Database::DBinsertQuery('user_working_hours', $ins2);
    } else {
        $increase_time = date("H:i", strtotime($row3['working_hours'].'+'.$extra.' minute'));
        $q4 = "UPDATE user_working_hours SET working_hours = '$increase_time' where id =" . $row3['id'];
        $run4 = Database::DBrunQuery($q2);
    }
    $result = 1;
    return $result;
    
}
