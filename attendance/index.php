<?php
require_once ("../../connection.php");
error_reporting(E_ALL & ~E_NOTICE);
date_default_timezone_set('UTC');
$de = date("m-d-Y");
$current_month = date("Y-m");
$prev_month = date('m', strtotime($current_month . ' -1 month'));
$prev_year = date('Y', strtotime($current_month . ' -1 month'));

if (isset($_FILES['image'])) {
    $file_name = $_FILES['image']['name'];
    $file_size = $_FILES['image']['size'];
    $file_tmp = $_FILES['image']['tmp_name'];
    $file_type = $_FILES['image']['type'];    
    if ($file_name != "AGL_001.TXT") {
        echo "Wrong file inserted";
    } else {
        
        if (!move_uploaded_file($file_tmp, "upload/" . $file_name)) {
            echo "File Not uploaded";
            die;
        }
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
        $dataKeys = array();
        $attendance_csv = getAttendanceKeys( $link );
        $attendance_csv_keys = json_decode($attendance_csv['value'], true);
        $toBeInsertData = array();

        while (!feof($file)) {
            $line = fgets($file);
            if ($i == 0) {
                //first row ignore
                $line = trim(preg_replace('/\s+/', ' ', $line));
                $dataKeys = explode(" ", $line);              
                foreach( $attendance_csv_keys as $key => $atCsvKey ){ 
                    if( $key == 'user_id' ){
                        $userIdKeys = $atCsvKey;
                    }
                    if( $key == 'time' ){
                        $timingKeys = $atCsvKey;
                    }
                }                                
                foreach( $dataKeys as $k => $dtkey ){
                    $dtkey = trim($dtkey);                    
                    if( in_array( $dtkey, $userIdKeys ) ){
                        $userIdKey = $k;                        
                    }
                    if( in_array( $dtkey, $timingKeys ) ){
                        $timingKey = $k;                        
                    }
                }
                if( !is_numeric($userIdKey) ){
                    echo "UserId key not found";
                    die;
                }
                if( !is_numeric($timingKey) ){
                    echo "Time key not found";
                    die;
                }
                             
            } else {
                $line = trim($line);
                $data = array();
                $datetime = "";
                // $line = trim(preg_replace('/\s+/', ' ', $line));
                if (!empty($line)) {
                    // $data = explode(" ", $line);
                    $data = preg_split("/[\t]/", $line);
                } else {
                    continue;
                }              
                // start old machine format
                // 06-30-2016 01:19:29PM
                // $user_id = $data['2'];
                // $datetime = $data['6'] . " " . $data['7'];
                // end old machine format

                // start new machine format // added by arun on 30th august
                // 2017/07/10 20:31:57
                // need to change this
                $user_id = trim( $data[$userIdKey] );
                if( strlen($user_id) > 5 ){
                    echo "Invalid User Id: " . $user_id . "<br>";
                    continue;
                }
                if( strpos( $data[$timingKey], "-")  ){
                    $datetime = trim(preg_replace('/\s+/', ' ', $data[$timingKey]));
                } else {
                    $explodeDateTime = explode( " ", $data[$timingKey] );
                    $raw_date = trim($explodeDateTime[0]);
                    $raw_time = trim($explodeDateTime[1]);
                    $final_date = date("m-d-Y", strtotime($raw_date));
                    $final_time = date("h:i:sA", strtotime($raw_time));
                    $datetime = $final_date . " " . $final_time;                
                }
                // end new machine format

                if (in_array($datetime, $attendance)) {

                } else {
                    if (strpos($datetime, '01-08-2017') === false) {
                        // below 2 comments line of multiple query to single query
                        // $q2 = "INSERT INTO attendance (user_id,timing) VALUES ($user_id,'$datetime')";
                        // mysqli_query($link, $q2) or die(mysqli_error($link));
                        $toBeInsertData[] = '(' . $user_id . ',"' . $datetime . '")';
                    }
                }                
            }
            $i++;
        }
        
        // above multiple insert query is changed to single insert query on 22 june 2018 by arun
        if( sizeof($toBeInsertData) > 0 ){
             $q2 = 'INSERT INTO attendance (user_id,timing) VALUES' . implode(',', $toBeInsertData);
             mysqli_query($link, $q2) or die(mysqli_error($link));
        }

    }

    // echo $de.'<br/>';

    $check_date = get_time_array($de, $link);

    // echo '<pre>';
    // print_r( $check_date );

    // start this was commented by arun on 22 june, since no use once the uploading is already processed above
    // if (sizeof($check_date) == 0) {
    //     echo "Please Upload updated attendance sheet";
    //     $sendmessage = 0;
    // }
    // end this was commented by arun on 22 june, since no use once the uploading is already processed above

}
$time_table2 = array();
$time_table2 = get_time_array($de, $link);

