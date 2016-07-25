<?php

require_once ("../connection.php");
error_reporting(E_ALL & ~E_NOTICE);
date_default_timezone_set('UTC');
$de = date("m-Y");
$current_date = date("Y-m-d");
$current_month = date("Y-m");
$cmonth_name = date("F Y");
$current_day = date('l'); 
//$current_day = 'Sunday';
$next_month = date('Y-m', strtotime($current_month . ' +1 month'));
$second_sat = date('Y-m-d', strtotime('second sat of '.$cmonth_name));
//$second_sat = '2016-07-25';
$fourth_sat = date('Y-m-d', strtotime('fourth sat of '.$cmonth_name));
//$fourth_sat = '2016-07-25';
if($current_day != "Sunday" && $current_date != $second_sat && $current_date != $fourth_sat){
 echo "Hello";
}

die;
$qv = "SELECT * from admin";
$qw = mysqli_query($link, $qv) or die(mysqli_error($link));
while ($qs = mysqli_fetch_assoc($qw)) {
    $client_id = $qs['client_id'];
    $client_secret = $qs['client_secret'];
    $token = $qs['token'];
}

//---------get slack channel id list of users -----------
$url = "https://slack.com/api/im.list?token=" . $token;
$cid_array = getCURL($url, $data = 'ims');

//-------------get slack userinfo list----------
$url2 = "https://slack.com/api/users.list?client_id=" . $client_id . "&token=" . $token . "&client_secret=" . $client_secret;
$fresult = getCURL($url2);

$query = "SELECT hr_data.*,users.status FROM hr_data LEFT JOIN users ON hr_data.user_id = users.id where users.status='Enabled' AND hr_data.date LIKE '%$de%'";
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
echo "<pre>";
//print_r($array);
$arr = array();
$arr2 = array();
foreach ($array as $k => $v) {
    ksort($v);
    $arr[$k] = $v;
}

if (isset($_GET['dailynotify'])) {
    foreach ($arr as $kk => $vv) {

        $msg = "";
        foreach ($vv as $f) {

            if ($f['entry_time'] == 0 || $f['exit_time'] == 0) {
                $key = $f['email'];
                $date = $f['date'];
                $entry = $f['entry_time'];
                $exit = $f['exit_time'];

                foreach ($fresult['members'] as $foo) {

                    if ($key == $foo['profile']['email'] && $key != "") {
                        $f = $foo['id'];
                        // $f = "U0FJZ0KDM";
                        $name = $foo['real_name'];
                        $c_id = get_channel_id($f, $cid_array);

                        $r = date('H:i', mktime(0, $to_compensate));
                        //  echo $key."----".$f."-----".$c_id;
                        if ($entry == 0) {
                            $msg = $msg . " You have to not entered entry time on " . $date . "\n";
                        } else {

                            $msg = $msg . " You have to not entered exit time on " . $date . "\n";
                        }
                    }
                }
            }
        }
        if ($msg != "") {
            $newmsg = "Hi " . $name . "\n" . $msg . "Contact HR asap to fix this";
            echo $newmsg;
            // send_slack_message($c_id, $token, $newmsg);
        }
    }

    //-- get one or two month employee completed slack message on hr channel----- 
    $one_month = date('Y-m-d', strtotime($current_date . ' -1 month'));
    $two_month = date('Y-m-d', strtotime($current_date . ' -2 month'));

    $q = "SELECT users.*,user_profile.name,user_profile.work_email,user_profile.dateofjoining FROM users LEFT JOIN user_profile ON users.id = user_profile.user_Id where users.status = 'Enabled' AND (user_profile.dateofjoining ='" . $one_month . "' OR user_profile.dateofjoining ='" . $two_month . "')";
    //echo $q;
    $rq = mysqli_query($link, $q) or die(mysqli_error($link));
    if (mysqli_num_rows($rq) > 0) {
        $newmes = "";
        while ($row = mysqli_fetch_assoc($rq)) {
            // print_r($row);
            $key = $row['work_email'];
            $name = $row['name'];
            $join_date = $row['dateofjoining'];

            if ($join_date == $one_month) {
                $msg = $name . " has completed one month as per date " . date("d-m-Y", strtotime(str_replace("-", "/", $join_date))) . "\n";
            } else {

                $msg = $name . " has completed two month as per date " . date("d-m-Y", strtotime(str_replace("-", "/", $join_date))) . "\n";
            }
            $newmes = $newmes . " " . $msg;
        }

        echo $newmes;
          //  send_slack_message($c_id = 'hr', $token, $newmes);
    }
    //--end get one or two month employee completed slack message on hr channel-----
}

