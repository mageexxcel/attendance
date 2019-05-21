<?php
$SHOW_ERROR = false;
if( $SHOW_ERROR ){
    error_reporting(E_ALL);
    ini_set('display_errors', 1); 
} else{
    error_reporting(0);
    ini_set('display_errors', 0);
}

define("weekoff", "Sunday");
require_once 'c-hr.php';
require_once ("../sal_info/c-salary.php");

$CRON_ACTION = false;
if( isset($_GET['action']) ){
	$CRON_ACTION = $_GET['action'];
}

if( !$CRON_ACTION ){
	echo "No action found. So Die!!";
	die;
}

echo '<pre>';
echo "<H1>Action CAll :: $CRON_ACTION</h1>";

// END GENERIC


// VARIABLES

$current_time_hour_min = date('H:i');

$current_date = date('d');
$current_month = date('m');
$current_year = date('Y');
$prev_month = date('m', strtotime(date('Y-m')." -1 month"));
$prev_month_year = date('Y', strtotime(date('Y-m')." -1 month"));
$todayDate_Y_m_d = date('Y-m-d');



// function to get previous month pending time and insert it to users_previous_month_time table 
// which will be list on manage_user_pending_hours page on HR portal
function calculate_previous_month_pending_time(){
	global $current_time_hour_min, $todayDate_Y_m_d, $current_month, $current_year, $prev_month, $prev_month_year, $current_date;

	// this will be run only manually by manish sir so below code is commented
	// if( $current_date * 1 !== 2 ){
	// 	echo "<h3>This cron action run's only on 2nd day of every month</h3>";
	// 	die;
	// }

	// added on 28th march 2019 to forcefully calculate for a month.
	if( isset($_GET['month']) && isset($_GET['year']) ){
		$fpm = $_GET['month'];
		$fpmy = $_GET['year'];
		if( is_numeric($fpm) && $fpm > 0 && $fpm < 13 && is_numeric($fpmy) ){
			if( $fpm < 10 ){
				if( substr($fpm, 0, 1) != 0 ){
					$fpm = '0'.$fpm;	
				}
			}
			$prev_month = $fpm;
			$prev_month_year = $fpmy;
		} else {
			echo "Invalid forced parmeters";
			die;
		}
	}

	echo "current_month :: $current_month<br>";
	echo "prev_month :: $prev_month<br>";
	echo "prev_month_year :: $prev_month_year<br>";
	$enabledUsersList = HR::getEnabledUsersList();

	// die;

	foreach( $enabledUsersList as $employee ){
		$employee_id = $employee['user_Id'];
		// print_r( $employee );

		$joining_month = date('m', strtotime($employee['dateofjoining']));
		$joining_year = date('Y', strtotime($employee['dateofjoining']));
		
		if ( $joining_month == $current_month && $joining_year == $current_year ) {
			continue;
		}

		$previousMonthAttendaceDetails = HR::getUserMonthAttendaceComplete($employee_id, $prev_month_year, $prev_month);

		// calculation from compensated time summary
		$c_seconds_to_be_compensate = 0;
		if( isset($previousMonthAttendaceDetails['data']['compensationSummary']) ){
			$compensationSummary = $previousMonthAttendaceDetails['data']['compensationSummary'];
			if( isset($compensationSummary['seconds_to_be_compensate']) && $compensationSummary['seconds_to_be_compensate'] > 0 ){
				$c_seconds_to_be_compensate = $compensationSummary['seconds_to_be_compensate'];
				// $c_time_to_be_compensate = $compensationSummary['time_to_be_compensate'];
				// $c_compensation_break_up = $compensationSummary['compensation_break_up'];
				// echo "$employee_id *** $employee_name **** $c_seconds_to_be_compensate ***** $c_time_to_be_compensate<br>";
				// echo "^^^^^^BREAK UP^^^^^^^<br>";
				// foreach( $c_compensation_break_up as $txt ){
				// 	echo " ---- ".$txt['text'].'<br>';
				// }
				// echo '<hr>';
			}
		}

		echo "c_seconds_to_be_compensate  :: $c_seconds_to_be_compensate<br>  ";

		// $summary = $previousMonthAttendaceDetails['data']['monthSummary'];

		// $seconds_pending_working_hours = $summary['seconds_pending_working_hours'];

		// only to keey whose pending seconds are greater then 0
		if( $c_seconds_to_be_compensate*1 > 0 ){
			echo $employee['name'].'<br>';
			echo $employee['user_Id'].'<br>';
			// print_r( $summary );

			$hms = HR::_secondsToTime($c_seconds_to_be_compensate);
			print_r($hms);

			echo '<hr>';	

			$extraTime = $hms['pad_hms']['h'].":".$hms['pad_hms']['m'];
			$yearAndMonth = $prev_month_year.'-'.$prev_month;

			echo $employee['name'].'<br>';
			echo $employee['user_Id'].' *** '.$extraTime.' *** '.$yearAndMonth.' *** '.$todayDate_Y_m_d ;
			echo '<br>';
			echo '<br>';

			$checkAlreadyExistsQuery = "SELECT * FROM users_previous_month_time where user_Id=$employee_id AND year_and_month='$yearAndMonth'";
			$runQuery = HR::DBrunQuery($checkAlreadyExistsQuery);
	    $no_of_rows = HR::DBnumRows($runQuery);
	    echo "<h2>$no_of_rows</h2>";
	    if($no_of_rows==0){						
			$insertQuery = "INSERT INTO users_previous_month_time (user_Id,extra_time,year_and_month,date) value ('$employee_id', '$extraTime', '$yearAndMonth', '$todayDate_Y_m_d')";
			HR::DBrunQuery($insertQuery);
	    	echo "<h2>--INSERT</h2>";
	    }else{
			$checkMergeStatusQuery = " SELECT * FROM users_previous_month_time where user_Id=$employee_id AND year_and_month='$yearAndMonth' AND status_merged = 0 ";
			$runQuery = HR::DBrunQuery($checkMergeStatusQuery);
			$rowExists = HR::DBnumRows($runQuery);
			if( $rowExists > 0 ){
				$deleteQuery = " DELETE FROM users_previous_month_time WHERE user_Id=$employee_id AND year_and_month='$yearAndMonth' AND status_merged = 0 ";
				HR::DBrunQuery($deleteQuery);
				echo "<h2>--Already exist with merge status = 0, So DELETED</h2>";
				$insertQuery = "INSERT INTO users_previous_month_time (user_Id,extra_time,year_and_month,date) value ('$employee_id', '$extraTime', '$yearAndMonth', '$todayDate_Y_m_d')";
				HR::DBrunQuery($insertQuery);
				echo "<h2>--Already exist with merge status = 0 DELETED & New INSERTED</h2>";
			} else {
				echo "<h2>--ALREADY EXISTS</h2>";
			}
	    }
		}
	}

	$beautyMonth = date('M', strtotime($prev_month));


	HR::sendSlackMessageToUser("hr_system", "CRON Executed - Pending compensation time of $prev_month_year - $beautyMonth  is calculated!!");
}


