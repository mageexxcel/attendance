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

// var_dump($PARAMS);
// this is added by arun to remove warning on 23 june 2017
$GET_action = "";
if( isset($_GET['action']) ){
    $GET_action = $_GET['action'];
}

if (isset($_GET['userslack_id']) || $GET_action == 'updatebandwidthstats' || $GET_action == 'send_slack_msg' 
    || $GET_action == 'save_bandwidth_detail' || $GET_action == 'get_bandwidth_detail' 
    || $GET_action == 'validate_unique_key'
    || $GET_action == 'reject_manual_attendance' || $GET_action == 'approve_manual_attendance' 
    || $GET_action == 'get_average_working_hours') {
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
if(isset($PARAMS['pagination_page'])){
    $pagination_page = $PARAMS['pagination_page'];
}
if(isset($PARAMS['pagination_limit'])){
    $pagination_limit = $PARAMS['pagination_limit'];
}

$pagination = array(
    'page' => $pagination_page,
    'limit' => $pagination_limit
);

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

// check for secret key
$secret_key = $PARAMS['secret_key'];
if(isset($secret_key) && $secret_key != ""){
    $validate_secret = HR::validateSecretKey($secret_key); 
    if($validate_secret) {
        $secret_actions = HR::getActionsForThirdPartyApiCall();
        foreach( $secret_actions as $secret_action ){
            if( $secret_action['name'] == $action ){
                $DO_TOKEN_VERIFICATION = false;
                $q = " UPDATE secret_tokens SET last_request = CURRENT_TIMESTAMP WHERE secret_key = '$secret_key' ";
                $runQuery = HR::DBrunQuery($q);
            }
        }   
    }
}

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

    $from_date = $to_date = $no_of_days = $reason = $day_status = '';

    $userid = $PARAMS['user_id'];
    if( isset($PARAMS['from_date']) ){
        $from_date = $PARAMS['from_date'];    
    }
    if( isset($PARAMS['to_date']) ){
        $to_date = $PARAMS['to_date'];    
    }
    if( isset($PARAMS['no_of_days']) ){
        $no_of_days = $PARAMS['no_of_days'];    
    }
    if( isset($PARAMS['reason']) ){
        $reason = $PARAMS['reason'];    
    }
    if( isset($PARAMS['day_status']) ){
        $day_status = $PARAMS['day_status'];    
    }
    
    if ($PARAMS['pending_id']) {

        $currentDate = date('Y-m-d');        
        $currentYear = date('Y');
        $currentMonth = date('m');
        $currentDateDate = date('d');

        $previousMonth = HR::_getPreviousMonth( $currentYear, $currentMonth );

        $reason = 'Previous month pending time is applied as leave!!';

        if( $from_date == '' ){
            $employeeLastPresentDay = HR::getEmployeeLastPresentDay( $userid, $previousMonth['year'], $previousMonth['month'] );
            $from_date = $employeeLastPresentDay['full_date'];
            $to_date = $employeeLastPresentDay['full_date'];
        }
        $res = HR::applyLeave($userid, $from_date, $to_date, $no_of_days, $reason, $day_status, $leave_type = "", $late_reason = "", $PARAMS['pending_id']);
    } else {
        $res = HR::applyLeave($userid, $from_date, $to_date, $no_of_days, $reason, $day_status);
    }
} else if ($action == 'update_new_password') {
    $res = HR::updatePassoword($PARAMS);
} 
// this is not required since functionality is stopped to update time by employee itself, also removed from front end - 12 july 2017
// else if ($action == 'update_user_entry_exit_time') {    
//     $userid = $loggedUserInfo['id'];
//     $date = $PARAMS['date'];
//     $entry_time = $PARAMS['entry_time'];
//     $exit_time = $PARAMS['exit_time'];
//     $reason = $PARAMS['reason'];
//     $res = HR::insertUserInOutTimeOfDay($userid, $date, $entry_time, $exit_time, $reason, $isadmin = false);    
// } 
else if ($action == 'get_user_worktime_detail') {
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
} else if ($action == 'revert_leave_status') {
    $leaveid = $PARAMS['leaveid'];
    $res = HR::revertLeaveStatus($leaveid);
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
} else if ($action == 'get_machine_history') {
    $machine_id = $PARAMS['machine_id'];
    $res = HR::getMachineHistory($machine_id);
} else if ($action == 'assign_user_machine') {
    $logged_user_id = $loggedUserInfo['id'];
    $machine_id = $PARAMS['machine_id'];
    $user_id = $PARAMS['user_id'];
    $res = HR::assignUserMachine($machine_id, $user_id, $logged_user_id);
} else if ($action == 'remove_machine_detail') {
    $id = $PARAMS['id'];
    // $userid = $PARAMS['userid'];
    $logged_user_id = $loggedUserInfo['id'];
    $res = HR::removeMachineDetails($id,$logged_user_id);
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
    $logged_user_id = $loggedUserInfo['id'];
    $res = HR::UpdateOfficeMachine( $logged_user_id, $PARAMS );    
} else if ($action == 'approve_machine') {
    $id = $PARAMS['id'];
    $res = HR::approveUnapprovedMachine($id);
} else if ($action == 'add_office_machine') {
    $logged_user_id = $loggedUserInfo['id'];
    $res = HR::addOfficeMachine($PARAMS, $logged_user_id);
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
        // below date and changes done by arun on 3 july 2017
        // this is case from when manually manipulating pending hours of users from manage_user_pending_hours page
        // $userNextWorkingDate = HR::getEmployeeNextWorkingDate( $userid );

        // this will be added to employee current month 1st working day;
        $userNextWorkingDate = HR::getEmployeeCurrentMonthFirstWorkingDate( $userid );

        // echo '<pre>';
        // print_r($userNextWorkingDate);

        $date = $userNextWorkingDate['full_date'];
        $reason = 'Previous month pending time merged!!';
        $res = HR::addUserWorkingHours($userid, $date, $working_hours, $reason, $PARAMS['pending_id']);
    } else {
        $res = HR::addUserWorkingHours($userid, $date, $working_hours, $reason);
    }    
} else if ($action == "get_holidays_list") {
    $year = $PARAMS['year'];
    $res = HR::API_getYearHolidays($year);
} else if ($action == "add_holiday") {
    $date = $PARAMS['holiday_date'];
    $name = $PARAMS['holiday_name'];
    $type = $PARAMS['holiday_type'];
    $res = HR::addHoliday($name, $date, $type);
} else if ($action == "update_holiday") {
    $date = $PARAMS['holiday_date'];
    $name = $PARAMS['holiday_name'];
    $type = $PARAMS['holiday_type'];
    $id = $PARAMS['holiday_id'];
    $res = HR::API_updateHoliday($id, $name, $date, $type);
} else if ($action == "delete_holiday") {    
    $id = $PARAMS['holiday_id'];
    $res = HR::API_deleteHoliday($id);
} else if ($action == "get_holiday_types_list") {
    $res = HR::API_getHolidayTypesList();
} else if ($action == "get_my_rh_leaves") {
    $year = $PARAMS['year'];
    $userid = $PARAMS['user_id'];
    $res = HR::API_getMyRHLeaves( $userid, $year );
} else if ($action == 'show_disabled_users') {  
    $res = HR::getDisabledUsersList($pagination);
} else if ($action == "working_hours_summary") {
    $year = $PARAMS['year'];
    $month = $PARAMS['month'];
    $res = HR::getWorkingHoursSummary($year, $month);
} else if ($action == "get_enable_user") {
    if( isset($PARAMS['secret_key']) || $PARAMS['secret_key'] != "" ){
        $validate_secret = HR::validateSecretKey($PARAMS['secret_key']);
        if($validate_secret){
            $role = 'guest';
        } 
    } else {
        $token = $PARAMS['token'];
        $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
        $role = $loggedUserInfo->role;
    }
    $res = HR::getEnabledUsersListWithoutPass($role);
} else if ($action == 'add_roles') { 
    $base_role_id = false;
    if( isset( $PARAMS['base_role_id']) && !empty( $PARAMS['base_role_id']) ){
        $base_role_id = $PARAMS['base_role_id'];
    }   
    $name = $PARAMS['name'];
    $description = $PARAMS['description'];
    $res = HR::AddNewRole($name, $description, $base_role_id);
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
} else if ($action == 'get_stats_attendance_summary') {   
    $res = HR::API_getStatsAttendanceSummary();
} else if ($action == 'delete_attendance_stats_summary') {   
    $year = $PARAMS['year'];
    $res = HR::API_deleteAttendanceStatsSummary($year);
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

    if ($slack_id != "") {
        $loggedUserInfo = HR::getUserInfofromSlack($slack_id);
        $userid = $loggedUserInfo['id'];
    }else{
        $userid = $loggedUserInfo['id'];    
    }
    
    if (!isset($PARAMS['month']) && $PARAMS['month'] == "") {
        $month = date('Y-m');
    } else {
        $month = $PARAMS['month'];
    }
    $res = HR::getlunchBreakDetail($userid, $month);    
} elseif ($action == "lunch_break") {
    if ($slack_id != "") {
        $loggedUserInfo = HR::getUserInfofromSlack($slack_id);
    }
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
} else if ($action == 'get_employee_life_cycle'){
    $res = HR::getEmployeeLifeCycle( $PARAMS['userid'] );
} else if ($action == 'update_employee_life_cycle' ){
    $res = HR::updateELC( $PARAMS['stepid'], $PARAMS['userid'] );
}

