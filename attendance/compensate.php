<?php

require_once ("../../connection.php");
require_once ("sal_info/config.php");
error_reporting(E_ALL & ~E_NOTICE);
date_default_timezone_set('UTC');

define("hr_system", "hr_system");
define("weekoff", "Sunday");
define("reason", "previous month pending time"); // reason for prevous month pending time.

$de = date("m-Y");
$current_date = date("Y-m-d");
//$current_date = "2016-10-03";
$current_month = date("Y-m");
//$current_month = "2016-10";
$cmonth_name = date("F Y");
$current_day = date('l');
$next_month = date('Y-m', strtotime($current_month . ' +1 month'));
$prev_month = date('Y-m', strtotime($current_month . ' -1 month'));
$p_month = date('m-Y', strtotime($current_month . ' -1 month'));
$second_sat = date('Y-m-d', strtotime('second sat of ' . $cmonth_name));
$fourth_sat = date('Y-m-d', strtotime('fourth sat of ' . $cmonth_name));
//get holiday date list
$h = "SELECT * FROM holidays WHERE  date like '%$current_date%'";
$qr = mysqli_query($link, $h) or die(mysqli_error($link));
$holiday = mysqli_num_rows($qr);

if ($current_day != weekoff && $current_date != $second_sat && $current_date != $fourth_sat && $holiday == 0) {
    $qv = "SELECT * from admin";
    $qw = mysqli_query($link, $qv) or die(mysqli_error($link));
    while ($qs = mysqli_fetch_assoc($qw)) {
        $client_id = $qs['client_id'];
        $client_secret = $qs['client_secret'];
        $token = $qs['token'];
    }
//---------get slack channel id list of users -----------
    $url = $slack_channel_id_url . $token;
    $cid_array = getCURL($url, $data = 'ims');
//-------------get slack userinfo list----------
    $url2 = $get_all_slack_user_list_url . $client_id . "&token=" . $token . "&client_secret=" . $client_secret;
    $fresult = getCURL($url2);

//---get all user in and out detail of current month.    
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
    $arr = array();
    $arr2 = array();
    foreach ($array as $k => $v) {
        ksort($v);
        $arr[$k] = $v;
    }

    //-- get slack notification for user not entering in time or out time 
    if (isset($_GET['dailynotify'])) {

        //-- send slack notification if employee miss entry or exit time   
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

                            if ($entry == 0) {
                                $msg = $msg . " You have to not entered entry time on " . $date . "\n";
                            } else {
                                $msg = $msg . " You have to not entered exit time on " . $date . "\n";
                            }
                        }
                    }
                }
            }

            $slack_msg = getSlackMsgSendStatus($kk, $link);

            if ($msg != "" && $slack_msg == 0) {
                $newmsg = "Hi " . $name . "\n" . $msg . "Contact HR asap to fix this";
                echo $newmsg;
            //    send_slack_message($c_id, $token, $newmsg); // send slack message
            }
        }


        //-- get one or two month employee completed slack message on hr channel----- 
        $one_month = date('Y-m-d', strtotime($current_date . ' -1 month'));
        $two_month = date('Y-m-d', strtotime($current_date . ' -2 month'));
        $one_week = date('Y-m-d', strtotime($current_date . ' -7 day'));
        $q = "SELECT users.*,user_profile.name,user_profile.work_email,user_profile.dateofjoining FROM users LEFT JOIN user_profile ON users.id = user_profile.user_Id where users.status = 'Enabled'";
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
                    $newmes = $newmes . " " . $msg;
                } if ($join_date == $two_month) {
                    $msg = $name . " has completed two month as per date " . date("d-m-Y", strtotime(str_replace("-", "/", $join_date))) . "\n";
                    $newmes = $newmes . " " . $msg;
                }
            }
            // slack notification if user has completed one week in company.-----      
            $aa = getslacklist($fresult, $cid_array);
            if (sizeof($aa) > 0) {
                foreach ($aa as $av) {
                    if ($one_week == $av['date']) {
                        $msg = $av['name'] . " has completed completed 1 week. Create HR login and Fingerprint  \n";
                        $newmes = $newmes . " " . $msg;
                    }
                }
            }
            if ($newmes != "") {
                echo $newmes;
             //   send_slack_message($c_id = hr_system, $token, $newmes); // send slack message to hr channel
            }

            if ($newmes == "") {
                $newmes = "No Message daily notify url run";
                echo $newmes;
              //  send_slack_message($c_id = hr_system, $token, $newmes); // send slack message to hr channel
            }
            // -- end slack notification if user has completed one week in company. ---  
        }
        //--end get one or two month employee completed slack message on hr channel-----
    }