// this will run every day to only send compensation time notifications
function notification_compensation_time(){
	global $current_year, $current_time_hour_min, $todayDate_Y_m_d, $current_month, $prev_month, $prev_month_year, $current_date;

	echo "current_month :: $current_month<br>";
	echo "current_year :: $current_year<br>";

	// check if current day is a working day then only send notification
	$sendNotifications = false;
	$genericMonthSummary = HR::getGenericMonthSummary($current_year, $current_month );
	foreach( $genericMonthSummary as $gd ){
		if( $gd['full_date'] ==  $todayDate_Y_m_d && $gd['day_type'] == 'WORKING_DAY'){
			$sendNotifications = true;
			break;
		}
	}
	if($sendNotifications === false){
		echo "<h3>Today is NON WORKING DAY, notifications will not sent!!</h3>";
		die;
	}
	
	$enabledUsersList = HR::getEnabledUsersList();

	foreach( $enabledUsersList as $employee ){
		$slack_userChannelid = $employee['slack_channel_id'];
		// print_r($employee);
		$employee_id = $employee['user_Id'];
		$employee_name = $employee['name'];
		$currentMonthAttendaceDetails = HR::getUserMonthAttendaceComplete($employee_id, $current_year, $current_month);			
		if( isset($currentMonthAttendaceDetails['data']['compensationSummary']) ){
			$compensationSummary = $currentMonthAttendaceDetails['data']['compensationSummary'];
			if( isset($compensationSummary['seconds_to_be_compensate']) && $compensationSummary['seconds_to_be_compensate'] > 0 ){
				$c_seconds_to_be_compensate = $compensationSummary['seconds_to_be_compensate'];
				$c_time_to_be_compensate = $compensationSummary['time_to_be_compensate'];
				$c_compensation_break_up = $compensationSummary['compensation_break_up'];
				
				$info =  "$employee_id *** $employee_name **** $c_seconds_to_be_compensate ***** $c_time_to_be_compensate<br>";

				echo $info;				

				$slackMessageForUser = "Hi $employee_name !!\n\n You have to compensate $c_time_to_be_compensate \n\n Compensation summary \n\n";

				echo "^^^^^^BREAK UP^^^^^^^<br>";
				foreach( $c_compensation_break_up as $txt ){
					echo " ---- ".$txt['text'].'<br>';

					$slackMessageForUser .= $txt['text']. "\n";
				}

				//sleep for 1 seconds to delay SLACK call -- added on 22june2018 by arun
    		sleep(1);
				$aa = HR::sendSlackMessageToUser($slack_userChannelid, $slackMessageForUser);
				echo '<hr>';
			}
		}

		// added on 26th feb 2018 new notification if leaves are not approved
		if( isset($currentMonthAttendaceDetails['data']['attendance']) ){
			$checkPendingLeaveForApproval = false;
			$attendanceOfUser = $currentMonthAttendaceDetails['data']['attendance'];
			foreach ($attendanceOfUser as $key => $value) {
				if( $value['day_type'] == 'LEAVE_DAY' ){
					$checkPendingLeaveForApproval = true;
				}
			}
			if($checkPendingLeaveForApproval){
				$userLeavesData = HR::getUserMonthLeaves($employee_id, $current_year, $current_month);
				if (sizeof($userLeavesData) > 0) {
					foreach( $userLeavesData as $l ){
						if( strtolower($l['status']) === 'pending') {
							$l_start = $l['from_date'];
							$b_l_start = date('d-M-Y', strtotime($l_start));

							$l_end = $l['to_date'];
							$b_l_end = date('d-M-Y', strtotime($l_end));

							$l_reason = $l['reason'];

							$slackLeaveMessageForUser = "Hi $employee_name !!\n\n Your leave for dates $b_l_start to $b_l_end is pending for approval. \n\n Reason of leave : $l_reason \n\n Contact HR for approval or your salary slip will not generate.";
							
							echo $slackLeaveMessageForUser;
							$aa = HR::sendSlackMessageToUser($slack_userChannelid, $slackLeaveMessageForUser);


							$slackLeaveMessageFor_HR = "Hi HR !!\n\n $employee_name leave for dates $b_l_start to $b_l_end is pending for approval. \n\n Reason of leave : $l_reason \n\n Respond for same else employee salary slip will not generate.";

							HR::sendSlackMessageToUser("hr_system", $slackLeaveMessageFor_HR);
						}
					}
				}
			}

		}
		// added on 26th feb 2018 new notification if leaves are not approved

	}
	HR::sendSlackMessageToUser("hr_system", "CRON Executed - Notifications of pending compensation time!!");
}

