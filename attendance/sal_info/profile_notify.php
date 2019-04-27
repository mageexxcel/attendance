<?php

// This script has been merged to crons.php on 07-December-2018.
die('This script has been merged to crons.php on 07-December-2018');
/*
  Cron file to notify about profile update and bank detail update if empty.
 */
error_reporting(0);
ini_set('display_errors', 0);
define("weekoff", "Sunday");
require_once ("c-salary.php");

$birthday = array("Bright Birthday Wishes. Our whole team is wishing you the happiest of birthdays.", "Our whole team is wishing you the happiest of birthdays.", "It’s time to get happy! Wishing you all the best on your birthday and everything good in the year ahead.");

$res = Salary::getAllUserDetail();

$allSlackUsers = Salary::getSlackUsersList();

$array = array();
$cmonth_name = date("F Y");
$current_day = date('l');
$second_sat = date('Y-m-d', strtotime('second sat of ' . $cmonth_name));
$fourth_sat = date('Y-m-d', strtotime('fourth sat of ' . $cmonth_name));

$cmonth = date("Y-m-d");
$assign_machine_msg="";
$q2 = "select * from config where type ='policy_document_update'";
$runQuery2 = Database::DBrunQuery($q2);
$row2 = Database::DBfetchRow($runQuery2);

$upload_date = $row2['value'];
if ($current_day != weekoff && $cmonth != $second_sat && $cmonth != $fourth_sat) {
    foreach ($res as $val) {
        $joining_date = $val['dateofjoining'];
        $user_id = $val['user_Id'];
        $endDate = strtotime($cmonth);
        $startDate = strtotime($joining_date);
        $numberOfMonths = abs((date('Y', $endDate) - date('Y', $startDate)) * 12 + (date('m', $endDate) - date('m', $startDate)));
        $slackinfo = "";
        if (sizeof($allSlackUsers) > 0) {
            foreach ($allSlackUsers as $sl) {
                if ($sl['profile']['email'] == $val['work_email'] && $val['work_email'] != null && $val['work_email'] != "") {
                    $slackinfo = $sl;
                    break;
                }
            }
        }

        // $slackinfo = Salary::getSlackUserInfo($val['work_email']);
        if ($slackinfo != "" && $slackinfo['is_bot'] == false) {
            $username = $slackinfo['real_name'];
           
            $slack_channel_id = $slackinfo['slack_channel_id'];
            $message = "";

            $po = Salary::getUserPolicyDocument($user_id);
            $m1 = "";

            foreach ($po['data'] as $val2) {

                if ($val2['read'] == 0) {
                    $m1.= "File name = " . $val2['name'] . " Link = " . $val2['value'] . "\n";
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
                    $message = $message . "\n Your Profile details are not Updated. Please update your details on hr system asap\n ";
                } else {
                    $message = "Hey $username !!  \n Your Profile details are not Updated. Please update your details on hr system asap\n ";
                }
            }

            if ($message != "") {
                echo $message;
                echo "<br>";
                $slackMessageStatus = Salary::sendSlackMessageToUser($slack_channel_id, $message);   // send slack notification to employee
            }

            $datediff = strtotime($cmonth) - strtotime($upload_date);
            $datediff = floor($datediff / (60 * 60 * 24));
            if ($m1 != "" && $datediff >= 7) {
                $message2 = "Hey $username !!  \nYou have not read some policy document in HR System. Login into your HR System to view document\n";
                echo $message2;
                echo "<br>";
                $slackMessageStatus = Salary::sendSlackMessageToUser($slack_channel_id, $message2);   // send slack notification to employee
            }

            // date of birth alert slack notification 
            if (is_null($val['dob']) || $val['dob'] == "0000-00-00") {
                $m4 = "Hi HR. Please update the date of birth of $username in hr-system";
                $slackMessageStatus = Salary::sendSlackMessageToUser('hr', $m4);   // send slack notification to employee
            }
            if (!is_null($val['dob']) && $val['dob'] != "0000-00-00") {
                $dob = explode("-", $val['dob']);
                $month = date('m');
                $day = date('d');
                if ($month == $dob[1] && $day == $dob[2]) {
                    $random_keys=array_rand($birthday,1);
                    
                    $message3 = "<@".$slackinfo['id']."|".$slackinfo['name']."> ".$birthday[$random_keys].":birthday: :blush:";
                 //   $slackMessageStatus = Salary::sendSlackMessageToUser('general', $message3);   // send slack notification to employee
                }
            }
        
             if(sizeof($val['user_assign_machine']) == 0){
                 $assign_machine_msg.= $username."\n";
             }
            
           }
    }
    
    if(!empty($assign_machine_msg)){
        $m = "Hi HR!\n Following employee assigned machine details are not store in database:\n";
        $m.= $assign_machine_msg."Please save them asap";
        $slackMessageStatus = Salary::sendSlackMessageToUser('hr', $m);
       
    }
}