//--- Compensation time, previous month pending time and leave not applied slack notification--------------
    if (isset($_GET['pending'])) {
        //---------send notification of previous month pending time.--------
        $qu = "SELECT users.*,user_profile.name,user_profile.work_email FROM users LEFT JOIN user_profile ON users.id = user_profile.user_Id where status = 'Enabled' AND users.username != 'admin'";
        $wq = mysqli_query($link, $qu) or die(mysqli_error($link));
        while ($qs = mysqli_fetch_assoc($wq)) {
            $wmail = $qs['work_email'];
            $previous_month_time = getUserPreviousMonthTime($qs['id'], $p_month, $link); //  get user previous month pending time.
            $ptime = 0;
            if ($previous_month_time > 0) {
                $ptime = date('H:i', mktime(0, $previous_month_time));
                $reason = reason;
                $ui = getMonthWorkingDays($year = date('Y'), $month = date('m'), $link); // get working days of current month.
              //  $employee_status = checkUserstatus($qs['id'], $current_date, $link); // check employee present on current date or not.

                $whour_already_updated = checkUserWorkingHours($qs['id'], $prev_month, $link); // check employee pending time already updated or not

                if ($whour_already_updated == 0) {
                    $pdate = $current_date;
                    $qt = "INSERT INTO users_previous_month_time (user_Id,extra_time,year_and_month,date) value (" . $qs['id'] . ", '$ptime', '$prev_month', '$pdate')";
                    $updt = mysqli_query($link, $qt) or die(mysqli_error($link));
                    $prev_mtime[$wmail] = $qs;
                    $prev_mtime[$wmail]['pending'] = $previous_month_time;
                    $prev_mtime[$wmail]['worktime'] = $ptime;
                }
            }
        }


//        if (sizeof($prev_mtime) > 0) {
//            foreach ($prev_mtime as $kk => $vao) {
//                $pmessage = "";
//                foreach ($fresult['members'] as $foo) {
//                    if ($kk == $foo['profile']['email'] && $kk != "") {
//                        $f = $foo['id'];
//                        //$f = "U0FJZ0KDM";
//                        $c_id = get_channel_id($f, $cid_array);
//                        $r = date('H:i', mktime(0, $to_compensate));
//                        $pmessage = $pmessage . "Hi " . $foo['real_name'] . " Your Working hour time for " . $pdate . " is increased to " . $vao['worktime'] . " \n";
//                        $pmessage = $pmessage . "Details: \n";
//                        $pmessage = $pmessage . "Previous Month Pending Time " . $vao['pending'] . " minutes \n";
//                        $pmessage = $pmessage . "Incase of issues, contact HR ";
//                        $slk_msg = getSlackMsgSendStatus($kk, $link);
//                        if ($slk_msg == 0) {
//                       //     send_slack_message($c_id, $token, $pmessage); // send slack notification to employee channel  
//                        }
//                   //     send_slack_message($c_id = hr_system, $token, $pmessage); // send slack notification to hr channel
//                        echo $pmessage;
//                        echo "<br>";
//                    }
//                }
//            }
//        }
        //-- end  send notification of previous month pending time ---------
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

//---- end all working day of current month-----------

        foreach ($arr as $kk => $vv) {

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
                            //     echo $user_id."-".$cdate."-".$working_hour;
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
                            $hd = getUserHalfDay($user_id, $cdate, $link);
                            if ($hd != 0) {
                                
                            } else {
                                $ed1 = strtotime($working_hour) - strtotime($te);
                                $te1 = $ed1 / 60;
                                if ($te1 >= 5) {
                                    $vv['ptime'][] = $te1;
                                    $vv['ctime'][] = 0;
                                    $vv['entry_exit'][] = $f['entry_time'] . "--" . $f['exit_time'] . "--" . $f['date'];
                                }
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

        // calculate employee pending time and send slack notification

        foreach ($arr2 as $key => $value) {
            $pending = $value['ptime'];
            $compensate = $value['ctime'];
            $entry = $value['entry_exit'];
            $wdate = $value['wdate'];
            $slack = getSlackMsgSendStatus($key, $link);

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
            $msg = "";
            if ($to_compensate >= 10) {  // if pending time is greater than 10 min
                foreach ($fresult['members'] as $foo) {
                    if ($key == $foo['profile']['email'] && $key != "") {
                        $f = $foo['id'];

                        $c_id = get_channel_id($f, $cid_array);
                        $r = date('H:i', mktime(0, $to_compensate));
                        $msg = $msg . "Hi " . $foo['real_name'] . " You have to compensate " . $r . " minutes \n";
                        $msg = $msg . "Details: \n";
                        foreach ($rep as $r) {
                            $dt = explode("--", $r['en']);
                            if ($r['pp'] == 0) {
                                $msg = $msg . "On " . $dt[2] . " Entry Time: " . $dt[0] . " Exit Time: " . $dt[1] . "  Compensated: " . $r['cc'] . " minutes \n";
                            } else {
                                $msg = $msg . "On " . $dt[2] . " Entry Time: " . $dt[0] . " Exit Time: " . $dt[1] . "  Less: " . $r['pp'] . " minutes \n";
                            }
                        }
                        if ($to_compensate >= 180) { // if pending time is more than 3 hr
                            $msg = $msg . "*If compensation is more than 3hr it will result in half day leave, so please compensate your time asap* \n";
                        }
                        $msg = $msg . "Incase of issues, contact HR ";
                        if ($slack == 0) {
                         //   send_slack_message($c_id, $token, $msg); // send slack notification to employee  
                        }
                     //   send_slack_message($c_id = hr_system, $token, $msg); // send slack notification to hr channel 
                        echo $msg;
                        echo "<br>";
                    }
                }
            }
            $uid = $value['userid'];
            $mm = "";
            // -- notificaiton for previous month pending time upto current month 7th date   
            if (strtotime($current_date) <= strtotime(date("Y-m-07"))) {
                //  $mm = $mm . getPrevMonthLeave($uid, $p_month, $link);
            }
            // -- end
            // check if employee applied leave or not   
            if (sizeof($wdate) > 0) {
                $diff = array_diff($set, $wdate);
                $arr = getLeaveNotification($uid, $link);
                $diff2 = array_diff($diff, $arr);
                if (sizeof($diff2) > 0) {
                    foreach ($diff2 as $v) {
                        $mm = $mm . "You have not applied your leave on " . date("d-m-Y", strtotime(str_replace("-", "/", $v))) . "\n";
                    }
                }
            }
            // check if employee applied for half day or not  
            if (sizeof($half) > 0) {
                $arr = getLeaveNotification($uid, $link);
                foreach ($half as $h) {
                    if (!in_array($h, $arr)) {
                        $mm = $mm . "You have not applied for halfday on " . date("d-m-Y", strtotime(str_replace("-", "/", $h))) . "\n";
                    }
                }
            }
            // send slack notification if leave not applied.    
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
                        if ($slack == 0) {
                    //        send_slack_message($c_id, $token, $msg2); // send slack message to employee
                        }
                   //     send_slack_message($c_id = hr_system, $token, $msg2); // send slack message to hr channel
                        echo $msg2;
                        echo "<br>";
                    }
                }
            }
        }
      //  send_slack_message($c_id = hr_system, $token, $m = 'Pending time message send url run'); // slack notification to tell cron has run
    }
