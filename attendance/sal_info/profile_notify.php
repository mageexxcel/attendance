<?php

error_reporting(0);
ini_set('display_errors', 0);
require_once ("c-salary.php");
$res = Salary::getAllUserDetail();
$array = array();
echo "<pre>";
$cmonth = date("Y-m-d");
foreach ($res as $val) {
    $joining_date = $val['dateofjoining'];

    $endDate = strtotime($cmonth);
    $startDate = strtotime($joining_date);
    $numberOfMonths = abs((date('Y', $endDate) - date('Y', $startDate)) * 12 + (date('m', $endDate) - date('m', $startDate)));
    $slackinfo = Salary::getSlackUserInfo($val['work_email']);
    $username = $slackinfo['real_name'];
    $slack_channel_id = $slackinfo['slack_channel_id'];
    $message = "";
    if ($val['user_bank_detail'] == "" && $numberOfMonths > 2) {

        
        $message = $message . "Hey $username !!  \n Your Bank details are empty. Please update it on your hr profile asap\n ";
    }
    if (!empty($val['updated_on'])) {

        $nofmonth = abs((date('Y', $endDate) - date('Y', strtotime($val['updated_on']))) * 12 + (date('m', $endDate) - date('m', strtotime($val['updated_on']))));
    }


    if ($val['updated_on'] == "" || $nofmonth > 3) {
        if ($message != "") {
            $message = $message . "\n Your Profile details are not Updated. Please update it on your hr profile asap\n ";
        } else {
            $message = "Hey $username !!  \n Your Profile details are not Updated. Please update it on your hr profile asap\n ";
        }
    }
  
    if ($message != "") {
         echo $message;
    echo "<br>";
          $slackMessageStatus = Salary::sendSlackMessageToUser( $slack_channel_id, $message );  
    }
}


