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
           // $arr['type'] = "admin";
        }
        return $arr;
    }

    public function getSalaryInfo($userid) {
        $q = "select * from salary where user_Id = $userid";
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

        $res = self::DBinsertQuery('user_holding_info', $ins);
        if ($res == false) {
            return false;
        } else {
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
        $msg=array();
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

    public function UserDocumentInfo($data) {
        $ins = array(
            'user_Id' => $data['user_id'],
            'Id_proof' => $data['id_proof'],
            'address_proof' => $data['address_proof'],
            'passport_photo' => $data['passport_photo'],
            'certificate' => $data['certificate'],
            'pancard' => $data['pancard'],
            'user_id_for_bank' => $data['uid_for_bank'],
            'prev_company_doc' => $data['previous_comp_doc'],
        );
        $whereField = 'user_Id';
        $whereFieldVal = $data['user_id'];
        $q = 'select * from user_document_detail where user_id=' . $whereFieldVal;
        $run = mysql_query($q);
        $num_rows = mysql_num_rows($run);
        if ($num_rows > 0) {
            $res = self::DBupdateBySingleWhere('user_document_detail', $whereField, $whereFieldVal, $ins);
        }
        if ($num_rows <= 0) {
            $res = self::DBinsertQuery('user_document_detail', $ins);
        }

        if ($res == false) {
            return false;
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

            echo $message;
            //$slackMessageStatus = self::sendSlackMessageToUser( $slack_userChannelid, $message );

            return "Successfully Inserted into table";
        }
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
    
    public static function generateUserSalary($userid){
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $salary_info = self::getSalaryInfo($userid);
        echo "<pre>";
        $num = sizeof($salary_info);
        echo $num;
        if($num > 0){
            $res2 = Salary::getSalaryDetail($salary_info[$num-1]);
             print_r($res2);
        }
        else {
          $r_message = "No salary detail for this user";
            $r_data['message'] = $r_message;  
        }
       
        
    }

}

new Salary();
?>