//--end compensate slack notification----------
//---------Applied leave messages to Hr channel-------------
    if (isset($_GET['leave'])) {
        $raw = array();
        $ss = "SELECT leaves.*,user_profile.name,user_profile.user_Id,user_profile.slack_id FROM leaves LEFT JOIN user_profile ON leaves.user_Id = user_profile.user_Id WHERE applied_on LIKE '%$current_month%' OR applied_on LIKE '%$next_month%' OR applied_on LIKE '%$prev_month%'";
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
                $assign_machine = getAssignDeviceStatus($vale['user_Id'], $link);
                if(sizeof($assign_machine) > 0){
                   $c_id = get_channel_id($vale['slack_id'], $cid_array);
                   $empmsg = "Hi ". $vale['name'] ."!!\n Please leave the ";
                   $i=0;
                   foreach($assign_machine as $vf){
                       if($i > 0){
                          $empmsg.= ", ".$vf['machine_name']." ".$vf['machine_type']." "; 
                       }
                       else{
                         $empmsg.= $vf['machine_name']." ".$vf['machine_type']." ";  
                       }
                       $i++;
                   }
                   $empmsg.="assigned to you with HR or reporting senior before you take leave. Else you would be help liable for the safe keeping of your assigned device";
                   send_slack_message($c_id , $token, $empmsg);
                }
                
                $msg3 = $msg3 . $vale['name'] . " had appplied for leave from " . $changefrom . " to " . $changeto . ". Reason : " . $vale['reason'] . " (" . $vale['status'] . ") \n";
            }
        }
        //----slack notification 7 days before employee applied leave starts   
        if ($msg1 != "") {
            $hr1 = "hrfile1";
         //   send_slack_message($c_id = hr_system, $token, $msg1, $hr1);
        }
        //----slack notification 3 days before employee applied leave starts    
        if ($msg2 != "") {
            $hr2 = "hrfile2";
         //   send_slack_message($c_id = hr_system, $token, $msg2, $hr2);
        }
        //----slack notification 1 days before employee applied leave starts    
        if ($msg3 != "") {
            $hr3 = "hrfile3";
         //   send_slack_message($c_id = hr_system, $token, $msg3, $hr3);
        }
        if ($msg3 == "" && $msg2 == "" && $msg1 == "") {
            $no_msg = "No Leave notification";
            echo $no_msg;
        //    send_slack_message($c_id = hr_system, $token, $no_msg);
        }
        echo $msg1 . "<br>" . $msg2 . "<br>" . $msg3 . "<br>";
        //--end applied leave slack message to hr ------------------ 
