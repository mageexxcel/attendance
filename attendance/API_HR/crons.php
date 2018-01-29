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
	global $current_time_hour_min, $todayDate_Y_m_d, $current_month, $prev_month, $prev_month_year, $current_date;

	if( $current_date * 1 !== 5 ){
		echo "<h3>This cron action run's only on 5th day of every month</h3>";
		die;
	}

	echo "current_month :: $current_month<br>";
	echo "prev_month :: $prev_month<br>";
	echo "prev_month_year :: $prev_month_year<br>";
	$enabledUsersList = HR::getEnabledUsersList();

	foreach( $enabledUsersList as $employee ){
		$employee_id = $employee['user_Id'];
		// print_r( $employee );
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
	    	echo "<h2>--ALREADY EXISTS</h2>";
	    }
		}
	}
}


// this will run every day to only send compensation time notifications
function notification_compensation_time(){
	global $current_year, $current_time_hour_min, $todayDate_Y_m_d, $current_month, $prev_month, $prev_month_year, $current_date;

	echo "current_month :: $current_month<br>";
	echo "current_year :: $current_year<br>";
	
	$enabledUsersList = HR::getEnabledUsersList();

	foreach( $enabledUsersList as $employee ){
		$employee_id = $employee['user_Id'];
		$employee_name = $employee['name'];
		$currentMonthAttendaceDetails = HR::getUserMonthAttendaceComplete($employee_id, $current_year, $current_month);			
		if( isset($currentMonthAttendaceDetails['data']['compensationSummary']) ){
			$compensationSummary = $currentMonthAttendaceDetails['data']['compensationSummary'];
			if( isset($compensationSummary['seconds_to_be_compensate']) && $compensationSummary['seconds_to_be_compensate'] > 0 ){
				$c_seconds_to_be_compensate = $compensationSummary['seconds_to_be_compensate'];
				$c_time_to_be_compensate = $compensationSummary['time_to_be_compensate'];
				$c_compensation_break_up = $compensationSummary['compensation_break_up'];
				echo "$employee_id *** $employee_name **** $c_seconds_to_be_compensate ***** $c_time_to_be_compensate<br>";
				echo "^^^^^^BREAK UP^^^^^^^<br>";
				foreach( $c_compensation_break_up as $txt ){
					echo " ---- ".$txt['text'].'<br>';
				}
				echo '<hr>';
			}
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
	
	default:
		break;
}