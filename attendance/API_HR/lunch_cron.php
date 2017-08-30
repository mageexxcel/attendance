<?php

// script die added by arun
echo "no need of this functionality from 28th august 2017";
die;

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
    $not_assign = array();
    $assign_with_data = "";
    $assign_with_no_data = "";
    foreach ($res as $val) {
        $userid = $val['user_Id'];

        $name = $val['name'];
        $slack_channel_id = $val['slack_channel_id'];
        $status = lunch_status($userid, $prev_workdate);
        if ($userid != 302 && $userid != 288 && $userid != 313 && $userid != 320 && $userid != 415 && $userid != 418) {

           if (sizeof($status) <= 0) {

                $d = HR::getUserDayPunchingDetails($userid, $prev_workdate);

                $diff = strtotime($d['out_time']) - strtotime($d['in_time']);
                $diff = abs($diff / 60);

                if ($diff > 300) {

                    $add = addUserWorkingHours($userid, $prev_workdate);

                    $lunch_start = $prev_workdate . " 13:25:01";
                    $lunch_end = $prev_workdate . " 14:25:01";

                    try {
                        $insert = "INSERT INTO lunch_break (user_Id, lunch_start, lunch_end, type) VALUES ($userid, '$lunch_start', '$lunch_end', 1)";
                        $run = Database::DBrunQuery($insert);
                        $hr_msg = "Hi $name ! \n You forgot to put your lunch timing on " . date("jS M ", strtotime($prev_workdate)) . ", so assumed 1 hour \n Added 30 min on your working hours /n In case of any issue contact HR";

                        HR::sendSlackMessageToUser($slack_channel_id, $hr_msg);
                        HR::sendSlackMessageToUser("hr", $hr_msg);
                    } catch (Exception $e) {

                        echo "Error occured while inserting data";
                    }
                }
            }
            if (sizeof($status) > 0 && $status['lunch_end'] == "") {


                $add = addUserWorkingHours($userid, $prev_workdate);
                $lunch_start = $prev_workdate . " 13:25:01";
                $lunch_end = $prev_workdate . " 14:25:01";


                try {
                    $insert = "UPDATE lunch_break SET lunch_start = '$lunch_start',lunch_end = '$lunch_end', type='1' WHERE id =" . $status['id'];
                    $run = Database::DBrunQuery($insert);
                    $hr_msg = "Hi $name ! \n You forgot to put your lunch_exit timing on " . date("jS M ", strtotime($prev_workdate)) . ", so assumed 1 hour \n Added 30 min on your working hours \n In case of any issue contact HR";

                    HR::sendSlackMessageToUser($slack_channel_id, $hr_msg);
                    HR::sendSlackMessageToUser("hr", $hr_msg);
                } catch (Exception $e) {

                    echo "Error occured while inserting data";
                }
            }
        }

        $query = "select machines_list.*,machines_user.user_Id from machines_list left join machines_user on machines_list.id = machines_user.machine_id  where machines_user.user_Id = $userid";
        $runQuery = Database::DBrunQuery($query);
        $row = Database::DBfetchRow($runQuery);

        if ($row != false) {
            $q = "select * from bandwidth_stats where mac ='" . $row['mac_address'] . "'";
            $runQuery = Database::DBrunQuery($q);
            $row = Database::DBfetchRows($runQuery);

            $date = date("Y-m-d");
            $month = date("Y-m");
            $current_month = date("F");
            $arr = array();


            $f1 = $f2 = 0;
            if ($row != false) {
                foreach ($row as $val) {
                    $mac_address = $val['mac'];
                    if ($val['date'] == $date) {
                        $f1 = round($val['rx'], 2);
                        $f2 = round($val['tx'], 2);
                    }

                    if (array_key_exists($mac_address, $arr)) {
                        $arr[$mac_address]['rx_total'] = round($arr[$mac_address]['rx_total'] + $val['rx'], 2);
                        $arr[$mac_address]['tx_total'] = round($arr[$mac_address]['tx_total'] + $val['tx'], 2);
                        $arr[$mac_address]['rx_' . $date] = $f1;
                        $arr[$mac_address]['tx_' . $date] = $f2;
                        if (strpos($val['date'], $month) !== false) {
                            $arr[$mac_address]['rx_month'] = round($arr[$mac_address]['rx_month'] + $val['rx'], 2);
                            $arr[$mac_address]['tx_month'] = round($arr[$mac_address]['tx_month'] + $val['tx'], 2);
                        }
                    } else {
                        $arr[$mac_address]['rx_total'] = round($val['rx'], 2);
                        $arr[$mac_address]['tx_total'] = round($val['tx'], 2);
                        $arr[$mac_address]['rx_' . $date] = $f1;
                        $arr[$mac_address]['tx_' . $date] = $f2;
                        if (strpos($val['date'], $month) !== false) {
                            $arr[$mac_address]['rx_month'] = round($val['rx'], 2);
                            $arr[$mac_address]['tx_month'] = round($val['tx'], 2);
                        } else {
                            $arr[$mac_address]['rx_month'] = $f1;
                            $arr[$mac_address]['tx_month'] = $f2;
                        }
                    }
                }

                $arr = array_sort($arr, 'rx_total', SORT_DESC);

                if (sizeof($arr) > 0) {
                    foreach ($arr as $key => $v2) {
                        $assign_with_data.= $name . " - " . $key . "\n";
                        $assign_with_data.= $current_month . " - " . Mb_or_Gb($v2['tx_month']) . " Up / " . Mb_or_Gb($v2['rx_month']) . " Down - " . Mb_or_Gb($v2['rx_month'] + $v2['tx_month']) . "\n";
                        if($v2['tx_' . $date] == 0){
                          $a = getLasttwoDaysData($userid, $key, $date);
                           if($a == 1 ){
                             $assign_with_data.= " " . date("jS M", strtotime($date)) . " - " . Mb_or_Gb($v2['tx_' . $date]) . " Up / " . Mb_or_Gb($v2['rx_' . $date]) . " Down - " . Mb_or_Gb($v2['rx_' . $date] + $v2['tx_' . $date]) . "\n";
                             $assign_with_data.= " Last three days data not stored. Please check machine\n\n";
                           }
                           else{
                               $assign_with_data.= " " . date("jS M", strtotime($date)) . " - " . Mb_or_Gb($v2['tx_' . $date]) . " Up / " . Mb_or_Gb($v2['rx_' . $date]) . " Down - " . Mb_or_Gb($v2['rx_' . $date] + $v2['tx_' . $date]) . "\n\n";
                           }
                        }
                        else{
                           $assign_with_data.= " " . date("jS M", strtotime($date)) . " - " . Mb_or_Gb($v2['tx_' . $date]) . " Up / " . Mb_or_Gb($v2['rx_' . $date]) . " Down - " . Mb_or_Gb($v2['rx_' . $date] + $v2['tx_' . $date]) . "\n\n";
                        }

                    }
                }
            } else {
                $assign_with_no_data.= $name . " - " . $key . " = ";
                $assign_with_no_data.= "No Data found \n";
            }
        } else {
            $not_assign[] = $name;
        }
    }


    if (!empty($assign_with_data)) {
        $title = "User consumed bandwidth list";
        $slackMessageStatus = HR::sendSlackMessageToUser('hr_system', $assign_with_data);

    }
    if (!empty($assign_with_no_data)) {
        $title = "User bandwidth not found list";
        $slackMessageStatus = HR::sendSlackMessageToUser('hr_system', $assign_with_no_data);

    }
    if (sizeof($not_assign) > 0) {
        $message = "";
        $message.= "List of employee whom Mac address not assigned \n";
        foreach ($not_assign as $v) {
            $message.= $v . "\n";
        }
        if (!empty($message)) {
            $slackMessageStatus = HR::sendSlackMessageToUser('hr_system', $message);

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

function addUserWorkingHours($userid, $date) {
    $result = 0;
    $extra = 30;
    $q3 = "select * from user_working_hours where date = '$date' AND user_Id = $userid";

    $run3 = Database::DBrunQuery($q3);
    $row3 = Database::DBfetchRow($run3);
    if (empty($row3)) {
        $increase_time = date("H:i", strtotime('09:00 + ' . $extra . ' minute'));
        $ins2 = array(
            'user_Id' => $userid,
            'date' => $date,
            'working_hours' => $increase_time,
            'reason' => 'lunch time exceed'
        );
        Database::DBinsertQuery('user_working_hours', $ins2);
    } else {
        $increase_time = date("H:i", strtotime($row3['working_hours'] . '+' . $extra . ' minute'));
        $q4 = "UPDATE user_working_hours SET working_hours = '$increase_time' where id =" . $row3['id'];
        $run4 = Database::DBrunQuery($q2);
    }
    $result = 1;
    return $result;
}

function array_sort($array, $on, $order = SORT_ASC) {
    $new_array = array();
    $sortable_array = array();

    if (count($array) > 0) {
        foreach ($array as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $k2 => $v2) {
                    if ($k2 == $on) {
                        $sortable_array[$k] = $v2;
                    }
                }
            } else {
                $sortable_array[$k] = $v;
            }
        }

        switch ($order) {
            case SORT_ASC:
                asort($sortable_array);
                break;
            case SORT_DESC:
                arsort($sortable_array);
                break;
        }
        foreach ($sortable_array as $k => $v) {
            $new_array[$k] = $array[$k];
        }
    }

    return $new_array;
}