//----update profile pic mad phone no. slack message-----   

       foreach ($fresult['members'] as $vol) {
            $update_msg = "";
            $ph_no = "";
            $image = "";
            $email_adr = $vol['profile']['email'];
            if ($vol['deleted'] == "" && $vol['is_primary_owner'] == "" && $vol['id'] != "USLACKBOT" && $vol['is_bot'] == false && (!array_key_exists("image_original", $vol['profile']))) {
//          $fr[] = $vol; 
                $f = $vol['id'];
                $update_msg = "Hi " . $vol['name'] . "\n You have not added your \n";
                if ($vol['profile']['phone'] == "") {
                    $ph_no = " phone number ";
                }
                 if ($vol['profile']['phone'] != "") {
                     $moph = $vol['profile']['phone']; 
                     $q = "SELECT * from user_profile where mobile_ph= '$moph' OR home_ph= '$moph'";
                     $rq = mysqli_query($link, $q) or die(mysqli_error($link));
                     if (mysqli_num_rows($rq) == 0) {
                        $ph_no = " phone number (same as in hr system) "; 
                     }
                }
                if (!array_key_exists("image_original", $vol['profile'])) {

                    $image = "profile picture";
                }
                if (!empty($ph_no) || !empty($image)) {
                    if (!empty($ph_no)) {
                        $update_msg = $update_msg . $ph_no . "\n";
                    }
                    if (!empty($image)) {
                        $update_msg = $update_msg . $image . "\n";
                    }
                    $update_msg = $update_msg . " in your slack profile. Please do that asap. ";
                    $c_id = get_channel_id($f, $cid_array);
                    echo "$update_msg";
                    echo "<br>";
                    $s = getSlackMsgSendStatus($email_adr, $link);
                    if ($s == 0) {
                    //    send_slack_message($c_id, $token, $update_msg, $hr3); // send slack message
                    }
                }
            }
        }
        
        $holy_msg1="";
        $holy_msg2="";
        $q4 = "Select * from holidays where date like '%$current_month%'";
        $s4 = mysqli_query($link, $q4) or die(mysqli_error($link));
        while ($row4 = mysqli_fetch_assoc($s4)) {
            
            $frm2 = date('Y-m-d', strtotime($row4['date'] . ' -2 day'));
            $frm3 = date('Y-m-d', strtotime($row4['date'] . ' -1 day'));
            if ($frm2 == $current_date) {
                $holy_msg1 = $holy_msg1 .$row4['name']. " holiday is on ".date('dS M(D)', strtotime($row4['date']))."\n";
               
            }
            if ($frm3 == $current_date) {
                $holy_msg2 = $holy_msg2 .$row4['name']. " holiday is on ".date('dS M(D)', strtotime($row4['date']))."\n";
                }
        }
        if($holy_msg1 !=""){
           echo $holy_msg1; 
           // send_slack_message($c_id = hr_system, $token, $holy_msg1);
        }
        if($holy_msg2 !=""){
            echo $holy_msg2;
           // send_slack_message($c_id = hr_system, $token, $holy_msg2);
        }
      
        
