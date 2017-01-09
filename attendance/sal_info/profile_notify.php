<?php
/* 
Cron file to notify about profile update and bank detail update if empty.
 */
error_reporting(0);
ini_set('display_errors', 0);
require_once ("c-salary.php");
$res = Salary::getAllUserDetail();
$array = array();

$cmonth = date("Y-m-d");
foreach ($res as $val) {
    $joining_date = $val['dateofjoining'];
    $user_id = $val['user_Id'];
    $endDate = strtotime($cmonth);
    $startDate = strtotime($joining_date);
    $numberOfMonths = abs((date('Y', $endDate) - date('Y', $startDate)) * 12 + (date('m', $endDate) - date('m', $startDate)));
    $slackinfo = Salary::getSlackUserInfo($val['work_email']);
    $username = $slackinfo['real_name'];
    $slack_channel_id = $slackinfo['slack_channel_id'];
    $message = "";
    
   $po =  Salary::getUserPolicyDocument($user_id);
   $m1 = "";
   
   foreach($po['data'] as $val){
       
           if($val['read'] == 0){
               $m1.= "File name = ".$val['name']. " Link = ".$val['value']."\n";
           }
       
   }
   
   
    
 // bank detail check   
    if ($val['user_bank_detail'] == "" && $numberOfMonths > 2) {

        
        $message = $message . "Hey $username !!  \n Your Bank details are empty. Please update it on your hr profile asap\n ";
    }
    if (!empty($val['updated_on'])) {

        $nofmonth = abs((date('Y', $endDate) - date('Y', strtotime($val['updated_on']))) * 12 + (date('m', $endDate) - date('m', strtotime($val['updated_on']))));
    }

// profile update check
    if ($val['updated_on'] == "") {
        if ($message != "") {
            $message = $message . "\n Your Profile details are not Updated. Please update it on your hr profile asap\n ";
        } else {
            $message = "Hey $username !!  \n Your Profile details are not Updated. Please update it on your hr profile asap\n ";
        }
    }
    
    if ($message != "") {
         echo $message;
    echo "<br>";
          $slackMessageStatus = Salary::sendSlackMessageToUser( $slack_channel_id, $message );  // send slack notification to employee
    }
    
    if ($m1 != "") {
        $message2 = "Hey $username !!  \nYou have not read some policy document in HR System. Login into your HR System to view document\n";
        echo $message2;
        echo "<br>";
  
           $slackMessageStatus = Salary::sendSlackMessageToUser( $slack_channel_id, $message2 );  // send slack notification to employee
      
    }
    
}


