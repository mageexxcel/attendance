<?php

require_once ("../../connection.php");
error_reporting(E_ALL & ~E_NOTICE);
date_default_timezone_set('UTC');
$de = date("m-d-Y");
$current_month = date("Y-m");
$prev_month = date('m', strtotime($current_month . ' -1 month'));
$prev_year = date('Y', strtotime($current_month . ' -1 month'));

$r_error = 1;
$r_message = "";
$r_data = array();


if (isset($_POST['token']) && $_POST['token'] != "") {
    $token = $_POST['token'];
    $query2 = "SELECT users.*,login_tokens.* FROM users LEFT JOIN login_tokens ON users.id = login_tokens.userid where token = '$token' ";
    $w = mysqli_query($link, $query2) or die(mysqli_error($link));
    while ($r = mysqli_fetch_assoc($w)) {
        $role = $r['type'];
    }
    if (strtolower($role) == "admin" || strtolower($role) == "hr") {
        if (isset($_FILES['image'])) {
            $file_name = $_FILES['image']['name'];
            $file_size = $_FILES['image']['size'];
            $file_tmp = $_FILES['image']['tmp_name'];
            $file_type = $_FILES['image']['type'];
            if ($file_name == "AGL_001.TXT") {
                if (!move_uploaded_file($file_tmp, "upload/" . $file_name)) {
                    $r_message = "File Not uploaded";
                } else {
                    $sendmessage = 1;
                    $attendance = array();
                    $query = "SELECT * FROM attendance";
                    $row = mysqli_query($link, $query) or die();
                    while ($r = mysqli_fetch_assoc($row)) {
                        $attendance[] = $r['timing'];
                    }
                    $PF_file_name = "upload/AGL_001.TXT";
                    $file = fopen($PF_file_name, "r");
                    $i = 0;
                    $data = array();
                    while (!feof($file)) {
                        $line = fgets($file);
                        if ($i == 0) {
                            //first row ignore
                        } else {
                            $line = trim($line);
                            $line = trim(preg_replace('/\s+/', ' ', $line));
                            if (!empty($line)) {
                                $data = explode(" ", $line);
                            }
                            $user_id = $data['2'];
                            $datetime = $data['6'] . " " . $data['7'];
                            if (in_array($datetime, $attendance)) {
                                
                            } else {
                                $q2 = "INSERT INTO attendance (user_id,timing) VALUES ($user_id,'$datetime')";
                                mysqli_query($link, $q2) or die(mysqli_error($link));
                            }
                        }
                        $i++;
                    }

                    $check_date = get_time_array($de, $link);
                    if (sizeof($check_date) == 0) {
                        $r_message = "Please Upload updated attendance sheet";
                        $sendmessage = 0;
                    }
                }
            } else {
                $r_message = "Wrong file inserted";
            }
        }
        $time_table2 = array();
        //$de = "08-09-2016";
        $time_table2 = get_time_array($de, $link);

        $time_table = array();
        $query2 = "SELECT users.*,user_profile.name,user_profile.work_email FROM users LEFT JOIN user_profile ON users.id = user_profile.user_Id where status = 'Enabled' ";
        $w = mysqli_query($link, $query2) or die(mysqli_error($link));
        while ($s = mysqli_fetch_assoc($w)) {
            $sid = $s['id'];
            if (array_key_exists($sid, $time_table2)) {
                $time_table[$sid] = $s;
                $time_table[$sid]['timing'] = $time_table2[$sid]['oppo'];
            } else {
                $time_table[$sid] = $s;
                $time_table[$sid]['timing'][] = "";
            }
        }
//
        if (isset($sendmessage) && $sendmessage == 1) {
            $qv = "SELECT * from admin";
            $qw = mysqli_query($link, $qv) or die(mysqli_error($link));
            while ($qs = mysqli_fetch_assoc($qw)) {
                $client_id = $qs['client_id'];
                $client_secret = $qs['client_secret'];
                $token = $qs['token'];
            }
            $time_table6 = array();
            //$date = "2016-08-09";
            $date = date("Y-m-d");
            $prev_date = date('m-d-Y', strtotime($date . ' -1 day'));
            $hr = "hrfile";
            $hr2 = "hrfile2";
            $time_table6 = get_time_array($prev_date, $link, $hr);
            $ada = $time_table6['date'];
            $pdate = date("d-m-Y", strtotime(str_replace("-", "/", $ada)));
            $day = date('l', strtotime($pdate));
            // echo $ada."--".$day;
            $w = mysqli_query($link, $query2) or die(mysqli_error($link));
            while ($s = mysqli_fetch_assoc($w)) {
                $sid = $s['id'];
                if (array_key_exists($sid, $time_table6)) {
                    $ttable[$sid] = $s;
                    $ttable[$sid]['timing'] = $time_table6[$sid]['oppo'];
                } else {
                    $ttable[$sid] = $s;
                    $ttable[$sid]['timing'][] = "";
                }
            }
            $string = "";
            $string1 = "";
            $string2 = "";
            $string3 = "";
            $string4 = "";
            // echo "<pre>";
            // print_r($ttable);
            //die;
            foreach ($ttable as $valo) {
                $a = current($valo['timing']);
                $a = strtotime(str_replace("-", "/", $a));
                $a1 = date("H:i", $a);
                $b = end($valo['timing']);
                $b = strtotime(str_replace("-", "/", $b));
                $b1 = date("H:i", $b);
                $c = $b - $a;
                $c = date("H:i", $c);
                if (current($valo['timing']) == "") {
                    $a1 = $b1 = 0;
                }
                if ($valo['name'] != "" && $valo['name'] != "Admin") {
//             echo $valo['name']."---".$a1."<br>";
                    if ($a1 == 0) {
                        $string1 = $string1 . $valo['name'] . ":  was absent on " . $day . "\n";
                    }
                    if (strtotime($a1) > strtotime('10:30') && $a1 != 0) {
                        $string4 = $string4 . $valo['name'] . ": Total hours on " . $c . " Entry Time: " . $a1 . " Exit Time: " . $b1 . "\n";
                    }
                    if ($a1 != 0 && strtotime($a1) < strtotime('10:30')) {
                        $string = $string . $valo['name'] . ": Total hours on " . $c . " Entry Time: " . $a1 . " Exit Time: " . $b1 . "\n";
                    }
                }
            }
            foreach ($time_table as $t) {
                $j = str_replace("PM", "", current($t['timing']));
                $j = strtotime(str_replace("-", "/", $j));
                $j1 = date("H:i", $j);
                if (current($t['timing']) == "") {
                    $j1 = 0;
                }
                if ($t['name'] != "" && $t['name'] != "Admin") {
                    if ($j1 == 0) {
                        $string5 = $string5 . $t['name'] . ": Did not Come Yet! \n";
                    }
                    if ($j1 != 0 && strtotime($j1) < strtotime('10:30')) {
                        $string2 = $string2 . $t['name'] . ":  Entry Time: " . $j1 . "\n";
                    }
                    if ($j1 != 0 && strtotime($j1) > strtotime('10:30')) {
                        $string3 = $string3 . $t['name'] . ":  Entry Time: " . $j1 . "\n";
                    }
                }
            }
//echo $string1;
//echo "<hr>";
//echo $string4;
//echo "<hr>";
//echo $string;
//echo "<hr>";
//echo "<hr>";
//echo $string2;
//echo "<hr>";
//echo $string3;
//echo "<hr>";
//echo $string5;
//echo "<hr>";
//echo "<br>";
//D0KGJ5HPH
//die;
            //  send_slack_message($c_id = 'hr_system', $token, $string, $hr, $day);
            if ($string4 != "") {
                $hr4 = "hrfile4";
                //        send_slack_message($c_id = 'hr_system', $token, $string4, $hr4, $day);
            }
            if ($string1 != "") {
                $hr1 = "hrfile1";
                //       send_slack_message($c_id = 'hr_system', $token, $string1, $hr1, $day);
            }
            //  send_slack_message($c_id = 'hr_system', $token, $string2, $hr2);
            if ($string3 != "") {
                $hr3 = "hrfile3";
                //     send_slack_message($c_id = 'hr_system', $token, $string3, $hr3, $day);
            }
            if ($string5 != "") {
                $hr5 = "hrfile5";
                //   send_slack_message($c_id = 'hr_system', $token, $string5, $hr5, $day);
            }
            $url = "https://slack.com/api/im.list?token=" . $token;
            $cid_array = array();
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $result = curl_exec($ch);
            if ($result === false) {
                echo 'Curl error: ' . curl_error($ch);
            } else {
                $channelid_list = json_decode($result, true);
                $cid_array = $channelid_list['ims'];
            }
            curl_close($ch);
            $url = "https://slack.com/api/users.list?client_id=" . $client_id . "&token=" . $token . "&client_secret=" . $client_secret;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $result = curl_exec($ch);
            if ($result === false) {
                echo 'Curl error: ' . curl_error($ch);
            } else {
                $fresult = json_decode($result, true);
            }
            curl_close($ch);
            $are = array();
            if (sizeof($time_table) > 0) {
                foreach ($time_table as $value) {
                    $e = $value['work_email'];
                    $id = $value['id'];
                    foreach ($fresult['members'] as $foo) {
                        $msg = "";
                        if ($e == $foo['profile']['email'] && $e != "") {
                            $are[] = $e;
                            $f = $foo['id'];
                            if (array_key_exists($id, $ttable)) {
                                $time_arr = $ttable[$id];
                                $aa = current($time_arr['timing']);
                                $aa = strtotime(str_replace("-", "/", $aa));
                                $aa = date("h:i A", $aa);
                                $bb = end($time_arr['timing']);
                                $bb = strtotime(str_replace("-", "/", $bb));
                                $bb = date("h:i A", $bb);
                                if (current($time_arr['timing']) == "") {
                                    $aa = $bb = 0;
                                }
                                if ($bb == $aa && $bb != 0) {
                                    $q = mysqli_query($link, "SELECT * FROM `hr_data` WHERE `email` = '$e' AND `date` = '$pdate'");
                                    if (strtotime($bb) <= strtotime("04:30 PM")) {
                                        $msg = $msg . "You didn't put in your exit time on " . $pdate . ", Please contact HR immediately or this day will be considered as a leave.\n";
                                        $ins2 = "INSERT INTO hr_data (user_id, email, entry_time, exit_time, date) VALUES ('$id', '$e', '$aa', '0','$pdate')";
                                    } else {
                                        $msg = $msg . "You didn't put in your entry time on " . $pdate . ", Please contact HR immediately or this day will be considered as a leave.\n";
                                        $ins2 = "INSERT INTO hr_data (user_id, email, entry_time, exit_time, date) VALUES ('$id', '$e', '0', '$bb','$pdate')";
                                    }
                                    if (mysqli_num_rows($q) <= 0) {
                                        mysqli_query($link, $ins2) or die(mysqli_error($link));
                                    }
                                }
                                if ($bb == $aa && $bb == 0) {
                                    $msg = $msg . "You were on leave on " . $pdate . ",\n";
                                }
                                if ($bb != $aa) {
                                    $msg = $msg . "Your Previous working day Entry Time: " . $aa . " Exit Time: " . $bb . "\n";
                                    $q1 = mysqli_query($link, "SELECT * FROM `hr_data` WHERE `email` = '$e' AND `date` = '$pdate'");
                                    if (mysqli_num_rows($q1) <= 0) {
                                        $ins3 = "INSERT INTO hr_data (user_id, email, entry_time, exit_time, date) VALUES ('$id', '$e', '$aa', '$bb','$pdate')";
                                        mysqli_query($link, $ins3) or die(mysqli_error($link));
                                    }
                                }
                            }
                            $ff = saveUserMonthPunching($id, $e, $link);
                            $ffprev = saveUserMonthPunching($id, $e, $link, $prev_year, $prev_month);
                            $c_id = get_channel_id($f, $cid_array);
                            //  if ($e == "meraj.etech@excellencetechnologies.in") {
                            $d = str_replace("PM", "", current($value['timing']));
                            $d = strtotime(str_replace("-", "/", $d));
                            $d1 = date("h:i A", $d);
                            if (current($value['timing']) == "") {
                                $d1 = 0;
                            }
                            if ($d1 == 0) {
                                $msg = $msg . "You have not entered time Today ";
                                //        send_slack_message($c_id, $token, $msg);
                            }
                            if ($d1 != 0 && strtotime($d1) > strtotime('10:30 AM')) {
                                $s = getLateComingInfo($e, $link);
                                if ($s != "") {
                                    $msg = $msg . $s;
                                }
                                $msg = $msg . "Today's Entry Time " . $d1;
                                $hr6 = "hrfile6";
                                //     send_slack_message($c_id, $token, $msg, $hr6);
                            } if ($d1 != 0 && strtotime($d1) <= strtotime('10:30')) {
                                $msg = $msg . "Today's Entry Time " . $d1;
                                //    send_slack_message($c_id, $token, $msg);
                            }
                          //   echo $msg;
                          //  echo "<hr>";
                            //   }
                        }
                    }
//                    if (!in_array($e, $are)) {
//                        echo $value['username'] . "--" . $e . "<br>";
//                    }
                }
            }
           $r_error = 0;
           $r_message = "";
           $r_data = $time_table;
        }
    } else {
        $r_message = "You are not authorize for this operation";
    }
} else {
    $r_message = "Please provide a valid token";
}

