<?php
require_once ("connection.php");
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
        echo "<pre>";
        print_r($fresult);
        echo "<hr>";
    }
 //  print_r($ttable); 
//die;
    curl_close($ch);
//

$query = "SELECT * from compensate where date like '%$de%' ";

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

foreach($array as $key=>$value){
    $pending = array_sum($value['ptime']);
    $compensate = array_sum($value['ctime']);
    $result = $pending - $compensate;
    if($result > 0){
        foreach ($fresult['members'] as $foo) {
            if ($key == $foo['profile']['email'] && $key != "") {
                    //$f = $foo['id'];
                       $f = "U0FJZ0KDM";
                    
                    $c_id = get_channel_id($f, $cid_array);
                   
                    
                  //  echo $key."----".$f."-----".$c_id;
                    $msg = "Hi ".$foo['real_name']." You have to compensate ".$result." minutes";
                   
                    send_slack_message($c_id, $token, $msg);
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
?>
