<?php

/* Allow cross origin resource access methods */
header("Access-Control-Allow-Origin: *");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    exit(0);
}
require_once 'c-database.php'; // DB class file
require_once 'c-jwt.php'; // Access token class file
//comman format for dates = "Y-m-d" eg "04/07/2016"

class Salary extends DATABASE {

    private static $SLACK_client_id = '';
    private static $SLACK_client_secret = '';
    private static $SLACK_token = '';
    public static $Sunday = 'Sunday';
    public static $Saturday = 'Saturday';
    public static $Admin = 'Admin';
    public static $isAdmin = '';

// key for token generate do not change. 
    const JWT_SECRET_KEY = 'HR_APP';

    //-------------------------------------
    public function __construct() {
        $q = "SELECT * from admin";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        foreach ($rows as $p) {
            self::$SLACK_client_id = $p['client_id'];
            self::$SLACK_client_secret = $p['client_secret'];
            self::$SLACK_token = $p['token'];
        }
        //self::getSlackChannelIds();
        //die;
    }

    public static function setAdmin($data) {
        self::$isAdmin = $data;
    }

    //check user token in database table and its time difference
    public static function validateToken($token) {
        $db = self::getInstance();
        $mysqli = $db->getConnection();

        $token = mysqli_real_escape_string($mysqli, $token);
        $q = "select * from login_tokens where token='$token' ";


        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);