$result = array();
$result['error'] = $r_error;
$result['message'] = $r_message;
$result['data'] = $r_data;


return $result;

function get_time_array($date, $link, $hr = false) {
    $final = array();
    $query3 = "SELECT * FROM attendance Where timing like '%$date%' ";
    $t = mysqli_query($link, $query3) or die(mysqli_error($link));
    while ($y = mysqli_fetch_assoc($t)) {
        $time_ta[] = $y;
    }
    if (sizeof($time_ta) > 0) {
        foreach ($time_ta as $er) {
            $u_id = $er['user_id'];
            if (array_key_exists($u_id, $final)) {
                $timing = $er['timing'];
                $final[$u_id] = array(
                    'id' => $er['id'],
                    'user_id' => $u_id,
                    'timing' => $timing,
                );
                $tim[$u_id][] = $er['timing'];
                $final[$u_id]['oppo'] = $tim[$u_id];
            } else {
                $final[$u_id] = $er;
                $tim[$u_id][] = $er['timing'];
                $final[$u_id]['oppo'] = $tim[$u_id];
            }
            $final['date'] = $date;
        }
    }
    if (sizeof($final) == 0 && $hr == "hrfile") {
        $date = str_replace("-", "/", $date);
        $date = date('Y-m-d', strtotime($date));
        $dat = date('m-d-Y', strtotime($date . ' -1 day'));
        $dayz = date('l', strtotime($dat));
        $final = get_time_array($dat, $link, $hr);
    }
    return $final;
}

