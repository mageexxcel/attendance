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

$q2 = "select * from config where type ='policy_document_update'";
    $runQuery2 = Database::DBrunQuery($q2);
    $row2 = Database::DBfetchRow($runQuery2);
    
$upload_date = $row2['value']; 

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
   
   foreach($po['data'] as $val2){
       
           if($val2['read'] == 0){
               $m1.= "File name = ".$val2['name']. " Link = ".$val2['value']."\n";
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
    
    
    
    $datediff = strtotime($cmonth) - strtotime($upload_date);
    $datediff = floor($datediff / (60 * 60 * 24));
    if ($m1 != "" && $datediff >= 7 ) {
        $message2 = "Hey $username !!  \nYou have not read some policy document in HR System. Login into your HR System to view document\n";
        echo $message2;
        echo "<br>";
           $slackMessageStatus = Salary::sendSlackMessageToUser( $slack_channel_id, $message2 );  // send slack notification to employee
      
    }
    
}


