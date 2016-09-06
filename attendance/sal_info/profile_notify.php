<?php

error_reporting(0);
ini_set('display_errors', 0);
require_once ("c-salary.php");
$res = Salary::getAllUserDetail();
$array = array(); 
foreach ($res as $val) {
    if($val['user_bank_detail'] == ""){
        $slackinfo = Salary::getSlackUserInfo($val['work_email']);
        $username = $slackinfo['real_name'];
        $slack_channel_id = $slackinfo['slack_channel_id'];
        $message = "Hey $username !!  \n Your Bank details are empty. Please update it on your hr profile asap\n ";
        echo $message;
        echo "<br>";
        
        $slackMessageStatus = Salary::sendSlackMessageToUser( $slack_channel_id, $message );
    }
}


