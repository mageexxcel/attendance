<?php

require_once ("../../connection.php");
error_reporting(E_ALL & ~E_NOTICE);
date_default_timezone_set('UTC');

$a =  "09:00";
$b = "01:00";
list($hrs, $mins) = explode(":", $a);
$mins += $hrs * 60;
$m = $mins + 72;
echo date('H:i', mktime(0,$m));
die;



$de = "08-2016";

$query = "SELECT hr_data.*,users.status FROM hr_data LEFT JOIN users ON hr_data.user_id = users.id where users.status='Enabled' AND users.id = 288 AND hr_data.date LIKE '%$de%'";
    $array = array();
    $w = mysqli_query($link, $query) or die(mysqli_error($link));
    while ($s = mysqli_fetch_assoc($w)) {
        $sid = $s['email'];
        $d = strtotime($s['date']);
        if (array_key_exists($sid, $array)) {
            $array[$sid][$d] = $s;
        } else {
            $array[$sid][$d] = $s;
        }
    }
    $arr = array();
    $arr2 = array();
    foreach ($array as $k => $v) {
        ksort($v);
        $arr[$k] = $v;
    }
   
//--- Compensation time slack notification--------------

//---- end-----------
        foreach ($arr as $kk => $vv) {
            // print_r($value);
            foreach ($vv as $f) {
                if ($f['entry_time'] != 0 && $f['exit_time'] != 0) {
                    $ed = strtotime($f['exit_time']) - strtotime($f['entry_time']);
                    $te = date("h:i", $ed);
                    $user_id = $f['user_id'];
                    $cdate = date('Y-m-d', strtotime($f['date']));
                    $working_hour = getWorkingHours($cdate, $link);
                    $half_time = date("h:i", strtotime($working_hour) / 2);
                    if ($working_hour != 0) {
                        $user_working_hour = getUserWorkingHours($user_id, $cdate, $link);
                        if ($user_working_hour != 0) {
                            $working_hour = $user_working_hour;
//                            echo $user_id."-".$cdate."-".$working_hour;
                        }
                        if (strtotime($te) < strtotime($half_time)) {
                            $ed1 = strtotime($half_time) - strtotime($te);
                            $te1 = $ed1 / 60;
                            if ($te1 >= 5) {
                                $vv['ptime'][] = $te1;
                                $vv['ctime'][] = 0;
                                $vv['entry_exit'][] = $f['entry_time'] . "--" . $f['exit_time'] . "--" . $f['date'];
                            }
                            $vv['half'][] = date("m-d-Y", strtotime($f['date']));
                        }
                        if (strtotime($half_time) <= strtotime($te) && strtotime($te) < strtotime($working_hour)) {
                            $ed1 = strtotime($working_hour) - strtotime($te);
                            $te1 = $ed1 / 60;
                            if ($te1 >= 5) {
                                $vv['ptime'][] = $te1;
                                $vv['ctime'][] = 0;
                                $vv['entry_exit'][] = $f['entry_time'] . "--" . $f['exit_time'] . "--" . $f['date'];
                            }
                        }
                        if (strtotime($te) > strtotime($working_hour)) {
                            $ed1 = strtotime($te) - strtotime($working_hour);
                            $te1 = $ed1 / 60;
                            if ($te1 >= 5) {
                                $vv['ctime'][] = $te1;
                                $vv['ptime'][] = 0;
                                $vv['entry_exit'][] = $f['entry_time'] . "--" . $f['exit_time'] . "--" . $f['date'];
                            }
                        }
                    }
                }
                $vv['wdate'][] = date('m-d-Y', strtotime($f['date']));
                $vv['userid'] = $f['user_id'];
            }
            $arr2[$kk] = $vv;
        }
echo "<pre>";
print_r($arr2);
        foreach ($arr2 as $key => $value) {
            $pending = $value['ptime'];
            $compensate = $value['ctime'];
            $entry = $value['entry_exit'];
            $wdate = $value['wdate'];
            $half = array();
            if (array_key_exists('half', $value)) {
                $half = $value['half'];
            }
            $to_compensate = 0;
            $index = 0;
            $rep = array();
            for ($i = 0; $i < sizeof($pending); $i++) {
                if ($pending[$i] != 0 || !empty($rep)) {
                    $at = array();
                    $at['pp'] = $pending[$i];
                    $at['cc'] = $compensate[$i];
                    $at['en'] = $entry[$i];
                    $rep[] = $at;
                }
                $to_compensate = $pending[$i] + $to_compensate;
                if ($to_compensate != 0) {
                    $to_compensate = $to_compensate - $compensate[$i];
                }
                if ($to_compensate <= 0) {
                    $to_compensate = 0;
                    $rep = array();
                }
            }
           echo $to_compensate;
           
           
            
        }
       


function getWorkingHours($data, $link) {
    $result = "09:00";
    $qry = "select * from working_hours where date='$data'";
    $resl = mysqli_query($link, $qry) or die(mysqli_error($link));
    if (mysqli_num_rows($resl) > 0) {
        while ($row = mysqli_fetch_assoc($resl)) {
            $result = $row['working_hours'];
        }
    }
    return $result;
}


function getUserWorkingHours($uid, $date, $link) {
    $result = 0;
    $qry = "select * from user_working_hours where user_Id = '$uid' AND date='$date'";
    $resl = mysqli_query($link, $qry) or die(mysqli_error($link));
    if (mysqli_num_rows($resl) > 0) {
        while ($row = mysqli_fetch_assoc($resl)) {
            $result = $row['working_hours'];
        }
    }
    return $result;
}
