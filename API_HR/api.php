<?php
	//error_reporting(0);
	//ini_set('display_errors', 0);

	header("Access-Control-Allow-Origin: *");
	if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
		if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
	        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");         
		if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
	        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
		exit(0);
	}

	//echo '<pre>';

	require_once 'c-hr.php';
        
        $request_body = file_get_contents('php://input');
	$PARAMS = json_decode($request_body, true );

	$action = false;
	if( isset( $PARAMS['action'] ) ){
		$action = $PARAMS['action'];
	}

	$token = $PARAMS['token'];

	//validate a token
	if( $action != 'login' ){
		$token = $PARAMS['token'];
		$validateToken = HR::validateToken( $token );
		if( $validateToken == false ){
			header("HTTP/1.1 401 Unauthorized");
			exit;
		}
	}


	$res = array(
		'error' => 1,
		'data' => array()
	);

	if( $action == 'login' ){
		$username = $password = '';	
		
		if( isset( $PARAMS['username']) && isset( $PARAMS['password']) ){
			$username = $PARAMS['username'];
			$password = md5( $PARAMS['password'] );
		}
		$res = HR::login( $username, $password );
	}else if( $action == 'logout' ){
		$res = HR::logout( $PARAMS['token'] );
	}else if( $action == 'month_attendance' ){
		$userid = $PARAMS['userid'];
		$year = $PARAMS['year'];
		$month = $PARAMS['month'];
		$res = HR::getUserMonthAttendaceComplete( $userid, $year, $month );
	}else if( $action == "attendance_summary" ){
		$year = $PARAMS['year'];
		$month = $PARAMS['month'];
		$res = HR::getMonthAttendaceSummary( $year, $month );
	}else if( $action == "user_day_summary" ){
		$userid = $PARAMS['userid'];
		$date = $PARAMS['date'];
		$res = HR::getUserDaySummary( $userid, $date );
	}else if( $action == "get_enable_user" ){
        $res = HR::getEnabledUsersListWithoutPass();
	}else if( $action == 'update_user_day_summary' ){
		$loggedUserInfo = JWT::decode( $token, HR::JWT_SECRET_KEY );
		$loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);
		//check for guest so that he can't update
		if( $loggedUserInfo['role'] == 'Guest' ){
			$res['error'] = 1;
            $res['data']['message'] = "You don't have permission to update";
		}else{
			$userid = $PARAMS['userid'];
			$date = $PARAMS['date'];
			$entry_time = $PARAMS['entry_time'];
			$exit_time = $PARAMS['exit_time'];
			$reason = $PARAMS['reason'];
			$res = HR::insertUserInOutTimeOfDay( $userid, $date, $entry_time, $exit_time, $reason );
		}
	}else if( $action == "working_hours_summary" ){
		$year = $PARAMS['year'];
		$month = $PARAMS['month'];
        $res = HR::getWorkingHoursSummary( $year, $month );
	}else if( $action == 'update_day_working_hours' ){
		$loggedUserInfo = JWT::decode( $token, HR::JWT_SECRET_KEY );
		$loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);
		//check for guest so that he can't update
		if( $loggedUserInfo['role'] == 'Guest' ){
			$res['error'] = 1;
            $res['data']['message'] = "You don't have permission to update";
		}else{
			$date = $PARAMS['date'];
			$time = $PARAMS['time'];
			$res = HR::updateDayWorkingHours( $date, $time );
		}
	}else if( $action == "get_holidays_list" ){
		$res = HR::API_getYearHolidays( );
	}else if( $action == "apply_leave" ){
		$loggedUserInfo = JWT::decode( $token, HR::JWT_SECRET_KEY );
		$loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);
		if( isset($loggedUserInfo['id']) ){
			$userid = $loggedUserInfo['id'];

			$from_date = $PARAMS['from_date'];
			$to_date = $PARAMS['to_date'];
			$no_of_days = $PARAMS['no_of_days'];
			$reason = $PARAMS['reason'];

			$res = HR::applyLeave( $userid, $from_date, $to_date, $no_of_days, $reason );
		}else{
			$res['error'] = 1;
            $res['data']['message'] = "userid not found";
		}
	}else if( $action == 'get_all_leaves' ){
		$loggedUserInfo = JWT::decode( $token, HR::JWT_SECRET_KEY );
		$loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);
		//check for guest so that he can't update
		// if( $loggedUserInfo['role'] == 'Guest' ){
		// 	$res['error'] = 1;
  //           $res['data']['message'] = "You don't have permission to update";
		// }else{
			$res = HR::getAllLeaves( );
		//}
	}



	echo json_encode( $res );
?>