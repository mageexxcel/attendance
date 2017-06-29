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

// this is added by arun to remove warning on 23 june 2017
$GET_action = "";
if( isset($_GET['action']) ){
    $GET_action = $_GET['action'];
}

if (isset($_GET['userslack_id']) || $GET_action == 'updatebandwidthstats' || $GET_action == 'send_slack_msg' || $GET_action == 'save_bandwidth_detail' || $GET_action == 'get_bandwidth_detail' || $GET_action == 'validate_unique_key') {
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

$token = $PARAMS['token'];

// start -- check if action required token
$DO_TOKEN_VERIFICATION = true;
$actionsNotRequiredToken = HR::getActionsNotRequiredToken();

foreach( $actionsNotRequiredToken as $ac ){
    if( $ac['name'] == $action ){
        $DO_TOKEN_VERIFICATION = false;
    }
}
// end -- check if action required token

//validate a token
//if ($action != 'login' && $action != 'forgot_password' && $slack_id == "" && $action != 'updatebandwidthstats' && $action != 'send_slack_msg' && $action != 'save_bandwidth_detail' && $action != 'get_bandwidth_detail' && $action != 'validate_unique_key') {
if ( $DO_TOKEN_VERIFICATION == true ) {
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
//if (!empty($action)) {
//    $role_id = "";
//    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
//    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);
//    if ($slack_id != "") {
//        $loggedUserInfo = HR::getUserInfofromSlack($slack_id);
//    }
//     $userid = $loggedUserInfo['id'];
//     $role_id = HR::getUserRole($userid);
//     $valid = HR::validateRoleAction($role_id,$action);
//     if($valid == false){
//         $res['error'] = 1;
//         $res['data']="You are not authorise for this action";
//        echo json_encode($res);
//        die;
//     }
//     
//       
//}


//--------------------------------------------------
// start - added by arun june 2017 --- check on the basis of new roles implementation
// if( !empty($token) ){

$IS_SUPER_ADMIN = false;  // can do every thing. this is the person whose type=Admin in users table itself.




// implement only for actions which required token

if( $DO_TOKEN_VERIFICATION == false  ){
    //echo "action :: $action <br>";
    // these are the actions which not required token, so need to check for role
}else{
    // loggedUserInfo : this variable is compulsory since it is used in most of the actions
    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);
    if ( $slack_id != "" ) { // these are called from slack rtm
        $loggedUserInfo = HR::getUserInfofromSlack($slack_id);
    }
    // dont do any change in above lines


    if( strtolower($loggedUserInfo['role']) == 'admin' ){
        $IS_SUPER_ADMIN = true;
    }

    // echo '<pre>';
    // print_r( $loggedUserInfo );
    // echo '<pre>';
    

    if( $IS_SUPER_ADMIN === true ){
        // this is the admin on existing role basis, have access to all. type = "Admin" defined in users table
    }else{

    //print_r($loggedUserInfo);

    $loggedUserInfo_emp_id = $loggedUserInfo['id'];
    //if( $loggedUserInfo_emp_id == 343 ){ // uncomment this line after testing for meraj user
        $is_user_valid_action = HR::is_user_valid_action( $action, $loggedUserInfo_emp_id );
        if( $is_user_valid_action == true ){
            
        }else{
            header("HTTP/1.1 401 Unauthorized");
            exit;
            //send 401 unathoried request, this will show an alert message and redirect to home page

            // $res['error'] = 1;
            // $res['data']['message'] = "$action - You are not authorized to perform this action!!";
            // echo json_encode($res);
            // die;
        }
    //}
    }
}

// end - added by arun june 2017 --- check on the basis of new roles implementation

//------------------------------------------------------------------------
//------------------------------------------------------------------------
// actions defined as constants DONE
//------------------------------------------------------------------------
//------------------------------------------------------------------------
if ($action == 'login') {   //added in getActionsNotRequiredToken
    $username = $password = '';
    if (isset($PARAMS['username']) && isset($PARAMS['password'])) {
        $username = $PARAMS['username'];
        $password = md5($PARAMS['password']);
    }
    $res = HR::login($username, $password);
} else if ($action == 'logout') {   //added in getActionsNotRequiredToken
    $res = HR::logout($PARAMS['token']);
} else if ($action == 'forgot_password') { //added in getActionsNotRequiredToken
    $username = '';
    if (isset($PARAMS['username'])) {
        $username = $PARAMS['username'];
    }
    $res = HR::forgotPassword($username);
} else if ($action == "get_days_between_leaves") {  //added in getActionsNotRequiredToken
    $start_date = $PARAMS['start_date'];
    $end_date = $PARAMS['end_date'];
    $res = HR::getDaysBetweenLeaves($start_date, $end_date);
} else if ($action == 'save_google_payslip_drive_access_token') {
    $google_access_token = $PARAMS['google_access_token'];
    $res = HR::updateGooglepaySlipDriveAccessToken($google_access_token);
} else if ($action == 'get_all_leaves') {
    $res = HR::getAllLeaves();
} else if ($action == "admin_user_apply_leave") {   
    $userid = $PARAMS['user_id'];
    $from_date = $PARAMS['from_date'];
    $to_date = $PARAMS['to_date'];
    $no_of_days = $PARAMS['no_of_days'];
    $reason = $PARAMS['reason'];
    $day_status = $PARAMS['day_status'];
    if ($PARAMS['pending_id']) {
        $res = HR::applyLeave($userid, $from_date, $to_date, $no_of_days, $reason, $day_status, $leave_type = "", $late_reason = "", $PARAMS['pending_id']);
    } else {
        $res = HR::applyLeave($userid, $from_date, $to_date, $no_of_days, $reason, $day_status);
    }
} else if ($action == 'update_new_password') {
    $res = HR::updatePassoword($PARAMS);
} else if ($action == 'update_user_entry_exit_time') {    
    $userid = $loggedUserInfo['id'];
    $date = $PARAMS['date'];
    $entry_time = $PARAMS['entry_time'];
    $exit_time = $PARAMS['exit_time'];
    $reason = $PARAMS['reason'];
    $res = HR::insertUserInOutTimeOfDay($userid, $date, $entry_time, $exit_time, $reason, $isadmin = false);    
} else if ($action == 'get_user_worktime_detail') {
    $userid = $PARAMS['user_id'];
    $date = $PARAMS['date'];
    $res = HR::userCompensateTimedetail($userid, $date);    
} else if ($action == 'send_request_for_doc') {
    $leaveid = $PARAMS['leaveid'];
    $doc_request = $PARAMS['doc_request'];
    $comment = $PARAMS['comment'];
    $res = HR::leaveDocRequest($leaveid, $doc_request, $comment);
} else if ($action == 'add_extra_leave_day') {
    $leaveid = $PARAMS['leaveid'];
    $extra_day = $PARAMS['extra_day'];
    $res = HR::addExtraLeaveDay($leaveid, $extra_day);
} 
else if ($action == 'add_hr_comment') {
    $leaveid = $PARAMS['leaveid'];
    $hr_comment = $PARAMS['hr_comment'];
    $hr_approve = $PARAMS['hr_approve'];
    $res = HR::addHrComment($leaveid, $hr_comment, $hr_approve);
} else if ($action == 'delete_employee') {
    $userid = $PARAMS['user_id'];
    $res = HR::deleteUser($userid);
} else if ($action == 'change_leave_status') {
    $leaveid = $PARAMS['leaveid'];
    $newstatus = $PARAMS['newstatus'];
    $messagetouser = $PARAMS['messagetouser'];
    $res = HR::updateLeaveStatus($leaveid, $newstatus, $messagetouser);
} else if ($action == "get_managed_user_working_hours") {
    $userid = $PARAMS['userid'];
    $res = HR::geManagedUserWorkingHours($userid);
} else if ($action == 'update_day_working_hours') {
    $date = $PARAMS['date'];
    $time = $PARAMS['time'];
    $res = HR::updateDayWorkingHours($date, $time);
} else if ($action == 'add_new_employee') {
    $res = HR::addNewEmployee($PARAMS);
} else if ($action == 'update_user_day_summary') {
    $userid = $PARAMS['userid'];
    $date = $PARAMS['date'];
    $entry_time = $PARAMS['entry_time'];
    $exit_time = $PARAMS['exit_time'];
    $reason = $PARAMS['reason'];
    $res = HR::insertUserInOutTimeOfDay($userid, $date, $entry_time, $exit_time, $reason);
} else if ($action == 'get_user_machine') {
    $userid = $PARAMS['user_id'];
    $res = HR::getUserMachine($userid);
} else if ($action == 'assign_user_machine') {
    $machine_id = $PARAMS['machine_id'];
    $user_id = $PARAMS['user_id'];
    $res = HR::assignUserMachine($machine_id, $user_id);
} else if ($action == 'remove_machine_detail') {
    $id = $PARAMS['id'];
    $res = HR::removeMachineDetails($id);
} else if ($action == 'get_machines_detail') {   
    if (isset($PARAMS['sort']) && $PARAMS['sort'] != "") {
        $sort = trim($PARAMS['sort']);
        $res = HR::getAllMachineDetail($sort);
    }if (isset($PARAMS['status_sort']) && $PARAMS['status_sort'] != "") {
        $status_sort = trim($PARAMS['status_sort']);
        $res = HR::getAllMachineDetail($sort = false, $status_sort);
    } else {
        $res = HR::getAllMachineDetail();
    }    
} else if ($action == 'get_machine') {
    $id = $PARAMS['id'];
    $res = HR::getMachineDetail($id);
} else if ($action == 'update_office_machine') {
    $res = HR::UpdateOfficeMachine($PARAMS);    
} else if ($action == 'add_office_machine') {
    $res = HR::addOfficeMachine($PARAMS);
} else if ($action == 'delete_machine_status') {
    $res = HR::deleteMachineStatus($PARAMS['status']);
} else if ($action == 'get_machine_type_list') {
    $res = HR::getMachineTypeList();
} else if ($action == 'add_machine_type') {
    $res = HR::addMachineType($PARAMS);    
} else if ($action == 'add_machine_status') {
    $res = HR::addMachineStatus($PARAMS);
} else if ($action == 'get_machine_status_list') {
    $res = HR::getMachineStatusList();
} else if ($action == 'get_machine_count') {
    $res = HR::getMachineCount();
} else if ($action == 'get_all_user_previous_month_time') {
    $year = $PARAMS['year'];
    $month = $PARAMS['month'];
    $res = HR::getAllUserPrevMonthTime($year, $month);    
} else if ($action == 'get_user_previous_month_time') {
    $userid = $PARAMS['user_id'];
    $year = $PARAMS['year'];
    $month = $PARAMS['month'];
    $res = HR::getUserPrevMonthTime($userid, $year, $month);   
} else if ($action == 'change_employee_status') {
    $res = HR::changeEmployeeStatus($PARAMS);
} else if ($action == 'add_user_working_hours') {    
    $userid = $PARAMS['userid'];
    $date = $PARAMS['date'];
    $working_hours = $PARAMS['working_hours'];
    $reason = $PARAMS['reason'];
    if (isset($PARAMS['pending_id'])) {
        $res = HR::addUserWorkingHours($userid, $date, $working_hours, $reason, $PARAMS['pending_id']);
    } else {
        $res = HR::addUserWorkingHours($userid, $date, $working_hours, $reason);
    }    
} else if ($action == "get_holidays_list") {
    $res = HR::API_getYearHolidays();
} else if ($action == 'show_disabled_users') {
    $res = HR::getDisabledUsersList();
} else if ($action == "working_hours_summary") {
    $year = $PARAMS['year'];
    $month = $PARAMS['month'];
    $res = HR::getWorkingHoursSummary($year, $month);
} else if ($action == "get_enable_user") {
    $res = HR::getEnabledUsersListWithoutPass();
} else if ($action == 'add_roles') {    
    $name = $PARAMS['name'];
    $description = $PARAMS['description'];
    $res = HR::AddNewRole($name, $description);
} else if ($action == 'update_role') {
    $res = HR::updateRole($PARAMS);
} else if ($action == 'list_all_roles') {
    $res = HR::listAllRole();
} else if ($action == 'assign_user_role') {
    $userid = $PARAMS['user_id'];
    $roleid = $PARAMS['role_id'];
    $res = HR::assignUserRole($userid, $roleid);    
} else if ($action == 'delete_role') {
    $roleid = $PARAMS['role_id'];
    $res = HR::deleteRole($roleid);
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
} else if ($action == "apply_leave") {
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
        $res = HR::applyLeave($userid, $from_date, $to_date, $no_of_days, $reason, $day_status, $leave_type, $late_reason);
    } else {
        $res['error'] = 1;
        $res['data']['message'] = "userid not found";
    }
} else if ($action == "get_my_leaves") {
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
} else if ($action == "get_all_leaves_summary") {
    $year = $PARAMS['year'];
    $month = $PARAMS['month'];
    $res = HR::getAllUsersPendingLeavesSummary($year, $month);
} else if ($action == "get_users_leaves_summary") {
    $userid = $PARAMS['user_id'];
    $year = $PARAMS['year'];
    $month = $PARAMS['month'];
    $res = HR::getUsersPendingLeavesSummary($userid, $year, $month);
} else if ($action == 'get_user_role_from_slack_id') {
    if ($slack_id == "") {
        $res['error'] = 1;
        $res['data']['message'] = "Please provide user slack id!!";
    } else {
        $res = HR::getUserInfofromSlack($slack_id);
    }
} else if ($action == 'get_all_not_approved_leave_of_user') {    
    $userid = $PARAMS['user_id'];
    $res = HR::getAllNotApprovedleaveUser($userid);    
} else if ($action == 'approve_decline_leave_of_user') {  
    $leave_id = $PARAMS['leave_id'];
    $leave_status = $PARAMS['leave_status'];
    $res = HR::ApproveDeclineUserLeave($leave_id, $leave_status);    
} elseif ($action == 'cancel_applied_leave') { // action to cancel employee applied leaves    
    if (isset($PARAMS['user_id']) && $PARAMS['user_id'] != "") {
        $res = HR::cancelAppliedLeave($PARAMS);
    } else {
        $res['data']['message'] = 'Please give user_id ';
    }    
} elseif ($action == 'cancel_applied_leave_admin') { // action to cancel employee applied leaves
    if (isset($PARAMS['user_id']) && $PARAMS['user_id'] != "") {
        $res = HR::cancelAppliedLeave($PARAMS);
    } else {
        $res['data']['message'] = 'Please give user_id ';
    }
} elseif ($action == 'get_all_leaves_of_user') {
    $userid = $PARAMS['user_id'];
    if ($userid != "") {
        $res = HR::getUsersLeaves($userid);
    } else {
        $res['data']['message'] = 'Please give user_slackid ';
    }
} elseif ($action == 'get_lunch_stats') {
    if (isset($PARAMS['date']) && $PARAMS['date'] != "") {
        $date = $PARAMS['date'];
    } else {
        $date = date("Y-m-d");
    }
    $res = HR::getAllUserLunchDetail($date);    
} elseif ($action == "get_lunch_break_detail") {
    $userid = $loggedUserInfo['id'];
    if (!isset($PARAMS['month']) && $PARAMS['month'] == "") {
        $month = date('Y-m');
    } else {
        $month = $PARAMS['month'];
    }
    $res = HR::getlunchBreakDetail($userid, $month);    
} elseif ($action == "lunch_break") {
    $PARAMS['user_id'] = $loggedUserInfo['id'];
    $res = HR::lunchBreak($PARAMS);    
} elseif ($action == 'get_user_current_status') {
    $res = HR::getAllUserCurrentStatus();
} elseif ($action == 'get_role_from_slackid') {
    if ($slack_id != "") {
        $res = HR::getUserInfofromSlack($slack_id);
    } else {
        $res['data']['message'] = 'Please give user_slackid ';
    }
} else if ($action == 'updatebandwidthstats') {
    $data = $PARAMS['data'];
    $res = HR::updateBandwidthStats($data);
} else if ($action == 'send_slack_msg') {
    $slack_userChannelid = $PARAMS['channel'];
    $message = $PARAMS['message'];
    $res = HR::sendSlackMessageToUser($slack_userChannelid, $message);
} else if ($action == 'save_bandwidth_detail') {
    $data = $PARAMS['details'];
    $data = json_decode($data, true);
    $res = HR::saveBandwidthDetail($data);
} else if ($action == 'get_bandwidth_detail') {
    $res = HR::getBandwidthDetail();
} else if ($action == 'validate_unique_key') {
    $res = HR::validateUniqueKey($PARAMS);
}
echo json_encode($res);
?>
