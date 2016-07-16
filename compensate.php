<?php
require_once ("../connection.php");
error_reporting(E_ALL & ~E_NOTICE);
date_default_timezone_set('UTC');
$de = date("m-Y");

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
//        echo "<pre>";
//        print_r($cid_array);
//        echo "<hr>";
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

$query = "SELECT * from hr_data where date like '%$de%'";

$array = array();
$w = mysqli_query($link, $query) or die(mysqli_error($link));
while ($s = mysqli_fetch_assoc($w)) {
     $sid = $s['email'];
     $d = strtotime($s['date']);
     if($s['entry_time'] != 0 && $s['exit_time'] !=0){
         
    if (array_key_exists($sid, $array)) {

         $array[$sid][$d] = $s;
           
        }
    else {

         $array[$sid][$d] = $s;
     }  
     }
    
}
echo "<pre>";
//print_r($array);
$arr = array();
$arr2 = array();
foreach($array as $k=>$v){
    ksort($v);
    $arr[$k]=$v; 
}
//echo "<hr>";
//print_r($arr);
//die;

foreach($arr as $kk => $vv){
  // print_r($value);
       foreach($vv as $f){
          
              if($f['entry_time'] != 0 && $f['exit_time'] !=0){
        $ed = strtotime($f['exit_time']) - strtotime($f['entry_time']);
         $te = date("h:i",$ed);
         if(strtotime($te) < strtotime("09:00")){
             $ed1 = strtotime('09:00') - strtotime($te);
                $te1 = $ed1 / 60;
             $vv['ptime'][] = $te1;
             $vv['ctime'][] = 0;
             $vv['entry_exit'][] = $f['entry_time']."--".$f['exit_time']."--".$f['date'];
            
         }
         else {
             $ed1 = strtotime($te) - strtotime('09:00');
                $te1 = $ed1 / 60;
             $vv['ctime'][] = $te1;
             $vv['ptime'][] = 0;
             $vv['entry_exit'][] = $f['entry_time']."--".$f['exit_time']."--".$f['date'];
            
         }
         
     }
           
       }
    

     $arr2[$kk] = $vv;
    
}
//echo "<pre>";
//print_r($arr2);
//die;

foreach($arr2 as $key=>$value){
  $pending = $value['ptime'];
  $compensate = $value['ctime'];
  $entry = $value['entry_exit'];
 
 
  
  echo "<pre>";
  print_r($pending);
  print_r($compensate);
  print_r($entry);
 
 
  

  $to_compensate = 0;
  $index = 0;
  $rep = array();
  for($i=0; $i<sizeof($pending); $i++){
      if($pending[$i] != 0 || !empty($rep)){
          $at = array();
          $at['pp'] =  $pending[$i];
          $at['cc'] =  $compensate[$i];
          $at['en'] =  $entry[$i];
         
          $rep[]= $at;
      }
      $to_compensate = $pending[$i] + $to_compensate;
      if($to_compensate != 0){
          
            $to_compensate = $to_compensate-$compensate[$i];

            
        
      
      }
      if($to_compensate <= 0){
          $to_compensate=0;
      }
      
  }
  //print_r($rep);
  echo "<hr>";
  if($to_compensate > 0){
      echo $to_compensate;
      echo "<br>";
      $msg = "";
              foreach ($fresult['members'] as $foo) {
            if ($key == $foo['profile']['email'] && $key != "") {
                //$f = $foo['id'];
                $f = "U0FJZ0KDM";

                $c_id = get_channel_id($f, $cid_array);

                $r = date('H:i', mktime(0, $to_compensate));
                //  echo $key."----".$f."-----".$c_id;
                $msg = $msg."Hi " . $foo['real_name'] . " You have to compensate " . $r . " minutes \n";
                $msg = $msg."Details: \n";
                foreach($rep as $r){
                     $dt = explode("--",$r['en']);
                    if($r['pp'] == 0){
                       
                        $msg = $msg."On ".$dt[2]." Entry Time: ".$dt[0]." Exit Time: ".$dt[1]."  Compensated: ".$r['cc']." minutes \n";
                    }
                    else {
                     $msg = $msg."On ".$dt[2]." Entry Time: ".$dt[0]." Exit Time: ".$dt[1]."  Pending: ".$r['pp']." minutes \n";
                    }
                }

                  send_slack_message($c_id, $token, $msg);
                echo $msg;
                echo "<br>";
            }
        }
    }
  
}

function get_channel_id($data, $array) {
    foreach ($array as $val) {
        if ($data == $val['user']) {
            return $val['id'];
            break;
        }
    }
}

function send_slack_message($channelid, $token, $sir = false, $s = false, $day = false ) {
   

    $message = '[{"text": "' . $sir . '", "fallback": "Message Send to Employee", "color": "#36a64f"}]';
    

    $room = $channelid;
    $message = str_replace("", "%20", $message);

    $icon = ":boom:";

    $url = "https://slack.com/api/chat.postMessage?token=".$token."&attachments=" . urlencode($message) . "&channel=" . $room;

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