//--- Compensation time slack notification--------------
if (isset($_GET['pending'])) {

    //---------get all working day of current month.--------

    $list = array();
    for ($d = 1; $d <= 31; $d++) {
        $time = mktime(12, 0, 0, date('m'), $d, date('Y'));
        if (date('m', $time) == date('m'))
            $list[] = date('m-d-Y', $time);
    }

    foreach ($list as $getd) {
        $de = 0;
        $de = getData($getd, $link);
        if ($de != 0) {
            $set[] = $getd;
        }
    }
    array_pop($set);
    array_pop($set);

//---- end-----------


    foreach ($arr as $kk => $vv) {
        // print_r($value);
        foreach ($vv as $f) {

            if ($f['entry_time'] != 0 && $f['exit_time'] != 0) {
                $ed = strtotime($f['exit_time']) - strtotime($f['entry_time']);
                $te = date("h:i", $ed);
                $cdate = date('Y-m-d', strtotime($f['date']));
                $working_hour = getWorkingHours($cdate, $link);
                $half_time = date("h:i", strtotime($working_hour) / 2 + 3600);

                if ($working_hour != 0) {

                    if (strtotime($te) < strtotime($half_time)) {
                        $ed1 = strtotime($half_time) - strtotime($te);
                        $te1 = $ed1 / 60;
                        if ($te1 > 0) {
                            $vv['ptime'][] = $te1;
                            $vv['ctime'][] = 0;
                            $vv['entry_exit'][] = $f['entry_time'] . "--" . $f['exit_time'] . "--" . $f['date'];
                        }

                        $vv['half'][] = date("m-d-Y", strtotime($f['date']));
                    }
                    if (strtotime($half_time) <= strtotime($te) && strtotime($te) < strtotime($working_hour)) {
                        $ed1 = strtotime($working_hour) - strtotime($te);
                        $te1 = $ed1 / 60;
                        $vv['ptime'][] = $te1;
                        $vv['ctime'][] = 0;
                        $vv['entry_exit'][] = $f['entry_time'] . "--" . $f['exit_time'] . "--" . $f['date'];
                    }
                    if (strtotime($te) > strtotime($working_hour)) {
                        $ed1 = strtotime($te) - strtotime($working_hour);
                        $te1 = $ed1 / 60;
                        $vv['ctime'][] = $te1;
                        $vv['ptime'][] = 0;
                        $vv['entry_exit'][] = $f['entry_time'] . "--" . $f['exit_time'] . "--" . $f['date'];
                    }
                }
            }
            $vv['wdate'][] = date('m-d-Y', strtotime($f['date']));
            $vv['userid'] = $f['user_id'];
        }



        $arr2[$kk] = $vv;
    }
//    echo "<pre>";
//    print_r($arr2);
//    echo "<br>";
//    die;

    foreach ($arr2 as $key => $value) {
        $pending = $value['ptime'];
        $compensate = $value['ctime'];
        $entry = $value['entry_exit'];
        $wdate = $value['wdate'];
        $half = array();
        if (array_key_exists('half', $value)) {
            $half = $value['half'];
        }


        echo "<pre>";
//  print_r($pending);
//  print_r($compensate);
//  print_r($entry);
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
            }
        }
        // print_r($rep);
        echo "<hr>";
        if ($to_compensate > 10) {
            // echo $to_compensate;
            // echo "<br>";
            $msg = "";
            foreach ($fresult['members'] as $foo) {
                if ($key == $foo['profile']['email'] && $key != "") {
                    //$f = $foo['id'];
                    $f = "U0FJZ0KDM";
                    //  $rname = $foo['real_name'];
                    // $c_id = get_channel_id($f, $cid_array);

                    $r = date('H:i', mktime(0, $to_compensate));
                    //  echo $key."----".$f."-----".$c_id;
                    $msg = $msg . "Hi " . $foo['real_name'] . " You have to compensate " . $r . " minutes \n";
                    $msg = $msg . "Details: \n";
                    foreach ($rep as $r) {
                        $dt = explode("--", $r['en']);
                        if ($r['pp'] == 0) {

                            $msg = $msg . "On " . $dt[2] . " Entry Time: " . $dt[0] . " Exit Time: " . $dt[1] . "  Compensated: " . $r['cc'] . " minutes \n";
                        } else {
                            $msg = $msg . "On " . $dt[2] . " Entry Time: " . $dt[0] . " Exit Time: " . $dt[1] . "  Pending: " . $r['pp'] . " minutes \n";
                        }
                    }

                    //  send_slack_message($c_id = 'hr', $token, $msg);
                    echo $msg;
                    echo "<br>";
                }
            }
        }
        $uid = $value['userid'];
