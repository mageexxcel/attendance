<?php
$SHOW_ERROR = false;
if( $SHOW_ERROR ){
    error_reporting(E_ALL);
    ini_set('display_errors', 1); 
} else{
    error_reporting(0);
    ini_set('display_errors', 0);
}

require_once 'c-hr.php';

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

	echo "current_month :: $current_month<br>";
	echo "prev_month :: $prev_month<br>";
	echo "prev_month_year :: $prev_month_year<br>";
	$enabledUsersList = HR::getEnabledUsersList();

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
				$userid = $user['user_Id'];
				if( strtolower($user['role_name']) != 'admin' ){					
					HR::forgotPassword( $user['username'], true );
				}			
			}
			HR::API_resetPasswordConfig( $resetPwdConfig['days'], $resetPwdConfig['status'] );
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
	
	default:
		break;
}