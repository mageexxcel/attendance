<?php

error_reporting(0);
ini_set('display_errors', 0);


require_once 'c-hr.php';


header("Access-Control-Allow-Origin: *");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    exit(0);
}


$request_body = file_get_contents('php://input');
$PARAMS = json_decode($request_body, true);

if (isset($_GET['userslack_id']) || $_GET['action'] == 'updatebandwidthstats' || $_GET['action'] == 'send_slack_msg' || $_GET['action'] == 'save_bandwidth_detail' || $_GET['action'] == 'get_bandwidth_detail' || $_GET['action'] == 'validate_unique_key') {
    $PARAMS = $_GET;
}
$action = false;
$slack_id = "";
if (isset($PARAMS['action'])) {
    $action = $PARAMS['action'];
}
if (isset($PARAMS['userslack_id'])) {
    $slack_id = $PARAMS['userslack_id'];
}

$res = array(
    'error' => 1,
    'data' => array()
);

if ($action == 'updatebandwidthstats') {
    $data = $PARAMS['data'];
    $res = HR::updateBandwidthStats($data);
}
if ($action == 'send_slack_msg') {
    $slack_userChannelid = $PARAMS['channel'];
    $message = $PARAMS['message'];
    $res = HR::sendSlackMessageToUser($slack_userChannelid, $message);
}
if ($action == 'save_bandwidth_detail') {
    $data = $PARAMS['details'];

    $data = json_decode($data, true);
    $res = HR::saveBandwidthDetail($data);
}
if ($action == 'get_bandwidth_detail') {
    $res = HR::getBandwidthDetail();
}
if ($action == 'validate_unique_key') {
    $res = HR::validateUniqueKey($PARAMS);
}


$token = $PARAMS['token'];

