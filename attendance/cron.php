<?php

require_once ("../../connection.php");

date_default_timezone_set('Asia/Kolkata');

$query = "SELECT * FROM cron_table where status = 0 ";
$run = mysqli_query($link, $query) or die(mysqli_error($link));
$time = date("H:i");
//$time = "15:10";


//echo $time;
if (mysqli_num_rows($run) > 0) {
    while ($row = mysqli_fetch_assoc($run)) {
        $cron_time = $row['run_time'];
        $cron_time2 = date("H:i", strtotime($row['run_time']) + 3600);
        // echo $cron_time."--".$cron_time2."<br>";
        if (strtotime($cron_time) <= strtotime($time) && strtotime($time) < strtotime($cron_time2)) {
            echo $row['cron_link'];

            $url = $row['cron_link'];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $result = curl_exec($ch);
             echo var_dump($result);
            if ($result === false) {
                echo 'Curl error: ' . curl_error($ch);
            } else {

                $q = "UPDATE cron_table set status = 1 where id =" . $row['id'];
                $r = mysqli_query($link, $q) or die(mysqli_error($link));
            }
        }
    }
} elseif(mysqli_num_rows($run) <= 0 & strtotime($time) > strtotime("21:00")) {
    $q2 = "UPDATE cron_table set status = 0";
    $run2 = mysqli_query($link, $q2) or die(mysqli_error($link));
}