/********************************/
/****** inventory actions********/
/********************************/

// add inventory comment
else if ($action == 'add_inventory_comment' ){
    $user_id = $loggedUserInfo['id'];
    $inventory_id = $PARAMS['inventory_id'];
    $comment = $PARAMS['comment'];
    $res = HR::api_addInventoryComment($inventory_id, $user_id,  $comment);
}

/****************************************/
/****** manual attendacne actions********/
/****************************************/

else if ($action == 'add_manual_attendance' ){
    $user_id = $loggedUserInfo['id'];
    $reason = $PARAMS['reason'];
    $date = $PARAMS['date'];
    $resMessageEntry = "";  
    $resMessageExit = "";  
    if ( isset($PARAMS['entry_time']) && !empty($PARAMS['entry_time']) ){
        $entry_time = $PARAMS['entry_time'];
        $resMessageEntry = HR::addManualAttendance( $user_id, 'entry', $date, $entry_time, $reason );
    }
    if ( isset($PARAMS['exit_time']) && !empty($PARAMS['exit_time']) ){
        $exit_time = $PARAMS['exit_time'];
        $resMessageExit = HR::addManualAttendance( $user_id, 'exit', $date, $exit_time, $reason );
    }
    $res['error'] = 0;
    $res['message'] = "$resMessageEntry  $resMessageExit";
    $res['data'] = array();
}