//---end update profile pic and phone no. slack message--- 
    }
}

//---get channel id of a user---------
function get_channel_id($data, $array) {
    foreach ($array as $val) {
        if ($data == $val['user']) {
            return $val['id'];
            break;
        }
    }
}

//--------Send slack message function------------
function sends_slack_message($channelid, $token, $sir = false, $s = false, $day = false) {

    include "sal_info/config.php";

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
    $url = $send_slack_message_url . $token . "&attachments=" . urlencode($message) . "&channel=" . $room;
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
function getLeaveNotification($data, $link, $date = false) {
    $year = date("Y");
    $month = date("m");
    if ($date != false) {
        $m = explode('-', $date);
        $year = $m['0'];
        $month = $m['1'];
    }
    $list = array();
    $qry = "SELECT * FROM leaves Where user_Id = $data ";
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
                $h_full_date = date("m-d-Y", strtotime($d));
                $list[] = $h_full_date;
            }
        }
    }
    ksort($list);

    return $list;
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

// get office working hours on a particular date
function getWorkingHours($data, $link) {
    $result = "09:00"; // default value
    $qry = "select * from working_hours where date='$data'";
    $resl = mysqli_query($link, $qry) or die(mysqli_error($link));
    if (mysqli_num_rows($resl) > 0) {
        while ($row = mysqli_fetch_assoc($resl)) {
            $result = $row['working_hours'];
        }
    }
    return $result;
}

// get slack profile created date of employee
function getslacklist($array1, $array2) {

    $result = array();
    if (sizeof($array1 > 0)) {
        foreach ($array1['members'] as $foo) {
            $id = $foo['id'];
            foreach ($array2 as $val) {
                if ($id == $val['user']) {
                    $arr['date'] = date("Y-m-d", $val['created']);
                    $arr['name'] = $foo['real_name'];
                    break;
                }
            }
            $result[] = $arr;
        }
    }
    return $result;
}

// get employee working hours on a date
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