//validate a token
if ($action != 'login' && $action != 'forgot_password' && $slack_id == "" && $action != 'updatebandwidthstats' && $action != 'send_slack_msg' && $action != 'save_bandwidth_detail' && $action != 'get_bandwidth_detail' && $action != 'validate_unique_key') {
    $token = $PARAMS['token'];
    
    $validateToken = HR::validateToken($token);
    if ($validateToken != false) {
        //start -- check for token expiry
        $tokenInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
        $tokenInfo = json_decode(json_encode($tokenInfo), true);
        if (is_array($tokenInfo) && isset($tokenInfo['login_time']) && $tokenInfo['login_time'] != "") {
            $token_start_time = $tokenInfo['login_time'];
            $current_time = time();
            $time_diff = $current_time - $token_start_time;
            $mins = $time_diff / 60;
            if ($mins > 60) { //if 60 mins more
                $validateToken = false;
            } else {
                if (strtolower($tokenInfo['role']) == 'admin') {
                    $data = "admin";
                    HR::setAdmin($data);
                }
            }
        } else {
            $validateToken = false;
        }
        //end -- check for token expiry
    }
    if ($validateToken == false) {
        header("HTTP/1.1 401 Unauthorized");
        exit;
    }
}
if ($action == 'login') {
    $username = $password = '';

    if (isset($PARAMS['username']) && isset($PARAMS['password'])) {
        $username = $PARAMS['username'];
        $password = md5($PARAMS['password']);
    }
    $res = HR::login($username, $password);
} else if ($action == 'forgot_password') {
    $username = '';
    if (isset($PARAMS['username'])) {
        $username = $PARAMS['username'];
    }
    $res = HR::forgotPassword($username);
} else if ($action == 'logout') {
    $res = HR::logout($PARAMS['token']);
} else if ($action == 'month_attendance') {
    $userid = $PARAMS['userid'];
    $year = $PARAMS['year'];
    $month = $PARAMS['month'];
    $res = HR::getUserMonthAttendaceComplete($userid, $year, $month);
} else if ($action == "attendance_summary") {
    $year = $PARAMS['year'];
    $month = $PARAMS['month'];
    $res = HR::getMonthAttendaceSummary($year, $month);
} else if ($action == "user_day_summary") {
    $userid = $PARAMS['userid'];
    $date = $PARAMS['date'];
    $res = HR::getUserDaySummary($userid, $date);
} else if ($action == "get_enable_user") {

    $res = HR::getEnabledUsersListWithoutPass();
} else if ($action == 'update_user_day_summary') {

    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);

    //check for guest so that he can't update
    if (strtolower($loggedUserInfo['role']) != 'hr' && strtolower($loggedUserInfo['role']) != 'admin') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission to update";
    } else {
        $userid = $PARAMS['userid'];
        $date = $PARAMS['date'];
        $entry_time = $PARAMS['entry_time'];
        $exit_time = $PARAMS['exit_time'];
        $reason = $PARAMS['reason'];
        $res = HR::insertUserInOutTimeOfDay($userid, $date, $entry_time, $exit_time, $reason);
    }
} else if ($action == "working_hours_summary") {
    $year = $PARAMS['year'];
    $month = $PARAMS['month'];
    $res = HR::getWorkingHoursSummary($year, $month);
} else if ($action == 'update_day_working_hours') {

    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);

    //check for guest so that he can't update
    
    if (strtolower($loggedUserInfo['role']) != 'hr' && strtolower($loggedUserInfo['role']) != 'admin') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission to update";
    } else {
           $date = $PARAMS['date'];
        $time = $PARAMS['time'];
        $res = HR::updateDayWorkingHours($date, $time);
    }
    
} else if ($action == "get_holidays_list") {
    $res = HR::API_getYearHolidays();
} else if ($action == "apply_leave") {
    if ($slack_id == "") {
        $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
        $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);
    }
    if ($slack_id != "") {
        $loggedUserInfo = HR::getUserInfofromSlack($slack_id);
    }
    if (isset($loggedUserInfo['id'])) {
        $userid = $loggedUserInfo['id'];
        $from_date = $PARAMS['from_date'];
        $to_date = $PARAMS['to_date'];
        $no_of_days = $PARAMS['no_of_days'];
        $reason = $PARAMS['reason'];
        $day_status = $PARAMS['day_status'];
        $leave_type = $PARAMS['leave_type'];
        $late_reason = $PARAMS['late_reason'];



        $res = HR::applyLeave($userid, $from_date, $to_date, $no_of_days, $reason, $day_status, $leave_type,$late_reason);
    } else {
        $res['error'] = 1;
        $res['data']['message'] = "userid not found";
    }
} else if ($action == "admin_user_apply_leave") { //admin apply leave on behalf of user.
    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);

    if (strtolower($loggedUserInfo['role']) != 'hr' && strtolower($loggedUserInfo['role']) != 'admin') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission";
    } else {
        $userid = $PARAMS['user_id'];
        $from_date = $PARAMS['from_date'];
        $to_date = $PARAMS['to_date'];
        $no_of_days = $PARAMS['no_of_days'];
        $reason = $PARAMS['reason'];
        $day_status = $PARAMS['day_status'];

        $res = HR::applyLeave($userid, $from_date, $to_date, $no_of_days, $reason, $day_status);
    }
} else if ($action == 'get_all_leaves') {

    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);


    $res = HR::getAllLeaves();
} else if ($action == 'change_leave_status') {

    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);

    //check for guest so that he can't update
    if ($loggedUserInfo['role'] == 'Guest') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission to update";
    } else {
        $leaveid = $PARAMS['leaveid'];
        $newstatus = $PARAMS['newstatus'];
        $messagetouser = $PARAMS['messagetouser'];
        $res = HR::updateLeaveStatus($leaveid, $newstatus, $messagetouser);
    }
} else if ($action == "get_my_leaves") {
    if ($slack_id == "") {
        $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
        $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);
    }
    if ($slack_id != "") {

        $loggedUserInfo = HR::getUserInfofromSlack($slack_id);
    }
    if (isset($loggedUserInfo['id'])) {
        $userid = $loggedUserInfo['id'];
        $res = HR::getMyLeaves($userid);
    } else {
        $res['error'] = 1;
        $res['data']['message'] = "userid not found";
    }
} else if ($action == "get_days_between_leaves") {
    $start_date = $PARAMS['start_date'];
    $end_date = $PARAMS['end_date'];
    $res = HR::getDaysBetweenLeaves($start_date, $end_date);
} else if ($action == "get_managed_user_working_hours") {
    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);
    if (strtolower($loggedUserInfo['role']) != 'hr' && strtolower($loggedUserInfo['role']) != 'admin') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission to update";
    } else {
        $userid = $PARAMS['userid'];
        $res = HR::geManagedUserWorkingHours($userid);
    }
    
} else if ($action == 'add_user_working_hours') {

    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);

    //check for guest so that he can't update
    if (strtolower($loggedUserInfo['role']) != 'hr' && strtolower($loggedUserInfo['role']) != 'admin') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission to update";
    } else {
        $userid = $PARAMS['userid'];
        $date = $PARAMS['date'];
        $working_hours = $PARAMS['working_hours'];
        $reason = $PARAMS['reason'];
        $res = HR::addUserWorkingHours($userid, $date, $working_hours, $reason);
    }
} else if ($action == "get_all_leaves_summary") {
    $year = $PARAMS['year'];
    $month = $PARAMS['month'];
    $res = HR::getAllUsersPendingLeavesSummary($year, $month);
} else if ($action == "get_users_leaves_summary") { // get leave summery of an employee
    $userid = $PARAMS['user_id'];
    $year = $PARAMS['year'];
    $month = $PARAMS['month'];
    $res = HR::getUsersPendingLeavesSummary($userid, $year, $month);
} else if ($action == 'save_google_payslip_drive_access_token') {

    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);

    //check for guest so that he can't update
    if (strtolower($loggedUserInfo['role']) != 'admin') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission";
    } else {
        $google_access_token = $PARAMS['google_access_token'];
        $res = HR::updateGooglepaySlipDriveAccessToken($google_access_token);
    }
} else if ($action == 'add_new_employee') {

    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);


    //check for guest so that he can't update
    if (strtolower($loggedUserInfo['role']) != 'hr' && strtolower($loggedUserInfo['role']) != 'admin') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission";
    } else {
        $res = HR::addNewEmployee($PARAMS);
    }
} else if ($action == 'change_employee_status') {

    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);

    //check for guest so that he can't update
    if (strtolower($loggedUserInfo['role']) != 'hr' && strtolower($loggedUserInfo['role']) != 'admin') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission";
    } else {
        $res = HR::changeEmployeeStatus($PARAMS);
    }
} else if ($action == 'show_disabled_users') {

    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);

    //check for guest so that he can't update
    if (strtolower($loggedUserInfo['role']) != 'hr' && strtolower($loggedUserInfo['role']) != 'admin') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission";
    } else {
        $res = HR::getDisabledUsersList();
    }
} else if ($action == 'update_new_password') {  // only employee can update his password
    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);



    //check for employee so that he can only update his password
    if (strtolower($loggedUserInfo['role']) != 'employee') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission to update your password!!";
    } else {
        $res = HR::updatePassoword($PARAMS);
    }
} else if ($action == 'get_user_role_from_slack_id') {  // get user role using slack id
    if ($slack_id == "") {
        $res['error'] = 1;
        $res['data']['message'] = "Please provide user slack id!!";
    } else {
        $res = HR::getUserInfofromSlack($slack_id);
    }
} else if ($action == 'get_all_not_approved_leave_of_user') {  // get all not approved leave of user access by admin and hr
    if ($slack_id == "") {
        $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
        $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);
    }
    if ($slack_id != "") {
        $loggedUserInfo = HR::getUserInfofromSlack($slack_id);
    }

    //check for admin and hr so that they can only access it 
    if (strtolower($loggedUserInfo['role']) != 'hr' && strtolower($loggedUserInfo['role']) != 'admin') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission to access this!!";
    } else {
        $userid = $PARAMS['user_id'];
        $res = HR::getAllNotApprovedleaveUser($userid);
    }
} else if ($action == 'approve_decline_leave_of_user') {  // change status of user leaves
    if ($slack_id == "") {
        $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
        $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);
    }
    if ($slack_id != "") {
        $loggedUserInfo = HR::getUserInfofromSlack($slack_id);
    }

    //check for employee so that he can only update his password
    if (strtolower($loggedUserInfo['role']) != 'hr' && strtolower($loggedUserInfo['role']) != 'admin') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission to access this!!";
    } else {
        $leave_id = $PARAMS['leave_id'];
        $leave_status = $PARAMS['leave_status'];
        $res = HR::ApproveDeclineUserLeave($leave_id, $leave_status);
    }
} else if ($action == 'update_user_entry_exit_time') {  // change user entry and exit time
    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);

    //check for employee so that he can only update his password
    if ($loggedUserInfo['role'] == 'Guest') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission to update";
    } else {
        $userid = $loggedUserInfo['id'];
        $date = $PARAMS['date'];
        $entry_time = $PARAMS['entry_time'];
        $exit_time = $PARAMS['exit_time'];
        $reason = $PARAMS['reason'];
        $res = HR::insertUserInOutTimeOfDay($userid, $date, $entry_time, $exit_time, $reason, $isadmin = false);
    }
} elseif ($action == 'cancel_applied_leave') { // action to cancel employee applied leaves
    if ($slack_id == "") {
        $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
        $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);
    }
    if ($slack_id != "") {
        $loggedUserInfo = HR::getUserInfofromSlack($slack_id);
        $PARAMS['user_id'] = $loggedUserInfo['id'];
    }

    if (strtolower($loggedUserInfo['role']) == 'guest') {
        $res['data']['message'] = 'You are not authorise for this operation';
    } else {
        if (isset($PARAMS['user_id']) && $PARAMS['user_id'] != "") {
            $res = HR::cancelAppliedLeave($PARAMS);
        } else {
            $res['data']['message'] = 'Please give user_id ';
        }
    }
} elseif ($action == 'cancel_applied_leave_admin') { // action to cancel employee applied leaves
    if ($slack_id != "") {
        $loggedUserInfo = HR::getUserInfofromSlack($slack_id);

        if (strtolower($loggedUserInfo['role']) != 'hr' && strtolower($loggedUserInfo['role']) != 'admin') {
            $res['data']['message'] = 'You are not authorise for this operation';
        } else {
            $PARAMS['role'] = strtolower($loggedUserInfo['role']);
            if (isset($PARAMS['user_id']) && $PARAMS['user_id'] != "") {
                $res = HR::cancelAppliedLeave($PARAMS);
            } else {
                $res['data']['message'] = 'Please give user_id ';
            }
        }
    } else {
        $res['data']['message'] = 'Please give user_slackid ';
    }
} elseif ($action == 'get_role_from_slackid') {
    if ($slack_id != "") {
        $res = HR::getUserInfofromSlack($slack_id);
    } else {
        $res['data']['message'] = 'Please give user_slackid ';
    }
} elseif ($action == 'get_all_leaves_of_user') {

    $userid = $PARAMS['user_id'];
    if ($userid != "") {
        $res = HR::getUsersLeaves($userid);
    } else {
        $res['data']['message'] = 'Please give user_slackid ';
    }
} elseif ($action == 'get_user_current_status') { // action to cancel employee applied leaves
    if ($slack_id != "") {
        $loggedUserInfo = HR::getUserInfofromSlack($slack_id);

        if (strtolower($loggedUserInfo['role']) != 'hr' && strtolower($loggedUserInfo['role']) != 'admin') {
            $res['data']['message'] = 'You are not authorise for this operation';
        } else {
            $res = HR::getAllUserCurrentStatus();
        }
    } else {
        $res['data']['message'] = 'Please give user_slackid ';
    }
} elseif ($action == "lunch_break") {
    if ($slack_id != "") {
        $loggedUserInfo = HR::getUserInfofromSlack($slack_id);
        $PARAMS['user_id'] = $loggedUserInfo['id'];
        $res = HR::lunchBreak($PARAMS);
    } else {
        $res['data']['message'] = 'Please give user_slackid ';
    }
} elseif ($action == "get_lunch_break_detail") {
    if ($slack_id != "") {
        $loggedUserInfo = HR::getUserInfofromSlack($slack_id);
        $userid = $loggedUserInfo['id'];
        if (!isset($PARAMS['month']) && $PARAMS['month'] == "") {
            $month = date('Y-m');
        } else {
            $month = $PARAMS['month'];
        }
        $res = HR::getlunchBreakDetail($userid, $month);
    } else {
        $res['data']['message'] = 'Please give user_slackid ';
    }
} elseif ($action == 'get_lunch_stats') { // action to cancel employee applied leaves
    if ($slack_id == "") {
        $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
        $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);
    }
    if ($slack_id != "") {
        $loggedUserInfo = HR::getUserInfofromSlack($slack_id);
    }

    if (strtolower($loggedUserInfo['role']) != 'hr' && strtolower($loggedUserInfo['role']) != 'admin') {
        $res['data']['message'] = 'You are not authorise for this operation';
    } else {

        if (isset($PARAMS['date']) && $PARAMS['date'] != "") {
            $date = $PARAMS['date'];
        } else {
            $date = date("Y-m-d");
        }

        $res = HR::getAllUserLunchDetail($date);
    }
} else if ($action == 'add_office_machine') {

    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);

    //check for guest so that he can't update
    if (strtolower($loggedUserInfo['role']) != 'hr' && strtolower($loggedUserInfo['role']) != 'admin') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission";
    } else {
        $res = HR::addOfficeMachine($PARAMS);
    }
} else if ($action == 'update_office_machine') {

    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);

    //check for guest so that he can't update
    if (strtolower($loggedUserInfo['role']) != 'hr' && strtolower($loggedUserInfo['role']) != 'admin') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission";
    } else {

        $res = HR::UpdateOfficeMachine($PARAMS);
    }
} else if ($action == 'get_machine') {

    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);

    //check for guest so that he can't update
    if (strtolower($loggedUserInfo['role']) != 'hr' && strtolower($loggedUserInfo['role']) != 'admin') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission";
    } else {
        $res = HR::getMachineDetail($PARAMS);
    }
} else if ($action == 'remove_machine_detail') {

    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);

    //check for guest so that he can't update
    if (strtolower($loggedUserInfo['role']) != 'hr' && strtolower($loggedUserInfo['role']) != 'admin') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission";
    } else {
        $id = $PARAMS['id'];
        $res = HR::removeMachineDetails($id);
    }
} else if ($action == 'assign_user_machine') {

    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);

    //check for guest so that he can't update
    if (strtolower($loggedUserInfo['role']) != 'hr' && strtolower($loggedUserInfo['role']) != 'admin') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission";
    } else {
        $machine_id = $PARAMS['machine_id'];
        $user_id = $PARAMS['user_id'];
        $res = HR::assignUserMachine($machine_id, $user_id);
    }
} else if ($action == 'get_user_machine') {

    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);

    //check for guest so that he can't update
    if (strtolower($loggedUserInfo['role']) != 'hr' && strtolower($loggedUserInfo['role']) != 'admin') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission";
    } else {
        $userid = $PARAMS['user_id'];
        $res = HR::getUserMachine($userid);
    }
} else if ($action == 'get_machines_detail') {
    
    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);
    //check for guest so that he can't update
    if(strtolower($loggedUserInfo['role']) != 'hr' && strtolower($loggedUserInfo['role']) != 'admin') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission";
    } else {
        if (isset($PARAMS['sort']) && $PARAMS['sort'] != "") {
            $sort = trim($PARAMS['sort']);
            $res = HR::getAllMachineDetail($sort);
        }if (isset($PARAMS['status_sort']) && $PARAMS['status_sort'] != "") {
            $status_sort = trim($PARAMS['status_sort']);
            $res = HR::getAllMachineDetail($sort=false,$status_sort);
        }
        else {
            $res = HR::getAllMachineDetail();
        }
    }
} else if ($action == 'get_user_worktime_detail') {

    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);
    $userid = $PARAMS['user_id'];
    $date = $PARAMS['date'];
    //check for guest so that he can't update
    if (strtolower($loggedUserInfo['role']) != 'hr' && strtolower($loggedUserInfo['role']) != 'admin') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission";
    } else {
        $res = HR::userCompensateTimedetail($userid, $date);
    }
} else if ($action == 'add_machine_type') {
 
    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);

    if (strtolower($loggedUserInfo['role']) != 'hr' && strtolower($loggedUserInfo['role']) != 'admin') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission";
    } else {
  
        $res = HR::addMachineType($PARAMS);
    }
}else if ($action == 'add_machine_status') {
 
    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);

    if (strtolower($loggedUserInfo['role']) != 'hr' && strtolower($loggedUserInfo['role']) != 'admin') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission";
    } else {
     $res = HR::addMachineStatus($PARAMS);
    }
} else if ($action == 'get_machine_type_list') {

    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);

    if (strtolower($loggedUserInfo['role']) != 'hr' && strtolower($loggedUserInfo['role']) != 'admin') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission";
    } else {
        $res = HR::getMachineTypeList();
    }
}else if ($action == 'get_machine_status_list') {
    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);

    if (strtolower($loggedUserInfo['role']) != 'hr' && strtolower($loggedUserInfo['role']) != 'admin') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission";
    } else {
        $res = HR::getMachineStatusList();
    }
}
else if ($action == 'delete_machine_status') {
    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);

    if (strtolower($loggedUserInfo['role']) != 'hr' && strtolower($loggedUserInfo['role']) != 'admin') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission";
    } else {
        $res = HR::deleteMachineStatus($PARAMS['status']);
    }
}
else if ($action == 'send_request_for_doc') {

    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);

    //check for guest so that he can't update
    if ($loggedUserInfo['role'] == 'Guest') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission to update";
    } else {
        $leaveid = $PARAMS['leaveid'];
        $doc_request = $PARAMS['doc_request'];
        $comment = $PARAMS['comment'];
        $res = HR::leaveDocRequest($leaveid, $doc_request, $comment);
    }
}
else if ($action == 'add_extra_leave_day') {

    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);

    //check for guest so that he can't update
    if ($loggedUserInfo['role'] == 'Guest') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission to update";
    } else {
        $leaveid = $PARAMS['leaveid'];
        $extra_day = $PARAMS['extra_day'];
        $res = HR::addExtraLeaveDay($leaveid, $extra_day);
    }
}
else if ($action == 'add_hr_comment') {
 
    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);

    //check for guest so that he can't update
     if ($loggedUserInfo['role'] == 'Guest') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission to update";
    } else {
       
        $leaveid = $PARAMS['leaveid'];
        $hr_comment = $PARAMS['hr_comment'];
        $hr_approve = $PARAMS['hr_approve'];
        $res = HR::addHrComment($leaveid, $hr_comment,$hr_approve);
    }
}
else if ($action == 'delete_employee') {

    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);

    //check for guest so that he can't update
    if (strtolower($loggedUserInfo['role']) != 'hr' && strtolower($loggedUserInfo['role']) != 'admin') {
        $res['error'] = 1;
        $res['data']['message'] = "You don't have permission to delete user";
    } else {
        $userid = $PARAMS['user_id'];
       $res = HR::deleteUser($userid);
    }
}




echo json_encode($res);
?>
