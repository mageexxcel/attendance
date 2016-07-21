<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once ("../connection.php");

//require_once '../API_HR/c-hr.php';
//$arr = HR::getEnabledUsersListWithoutPass();
//echo "<pre>";
////print_r($arr);
//////$ar = HR::getUserMonthAttendaceComplete( 313, 2016, 07 );
//////    print_r($ar);
////    die;
//foreach($arr['data'] as $val){
//    $userid = $val['user_Id'];
//    $year = date("Y");
//    $month = date("m");
// 
//    $ar = HR::getUserMonthAttendaceComplete( $userid, $year, $month );
//    print_r($ar);
//}

$qv = "SELECT * from admin";
$qw = mysqli_query($link, $qv) or die(mysqli_error($link));
while ($qs = mysqli_fetch_assoc($qw)) {
   $client_id = $qs['client_id'];
   $client_secret = $qs['client_secret'];
   $token = $qs['token'];
}

//cron jobs
$url = "https://slack.com/api/im.list?token=".$token;
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
      //  echo "<pre>";
      //  print_r($cid_array);
       // echo "<hr>";
    }
    curl_close($ch);
//die;

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
//        echo "<pre>";
//        print_r($fresult);
//        echo "<hr>";
    }
 //  print_r($ttable); 
//die;
    curl_close($ch);
//


$query = "SELECT * from hr_data where date like '%07-2016%'";
$array = array();
$w = mysqli_query($link, $query) or die(mysqli_error($link));
while ($s = mysqli_fetch_assoc($w)) {
     $sid = $s['email'];
     $d = strtotime($s['date']);
  //   if($s['entry_time'] != 0 && $s['exit_time'] !=0){
         
    if (array_key_exists($sid, $array)) {

         $array[$sid][$d] = $s;
           
        }
    else {

         $array[$sid][$d] = $s;
     }  
  //   }
    
}
echo "<pre>";
//print_r($array);
$arr = array();
$arr2 = array();
foreach($array as $k=>$v){
    ksort($v);
    $arr[$k]=$v; 
}

foreach($arr as $kk => $vv){
  // print_r($value);
       foreach($vv as $f){
         //  print_r($f);
       if($f['entry_time'] == 0 || $f['exit_time'] == 0){
           $key = $f['email'];
           $date = $f['date'];
           foreach ($fresult['members'] as $foo) {
               $msg="";
            if ($key == $foo['profile']['email'] && $key != "") {
                //$f = $foo['id'];
                $f = "U0FJZ0KDM";

               // $c_id = get_channel_id($f, $cid_array);
                
                $r = date('H:i', mktime(0, $to_compensate));
                //  echo $key."----".$f."-----".$c_id;
                $msg = $msg."Hi " . $foo['real_name'] . " You have to not entered entry or exit time on " . $date;
               
                

                 // send_slack_message($c_id = 'hr', $token, $msg);
                echo $msg;
                echo "<br>";
            }
           
       }
      
       
       }
    
}
}