function sendBirthdayWishes(){
	global $current_month, $current_date;
	$users = HR::getEnabledUsersList();
	foreach($users as $user){
		$user_id = $user['user_Id'];
		$user_dob = $user['dob'];
		$user_name = $user['name'];
		$user_slack_channel_id = $user['slack_channel_id'];		
		if( isset($user_dob) && $user_dob != "" && $user_dob != '0000-00-00' ){
			$user_dob_month_day = date('m-d', strtotime($user_dob));
			$current_month_day = $current_month . "-" . $current_date;
			if( $user_dob_month_day == $current_month_day ){
				HR::sendBirthdayWishEmail($user_id);
				if( isset($user_slack_channel_id) && $user_slack_channel_id != "" ){
					$message = "Happy Birthday " . $user_name . " !!";
					HR::sendSlackMessageToUser( $user_slack_channel_id, $message );
				}
			}
		}		
	}
}

function resetPasswords(){
	global $todayDate_Y_m_d;
	$users = HR::getEnabledUsersList();
	$config = HR::API_getResetPasswordConfig();	
	$resetPwdConfig = $config['data']['value'];
	if( $resetPwdConfig['status'] ){
		$dates = HR::_getDatesBetweenTwoDates( $resetPwdConfig['last_updated'], $todayDate_Y_m_d );
		if( sizeof($dates) > $resetPwdConfig['days'] ){	
			foreach( $users as $key => $user ){
				sleep(2);
				$userid = $user['user_Id'];
				if( strtolower($user['role_name']) != 'admin' ){					
					HR::forgotPassword( $user['username'], true, true );
				}			
			}
			HR::API_resetPasswordConfig( $resetPwdConfig['days'], $resetPwdConfig['status'] );
		}
	}
}