function get_channel_id($data, $array) {
    foreach ($array as $val) {
        if ($data == $val['user']) {
            return $val['id'];
            break;
        }
    }
}

function sends_slack_message($channelid, $token, $sir = false, $s = false, $day = false) {
    $message = '[{"text": "' . $sir . '", "fallback": "Message Send to Employee", "color": "#36a64f"}]';
    if ($sir == "You have not Entered your time Today") {
        $message = '[{"text": "' . $sir . '", "fallback": "Message Send to Employee", "color": "#AF2111"}]';
    }
    if (isset($s) && $s == "hrfile6") {
        $message = '[{"text": "' . $sir . '", "fallback": "Message Send to Employee", "color": "#AF2111"}]';
    }
    if (isset($s) && $s == "hrfile") {
        $message = '[{"text": "' . $sir . '",  "author_name": "' . $day . ' Attendance Tables (On Time)", "fallback": "Message Send to Hr Channel", "color": "#36a64f"}]';
    }
    if (isset($s) && $s == "hrfile1") {
        $message = '[{"text": "' . $sir . '",  "author_name": "' . $day . ' Attendance Tables (On Leave)", "fallback": "Message Send to Hr Channel", "color": "#F2801D"}]';
    }
    if (isset($s) && $s == "hrfile4") {
        $message = '[{"text": "' . $sir . '",  "author_name": "' . $day . ' Attendance Tables (Late Comers)", "fallback": "Message Send to Hr Channel", "color": "#AF2111"}]';
    }
    if (isset($s) && $s == "hrfile2") {
        $message = '[{"text": "' . $sir . '",  "author_name": "Today Attendance Tables", "fallback": "Message Send to Hr Channel", "color": "#439FE0"}]';
    }
    if (isset($s) && $s == "hrfile3") {
        $message = '[{"text": "' . $sir . '",  "author_name": "Today Attendance Tables (Late Comers)", "fallback": "Message Send to Hr Channel", "color": "#AF2111"}]';
    }
    if (isset($s) && $s == "hrfile5") {
        $message = '[{"text": "' . $sir . '",  "author_name": "Today Attendance Tables (Did not Come Yet!)", "fallback": "Message Send to Hr Channel", "color": "#F2801D"}]';
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

function getLateComingInfo($data, $link) {
    $date = date("m-Y");
    $string = "";
    $query3 = "SELECT * FROM hr_data Where email like '%$data%' AND date like '%$date%' ";
    $t = mysqli_query($link, $query3) or die(mysqli_error($link));
    if (mysqli_num_rows($t) >= 1) {
        while ($row = mysqli_fetch_assoc($t)) {
            if (strtotime($row['entry_time']) > strtotime("10:40 AM")) {
                $arr[] = $row;
            }
        }
        if (sizeof($arr) >= 3) {
            $first = date("dS", strtotime($arr[0]['date']));
            $second = date("dS", strtotime($arr[1]['date']));
            $third = date("dS", strtotime($arr[2]['date']));
            $string = "You have been late more than 3 times already on $first, $second, $third  this month. Make sure to be on time. \n";
        }
    }
    return $string;
}

function saveUserMonthPunching($userid, $email, $link, $cyear = false, $cmonth = false) {

    $year = date("Y");
    $month = date("m");
    $c_day = date("d");
    if ($cyear != "" && $cmonth != "") {
        $year = $cyear;
        $month = $cmonth;
        $c_day = "";
    }
    //$c_day = date("05");
    $list = array();
    $q = "SELECT * FROM attendance Where user_id = $userid";
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
    if ($c_day != "") {
        if (array_key_exists($c_day, $list)) {
            unset($list[$c_day]);
        }
    }

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
            mysqli_query($link, $ins3) or die(mysqli_error($link));
        } else {
            while ($r = mysqli_fetch_assoc($q1)) {
                $id = $r['id'];
            }
            $ins3 = "UPDATE hr_data SET user_id='$userid', email='$email', entry_time='$a', exit_time='$b', date='$pdate' WHERE id = $id";
            mysqli_query($link, $ins3) or die(mysqli_error($link));
        }
    }
    return $list;
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

//die;
?>