        if (sizeof($rows) > 0) {
            //start -- check for token expiry
            $tokenInfo = JWT::decode($token, self::JWT_SECRET_KEY);
            $tokenInfo = json_decode(json_encode($tokenInfo), true);
            if (is_array($tokenInfo) && isset($tokenInfo['login_time']) && $tokenInfo['login_time'] != "") {
                $token_start_time = $tokenInfo['login_time'];
                $current_time = time();
                $time_diff = $current_time - $token_start_time;
                $mins = $time_diff / 60;
                if ($mins > 60) { //if 60 mins more
                    return false;
                } else {
                    return true;
                }
            } else {
                return false;
            }
            //end -- check for token expiry
        } else {
            return false;
        }
    }

    // get user id  on basis of access token
    public static function getIdUsingToken($token) {
        $db = self::getInstance();
        $mysqli = $db->getConnection();

        $token = mysqli_real_escape_string($mysqli, $token);
        $q = "select * from login_tokens where token='$token' ";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRow($runQuery);
        if (sizeof($rows) > 0) {
            return $rows['userid'];
        } else {
            return false;
        }
    }

    //get details of an employee
    public function getUserDetail($userid) {
        $q = "SELECT users.*,user_profile.* FROM users LEFT JOIN user_profile ON users.id = user_profile.user_Id where users.status = 'Enabled' AND users.id = $userid";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        $arr = array();
        foreach ($row as $val) {
            $arr['id'] = $val['user_Id'];
            $arr['name'] = $val['name'];
            $arr['email'] = $val['work_email'];
            $arr['date_of_joining'] = $val['dateofjoining'];
            $arr['type'] = strtolower($val['type']);
        }
        return $arr;
    }

    // get all employee detail
    public function getAllUserDetail($data = false) {

        if ($data == "") {
            $q = "SELECT users.*,user_profile.* FROM users LEFT JOIN user_profile ON users.id = user_profile.user_Id where users.status = 'Enabled'";
        }
        if ($data != "") {
            $q = "SELECT users.*,user_profile.* FROM users LEFT JOIN user_profile ON users.id = user_profile.user_Id where users.status = 'Enabled' AND user_profile.team = '$data'";
        }
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        $row2 = array();
        foreach ($row as $val) {
            if ($val['username'] != strtolower(self::$Admin)) {
                $userid = $val['user_Id'];
                $val['user_bank_detail'] = self::getUserBankDetail($userid); // user bank details.
                $val['user_assign_machine']=self::getUserAssignMachines($userid);
                if (empty(self::$isAdmin)) {
                    unset($val['holding_comments']);
                }

                $row2[] = $val;
            }
        }

        return $row2;
    }

    //get employee salary info 
    public function getSalaryInfo($userid, $sort = false, $date = false) {

        $q = "select * from salary where user_Id = $userid";

        if ($sort == 'first_to_last') {
            $q = "select * from salary where user_Id = $userid ORDER by id ASC";
        }
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);

        // calculate applicable month from applicable_from and applicable_till date
        $applicable_month = 0;
        foreach($row as $key => $r){
            if(isset($r['applicable_from']) && $r['applicable_from'] != "" && $r['applicable_from'] != "0000-00-00" ){
                $applicable_from = $r['applicable_from'];
            }            
            if(isset($r['applicable_till']) && $r['applicable_till'] != "" && $r['applicable_till'] != "0000-00-00"){
                $applicable_till = $r['applicable_till'];
            }
            if( isset($applicable_from) && isset($applicable_till) ){                
                $begin = new DateTime( $applicable_from );
                $end = new DateTime( $applicable_till );
                $interval = DateInterval::createFromDateString('1 month');
                $period = new DatePeriod($begin, $interval, $end);                                
                $applicable_month = iterator_count($period);
            }            
            $row[$key]['applicable_month'] = $applicable_month;
            $applicable_month = 0;
        }
                        
        if ($date != false) {
            $arr = array();
            foreach ($row as $val) {
                if (strtotime($date) >= strtotime($val['applicable_from'])) {
                    $arr[] = $val;
                }
            }
            return $arr;
        } else {
            return $row;
        }
    }

    //get all payslips info of a employee
    public function getUserPayslipInfo($userid, $hidePayslip = false) {
        $q = "select * from payslips where user_Id = $userid ORDER by id DESC";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        if( $hidePayslip == true ){
            if( sizeof($row) > 0 ){
                foreach ($row as $key => $value) {
                    if( isset($value['payslip_url'])){
                        unset( $row[$key]['payslip_url']);    
                    }
                    if( isset($value['payslip_file_id'])){
                        unset( $row[$key]['payslip_file_id']);    
                    }
                }
            }
        }
        return $row;
    }

    // get employee balance leave info of previous month.
    public function getUserBalanceLaveInfo($userid, $year, $month) {
        $current_month = $year . "-" . $month;
        $prev_month = date('Y-m', strtotime($current_month . ' -1 month'));
        $q = "select * from payslip where user_Id = $userid ORDER by id DESC";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        $r = array();
        foreach ($row as $val) {
            if (strpos($val['payslip_month'], $prev_month) !== false) {
                $r = $val;
            }
        }
        return $r;
    }

    //get all employee payslip info of particular year and month
    public function getAllUserPayslip($userid, $year, $month) {
        $q = "select * from payslips where month='$month' AND year = '$year'";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        return $row;
    }

    // get Salary details on basis of salary id of employee    
    public function getSalaryDetail($salary_id) {
        $ret = array();
        $q = "select * from salary_details where salary_id = $salary_id";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        foreach ($row as $val) {
            $ret[$val['key']] = $val['value'];
        }
        return $ret;
    }

    //get Holding amount detail of a employee
    public function getHoldingDetail($user_id) {
        $ret = array();
        $q = "select * from user_holding_info where user_Id = $user_id";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        // calculate holding_month from holding_start_date and holding_end_date
        $applicable_month = 0;
        foreach($row as $key => $r){
            if(isset($r['holding_start_date']) && $r['holding_start_date'] != "" && $r['holding_start_date'] != "0000-00-00" ){
                $holding_start_date = $r['holding_start_date'];
            }            
            if(isset($r['holding_end_date']) && $r['holding_end_date'] != "" && $r['holding_end_date'] != "0000-00-00"){
                $holding_end_date = $r['holding_end_date'];
            }
            if( isset($holding_start_date) && isset($holding_end_date) ){                
                $begin = new DateTime( $holding_start_date );
                $end = new DateTime( $holding_end_date );
                $interval = DateInterval::createFromDateString('1 month');
                $period = new DatePeriod($begin, $interval, $end);                                
                $holding_month = iterator_count($period);
            }            
            $row[$key]['holding_month'] = $holding_month;
            $holding_month = 0;
        }
        $ret = $row;
        return $ret;
    }

    // get all enabled employee list.
    public static function getEnabledUsersList() {
        $q = "SELECT users.*,user_profile.* FROM users LEFT JOIN user_profile ON users.id = user_profile.user_Id where users.status = 'Enabled' ";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        $newRows = array();
        foreach ($rows as $pp) {
            if ($pp['username'] == self::$Admin || $pp['username'] == strtolower(self::$Admin)) {
                
            } else {
                if (empty(self::$isAdmin)) {
                    unset($pp['holding_comments']);
                }
                $newRows[] = $pp;
            }
        }
        return $newRows;
    }

    // get refresh token of google drive from database
    public function getrefreshToken() {
        $ret = array();
        $q = "select * from config where type = 'google_payslip_drive_token'";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRow($runQuery);
        return $row;
    }

    public static function getUnapprovedMachineList() {        
        $q = "select * from machines_list where approval_status = 0 ORDER BY id DESC"; //
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        $return = array();
        $return['error'] = 0;
        $return['data'] = $row;
        return $return;    
    }

    public static function userCommentOnMachine($userId,$serial_number,$comment) {
        $r_error = 1;
        $r_message = "";

        $q = "select id from machines_list where serial_number = '$serial_number'";
        $runQuery = self::DBrunQuery($q);
        $machine_id = self::DBfetchRows($runQuery);

        if(empty($comment)) {
            $r_message = "Comment field must not be Empty!!";
        }
        else {
            $comment = self::DBescapeString($comment);
            $date = date('Y-m-d');
            $q = "INSERT INTO user_comment_machine (user_Id,machine_id,comment,comment_date) VALUES ($userId,$machine_id,'$comment','$date')";
            self::DBrunQuery($q);
            $r_error = 0;            
            $r_message = "Comment added Successfully !!";
        }
        $return = array();
        $r_data = array();
        $return['error'] = $r_error;
        $r_data['message'] = $r_message;
        $return['data'] = $r_data;

        return $return;
    }

    //Update employee salary details with slack notification message send to employee 
    public static function updateSalary($data) {

        $db = self::getInstance();
        $mysqli = $db->getConnection();

        $token = $data['token'];
        $update_by = self::getUserName($token);
        if ($update_by == false) {
            return "Invalid token";
        }
        $applicable_month = $data['applicable_month'];
        $applicable_till = date('Y-m-d', ( strtotime("+$applicable_month months", strtotime($data['applicable_from']))) - 1 );        
        $ins = array(
            'user_Id' => $data['user_id'],
            'total_salary' => $data['total_salary'],
            'last_updated_on' => date("Y-m-d"),
            'updated_by' => $update_by,
            'leaves_allocated' => $data['leave'],
            'applicable_from' => date("Y-m-d", strtotime($data['applicable_from'])),
            'applicable_till' => $applicable_till
        );
        self::DBinsertQuery('salary', $ins);
        $salary_id = mysqli_insert_id($mysqli);

        $ins2 = array(
            'Special_Allowance' => $data['special_allowance'],
            'Medical_Allowance' => $data['medical_allowance'],
            'Conveyance' => $data['conveyance'],
            'HRA' => $data['hra'],
            'Basic' => $data['basic'],
            'Arrears' => $data['arrear'],
            'Increment_Amount' => $data['increment_amount'],
            'TDS' => $data['tds'],
            'Misc_Deductions' => $data['misc_deduction'],
            'Advance' => $data['advance'],
            'Loan' => $data['loan'],
            'EPF' => $data['epf']
        );
        // In Salary structure type = 1 for earnings 
        // type= 2 for deductions
        $type = 1;
        foreach ($ins2 as $key => $val) {
            // change value of type on and after array key TDS    
            if ($key == 'TDS') {
                $type = 2;
            }
            $query = "Insert Into salary_details (`salary_id`, `key`, `value`,`type`) Value ($salary_id,'$key',$val,$type)";
            $runQuery = self::DBrunQuery($query);
        }
        $userid = $data['user_id'];
        $userInfo = self::getUserInfo($userid);
        $userInfo_name = $userInfo['name'];
        $slack_userChannelid = $userInfo['slack_profile']['slack_channel_id'];
        $message = "Hey $userInfo_name !!  \n Your Salary details are updated \n Details: \n ";
        $message = $message . "Total Salary = " . $data['total_salary'] . " Rs \n";
        $message = $message . "Basic = " . $data['basic'] . " Rs \n";
        $message = $message . "HRA = " . $data['total_salary'] . " Rs \n";
        $message = $message . "Medical Allowance = " . $data['medical_allowance'] . " Rs \n";
        $message = $message . "Special Allowance = " . $data['special_allowance'] . " Rs \n";
        $message = $message . "Arrears = " . $data['arrear'] . " Rs \n";
        $message = $message . "EPF = " . $data['epf'] . " Rs \n";
        $message = $message . "Loan = " . $data['loan'] . " Rs \n";
        $message = $message . "Advance = " . $data['advance'] . " Rs \n";
        $message = $message . "Misc Deductions = " . $data['Misc_deduction'] . " Rs \n";
        $message = $message . "TDS = " . $data['tds'] . " Rs \n";
        $slackMessageStatus = self::sendSlackMessageToUser($slack_userChannelid, $message); // send slack notification.
        return "Successfully Salary Updated";
    }

    //get the name of employee
    public static function getUserName($data) {
        $q = "select login_tokens.userid,user_profile.name from login_tokens LEFT JOIN user_profile ON login_tokens.userid = user_profile.user_Id where login_tokens.token='$data'";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRow($runQuery);
        if ($rows['name'] != "") {
            return $rows['name'];
        } else {
            return false;
        }
    }

    //Insert the holding amount details of a employee
    public function insertHoldingInfo($data) {
        $holding_month = $data['holding_month'];
        $holding_end_date = date('Y-m-d', ( strtotime("+$holding_month months", strtotime($data['holding_start_date']))) - 1 );        
        $ins = array(
            'user_Id' => $data['user_id'],
            'holding_amt' => $data['holding_amt'],
            'holding_start_date' => $data['holding_start_date'],
            'holding_end_date' => $holding_end_date,
            'reason' => $data['reason'],
            'last_updated_on' => date("Y-m-d")
        );
        $userid = $data['user_id'];
        $username = self::getUserDetail($userid);
        $res = self::DBinsertQuery('user_holding_info', $ins);
        if ($res == false) {
            return false;
        } else {
            $message = "Holding amount info of an Employee " . $username['name'] . " is added in database \n Details: \n ";
            $message = $message . "Holding Amount = " . $data['holding_amt'] . "\n";
            $message = $message . "Holding start date= " . $data['holding_start_date'] . "\n";
            $message = $message . "Holding end date = " . $holding_end_date . "\n";
            $message = $message . "Reason = " . $data['reason'] . "\n";
            $slack_userChannelid = "hr_system";
            // $slackMessageStatus = self::sendSlackMessageToUser($slack_userChannelid, $message);
            return "Successfully Inserted into table";
        }
    }

    //insert or update employee bank info of na employee
    public function insertUserBankInfo($data) {
        $ins = array(
            'user_Id' => $data['user_id'],
            'bank_name' => $data['bank_name'],
            'bank_address' => $data['address'],
            'bank_account_no' => $data['account_no'],
            'ifsc' => $data['ifsc']
        );
        $whereField = 'user_Id';
        $whereFieldVal = $data['user_id'];
        $q = 'select * from user_bank_details where user_Id=' . $whereFieldVal;
        $run = mysql_query($q);
        $num_rows = mysql_num_rows($run);
        if ($num_rows > 0) {
            $res = self::DBupdateBySingleWhere('user_bank_details', $whereField, $whereFieldVal, $ins);
        }
        if ($num_rows <= 0) {
            $res = self::DBinsertQuery('user_bank_details', $ins);
        }
        if ($res == false) {
            return false;
        } else {
            return "Successfully Inserted into table";
        }
    }

    // get an employee all info with iits slack info details
    public static function getUserInfo($userid) {
        $q = "SELECT users.*,user_profile.* FROM users LEFT JOIN user_profile ON users.id = user_profile.user_Id where users.id = $userid ";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRow($runQuery);
        if (empty(self::$isAdmin)) {
            unset($row['holding_comments']);
        }
        //slack info of user
        $userSlackInfo = self::getSlackUserInfo($row['work_email']);
        $row['slack_profile'] = $userSlackInfo;
        return $row;
    }

    //update employee profile details with update slack message notification
    public function UpdateUserInfo($data) {
        $r_error = 1;
        $r_message = "";
        $data['updated_on'] = date("Y-m-d");
        $r_data = array();
        $userid = $data['user_id'];
        $user_profile_detail = self::getUserprofileDetail($userid);
        $whereField = 'user_Id';
        $whereFieldVal = $userid;
        $msg = array();
        $res = false;
        foreach ($user_profile_detail as $key => $val) {
            if (array_key_exists($key, $data)) {
                if ($data[$key] != $user_profile_detail[$key]) {
                    $arr = array();
                    $arr[$key] = $data[$key];
                    $res = self::DBupdateBySingleWhere('user_profile', $whereField, $whereFieldVal, $arr);
                    $msg[$key] = $data[$key];
                }
            }
        }
        if ($res == false) {
            $r_error = 0;
            $r_message = "No fields updated into table";
            $r_data['message'] = $r_message;
        } else {
            $userInfo = self::getUserInfo($userid);
            $userInfo_name = $userInfo['name'];
            $slack_userChannelid = $userInfo['slack_profile']['slack_channel_id'];

            if ($data['send_slack_msg'] == "") {

                if (sizeof($msg > 0)) {
                    $message = "Hey $userInfo_name !!  \n Your profile details are updated \n Details: \n ";
                    foreach ($msg as $key => $valu) {
                        if ($key != "holding_comments" && $key != "termination_date") {
                            $message = $message . "$key = " . $valu . "\n";
                        }
                    }

                    $slackMessageStatus = self::sendSlackMessageToUser($slack_userChannelid, $message); // send slack message
                }
            }
            $r_error = 0;
            $r_message = "Employee details updated successfully!!";
            $r_data['message'] = $r_message;
        }
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

// CURL operation for slack api
    public static function getHtml($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    ///----slacks fns
    public static function sendSlackMessageToUser($channelid, $message) {
        //include url variables files. 
        include "config.php";
        $return = false;
        $message = '[{"text": "' . $message . '", "fallback": "Message Send to Employee", "color": "#36a64f "}]';
        $message = str_replace("", "%20", $message);
        $message = stripslashes($message); // to remove \ which occurs during mysqk_real_escape_string
        $url = $send_slack_message_url . self::$SLACK_token . "&attachments=" . urlencode($message) . "&channel=" . $channelid;
        $html = self::getHtml($url);
        if ($html === false) {
            
        } else {
            $fresult = json_decode($html, true);
            if (is_array($fresult) && isset($fresult['ok'])) {
                $return = true;
            }
        }
        return $return;
    }

    //get employee slack channel id
    public static function getSlackChannelIds() {
        //include url variables files
        include "config.php";
        $return = array();
        $url = $slack_channel_id_url . self::$SLACK_token;
        $html = self::getHtml($url);
        if ($html === false) {
            
        } else {
            $fresult = json_decode($html, true);
            if (isset($fresult['ims']) && sizeof($fresult['ims']) > 0) {
                foreach ($fresult['ims'] as $pp) {
                    $return[] = $pp;
                }
            }
        }
        return $return;
    }

    //get slack info of an employee
    public static function getSlackUserInfo($emailid) {
        $return = false;
        $allSlackUsers = self::getSlackUsersList();
        if (sizeof($allSlackUsers) > 0) {
            foreach ($allSlackUsers as $sl) {
                if ($sl['profile']['email'] == $emailid) {
                    $return = $sl;
                    break;
                }
            }
        }
        return $return;
    }

    //get all slack user list
    public static function getSlackUsersList() {
        include "config.php";
        $return = array();
        $slackChannelIdsLists = self::getSlackChannelIds();
        $url = $get_all_slack_user_list_url . self::$SLACK_client_id . "&token=" . self::$SLACK_token . "&client_secret=" . self::$SLACK_client_secret; // slack user list url with variable token and client_secret.
        $html = self::getHtml($url);
        if ($html === false) {
            //echo 'Curl error: ' . curl_error($ch);
        } else {
            $fresult = json_decode($html, true);
        }
        if ($fresult) {
            if (isset($fresult['members']) && sizeof($fresult['members']) > 0) {
                foreach ($fresult['members'] as $pp) {
                    $slack_channel_id_info = array();
                    $slack_channel_id = '';
                    foreach ($slackChannelIdsLists as $chid) {
                        if ($pp['id'] == $chid['user']) {
                            $slack_channel_id = $chid['id'];
                            $slack_channel_id_info = $chid;
                            break;
                        }
                    }
                    //added for channedl details 
                    $pp['slack_channel_id_info'] = $slack_channel_id_info;
                    $pp['slack_channel_id'] = $slack_channel_id;
                    $return[] = $pp;
                }
            }
        }
        return $return;
    }

    //get employee hr system profile details 
    public function getUserprofileDetail($userid) {
        $q = "SELECT users.status,user_profile.* FROM users LEFT JOIN user_profile ON users.id = user_profile.user_Id where users.status = 'Enabled' AND users.id = $userid";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRow($runQuery);
        if (empty(self::$isAdmin)) {
            unset($row['holding_comments']);
        }
        // addition on 21st june 2018 by arun to return profile image also. i.e slack image
        $slack_image = "";
        $allSlackUsers = self::getSlackUsersList();
        foreach ($allSlackUsers as $s) {
            if ($s['profile']['email'] == $row['work_email']) {
                $sl = $s;
                break;
            }
        }
        if (sizeof($sl) > 0) {
            $slack_image = $sl['profile']['image_192'];
        }
        $row['profileImage'] = $slack_image;

        $arr = "";
        $arr = $row;
        return $arr;
    }

    //get all holiday list of particular year and month
    public function getCurrentMonthHoliday($year, $month) {
        $q = "SELECT * from holidays where date like '%" . $year . "-" . $month . "%'";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        $arr = "";
        $arr = $row;
        return $arr;
    }

    // get employee bank details    
    public function getUserBankDetail($userid) {
        $q = "SELECT * FROM user_bank_details WHERE user_Id = $userid";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRow($runQuery);
        $arr = "";
        $arr = $row;
        return $arr;
    }

// get employee profile details with bank details.
    public static function getUserDetailInfo($userid) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $user_bank_detail = self::getUserBankDetail($userid);
        $user_profile_detail = self::getUserprofileDetail($userid);
        $user_assign_machine = self::getUserAssignMachines($userid);
        $return = array();
        $r_error = 0;
        $return['error'] = $r_error;
        $return['data']['user_profile_detail'] = $user_profile_detail;
        $return['data']['user_bank_detail'] = $user_bank_detail;
        $return['data']['user_assign_machine'] = $user_assign_machine;
        
        return $return;
    }

    // update employee bank details with slack notification message to employee 
    public static function UpdateUserBankInfo($data) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $userid = $data['user_id'];
        $userInfo = self::getUserInfo($userid);
        $userInfo_name = $userInfo['name'];
        $slack_userChannelid = $userInfo['slack_profile']['slack_channel_id'];
        $f_bank_name = $data['bank_name'];
        $f_bank_address = $data['bank_address'];
        $f_bank_account_no = $data['bank_account_no'];
        $f_ifsc = $data['ifsc'];
        $message = "";
        $q = "SELECT * from user_bank_details WHERE user_Id=$userid";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRow($runQuery);
        if ($row == false) {
            $q = "INSERT INTO user_bank_details ( user_id, bank_name, bank_address, bank_account_no, ifsc ) VALUES ( $userid, '$f_bank_name', '$f_bank_address', '$f_bank_account_no', '$f_ifsc' )";
            self::DBrunQuery($q);
            $message = "Hey $userInfo_name !!  \n Your bank details are inserted \n Details: \n ";
            $message = $message . "Bank name = $f_bank_name \n ";
            $message = $message . "Bank address = $f_bank_address \n ";
            $message = $message . "Bank Account No = $f_bank_account_no \n ";
            $message = $message . "Bank IFSC Code = $f_ifsc \n ";
            $r_error = 0;
            $r_message = "Data Successfully Updated";
            $r_data['message'] = $r_message;
        } else {
            $q = "UPDATE user_bank_details set bank_name='$f_bank_name', bank_address='$f_bank_address', bank_account_no='$f_bank_account_no', ifsc='$f_ifsc' WHERE user_Id=$userid";
            self::DBrunQuery($q);
            $message = "Hey $userInfo_name !!  \n Your bank details are updated \n Details: \n ";
            $message = $message . "Bank name = $f_bank_name \n ";
            $message = $message . "Bank address = $f_bank_address \n ";
            $message = $message . "Bank Account No = $f_bank_account_no \n ";
            $message = $message . "Bank IFSC Code = $f_ifsc \n ";
            $r_error = 0;
            $r_message = "Data Successfully Inserted";
            $r_data['message'] = $r_message;
        }
        if ($message != "") {
            $slackMessageStatus = self::sendSlackMessageToUser($slack_userChannelid, $message); // send slack message
        }
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    public static function generateUserSalary($userid) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $salary_info = self::getSalaryInfo($userid);
        $num = sizeof($salary_info);
        if ($num > 0) {
            $res2 = Salary::getSalaryDetail($salary_info[$num - 1]);
            print_r($res2);
        } else {
            $r_message = "No salary detail for this user";
            $r_data['message'] = $r_message;
        }
    }

    // create a new client
    public static function createNewClient($data) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $ins = array(
            'name' => $data['name'],
            'address' => $data['address'],
            'created_on' => date("Y-m-d"),
            'status' => 1
        );
        $res = self::DBinsertQuery('clients', $ins);
        if ($res == false) {
            $r_error = 1;
            $r_message = "Error occured while inserting data";
            $r_data['message'] = $r_message;
        } else {
            $r_error = 0;
            $r_message = "Data Successfully Inserted";
            $r_data['message'] = $r_message;
        }
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

