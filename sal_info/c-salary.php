<?php

header("Access-Control-Allow-Origin: *");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

     exit(0);
}
require_once 'c-database.php';

//comman format for dates = "Y-m-d" eg "04/07/2016"

class Salary extends DATABASE {

    private static $SLACK_client_id = '';
    private static $SLACK_client_secret = '';
    private static $SLACK_token = '';

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
            //$arr['type'] = "admin";
        }
        return $arr;
    }

    public function getSalaryInfo($userid) {
        $q = "select * from salary where user_Id = $userid";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        return $row;
    }

    public function getUserPayslipInfo($userid) {
        $q = "select * from payslips where user_Id = $userid ORDER by id DESC";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        return $row;
    }

    public function getAllUserPayslip($userid, $year, $month) {
        $q = "select * from payslips where month='$month' AND year = '$year'";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        return $row;
    }

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

    public function getHoldingDetail($user_id) {
        $ret = array();
        $q = "select * from user_holding_info where user_Id = $user_id";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        $ret = $row;
        return $ret;
    }

    public static function getEnabledUsersList() {
        $q = "SELECT users.*,user_profile.* FROM users LEFT JOIN user_profile ON users.id = user_profile.user_Id where users.status = 'Enabled' ";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        $newRows = array();
        foreach ($rows as $pp) {
            if ($pp['username'] == 'Admin' || $pp['username'] == 'admin') {
                
            } else {

                $newRows[] = $pp;
            }
        }
        return $newRows;
    }

    public function getrefreshToken() {
        $ret = array();
        $q = "select * from config where type = 'google_payslip_drive_token'";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRow($runQuery);

        return $row;
    }

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
        $type = 1;
        foreach ($ins2 as $key => $val) {

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

        //  $slackMessageStatus = self::sendSlackMessageToUser( $slack_userChannelid, $message );

        return "Successfully Salary Updated";
    }

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
            $slackMessageStatus = self::sendSlackMessageToUser($slack_userChannelid, $message);

            return "Successfully Inserted into table";
        }
    }

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

    public static function getUserInfo($userid) {
        $q = "SELECT users.*,user_profile.* FROM users LEFT JOIN user_profile ON users.id = user_profile.user_Id where users.id = $userid ";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRow($runQuery);
        //slack info if user
        $userSlackInfo = self::getSlackUserInfo($row['work_email']);
        $row['slack_profile'] = $userSlackInfo;
        return $row;
    }

    public function UpdateUserInfo($data) {
        $r_error = 1;
        $r_message = "";
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
                //    $slackMessageStatus = self::sendSlackMessageToUser($slack_userChannelid, $message);
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

    public function insertUserDocumentInfo($data) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();

        $ins = array(
            'user_Id' => $data['user_id'],
            'id_proof' => $data['id_proof'],
            'address_proof' => $data['address_proof'],
            'passport_photo' => $data['passport_photo'],
            'certificate' => $data['certificate'],
            'pancard' => $data['pancard'],
            'user_id_for_bank' => $data['uid_for_bank'],
            'prev_company_doc' => $data['previous_comp_doc'],
        );
        $whereField = 'user_Id';
        $whereFieldVal = $data['user_id'];
        $q = 'select * from user_document_detail where user_Id=' . $whereFieldVal;
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRow($runQuery);
        $num_rows = mysql_num_rows($runQuery);

        if ($num_rows > 0) {
            foreach ($row as $key => $val) {
                if (array_key_exists($key, $ins)) {
                    if ($ins[$key] != $row[$key]) {
                        $arr = array();
                        $arr[$key] = $ins[$key];
                        $res = self::DBupdateBySingleWhere('user_document_detail', $whereField, $whereFieldVal, $ins);
                    }
                }
            }
        }
        if ($num_rows <= 0) {
            $res = self::DBinsertQuery('user_document_detail', $ins);
        }

        if ($res == false) {
            $r_error = 1;
            $r_message = "No fields updated into table";
            $r_data['message'] = $r_message;
        } else {
            $userid = $data['user_id'];
            $userInfo = self::getUserInfo($userid);
            $userInfo_name = $userInfo['name'];
            $slack_userChannelid = $userInfo['slack_profile']['slack_channel_id'];
            $message = "Hey $userInfo_name !!  \n Your document details are updated \n Details: \n ";

            $message = $message . "Id Proof = " . $data['id_proof'] . "\n";
            $message = $message . "Address Proof = " . $data['address_proof'] . "\n";
            $message = $message . "Passport Photo = " . $data['passport_photo'] . "\n";
            $message = $message . "Ceritficate = " . $data['certificate'] . "\n";
            $message = $message . "Pancard = " . $data['pancard'] . "\n";
            $message = $message . "User Id for Bank = " . $data['uid_for_bank'] . "\n";
            $message = $message . "Previous Company Document = " . $data['previous_comp_doc'] . "\n";

            //  echo $message;
            //$slackMessageStatus = self::sendSlackMessageToUser( $slack_userChannelid, $message );

            $r_error = 0;
            $r_message = "Successfully Updated into table";
            $r_data['message'] = $r_message;
        }
        $return = array();

        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

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
        $return = false;
        $message = '[{"text": "' . $message . '", "fallback": "Message Send to Employee", "color": "#36a64f "}]';
        $message = str_replace("", "%20", $message);
        $message = stripslashes($message); // to remove \ which occurs during mysqk_real_escape_string
        $url = "https://slack.com/api/chat.postMessage?token=" . self::$SLACK_token . "&attachments=" . urlencode($message) . "&channel=" . $channelid;
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

    public static function getSlackChannelIds() {
        $return = array();
        $url = "https://slack.com/api/im.list?token=" . self::$SLACK_token;
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

    public static function getSlackUsersList() {
        $return = array();

        $slackChannelIdsLists = self::getSlackChannelIds();

        $url = "https://slack.com/api/users.list?client_id=" . self::$SLACK_client_id . "&token=" . self::$SLACK_token . "&client_secret=" . self::$SLACK_client_secret;
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

    public function getUserprofileDetail($userid) {
        $q = "SELECT users.status,user_profile.* FROM users LEFT JOIN user_profile ON users.id = user_profile.user_Id where users.status = 'Enabled' AND users.id = $userid";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRow($runQuery);
        $arr = "";
        $arr = $row;
        return $arr;
    }

    public function getCurrentMonthHoliday($year, $month) {
        $q = "SELECT * from holidays where date like '%" . $year . "-" . $month . "%'";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        $arr = "";
        $arr = $row;
        return $arr;
    }

    public function getUserBankDetail($userid) {
        $q = "SELECT * FROM user_bank_details WHERE user_Id = $userid";

        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRow($runQuery);
        $arr = "";
        $arr = $row;
        return $arr;
    }

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

    public static function UpdateUserBankInfo($data) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $userid = $data['user_id'];
        $user_bank_detail = self::getUserBankDetail($userid);
        $ins = array(
            'bank_name' => $data['bank_name'],
            'bank_address' => $data['bank_address'],
            'bank_account_no' => $data['account_no'],
            'ifsc' => $data['ifsc']
        );

        $whereField = 'user_Id';
        $whereFieldVal = $userid;
        $msg = array();
        $res = false;
        foreach ($user_bank_detail as $key => $val) {
            if (array_key_exists($key, $data)) {
                if ($data[$key] != $user_bank_detail[$key]) {
                    $arr = array();
                    $arr[$key] = $data[$key];
                    $res = self::DBupdateBySingleWhere('user_bank_details', $whereField, $whereFieldVal, $arr);
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
                $message = "Hey $userInfo_name !!  \n Your bank details are updated \n Details: \n ";
                foreach ($msg as $key => $valu) {
                    $message = $message . "$key = " . $valu . "\n";
                }
                //    $slackMessageStatus = self::sendSlackMessageToUser($slack_userChannelid, $message);
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
            $file_path = "http://" . $_SERVER['SERVER_NAME'] . "/slack_dev/attendance/sal_info/" . $suc;
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

    public static function createPDf($html, $invoice_no, $path = false) {
        require_once "dompdf-master/dompdf_config.inc.php";

        $pname = $invoice_no . ".pdf";
        $theme_root = "a_pdfs/" . $pname;
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
            'leave_balance' => $data['final_leave_balance'],
            'allocated_leaves' => $data['allocated_leaves'],
            'paid_leaves' => $data['paid_leaves'],
            'unpaid_leaves' => $data['unpaid_leaves'],
            'final_leave_balance' => $data['final_leave_balance'],
            'payslip_url' => ""
        );
        $check_google_drive_connection = self::getrefreshToken();
        if (sizeof($check_google_drive_connection) <= 0) {
            $r_error = 1;
            $r_message = "Refresh token not found. Connect do google login first";
            $r_data['message'] = $r_message;
        } else {
            $userid = $data['user_id'];
            $userInfo = self::getUserInfo($userid);
            $userInfo_name = $userInfo['name'];

            $html = '';
            $html = ob_start();
            require_once 'template_payslip.php';
            $html = ob_get_clean();

            $q = "SELECT * FROM payslips where user_Id =" . $data['user_id'] . " AND month ='" . $data['month'] . "' AND year ='" . $data['year'] . "'";
            $runQuery = self::DBrunQuery($q);
            $row = self::DBfetchRow($runQuery);

            if (mysql_num_rows($runQuery) > 0) {
                $payslip_no = $row['id'];
                $file_id = $row['payslip_file_id'];
                $payslip_name = $month_name;
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

                $google_drive_file_url = self::saveFileToGoogleDrive($payslip_name, $userInfo_name, $file_id);

                $query = "UPDATE payslips SET payslip_url= '" . mysql_real_escape_string($google_drive_file_url['url']) . "' , payslip_file_id = '" . $google_drive_file_url['file_id'] . "', status = 0 WHERE id = $payslip_no";
                mysql_query($query);



                $r_error = 0;
                $r_message = "Salary slip updated successfully";
                $r_data['message'] = $r_message;
            } else {
                $res = self::DBinsertQuery('payslips', $ins);

                if ($res == false) {
                    $r_error = 1;
                    $r_message = "Error occured while inserting data";
                    $r_data['message'] = $r_message;
                } else {
                    $payslip_no = mysql_insert_id();
                    $payslip_name = $month_name;

                    $suc = self::createPDf($html, $payslip_name, $path = "payslip");

                    $google_drive_file_url = self::saveFileToGoogleDrive($payslip_name, $userInfo_name);

                    $query = "UPDATE payslips SET payslip_url= '" . mysql_real_escape_string($google_drive_file_url['url']) . "' , payslip_file_id = '" . $google_drive_file_url['file_id'] . "' WHERE id = $payslip_no";
                    mysql_query($query);


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

    public function getUserManagePayslip($userid, $year, $month) {

        $r_error = 1;
        $r_message = "";
        $r_data = array();

        $date = $year . "-" . $month . "-01";
        $month_name = date('F', strtotime($date));

        $user_salaryinfo = array();
        $res1 = self::getSalaryInfo($userid);
        $res0 = self::getUserprofileDetail($userid);
        $latest_sal_id = sizeof($res1) - 1;

        $user_salaryinfo = $res1[$latest_sal_id];
        $salary_detail = self::getSalaryDetail($user_salaryinfo['id']);
        $user_salaryinfo['year'] = $year;
        $user_salaryinfo['month'] = $month;
        $user_salaryinfo['month_name'] = $month_name;
        $user_salaryinfo['name'] = $res0['name'];
        $user_salaryinfo['dateofjoining'] = $res0['dateofjoining'];
        $user_salaryinfo['jobtitle'] = $res0['jobtitle'];
        $user_salaryinfo['salary_detail'] = $salary_detail;
        $total_earning = $salary_detail['Basic'] + $salary_detail['HRA'] + $salary_detail['Conveyance'] + $salary_detail['Medical_Allowance'] + $salary_detail['Special_Allowance'] + $salary_detail['Arrears'];

        $total_deduction = $salary_detail['EPF'] + $salary_detail['Loan'] + $salary_detail['Advance'] + $salary_detail['Misc_Deductions'] + $salary_detail['TDS'];
        $net_salary = $total_earning - $total_deduction;
        $user_salaryinfo['total_earning'] = $total_earning;
        $user_salaryinfo['total_deduction'] = $total_deduction;
        $user_salaryinfo['net_salary'] = abs($net_salary);

        $res = self::getUserPayslipInfo($userid);
        if (sizeof($res) > 0) {

            if ($res[0]['leave_balance'] == "") {
                $res[0]['leave_balance'] = 0;
            }
        }



        $current_month_leave = self::getUserLeaveInfo($userid, $year, $month);
        $leaves = $res[0]['leave_balance'] - $current_month_leave;
        if ($leaves >= 0) {
            $paid_leave = $current_month_leave;
            $unpaid_leave = 0;
        }
        if ($leaves < 0) {
            $paid_leave = $current_month_leave - abs($leaves);
            $unpaid_leave = abs($leaves);
        }

        $user_salaryinfo['total_working_days'] = self::getTotalWorkingDays($year, $month);
        $user_salaryinfo['days_present'] = self::getUserMonthPunching($userid, $year, $month);
        $user_salaryinfo['paid_leaves'] = $paid_leave;
        $user_salaryinfo['unpaid_leaves'] = $unpaid_leave;
        $user_salaryinfo['total_leave_taken'] = $current_month_leave;
        $user_salaryinfo['leave_balance'] = $res[0]['leave_balance'];

        $final_leave_balance = $res[0]['leave_balance'] + $res1[$latest_sal_id]['leaves_allocated'] - $current_month_leave;
        if ($final_leave_balance <= 0) {
            $user_salaryinfo['final_leave_balance'] = 0;
        }
        if ($final_leave_balance > 0) {
            $user_salaryinfo['final_leave_balance'] = $res[0]['leave_balance'] + $res1[$latest_sal_id]['leaves_allocated'] - $current_month_leave;
        }

        $check_google_drive_connection = self::getrefreshToken();

        $r_error = 0;
        $r_data['user_data_for_payslip'] = $user_salaryinfo;
        $r_data['user_payslip_history'] = $res;
        $r_data['google_drive_emailid'] = "";
        if (sizeof($check_google_drive_connection) > 0) {
            //$r_data['google_drive_emailid'] = $check_google_drive_connection['email_id'];
            $r_data['google_drive_emailid'] = "Yes email id exist";
        }
        $r_data['all_users_latest_payslip'] = self::getAllUserPayslip($userid, $year, $month);

        $return = array();

        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    public static function getTotalWorkingDays($year, $month) {
        $list = array();
        for ($d = 1; $d <= 31; $d++) {
            $time = mktime(12, 0, 0, date('m'), $d, date('Y'));
            if (date('m', $time) == date('m'))
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
                $pp['full_date'] = $h_full_date; // added on 27 for daysbetwweb leaves
                $list[$h_date] = $pp;
            }
        }
        return $list;
    }

    // get weekends off list
    public static function getWeekendsOfMonth($year, $month) {
        $list = array();
        $monthDays = self::getDaysOfMonth($year, $month);
        $alternateSaturdayCheck = false;
        foreach ($monthDays as $k => $v) {
            if ($v['day'] == 'Sunday') {
                $list[$k] = $v;
            }
            if ($v['day'] == 'Saturday') {
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

    public static function getUserMonthPunching($userid, $year, $month) {
        //$userid = '313';
        $list = array();
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
            if ($d_year == $year && $d_month == $month) {
                $d['timestamp'] = $d_timestamp;
                $allMonthAttendance[$d_date][] = $d;
            }
        }
        foreach ($allMonthAttendance as $pp_key => $pp) {
            $daySummary = self::_beautyDaySummary($pp);
            $list[$pp_key] = $daySummary;
        }

        return sizeof($list);
    }

    public static function _beautyDaySummary($dayRaw) {
        $TIMESTAMP = '';
        $numberOfPunch = sizeof($dayRaw);

        $timeStampWise = array();
        foreach ($dayRaw as $pp) {
            $TIMESTAMP = $pp['timestamp'];
            $timeStampWise[$pp['timestamp']] = $pp;
        }
        ksort($timeStampWise);
        $inTimeKey = key($timeStampWise);
        end($timeStampWise);
        $outTimeKey = key($timeStampWise);
        $inTime = date('h:i A', $inTimeKey);
        $outTime = date('h:i A', $outTimeKey);
        $r_date = (int) date('d', $TIMESTAMP);
        $r_day = date('l', $TIMESTAMP);

        $r_total_time = $r_extra_time_status = $r_extra_time = '';
        $r_total_time = (int) $outTimeKey - (int) $inTimeKey;
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

    public static function getUserDocumentDetail($userid) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $q = "SELECT * FROM user_document_detail where user_Id = $userid";

        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRow($runQuery);

        if ($row == false) {
            $r_error = 1;
            $r_message = "Error occured while fetching data";
            $r_data['message'] = $r_message;
        } else {
            $r_error = 0;
            $r_data['user_document_info'] = $row;
        }
        $return = array();

        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

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

    public static function sendEmail($userinfo) {
        include "phpmailer/examples/gmail.php";
    }

    public static function saveFileToGoogleDrive($payslip_no, $userInfo_name, $file_id = false) {
        $filename = $payslip_no . ".pdf";
        //upload file in google drive;
        $parent_folder = "Employees Salary Payslips";
        $subfolder_empname = $userInfo_name;
        $subfolder_year = date("Y");
        $r_token = self::getrefreshToken();
        $refresh_token = $r_token['value'];

        include "google-api/examples/indextest.php";

        if ($file_id != false ) {

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

            $fileMetadata = new Google_Service_Drive_DriveFile(array(
                'name' => $parent_folder,
                'mimeType' => 'application/vnd.google-apps.folder'));
            $filez = $service->files->create($fileMetadata, array(
                'fields' => 'id'));


            $pfolder = $filez->id;
        }
        if (!array_key_exists($subfolder_empname, $arr)) {

            $fileMetadata = new Google_Service_Drive_DriveFile(array(
                'name' => $subfolder_empname,
                'parents' => array($pfolder),
                'mimeType' => 'application/vnd.google-apps.folder'));
            $filez = $service->files->create($fileMetadata, array(
                'fields' => 'id'));


            $sfolder = $filez->id;
        }
        if (!array_key_exists($subfolder_year, $arr)) {

            $fileMetadata = new Google_Service_Drive_DriveFile(array(
                'name' => $subfolder_year,
                'parents' => array($sfolder),
                'mimeType' => 'application/vnd.google-apps.folder'));
            $filez = $service->files->create($fileMetadata, array(
                'fields' => 'id'));
            //printf("Folder ID: %s\n", $filez->title);

            $syearfolder = $filez->id;
        }

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

        $url['url'] = "https://drive.google.com/file/d/" . $result2->id . "/preview";
        $url['file_id'] = $result2->id;
////                    //  echo $url;
        $permission = new Google_Service_Drive_Permission();
        $permission->setRole('writer');
        $permission->setType('anyone');
////$permission->setValue( 'me' );
        try {
            $service->permissions->create($result2->id, $permission);
        } catch (Exception $e) {
            print "An error occurred: " . $e->getMessage();
        }
        unlink($testfile);

        return $url;
    }

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
            // echo  $message;
            $slackMessageStatus = self::sendSlackMessageToUser($slack_userChannelid, $message);

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

}

new Salary();
?>