function Mb_or_Gb($data) {
    if ($data >= 1) {
        return $data . " Gb";
    } else {
        return $data * 1000 . " Mb";
    }
}

function getLasttwoDaysData($userid, $mac_address, $date) {

    $result = 0;
    $i = 0;
    $date2 = date("Y-m-d", strtotime(getPreviousWorkDate($date)));
    $date3 = date("Y-m-d", strtotime(getPreviousWorkDate($date2)));
    $arr = array($date, $date2, $date3);
    $pr = HR::getUserMonthLeaves($userid, date("Y"), date("m"));

   //$pr = HR::getUserMonthLeaves($userid, "2017", "04");

    $q = "select * from bandwidth_stats where mac = '$mac_address' AND (date='$date' OR date='$date2' OR date='$date3')";
    $run = Database::DBrunQuery($q);
    $row = Database::DBfetchRows($run);

    if (empty($row)) {
        if (sizeof($pr) > 0) {
            foreach ($arr as $v) {
                $d = date("d", strtotime($v));
            if (array_key_exists($d, $pr) && ($pr[$d]['status']=="Approved" || $pr[$d]['status']=="Pending")) {
                    $i = $i + 1;
                }
            }
            if ($i == 0) {
                $result = 1;
            }
        } else {
            $result = 1;
        }
    } else {
        $result = 0;
    }

    return $result;
}