else if ( $action == 'approve_manual_attendance'){
    $manual_attendance_id = $PARAMS['id'];
    $deductminutes = false;
    if( isset( $PARAMS['deductminutes']) ){
        $deductminutes = $PARAMS['deductminutes'];
    }
    $res = HR::approveManualAttendance( $manual_attendance_id, $deductminutes );
}

else if ( $action == 'reject_manual_attendance'){
    $manual_attendance_id = $PARAMS['id'];
    $res = HR::rejectManualAttendance( $manual_attendance_id );   
}

else if ( $action == 'update_inventory_status'){
    $logged_user_id = $loggedUserInfo['id'];
    $inventory_id = $PARAMS['inventory_id'];
    $new_status = $PARAMS['new_status'];
    $res = HR::updateInventoryStatus( $logged_user_id, $inventory_id, $new_status );   
}

else if ($action == 'get_unapproved_inventory_list') {
    $res = HR::getUnapprovedInventoryList();
}

// logges in user can unassigned his inventory
else if ($action == 'unassigned_my_inventory' ){
    $logged_user_id = $loggedUserInfo['id'];
    $inventory_id = $PARAMS['inventory_id'];
    $reason_of_removal = $PARAMS['comment'];
    $res = HR::removeMachineAssignToUser( $inventory_id, $logged_user_id, $reason_of_removal );
}

else if ( $action == 'get_unassigned_inventories' ){
    $logged_user_id = $loggedUserInfo['id'];
    $res = HR::api_getUnassignedInventories($logged_user_id);
}

else if ( $action == 'get_unapproved_inventories' ){
    $logged_user_id = $loggedUserInfo['id'];
    $res = HR::api_getUnapprovedInventories($logged_user_id);
}

/****************************************/
/****** Inventory audit******************/
/****************************************/
else if( $action == 'get_my_inventories' ){
    $logged_user_id = $loggedUserInfo['id'];
    $res = HR::api_getMyInventories($logged_user_id);
}

else if ( $action == 'add_inventory_audit' ){
    $logged_user_id = $loggedUserInfo['id'];
    $inventory_id = $PARAMS['inventory_id'];
    $audit_message = $PARAMS['audit_message'];
    $res = HR::api_addInventoryAudit( $inventory_id, $logged_user_id, $audit_message );
    // update user token when he audit the inventory
    if( HR::isInventoryAuditPending( $logged_user_id ) == false ){
        $newToken = HR::refreshToken( $token );
        $res['data']['new_token'] = $newToken;
    }
}

else if( $action == 'get_inventory_audit_status_month_wise' ){
    $currentTime = HR::_getDateTimeData();

    if( isset($PARAMS['month']) && $PARAMS['month'] != "" ){
        $month = $PARAMS['month'];
    } else {
        $month = $currentTime['current_month_number'];
    }

    if( isset($PARAMS['year']) && $PARAMS['year'] != "" ){
        $year = $PARAMS['year'];
    } else {
        $year = $currentTime['current_year_number'];
    }
    
    $res = HR::getInventoriesAuditStatusForYearMonth( $month, $year );
}

/****************************************/
/*******AVERAGE WORKING HOURS************/
/****************************************/