function notificationUpdateProfile(){
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
	$runQuery2 = HR::DBrunQuery($q2);
	$row2 = HR::DBfetchRow($runQuery2);

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
				
				// notification for updating profile fields added on 12-Dec-2018
				if ( $slackinfo['deleted'] == "" && $slackinfo['is_primary_owner'] == "" && $slackinfo['id'] != "USLACKBOT" && $slackinfo['is_bot'] == false && (!array_key_exists("image_original", $slackinfo['profile'])) ) {
					// update phone number
					$update_msg = "Hi " . $val['name'] . "\n You have not added your \n";
					if ( $slackinfo['profile']['phone'] == "" ) {
						$ph_no = " phone number ";
					}
					if( $slackinfo['profile']['phone'] != "" ){
						if( $val['mobile_ph'] != $slackinfo['profile']['phone'] && $val['home_ph'] != $slackinfo['profile']['phone'] ){
							$ph_no = " phone number (same as in hr system) ";
						}
					}

					// update profile image on slack
					if (!array_key_exists("image_original", $slackinfo['profile'])) {
						$image = "profile picture";
					}

					if ( !empty($ph_no) || !empty($image) ) {
						if (!empty($ph_no)) {
							$update_msg = $update_msg . $ph_no . "\n";
						}
						if (!empty($image)) {
							$update_msg = $update_msg . $image . "\n";
						}
						$update_msg = $update_msg . " in your slack profile. Please do that asap. ";
						echo "$update_msg";
						echo "<br><br>";
						$slackMessageStatus = Salary::sendSlackMessageToUser($slack_channel_id, $update_msg);   // send slack notification to employee						
					}
				}
				

			}
		}
		
		if(!empty($assign_machine_msg)){
			$m = "Hi HR!\n Following employee assigned machine details are not store in database:\n";
			$m.= $assign_machine_msg."Please save them asap";
			$slackMessageStatus = Salary::sendSlackMessageToUser('hr', $m);
		
		}
	}
}

// Below function will be used for only one time and after that you can comment it and its action case also.
function backupBankDetailsOfDisabledEmployees(){
	$disabledUsers = HR::getDisabledUsersList();
	foreach( $disabledUsers as $disUser ){
		try {
			HR::backupBankAccountDetails( $disUser['user_Id'] );			
		} catch( Exception $ex ){
			echo $ex->getMessage() . "\n";                    
		}
	}
}


switch ($CRON_ACTION) {
	case 'calculate_previous_month_pending_time':
		calculate_previous_month_pending_time();
		break;

	case 'notification_compensation_time':
		notification_compensation_time();
		break;

	case 'send_birthday_wishes':
		sendBirthdayWishes();
		break;
		
	case 'reset_passwords':
		resetPasswords();
		break;
	
	case 'notification_update_profile':
		notificationUpdateProfile();
		break;

	// Below case will be used only one time after that you can comment it. 
	case 'backup_bank_details_of_disabled_employees':
		backupBankDetailsOfDisabledEmployees();
		break;

	default:
		break;
}