$time_table = array();
$query2 = "SELECT users.*,user_profile.name,user_profile.work_email,user_profile.slack_msg FROM users LEFT JOIN user_profile ON users.id = user_profile.user_Id where status = 'Enabled' ";
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

if (isset($sendmessage) && $sendmessage == 1) {
    $qv = "SELECT * from admin";
    $qw = mysqli_query($link, $qv) or die(mysqli_error($link));
    while ($qs = mysqli_fetch_assoc($qw)) {
        $client_id = $qs['client_id'];
        $client_secret = $qs['client_secret'];
        $token = $qs['token'];
    }
    $time_table6 = array();
    $date = date("Y-m-d");
    $prev_date = date('m-d-Y', strtotime($date . ' -1 day'));
    $prev_date2 = date('Y-m-d', strtotime($date . ' -1 day'));
    $hr = "hrfile";
    $hr2 = "hrfile2";
    $time_table6 = get_time_array($prev_date, $link, $hr);
    $ada = $time_table6['date'];
    $pdate = date("d-m-Y", strtotime(str_replace("-", "/", $ada)));
    $day = date('l', strtotime($pdate));
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
            if ($a1 == 0) {
               $string1 = $string1 . $valo['name'] . ":  was absent on " . $day ;
                  $r = getleaveinfo($valo['id'],$prev_date2,$link);
               if($r == 1){
                   $string1.=" (Leave Applied)\n";
               }
               else{
                    $string1.=" (Leave Not Applied)\n";
               }
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
               $string5 = $string5 . $t['name'] . ": Did not Come Yet!";
                $r = getleaveinfo($t['id'],$date,$link);
               if($r == 1){
                   $string5.=" (Leave Applied)\n";
               }
               else{
                    $string5.=" (Leave Not Applied)\n";
               }
            }
            if ($j1 != 0 && strtotime($j1) < strtotime('10:30')) {
                $string2 = $string2 . $t['name'] . ":  Entry Time: " . $j1 . "\n";
            }
            if ($j1 != 0 && strtotime($j1) > strtotime('10:30')) {
                $string3 = $string3 . $t['name'] . ":  Entry Time: " . $j1 . "\n";
            }
        }
    }

       send_slack_message($c_id = 'hr_system', $token, $string, $hr, $day);
    if ($string4 != "") {
        $hr4 = "hrfile4";
               send_slack_message($c_id = 'hr_system', $token, $string4, $hr4, $day);
    }
    if ($string1 != "") {
        $hr1 = "hrfile1";
              send_slack_message($c_id = 'hr_system', $token, $string1, $hr1, $day);
    }
      send_slack_message($c_id = 'hr_system', $token, $string2, $hr2);
    if ($string3 != "") {
        $hr3 = "hrfile3";
             send_slack_message($c_id = 'hr_system', $token, $string3, $hr3, $day);
    }
    if ($string5 != "") {
        $hr5 = "hrfile5";
           send_slack_message($c_id = 'hr_system', $token, $string5, $hr5, $day);
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
            $slack_msg = $value['slack_msg'];
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
                    $u_id = get_slack_user_id($f, $cid_array);

                    $d = str_replace("PM", "", current($value['timing']));
                    $d = strtotime(str_replace("-", "/", $d));
                    $d1 = date("h:i A", $d);
                    if (current($value['timing']) == "") {
                        $d1 = 0;
                    }
                    if ($d1 == 0) {
                        $msg = $msg . "You have not entered time Today ";
                        $msg = "Hi <@" . $u_id . "> \n" . $msg;
                        send_slack_message($u_id, $token, $msg);
                        send_slack_message($c_id = 'hr_system', $token, $msg);
                    }
                    if ($d1 != 0 && strtotime($d1) > strtotime('10:30 AM')) {
                        $s = getLateComingInfo($e, $link);
                        if ($s != "") {
                            $msg = $msg . $s;
                        }
                        $msg = $msg . "Today's Entry Time " . $d1;
                        $hr6 = "hrfile6";
                        $msg = "Hi <@" . $u_id . "> \n" . $msg;
                        send_slack_message($u_id, $token, $msg, $hr6);
                        send_slack_message($c_id = 'hr_system', $token, $msg, $hr6);
                    } if ($d1 != 0 && strtotime($d1) <= strtotime('10:30')) {
                        $msg = $msg . "Today's Entry Time " . $d1;
                        $msg = "Hi <@" . $u_id . "> \n" . $msg;
                        send_slack_message($u_id, $token, $msg);
                        send_slack_message($c_id = 'hr_system', $token, $msg);
                    }

                }
            }
            if (!in_array($e, $are)) {
                echo $value['username'] . "--" . $e . "<br>";
            }
        }
    }
}

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