else if( $action == 'get_average_working_hours' ){
    $start_date = $PARAMS['start_date'];
    $end_date = $PARAMS['end_date'];
    $res = HR::api_getAverageWorkingHours( $start_date, $end_date );
}
else if ( $action == 'get_employees_history_stats' ){  
    $res = HR::getEmployeesHistoryStats();
}
/****************************************/
/******* THIRD PARTY API's ************/
/****************************************/
else if ( $action == 'generate_secret_key' ){  
    $app_name = $PARAMS['app_name'];
    $user_id = $tokenInfo['id'];   
    $res = HR::API_generateSecretKey( $app_name, $user_id );
}
else if ( $action == 'regenerate_secret_key' ){    
    $app_id = $PARAMS['app_id'];
    $res = HR::API_regenerateSecretKey( $app_id );
}
else if ( $action == 'delete_secret_key' ){  
    $app_id = $PARAMS['app_id'];
    $res = HR::API_deleteSecretKey( $app_id );
}
else if ( $action == 'get_all_secret_keys' ){  
    $res = HR::API_getAllSecretKeys();
}
/****************************************/
/************* Leaves Stats *************/
/****************************************/
else if ( $action == 'get_employees_leaves_stats' ){  
    $year = $PARAMS['year'];
    $month = $PARAMS['month'];
    $res = HR::API_getEmployeesLeavesStats( $year, $month );
}


else if ( $action == 'update_user_eth_token' ){
    $logged_user_id = $loggedUserInfo['id'];
    $eth_token = $PARAMS['eth_token'];
    $res = HR::updateUserEthToken( $logged_user_id, $eth_token );
}
/****************************************/
/************ User Meta Data ************/
/****************************************/
else if ( $action == 'update_user_meta_data' ){
    $user_id = $PARAMS['user_id'];
    $data = $PARAMS['data'];    
    $res = HR::API_updateUserMetaData( $user_id, $data );
}
else if ( $action == 'delete_user_meta_data' ){
    $user_id = $PARAMS['user_id'];
    $keys = $PARAMS['metadata_keys'];    
    $res = HR::API_deleteUserMetaData( $user_id, $keys );
}
else if ( $action == 'get_user_meta_data' ){
    $user_id = $PARAMS['user_id'];
    $res = HR::API_getUserMetaData( $user_id );
}
else if ( $action == 'employee_punch_time' ){
    $user_id = $PARAMS['user_id'];
    $time = $PARAMS['punch_time'];    
    $punch_time = substr( $time, 0, 8 );
    $punch_date = substr( $time, 9 );
    $punchTime = $punch_date . ' ' . $punch_time;
    $insertPunchTime = date('m-d-Y h:i:sA', strtotime($punchTime));    
    $res = HR::insertUserPunchTime( $user_id, $insertPunchTime );
}
else if ( $action == 'get_employee_recent_punch_time' ){
    $user_id = $PARAMS['user_id'];
    $res = HR::API_getUserRecentPunchTime( $user_id );
}
else if ( $action == 'get_employee_punches_by_date' ){
    $user_id = $PARAMS['user_id'];
    $date = $PARAMS['date'];
    $res = HR::API_getUserPunchesByDate( $user_id, $date );
}
else if ( $action == 'get_employees_monthly_attendance' ){
    $month = $PARAMS['month'];
    $year = $PARAMS['year'];
    $res = HR::API_getEmployeesMonthlyAttendance( $year, $month );
}
else if ( $action == 'add_attendance_keys' ){
    $userid_key = trim($PARAMS['userid_key']);
    $timing_key = trim($PARAMS['timing_key']);
    $res = HR::API_addAttendanceKeys( $userid_key, $timing_key );
}
else if ( $action == 'get_attendance_keys' ){    
    $res = HR::API_getAttendanceKeys();
}
else if ( $action == 'delete_attendance_keys' ){    
    $key_text = trim($PARAMS['key_text']);
    $field_name = trim($PARAMS['field_name']);
    $res = HR::API_deleteAttendanceKeys( $field_name, $key_text );
}
else if ( $action == 'add_reset_password_config' ){    
    $no_of_days = $PARAMS['pwd_reset_interval'];
    $status = $PARAMS['pwd_reset_status'];
    $res = HR::API_resetPasswordConfig( $no_of_days, $status );
}
else if ( $action == 'get_reset_password_config' ){    
    $res = HR::API_getResetPasswordConfig();
}
else if ( $action == 'get_user_rh_stats' ) {    
    if( !isset( $PARAMS['user_id'] ) || $PARAMS['user_id'] == "" ){
        $res['error'] = 1;
        $res['data']['message'] = "User id not found";
    } else {
        $userid = $PARAMS['user_id'];
        $year = $PARAMS['year'];
        $res = HR::API_getEmployeeRHStats( $userid, $year );
    }
}

echo json_encode($res);
?>