// update existing client details 
    public static function UpdateClientDetails($data) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $clientid = $data['client_id'];
        $q = "SELECT * FROM clients WHERE id=$clientid";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRow($runQuery);
        $whereField = 'id';
        $whereFieldVal = $clientid;
        $msg = array();
        $res = false;
        foreach ($row as $key => $val) {
            if (array_key_exists($key, $data)) {
                if ($data[$key] != $row[$key]) {
                    $arr = array();
                    $arr[$key] = $data[$key];
                    $res = self::DBupdateBySingleWhere('clients', $whereField, $whereFieldVal, $arr);
                }
            }
        }
        if ($res == false) {
            $r_error = 0;
            $r_message = "No fields updated into table";
            $r_data['message'] = $r_message;
        } else {
            $r_error = 0;
            $r_message = "Successfully Updated into table";
            $r_data['message'] = $r_message;
        }
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    // get all clients list
    public static function getAllClient() {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $query = "SELECT * FROM clients ORDER BY id DESC";
        $runQuery = self::DBrunQuery($query);
        $res = self::DBfetchRows($runQuery);
        if ($res == false) {
            $r_error = 1;
            $r_message = "Error occured while fetching data";
            $r_data['message'] = $r_message;
        } else {
            $r_error = 0;
            $r_data = $res;
        }
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    // create client invoice pdf and save invoice detail.  
    public static function createClientInvoice($data) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $arr_item = $data['items'];
        $items = json_encode($data['items']);
        $ins = array(
            'client_id' => $data['client_id'],
            'client_name' => $data['client_name'],
            'client_address' => $data['client_address'],
            'currency' => $data['currency'],
            'items' => $items,
            'sub_total' => $data['sub_total'],
            'service_tax' => $data['service_tax'],
            'total_amount' => $data['total_amount'],
            'due_date' => $data['due_date'],
            'created_on' => date("Y-m-d"),
            'status' => 1
        );
//        $url = "http://excellencetechnologies.co.in/imap/upload_invoice.php?file=".  urlencode($file_path);
//        echo $url;
//        
//        $ch = curl_init();
//            curl_setopt($ch, CURLOPT_URL, $url);
//            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
//            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//            $result = curl_exec($ch);
//    if ($result === false) {
//        echo 'Curl error: ' . curl_error($ch);
//    } else {
//        $fresult = json_decode($result, true);
//    }
//    curl_close($ch);
//            echo $result;
        $res = self::DBinsertQuery('clients_invoices', $ins);
        if ($res == false) {
            $r_error = 1;
            $r_message = "Error occured while inserting data";
            $r_data['message'] = $r_message;
        } else {
            $invoice_no = mysql_insert_id();
            $html = '';
            $html = ob_start();
            require_once 'template_invoice.php';
            $html = ob_get_clean();
            $html = str_replace("##client_name##", $data['client_name'], $html);
            $html = str_replace("##client_address##", $data['client_address'], $html);
            $html = str_replace("##due_date##", $data['due_date'], $html);
            $html = str_replace("##invoice_no##", $invoice_no, $html);
            $html = str_replace("##sub_total##", $data['sub_total'], $html);
            $html = str_replace("##service_tax##", $data['service_tax'], $html);
            $html = str_replace("##total_amount##", $data['total_amount'], $html);
            $html = str_replace("##currency##", $data['currency'], $html);
            $suc = self::createPDf($html, $invoice_no);
            $file_path = $suc;
            $query = "UPDATE clients_invoices SET file_address= '" . mysql_real_escape_string($file_path) . "' WHERE id = $invoice_no";
            mysql_query($query);
            $r_error = 0;
            $r_message = "Successfully Created";
            $r_data['message'] = $r_message;
        }
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    // delete client invoice 
    public static function DeleteInvoice($data) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $q = "DELETE FROM clients_invoices WHERE id =" . $data['invoice_id'];
        $res = self::DBrunQuery($q);
        if ($res == false) {
            $r_error = 1;
            $r_message = "Error occured while deleting invoice";
            $r_data['message'] = $r_message;
        } else {
            $r_error = 0;
            $r_message = "Invoice Deleted";
            $r_data['message'] = $r_message;
        }
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    // get client all details 
    public static function getClientDetails($client_id) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $query = "SELECT * FROM clients WHERE id=$client_id";
        $runQuery = self::DBrunQuery($query);
        $res = self::DBfetchRow($runQuery);
        $query2 = "SELECT * FROM clients_invoices WHERE client_id=$client_id ORDER BY id DESC";
        $runQuery2 = self::DBrunQuery($query2);
        $res2 = self::DBfetchRows($runQuery2);
        $res4 = array();
        foreach ($res2 as $val) {
            $res3 = $val;
            $res3['items'] = json_decode($val['items']);
            $res4[] = $res3;
        }
        if ($res == false || sizeof($res4) < 0) {
            $r_error = 1;
            $r_message = "Client and invoice not found";
            $r_data['message'] = $r_message;
        } else {
            $r_error = 0;
            $r_data['client_info'] = $res;
            $r_data['invoices'] = $res4;
        }
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    public static function createEmailTempPdf($data, $include_header_footer = false ) {

        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $html = ob_start();
        
        require_once 'templatehead.php';
        
        $html = ob_get_clean();

        $q = 'Select * from template_variables';
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);


        foreach ($row as $s) {
            $html = str_replace($s['name'], $s['value'], $html);
        }

        $html = str_replace("#page_content", $data['template'], $html);
        $file_name = $data['file_name'];
        $path = "payslip";
        $testfile = 'payslip/' . $file_name;
        unlink($testfile);
        $suc = self::createPDf($html, $file_name, $path);
        $r_error = 0;
        $r_data['message'] = $suc;

        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    // function to create pdf file from html text 
    public static function createPDf($html, $invoice_no, $path = false) {
        //dom pdf library file


        require_once "dompdf-master/dompdf_config.inc.php";
        $pname = $invoice_no . ".pdf";
        $theme_root = "invoice/" . $pname;
        if ($path != false) {
            $theme_root = $path . "/" . $pname;
        }
        if (get_magic_quotes_gpc())
            $html = stripslashes($html);
        $dompdf = new DOMPDF();
        $dompdf->load_html($html);
        $dompdf->render();
        //    $dompdf->stream("test.pdf");
        $output = $dompdf->output();
        try {
            file_put_contents($theme_root, $output);
            return $theme_root;
        } catch (Exception $e) {
            return $e;
        }
    }

//employee payslip----------------------------------------
    // create employee payslip and save pdf to google drive
    public static function createUserPayslip($data) {

        $db = self::getInstance();
        $mysqli = $db->getConnection();

        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $date = $data['year'] . "-" . $data['month'] . "-01";
        $month_name = date('F', strtotime($date));
        $ins = array(
            'user_Id' => $data['user_id'],
            'month' => $data['month'],
            'year' => $data['year'],
            'total_leave_taken' => $data['total_leave_taken'],
            'leave_balance' => $data['leave_balance'],
            'allocated_leaves' => $data['allocated_leaves'],
            'paid_leaves' => $data['paid_leaves'],
            'unpaid_leaves' => $data['unpaid_leaves'],
            'final_leave_balance' => $data['final_leave_balance'],
            'misc_deduction_2' => $data['misc_deduction_2'],
            'bonus' => $data['bonus'],
            'payslip_url' => ""
        );



        // check refresh token of google drive 
        $check_google_drive_connection = self::getrefreshToken();
        if (!is_array($check_google_drive_connection) && sizeof($check_google_drive_connection) > 0) {
            $r_error = 1;
            $r_message = "Refresh token not found. Connect do google login first";
            $r_data['message'] = $r_message;
        } else {
            $userid = $data['user_id'];
            $userInfo = self::getUserInfo($userid);
            $userInfo_name = $userInfo['name'];
            $html = '';
            $html = ob_start();
            //get payslip template
            require_once 'template_payslip.php';

            $html = ob_get_clean();

            $q = "SELECT * FROM payslips where user_Id =" . $data['user_id'] . " AND month ='" . $data['month'] . "' AND year ='" . $data['year'] . "'";

            $runQuery = self::DBrunQuery($q);
            $row = self::DBfetchRow($runQuery);
            //if current month payslip already present in database
            if (mysqli_num_rows($runQuery) > 0) {
                $payslip_no = $row['id'];
                $file_id = $row['payslip_file_id'];
                $payslip_name = $month_name;
                //create pdf file of payslip template    
                $suc = self::createPDf($html, $payslip_name, $path = "payslip");

                $whereFieldVal = $row['id'];
                $whereField = 'id';
                foreach ($row as $key => $val) {
                    if (array_key_exists($key, $data)) {
                        if ($data[$key] != $row[$key]) {
                            $arr = array();
                            $arr[$key] = $data[$key];


                            $res = self::DBupdateBySingleWhere('payslips', $whereField, $whereFieldVal, $arr);
                        }
                    }
                }

                // upload created payslip pdf file in google drive
                $google_drive_file_url = self::saveFileToGoogleDrive($payslip_name, $userInfo_name, $userid, $file_id);



                $query = "UPDATE payslips SET payslip_url= '" . mysqli_real_escape_string($mysqli, $google_drive_file_url['url']) . "' , payslip_file_id = '" . $google_drive_file_url['file_id'] . "', status = 0 WHERE id = $payslip_no";
                self::DBrunQuery($query);
                // if send mail option is true
                if ($data['send_email'] == 1 || $data['send_email'] == '1') {

                    self::sendPayslipMsgEmployee($payslip_no);
                }

                if ($data['send_slack_msg'] == 1 || $data['send_slack_msg'] == '1') {
                    // send slack notification message 
                    self::sendPayslipMsgEmployee($payslip_no, $data);
                }

                $r_error = 0;
                $r_message = "Salary slip updated successfully";
                $r_data['message'] = $r_message;
            } else { // if current month payslip is not present in database
                $res = self::DBinsertQuery('payslips', $ins);

                if ($res == false) {
                    $r_error = 1;
                    $r_message = "Error occured while inserting data";
                    $r_data['message'] = $r_message;
                } else {
                    $payslip_no = mysqli_insert_id($mysqli);
                    $payslip_name = $month_name;
                    //create pdf of payslip template
                    $suc = self::createPDf($html, $payslip_name, $path = "payslip");


                    // upload created payslip pdf file in google drive
                    $google_drive_file_url = self::saveFileToGoogleDrive($payslip_name, $userInfo_name, $userid);
                    $query = "UPDATE payslips SET payslip_url= '" . mysqli_real_escape_string($mysqli, $google_drive_file_url['url']) . "' , payslip_file_id = '" . $google_drive_file_url['file_id'] . "' WHERE id = $payslip_no";
                    self::DBrunQuery($query);
                    // if send mail option is true
                    if ($data['send_email'] == 1 || $data['send_email'] == '1') {
                        // send slack notification message 
                        self::sendPayslipMsgEmployee($payslip_no);
                    }

                    if ($data['send_slack_msg'] == 1 || $data['send_slack_msg'] == '1') {
                        // send slack notification message 
                        self::sendPayslipMsgEmployee($payslip_no, $data);
                    }

                    $r_error = 0;
                    $r_message = "Salary slip generated successfully";
                    $r_data['message'] = $r_message;
                }
            }
        }
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    public static function _getCurrentMonth($year, $month) {
        $currentMonthDate = date('Y-m-d', strtotime("$year-$month-01"));
        $currentMonth = array();
        $currentMonth['year'] = date('Y', strtotime($currentMonthDate));
        $currentMonth['month'] = date('m', strtotime($currentMonthDate));
        $currentMonth['monthName'] = date('F', strtotime($currentMonthDate));
        return $currentMonth;
    }

    public static function _getPreviousMonth($year, $month) {
        $previousMonthDate = date('Y-m-d', strtotime('-1 month', strtotime("$year-$month-01")));
        $previousMonth = array();
        $previousMonth['year'] = date('Y', strtotime($previousMonthDate));
        $previousMonth['month'] = date('m', strtotime($previousMonthDate));
        $previousMonth['monthName'] = date('F', strtotime($previousMonthDate));
        return $previousMonth;
    }

// get employee particular month and year  salary details 
    public function getUserManagePayslip($userid, $year, $month, $extra_arrear, $arrear_for_month) {
        
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $date = $year . "-" . $month . "-01";
        $month_name = date('F', strtotime($date));
        $user_salaryinfo = array();
        //get all salary list of employee
        $res1 = self::getSalaryInfo($userid, "first_to_last", $date);        
        //get employee profile detail.   
        $res0 = self::getUserprofileDetail($userid);
        // get latest salary id
        $latest_sal_id = sizeof($res1) - 1;
        $user_salaryinfo = $res1[$latest_sal_id];
        //get total working days of month
        $user_salaryinfo['total_working_days'] = self::getTotalWorkingDays($year, $month);
        //get employee month attendance
        $dayp = self::getUserMonthPunching($userid, $year, $month);

        $user_salaryinfo['days_present'] = sizeof($dayp);
        //get employee month salary details
        $actual_salary_detail = $salary_detail = self::getSalaryDetail($user_salaryinfo['id']);
        //get misc deduction of salary month form payslips table. 
        $misc_deduction = self::getUserMiscDeduction($userid, $year, $month);
        $salary_detail['misc_deduction2'] = $misc_deduction;
        //get bonus of salary month form payslips table.
        $bonus = self::getUserBonus($userid, $year, $month);
        $salary_detail['bonus'] = $bonus;
        //per day calculate salary
        $pday_basic = $salary_detail['Basic'] / $user_salaryinfo['total_working_days'];
        $pday_hra = $salary_detail['HRA'] / $user_salaryinfo['total_working_days'];
        $pday_conve = $salary_detail['Conveyance'] / $user_salaryinfo['total_working_days'];
        $pday_med = $salary_detail['Medical_Allowance'] / $user_salaryinfo['total_working_days'];
        $pday_spl = $salary_detail['Special_Allowance'] / $user_salaryinfo['total_working_days'];
        $pday_arrear = $salary_detail['Arrears'] / $user_salaryinfo['total_working_days'];
        //calculate end    
        $user_salaryinfo['year'] = $year;
        $user_salaryinfo['month'] = $month;
        $user_salaryinfo['month_name'] = $month_name;
        $user_salaryinfo['name'] = $res0['name'];
        $user_salaryinfo['dateofjoining'] = $res0['dateofjoining'];
        $user_salaryinfo['jobtitle'] = $res0['jobtitle'];
        // get employee actual salary total earning and total deduction.
        $actual_salary_detail['total_earning'] = $actual_salary_detail['Basic'] + $actual_salary_detail['HRA'] + $actual_salary_detail['Conveyance'] + $actual_salary_detail['Medical_Allowance'] + $actual_salary_detail['Special_Allowance'] + $actual_salary_detail['Arrears'];
        $actual_salary_detail['total_deduction'] = $salary_detail['EPF'] + $actual_salary_detail['Loan'] + $actual_salary_detail['Advance'] + $actual_salary_detail['Misc_Deductions'] + $actual_salary_detail['TDS'];
        //employee actual net salary
        $actual_salary_detail['net_salary'] = $actual_salary_detail['total_earning'] - $actual_salary_detail['total_deduction'];
        // get employee month payslip info   
        $res = self::getUserPayslipInfo($userid);
        // echo '<pre>';
        // print_r( $res );

        // changes done on 7ht june 2018 by arun
        // this was the calulcation to have the leave balance and every time it was returning the latest leave balance
        // even if we were viewing many months back payslip
        // to fix this we need to we need to process on the basis of month opted

        if (sizeof($res) > 0) {

            // 7 June 2018 : first check if we are calculating for previous month of current month
            // if above condition is true then old logic else new logic will be implemented to call the balance leave
            $currentYear = date('Y');
            $currentMonth = date('m');

            $current_month_previous_month_year = self::_getPreviousMonth( $currentYear, $currentMonth);
            $current_month_previous_year = $current_month_previous_month_year['year'];
            $current_month_previous_month = $current_month_previous_month_year['month'];


            if( $current_month_previous_year == $year && $current_month_previous_month == $month ){
                // start old logic
                if ($res[0]['month'] == $month) {
                    if ($res[1]['final_leave_balance'] == "") {
                        $balance_leave = 0;
                    } else {
                        $balance_leave = $res[1]['final_leave_balance'];
                    }
                } else {
                    if ($res[0]['final_leave_balance'] == "") {
                        $balance_leave = 0;
                    } else {
                        $balance_leave = $res[0]['final_leave_balance'];
                    }
                }
                // end old logic
            } else {
                // start new logic
                $previousMonthDetails = self::_getPreviousMonth( $year, $month);
                $balance_leave_check_of_month = $previousMonthDetails['month'];
                $balance_leave_check_of_year = $previousMonthDetails['year'];

                $balance_leave = 0;

                foreach( $res as $ps ){
                    if( $ps['year'] ==  $balance_leave_check_of_year && $ps['month'] == $balance_leave_check_of_month ){
                        if( $ps['final_leave_balance'] != '' ){
                            $balance_leave = $ps['final_leave_balance'];
                            break;
                        } 
                    }
                }// end new logic
            }
        }

        // echo "$balance_leave -- <br>";

        // if no data of employee in payslips table   
        if (sizeof($res) <= 0) {
            //get employee detail from payslip table of previous  hr system
            $prev = self::getUserBalanceLaveInfo($userid, $year, $month);
            $balance_leave = $prev['final_leave_balance'];
        }
        //get employee month leave info
        $c = self::getUserMonthLeaves($userid, $year, $month);


        $current_month_leave = 0;
        // get employee total no. of leave taken in month
        if (sizeof($c) > 0) {
            foreach ($c as $v) {
                if ($v['status'] == "Approved" || $v['status'] == "approved" || $v['status'] == "Pending" || $v['status'] == "pending") {
                    if ($v['no_of_days'] < 1) {
                        $current_month_leave = $current_month_leave + $v['no_of_days'];
                        $user_salaryinfo['days_present'] = $user_salaryinfo['days_present'] - $v['no_of_days'];
                    } else {
                        $current_month_leave = $current_month_leave + 1;
                    }
                }
            }
        }

        // get final leave balance of employee
        $leaves = $balance_leave - $current_month_leave + $user_salaryinfo['leaves_allocated'];
        if ($leaves >= 0) {
            $paid_leave = $current_month_leave;
            $unpaid_leave = 0;
        }
        if ($leaves < 0) {
            $paid_leave = $current_month_leave - abs($leaves);
            $unpaid_leave = abs($leaves);
        }
        //final salary calculate
        $final_basic = $salary_detail['Basic'] - ( $pday_basic * $unpaid_leave);
        $final_hra = $salary_detail['HRA'] - ( $pday_hra * $unpaid_leave);
        $final_conve = $salary_detail['Conveyance'] - ( $pday_conve * $unpaid_leave);
        $final_med = $salary_detail['Medical_Allowance'] - ( $pday_med * $unpaid_leave);
        $final_spl = $salary_detail['Special_Allowance'] - ( $pday_spl * $unpaid_leave);
        $final_arrear = $salary_detail['Arrears'] - ( $pday_arrear * $unpaid_leave);
        //end final salary calculation 
        // calculate arrear of previous month
        if (!empty($extra_arrear) && !empty($arrear_for_month)) {
            
           $user_salaryinfo['extra_arrear'] = $extra_arrear;
           $user_salaryinfo['arrear_for_month'] = $arrear_for_month;
           $final_arrear = self::checkArrearDetail($userid, $year, $month, $extra_arrear, $arrear_for_month);
        }
        

        //array formation with values to display on hr system.
        $salary_detail['Basic'] = round($final_basic, 2);
        $salary_detail['HRA'] = round($final_hra, 2);
        $salary_detail['Conveyance'] = round($final_conve, 2);
        $salary_detail['Medical_Allowance'] = round($final_med, 2);
        $salary_detail['Special_Allowance'] = round($final_spl, 2);
        $salary_detail['Arrears'] = round($final_arrear, 2);
        $user_salaryinfo['salary_detail'] = $salary_detail;
        $total_earning = $salary_detail['Basic'] + $salary_detail['HRA'] + $salary_detail['Conveyance'] + $salary_detail['Medical_Allowance'] + $salary_detail['Special_Allowance'] + $salary_detail['Arrears'] + $salary_detail['bonus'];
        $total_deduction = $salary_detail['EPF'] + $salary_detail['Loan'] + $salary_detail['Advance'] + $salary_detail['Misc_Deductions'] + $salary_detail['TDS'] + $salary_detail['misc_deduction2'];
        $net_salary = $total_earning - $total_deduction;
        $user_salaryinfo['total_earning'] = round($total_earning, 2);
        $user_salaryinfo['total_deduction'] = round($total_deduction, 2);
        $user_salaryinfo['net_salary'] = round($net_salary, 2);
        $user_salaryinfo['total_working_days'] = self::getTotalWorkingDays($year, $month);
        // $user_salaryinfo['days_present'] = $user_salaryinfo['total_working_days'] - $current_month_leave;
        $user_salaryinfo['paid_leaves'] = $paid_leave;
        $user_salaryinfo['unpaid_leaves'] = $unpaid_leave;
        $user_salaryinfo['total_leave_taken'] = $current_month_leave;
        $user_salaryinfo['leave_balance'] = $balance_leave;
        $final_leave_balance = $balance_leave + $res1[$latest_sal_id]['leaves_allocated'] - $current_month_leave;
        if ($final_leave_balance <= 0) {
            $user_salaryinfo['final_leave_balance'] = 0;
        }
        if ($final_leave_balance > 0) {
            $user_salaryinfo['final_leave_balance'] = $balance_leave + $res1[$latest_sal_id]['leaves_allocated'] - $current_month_leave;
        }
        $check_google_drive_connection = self::getrefreshToken();
        $r_error = 0;
        $r_data['user_data_for_payslip'] = $user_salaryinfo;
        $r_data['employee_pending_leave'] = self::getTotalWorkingDayslist($userid, $year, $month);
        $r_data['user_payslip_history'] = $res;
        $r_data['google_drive_emailid'] = "";
        $r_data['employee_actual_salary'] = $actual_salary_detail;
        if (is_array($check_google_drive_connection) && sizeof($check_google_drive_connection) > 0) {
            //$r_data['google_drive_emailid'] = $check_google_drive_connection['email_id'];
            $r_data['google_drive_emailid'] = "Yes email id exist";
        }
        //get employee all previous payslip.
        $r_data['all_users_latest_payslip'] = self::getAllUserPayslip($userid, $year, $month);

        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;

        return $return;
    }

    // get particular month and year total working days
    public static function getTotalWorkingDays($year, $month) {
        $list = array();
        for ($d = 1; $d <= 31; $d++) {
            $time = mktime(12, 0, 0, $month, $d, $year);
            if (date('m', $time) == $month)
                $list[] = date('m-d-Y', $time);
        }
        //Added by meraj       
        foreach ($list as $getd) {
            $de = 0;
            $de = self::checkDatePresent($getd);
            if ($de != 0) {
                $set[] = $getd;
            }
        }
//        
//        echo "<pre>";
//        print_r($set);
// end
//              
//        $no_of_holidays = self::getHolidaysOfMonth($year, $month);
//        $weekends_of_month = self::getWeekendsOfMonth($year, $month);
//        if (sizeof($weekends_of_month) > 0) {
//            $arru = $weekends_of_month;
//        }
//        if (sizeof($no_of_holidays) > 0) {
//            foreach ($no_of_holidays as $k => $p) {
//                if (!array_key_exists($k, $arru)) {
//                    $arru[$k] = $p;
//                }
//            }
//        }
//        
//        echo "<pre>";
//        print_r($list);
//        print_r($arru);
//        die;
        // $total_no_of_workdays = sizeof($list) - sizeof($arru);
        $total_no_of_workdays = sizeof($set);
        return $total_no_of_workdays;
    }

    // get holidays list of month and year
    public static function getHolidaysOfMonth($year, $month) {
        $q = "SELECT * FROM holidays";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        $list = array();
        foreach ($rows as $pp) {
            $h_date = $pp['date'];
            $h_month = date('m', strtotime($h_date));
            $h_year = date('Y', strtotime($h_date));
            if ($h_year == $year && $h_month == $month) {
                $h_full_date = date("Y-m-d", strtotime($h_date));
                $h_date = date("d", strtotime($h_date));
                $pp['date'] = $h_date;
                $pp['full_date'] = $h_full_date; // added on 27 for days between leaves
                $list[$h_date] = $pp;
            }
        }
        return $list;
    }

    // get weekends off list
    public static function getWeekendsOfMonth($year, $month) {
        // echo $year.'-----'.$month.'*******';
        $list = array();
        $monthDays = self::getDaysOfMonth($year, $month);

        $firstSatOff = false;

        if( ( $year == 2018 && $month >= 03) || $year > 2018 ){
            $firstSatOff = true;            
        }

        $alternateSaturdayCheck = false; // this is change from false to true to make 1st saturday off
        if($firstSatOff == true ){
            $alternateSaturdayCheck = true;
        }

        $saturdayCount = 0; // to make 5th saturday working

        foreach ($monthDays as $k => $v) {
            if ($v['day'] == 'Sunday') {
                $list[$k] = $v;
            }
            if ($v['day'] == 'Saturday') { 
                $saturdayCount++; // to make 5th saturday working
                if( $saturdayCount == 5 ){ // to make 5th saturday working
                    $alternateSaturdayCheck = false; // to make 5th saturday working
                } // to make 5th saturday working

                if ($alternateSaturdayCheck == true) {
                    $list[$k] = $v;
                    $alternateSaturdayCheck = false;
                } else {
                    $alternateSaturdayCheck = true;
                }
            }
        }

        //exclude working weekend from month weekends
        $list2 = self::getWorkingHoursOfMonth($year, $month);

        $pop = array();

        $pop = array_diff_key($list, $list2);

        return $pop;
    }

    // get month working hours times
    public static function getWorkingHoursOfMonth($year, $month) {
        $q = "SELECT * FROM working_hours";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        $list = array();
        foreach ($rows as $pp) {
            $h_date = $pp['date'];
            $h_month = date('m', strtotime($h_date));
            $h_year = date('Y', strtotime($h_date));
            if ($h_year == $year && $h_month == $month) {
                $h_full_date = date("Y-m-d", strtotime($h_date));
                $h_date = date("d", strtotime($h_date));
                $pp['date'] = $h_date;
                $list[$h_date] = $pp;
            }
        }
        return $list;
    }

    // get generic month days will have date, day, and full date
    public static function getDaysOfMonth($year, $month) {
        $list = array();
        for ($d = 1; $d <= 31; $d++) {
            $time = mktime(12, 0, 0, $month, $d, $year);
            if (date('m', $time) == $month) {
                $c_full_date = date('Y-m-d', $time);
                $c_date = date('d', $time);
                $c_day = date('l', $time);
                $row = array(
                    'full_date' => $c_full_date,
                    'date' => $c_date,
                    'day' => $c_day
                );
                $list[$c_date] = $row;
            }
        }
        return $list;
    }

//employee attendace--------------------------------------
    //get employee attendance in particular month and year
    public static function getUserMonthPunching($userid, $year, $month) {
        $list = array();
        // get all attendance detail of an employee.
        $q = "SELECT * FROM attendance Where user_id = $userid";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        $allMonthAttendance = array();
        foreach ($rows as $key => $d) {
            $d_timing = $d['timing'];
            $d_timing = str_replace("-", "/", $d_timing);
            $d_full_date = date("Y-m-d", strtotime($d_timing));
            $d_timestamp = strtotime($d_timing);
            $d_month = date("m", $d_timestamp);
            $d_year = date("Y", $d_timestamp);
            $d_date = date("d", $d_timestamp);
            //$d_date = (int)$d_date;
            //get the particular year and month attendance
            if ($d_year == $year && $d_month == $month) {
                $d['timestamp'] = $d_timestamp;
                $allMonthAttendance[$d_date][] = $d;
            }
        }
        foreach ($allMonthAttendance as $pp_key => $pp) {
            $daySummary = self::_beautyDaySummary($pp); // get summery of the date
            $list[$pp_key] = $daySummary;
        }

        return ($list);
    }

// get employee present date summery
    public static function _beautyDaySummary($dayRaw) {
        $TIMESTAMP = '';
        $numberOfPunch = sizeof($dayRaw);
        $timeStampWise = array();
        foreach ($dayRaw as $pp) {
            $TIMESTAMP = $pp['timestamp'];
            $timeStampWise[$pp['timestamp']] = $pp;
        }
        // sort on the basis of timestamp 
        ksort($timeStampWise);
        $inTimeKey = key($timeStampWise);
        end($timeStampWise);
        $outTimeKey = key($timeStampWise);
        // employee in time   
        $inTime = date('h:i A', $inTimeKey);
        // employee out time   
        $outTime = date('h:i A', $outTimeKey);
        $r_date = (int) date('d', $TIMESTAMP);
        $r_day = date('l', $TIMESTAMP);
        $r_total_time = $r_extra_time_status = $r_extra_time = '';
        // total no of hours present  
        $r_total_time = (int) $outTimeKey - (int) $inTimeKey;
        // extra time  
        $r_extra_time = (int) $r_total_time - (int) ( 9 * 60 * 60 );
        if ($r_extra_time < 0) { // not completed minimum hours
            $r_extra_time_status = "-";
            $r_extra_time = $r_extra_time * -1;
        } else if ($r_extra_time > 0) {
            $r_extra_time_status = "+";
        }
        $return = array();
        $return['in_time'] = $inTime;
        $return['out_time'] = $outTime;
        $return['total_time'] = $r_total_time;
        $return['extra_time_status'] = $r_extra_time_status;
        $return['extra_time'] = $r_extra_time;
        return $return;
    }

    //get employee leave info of particular month and year
    public static function getUserLeaveInfo($userid, $year, $month) {
        $list = array();
        $q = "SELECT * FROM leaves Where user_id = $userid AND from_date like '%" . $year . "-" . $month . "%'";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        $total_no_of_leaves = 0;
        if (sizeof($rows) > 0) {
            foreach ($rows as $val) {
                $total_no_of_leaves = $total_no_of_leaves + $val['no_of_days'];
            }
        }
        return $total_no_of_leaves;
    }

    //get all document detail of an employee.    
    public static function getUserDocumentDetail($userid) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $row = array();
        $q = "SELECT * FROM user_document_detail where user_Id = $userid ";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        $r_error = 0;
        $r_data['user_document_info'] = $row;
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    //update document read status   
    public static function UpdateDocumentDetail($userid, $doc_id) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();

        $q2 = "UPDATE user_document_detail SET read_status = '1' WHERE id = $doc_id ";
        self::DBrunQuery($q2);
        $message = "Document read status changed";
        $r_error = 0;
        $r_data['message'] = $message;
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

// Delete document of an employee.
    public static function deleteUserDocument($id) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $q = "SELECT link_1 FROM user_document_detail WHERE id = $id";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRow($runQuery);
        $a = str_replace("iframe", " ", $row['link_1']);
        $b = explode("/", $a);
        $file_id = $b[5];
        $r_token = self::getrefreshToken();
        $refresh_token = $r_token['value'];
        include "google-api/drive_file/upload.php";
        if ($file_id != false) {
            try {
                $service->files->delete($file_id);
            } catch (Exception $e) {
                $r_error = 1;
                $r_data['message'] = $e->getMessage();
            }
        }
        $q = "DELETE FROM user_document_detail WHERE id = $id";
        $runQuery = self::DBrunQuery($q);
        $r_error = 0;
        $r_message = "Document deleted successfully";
        $r_data['message'] = $r_message;
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    //Delete salary detail of an employee
    public static function deleteUserSalary($userid, $salaryid) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $q = "DELETE FROM salary WHERE id = $salaryid";
        $runQuery = self::DBrunQuery($q);
        $q2 = "DELETE FROM salary_details WHERE salary_id = $salaryid";
        $runQuery2 = self::DBrunQuery($q2);
        $r_error = 0;
        $r_message = "Salary slip deleted successfully";
        $r_data['message'] = $r_message;
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    //save payslip file to google drive 
    public static function saveFileToGoogleDrive($payslip_no, $userInfo_name, $userid, $file_id = false) {
        $filename = $payslip_no . ".pdf";
        //upload file in google drive;
        $parent_folder = "Employees Salary Payslips";
        $subfolder_empname = $userInfo_name;
        $subfolder_year = date("Y") . "-" . $userid;
        $r_token = self::getrefreshToken();
        $refresh_token = $r_token['value'];
        //include url variables file.
        include "config.php";
        //include google drive api file to upload file in google drive 
        include "google-api/drive_file/upload.php";



        if ($file_id != false) {
            try {
                $service->files->delete($file_id);
            } catch (Exception $e) {
                print "An error occurred: " . $e->getMessage();
            }
        }
        $testfile = 'payslip/' . $filename;
        if (!file_exists($testfile)) {
            $fh = fopen($testfile, 'w');
            fseek($fh, 1024 * 1024);
            fwrite($fh, "!", 1);
            fclose($fh);
        }
        if (array_key_exists($parent_folder, $arr)) {
            $pfolder = $arr[$parent_folder];
        }
        if (array_key_exists($subfolder_empname, $arr)) {
            $sfolder = $arr[$subfolder_empname];
        }
        if (array_key_exists($subfolder_year, $arr)) {
            $syearfolder = $arr[$subfolder_year];
        }
        if (!array_key_exists($parent_folder, $arr)) {
            // create a parent folder in  google drive 
            $fileMetadata = new Google_Service_Drive_DriveFile(array(
                'name' => $parent_folder,
                'mimeType' => 'application/vnd.google-apps.folder'));
            $filez = $service->files->create($fileMetadata, array(
                'fields' => 'id'));
            $pfolder = $filez->id;
        }
        if (!array_key_exists($subfolder_empname, $arr)) {
            // create a sub folder inside parent folder in google drive.
            $fileMetadata = new Google_Service_Drive_DriveFile(array(
                'name' => $subfolder_empname,
                'parents' => array($pfolder),
                'mimeType' => 'application/vnd.google-apps.folder'));
            $filez = $service->files->create($fileMetadata, array(
                'fields' => 'id'));
            $sfolder = $filez->id;
        }
        if (!array_key_exists($subfolder_year, $arr)) {
            // create a sub sub folder inside  sub folder in google drive.
            $fileMetadata = new Google_Service_Drive_DriveFile(array(
                'name' => $subfolder_year,
                'parents' => array($sfolder),
                'mimeType' => 'application/vnd.google-apps.folder'));
            $filez = $service->files->create($fileMetadata, array(
                'fields' => 'id'));
            //printf("Folder ID: %s\n", $filez->title);
            $syearfolder = $filez->id;
        }
        // upload file in google drive   
        $file = new Google_Service_Drive_DriveFile(
                array(
            'name' => $filename,
            'parents' => array($syearfolder)
        ));
        $result2 = $service->files->create(
                $file, array(
            'data' => file_get_contents($testfile),
            'mimeType' => 'application/octet-stream',
            'uploadType' => 'multipart'
                )
        );
        $url['url'] = $google_drive_url . $result2->id . "/preview";
        $url['file_id'] = $result2->id;


// change uploaded file permission in google drive
        $permission = new Google_Service_Drive_Permission();
        $permission->setRole('writer');
        $permission->setType('anyone');
        try {
            $service->permissions->create($result2->id, $permission);
        } catch (Exception $e) {
            print "An error occurred: " . $e->getMessage();
        }
        // delete file from folder
        unlink($testfile);
        return $url;
    }

// send payslip slack notification message to employee 
    public static function sendPayslipMsgEmployee($payslip_id, $arr = false) {

        $db = self::getInstance();
        $mysqli = $db->getConnection();
        $email_data = array();
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $q = "SELECT * FROM payslips where id =" . $payslip_id;
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRow($runQuery);
        if (mysqli_num_rows($runQuery) > 0) {
            $google_drive_file_url = $row['payslip_url'];
            $date = $row['year'] . "-" . $row['month'] . "-01";
            $month_name = date('F', strtotime($date));
            $userid = $row['user_Id'];
            $userInfo = self::getUserInfo($userid);
            $userInfo_name = $userInfo['name'];
            $user_slack_id = $userInfo['slack_profile']['id'];
            $channel_id = self::getSlackChannelIds();
            $slack_userChannelid = "";
            foreach ($channel_id as $v) {
                if ($v['user'] == "$user_slack_id") {
                    $slack_userChannelid = $v['id'];
                }
            }
            
            $email_data['email_id'] = $userInfo['work_email'];
                $email_data['name'] = $userInfo_name;
                $email_data['subject'] = "Payslip detail for month $month_name";
                
            
            if ($arr != 0) {
                $message1 = "Hi <b>" . $userInfo_name . "</b>.<br>Your salary slip is created for month of $month_name. Details: <br>";
                $message1.= "Total Working Days = " . $arr['total_working_days'] . "<br>";
                $message1.= "Days Present = " . $arr['days_present'] . "<br>";
                $message1.= "Paid Leave Taken = " . $arr['paid_leaves'] . "<br>";
                $message1.= "Leave Without Pay = " . $arr['unpaid_leaves'] . "<br>";
                $message1.= "Total leave taken = " . $arr['total_leave_taken'] . "<br>";
                $message1.= "Allocated Leave = " . $arr['allocated_leaves'] . "<br>";
                $message1.= "Previous month leave  balance = " . $arr['leave_balance'] . "<br>";
                $message1.= "Final leave balance = " . $arr['final_leave_balance'] . "<br>";
                $message1.= "Arrears = " . $arr['arrear'] . "<br>";
                $message1.= "Misc Deduction = " . $arr['misc_deduction'] . "<br>";
                $message1.= "Bonus = " . $arr['bonus'] . "<br>";
                $message1.= "Total earning = " . $arr['total_earning'] . "<br>";
                $message1.= "Total deduction = " . $arr['total_deduction'] . "<br>";
                $message1.= "Net Salary = " . $arr['net_salary'] . "<br>";
                
                $email_data['body'] = $message1;
                $email_dta['email'][] = $email_data;
                self::sendEmail($email_dta);
                
                $message = "Hi " . $userInfo_name . ". \nPlease check you office email for salary details";
                $slackMessageStatus = self::sendSlackMessageToUser($slack_userChannelid, $message); // send slack message notification to employee
            }
            $query = "UPDATE payslips SET status= 0 WHERE id = " . $row['id'];
            if ($arr == 0) {
                $message = "Hi <b>" . $userInfo_name . "</b>. <br>Your salary slip is created for month of $month_name. Please visit below link <br> $google_drive_file_url";
               
                $email_data['body'] = $message;
                $email_dta['email'][] = $email_data;
                self::sendEmail($email_dta);
                
                $query = "UPDATE payslips SET status= 1 WHERE id = " . $row['id'];
                
                
            }
            self::DBrunQuery($query);
            //Please visit below link \n $google_drive_file_url



            

            $r_error = 0;
            $r_message = "Slack Message send to employee";
            $r_data['message'] = $r_message;
        } else {
            $r_error = 1;
            $r_message = "Pdf file link not found of particular payslip_id";
            $r_data['message'] = $r_message;
        }
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;

        return $return;
    }

    // get employee particular month and year leaves 
    public static function getUserMonthLeaves($userid, $year, $month) {
        $list = array();
        $q = "SELECT * FROM leaves Where user_Id = $userid  AND (status = 'Approved' || status = 'Pending')";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        foreach ($rows as $pp) {
            $pp_start = $pp['from_date'];
            $pp_end = $pp['to_date'];
            $datesBetween = self::_getDatesBetweenTwoDates($pp_start, $pp_end);
            foreach ($datesBetween as $d) {
                $h_month = date('m', strtotime($d));
                $h_year = date('Y', strtotime($d));
                if ($h_year == $year && $h_month == $month) {
                    $h_full_date = date("Y-m-d", strtotime($d));
                    $h_date = date("d", strtotime($d));
                    $list[$h_date] = $pp;
                }
            }
        }
        ksort($list);
        ///// remove non working days from leaves
        $monthHolidays = self::getHolidaysOfMonth($year, $month);
        if (sizeof($monthHolidays) > 0) {
            foreach ($monthHolidays as $d => $v) {
                if (array_key_exists($d, $list)) {
                    unset($list[$d]);
                }
            }
        }
        /// remove weekends from leaves
        $weekendHolidays = self::getWeekendsOfMonth($year, $month);
        if (sizeof($weekendHolidays) > 0) {
            foreach ($weekendHolidays as $d => $v) {
                if (array_key_exists($d, $list)) {
                    unset($list[$d]);
                }
            }
        }
        return $list;
    }

    public static function _getDatesBetweenTwoDates($startDate, $endDate) {
        $return = array($startDate);
        $start = $startDate;
        $i = 1;
        if (strtotime($startDate) < strtotime($endDate)) {
            while (strtotime($start) < strtotime($endDate)) {
                $start = date('Y-m-d', strtotime($startDate . '+' . $i . ' days'));
                $return[] = $start;
                $i++;
            }
        }
        return $return;
    }

    // function to save employee documents  to google drive 
    public static function saveDocumentToGoogleDrive($document_type, $userInfo_name, $userid, $filename, $file_id = false) {
        $parent_folder = "Employees Documents";
        $subfolder_empname = $userInfo_name . " doc -" . $userid;
        $rest = array();
        $r_token = self::getrefreshToken();
        if (sizeof($r_token) > 0) {
            $refresh_token = $r_token['value'];
            //include google drive api file to upload document in google drive.
            include "google-api/drive_file/upload.php";
            //include url variables file.
            include "config.php";
//'demo' folder from where file to be fetched.
            $testfile = 'payslip/' . $filename;
            if (!file_exists($testfile)) {
                $fh = fopen($testfile, 'w');
                fseek($fh, 1024 * 1024);
                fwrite($fh, "!", 1);
                fclose($fh);
            }
            if (array_key_exists($parent_folder, $arr)) {
                $pfolder = $arr[$parent_folder];
            }
            if (array_key_exists($subfolder_empname, $arr)) {
                $sfolder = $arr[$subfolder_empname];
            }
            if (!array_key_exists($parent_folder, $arr)) {
                // create parent folder in google drive
                $fileMetadata = new Google_Service_Drive_DriveFile(array(
                    'name' => $parent_folder,
                    'mimeType' => 'application/vnd.google-apps.folder'));
                $filez = $service->files->create($fileMetadata, array(
                    'fields' => 'id'));
                $pfolder = $filez->id;
            }
            if (!array_key_exists($subfolder_empname, $arr)) {
                // create sub folder inside parent folder in google drive
                $fileMetadata = new Google_Service_Drive_DriveFile(array(
                    'name' => $subfolder_empname,
                    'parents' => array($pfolder),
                    'mimeType' => 'application/vnd.google-apps.folder'));
                $filez = $service->files->create($fileMetadata, array(
                    'fields' => 'id'));
                $sfolder = $filez->id;
            }
            $file = new Google_Service_Drive_DriveFile(
                    array(
                'name' => $filename,
                'parents' => array($sfolder)
            ));
            $result2 = $service->files->create(
                    $file, array(
                'data' => file_get_contents($testfile),
                'mimeType' => 'application/octet-stream',
                'uploadType' => 'multipart'
                    )
            );
            $url['url'] = $google_drive_url . $result2->id . "/preview";
            $url['file_id'] = $result2->id;
// change file permisison in google drive
            $permission = new Google_Service_Drive_Permission();
            $permission->setRole('writer');
            $permission->setType('anyone');
            try {
                $service->permissions->create($result2->id, $permission);
            } catch (Exception $e) {
                print "An error occurred: " . $e->getMessage();
            }
            $rest = $url;
            // delete file from folder   
            unlink($testfile);
        }
        return $rest;
    }

    // get employee latest salary info 
    public static function getUserlatestSalary($userid) {
        $q = "select * from salary where user_Id = $userid ORDER BY id DESC LIMIT 2";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        return $row;
    }

    // get all employee info with salary detail and holding details
    public static function getAllUserInfo($data = false) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        if ($data == "") {
            $a = self::getAllUserDetail();
        }
        if ($data != "") {
            $a = self::getAllUserDetail($data);
        }
        $row2 = array();
        $allSlackUsers = self::getSlackUsersList();
        foreach ($a as $val) {
            $userid = $val['user_Id'];
            $sal = self::getUserlatestSalary($userid);
            $salary_detail = "";
            $previous_increment = "";
            $next_increment_date = "";
            $slack_image = "";
            $holding = "";

            $latest_sal_id = $sal[0]['id'];
            $q = "SELECT * FROM salary_details WHERE `salary_id`= $latest_sal_id AND `key` = 'Misc_Deductions'";
            $runQuery = self::DBrunQuery($q);
            $row = self::DBfetchRow($runQuery);
            $row['value'];
            $emailid = $val['work_email'];
            if (sizeof($sal) >= 2) {
                $previous_increment = abs($sal[0]['total_salary'] - $sal[1]['total_salary']);
                $salary_detail = $sal[0]['total_salary'] + $row['value'];
                $next_increment_date = $sal[0]['applicable_till'];
                $start_increment_date = $sal[0]['applicable_from'];
            }
            if (sizeof($sal) >= 1 && sizeof($sal) < 2) {
                $salary_detail = $sal[0]['total_salary'] + $row['value'];
                $next_increment_date = $sal[0]['applicable_till'];
                $start_increment_date = $sal[0]['applicable_from'];
            }
            $now = date("Y-m-d"); // or your date as well
            $your_date = $val['dateofjoining'];
            $date1 = new DateTime($your_date);
            $date2 = new DateTime($now);
            $interval = $date1->diff($date2);

            foreach ($allSlackUsers as $s) {
                if ($s['profile']['email'] == $emailid) {
                    $sl = $s;
                    break;
                }
            }

            // $sl = self::getSlackUserInfo($emailid);
            if (sizeof($sl) > 0) {
                $slack_image = $sl['profile']['image_72'];
                $slack_id = $sl['id'];
            }
            $h = self::getHoldingDetail($userid);
            if (sizeof($h) > 0) {
                $holding = end($h);
            }
            $val['slack_image'] = $slack_image;
            $val['user_slack_id'] = $slack_id;
            $val['salary_detail'] = $salary_detail;
            $val['previous_increment'] = $previous_increment;
            $val['next_increment_date'] = $next_increment_date;
            $val['start_increment_date'] = $start_increment_date;
            $val['no_of_days_join'] = $interval->y . " years, " . $interval->m . " months, " . $interval->d . " days ";
            $val['holdin_amt_detail'] = $holding;
            $row2[] = $val;
//            $q = "SELECT * FROM user_profile where user_Id = $userid ";
//
//            $runQuery = self::DBrunQuery($q);
//            $row = self::DBfetchRow($runQuery);
//            $no_of_rows = self::DBnumRows($runQuery);
//
//            if ($no_of_rows > 0) {
//                if ($row['slack_id'] == "") {
//                    $q2 = "UPDATE user_profile SET slack_id = '$slack_id' WHERE user_Id = $userid ";
//                    $runQuery2 = self::DBrunQuery($q2);
//                }
//            }
        }
        $return = array();
        $r_error = 0;
        $return['error'] = $r_error;
        $return['data'] = $row2;
        return $return;
    }

    // get all email templates variables
    public function getAllTemplateVariable() {
        $q = "SELECT * FROM template_variables";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        return $row;
    }

// create an email template variables
    public function createTemplateVariable($data) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $ins = array(
            'name' => $data['name'],
            'value' => $data['value'],
            'variable_type' => $data['variable_type']
        );
        $q1 = "select * from template_variables where name ='" . $data['name'] . "'";
        $runQuery1 = self::DBrunQuery($q1);
        $row1 = self::DBfetchRow($runQuery1);
        $no_of_rows = self::DBnumRows($runQuery1);
        if ($no_of_rows == 0) {
            $res = self::DBinsertQuery('template_variables', $ins);
            $r_error = 0;
            $r_message = "Variable Successfully Inserted";
            $r_data['message'] = $r_message;
        } else {
            $r_error = 1;
            $r_message = "Variable name already exist";
            $r_data['message'] = $r_message;
        }
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    // update email template variables 
    public function updateTemplateVariable($data) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $ins = array(
            'name' => $data['name'],
            'value' => $data['value'],
            'variable_type' => $data['variable_type']
        );
        $id = $data['id'];
        $q = "select * from template_variables where id = $id ";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRow($runQuery);
        $whereFieldVal = $id;
        $whereField = 'id';
        foreach ($row as $key => $val) {
            if (array_key_exists($key, $data)) {
                if ($data[$key] != $row[$key]) {
                    $arr = array();
                    $arr[$key] = $data[$key];
                    $res = self::DBupdateBySingleWhere('template_variables', $whereField, $whereFieldVal, $arr);
                }
            }
        }
        $r_error = 0;
        $r_message = "Variable Successfully Updated";
        $r_data['message'] = $r_message;
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

// delete an email template variable
    public function deleteTemplateVariable($data) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $id = $data['id'];
        $q = "DELETE from template_variables where id = $id ";
        $runQuery = self::DBrunQuery($q);
        $r_error = 0;
        $r_message = "Variable Successfully deleted";
        $r_data['message'] = $r_message;
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

// get all email templates
    public function getAllEmailTemplate() {
        $q = "SELECT * FROM email_templates";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        // start added to fix issue on json_encode by arun on 10th nov 2017
        $encodedRows = [];
        if( sizeof($row) > 0 ){
            foreach( $row as $p ){
                $encodedRows[] = array_map('utf8_encode', $p);
            }
        }
        return $encodedRows;
    }

// create an email template
    public function createEmailTemplate($data) {

        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $ins = array(
            'name' => $data['name'],
            'subject' => $data['subject'],
            'body' => $data['body'],
        );
        $q1 = "select * from email_templates where name ='" . $data['name'] . "'";
        $runQuery1 = self::DBrunQuery($q1);
        $row1 = self::DBfetchRow($runQuery1);
        $no_of_rows = self::DBnumRows($runQuery1);
        if ($no_of_rows == 0) {
            $res = self::DBinsertQuery('email_templates', $ins);
            $r_error = 0;
            $r_message = "Template Successfully Inserted";
            $r_data['message'] = $r_message;
        } else {
            $r_error = 1;
            $r_message = "Template name already exist";
            $r_data['message'] = $r_message;
        }
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    //update an email template
    public function updateEmailTemplate($data) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $ins = array(
            'name' => $data['name'],
            'subject' => $data['subject'],
            'body' => $data['body'],
        );
        $id = $data['id'];
        $q = "select * from email_templates where id = $id ";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRow($runQuery);
        $whereFieldVal = $id;
        $whereField = 'id';
        foreach ($row as $key => $val) {
            if (array_key_exists($key, $data)) {
                if ($data[$key] != $row[$key]) {
                    $arr = array();
                    $arr[$key] = $data[$key];
                    $res = self::DBupdateBySingleWhere('email_templates', $whereField, $whereFieldVal, $arr);
                }
            }
        }
        $r_error = 0;
        $r_message = "Template Successfully Updated";
        $r_data['message'] = $r_message;
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    //delete an email template
    public function deleteEmailTemplate($data) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $id = $data['id'];
        $q = "DELETE from email_templates where id = $id ";
        $runQuery = self::DBrunQuery($q);
        $r_error = 0;
        $r_message = "Template Successfully deleted";
        $r_data['message'] = $r_message;
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    // fetch a particular email template
    public function getEmailTemplateById($data) {
        $q = "SELECT * FROM email_templates where id=" . $data['id'];
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        return $row;
    }

// send email to employee
    public static function sendEmployeeEmail($data) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        if (!empty($data['email'])) {

            foreach ($data['email'] as $var) {

                $row3 = self::sendEmail($var);
                $r_error = 0;
                $r_message = $row3;
            }
        }
        if ($row3 != "Message sent") {
            $r_error = 1;
            $r_message = $row3;
            $r_data['message'] = $r_message;
        }
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    // function to send email
    public static function sendEmail($data) {

        $r_error = 1;
        $r_message = "";
        $r_data = array();

        $q = "select * from config where type='email_detail'";
        $r = self::DBrunQuery($q);
        $row = self::DBfetchRow($r);

        $detail = json_decode($row['value'], true);

        include "phpmailer/PHPMailerAutoload.php";


        if (!empty($data['email'])) {

            
            foreach ($data['email'] as $var) {

                $work_email = $var['email_id'];

                $name = $var['name'];
                $subject = $var['subject'];
                $body = $var['body'];

                $cc = $var['cc_detail'];
                $bcc = $var['bcc_detail'];
                $file_upload = $var['upload_file'];
                
                $mail = new PHPMailer;
                $mail->isSMTP();
                $mail->SMTPDebug = 0;
                $mail->Debugoutput = 'html';
                $mail->Host = $detail['host'];
                $mail->Port = $detail['post'];
                $mail->SMTPSecure = 'tls';
                $mail->SMTPAuth = true;
                $mail->Username = $detail['username']; //sender email address 
                $mail->Password = $detail['password']; // sender email password
                $mail->setFrom('hr@excellencetechnologies.in', 'Excellence Technologies'); // name and email address from which email is send
                $mail->addReplyTo('hr@excellencetechnologies.in', 'Excellence Technologies'); // reply email address with name 
                $mail->addAddress($work_email, $name); // name and address to whome mail is to send
                if (sizeof($cc) > 0) {
                    foreach ($cc as $d) {
                        $mail->addCC($d[0], $d[1]);
                    }
                }
                if (sizeof($bcc) > 0) {
                    foreach ($bcc as $d2) {
                        $mail->addBCC($d2[0], $d2[1]);
                    }
                }
                $mail->Subject = $subject; // subject of email message 
                $mail->msgHTML($body); // main message 
                // $mail->AltBody = 'This is a plain-text message body';
                //Attach an image file
                if (sizeof($file_upload) > 0) {
                    foreach ($file_upload as $d3) {
                        $mail->addAttachment($d3);
                    }
                }
//send the message, check for errors
                if (!$mail->send()) {
                    $row3 = $mail->ErrorInfo;
                } else {
                    $row3 = "Message sent";
                }
            }
        }

        
        if ($row3 != "Message sent") {

            $r_error = 1;
            $r_message = $row3;
            $r_data['message'] = $r_message;
        } else {
            $r_error = 0;
            $r_message = "Message Sent";
            $r_data['message'] = $r_message;
        }
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    // get employee misc deduction amount of particular month and year 
    public static function getUserMiscDeduction($userid, $year, $month) {
        $result = 0;
        $q = "SELECT * FROM payslips WHERE user_Id= $userid  AND month = '$month' AND year= '$year' ";
        $runQuery = self::DBrunQuery($q);
        $row2 = self::DBfetchRow($runQuery);
        $no_of_rows = self::DBnumRows($runQuery);
        if ($no_of_rows > 0) {
            $result = $row2['misc_deduction_2'];
        }
        return $result;
    }

    // get employee bonus amount of particular month and year 
    public static function getUserBonus($userid, $year, $month) {
        $result = 0;
        $q = "SELECT * FROM payslips WHERE user_Id= $userid  AND month = '$month' AND year= '$year' ";
        $runQuery = self::DBrunQuery($q);
        $row2 = self::DBfetchRow($runQuery);
        $no_of_rows = self::DBnumRows($runQuery);
        if ($no_of_rows > 0) {
            $result = $row2['bonus'];
        }
        return $result;
    }

    // cancel applied leave 
    public static function cancelAppliedLeave($data) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $userid = $data['user_id'];
        $leave_start_date = date('Y-m-d', strtotime($data['date']));
        $current_date = date("Y-m-d");
        if (strtotime($current_date) < strtotime($leave_start_date)) {
            $q = "SELECT * FROM leaves WHERE user_Id= $userid  AND from_date= '$leave_start_date' AND (status = 'Approved' OR status = 'Pending')";

            $runQuery = self::DBrunQuery($q);
            $row2 = self::DBfetchRow($runQuery);
            $no_of_rows = self::DBnumRows($runQuery);
            if ($no_of_rows > 0) {
                $q2 = "UPDATE leaves SET status = 'Cancelled Request' WHERE id=" . $row2['id'];
                $runQuery2 = self::DBrunQuery($q2);
                $r_error = 0;
                $r_message = "Your applied leave for " . $data['date'] . " has been cancelled";
                $r_data['message'] = $r_message;
            } else {
                $r_error = 1;
                $r_message = "No Leave applied on " . $data['date'] . " or it has been cancelled already";
                $r_data['message'] = $r_message;
            }
        } else {
            $r_error = 1;
            $r_message = "You cannot cancel leave of " . $data['date'] . " . Contact HR for cancellation";
            $r_data['message'] = $r_message;
        }
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    public static function savePolicyDocument($data) {

        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $ins = array(
            'type' => $data['type'],
            'value' => $data['value']
        );
        $q1 = "select * from config where type ='" . $data['type'] . "'";
        $runQuery1 = self::DBrunQuery($q1);
        $row1 = self::DBfetchRow($runQuery1);
        $no_of_rows = self::DBnumRows($runQuery1);
        if ($no_of_rows == 0) {
            $res = self::DBinsertQuery('config', $ins);
            $r_error = 0;
            $r_message = "Variable Successfully Inserted";
            $r_data['message'] = $r_message;
        } if ($no_of_rows != 0) {
            $value = $data['value'];
            $q = "UPDATE config set value='$value' WHERE type ='" . $data['type'] . "'";
            self::DBrunQuery($q);

            $r_error = 0;
            $r_message = "Variable updated successfully";
            $r_data['message'] = $r_message;
        }

        $q2 = "select * from config where type ='policy_document_update'";

        $ins2 = array(
            'type' => "policy_document_update",
            'value' => date("Y-m-d")
        );
        $runQuery2 = self::DBrunQuery($q2);
        $row2 = self::DBfetchRow($runQuery2);
        $no_of_row = self::DBnumRows($runQuery2);
        if ($no_of_row == 0) {
            $res = self::DBinsertQuery('config', $ins2);
        } if ($no_of_row != 0) {
            $value = date("Y-m-d");
            $q = "UPDATE config set value='$value' WHERE type ='policy_document_update'";
            self::DBrunQuery($q);
        }


        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    public static function getPolicyDocument($data) {

        $r_error = 1;
        $r_message = "";
        $r_data = array();

        $q1 = "select * from config where type ='policy_document'";
        $runQuery1 = self::DBrunQuery($q1);
        $row1 = self::DBfetchRow($runQuery1);
        $no_of_rows = self::DBnumRows($runQuery1);
        if ($no_of_rows != 0) {
            $r_data = json_decode($row1['value'], true);
            $r_error = 0;
        } else {
            $r_error = 1;
            $r_message = "Variable not found";
            $r_data['message'] = $r_message;
        }
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    public static function getUserPolicyDocument($userid) {

        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $q1 = "SELECT * FROM user_profile where user_Id = $userid";
        $runQuery1 = self::DBrunQuery($q1);
        $row1 = self::DBfetchRow($runQuery1);

        $ar0 = json_decode($row1['policy_document'], true);

        $q2 = "SELECT * FROM config where type ='policy_document'";
        $runQuery2 = self::DBrunQuery($q2);
        $row2 = self::DBfetchRow($runQuery2);

        $ar1 = json_decode($row2['value'], true);
        $arr = array();
        if (empty($ar0)) {
            foreach ($ar1 as $v2) {
                $v2['read'] = 0;
                $arr[] = $v2;
            }
        }
        if (!empty($ar0)) {
            foreach ($ar1 as $v3) {
                if (in_array($v3['name'], $ar0)) {
                    $v3['read'] = 1;
                    $arr[] = $v3;
                } else {
                    $v3['read'] = 0;
                    $arr[] = $v3;
                }
            }
        }

        $r_error = 0;

        $r_data = $arr;

        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    public static function updateUserPolicyDocument($data) {

        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $q1 = "UPDATE user_profile SET policy_document = '" . $data['policy_document'] . "' where user_Id =" . $data['user_id'];

        self::DBrunQuery($q1);

        $r_error = 0;
        $r_message = "Profile successfully updated";
        $r_data['message'] = $r_message;

        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    public static function saveTeamList($data) {


        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $ins = array(
            'type' => $data['type'],
            'value' => $data['value']
        );
        $q1 = "select * from config where type ='" . $data['type'] . "'";
        $runQuery1 = self::DBrunQuery($q1);
        $row1 = self::DBfetchRow($runQuery1);
        $no_of_rows = self::DBnumRows($runQuery1);
        if ($no_of_rows == 0) {
            $res = self::DBinsertQuery('config', $ins);
            $r_error = 0;
            $r_message = "Variable Successfully Inserted";
            $r_data['message'] = $r_message;
        } else {
            $value = $data['value'];
            $q = "UPDATE config set value='$value' WHERE type ='" . $data['type'] . "'";
            self::DBrunQuery($q);

            $r_error = 0;
            $r_message = "Variable updated successfully";
            $r_data['message'] = $r_message;
        }
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    public static function getTeamList() {


        $r_error = 1;
        $r_message = "";
        $r_data = array();

        $q1 = "select * from config where type ='team_list'";
        $runQuery1 = self::DBrunQuery($q1);
        $row1 = self::DBfetchRow($runQuery1);
        $no_of_rows = self::DBnumRows($runQuery1);
        if ($no_of_rows == 0) {
            $r_error = 1;
            $r_message = "Team list not found";
            $r_data['message'] = $r_message;
        } else {

            $r_error = 0;

            $r_data = json_decode($row1['value'], true);
        }
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    public static function getTotalWorkingDayslist($userid, $year, $month) {

        $list = array();
        for ($d = 1; $d <= 31; $d++) {
            $time = mktime(12, 0, 0, $month, $d, $year);
            if (date('m', $time) == $month)
                $list[] = date('m-d-Y', $time);
        }
        foreach ($list as $getd) {
            $de = 0;
            $de = self::checkDatePresent($getd);
            if ($de != 0) {
                $set[] = $getd;
            }
        }
        $po = self::getUserMonthPunching($userid, $year, $month);

        $c = self::getUserMonthLeaves($userid, $year, $month);
        $arr = array();
        foreach ($set as $v) {
            $op = explode("-", $v);
            if (!array_key_exists($op[1], $po)) {
                $arr[] = $v;
            }
        }
        if (empty($c)) {
            $return = $arr;
        }
        if (!empty($c)) {
            $arr2 = array();
            foreach ($arr as $v3) {
                $p = explode("-", $v3);
                if (!array_key_exists($p[1], $c)) {
                    $arr2[] = $v3;
                }
            }
            $return = $arr2;
        }
        return $return;
    }

    public static function checkDatePresent($data) {
        $db = self::getInstance();
        $mysqli = $db->getConnection();
        $result = 0;
        $q1 = "select * from attendance where timing like '%$data%'";
        $runQuery1 = self::DBrunQuery($q1);
        $row1 = self::DBfetchRows($runQuery1);

        if (mysqli_num_rows($runQuery1) >= 1) {

            // start added by arun on 6th october to check date is off day
            $is_off_day = false;
            $dateBreak = explode("-", $data);
            $req_month = $dateBreak[0];
            $req_date = $dateBreak[1];
            $req_year = $dateBreak[2];
            $weekendsOfMonth = self::getWeekendsOfMonth( $req_year, $req_month );
            if( sizeof($weekendsOfMonth) > 0 ){
                foreach( $weekendsOfMonth as $wom ){
                    if( $wom['date'] == $req_date ){
                       $is_off_day = true; 
                    }
                }
            }
            $holidaysOfMonth = self::getHolidaysOfMonth($req_year, $req_month);
            if( sizeof($holidaysOfMonth) > 0 ){
                foreach( $holidaysOfMonth as $wom ){
                    if( $wom['date'] == $req_date ){
                       $is_off_day = true; 
                    }
                }
            }
            // end added by arun on 6th october to check date is off day
            if( $is_off_day == false ){
                $result = 1;    
            }
            return $result;
        } else {
            return $result;
        }
    }
    
    public static function checkArrearDetail($userid, $year, $month, $extra_arrear, $arrear_for_month) {
            $prev_month = $month;
            $prev_year = $year;

            $prev_arr = array();
            
           

            for ($i = 1; $i <= $arrear_for_month; $i++) {
                $arr = array();
                $prev_month = $prev_month - 1;
                if ($prev_month <= 0) {
                    $prev_year = date('Y', strtotime($year . ' -1 year'));
                    $prev_month = 12;
                }


                $mon = self::getTotalWorkingDays($prev_year, $prev_month);
                $c1 = self::getUserMonthLeaves($userid, $prev_year, $prev_month);


                $c1_month_leave = 0;
                // get employee total no. of leave taken in month
                if (sizeof($c1) > 0) {
                    foreach ($c1 as $v) {
                        if ($v['status'] == "Approved" || $v['status'] == "approved" || $v['status'] == "Pending" || $v['status'] == "pending") {
                            if ($v['no_of_days'] < 1) {
                                $c1_month_leave = $c1_month_leave + $v['no_of_days'];
                            } else {
                                $c1_month_leave = $c1_month_leave + 1;
                            }
                        }
                    }
                }

                $paySlipInfo = self::getUserPayslipInfo($userid);
                if( sizeof($paySlipInfo) > 0 ){
                    foreach( $paySlipInfo as $ps ){
                        if( $ps['year'] ==  $prev_year && $ps['month'] == $prev_month ){
                            if( $ps['final_leave_balance'] != '' ){
                                $balance_leave = $ps['final_leave_balance'];
                                break;
                            } 
                        }
                    }
                }
                
                if( sizeof($paySlipInfo) <= 0 ){
                    //get employee detail from payslip table of previous  hr system
                    $prev = self::getUserBalanceLaveInfo($userid, $prev_year, $prev_month);                    
                    $balance_leave = $prev['final_leave_balance'];
                }

                $date = $prev_year . "-" . $prev_month . "-01";
                $salInfo = self::getSalaryInfo($userid, "first_to_last", $date);                
                // get latest salary id
                $latest_sal_id = sizeof($salInfo) - 1;
                $user_salaryinfo = $salInfo[$latest_sal_id];
                
                // get final leave balance of employee
                $leaves = $balance_leave - $c1_month_leave + $user_salaryinfo['leaves_allocated'];
                if ($leaves >= 0) {
                    $unpaid_leave = 0;
                }
                if ($leaves < 0) {
                    $unpaid_leave = abs($leaves);
                }
               $arr['year'] =  $prev_year;
               $arr['month'] =  $prev_month;
               $arr['total_working_days'] =  $mon;
               $arr['leave'] = $c1_month_leave;
               $arr['arrear_amount'] = $extra_arrear - ($extra_arrear/$mon)*$unpaid_leave;
               $prev_arr[]= $arr;
                
            }
            
            $arrear = 0;
            
            foreach($prev_arr as $fin){
                $arrear = $arrear + $fin['arrear_amount'];
            }
            
            return $arrear;
            
           
    }
    // get employee profile details with bank details.
    public static function getUserAssignMachines($userid) {
        $q = "select machines_list.id, machines_list.machine_type,machines_list.machine_name,machines_list.mac_address,machines_list.serial_number,machines_user.user_Id,machines_user.assign_date from machines_list left join machines_user on machines_list.id = machines_user.machine_id where machines_user.user_Id = '$userid'";
        
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        return $row;
    }

}

new Salary();
?>