function get_slack_user_id($data, $array) {
    foreach ($array as $val) {
        if ($data == $val['user']) {
            return $val['user'];
            break;
        }
    }
}

function send_slack_message($channelid, $token, $sir = false, $s = false, $day = false) {
    //sleep for 1 seconds to delay SLACK call -- added on 22june2018 by arun
    sleep(1);

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
function getleaveinfo($userid,$date,$link) {
 $result= 0;
 $year = date("Y");
 $month = date("m");
 $list = array();
    $qry = "SELECT * FROM leaves Where user_Id = $userid ";
    $resl = mysqli_query($link, $qry) or die(mysqli_error($link));
    $rows = array();
    while ($row = mysqli_fetch_assoc($resl)) {
        $rows[] = $row;
    }

    foreach ($rows as $pp) {
        $pp_start = $pp['from_date'];
        $pp_end = $pp['to_date'];
        $datesBetween = getDatesBetweenTwoDates($pp_start, $pp_end);

        foreach ($datesBetween as $d) {
            $h_month = date('m', strtotime($d));
            $h_year = date('Y', strtotime($d));

            if ($h_year == $year && $h_month == $month) {
                $h_full_date = date("Y-m-d", strtotime($d));
                $list[] = $h_full_date;
            }
        }
    }
    ksort($list);

    if(in_array($date,$list)){
      $result= 1;
    }

   return $result;
}
function getDatesBetweenTwoDates($startDate, $endDate) {
    $return = array($startDate);
    $start = $startDate;
    $i = 1;
    if (strtotime($startDate) < strtotime($endDate)) {
        while (strtotime($start) < strtotime($endDate)) {
            $start = date('Y-m-d', strtotime($startDate . '+' . $i . ' days'));
            $return[] = $start;
            $i++;
        }
    }
    return $return;
}

function getAttendanceKeys( $link ){      
    $q = " SELECT * FROM config WHERE type = 'attendance_csv' ";
    $res = mysqli_query($link, $q) or die(mysqli_error($link));
    $row = mysqli_fetch_assoc($res);
    return $row;
}

?>
<html>
    <head>
        <meta charset="utf-8">
        <link rel="stylesheet" type="text/css" href="css/bootstrap.css">
        <title>Time</title>
        <link rel="stylesheet" href="css/jquery-ui.css">
        <script src="js/jquery.min.js"></script>
        <script src="js/jquery-ui.min.js"></script>

        <script>
            $(document).ready(function() {
                $("#datepicker").datepicker();
            });
        </script>
    </head>
    <body>

        <div class="container">
            <br><br>
            <div class="row">
                <h3>Time Table</h3><br>
                <div class="col-md-6">

                    <form class="form-inline" action="#" method="POST">
                        <div class="form-group">
                            <label>Search Date</label>
                            <input id="datepicker" name="date" />
                        </div>
                        <button type="submit" name="submit" class="btn btn-default">Search</button>
                    </form>
                </div>

                <form class="form-inline" action="#" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Upload File</label>
                        <input type="file" name="image" />
                    </div>
                    <button type="submit" name="submit" class="btn btn-default">upload</button>
                </form>
            </div>
        </div>

        <hr><br>
        <div class="container">
            <div class="row">
                <table class="table table-hover">
                    <tr>
                        <th>#</th>
                        <th>User Id</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Timing</th>
                        <th>Duration</th>
                    </tr>
                    <?php
                    if (sizeof($time_table) > 0) {
                        $i = 1;
                        $time1 = "";
                        $time2 = "";
                        foreach ($time_table as $val) {
                            $time1 = str_replace("PM", "", current($val['timing']));
                            $time1 = strtotime(str_replace("-", "/", $time1));
                            $time2 = str_replace("PM", "", end($val['timing']));
                            $time2 = strtotime(str_replace("-", "/", $time2));
                            $time3 = $time2 - $time1;
                            $time3 = date("H:i:s", $time3);
                            echo "<tr>";
                            echo "<td>" . $i . "</td>";
                            echo "<td>" . $val['id'] . "</td>";
                            echo "<td>" . $val['name'] . "</td>";
                            echo "<td>" . $val['work_email'] . "</td>";
                            echo "<td>";
                            foreach ($val['timing'] as $d) {
                                echo $d . "<br>";
                            }
                            echo "</td>";
                            echo "<td>" . $time3 . "</td>";
                            echo "</tr>";
                            $i++;
                        }
                    } else {
                        echo "<tr>";
                        echo "<td colspan='6'>No Data To Show</td>";
                        echo "</tr>";
                    }
                    ?>

                </table>
            </div>
        </div>

    </body>
</html>