//get employee previous month attendance detail.
function getUserPreviousMonthDetail($uid, $date, $link) {
    $query = "SELECT hr_data.*,users.status FROM hr_data LEFT JOIN users ON hr_data.user_id = users.id where users.status='Enabled' AND users.id = $uid AND hr_data.date LIKE '%$date%'";
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
    foreach ($arr as $kk => $vv) {
        // print_r($value);
        foreach ($vv as $f) {
            if ($f['entry_time'] != 0 && $f['exit_time'] != 0) {
                $ed = strtotime($f['exit_time']) - strtotime($f['entry_time']);
                $time = abs($ed) / 60 . " minute";
                $hours = floor($time / 60);
                $minutes = ($time % 60);
                $t = $hours . ":" . $minutes;
                $te = date("h:i", strtotime($t));
                $user_id = $f['user_id'];
                $cdate = date('Y-m-d', strtotime($f['date']));
                $working_hour = getWorkingHours($cdate, $link);
                $half_time = date("h:i", strtotime($working_hour) / 2);
                if ($working_hour != 0) {
                    $user_working_hour = getUserWorkingHours($user_id, $cdate, $link);
                    if ($user_working_hour != 0) {
                        $working_hour = $user_working_hour;
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
                        $hd = getUserHalfDay($user_id, $cdate, $link);
                        if ($hd != 0) {
                            
                        } else {
                            $ed1 = strtotime($working_hour) - strtotime($te);
                            $te1 = $ed1 / 60;
                            if ($te1 >= 5) {
                                $vv['ptime'][] = $te1;
                                $vv['ctime'][] = 0;
                                $vv['entry_exit'][] = $f['entry_time'] . "--" . $f['exit_time'] . "--" . $f['date'];
                            }
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
    return $arr2;
}

// get employee previous month not applied leave detail
function getPrevMonthLeave($userid, $date, $link) {
    $array = getUserPreviousMonthDetail($userid, $date, $link);
    $m = explode('-', $date);
    $month = $m['0'];
    $year = $m['1'];
    $list = array();
    for ($d = 1; $d <= 31; $d++) {
        $time = mktime(12, 0, 0, $month, $d, $year);
        if (date('m', $time) == $month)
            $list[] = date('m-d-Y', $time);
    }
    foreach ($list as $getd) {
        $de = 0;
        $de = getData($getd, $link);
        if ($de != 0) {
            $set[] = $getd;
        }
    }
    //array_pop($set);
    foreach ($array as $key => $value) {
        $pending = $value['ptime'];
        $compensate = $value['ctime'];
        $entry = $value['entry_exit'];
        $wdate = $value['wdate'];
        $half = array();
        if (array_key_exists('half', $value)) {
            $half = $value['half'];
        }
        $mm = "";
        if (sizeof($wdate) > 0) {
            $diff = array_diff($set, $wdate);
            $arr = getLeaveNotification($userid, $link, $date = $year . '-' . $month);
            $diff2 = array_diff($diff, $arr);
            if (sizeof($diff2) > 0) {
                foreach ($diff2 as $v) {
                    $mm = $mm . "You have not applied your leave on " . date("d-m-Y", strtotime(str_replace("-", "/", $v))) . "\n";
                }
            }
        }
        if (sizeof($half) > 0) {
            $arr = getLeaveNotification($userid, $link, $date = $year . '-' . $month);
            foreach ($half as $h) {
                if (!in_array($h, $arr)) {
                    $mm = $mm . "You have not applied for halfday on " . date("d-m-Y", strtotime(str_replace("-", "/", $h))) . "\n";
                }
            }
        }
    }
    return $mm;
}

// get user previous month pending time 
function getUserPreviousMonthTime($uid, $date, $link) {
    $arr2 = getUserPreviousMonthDetail($uid, $date, $link);
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
        //echo  $to_compensate;
        return $to_compensate;
    }
}

// get holidays of particular month
function getHolidaysOfMonth($year, $month, $link) {
    $q = "SELECT * FROM holidays";
    $resl = mysqli_query($link, $q) or die(mysqli_error($link));
    while ($row = mysqli_fetch_assoc($resl)) {
        $rows[] = $row;
    }
    $list = array();
    foreach ($rows as $pp) {
        $h_date = $pp['date'];
        $h_month = date('m', strtotime($h_date));
        $h_year = date('Y', strtotime($h_date));
        if ($h_year == $year && $h_month == $month) {
            $h_full_date = date("Y-m-d", strtotime($h_date));
            $h_date = date("d", strtotime($h_date));
            $pp['date'] = $h_date;
            $pp['full_date'] = $h_full_date; // added on 27 for daysbetwweb leaves
            $list[$h_date] = $pp;
        }
    }
    return $list;
}

// get weekends off particular month
function getWeekendsOfMonth($year, $month) {
    $list = array();
    $monthDays = getDaysOfMonth($year, $month);
    $alternateSaturdayCheck = false;
    foreach ($monthDays as $k => $v) {
        if ($v['day'] == 'Sunday') {
            $list[$k] = $v;
        }
        if ($v['day'] == 'Saturday') {
            if ($alternateSaturdayCheck == true) {
                $list[$k] = $v;
                $alternateSaturdayCheck = false;
            } else {
                $alternateSaturdayCheck = true;
            }
        }
    }
    return $list;
}

// get all days of particular month
function getDaysOfMonth($year, $month) {
    $list = array();
    for ($d = 1; $d <= 31; $d++) {
        $time = mktime(12, 0, 0, $month, $d, $year);
        if (date('m', $time) == $month) {
            $c_full_date = date('Y-m-d', $time);
            $c_date = date('d', $time);
            $c_day = date('l', $time);
            $row = array(
                'full_date' => $c_full_date,
                'date' => $c_date,
                'day' => $c_day
            );
            $list[$c_date] = $row;
        }
    }
    return $list;
}

// get working days list of particular month.
function getMonthWorkingDays($year, $month, $link) {
    $w = getHolidaysOfMonth($year, $month, $link);
    $g = getWeekendsOfMonth($year, $month);
    $f = getDaysOfMonth($year, $month);
    if (sizeof($g) > 0) {
        $arru = $g;
    }
    if (sizeof($w) > 0) {
        foreach ($w as $k => $p) {
            if (!array_key_exists($k, $arru)) {
                $arru[$k] = $p;
            }
        }
    }
    $rae = array();
    foreach ($f as $kk => $vv) {
        if (!array_key_exists($kk, $arru)) {
            $rae[$kk] = $vv;
        }
    }
    array_shift($rae);
    $value = reset($rae);
    return $value;
}

// get employee leave info an a particular date
function getUserHalfDay($userid, $date, $link) {
    $query = "select * from leaves where user_Id=" . $userid . " AND from_date like '%$date%' AND status != 'Rejected'";
    $w = mysqli_query($link, $query) or die(mysqli_error($link));
    return mysqli_num_rows($w);
}

// check employee previous month working hour added or not
function checkUserWorkingHours($uid, $p_date, $link) {
    $result = 0;
    $qry = "select * from users_previous_month_time where user_Id = '$uid' AND year_and_month like '%$p_date%'";
    $resl = mysqli_query($link, $qry) or die(mysqli_error($link));
    if (mysqli_num_rows($resl) > 0) {
        $result = 1;
    }
    return $result;
}

// check if employee present on a particular date
function checkUserstatus($uid, $date, $link) {
    $result = 0;
    $date = date("m-d-Y", strtotime($date));
    $qry = "select * from attendance where user_id = '$uid' AND timing like '%$date%' ";
    $resl = mysqli_query($link, $qry) or die(mysqli_error($link));
    if (mysqli_num_rows($resl) > 0) {
        $result = 1;
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

function getSlackMsgSendStatus($email, $link) {
    $result = 0;
    $qry = "select * from user_profile where work_email = '$email' AND slack_msg = 1";

    $resl = mysqli_query($link, $qry) or die(mysqli_error($link));
    if (mysqli_num_rows($resl) > 0) {
        $result = 1;
    }
    return $result;
}

function getAssignDeviceStatus($userid,$link) {
    $rows = array();
    $qry = "select ml.machine_type as machine_type ,ml.machine_name as machine_name ,up.name as user_name from machines_list ml Inner Join machines_user mu ON  ml.id = mu.machine_id Inner Join user_profile up On mu.user_id = up.user_Id where up.user_Id = ".$userid." AND (ml.machine_type = 'laptop' OR ml.machine_type = 'mobile phone')";
    $resl = mysqli_query($link, $qry) or die(mysqli_error($link));
    while ($row = mysqli_fetch_assoc($resl)) {
        $rows[] = $row;
    }
    
    return $rows;
}
