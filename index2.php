<?php
require_once ("../connection.php");
error_reporting(E_ALL & ~E_NOTICE);
date_default_timezone_set('UTC');
$de = date("m-d-Y");

if (isset($_FILES['image'])) {

    $file_name = $_FILES['image']['name'];
    $file_size = $_FILES['image']['size'];
    $file_tmp = $_FILES['image']['tmp_name'];
    $file_type = $_FILES['image']['type'];
    // $file_ext = strtolower(end(explode('.', $_FILES['image']['name'])));

    if (!move_uploaded_file($file_tmp, "upload/" . $file_name)) {
        echo "File Not uploaded";
        die;
    }
    $sendmessage = true;
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
            // $line = str_replace(" ","dd",$line);
            $line = trim($line);
            $line = trim(preg_replace('/\s+/', ' ', $line));
//            echo $line;
//            echo "<br>";
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
}

$time_table2 = array();
//$de = "07-12-2016";
if (isset($_POST['date'])) {
    $de = $_POST['date'];
    $de = str_replace("/", "-", $de);
}

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
//echo "<pre>";
//print_r($time_table);
//die;


//$sendmessage = "Hello";
if (isset($sendmessage)) {

    $qv = "SELECT * from admin";
    $qw = mysqli_query($link, $qv) or die(mysqli_error($link));
    while ($qs = mysqli_fetch_assoc($qw)) {
        $client_id = $qs['client_id'];
        $client_secret = $qs['client_secret'];
        $token = $qs['token'];
    }


    $time_table6 = array();
   //$date = "2016-07-12";
   $date = date("Y-m-d");

    $prev_date = date('m-d-Y', strtotime($date . ' -1 day'));


    $hr = "hrfile";
    $hr2 = "hrfile2";

    $time_table6 = get_time_array($prev_date, $link, $hr);
//     echo "<pre>";
//    print_r($time_table6);
//    echo "<hr>";
//    die;
    $ada = $time_table6['date'];
    $pdate = date("d-m-Y", strtotime(str_replace("-", "/", $ada)));

    $day = date('l', strtotime($time_table6['date']));
    // echo $day;
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
    //echo "<pre>";
    // print_r($ttable);
    // die;
    foreach ($ttable as $valo) {
        //$a = str_replace("PM", "", current($valo['timing']));
        $a = current($valo['timing']);
        $a = strtotime(str_replace("-", "/", $a));
        $a1 = date("H:i", $a);
        //$b = str_replace("PM", "", end($valo['timing']));
        $b = end($valo['timing']);
        $b = strtotime(str_replace("-", "/", $b));
        $b1 = date("H:i", $b);
        $c = $b - $a;
        $c = date("H:i", $c);
        $email = $valo['work_email'];


        if ($valo['name'] != "" && $valo['name'] != "Admin") {
//             echo $valo['name']."---".$a1."<br>";
            if (strtotime($a1) == strtotime('00:00')) {
                $string1 = $string1 . $valo['name'] . ":  was absent on " . $day . "\n";
            }
            if (strtotime($a1) != strtotime('00:00') && strtotime($c) < strtotime('09:00')) {
                if($a1 != $b1){
                $ed = strtotime('09:00') - strtotime($c);
                $te = $ed / 60;
                $q = mysqli_query($link, "SELECT * FROM `compensate` WHERE `email_id` = '$email' AND `date` = '$pdate'");
              
                if (mysqli_num_rows($q) <= 0) {

                    $ins = "INSERT INTO compensate (email_id, date, total_time, time_compensate) VALUES ('$email', '$pdate', '$te', '0')";
                    mysqli_query($link, $ins) or die(mysqli_error($link));
                }
                $string4 = $string4 . $valo['name'] . ": Total hours on ".$day." " . $c . " Entry Time: " . $a1 . " Exit Time: " . $b1 . "\n";
                }
                if($a1 == $b1){
                     $string4 = $string4 . $valo['name'] . ": Haven't enter entry or exit time on ".$day." \n";
                }
                
            }
            if (strtotime($a1) != strtotime('00:00') && strtotime($c) > strtotime('09:00')) {
                $ed = strtotime($c) - strtotime('09:00');
                $te = $ed / 60;
                $q = mysqli_query($link, "SELECT * FROM `compensate` WHERE `email_id` = '$email' AND `date` = '$pdate'");
                if (mysqli_num_rows($q) <= 0) {
                    $rat = get_compensate_time($email,$link);
                   if($rat > 0 && $te >= 10){
                    $ins2 = "INSERT INTO compensate (email_id, date, total_time, time_compensate) VALUES ('$email', '$pdate', '0', '$te')";
                    mysqli_query($link, $ins2) or die(mysqli_error($link));
                   }
                }

                $string = $string . $valo['name'] . ": Total hours on " . $c . " Entry Time: " . $a1 . " Exit Time: " . $b1 . "\n";
            }
        }
    }

    foreach ($time_table as $t) {
        $j = str_replace("PM", "", current($t['timing']));
        $j = strtotime(str_replace("-", "/", $j));
        $j1 = date("H:i", $j);
        if ($t['name'] != "" && $t['name'] != "Admin") {

            if (strtotime($j1) == strtotime('00:00')) {
                $string5 = $string5 . $t['name'] . ": Did not Come Yet! \n";
            }
            if (strtotime($j1) != strtotime('00:00') && strtotime($j1) < strtotime('10:30')) {
                $string2 = $string2 . $t['name'] . ":  Entry Time: " . $j1 . "\n";
            }
            if (strtotime($j1) > strtotime('10:30')) {
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
//die;
    //  send_slack_message($c_id = 'hr', $token, $string, $hr, $day);

    if ($string4 != "") {
        $hr4 = "hrfile4";
        //  send_slack_message($c_id = 'hr', $token, $string4, $hr4, $day);
    }
    if ($string1 != "") {
        $hr1 = "hrfile1";
        //  send_slack_message($c_id = 'hr', $token, $token, $string1, $hr1, $day);
    }
    // send_slack_message($c_id = 'hr', $token, $string2, $hr2);
    if ($string3 != "") {
        $hr3 = "hrfile3";
        //  send_slack_message($c_id = 'hr', $token, $string3, $hr3, $day);
    }
    if ($string5 != "") {
        $hr5 = "hrfile5";
        //  send_slack_message($c_id = 'hr', $token, $string5, $hr5, $day);
    }


// curl operation to get list of all user's channel id from slack.

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
        // echo "<pre>";
        // print_r($cid_array);
        // echo "<hr>";
    }
    curl_close($ch);
//die;
// curl operation to get list of all user from slack.
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
        //echo "<pre>";
        // print_r($fresult);
        // echo "<hr>";
    }
  curl_close($ch);
//die;
  
 

    if (sizeof($time_table) > 0) {
        foreach ($time_table as $value) {

            $e = $value['work_email'];
            $id = $value['id'];
            foreach ($fresult['members'] as $foo) {
                $msg = "";
                if ($e == $foo['profile']['email'] && $e != "") {
                    $f = $foo['id'];
                    //  echo $value['id'] . "--" . $f . "--" . $e . "<br>";
                    if (array_key_exists($id, $ttable)) {
                        $time_arr = $ttable[$id];

                        $aa = current($time_arr['timing']);
                        $aa = strtotime(str_replace("-", "/", $aa));

                        $aa = date("h:i A", $aa);
                        $bb = end($time_arr['timing']);
                        $bb = strtotime(str_replace("-", "/", $bb));
                        $bb = date("h:i A", $bb);
                     //get previous days list of no putting exit time. 
                        $ar = array();
                        $qry = mysqli_query($link, "SELECT * FROM `hr_data` WHERE `email` = '$e' AND `exit_time` = '0'");
                            if (mysqli_num_rows($qry) > 0) {
                              while ($ss = mysqli_fetch_assoc($qry)) {
                                  $ar[] = $ss;
                                  $msg = $msg . "You didn't put in your exit time on " . $ss['date'] . ", Please contact HR immediately or this day will be considered as a leave.\n";
                              }
                            }
                    
                      //end
                        if ($bb == $aa && strtotime($bb) != strtotime("12:00 AM")) {
                            
                            $msg = $msg . "You didn't put in your exit time on " . $ada . ", Please contact HR immediately or this day will be considered as a leave.\n";
                            
                            $q = mysqli_query($link, "SELECT * FROM `hr_data` WHERE `email` = '$e' AND `date` = '$pdate'");
                            if (mysqli_num_rows($q) <= 0) {
                                $ins2 = "INSERT INTO hr_data (user_id, email, entry_time, exit_time, date) VALUES ('$id', '$e', '$aa', '0','$pdate')";
                             
                                mysqli_query($link, $ins2) or die(mysqli_error($link));
                            }
                        }
                        if ($bb == $aa && $bb == "12:00 AM") {

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
                    $c_id = get_channel_id($f, $cid_array);
                    //     if ($f == "U0FJZ0KDM" ) {

                    $d = str_replace("PM", "", current($value['timing']));
                    $d = strtotime(str_replace("-", "/", $d));
                    $d1 = date("h:i A", $d);
                    
                    if ($d1 == "12:00 AM") {
                        $msg = $msg . "You have not entered time Today";
                        // send_slack_message($c_id, $token, $msg);
                    }
                    if (strtotime($d1) >= strtotime('10:30 AM')) {

                        $msg = $msg . "Today's Entry Time " . $d1;
                        $hr6 = "hrfile6";
                        //   send_slack_message($c_id, $token, $msg, $hr6);
                    } if ($d1 != "12:00 AM" && strtotime($d1) < strtotime('10:30')) {
                        
                        $msg = $msg . "Today's Entry Time " . $d1;
                        //  send_slack_message($c_id, $token, $msg);
                    }

                    //  }
//                    echo $msg;
//                    echo "<hr>";
                }
            }
        }
    }
}


// function to get office entry and exit time of working day
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
//    if (sizeof($final) != 0 ) {
//        echo sizeof($final);
//        echo $dat;
//        echo "<br>";
//    }

    return $final;
}

//function to get channel id's of different slack users
function get_channel_id($data, $array) {
    foreach ($array as $val) {
        if ($data == $val['user']) {
            return $val['id'];
            break;
        }
    }
}
//function  end

//function to send message to different channels in slack

function send_slack_message($channelid, $token, $sir = false, $s = false, $day = false) {


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
//function  end

function get_compensate_time($email,$link){
    $d = date("m-Y");
    $query = "SELECT * from compensate where date like '%$d%' AND email_id = '$email' ";

$array = array();
$w = mysqli_query($link, $query) or die(mysqli_error($link));
while ($s = mysqli_fetch_assoc($w)) {
    $sid = $s['email_id'];
    $dd = $s['date'];
    //echo $sid;
    //echo $dd."  --  $sid<br>";
   // $array[] = $s;
    if (array_key_exists($sid, $array)) {
        
       // echo $sid;
            //$array[$sid] = $s;
            $array[$sid]['ptime'][]=$s['total_time'];
            $array[$sid]['ctime'][]  = $s['time_compensate'];
        }
    else {
        
         //$array[$sid] = $s;
         $array[$sid]['ptime'][]=$s['total_time'];
         $array[$sid]['ctime'][]=$s['time_compensate'];
        
    }    
}

//print_r($array);
foreach($array as $val){
   $pending = array_sum($val['ptime']);
    $compensate = array_sum($val['ctime']);
    $result = $pending - $compensate;
    if($result > 0){
       return $result;
    }
    else {
      $result = 0;
      return $result;
    }
}
}
//die;
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