//echo "<pre>";
//print_r($wdate);
//die;
        if (sizeof($wdate) > 0) {
            $diff = array_diff($set, $wdate);

            $arr = getLeaveNotification($uid, $link);

            $diff2 = array_diff($diff, $arr);

            $mm = "";
            if (sizeof($diff2) > 0) {

                foreach ($diff2 as $v) {
                    $mm = $mm . "You have not applied your leave on " . date("d-m-Y", strtotime(str_replace("-", "/", $v))) . "\n";
                }
            }
        }

        if (sizeof($half) > 0) {
            $arr = getLeaveNotification($uid, $link);

            foreach ($half as $h) {
                if (!in_array($h, $arr)) {
                    $mm = $mm . "You have not applied for halfday on " . date("d-m-Y", strtotime(str_replace("-", "/", $h))) . "\n";
                }
            }
        }

        if (!empty($mm)) {
            $msg2 = "";
            foreach ($fresult['members'] as $foo) {
                if ($key == $foo['profile']['email'] && $key != "") {
                    $f = $foo['id'];
                    // $f = "U0FJZ0KDM";
                    $rname = $foo['real_name'];
                    $c_id = get_channel_id($f, $cid_array);

                    //  echo $key."----".$f."-----".$c_id;
                    $msg2 = $msg2 . "Hi " . $rname . "\n";
                    $msg2 = $msg2 . $mm . "Please apply asap on HR System";


                  //  send_slack_message($c_id, $token, $msg2);
                    echo $msg2;
                    echo "<br>";
                }
            }
        }
    }
}
//--end compensate slack notification----------
//---------Applied leave messages to Hr channel-------------
if (isset($_GET['leave'])) {
    $raw = array();
    $ss = "SELECT leaves.*,user_profile.name FROM leaves LEFT JOIN user_profile ON leaves.user_Id = user_profile.user_Id WHERE applied_on LIKE '%$current_month%' OR applied_on LIKE '%$next_month%'";

    $sw = mysqli_query($link, $ss) or die(mysqli_error($link));
    while ($row = mysqli_fetch_assoc($sw)) {
        $raw[] = $row;
    }

    $msg1 = "";
    $msg2 = "";
    $msg3 = "";
    foreach ($raw as $vale) {

        $from1 = date('Y-m-d', strtotime($vale['from_date'] . ' -7 day'));
        $from2 = date('Y-m-d', strtotime($vale['from_date'] . ' -3 day'));
        $from3 = date('Y-m-d', strtotime($vale['from_date'] . ' -1 day'));
        $changefrom = date('dS M(D)', strtotime($vale['from_date']));
        $changeto = date('dS M(D)', strtotime($vale['to_date']));
        if ($from1 == $current_date) {
            $msg1 = $msg1 . $vale['name'] . " had appplied for leave from " . $changefrom . " to " . $changeto . ". Reason : " . $vale['reason'] . " (" . $vale['status'] . ") \n";
        }
        if ($from2 == $current_date) {
            $msg2 = $msg2 . $vale['name'] . " had appplied for leave from " . $changefrom . " to " . $changeto . ". Reason : " . $vale['reason'] . " (" . $vale['status'] . ") \n";
        }
        if ($from3 == $current_date) {
            $msg3 = $msg3 . $vale['name'] . " had appplied for leave from " . $changefrom . " to " . $changeto . ". Reason : " . $vale['reason'] . " (" . $vale['status'] . ") \n";
        }
    }

    if ($msg1 != "") {
        $hr1 = "hrfile1";
        //  send_slack_message($c_id = 'hr', $token, $msg1, $hr1);
    }
    if ($msg2 != "") {
        $hr2 = "hrfile2";
       //  send_slack_message($c_id = 'hr', $token, $msg2, $hr2);
    }

    if ($msg3 != "") {
        $hr3 = "hrfile3";
       // send_slack_message($c_id = 'hr', $token, $msg3, $hr3);
    }

    echo $msg1 . "<br>" . $msg2 . "<br>" . $msg3 . "<br>";
}

