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
    //check user token in database table and its time difference
    public static function validateToken($token) {
        $token = mysql_real_escape_string($token);
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
        $token = mysql_real_escape_string($token);
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
            $arr['type'] = strtolower($val['type']);
        }
        return $arr;
    }
    // get all employee detail
    public function getAllUserDetail() {
        $q = "SELECT users.*,user_profile.* FROM users LEFT JOIN user_profile ON users.id = user_profile.user_Id where users.status = 'Enabled'";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        $row2 = array();
        foreach ($row as $val) {
            if ($val['username'] != strtolower(self::$Admin)) {
                $userid = $val['user_Id'];
                $val['user_bank_detail'] = self::getUserBankDetail($userid); // user bank details.
                $row2[] = $val;
            }
        }
        return $row2;
    }
    //get employee salary info 
    public function getSalaryInfo($userid, $sort = false) {
        if ($sort == 'first_to_last') {
            $q = "select * from salary where user_Id = $userid ORDER by id ASC";
        } else {
            $q = "select * from salary where user_Id = $userid";
        }
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        return $row;
    }
    //get all payslips info of a employee
    public function getUserPayslipInfo($userid) {
        $q = "select * from payslips where user_Id = $userid ORDER by id DESC";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
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
    //Update employee salary details with slack notification message send to employee 
    public static function updateSalary($data) {
        $token = $data['token'];
        $update_by = self::getUserName($token);
        if ($update_by == false) {
            return "Invalid token";
        }
        $ins = array(
            'user_Id' => $data['user_id'],
            'total_salary' => $data['total_salary'],
            'last_updated_on' => date("Y-m-d"),
            'updated_by' => $update_by,
            'leaves_allocated' => $data['leave'],
            'applicable_from' => date("Y-m-d", strtotime($data['applicable_from'])),
            'applicable_till' => date("Y-m-d", strtotime($data['applicable_till']))
        );
        self::DBinsertQuery('salary', $ins);
        $salary_id = mysql_insert_id();
        $ins2 = array(
            'Special_Allowance' => $data['special_allowance'],
            'Medical_Allowance' => $data['medical_allowance'],
            'Conveyance' => $data['conveyance'],
            'HRA' => $data['hra'],
            'Basic' => $data['basic'],
            'Arrears' => $data['arrear'],
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
        $ins = array(
            'user_Id' => $data['user_id'],
            'holding_amt' => $data['holding_amt'],
            'holding_start_date' => $data['holding_start_date'],
            'holding_end_date' => $data['holding_end_date'],
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
            $message = $message . "Holding end date = " . $data['holding_end_date'] . "\n";
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
            if (sizeof($msg > 0)) {
                $message = "Hey $userInfo_name !!  \n Your profile details are updated \n Details: \n ";
                foreach ($msg as $key => $valu) {
                    $message = $message . "$key = " . $valu . "\n";
                }
                $slackMessageStatus = self::sendSlackMessageToUser($slack_userChannelid, $message); // send slack message
            }
            $r_error = 0;
            $r_message = "Successfully Updated into table";
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
        $return = array();
        $r_error = 0;
        $return['error'] = $r_error;
        $return['data']['user_profile_detail'] = $user_profile_detail;
        $return['data']['user_bank_detail'] = $user_bank_detail;
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
        //$dompdf->stream("test.pdf");
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
            if (mysql_num_rows($runQuery) > 0) {
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
                $query = "UPDATE payslips SET payslip_url= '" . mysql_real_escape_string($google_drive_file_url['url']) . "' , payslip_file_id = '" . $google_drive_file_url['file_id'] . "', status = 0 WHERE id = $payslip_no";
                mysql_query($query);
                // if send mail option is true
                if ($data['send_email'] == 1 || $data['send_email'] == '1') {
                    self::sendPayslipMsgEmployee($payslip_no);
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
                    $payslip_no = mysql_insert_id();
                    $payslip_name = $month_name;
                    //create pdf of payslip template
                    $suc = self::createPDf($html, $payslip_name, $path = "payslip");
                    // upload created payslip pdf file in google drive
                    $google_drive_file_url = self::saveFileToGoogleDrive($payslip_name, $userInfo_name, $userid);
                    $query = "UPDATE payslips SET payslip_url= '" . mysql_real_escape_string($google_drive_file_url['url']) . "' , payslip_file_id = '" . $google_drive_file_url['file_id'] . "' WHERE id = $payslip_no";
                    mysql_query($query);
                    // if send mail option is true
                    if ($data['send_email'] == 1 || $data['send_email'] == '1') {
                        // send slack notification message 
                        self::sendPayslipMsgEmployee($payslip_no);
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
// get employee particular month and year  salary details 
    public function getUserManagePayslip($userid, $year, $month) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $date = $year . "-" . $month . "-01";
        $month_name = date('F', strtotime($date));
        $user_salaryinfo = array();
        //get all salary list of employee
        $res1 = self::getSalaryInfo($userid, "first_to_last");
        //get employee profile detail.   
        $res0 = self::getUserprofileDetail($userid);
// get latest salary id
        $latest_sal_id = sizeof($res1) - 1;
        $user_salaryinfo = $res1[$latest_sal_id];
        //get total working days of month
        $user_salaryinfo['total_working_days'] = self::getTotalWorkingDays($year, $month);
        //get employee month attendance
        $user_salaryinfo['days_present'] = self::getUserMonthPunching($userid, $year, $month);
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
        if (sizeof($res) > 0) {
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
        }
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
                if ($v['status'] == "Approved" || $v['status'] == "approved") {
                    if ($v['no_of_days'] < 1) {
                        $current_month_leave = $current_month_leave + $v['no_of_days'];
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
        $user_salaryinfo['days_present'] = $user_salaryinfo['total_working_days'] - $current_month_leave;
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
        $no_of_holidays = self::getHolidaysOfMonth($year, $month);
        $weekends_of_month = self::getWeekendsOfMonth($year, $month);
        if (sizeof($weekends_of_month) > 0) {
            $arru = $weekends_of_month;
        }
        if (sizeof($no_of_holidays) > 0) {
            foreach ($no_of_holidays as $k => $p) {
                if (!array_key_exists($k, $arru)) {
                    $arru[$k] = $p;
                }
            }
        }
        $total_no_of_workdays = sizeof($list) - sizeof($arru);
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
    // get weekends off days list
    public static function getWeekendsOfMonth($year, $month) {
        $list = array();
        $monthDays = self::getDaysOfMonth($year, $month);
        $alternateSaturdayCheck = false;
        foreach ($monthDays as $k => $v) {
            if ($v['day'] == self::$Sunday) {
                $list[$k] = $v;
            }
            if ($v['day'] == self::$Saturday) {
                if ($alternateSaturdayCheck == true) {
                    $list[$k] = $v;
                    $alternateSaturdayCheck = false;
                } else {
                    $alternateSaturdayCheck = true;
                }
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
        return sizeof($list);
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
                $q = "DELETE FROM user_document_detail WHERE id = $id";
                $runQuery = self::DBrunQuery($q);
                $r_error = 0;
                $r_message = "Document deleted successfully";
                $r_data['message'] = $r_message;
            } catch (Exception $e) {
                $r_error = 1;
                $r_data['message'] = $e->getMessage();
            }
        }
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
    public static function sendPayslipMsgEmployee($payslip_id) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $q = "SELECT * FROM payslips where id =" . $payslip_id;
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRow($runQuery);
        if (mysql_num_rows($runQuery) > 0) {
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
            $message = "Hi " . $userInfo_name . ". \n Your salary slip is created for month of $month_name. Please visit below link \n $google_drive_file_url";
            $slackMessageStatus = self::sendSlackMessageToUser($slack_userChannelid, $message); // send slack message notification to employee
            $query = "UPDATE payslips SET status= 1 WHERE id = " . $row['id'];
            mysql_query($query);
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
        $q = "SELECT * FROM leaves Where user_Id = $userid";
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
            $testfile = 'demo/' . $filename;
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
    public static function getAllUserInfo() {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $a = self::getAllUserDetail();
        $row2 = array();
        foreach ($a as $val) {
            $userid = $val['user_Id'];
            $sal = self::getUserlatestSalary($userid);
            $salary_detail = "";
            $previous_increment = "";
            $next_increment_date = "";
            $slack_image = "";
            $holding = "";
            $emailid = $val['work_email'];
            if (sizeof($sal) >= 2) {
                $previous_increment = abs($sal[0]['total_salary'] - $sal[1]['total_salary']);
                $salary_detail = $sal[0]['total_salary'];
                $next_increment_date = $sal[0]['applicable_till'];
            }
            if (sizeof($sal) >= 1 && sizeof($sal) < 2) {
                $salary_detail = $sal[0]['total_salary'];
                $next_increment_date = $sal[0]['applicable_till'];
            }
            $now = date("Y-m-d"); // or your date as well
            $your_date = $val['dateofjoining'];
            $date1 = new DateTime($your_date);
            $date2 = new DateTime($now);
            $interval = $date1->diff($date2);
            $sl = self::getSlackUserInfo($emailid);
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
            $val['no_of_days_join'] = $interval->y . " years, " . $interval->m . " months, " . $interval->d . " days ";
            $val['holdin_amt_detail'] = $holding;
            $row2[] = $val;
            $q = "SELECT * FROM user_profile where user_Id = $userid ";
         
            $runQuery = self::DBrunQuery($q);
            $row = self::DBfetchRow($runQuery);
             $no_of_rows = self::DBnumRows($runQuery);
         
             if($no_of_rows > 0 ){
                 if($row['slack_id'] == ""){
                    $q2 = "UPDATE user_profile SET slack_id = '$slack_id' WHERE user_Id = $userid ";
                 echo $q2;
                    $runQuery2 = self::DBrunQuery($q2); 
                 }
             }
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
        return $row;
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
        echo $q1;
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
        $userid = $data['user_id'];
        $array = array();
        $row = self::getUserDetail($userid);
        if (sizeof($row) > 0) {
            $array['name'] = $row['name'];
            $array['work_email'] = $row['email'];
        }
        $q = "SELECT * FROM email_templates WHERE id=" . $data['template_id'];
        $runQuery = self::DBrunQuery($q);
        $row2 = self::DBfetchRow($runQuery);
        $body = $row2['body'];
        $subject = "";
        foreach ($data as $key => $val) {
            if (strpos($row2['subject'], $key) !== false) {
                $subject = $val;
            }
            if (strpos($row2['body'], $key)) {
                $body = str_replace($key, $val, $body);
            }
        }
        $body = str_replace('\\', '', $body);
        $array['subject'] = $subject;
        $array['body'] = $body;
        $row3 = self::sendEmail($array);
        $r_error = 0;
        $r_message = $row3;
        $r_data['message'] = $r_message;
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
        $work_email = $data['work_email'];
        $name = $data['name'];
        $subject = $data['subject'];
        $body = $data['body'];
        include "phpmailer/PHPMailerAutoload.php";
        $mail = new PHPMailer;
        $mail->isSMTP();
        $mail->SMTPDebug = 0;
        $mail->Debugoutput = 'html';
        $mail->Host = 'smtp.gmail.com';
        $mail->Port = 587;
        $mail->SMTPSecure = 'tls';
        $mail->SMTPAuth = true;
        $mail->Username = "exceltes@gmail.com"; //sender email address 
        $mail->Password = "java@123"; // sender email password
        $mail->setFrom('exceltes@gmail.com', 'Excellence'); // name and email address from which email is send
        $mail->addReplyTo('replyto@example.com', 'no-reply'); // reply email address with name 
        $mail->addAddress($work_email, $name); // name and address to whome mail is to send
        $mail->Subject = $subject; // subject of email message 
        $mail->msgHTML($body); // main message 
        $mail->AltBody = 'This is a plain-text message body';
//Attach an image file
//$mail->addAttachment('images/phpmailer_mini.png');
//send the message, check for errors
        if (!$mail->send()) {
            return $mail->ErrorInfo;
        } else {
            return "Message sent";
        }
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
}
new Salary();
?>