//--end applied leave slack message to hr ------------------ 
//---get channel id of a user---------
function get_channel_id($data, $array) {
    foreach ($array as $val) {
        if ($data == $val['user']) {
            return $val['id'];
            break;
        }
    }
}

//--------Send slack message------------
function send_slack_message($channelid, $token, $sir = false, $s = false, $day = false) {


    $message = '[{"text": "' . $sir . '", "fallback": "Message Send to Employee", "color": "#36a64f"}]';

    if (isset($s) && $s == "hrfile1") {

        $message = '[{"text": "' . $sir . '",  "author_name": " 7 Day before leave notification ", "fallback": "Message Send to Hr Channel", "color": "#00C1F2"}]';
    }
    if (isset($s) && $s == "hrfile2") {

        $message = '[{"text": "' . $sir . '",  "author_name": " 3 Day before leave notification ", "fallback": "Message Send to Hr Channel", "color": "#F2801D"}]';
    }
    if (isset($s) && $s == "hrfile3") {

        $message = '[{"text": "' . $sir . '",  "author_name": " 1 Day before leave notification ", "fallback": "Message Send to Hr Channel", "color": "#C61E0B"}]';
    }


    $room = $channelid;
    $message = str_replace("", "%20", $message);

    $icon = ":boom:";

    $url = "https://slack.com/api/chat.postMessage?token=" . $token . "&attachments=" . urlencode($message) . "&channel=" . $room;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    // echo var_dump($result);
    if ($result === false) {
        echo 'Curl error: ' . curl_error($ch);
        $success = "not send";
    } else {
        $success = "send";
    }

    curl_close($ch);
}

//----Get dates of working days in curerent month------------
function getData($data, $link) {
    $result = 0;
    $qry = "select * from attendance where timing like '%$data%'";
    $resl = mysqli_query($link, $qry) or die(mysqli_error($link));
    if (mysqli_num_rows($resl) >= 1) {
        $result = 1;
        return $result;
    } else {
        return $result;
    }
}

//------Get leave detail of employee of current month.
function getLeaveNotification($data, $link) {
    $date = date("Y-m");
    $result = array();
    $qry = "select * from leaves where user_Id=" . $data . " AND from_date like '%$date%'";

    $resl = mysqli_query($link, $qry) or die(mysqli_error($link));
    while ($row = mysqli_fetch_assoc($resl)) {
        if (sizeof($row) > 0) {

            $from = $row['from_date'];
            $to = $row['to_date'];
            $datediff = strtotime($to) - strtotime($from);
            $no = floor($datediff / (60 * 60 * 24));
            $f = $from;
            for ($i = 0; $i <= $no; $i++) {

                $result[] = date("m-d-Y", strtotime($f . '+' . $i . ' day'));
            }
        }
    }
    return $result;
}

// ----------Run curl url------
function getCURL($url, $data = false) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);

    if ($result === false) {
        $rest = 'Curl error: ' . curl_error($ch);
    } else {
        $rest = json_decode($result, true);
        if ($data == 'ims') {
            $rest = $rest['ims'];
        }
    }
    return $rest;
    curl_close($ch);
}

function getWorkingHours($data, $link) {

    $result = 0;
    $qry = "select * from working_hours where date='$data'";
    $resl = mysqli_query($link, $qry) or die(mysqli_error($link));
    if (mysqli_num_rows($resl) > 0) {
        while ($row = mysqli_fetch_assoc($resl)) {
            $result = $row['working_hours'];
        }
    }

    return $result;
}
