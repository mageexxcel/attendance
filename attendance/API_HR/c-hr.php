<?php

require_once 'c-database.php';
require_once 'c-roles.php';
require_once 'c-jwt.php';
require_once 'c-holiday.php';
require_once 'c-thirdPartyApi.php';

//comman format for dates = "Y-m-d" eg "04/07/2016"

class HR extends DATABASE {

    use Roles;
    use Holiday;
    use ThirdPartyAPI;

    const DEFAULT_WORKING_HOURS = "09:00";
    const DEFAULT_ENTRY_TIME = "10:30 AM";
    const DEFAULT_EXIT_TIME = "07:30 PM";

    private static $SLACK_client_id = '';
    private static $SLACK_client_secret = '';
    private static $SLACK_token = '';
    private static $SLACK_msgtoken = '';
    public static $isAdmin = '';

    const JWT_SECRET_KEY = 'HR_APP';
    const EMPLOYEE_FIRST_PASSWORD = "java@123";

    //-------------------------------------
    function __construct() {
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

    //set isAdmin value
    public static function setAdmin($data) {
        self::$isAdmin = $data;
    }

    //--start login------------------------------------------------------------
    public static function deleteUserTokens($userid) {
        $q = "DELETE FROM login_tokens WHERE userid='$userid'";
        self::DBrunQuery($q);
        return true;
    }

    public static function logout($token) {
        $userInfo = JWT::decode($token, self::JWT_SECRET_KEY);
        $userInfo = json_decode(json_encode($userInfo), true);
        self::deleteUserTokens($userInfo['id']);
        $return = array();
        $return['error'] = 0;
        $r_data = array();
        $r_data['message'] = 'Successfully logout';
        $return['data'] = $r_data;
        return $return;
    }

    public static function validateToken($token) {
        $db = self::getInstance();
        $mysqli = $db->getConnection();

        $token = mysqli_real_escape_string($mysqli, $token);
        $q = "select * from login_tokens where token='$token' ";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        if (sizeof($rows) > 0) {
            return true;
        } else {
            return false;
        }
    }

    public static function insertToken($userid, $token) {
        $creation_timestamp = time();
        $creation_date_time = date('d-M-Y H:i:s');
        $ins = array(
            'userid' => $userid,
            'token' => $token,
            'creation_timestamp' => $creation_timestamp,
            'creation_date_time' => $creation_date_time
        );
        self::DBinsertQuery('login_tokens', $ins);
        return true;
    }

    //START -- added by arun on 4 july2017
    public static function isValidTokenAgainstTime( $token ){
        $return = true;
        $tokenInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
        $tokenInfo = json_decode(json_encode($tokenInfo), true);
        if (is_array($tokenInfo) && isset($tokenInfo['login_time']) && $tokenInfo['login_time'] != "") {
            $token_start_time = $tokenInfo['login_time'];
            $current_time = time();
            $time_diff = $current_time - $token_start_time;
            $mins = $time_diff / 60;
            if ($mins > 60) { //if 60 mins more
                $return = false;
            }
        } else {
            $return = false;
        }
        return $return;
    }

    public static function refreshToken( $oldToken ){
        $return = $oldToken;
        if( self::isValidTokenAgainstTime( $oldToken ) ){
            $loggedUserInfo = JWT::decode($oldToken, HR::JWT_SECRET_KEY);
            $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);
            $loggedUserInfo_userid = $loggedUserInfo['id'];
            $return = self::generateUserToken( $loggedUserInfo_userid );
        }
        return $return;
    }

    public static function generateUserToken( $userid ){
        $jwtToken = '';
        $userInfo = self::getUserInfo($userid);
        if ($userInfo == false) {

        } else {
            $userProfileImage = '';
            try {
                $userProfileImage = $userInfo['slack_profile']['profile']['image_192'];
            } catch (Exception $e) {

            }

            // start added by arun for role update in toked on 21st july
            $userRole = '';
            if( strtolower( $userInfo['type'] ) === 'admin' ){ // this is for wher type is admin
                $userRole = $userInfo['type'];
            }else{
                $roleInfo = self::getUserRole( $userInfo['user_Id'] );
                if( $roleInfo != false ){
                     $userRole = $roleInfo['name'];
                }
            }
            // end added by arun for role update in toked on 21st july


            $u = array(
                "id" => $userInfo['user_Id'],
                "username" => $userInfo['username'],
                "role" => $userRole,
                "name" => $userInfo['name'],
                "jobtitle" => $userInfo['jobtitle'],
                "profileImage" => $userProfileImage,
                "login_time" => time(),
                "login_date_time" => date('d-M-Y H:i:s'),
                "eth_token" => $userInfo['eth_token']
            );

            // start - get user role and then role pages
            if( strtolower( $userInfo['type'] ) == 'admin' ){ // this is super admin
                $u['role_pages'] = self::getRolePagesForSuperAdmin();
            }else{
                $roleInfo = self::getUserRole( $userInfo['user_Id'] );
                if( $roleInfo != false && isset( $roleInfo['role_pages']) ){
                    $u['role_pages'] = self::getRolePagesForApiToken( $roleInfo['id'] );
                }
            }

            // echo '<pre>';
            // print_r( $u) ;
            // end - get user role and then role pages

            
            $u['is_policy_documents_read_by_user'] = 1;
            $u['is_inventory_audit_pending'] = 0; 
            if( strtolower( $userInfo['type'] ) == 'admin' ){ // this is super admin

            }else{
                //start - check if policy docs are read
                $is_policy_documents_read_by_user = self::is_policy_documents_read_by_user( $userInfo['user_Id'] );
                if( $is_policy_documents_read_by_user == false ){
                    $u['is_policy_documents_read_by_user'] = 0;
                    $u['role_pages'] = self::getGenericPagesForAllRoles('');
                }
                //end - check if policy docs are read

                // start - check audit is pending or not                       
                if( HR::isInventoryAuditPending($userid) ){
                    $u['is_inventory_audit_pending'] = 1;
                    $u['role_pages'] = self::getGenericPagesForAllRoles('');
                }
                // end - check audit is pending or not
            }
   
            // echo '<pre>';
            // print_r( $u );

            $jwtToken = JWT::encode($u, self::JWT_SECRET_KEY);
            self::insertToken($userInfo['user_Id'], $jwtToken);
        }
        return $jwtToken;
    }

    //END  -- added by arun on 4 july2017

    public static function login($username, $password) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $q = "select * from users where username='$username' AND password='$password' AND status='Enabled' ";

        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRow($runQuery);

        if ($row == false) {
            $r_error = 1;
            $r_message = "Invalid Login";
        } else {
            $userid = $row['id'];
            ////------
            $userInfo = self::getUserInfo($userid);

            if ($userInfo == false) {
                $r_message = "Invalid Login";
            } else {
                // check if role is not assigned then show message to contact admin
                $is_super_admin = false;
                if( strtolower( $userInfo['type'] ) == 'admin' ){
                    $is_super_admin = true;
                }
                if( $is_super_admin == false && ( !isset( $userInfo['role_id'] ) || $userInfo['role_id'] == '') ){
                    $r_error = 1;
                    $r_message = "Role is not assigned. Contact Admin!";
                }else{
                    $r_error = 0;
                    $r_message = "Success Login";

                    $jwtToken = self::generateUserToken( $userInfo['user_Id'] );

                    self::insertToken($userInfo['user_Id'], $jwtToken);
                    $r_data = array(
                        "token" => $jwtToken,
                        "userid" => $userInfo['user_Id']
                    );
                }
            }
        }

        $return = array();
        $return['error'] = $r_error;
        $r_data['message'] = $r_message;
        $return['data'] = $r_data;

        return $return;
    }

    public static function getUserIdFromUsername($username) {
        $q = "SELECT * from users WHERE username='$username' ";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRow($runQuery);
        if ($row == false) {
            return false;
        } else {
            return $row['id'];
        }
    }

    public static function getUserInfo($userid) {
        // $q = "SELECT users.*,user_profile.* FROM users LEFT JOIN user_profile ON users.id = user_profile.user_Id where users.id = $userid ";
        // $q = "SELECT users.*,user_profile.*,roles.id as role_id,roles.name as role_name FROM users LEFT JOIN user_profile ON users.id = user_profile.user_Id LEFT JOIN user_role ON users.id=user_role.user_Id LEFT JOIN roles ON user_role.role_Id=roles.id where users.id = $userid ";
        $q = "SELECT
                users.*,
                user_profile.*,
                roles.id as role_id,
                roles.name as role_name
                FROM users
                LEFT JOIN user_profile ON users.id = user_profile.user_id
                LEFT JOIN user_roles ON users.id = user_roles.user_id
                LEFT JOIN roles ON user_roles.role_id = roles.id
                where
                users.id = $userid ";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRow($runQuery);
        //slack info if user
        $userSlackInfo = self::getSlackUserInfo($row['work_email']);
        $row['slack_profile'] = $userSlackInfo;
        return $row;
    }

    public static function getEnabledUsersList() {
        $q = "SELECT
                users.*,
                user_profile.*,
                roles.id as role_id,
                roles.name as role_name
                FROM users
                LEFT JOIN user_profile ON users.id = user_profile.user_id
                LEFT JOIN user_roles ON users.id = user_roles.user_id
                LEFT JOIN roles ON user_roles.role_id = roles.id
                where
                users.status = 'Enabled' ";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        $newRows = array();
        foreach ($rows as $pp) {
            if ($pp['username'] == 'Admin' || $pp['username'] == 'admin') {

            } else {
                if (empty(self::$isAdmin)) {
                    unset($pp['holding_comments']);
                }
                $pp['slack_profile'] = array();
                $newRows[] = $pp;
            }
        }
        // slack users
        $slackUsersList = self::getSlackUsersList();


        if (sizeof($slackUsersList) > 0) {
            foreach ($newRows as $key => $pp) {
                $pp_work_email = $pp['work_email'];
                $userid = $pp['user_Id'];
                foreach ($slackUsersList as $sl) {
                    if ($sl['profile']['email'] == $pp_work_email) {
                        $newRows[$key]['slack_profile'] = $sl['profile'];
                        $newRows[$key]['slack_channel_id'] = $sl['slack_channel_id'];
                        $slack_id = $sl['id'];
                        $q = "SELECT * FROM user_profile where user_Id = $userid ";

                        $runQuery = self::DBrunQuery($q);
                        $row = self::DBfetchRow($runQuery);
                        $no_of_rows = self::DBnumRows($runQuery);

                        if ($no_of_rows > 0) {
                            if ($row['slack_id'] == "") {
                                $q2 = "UPDATE user_profile SET slack_id = '$slack_id' WHERE user_Id = $userid ";
                                $runQuery2 = self::DBrunQuery($q2);
                            }
                            if ($row['unique_key'] == "") {
                                $bytes = uniqid();
                                $q2 = "UPDATE user_profile SET unique_key = '$bytes' WHERE user_Id = $userid ";
                                $runQuery2 = self::DBrunQuery($q2);
                            }
                        }

                        break;
                    }
                }
            }
        }

        return $newRows;
    }

    public static function getEnabledUsersListWithoutPass($role = false) {

        $row = self::getEnabledUsersList();
        $secureKeys = [ 'bank_account_num', 'blood_group', 'address1', 'address2', 'emergency_ph1', 'emergency_ph2', 'medical_condition', 'dob', 'marital_status', 'city', 'state', 'zip_postal', 'country', 'home_ph', 'mobile_ph', 'work_email', 'other_email', 'special_instructions', 'pan_card_num', 'permanent_address', 'current_address', 'slack_id', 'policy_document', 'training_completion_date', 'termination_date', 'training_month', 'slack_msg', 'signature', 'role_id', 'role_name', 'eth_token' ];        
        foreach ($row as $val) {
            unset($val['password']);
            if( strtolower($role) == 'guest' ){
                foreach( $val as $key => $value ){
                    foreach( $secureKeys as $secureKey ){
                        if( $key == $secureKey ){
                            unset($val[$key]);
                        }
                    }
                }               
            }
            $rows[] = $val;
        }
        $return = array();
        $return['error'] = 0;
        $return['data'] = $rows;
        return $return;
    }

    //--end login------------------------------------------------------------
    //--start attendance------------------------------------------------------------
    public static function _secondsToTime($seconds) {
        // $padHours == true will return with 0 , ie, if less then 10 then 0 will be attached
        $status = "+";

        if( $seconds * 1 < 0 ){
            $seconds = $seconds * -1;
            $status = "-";
        }

        // extract hours
        $hours = floor($seconds / (60 * 60));

        // extract minutes
        $divisor_for_minutes = $seconds % (60 * 60);
        $minutes = floor($divisor_for_minutes / 60);

        // extract the remaining seconds
        $divisor_for_seconds = $divisor_for_minutes % 60;
        $seconds = ceil($divisor_for_seconds);

        // return the final array
        $obj = array(
            "h" => (int) $hours,
            "m" => (int) $minutes,
            "s" => (int) $seconds,
            "status" => $status
        );

        $padData = array(
            "h" => (int) $hours,
            "m" => (int) $minutes,
            "s" => (int) $seconds,
        );
        if( $hours < 10 ){
            $padData['h'] = (int) '0'.$hours;    
        }
        if( $minutes < 10 ){
            $padData['m'] = (int) '0'.$minutes;    
        }
        if( $seconds < 10 ){
            $padData['s'] = (int) '0'.$seconds;    
        }
            
        $obj["pad_hms"] = $padData;

        return $obj;
    }

    public static function _beautyDaySummary($dayRaw, $dayWorkingHours = false ) {
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

        $orignal_total_time = 0;

        if( $dayWorkingHours === false ){
            $orignal_total_time = (int) ( 9 * 60 * 60 ); 
            $r_extra_time = (int) $r_total_time - (int) ( 9 * 60 * 60 );    
        } else {
            // this will calculate from the hours passed
            $explodeDayWorkingHours = explode(":",$dayWorkingHours);
            $explodeDay_hour = $explodeDayWorkingHours[0] * 60 * 60;
            $explodeDay_minute = $explodeDayWorkingHours[1] * 60;

            $orignal_total_time = (int) ( $explodeDay_hour + $explodeDay_minute );

            $r_extra_time = (int) $r_total_time - (int) ( $explodeDay_hour + $explodeDay_minute );
        }

        

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
        $return['orignal_total_time'] = $orignal_total_time;

        // added new keys to get times in seconds
        $return['seconds_actual_working_time'] = $orignal_total_time;
        $return['seconds_actual_worked_time'] = $r_total_time;
        $return['seconds_extra_time'] = $r_extra_time;

        // calulate active hours, i.e the time user was in the office
        $insideOfficeTime =  self::getInsideOfficeTime($dayRaw);
        $return['office_time_inside'] = $insideOfficeTime['inside_time_seconds'];
        $return['user_punches'] = $dayRaw;
        
        return $return;
    }

    //
    public static function getInsideOfficeTime( $dayPunches ){
        $totalInsideTime = 0;
        if( sizeOf( $dayPunches ) > 1 ){
            $b = array_chunk($dayPunches,2);            
            foreach( $b as $break ){
                if( sizeof($break) == 2 ){
                    $startInside = $break[0]['timestamp'];
                    $endInside = $break[1]['timestamp'];
                    $timeInside = $endInside - $startInside;
                    $totalInsideTime += $timeInside;                    
                }
            }
           
        }
        $ret = array();
        $ret['inside_time_seconds'] = $totalInsideTime;
        return $ret;
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

    // get month holidays list

    public static function getHolidaysOfMonth($year, $month) {
        $q = "SELECT * FROM holidays WHERE NOT type = " . self::$RESTRICTED_HOLIDAY;
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        $holiday_type = self::getHolidayTypesList();        
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
                for($i = 0; $i < count($holiday_type); $i++){
                    if($pp['type'] == $holiday_type[$i]['type']){
                        $pp['type_text'] = $holiday_type[$i]['text'];
                    }
                }
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

        if( $year >= 2018 && $month >= 03 ){
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

    public static function getNonworkingdayAsWorking($year, $month) {
        $list = array();
        $list = self::getWorkingHoursOfMonth($year, $month);


        return $list;
    }

    public static function getMonthTotalWorkingHours($month) {

    }

    ///------working hours
    public static function getWorkingHoursSummary($year, $month) {  //API CALL FUNCTION
        $workingHoursSummary = self::getGenericMonthSummary($year, $month);

        $aa = array();
        foreach ($workingHoursSummary as $p) {
            $aa[] = $p;
        }


        $nextMonth = self::_getNextMonth($year, $month);
        $previousMonth = self::_getPreviousMonth($year, $month);
        $currentMonth = self::_getCurrentMonth($year, $month);



        $r_data['year'] = $year;
        $r_data['month'] = $month;
        $r_data['monthName'] = $currentMonth['monthName'];
        $r_data['monthSummary'] = $monthSummary;
        $r_data['nextMonth'] = $nextMonth;
        $r_data['previousMonth'] = $previousMonth;
        $r_data['monthSummary'] = $aa;

        $r_error = 0;
        $return = array();
        $return['error'] = $r_error;
        $r_data['message'] = '';
        $return['data'] = $r_data;

        return $return;
    }

    public static function updateDayWorkingHours($date, $time) {  //API CALL FUNCTION
        //date = Y-m-d
        $q = "SELECT * FROM working_hours WHERE `date`='$date'";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);

        $message = "";

        if (is_array($rows) && sizeof($rows) > 0) {
            $q = "UPDATE working_hours set working_hours='$time' WHERE `date` = '$date' ";
            self::DBrunQuery($q);
            $message = "Success Update";
        } else {
            $q = "INSERT into working_hours ( working_hours, `date`  ) VALUES ( '$time', '$date' )";
            self::DBrunQuery($q);
            $message = "Success Insert";
        }


        $monthYear = array(
            'month' => date('m', strtotime($date)),
            'year' => date('Y', strtotime($date)),
        );

        $r_error = 0;
        $return = array();
        $r_data = array();
        $return['error'] = $r_error;
        $r_data['message'] = $message;
        $r_data['monthYear'] = $monthYear;
        $return['data'] = $r_data;
        return $return;
    }

    // add keys required for a day summary
    public static function _addRequiredKeysForADay($days) {
        $return = array();
        foreach ($days as $k => $day) {
            $day['day_type'] = 'WORKING_DAY';
            $day['day_text'] = '';
            $day['in_time'] = '';
            $day['out_time'] = '';
            $day['total_time'] = '';
            $day['extra_time'] = '';
            $day['text'] = '';
            $day['admin_alert'] = '';
            $day['admin_alert_message'] = '';
            $return[$k] = $day;
        }
        return $return;
    }

    public static function getGenericMonthSummary($year, $month, $userid = false) { // $userid added on 5jan2018 by arun so as to use user working hours
        $daysOfMonth = self::getDaysOfMonth($year, $month);

        //add default working hours
        foreach ($daysOfMonth as $kk => $pp) {
            $daysOfMonth[$kk]['office_working_hours'] = self::DEFAULT_WORKING_HOURS;
        }

        $daysOfMonth = self::_addRequiredKeysForADay($daysOfMonth);
        $holidaysOfMonth = self::getHolidaysOfMonth($year, $month);
        $weekendsOfMonth = self::getWeekendsOfMonth($year, $month);
        $nonworkingdayasWorking = self::getNonworkingdayAsWorking($year, $month);
        $workingHoursOfMonth = self::getWorkingHoursOfMonth($year, $month); // change thisis arun

        if (sizeof($holidaysOfMonth) > 0) {
            foreach ($holidaysOfMonth as $hm_key => $hm) {
                $daysOfMonth[$hm_key]['day_type'] = 'NON_WORKING_DAY';
                $daysOfMonth[$hm_key]['day_text'] = $hm['name'];
            }
        }
        if (sizeof($weekendsOfMonth) > 0) {
            foreach ($weekendsOfMonth as $hm_key => $hm) {
                $daysOfMonth[$hm_key]['day_type'] = 'NON_WORKING_DAY';
                $daysOfMonth[$hm_key]['day_text'] = 'Weekend Off';
            }
        }
//        if (sizeof($nonworkingdayasWorking) > 0) {
//            foreach ($nonworkingdayasWorking as $hm_key => $hm) {
//                $daysOfMonth[$hm_key]['day_type'] = 'WORKING_DAY';
//                $daysOfMonth[$hm_key]['day_text'] = '';
//            }
//        }
        if (sizeof($workingHoursOfMonth) > 0) {
            foreach ($workingHoursOfMonth as $hm_key => $hm) {
                $daysOfMonth[$hm_key]['day_type'] = 'WORKING_DAY';
                $daysOfMonth[$hm_key]['office_working_hours'] = $hm['working_hours'];
            }
        }

        // start ---- added on 5jan2018
        if( $userid != false ){
            $userWorkingHours = self::getUserMangedHours($userid);
            if (sizeof($userWorkingHours) > 0) {
                foreach( $daysOfMonth as $key => $dm ){
                    foreach ($userWorkingHours as $hm_key => $hm) {
                        if( $dm['full_date'] == $hm['date'] ){
                            $daysOfMonth[$key]['office_working_hours'] = $hm['working_hours'];
                        }
                    }                    
                }
            }
        }
        // end ---- added on 5jan2018

        // add  original_working_time // added on 23rd jan 2018 by arun

        foreach( $daysOfMonth as $key => $dom ){
            if( $dom['office_working_hours'] != '' ){
                $explodeDayWorkingHours = explode(":",$dom['office_working_hours']);
                $explodeDay_hour = $explodeDayWorkingHours[0] * 60 * 60;
                $explodeDay_minute = $explodeDayWorkingHours[1] * 60;
                $orignal_total_time = (int) ( $explodeDay_hour + $explodeDay_minute );
                $daysOfMonth[$key]['orignal_total_time'] = $orignal_total_time;
            }
        }

        return $daysOfMonth;
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

            // check if date and time are not there in string
            if( strlen($d_timing) < 10 ){

            } else {
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
        }

        // added on 5jan2018----
        $genericMonthDays = self::getGenericMonthSummary($year, $month, $userid); // $userid added on 5jan2018 by arun so as to use user working hours
        
        // userMonthLeaves is added to get the working hours for halfday
        $userMonthLeaves = self::getUserMonthLeaves($userid,$year,$month);

        foreach ($allMonthAttendance as $pp_key => $pp) {
            $dayW_hours = false;
            if( isset($genericMonthDays[$pp_key]) && isset($genericMonthDays[$pp_key]['office_working_hours'])){
                $dayW_hours = $genericMonthDays[$pp_key]['office_working_hours'];
            }
            // check if day is a leave and it is half day then daywhours will be 04:30 hours
            if( isset($userMonthLeaves[$pp_key]) && isset($userMonthLeaves[$pp_key]['no_of_days']) && $userMonthLeaves[$pp_key]['no_of_days'] == '0.5' ){
                $dayW_hours = '04:30';
            }
            $daySummary = self::_beautyDaySummary($pp, $dayW_hours);
            $list[$pp_key] = $daySummary;
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

    public static function getUserMonthLeaves($userid, $year, $month) {
        //$userid = '313';
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
        $monthWeekends = self::getWeekendsOfMonth($year, $month);
        if (sizeof($monthHolidays) > 0) {
            foreach ($monthHolidays as $d => $v) {
                if (array_key_exists($d, $list)) {
                    unset($list[$d]);
                }
            }
        }
        if (sizeof($monthWeekends) > 0) {
            foreach ($monthWeekends as $w => $v2) {
                if (array_key_exists($w, $list)) {
                    unset($list[$w]);
                }
            }
        }

        return $list;
    }

    public static function getUserMonthAttendace($userid, $year, $month) {
        $genericMonthDays = self::getGenericMonthSummary($year, $month, $userid); // $userid added on 5jan2018 by arun so as to use user working hours
        $userMonthPunching = self::getUserMonthPunching($userid, $year, $month);
        $userMonthLeaves = self::getUserMonthLeaves($userid, $year, $month);

        //start ---added on 8th august to ignore if leaves are not pending and approved
        if (sizeof($userMonthLeaves) > 0) {
            $raw_userMonthLeaves = $userMonthLeaves;
            $userMonthLeaves = array();
            foreach ($raw_userMonthLeaves as $k => $v) {
                $v_status = $v['status'];
                if (strtolower($v_status) == 'pending' || strtolower($v_status) == 'approved') {
                    $userMonthLeaves[$k] = $v;
                }
            }
        }

        //end ---added on 8th august to ignore if leaves are not pending and approved

        $return = array();
        foreach ($genericMonthDays as $k => $v) {
            if (array_key_exists($k, $userMonthPunching)) {
                $v['in_time'] = $userMonthPunching[$k]['in_time'];
                $v['out_time'] = $userMonthPunching[$k]['out_time'];
                $v['total_time'] = $userMonthPunching[$k]['total_time'];
                $v['extra_time_status'] = $userMonthPunching[$k]['extra_time_status'];
                $v['extra_time'] = $userMonthPunching[$k]['extra_time'];
                $v['orignal_total_time'] = $userMonthPunching[$k]['orignal_total_time'];

                $v['seconds_actual_working_time'] = $userMonthPunching[$k]['seconds_actual_working_time'];
                $v['seconds_actual_worked_time'] = $userMonthPunching[$k]['seconds_actual_worked_time'];
                $v['seconds_extra_time'] = $userMonthPunching[$k]['seconds_extra_time'];
                $v['office_time_inside'] = $userMonthPunching[$k]['office_time_inside'];

                $return[$k] = $v;
            } else {
                $return[$k] = $v;
            } 
        }

        foreach ($return as $k => $v) {

            if (array_key_exists($k, $userMonthLeaves)) {
                $leave_number_of_days = $userMonthLeaves[$k]['no_of_days'];
                if ($leave_number_of_days < 1) { // this means less then 1 day leave like half day
                    $v['day_type'] = 'HALF_DAY';
                    $v['day_text'] = $userMonthLeaves[$k]['reason'];
                    $v['office_working_hours'] = "04:30";
                    $v['orignal_total_time'] = ($v['orignal_total_time'] / 2) ;
                } else {
                    $v['day_type'] = 'LEAVE_DAY';
                    $v['day_text'] = $userMonthLeaves[$k]['reason'];
                }
                $return[$k] = $v;
            } else {
                $return[$k] = $v;
            }
        }

        //--check for admin alert if in/out time missing

        foreach ($return as $k => $r) {
            if ($r['day_type'] == 'WORKING_DAY') {
                if ($r['in_time'] == '' || $r['out_time'] == '') {
                    $r['admin_alert'] = 1;
                    $r['admin_alert_message'] = "In/Out Time Missing";
                }
                $return[$k] = $r;
            }
        }


        $finalReturn = array();
        foreach ($return as $r) {
            $finalReturn[] = $r;
        }



        return $finalReturn;
    }

    public static function API_getStatsAttendanceSummary() {

        $r_error = 0;
        $r_data = array();
        $return = array();
        $attendance_rows = array();

        $q = "SELECT * from attendance ORDER BY timing ASC";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);

        foreach($rows as $key => $date){
            $full_date = explode(" ", $date['timing']);
            $explode_full_date = explode("-", $full_date[0]);
            $year = $explode_full_date[2];

            $flag = false;
            if(count($attendance_rows)){
                foreach($attendance_rows as $key => $attendance){
                    if($attendance['year'] == $year){
                        $attendance_rows[$key]['count']++;
                        $flag = true;
                        break;
                    } 
                }
            }
            if($flag == false){
                $attendance_rows[] = [
                    'year' => $year,
                    'count' => 1
                ];
            }
        }
        
        $r_data['message'] = '';
        $r_data['attendance_rows'] = $attendance_rows;
        
        $return = [
            'error' => $r_error,
            'data' => $r_data
        ];

        return $return;
    }

    public static function API_deleteAttendanceStatsSummary($year) {

        $r_error = 0;
        $r_data = array();
        $return = array();
        $current_year = date('Y');
        $previous_year = $current_year - 1;
        
        if ( isset($year) && $year != "" ) {

            if ( $year == $current_year || $year == $previous_year ) {
                $r_error = 1;
                $r_data['message'] = "Can't delete current or previous year attendance.";
    
            } else {
    
                $q = "SELECT * from attendance where timing like '%$year%'";
                $runQuery = self::DBrunQuery($q);
                $rows = self::DBfetchRows($runQuery);
                
                if( count($rows) > 0 ){
                    
                    $q = "DELETE FROM attendance WHERE timing like '%$year%'";
                    $runQuery = self::DBrunQuery($q);
                    $r_data['message'] = "Records deleted for " . $year;
    
                } else {
                    $r_error = 1;
                    $r_data['message'] = "Records not found for " . $year;
                }
    
            }
    
        } else {
            $q = "SELECT * from attendance where timing like '__:%'";
            $runQuery = self::DBrunQuery($q);
            $rows = self::DBfetchRows($runQuery);
            
            if ( count($rows) > 1 ) {
                $q = "DELETE FROM attendance WHERE timing like '__:%'";
                $runQuery = self::DBrunQuery($q);
                $r_data['message'] = "Junk Records deleted";

            } else {
                $r_error = 1;
                $r_data['message'] = "No Junk Records Found";
            }
        }

        
        $return = [
            'error' => $r_error,
            'data' => $r_data
        ];

        return $return;        
    }

    public static function _beautyMonthSummary($monthAttendace) {

        // print_r( $monthAttendace );

        $r_actual_working_hours = $r_completed_working_hours = $r_pending_working_hours = 0;

        $WORKING_DAYS = $NON_WORKING_DAYS = $LEAVE_DAYS = $HALF_DAYS = 0;

        $r_actual_working_seconds = $r_completed_working_seconds = $r_pending_working_seconds = 0;


        foreach ($monthAttendace as $pp) {           
            
            $day_type = $pp['day_type'];

            if($day_type == 'WORKING_DAY' || $day_type == 'HALF_DAY' ){
                $r_actual_working_seconds += $pp['orignal_total_time']; 
            }


            if ($day_type == 'WORKING_DAY') {
                $WORKING_DAYS++;
                $r_completed_working_seconds += $pp['total_time'];
            } else if ($day_type == 'NON_WORKING_DAY') {
                $NON_WORKING_DAYS++;
            } else if ($day_type == 'LEAVE_DAY') {
                $LEAVE_DAYS++;
            } else if ($day_type == 'HALF_DAY') {
                $HALF_DAYS++;
                $r_completed_working_seconds += $pp['total_time'];
            }
        }

        //-----------------------------
        //$r_actual_working_seconds = $WORKING_DAYS * 9 * 60 * 60;
        $r_pending_working_seconds = $r_actual_working_seconds - $r_completed_working_seconds;
        //-----------------------------
        $a = self::_secondsToTime($r_actual_working_seconds);
        $r_actual_working_hours = $a['h']. ' Hrs ' . $a['m'] . ' Mins';

        $b = self::_secondsToTime($r_completed_working_seconds);
        $r_completed_working_hours = $b['h'] . ' Hrs ' . $b['m'] . ' Mins';

        $c = self::_secondsToTime($r_pending_working_seconds);
        $r_pending_working_hours = $c['status'].' '.$c['h'] . ' Hrs ' . $c['m'] . ' Mins';

        // echo "r_pending_working_seconds ----- $r_pending_working_seconds<br>";
        // print_r($c);
        // echo "r_pending_working_seconds ----- $r_pending_working_hours<br>";
        // echo "<hr>";
        //-----------------------------

        $monthSummary = array();
        $monthSummary['actual_working_hours'] = $r_actual_working_hours;
        $monthSummary['completed_working_hours'] = $r_completed_working_hours;
        $monthSummary['pending_working_hours'] = $r_pending_working_hours;
        $monthSummary['WORKING_DAY'] = $WORKING_DAYS;
        $monthSummary['NON_WORKING_DAY'] = $NON_WORKING_DAYS;
        $monthSummary['LEAVE_DAY'] = $LEAVE_DAYS;
        $monthSummary['HALF_DAY'] = $HALF_DAYS;
        $monthSummary['admin_alert'] = '';
        $monthSummary['admin_alert_message'] = '';

        $monthSummary['seconds_actual_working_hours'] = $r_actual_working_seconds;
        $monthSummary['seconds_completed_working_hours'] = $r_completed_working_seconds;
        $monthSummary['seconds_pending_working_hours'] = $r_pending_working_seconds;

        return $monthSummary;
    }

    public static function _beautyMonthAttendance($monthAttendance) {
        foreach ($monthAttendance as $key => $mp) {
            //check for future working day
            if (isset($mp['day_type']) && $mp['day_type'] == 'WORKING_DAY') {
                $currentTimeStamp = time();
                $mp_timeStamp = strtotime($mp['full_date']);
                if ((int) $mp_timeStamp > (int) $currentTimeStamp) {

                    $monthAttendance[$key]['day_type'] = "FUTURE_WORKING_DAY";
                }
            }
            // convert total working time to readable format
            if (isset($mp['total_time']) && !empty($mp['total_time'])) {
                $aa = self::_secondsToTime($mp['total_time']);
                $monthAttendance[$key]['total_time'] = $aa['h'] . 'h : ' . $aa['m'] . 'm :' . $aa['s'] . 's';
            }
            //convert extra time to readable format
            if (isset($mp['extra_time']) && !empty($mp['extra_time'])) {

                $bb = self::_secondsToTime($mp['extra_time']);
                $monthAttendance[$key]['extra_time'] = $bb['h'] . 'h : ' . $bb['m'] . 'm :' . $bb['s'] . 's';
            }
        }
        return $monthAttendance;
    }

    public static function _getCurrentMonth($year, $month) {
        $currentMonthDate = date('Y-m-d', strtotime("$year-$month-01"));
        $currentMonth = array();
        $currentMonth['year'] = date('Y', strtotime($currentMonthDate));
        $currentMonth['month'] = date('m', strtotime($currentMonthDate));
        $currentMonth['monthName'] = date('F', strtotime($currentMonthDate));
        return $currentMonth;
    }

    public static function _getNextMonth($year, $month) {
        $nextMonthDate = date('Y-m-d', strtotime('+1 month', strtotime("$year-$month-01")));
        $nextMonth = array();
        $nextMonth['year'] = date('Y', strtotime($nextMonthDate));
        $nextMonth['month'] = date('m', strtotime($nextMonthDate));
        $nextMonth['monthName'] = date('F', strtotime($nextMonthDate));
        return $nextMonth;
    }

    public static function _getPreviousMonth($year, $month) {
        $previousMonthDate = date('Y-m-d', strtotime('-1 month', strtotime("$year-$month-01")));
        $previousMonth = array();
        $previousMonth['year'] = date('Y', strtotime($previousMonthDate));
        $previousMonth['month'] = date('m', strtotime($previousMonthDate));
        $previousMonth['monthName'] = date('F', strtotime($previousMonthDate));
        return $previousMonth;
    }

    public static function getUserMonthAttendaceComplete($userid, $year, $month) {
        
        $r_error = 1;
        $r_message = "";
        $r_data = array();

        $userMonthAttendance = self::getUserMonthAttendace($userid, $year, $month);
        $monthSummary = self::_beautyMonthSummary($userMonthAttendance);

        $beautyMonthAttendance = self::_beautyMonthAttendance($userMonthAttendance);

        $nextMonth = self::_getNextMonth($year, $month);
        $previousMonth = self::_getPreviousMonth($year, $month);
        $currentMonth = self::_getCurrentMonth($year, $month);

        //----user details -----
        $userDetails = self::getUserInfo($userid);
        unset($userDetails['password']);

        ///////////
        $r_data['userProfileImage'] = $userDetails['slack_profile']['profile']['image_192'];
        $r_data['userName'] = $userDetails['name'];
        $r_data['userjobtitle'] = $userDetails['jobtitle'];
        $r_data['userid'] = $userid;
        $r_data['year'] = $year;
        $r_data['month'] = $month;
        $r_data['monthName'] = $currentMonth['monthName'];
        $r_data['monthSummary'] = $monthSummary;
        $r_data['nextMonth'] = $nextMonth;
        $r_data['previousMonth'] = $previousMonth;
        $r_data['attendance'] = $beautyMonthAttendance;


        // added to calculate compensation times added by arun on 29th jan 2018
        $analyseCompensationTime = self::_analyseCompensationTime($beautyMonthAttendance);
        $r_data['compensationSummary'] = $analyseCompensationTime;

        $r_error = 0;
        $return = array();
        $return['error'] = $r_error;
        $r_data['message'] = $r_message;
        $return['data'] = $r_data;        

        return $return;
    }

    // this is added to calculate compensation timings by arun on 29th jan 2018 this accepts final beauty attendance
    public static function _analyseCompensationTime($beautyAttendance){
        $seconds_to_be_compensate = 0;
        $seconds_for_compensation = 0;
        $compensation_break_up = [];
        $currentDate = date('Y-m-d');
        
        foreach( $beautyAttendance as $day ){
            // don't include todays date
            if( $currentDate == $day['full_date'] ){
                continue;
            }
            $breakUpText = "";
            // print_r($day);
            if( $day['day_type'] === 'WORKING_DAY' || $day['day_type'] === 'HALF_DAY' ){
                $day_full_date = $day['full_date'];                
                $day_orignal_total_time = $day['orignal_total_time'];

                $date_for_break_up = date('d-M', strtotime($day['full_date']));              

                // if in out time is missing
                if( trim($day['total_time']) == ""){
                    $seconds_to_be_compensate += $day_orignal_total_time;
                    // storing per day working hours if in out time is missing ( 9 hrs ) 
                    $seconds_for_compensation += $day_orignal_total_time;
                    $hms = self::_secondsToTime($day_orignal_total_time);
                    $hms_show = $hms['pad_hms']['h']."h:".$hms['pad_hms']['m']."m:".$hms['pad_hms']['s'].'s';

                    $breakUpText = "$date_for_break_up # Addition # $hms_show";
                } else{
                    $day_extra_time_status = $day['extra_time_status'];
                    $day_seconds_extra_time = $day['seconds_extra_time'];
                    if( $day_extra_time_status === '-'){
                        // echo "PLUS <br>";
                        $seconds_to_be_compensate += $day_seconds_extra_time;                        
                        // $breakUpText = "$date_for_break_up # Addition in compensation Time : $day_full_date : $day_seconds_extra_time";

                        $hms = self::_secondsToTime($day_seconds_extra_time);
                        $hms_show = $hms['pad_hms']['h']."h:".$hms['pad_hms']['m']."m:".$hms['pad_hms']['s'].'s';
                        // calculate per day compensaton time if less than 4hrs and add it to previous compensation time
                        if( $day_seconds_extra_time < 14400 ){
                            $seconds_for_compensation += $day_seconds_extra_time;
                            $breakUpText = "$date_for_break_up # Addition # $hms_show";                        
                        }
                    }
                    if( $day_extra_time_status === '+' && $seconds_to_be_compensate > 0 ){
                        // echo "MINUS <br>";
                        $seconds_to_be_compensate -= $day_seconds_extra_time;

                        
                        $hms = self::_secondsToTime($day_seconds_extra_time);
                        $hms_show = $hms['pad_hms']['h']."h:".$hms['pad_hms']['m']."m:".$hms['pad_hms']['s'].'s';
                        // calculate per day compensaton time if less than 4hrs and subtract it from previous compensation time
                        if( $day_seconds_extra_time < 14400 ){
                            $seconds_for_compensation -= $day_seconds_extra_time;
                            $breakUpText = "$date_for_break_up # Deduction # $hms_show";
                        }
                    }
                }
            }

            if( $seconds_to_be_compensate < 0 ){
                $seconds_to_be_compensate = 0;
            }
            if( $seconds_for_compensation < 0 ){
                $seconds_for_compensation = 0;
            }

            if( $breakUpText != ''){
                // $hms = self::_secondsToTime($seconds_to_be_compensate);
                // calculate pending compensation time and skipping 4hr or more compensation time 
                $hms = self::_secondsToTime($seconds_for_compensation);
                $hms_show = $hms['pad_hms']['h']."h:".$hms['pad_hms']['m']."m:".$hms['pad_hms']['s'].'s';                
                $breakUpText = $breakUpText. " ## Pending = $hms_show";
            }
            
            // echo "----------------- :: $seconds_to_be_compensate<br><br>";

            if($breakUpText != ''){
                $row = array(
                    'text' => $breakUpText
                );
                $compensation_break_up[] = $row;
            }

        }

        $return = [];
        $return['seconds_to_be_compensate'] = $seconds_to_be_compensate;
        $return['time_to_be_compensate'] = "";
        if( $seconds_to_be_compensate > 0 ){
            $bb = self::_secondsToTime($seconds_to_be_compensate);
            $return['time_to_be_compensate'] = $bb['h'] . 'h : ' . $bb['m'] . 'm :' . $bb['s'] . 's';
        }
        $return['compensation_break_up'] = $compensation_break_up;
        return $return;
    }


    //--end attendance------------------------------------------------------------
    //-start all users attendance summary----------------
    public static function getMonthAttendaceSummary($year, $month) {

        $r_error = 1;
        $r_message = "";
        $r_data = array();

        $usersAttendance = array();

        $enabledUsersList = self::getEnabledUsersList();
        foreach ($enabledUsersList as $u) {
            $userid = $u['user_Id'];
            $username = $u['username'];
            if ($username == 'admin' || $userid == '' || $username == '') {
                continue;
            }

            // if( $userid != 313 && $userid != 288 && $userid != 343 ){
            //     continue;
            // }

            $user_month_attendance = self::getUserMonthAttendaceComplete($userid, $year, $month);

            $user_month_attendance = $user_month_attendance['data'];

            $u_data = array();
            $u_data['name'] = $u['name'];
            $u_data['profileImage'] = '';
            $u_data['jobtitle'] = $u['jobtitle'];
            $u_data['userid'] = $userid;
            $u_data['year'] = $user_month_attendance['year'];
            $u_data['month'] = $user_month_attendance['month'];
            $u_data['monthName'] = $user_month_attendance['monthName'];
            $u_data['monthSummary'] = $user_month_attendance['monthSummary'];
            $u_data['nextMonth'] = $user_month_attendance['nextMonth'];
            $u_data['previousMonth'] = $user_month_attendance['previousMonth'];
            $u_data['attendance'] = $user_month_attendance['attendance'];
            $usersAttendance[] = $u_data;
        }
        //----------
        $nextMonth = self::_getNextMonth($year, $month);
        $previousMonth = self::_getPreviousMonth($year, $month);
        $currentMonth = self::_getCurrentMonth($year, $month);
        //----------

        $r_data['year'] = $year;
        $r_data['month'] = $month;
        $r_data['monthName'] = $currentMonth['monthName'];
        $r_data['nextMonth'] = $nextMonth;
        $r_data['previousMonth'] = $previousMonth;
        $r_data['usersAttendance'] = $usersAttendance;

        $r_error = 0;
        $return = array();
        $return['error'] = $r_error;
        $r_data['message'] = $r_message;
        $return['data'] = $r_data;

        return $return;
    }

    //-end all users attendance summary
    //--start-- user day summary
    public static function getUserDayPunchingDetails($userid, $date) {
        $requested_date = date('d', strtotime($date));
        $requested_month = date('m', strtotime($date));
        $requested_year = date('Y', strtotime($date));
        $requested_month_name = date('F', strtotime($date));
        $requested_day = date('l', strtotime($date));

        $userMonthPunching = self::getUserMonthPunching($userid, $requested_year, $requested_month);

        $r_in_time = $r_out_time = $r_total_time = '';
        $r_extra_time_status = $r_extra_time = '';

        if (array_key_exists($requested_date, $userMonthPunching)) {
            $dayPunchFound = $userMonthPunching[$requested_date];
            $r_in_time = $dayPunchFound['in_time'];
            $r_out_time = $dayPunchFound['out_time'];
            $r_total_time = $dayPunchFound['total_time'];
            $r_extra_time_status = $dayPunchFound['extra_time_status'];
            $r_extra_time = $dayPunchFound['extra_time'];
        }

        $return = array();
        $return['year'] = $requested_year;
        $return['month'] = $requested_month;
        $return['monthName'] = $requested_month_name;
        $return['date'] = $requested_date;
        $return['day'] = $requested_day;
        $return['in_time'] = $r_in_time;
        $return['out_time'] = $r_out_time;
        $return['total_time'] = $r_total_time;
        $return['extra_time_status'] = $r_extra_time_status;
        $return['extra_time'] = $r_extra_time;

        return $return;
    }

    public static function getUserDaySummary($userid, $date) {
        $userInfo = self::getUserInfo($userid);


        $r_error = 1;
        $r_message = "";
        $r_data = array();

        $userDayPunchingDetails = self::getUserDayPunchingDetails($userid, $date);

        $r_data['name'] = $userInfo['name'];
        $r_data['profileImage'] = '';
        $r_data['userid'] = $userid;
        $r_data['year'] = $userDayPunchingDetails['year'];
        $r_data['month'] = $userDayPunchingDetails['month'];
        $r_data['monthName'] = $userDayPunchingDetails['monthName'];
        $r_data['day'] = $userDayPunchingDetails['day'];
        $r_data['entry_time'] = $userDayPunchingDetails['in_time'];
        $r_data['exit_time'] = $userDayPunchingDetails['out_time'];

        $r_data['total_working'] = '';

        if (!empty($userDayPunchingDetails['total_time'])) {
            $aa = self::_secondsToTime($userDayPunchingDetails['total_time']);
            $r_data['total_working'] = $aa['h'] . 'h : ' . $aa['m'] . 'm :' . $aa['s'] . 's';
        }



        $r_error = 0;
        $return = array();
        $return['error'] = $r_error;
        $r_data['message'] = $r_message;
        $return['data'] = $r_data;

        return $return;
    }

    //--end---- user day summary

    public static function insertUserPunchTime($user_id, $timing) {
        $q = "INSERT into attendance ( user_id, timing ) VALUES ( $user_id, '$timing')";
        self::DBrunQuery($q);
        return true;
    }

    //----update in hr_data table
    public static function insertUpdateHr_data($userid, $date, $entry_time, $exit_time) {

        //d-m-Y
        $q = "SELECT * FROM hr_data WHERE user_id = '$userid' AND `date`= '$date' ";

        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);

        if (sizeof($rows) > 0) {
            //update
            $q = "UPDATE hr_data set entry_time='$entry_time', exit_time='$exit_time' WHERE user_id = '$userid' AND `date` = '$date' ";
            self::DBrunQuery($q);
        } else {
            //insert
            $userInfo = self::getUserInfo($userid);
            $emailid = $userInfo['work_email'];
            $q = "INSERT into hr_data ( user_id, email, entry_time, exit_time, `date`  ) VALUES ( '$userid', '$emailid', '$entry_time', '$exit_time', '$date' )";
            self::DBrunQuery($q);
        }
        return true;
    }

    //--start insert user in/out punchig time
    public static function insertUserInOutTimeOfDay($userid, $date, $inTime, $outTime, $reason, $isadmin = true) {

        $extra_time = 0;
        $newdate = date("d-m-y", strtotime($date));

        if ($isadmin == false) {
            $q = "select * from config where type='extra_time' ";
            $runQuery = self::DBrunQuery($q);
            $row = self::DBfetchRow($runQuery);
            $no_of_rows = self::DBnumRows($runQuery);
            if ($no_of_rows > 0) {
                $extra_time = $row['value'];
            }

            $q2 = "select * from hr_data where user_id= $userid AND date = '$newdate' ";
            $runQuery2 = self::DBrunQuery($q2);
            $row2 = self::DBfetchRow($runQuery2);
            $no_of_rows = self::DBnumRows($runQuery2);
            if ($no_of_rows > 0) {
                if (empty($row2['entry_time'])) {
                    $inTime = date("h:i A", strtotime($inTime) + ($extra_time * 60));
                }
                if (empty($row2['exit_time'])) {
                    $outTime = date("h:i A", strtotime($outTime) - ($extra_time * 60));
                }
            } else {
                $outTime = date("h:i A", strtotime($outTime) - ($extra_time * 60));
            }
        }


        //start -- first get existing time details
        $previous_entry_time = "";
        $previous_exit_time = "";
        $existingDetails = self::getUserDaySummary($userid, $date);
        if (isset($existingDetails['data'])) {
            $previous_entry_time = $existingDetails['data']['entry_time'];
            $previous_exit_time = $existingDetails['data']['exit_time'];
        }
        //end -- first get existing time details


        $r_error = 1;
        $r_message = "";
        $r_data = array();

        if ($inTime != '') {
            $inTime1 = $date . ' ' . $inTime;
            $insertInTime = date('m-d-Y h:i:sA', strtotime($inTime1));
            self::insertUserPunchTime($userid, $insertInTime);
        }
        if ($outTime != '') {
            $outTime1 = $date . ' ' . $outTime;
            $insertOutTime = date('m-d-Y h:i:sA', strtotime($outTime1));
            self::insertUserPunchTime($userid, $insertOutTime);
        }

        //new modification ofr hr_data table
        if ($inTime != '' && $outTime != '') {
            $h_date = date('d-m-Y', strtotime($date));
            self::insertUpdateHr_data($userid, $h_date, $inTime, $outTime);

            ////send  slack message to user
            $userInfo = self::getUserInfo($userid);
            $userInfo_name = $userInfo['name'];
            $slack_userChannelid = $userInfo['slack_profile']['slack_channel_id'];

            $message = "Hey $userInfo_name !!  \n Your timings is updated for date $h_date as below : \n ";
            if ($previous_entry_time != '' && $previous_entry_time != $inTime) {
                $message .= "Entry Time - From $previous_entry_time to $inTime \n ";
            } else {
                $message .= "Entry Time - $inTime \n ";
            }

            if ($previous_exit_time != '' && $previous_exit_time != $outTime) {
                $message .= "Exit Time - From $previous_exit_time to $outTime \n ";
            } else {
                $message .= "Exit Time - $outTime \n";
            }

            $message .= "Reason - $reason";

            $slackMessageStatus = self::sendSlackMessageToUser($slack_userChannelid, $message);
        }

        $r_error = 0;
        $return = array();
        $return['error'] = $r_error;
        $r_data['message'] = $r_message;
        $return['data'] = $r_data;

        return $return;
    }

    //--end insert user in/out punchig time
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

    public static function sendSlackMessageToUser($channelid, $message, $auth_array = false, $actions = false ) {
        $return = false;
        $paramMessage = $message;
        
        $message = '[{"text": "' . $message . '", "fallback": "Message Send to Employee", "color": "#36a64f" }]';

        if( $actions != false ){
            $message = '[{"text": "' . $paramMessage . '", "fallback": "Message Send to Employee", "color": "#36a64f", "actions": '.$actions.' }]';
        }

        $message = str_replace("", "%20", $message);

        $message = stripslashes($message); // to remove \ which occurs during mysqk_real_escape_string

       //  if ($auth_array != false || $channelid == "hr") {
       //     $q = "select * from roles_notification where role_Id=" . $auth_array['role_id'] . " AND notification_Id =" . $auth_array['notification_id'];
       //     $run = self::DBrunQuery($q);
       //     $no_of_row = self::DBnumRows($run);
       //     if ($no_of_row > 0 || $channelid == "hr") {
                $url = "https://slack.com/api/chat.postMessage?token=" . self::$SLACK_token . "&attachments=" . urlencode($message) . "&channel=" . $channelid;

                $html = self::getHtml($url);
                if ($html === false) {
                    echo $html;
                } else {
                    $fresult = json_decode($html, true);
                    if (is_array($fresult) && isset($fresult['ok'])) {
                        $return = true;
                    }
                }
         //   }

       // }


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
                if ( isset($sl['profile'] ) && $sl['profile']['email'] == $emailid) {
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

    public static function API_getYearHolidays($year = false) {  //API
        if ($year == false) {
            $year = date('Y', time());
        }
        $q = "SELECT * FROM holidays";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        $list = array();
        
        $type_text = self::getHolidayTypesList();

        if ($year == false) {
            $list = $rows;
        } else {
            foreach ($rows as $pp) {
                $h_date = $pp['date'];
                $h_year = date('Y', strtotime($h_date));
                foreach($type_text as $text){
                    if( $pp['type'] == $text['type'] ){
                        $pp['type_text'] = $text['text'];
                    }
                }
                if ($h_year == $year) {
                    $list[] = $pp;
                }                
            }
        }
        
        if (sizeof($list) > 0) {
            foreach ($list as $key => $v) {
                $list[$key]['month'] = date('F', strtotime($v['date']));
                $list[$key]['dayOfWeek'] = date('l', strtotime($v['date']));
            }
        }

        
        $r_error = 0;
        $r_data = array();
        $return = array();
        $return['error'] = $r_error;
        $r_data['message'] = "";
        $r_data['holidays'] = $list;
        $return['data'] = $r_data;

        return $return;
    }

    public static function manipulatingPendingTimeWhenLeaveIsApplied( $pending_id, $leavesNumDays ){
        $q = "Select * from users_previous_month_time where id = $pending_id";
        $run = self::DBrunQuery($q);
        $row = self::DBfetchRow($run);

        $newPendingHour = '00';
        $newPendingMinutes = '00';

        if( $row != false ){
            $pendingTime = $row['extra_time'];
            $pendingTimeExplode = explode(":",$pendingTime);
            $pending_hour = $pendingTimeExplode[0];
            $pending_minute = $pendingTimeExplode[1];

            if( $leavesNumDays === '0.5' ){
                $newPendingHour = ($pending_hour * 1) - 4; // less 4 hrs as half day
            }else{
                $newPendingHour = ($pending_hour * 1) - ( $leavesNumDays * 9 );
            }

            if( $newPendingHour > 0 ){
                if( $newPendingHour < 10 ){
                    $newPendingHour = '0'.$newPendingHour;
                }
            }else {
                $newPendingHour = '00';
            }
            if( $pending_minute > 0 ){
                $newPendingMinutes = $pending_minute;
            }

            if( $pending_hour == '00' ){
                $newPendingMinutes = '00';
            }
        }

        $newPendingTime = $newPendingHour.':'.$newPendingMinutes;

        // update new time pending time to db
        if( $newPendingTime != '00:00' ){
            $q1 = "UPDATE users_previous_month_time SET extra_time = '$newPendingTime'  Where id = $pending_id";
            self::DBrunQuery($q1);
            return false; // means set status_merged will be 0
        }
        return true; // means set status_merged to 1
    }

    public static function checkLeavesClashOfSameTeamMember( $userid, $from_date, $to_date ){
        $check = false;
        $team = ""; 
        $year = date('Y', strtotime($from_date));
        $month = date('m', strtotime($from_date));
        $applied_days = self::getDaysBetweenLeaves( $from_date, $to_date );
        $applied_user_info = self::getUserInfo($userid);
        $team = $applied_user_info['team'];
        $leaves = self::getLeavesForYearMonth( $year, $month );
        foreach( $leaves as $leave ){
            $userInfo = self::getUserInfo( $leave['user_Id'] );
            if( strtolower($userInfo['team']) == strtolower($team) ){
                $check_days = self::getDaysBetweenLeaves( $leave['from_date'], $leave['to_date'] );
                foreach( $applied_days['data']['days'] as $applied_day ){
                    foreach( $check_days['data']['days'] as $check_day ){
                        if( $applied_day['type'] == 'working' ){
                            if( $applied_day['full_date'] == $check_day['full_date'] ){
                                $check = true;
                            }
                        }
                    }
                }
            }             
        }
        return $check;
    }

    public static function getEmployeeRHStats( $userid, $year ) {
        $return = [];
        $rh_can_be_taken = 5;
        $rh_approved = $rh_rejected = $rh_left = $rh_compensation_used = $rh_compensation_pending = 0;
        $rh_leaves = self::getUserRHLeaves( $userid, $year );
        $rh_compensation_leaves = self::getUserRHCompensationLeaves( $userid, $year );
        $rh_approved_leaves = self::getUserApprovedRHLeaves( $userid, $year );
        $rh_list = self::getMyRHLeaves( $year );
        $rh_approved = sizeof($rh_approved_leaves);
        $rh_compensation_used = sizeof($rh_compensation_leaves);
        if( sizeof($rh_leaves) > 0 ){
            foreach( $rh_leaves as $rh_leave ){
                if( strtolower($rh_leave['status']) == 'rejected' ){
                    $rh_rejected++;
                }
            }
            if( $rh_approved < $rh_can_be_taken ){
                $total_rh_taken = $rh_approved + $rh_compensation_used;
                if( $total_rh_taken < $rh_can_be_taken ){
                    $left = $rh_can_be_taken - $total_rh_taken;
                    $rh_left =  $left;
                    if( $rh_rejected <= $left ){
                        $rh_compensation_pending = $rh_rejected;
                    } else {
                        $rh_compensation_pending = $left;
                    }
                }
            }
            

        } else {
            $rh_left = $rh_can_be_taken;
        }

        $return = [
            'error' => 0,
            'data' => [
                'rh_approved' => $rh_approved,
                'rh_rejected' => $rh_rejected,
                'rh_left' => $rh_left,
                'rh_compensation_used' => $rh_compensation_used,
                'rh_compensation_pending' => $rh_compensation_pending
            ]
        ];
        
        return $return;
    }

    public static function API_getEmployeeRHStats( $userid, $year = false ) {
        $year = $year ? $year : date('Y');
        $stats = self::getEmployeeRHStats($userid, $year);
        return $stats;
    }

    public static function getQuarterByMonth( $month = false ) {
        $month = $month ? $month : date('m');
        $current_quarter = false;
        $quarters = [
            1 => [ 1, 2, 3 ],
            2 => [ 4, 5, 6 ],
            3 => [ 7, 8, 9 ],
            4 => [ 10, 11, 12 ]
        ];        
        foreach( $quarters as $key => $quarter ){
            if( in_array( $month, $quarter ) ){
                $current_quarter['quarter'] = $key;
                $current_quarter['months'] = $quarter;
                break;
            }
        }

        return $current_quarter;
    }

    public static function checkRHQuarterWise( $userid, $from_date ) {
        $check = false;
        $return = [];
        $no_of_quaters = 4;
        $rh_can_be_taken = 5;
        $rh_can_be_taken_per_quarter = 1;

        $user = self::getUserInfo($userid);    

        if( $user['training_completion_date'] != '0000-00-00' && $user['training_completion_date'] != '1970-01-01' ) {
            
            $from_date_year = date('Y', strtotime($from_date));
            $from_date_month = date('m', strtotime($from_date));
            $current_date = date('Y-m-d');
            $current_year = date('Y');
            $current_month = date('m');
            $current_quarter = self::getQuarterByMonth();
            $confirm_year = date('Y', strtotime($user['training_completion_date']));
            $confirm_month = date('m', strtotime($user['training_completion_date']));
            $confirm_quarter = self::getQuarterByMonth($confirm_month);
            $from_date_quarter = self::getQuarterByMonth( $from_date_month );
            $rh_list = array_map( function($iter){ return $iter['raw_date']; }, self::getMyRHLeaves($current_year) );
            $rh_leaves = array_map( function($iter){ return $iter['from_date']; }, self::getUserRHLeaves($userid, $current_year) );
            $rh_approved = array_map( function($iter){ return $iter['from_date']; }, self::getUserApprovedRHLeaves($userid, $current_year) );
            $rh_compensated = array_map( function($iter){ return $iter['from_date']; }, self::getUserRHCompensationLeaves($userid, $current_year) );
            $total_rh_taken = sizeof($rh_approved) + sizeof($rh_compensated);
            
            $two_rh_quarter = false;
            $rh_taken_per_quarter = [];
            foreach( $rh_approved as $rh_approve ){
                $quarter = self::getQuarterByMonth( date('m', strtotime($rh_approve)) );
                if( isset( $rh_taken_per_quarter[$quarter['quarter']] ) ){
                    $rh_taken_per_quarter[$quarter['quarter']]++;
                } else {
                    $rh_taken_per_quarter[$quarter['quarter']] = 1;
                }
            }        
            foreach( $rh_taken_per_quarter as $quarter => $rh_taken ){
                if( $rh_taken_per_quarter[$quarter] >= 2 ){
                    $two_rh_quarter = true;
                    break;
                }
            }
            
            if( $from_date_year < $current_year || strtotime($from_date) < strtotime($current_date) ){
                $message = 'You cannot apply previous RH.';                

            } else {
                
                if( in_array( $from_date, $rh_list ) ){
                    if( $confirm_year != $current_year ){ 
                        if( $total_rh_taken >= $rh_can_be_taken ){
                            $message = 'You have reached the RH quota. You are not eligible for other RH this year.';
                            
                        } else {
                            if( $from_date_quarter['quarter'] >= $current_quarter['quarter'] ){
                                if( array_key_exists( $from_date_quarter['quarter'], $rh_taken_per_quarter ) ){
                                    if( $rh_taken_per_quarter[$from_date_quarter['quarter']] > 0 ) {
                                        if( $two_rh_quarter ){
                                            $message = 'You are not allowed take 2nd RH this quarter as you have taken 2 RH already in single quarter.';
                                        } else {
                                            $check = true;
                                        }
                                    }
                                } else {
                                    $check = true;
                                }
                            } else {
                                $message = 'You cannot apply previous quarter RH.';
                            }
                        }
                    } else {

                        $remaining_quarters = $no_of_quaters - $confirm_quarter['quarter'];
                        $eligible_for_confirm_quarter_rh = false;
                        if( $confirm_quarter['months'][0] == $confirm_month ){
                            $eligible_for_confirm_quarter_rh = true;
                        }
                        if( $eligible_for_confirm_quarter_rh ){
                            $rh_can_be_taken = $remaining_quarters + 2;
                        } else {
                            $rh_can_be_taken = $remaining_quarters + 1;
                        }

                        if( $total_rh_taken >= $rh_can_be_taken ){
                            $message = 'You have reached the RH quota. You are not eligible for other RH this year.';
                            
                        } else {
                            if( $from_date_quarter['quarter'] >= $current_quarter['quarter'] ){
                                if( array_key_exists( $from_date_quarter['quarter'], $rh_taken_per_quarter ) ){
                                    if( $rh_taken_per_quarter[$from_date_quarter['quarter']] > 0 ) {
                                        if( $two_rh_quarter ){
                                            $message = 'You are not allowed take 2nd RH this quarter as you have taken 2 RH already in single quarter.';
                                        } else {
                                            $check = true;
                                        }
                                    }
                                } else {
                                    $check = true;
                                }
                            } else {
                                $message = 'You cannot apply previous quarter RH.';
                            }
                        }
                    }
                } else {
                    $message = 'The date is not yet added in the RH list.';
                } 
            }

        } else {
            $message = 'You are not a confirm employee so you are not eligible for RH';
        }

        $return['check'] = $check;
        $return['message'] = $message;

        return $return;
    }

    public static function applyLeave($userid, $from_date, $to_date, $no_of_days, $reason, $day_status, $leave_type, $late_reason, $pending_id = false) {
        //date format = Y-m-d
        $db = self::getInstance();
        $mysqli = $db->getConnection();

        if( strtolower($leave_type) == 'restricted' ){
            $rh_check = self::checkRHQuarterWise($userid, $from_date);
            if( !$rh_check['check'] ){
                return [
                    'error' => 1,
                    'data' => [ 'message' => $rh_check['message'] ]
                ];
            }
        }

        $alert_message = "";
        $check = self::checkLeavesClashOfSameTeamMember( $userid, $from_date, $to_date );
        if( $check ){
            $alert_message = "Another team member already has applied during this period so leave approve will depend on project.";
        }
        
        $applied_date = date('Y-m-d');
        $reason = self::DBescapeString($reason);
        $q = "INSERT into leaves ( user_Id, from_date, to_date, no_of_days, reason, status, applied_on, day_status,leave_type,late_reason ) VALUES ( $userid, '$from_date', '$to_date', $no_of_days, '$reason', 'Pending', '$applied_date', '$day_status','$leave_type','$late_reason' )";

        $r_error = 0;
        $r_message = "";
        $leave_id = "";

        try {
            self::DBrunQuery($q);
            $success = true;
            $r_message = "Leave applied.";
            $leave_id = mysqli_insert_id($mysqli);
        } catch (Exception $e) {
            $r_error = 1;
            $r_message = "Error in applying leave.";
        }

        if ($r_error == 0) {
            if ($pending_id != false) {
                if ( self::manipulatingPendingTimeWhenLeaveIsApplied( $pending_id, $no_of_days ) ) {
                    $q = "Select * from users_previous_month_time where id = $pending_id";
                    $run = self::DBrunQuery($q);
                    $row = self::DBfetchRow($run);
                    $oldStatus = $row['status'];

                    $q1 = "UPDATE users_previous_month_time SET status = '$oldStatus - Leave applied for previous month pending time', status_merged = 1  Where id = $pending_id";
                    self::DBrunQuery($q1);
                }

            }
            ////send  slack message to user && HR
            $userInfo = self::getUserInfo($userid);
            $userInfo_name = $userInfo['name'];
            $slack_userChannelid = $userInfo['slack_profile']['slack_channel_id'];

            if ($day_status == "2") {
                $message_to_user = "Hi $userInfo_name !!  \n You just had applied for second half days of leave from $from_date to $to_date. \n Reason mentioned : $reason  \n $alert_message";
                $message_to_hr = "Hi HR !!  \n $userInfo_name just had applied for second half days of leave from $from_date to $to_date. \n Reason mentioned : $reason \n $alert_message";
            } elseif ($day_status == "1") {
                $message_to_user = "Hi $userInfo_name !!  \n You just had applied for first half days of leave from $from_date to $to_date. \n Reason mentioned : $reason \n $alert_message";
                $message_to_hr = "Hi HR !!  \n $userInfo_name just had applied for first half days of leave from $from_date to $to_date. \n Reason mentioned : $reason \n $alert_message";
            } else {
                $message_to_user = "Hi $userInfo_name !!  \n You just had applied for $no_of_days days of leave from $from_date to $to_date. \n Reason mentioned : $reason \n $alert_message";
                $message_to_hr = "Hi HR !!  \n $userInfo_name just had applied for $no_of_days days of leave from $from_date to $to_date. \n Reason mentioned : $reason \n $alert_message";
            }

            if ($late_reason != "") {
                $message_to_user.="\nLate Reason: $late_reason";
                $message_to_hr.="\nLate Reason: $late_reason";
            }

            $slackMessageStatus = self::sendSlackMessageToUser($slack_userChannelid, $message_to_user);
            $slackMessageStatus = self::sendSlackMessageToUser("hr", $message_to_hr);
        }
        $return = array();
        $r_data = array();
        $return['error'] = $r_error;
        $r_data['message'] = $r_message;
        $r_data['leave_id'] = $leave_id;
        $return['data'] = $r_data;
        return $return;
    }

    public static function getUsersLeaves($userid) {
        $list = array();
        $q = "SELECT * FROM leaves Where user_Id = $userid order by id DESC";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        return $rows;
    }

    public static function getMyLeaves($userid) { //api call
        $userLeaves = self::getUsersLeaves($userid);

        if (sizeof($userLeaves) > 0) {
            foreach ($userLeaves as $k => $v) {
                $userLeaves[$k]['from_date'] = date('d-F-Y', strtotime($v['from_date']));
                $userLeaves[$k]['to_date'] = date('d-F-Y', strtotime($v['to_date']));
                $userLeaves[$k]['applied_on'] = date('d-F-Y', strtotime($v['applied_on']));
            }
        }

        $return = array();
        $r_data = array();
        $return['error'] = 0;
        $r_data['message'] = '';
        $r_data['leaves'] = $userLeaves;
        $return['data'] = $r_data;

        return $return;
    }

    public static function getAllLeaves() {     //api call
        //$q = "SELECT users.*,user_profile.* FROM users LEFT JOIN user_profile ON users.id = user_profile.user_Id where users.status = 'Enabled' ";
        $q = "SELECT users.*,leaves.* FROM leaves LEFT JOIN users ON users.id = leaves.user_Id where users.status = 'Enabled' order by leaves.id DESC ";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);

        $pendingLeaves = array();

        if (sizeof($rows) > 0) {
            foreach ($rows as $k => $p) {
                $p_id = $p['id'];
                //$userInfo = self::getUserInfo( $p['user_Id'] );
                //$rows[$k]['user_complete_info'];
                unset($rows[$k]['password']);

                ///
                if (trim(strtolower($p['status'])) == 'pending') {

                    $lastLeaves = self::getUsersLeaves($p['user_Id']);
                    if (sizeof($lastLeaves) > 0) {
                        foreach ($lastLeaves as $lk => $lp) {
                            if ($lp['id'] == $p_id) {
                                unset($lastLeaves[$lk]);
                            }
                        }
                        if (sizeof($lastLeaves) > 0) {
                            foreach ($lastLeaves as $kl => $ll) {
                                $lastLeaves[$kl]['from_date'] = date('d-F-Y', strtotime($ll['from_date']));
                                $lastLeaves[$kl]['to_date'] = date('d-F-Y', strtotime($ll['to_date']));
                                $lastLeaves[$kl]['applied_on'] = date('d-F-Y', strtotime($ll['applied_on']));
                            }
                        }
                        $lastLeaves = array_slice($lastLeaves, 0, 5);
                        $p['last_applied_leaves'] = $lastLeaves;
                    }

                    $pendingLeaves[] = $p;
                    unset($rows[$k]);
                } else {
                    $row[$k]['last_applied_leaves'] = array();
                }
            }
        }
        $newRows = $rows;

        if (sizeof($pendingLeaves > 0)) {
            $newRows = array_merge($pendingLeaves, $rows);
        }

        // date view change
        if (sizeof($newRows) > 0) {
            foreach ($newRows as $k => $v) {
                $newRows[$k]['from_date'] = date('d-F', strtotime($v['from_date']));
                $newRows[$k]['to_date'] = date('d-F', strtotime($v['to_date']));
                $newRows[$k]['applied_on'] = date('d-F-Y', strtotime($v['applied_on']));
            }
        }


        //----
        if (sizeof($newRows) > 0) {
            $enabledUsersList = self::getEnabledUsersList();
            foreach ($newRows as $k => $p) {
                $p_userid = $p['user_Id'];
                foreach ($enabledUsersList as $ev) {
                    if ($p_userid == $ev['user_Id']) {
                        $newRows[$k]['user_profile_name'] = $ev['name'];
                        $newRows[$k]['user_profile_jobtitle'] = $ev['jobtitle'];
                        $newRows[$k]['user_profile_image'] = $ev['slack_profile']['image_192'];
                        break;
                    }
                }
            }
        }

        $return = array();
        $r_data = array();
        $return['error'] = 0;
        $r_data['message'] = '';
        $r_data['leaves'] = $newRows;
        $return['data'] = $r_data;

        return $return;
    }

    public static function getLeaveDetails($leaveid) {
        $q = "SELECT users.*,leaves.* FROM leaves LEFT JOIN users ON users.id = leaves.user_Id where leaves.id = $leaveid ";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRow($runQuery);
        return $row;
    }

    public static function changeLeaveStatus($leaveid, $newstatus) {
        $q = "UPDATE leaves set status='$newstatus' WHERE id = $leaveid ";
        self::DBrunQuery($q);
        return true;
    }

    public static function updateLeaveStatus($leaveid, $newstatus, $messagetouser) { //api call
        $leaveDetails = self::getLeaveDetails($leaveid);

        $r_error = 0;
        $r_message = "";

        if (is_array($leaveDetails)) {
            $old_status = $leaveDetails['status'];

            $from_date = $leaveDetails['from_date'];
            $to_date = $leaveDetails['to_date'];
            $no_of_days = $leaveDetails['no_of_days'];
            $applied_on = $leaveDetails['applied_on'];
            $reason = $leaveDetails['reason'];

            $changeLeaveStatus = self::changeLeaveStatus($leaveid, $newstatus);
            if( strtolower($leaveDetails['leave_type']) == 'restricted' ){
                if( $changeLeaveStatus ){
                    $updatedLeaveDetails = self::getLeaveDetails($leaveid);
                    if( strtolower($updatedLeaveDetails['status']) == 'approved' ){
                        $entry_time = self::DEFAULT_ENTRY_TIME;
                        $exit_time = self::DEFAULT_EXIT_TIME;
                        self::insertUserInOutTimeOfDay($leaveDetails['user_Id'], $from_date, $entry_time, $exit_time, $reason);
                    }
                }
            }            

            $userInfo = self::getUserInfo($leaveDetails['user_Id']);
            $userInfo_name = $userInfo['name'];
            $slack_userChannelid = $userInfo['slack_profile']['slack_channel_id'];

            $message_to_user = "Hi $userInfo_name !!  \n Your leave has been $newstatus. \n \n Leave Details : \n";
            $message_to_user .= " From : $from_date \n To : $to_date \n No. of days : $no_of_days \n Applied On : $applied_on \n Reason : $reason";

            $message_to_hr = "Hi HR !!  \n  $userInfo_name leave has been $newstatus. \n \n Leave Details : \n";
            $message_to_hr .= " From : $from_date \n To : $to_date \n No. of days : $no_of_days \n Applied On : $applied_on \n Reason : $reason";

            if ($messagetouser != '') {
                $message_to_user .= "\n Message from Admin : $messagetouser";
                $message_to_hr .= "\n Message from Admin : $messagetouser";
            }

            $slackMessageStatus = self::sendSlackMessageToUser($slack_userChannelid, $message_to_user);
            $slackMessageStatus = self::sendSlackMessageToUser("hr", $message_to_hr);

            $r_message = "Leave status changes from $old_status to $newstatus";
        } else {
            $r_message = "No such leave found";
            $r_error = 1;
        }

        $return = array();
        $r_data = array();
        $return['error'] = 0;
        $r_data['message'] = $r_message;
        $return['data'] = $r_data;

        return $return;
    }

    public static function revertLeaveStatus($leaveid){

        $return = array();
        $r_error = 0;
        $r_message = "";
        $leaveDetails = self::getLeaveDetails($leaveid);

        if(count($leaveDetails) > 0){

            if ( $leaveDetails['status'] == "Approved" || $leaveDetails['status'] == "Rejected" ) {
                $newstatus = "Pending";
                self::changeLeaveStatus($leaveid, $newstatus);                
                $r_message = "Leave Status Reverted Successfully.";
                $r_data['status'] = true;

            } else {
                $r_error = 1;
                $r_message = "Status is Pending yet.";
            }

        } else {            
            $r_message = "No such leave found";
        }
        
        $return = [
            'error' => $r_error,
            'message' => $r_message
        ];

        return $return;
    }

    public static function addExtraLeaveDay($leaveid, $extra_day) {
        $r_error = 0;
        $r_message = "";

        $q = "UPDATE leaves set extra_day='$extra_day' WHERE id = $leaveid ";
        try {
            self::DBrunQuery($q);
            $r_error = 0;
            $r_message = "Extra day added";
        } catch (Exception $e) {
            $r_error = 1;
            $r_message = "Some error occured";
        }
        $return = array();
        $return['error'] = $r_error;
        $return['message'] = $r_message;
        return $return;
    }

    public static function addHrComment($leaveid, $hr_comment, $hr_approve) {

        $leaveDetails = self::getLeaveDetails($leaveid);
        $r_error = 0;
        $r_message = "";
        $slkmsg = "";
        $r_data = array();
        if (is_array($leaveDetails)) {
            $old_status = $leaveDetails['status'];

            $from_date = $leaveDetails['from_date'];
            $to_date = $leaveDetails['to_date'];
            $no_of_days = $leaveDetails['no_of_days'];
            $applied_on = $leaveDetails['applied_on'];
            $reason = $leaveDetails['reason'];
            $username = $leaveDetails['username'];
            $userInfo = self::getUserInfo($leaveDetails['user_Id']);
            $userInfo_name = $userInfo['name'];
            if (!empty($hr_comment)) {
                $q = "UPDATE leaves set hr_comment='$hr_comment' WHERE id = $leaveid ";
                $r_message = "Hr comment updated";
                $slkmsg = "On applied leave of $userInfo_name from $from_date to $to_date \n Hr has commented \n $hr_comment";
            }
            if (!empty($hr_approve)) {
                $q = "UPDATE leaves set hr_approved='$hr_approve' WHERE id = $leaveid ";
                $r_message = "Hr approved leave ";
                $slkmsg = "Hr has approved the applied leave of $userInfo_name from $from_date to $to_date";
                if ($hr_approve == "2") {
                    $r_message = "Hr not approved leave ";
                    $slkmsg = "Hr has not approved the applied leave of $userInfo_name from $from_date to $to_date";
                }
            }


            try {
                self::DBrunQuery($q);
                $r_error = 0;
                if (!empty($slkmsg)) {
                    $slackMessageStatus = self::sendSlackMessageToUser("D1HUPANG6", $slkmsg);
                }
            } catch (Exception $e) {
                $r_error = 1;
                $r_message = "Some error occured";
            }
        } else {
            $r_message = "No such leave found";
            $r_error = 1;
        }

        $r_data['message'] = $r_message;
        $r_data['leaveid'] = $leaveid;
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    public static function leaveDocRequest($leaveid, $doc_request, $comment) { //api call
        $leaveDetails = self::getLeaveDetails($leaveid);

        $r_error = 0;
        $r_message = "";
        $message_to_user = "";
        $r_data = array();
        if (is_array($leaveDetails)) {
            $old_status = $leaveDetails['status'];

            $from_date = $leaveDetails['from_date'];
            $to_date = $leaveDetails['to_date'];
            $no_of_days = $leaveDetails['no_of_days'];
            $applied_on = $leaveDetails['applied_on'];
            $reason = $leaveDetails['reason'];

            $userInfo = self::getUserInfo($leaveDetails['user_Id']);
            $userInfo_name = $userInfo['name'];
            $slack_userChannelid = $userInfo['slack_profile']['slack_channel_id'];

            if (!empty($doc_request)) {
                $q = "UPDATE leaves set doc_require= 1 WHERE id = $leaveid ";
                self::DBrunQuery($q);

                $message_to_user = "Hi $userInfo_name !!  \n You are requested to submit doc proof for your applied leave \n  Leave Details : \n";
                $message_to_user .= " From : $from_date \n To : $to_date \n No. of days : $no_of_days \n Applied On : $applied_on \n Reason : $reason";
                $r_message = 'Admin request for leave doc send';
            }
            if (!empty($comment)) {
                $q = "UPDATE leaves set comment= '$comment' WHERE id = $leaveid ";
                self::DBrunQuery($q);
                $message_to_user = "Hi $userInfo_name !!  \n Admin has commented \n '$comment' \n on your applied leave From : $from_date  To : $to_date \n \n";
                $r_message = 'Admin commented on employee leave saved';
            }

            if ($message_to_user != '') {
                $slackMessageStatus = self::sendSlackMessageToUser($slack_userChannelid, $message_to_user);
            }
        } else {
            $r_message = "No such leave found";
            $r_error = 1;
        }

        $return = array();
        $r_data = array();
        $r_data['message'] = $r_message;
        $r_data['leaveid'] = $leaveid;
        $return['error'] = $r_error;
        $return['data'] = $r_data;

        return $return;
    }

    public static function getDaysBetweenLeaves($startDate, $endDate) { // api calls
        $allDates = self::_getDatesBetweenTwoDates($startDate, $endDate);

        //extract year and month of b/w dates
        $yearMonth = array();

        foreach ($allDates as $d) {
            $m = date('m', strtotime($d));
            $y = date('Y', strtotime($d));
            $check_key = $y . '_' . $m;
            if (!array_key_exists($check_key, $yearMonth)) {
                $row = array(
                    'year' => $y,
                    'month' => $m,
                );
                $yearMonth[$check_key] = $row;
            }
        }
        //--all holidays between dates
        $ALL_HOLIDAYS = array();
        $ALL_WEEKENDS = array();

        foreach ($yearMonth as $v) {
            $my_holidays = self::getHolidaysOfMonth($v['year'], $v['month']);
            $my_weekends = self::getWeekendsOfMonth($v['year'], $v['month']);

            $ALL_HOLIDAYS = array_merge($ALL_HOLIDAYS, $my_holidays);
            $ALL_WEEKENDS = array_merge($ALL_WEEKENDS, $my_weekends);
        }
        $finalDates = array();
        foreach ($allDates as $ad) {
            $row = array(
                'type' => 'working',
                'sub_type' => '',
                'sub_sub_type' => '',
                'full_date' => $ad
            );
            $finalDates[] = $row;
        }

        if (sizeof($finalDates) > 0 && sizeof($ALL_WEEKENDS) > 0) {
            foreach ($finalDates as $key => $ad) {
                foreach ($ALL_WEEKENDS as $aw) {
                    if ($ad['full_date'] == $aw['full_date']) {
                        $row = array(
                            'type' => 'non_working',
                            'sub_type' => 'weekend',
                            'sub_sub_type' => '',
                            'date' => $ad['full_date']
                        );
                        $finalDates[$key] = $row;
                        break;
                    }
                }
            }
        }
        if (sizeof($finalDates) > 0 && sizeof($ALL_HOLIDAYS) > 0) {
            foreach ($finalDates as $key => $ad) {
                foreach ($ALL_HOLIDAYS as $aw) {
                    if ($ad['full_date'] == $aw['full_date']) {
                        $row = array(
                            'type' => 'non_working',
                            'sub_type' => 'holiday',
                            'sub_sub_type' => $aw['name'],
                            'date' => $ad['full_date']
                        );
                        $finalDates[$key] = $row;
                        break;
                    }
                }
            }
        }

        //-----------------
        $res_working_days = 0;
        $res_holidays = 0;
        $res_weekends = 0;

        foreach ($finalDates as $f) {
            if ($f['type'] == 'working') {
                $res_working_days++;
            } else if ($f['type'] == 'non_working') {
                if ($f['sub_type'] == 'holiday') {
                    $res_holidays++;
                } else if ($f['sub_type'] == 'weekend') {
                    $res_weekends++;
                }
            }
        }

        $r_data = array();
        $r_data['start_date'] = $startDate;
        $r_data['end_date'] = $endDate;
        $r_data['working_days'] = $res_working_days;
        $r_data['holidays'] = $res_holidays;
        $r_data['weekends'] = $res_weekends;
        $r_data['days'] = $finalDates;


        $return = array();
        $return['error'] = 0;
        $r_data['message'] = '';
        $return['data'] = $r_data;



        return $return;
    }

    ////

    public static function getUserMangedHours($userid) {
        $q = "SELECT * FROM user_working_hours WHERE user_Id = $userid order by id DESC";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        return $rows;
    }

    public static function geManagedUserWorkingHours($userid) { // api call
        $allWorkingHours = self::getUserMangedHours($userid);

        $finalData = array();
        if (is_array($allWorkingHours) && sizeof($allWorkingHours) > 0) {
            $finalData = $allWorkingHours;
        }

        $return = array();
        $return['error'] = 0;
        $r_data = array();
        $r_data['message'] = '';
        $r_data['list'] = $finalData;
        $userInfo = self::getUserInfo($userid);
        unset($userInfo['password']);
        $r_data['userInfo'] = $userInfo;
        $return['data'] = $r_data;

        return $return;
    }

    public static function insertUserWorkingHours($userid, $date, $working_hours, $reason) { //api call
        $q = "INSERT INTO user_working_hours ( user_Id, `date`, working_hours, reason ) VALUES ( $userid, '$date', '$working_hours', '$reason') ";
        self::DBrunQuery($q);
        return true;
    }

    public static function addUserWorkingHours($userid, $date, $working_hours, $reason, $pending_id = false) { //api call
        $insert = self::insertUserWorkingHours($userid, $date, $working_hours, $reason);
        $userInfo = self::getUserInfo($userid);
        $userInfo_name = $userInfo['name'];
        $role_id = $userInfo['role_id'];
        $slack_userChannelid = $userInfo['slack_profile']['slack_channel_id'];

        $beautyDate = date('d-M-Y', strtotime($date));

        $message_to_user = "Hi $userInfo_name !!  \n Your working hours is updated for date $beautyDate to $working_hours Hours \n Reason - $reason ";
        $message_to_hr = "Hi HR !!  \n $userInfo_name working hours is updated for date $beautyDate to $working_hours Hours \n Reason - $reason ";

        $slackMessageStatus = self::sendSlackMessageToUser($slack_userChannelid, $message_to_user);
        $slackMessageStatus = self::sendSlackMessageToUser("hr", $message_to_hr);

        if ($pending_id != false) {
            $q = "Select * from users_previous_month_time where id = $pending_id";
            $run = self::DBrunQuery($q);
            $row = self::DBfetchRow($run);
            $oldStatus = $row['status'];
            $q = "UPDATE users_previous_month_time SET status = '$oldStatus - Time added to user working hours', status_merged = 1  Where id = $pending_id";
            self::DBrunQuery($q);
        }

        $r_data = array();
        $return = array();
        $return['error'] = 0;
        $r_data['message'] = 'Successfully added';
        $return['data'] = $r_data;

        return $return;
    }

    public static function getAllUsersPendingLeavesSummary($year, $month) { // api call
        $r_error = 1;
        $r_message = "";
        $r_data = array();

        $usersAttendance = array();

        $enabledUsersList = self::getEnabledUsersList();
        foreach ($enabledUsersList as $u) {
            $userid = $u['user_Id'];
            $username = $u['username'];
            if ($username == 'admin' || $userid == '' || $username == '') {
                continue;
            }

            // if( $userid != 313 && $userid != 288 && $userid != 343 ){
            //     continue;
            // }

            $user_month_attendance = self::getUserMonthAttendaceComplete($userid, $year, $month);

            $user_month_attendance = $user_month_attendance['data'];

            //---final leaves and missing punching days
            $raw = $user_month_attendance['attendance'];
            $finalAttendance = array();
            foreach ($raw as $pp) {
                $pp['display_date'] = date('d-M-Y', strtotime($pp['full_date']));
                if ($pp['day_type'] == 'WORKING_DAY') {
                    if ($pp['in_time'] == '' || $pp['out_time'] == '') {
                        $finalAttendance[] = $pp;
                    }
                } else if ($pp['day_type'] == 'LEAVE_DAY' || $pp['day_type'] == 'HALF_DAY') {
                    $finalAttendance[] = $pp;
                }
            }

            //---final leaves and missing punching days

            if (sizeof($finalAttendance) > 0) {

                $u_data = array();
                $u_data['name'] = $u['name'];
                $u_data['profileImage'] = '';
                $u_data['jobtitle'] = $u['jobtitle'];
                $u_data['userid'] = $userid;
                $u_data['year'] = $user_month_attendance['year'];
                $u_data['month'] = $user_month_attendance['month'];
                $u_data['monthName'] = $user_month_attendance['monthName'];
                $u_data['monthSummary'] = $user_month_attendance['monthSummary'];
                $u_data['nextMonth'] = $user_month_attendance['nextMonth'];
                $u_data['previousMonth'] = $user_month_attendance['previousMonth'];
                $u_data['attendance'] = $finalAttendance;
                $usersAttendance[] = $u_data;
            }
        }
        //----------
        $nextMonth = self::_getNextMonth($year, $month);
        $previousMonth = self::_getPreviousMonth($year, $month);
        $currentMonth = self::_getCurrentMonth($year, $month);
        //----------

        $r_data['year'] = $year;
        $r_data['month'] = $month;
        $r_data['monthName'] = $currentMonth['monthName'];
        $r_data['nextMonth'] = $nextMonth;
        $r_data['previousMonth'] = $previousMonth;
        $r_data['leavesSummary'] = $usersAttendance;

        $r_error = 0;
        $return = array();
        $return['error'] = $r_error;
        $r_data['message'] = $r_message;
        $return['data'] = $r_data;

        return $return;
    }

    public static function updateGooglepaySlipDriveAccessToken($google_access_token) { //api call
        $r_error = 1;
        $r_message = "";
        $r_data = array();

        $q = "select * from config where type='google_payslip_drive_token' ";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRow($runQuery);

        if ($row == false) {
            $q = "INSERT INTO config ( type, value ) VALUES ( 'google_payslip_drive_token', '$google_access_token' ) ";
            self::DBrunQuery($q);
            $r_error = 0;
            $r_message = "Insert Successfully!!";
        } else {
            $q = "UPDATE config set value='$google_access_token' WHERE type = 'google_payslip_drive_token' ";
            self::DBrunQuery($q);
            $r_error = 0;
            $r_message = "Update Successfully!!";
        }
        $return = array();
        $return['error'] = $r_error;
        $r_data['message'] = $r_message;
        $return['data'] = $r_data;

        return $return;
    }

     // function to send email
     public static function sendEmail($data) {
        
        $r_error = 1;
        $r_message = "";
        $r_data = array();        

        $q = "SELECT * from config where type='email_detail'";
        $r = self::DBrunQuery($q);
        $row = self::DBfetchRow($r);

        // convert this string into associative array
        parse_str($row['value'], $detail);
        include_once "phpmailer/PHPMailerAutoload.php";
        
        
        if (!empty($data['email'])) {
            
            
            foreach ($data as $var) {
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




    //New Employee Welcome Email

    public function sendWelcomeMail($userID) {
        $userInfo = self::getUserInfo($userID);

        // Fetching New Employee Welcome Email template
        $q = "SELECT * FROM email_templates where name='New Employee Welcome Email'";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        if(empty($row)) {
           $r_message = "Warning - New Employee Welcome Email template not found!!";
           return $r_message; 
        }
        $mail_body = $row[0]['body'];
        $mail_subject = $row[0]['subject'];       
        $work_email = $userInfo['work_email'];
        $replace_to = array();
        $replace_to[0] = $userInfo['name'];
        $replace_to[1] = $userInfo['dateofjoining'];
        $replace_from = array('#employee_name','#joining_date');

        $mail_body = str_replace($replace_from,$replace_to,$mail_body);

        // Fetching value of Variables in Template
        $q2 = 'Select * from template_variables';
        $runQuery2 = self::DBrunQuery($q2);
        $row2 = self::DBfetchRows($runQuery2);
        foreach ($row2 as $s) {
            $mail_body = str_replace($s['name'], $s['value'], $mail_body);
        }

        $data = array();
        $data['email']['subject'] = $mail_subject;
        $data['email']['name'] = $userInfo['name'];
        $data['email']['body'] = $mail_body;
        $data['email']['email_id'] = $work_email;
        self::sendEmail($data);
        $r_message = "Welcome mail sent!!";
        return $r_message;
     }

    //end New Employee Welcome Email

    public static function sendBirthdayWishEmail($userID){
        $userInfo = self::getUserInfo($userID);

        // Fetching New Employee Welcome Email template
        $q = "SELECT * FROM email_templates where name='Birthday Wish'";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        if(empty($row)) {
           $r_message = "Warning - Birthday Wish template not found!!";
           return $r_message; 
        }
        $mail_body = $row[0]['body'];
        $mail_subject = $row[0]['subject'];       
        $work_email = $userInfo['work_email'];
        $replace_to = array();
        $replace_to[0] = $userInfo['name'];
        $replace_from = array('#employee_name');

        $mail_body = str_replace($replace_from,$replace_to,$mail_body);
        
        // Fetching value of Variables in Template
        $q2 = 'Select * from template_variables';
        $runQuery2 = self::DBrunQuery($q2);
        $row2 = self::DBfetchRows($runQuery2);
        foreach ($row2 as $s) {
            $mail_body = str_replace($s['name'], $s['value'], $mail_body);
        }
        
        $data = array();
        $data['email']['subject'] = $mail_subject;
        $data['email']['name'] = $userInfo['name'];
        $data['email']['body'] = $mail_body;
        $data['email']['email_id'] = $work_email;
        self::sendEmail($data);
        $r_message = "Birthday wish sent!!";
        return $r_message;
    }

    //Leave of New Employee

    public static function applyNewEmployeeLeaves($userID) {
        $r_message3 = "";
        $userInfo = self::getUserInfo($userID);
       $date_of_joining = $userInfo['dateofjoining'];        
        $year = date('Y', strtotime($date_of_joining));
        $month= date('m', strtotime($date_of_joining));
        $monthSummary = self::getGenericMonthSummary($year, $month);
        $from_date = $to_date = "";
        foreach($monthSummary as $var) {
            
                if($var['day_type'] == 'WORKING_DAY') {
                    $from_date = $var['full_date'];
                    break;
                }
            }
           
        if($date_of_joining!=$from_date) {
            
            foreach($monthSummary as $var) {        
                if($var['day_type'] == 'WORKING_DAY') {
                    if($var['full_date'] == $date_of_joining) {
                        break;
                    }
                    $to_date = $var['full_date'];
                }
            }            
            $data = self::getDaysBetweenLeaves($from_date, $to_date);
            
            $no_of_days = $data['data']['working_days'];
            $reason = "Joining day was ".$date_of_joining;    
            $day_status = "";
            $leave_type = "Casual Leave";
            $late_reason = "";
            $res = HR::applyLeave($userID, $from_date, $to_date, $no_of_days, $reason, $day_status, $leave_type, $late_reason);
            if($res['error']==0) {
                $leavedetails = self::getMyLeaves($userID);
                $leaveid = $leavedetails['data']['leaves']['0']['id'];
                $newstatus = "Approved";
                $messagetouser = "Your Leaves has been approved!";
                self::updateLeaveStatus($leaveid, $newstatus, $messagetouser);
                $r_message3 = "Leave Applied!!"; 
            }
            else {
                $r_message3 = "Leave Not Applied!!";
            }
        }
        return $r_message3;
    }

    // end Leave of New Employee

    public static function addNewSalary($userID, $PARAMS){
        
        $db = self::getInstance();
        $mysqli = $db->getConnection();

        $token = $PARAMS['token'];
        $loggedUserInfo = JWT::decode($token, self::JWT_SECRET_KEY);        
        $update_by = $loggedUserInfo->name;
        
        $ins_salary = array(
            'user_Id' => $userID,
            'total_salary' => $PARAMS['total_salary'],
            'last_updated_on' => date("Y-m-d"),
            'updated_by' => $update_by,
            'leaves_allocated' => $PARAMS['leave'],
            'applicable_from' => $PARAMS['applicable_from'],
            'applicable_till' => $PARAMS['applicable_till']
        );

        self::DBinsertQuery('salary', $ins_salary);
        $salary_id = mysqli_insert_id($mysqli);        

        $ins_salary_details = array(
            'Special_Allowance' => $PARAMS['special_allowance'],
            'Medical_Allowance' => $PARAMS['medical_allowance'],
            'Conveyance' => $PARAMS['conveyance'],
            'HRA' => $PARAMS['hra'],
            'Basic' => $PARAMS['basic'],
            'Arrears' => $PARAMS['arrear'],
            'TDS' => $PARAMS['tds'],
            'Misc_Deductions' => $PARAMS['misc_deduction'],
            'Advance' => $PARAMS['advance'],
            'Loan' => $PARAMS['loan'],
            'EPF' => $PARAMS['epf']
        );

        $type = 1;
        foreach ($ins_salary_details as $key => $val) {
            // change value of type on and after array key TDS    
            if ($key == 'TDS') {
                $type = 2;
            }
            $query = "Insert Into salary_details (`salary_id`, `key`, `value`,`type`) Value ($salary_id,'$key',$val,$type)";
            $runQuery = self::DBrunQuery($query);
        }
        
        return "Salary Inserted Successfully.";
    }

    public static function addNewEmployeeFirstSalary($userID, $PARAMS){

        $special_allowance = "1000";
        $medical_allowance = "1000";
        $conveyance = "1000";
        $hra = "1000";
        $basic = "1000";
        $arrear = "0";
        $tds = "0";
        $misc_deduction = "0";
        $advance = "0";
        $loan = "0";
        $epf = "0";
        $leave = "0";

        $total_salary = ( $special_allowance + $medical_allowance + $conveyance + $hra + $basic + $arrear ) - ( $misc_deduction + $advance + $loan + $epf + $tds );
        
        $PARAMS['total_salary'] = $total_salary;
        $PARAMS['special_allowance'] = $special_allowance;
        $PARAMS['medical_allowance'] = $medical_allowance;
        $PARAMS['conveyance'] = $conveyance;
        $PARAMS['hra'] = $hra;
        $PARAMS['basic'] = $basic;
        $PARAMS['arrear'] = $arrear;
        $PARAMS['misc_deduction'] = $misc_deduction;
        $PARAMS['advance'] = $advance;
        $PARAMS['loan'] = $loan;
        $PARAMS['tds'] = $tds;
        $PARAMS['epf'] = $epf;
        $PARAMS['leave'] = $leave;
        
        $addSalary = self::addNewSalary($userID, $PARAMS);
        
        return "Salary Inserted Successfully.";
    }

    public static function addNewEmployee($PARAMS) { //api call
        $r_error = 1;
        $r_message = "";
        $r_data = array();

        $f_dateofjoining = $f_name = $f_jobtitle = $f_gender = $f_dob = $f_username = $f_workemail = "";
        $f_training_month = 0;

        if (isset($PARAMS['dateofjoining']) && $PARAMS['dateofjoining'] != '') {
            $f_dateofjoining = trim($PARAMS['dateofjoining']);
        }
        if (isset($PARAMS['name']) && $PARAMS['name'] != '') {
            $f_name = trim($PARAMS['name']);
        }
        if (isset($PARAMS['jobtitle']) && $PARAMS['jobtitle'] != '') {
            $f_jobtitle = trim($PARAMS['jobtitle']);
        }
        if (isset($PARAMS['gender']) && $PARAMS['gender'] != '') {
            $f_gender = trim($PARAMS['gender']);
        }
        if (isset($PARAMS['dob']) && $PARAMS['dob'] != '') {
            $f_dob = trim($PARAMS['dob']);
        }
        if (isset($PARAMS['username']) && $PARAMS['username'] != '') {
            $f_username = trim($PARAMS['username']);
        }
        if (isset($PARAMS['workemail']) && $PARAMS['workemail'] != '') {
            $f_workemail = trim($PARAMS['workemail']);
        }
        if (isset($PARAMS['training_month']) && $PARAMS['training_month'] != '') {
            $f_training_month = trim($PARAMS['training_month']);
        }

        
        if ($f_dateofjoining == '') {
            $r_message = "Date of joining is empty";
        } else if ($f_name == '') {
            $r_message = "Name is empty";
        } else if ($f_jobtitle == '') {
            $r_message = "Job Title is empty";
        } else if ($f_gender == '') {
            $r_message = "Gender is empty";
        } else if ($f_dob == '') {
            $r_message = "Date of birth is empty";
        } else if ($f_username == '') {
            $r_message = "Username is empty";
        } else if ($f_workemail == '') {
            $r_message = "Work email is empty";
        } else {
            //check user name exists
            $q = "select * from users where username='$f_username' ";
            $runQuery = self::DBrunQuery($q);
            $row = self::DBfetchRow($runQuery);
            if ($row != false) {
                $r_message = "Username already exists!!";
            } else {
                $f_type = "Employee";
                $f_password = md5(self::EMPLOYEE_FIRST_PASSWORD);
                $f_status = "Enabled";
                $q = "INSERT INTO users ( username, password, type, status ) VALUES ( '$f_username', '$f_password', '$f_type', '$f_status' ) ";
                self::DBrunQuery($q);
                $userID = self::getUserIdFromUsername($f_username);
                if ($userID == false) {
                    //user is not inserted
                    $r_message = "Errosr occurs while inserting user";
                } else {                    
                    //user is inserted
                    $q1 = "INSERT INTO user_profile ( name, jobtitle, dateofjoining, user_Id, dob, gender, work_email, training_month ) VALUES
                        ( '$f_name', '$f_jobtitle', '$f_dateofjoining', $userID, '$f_dob', '$f_gender', '$f_workemail', $f_training_month ) ";
                    self::DBrunQuery($q1);
                    $PARAMS['applicable_from'] = date('Y-m-d', strtotime($f_dateofjoining));
                    $applicable_till = date('Y-m-d', ( strtotime("+$f_training_month months", strtotime($PARAMS['applicable_from']))) - 1 );
                    $PARAMS['applicable_till'] = $applicable_till;
                    
                    // first salary will not add if an employee is added using third party key
                    if( !isset($PARAMS['secret_key']) || $PARAMS['secret_key'] == "" ){
                        self::addNewEmployeeFirstSalary($userID, $PARAMS);
                    }
                    $r_error = 0;
                    
                    $r_message = "Employee added Successfully !!";

                    // Added on 15-03-18 to send Welcome mail to new user
                    if (!empty($userID)) {
                        $r_message2 = self::sendWelcomeMail($userID); // call Welcome mail
                        $r_message3 = self::applyNewEmployeeLeaves($userID); 
                        $r_message = $r_message." ".$r_message2." ".$r_message3;

                        
                    }

                    // start -- added on 5th jan 2018 - by arun - to add Employee as default role when new user is added
                    $allRoles = self::getAllRole();
                    $defaultRoleId = false;
                    if( sizeof($allRoles) > 0 ){
                        foreach( $allRoles as $role ){
                            if( strtolower($role['name']) == 'employee' ){
                                $defaultRoleId = $role['id'];
                            }
                        }
                    }
                    if( $userID && $defaultRoleId !== false ){
                        self::assignUserRole($userID,$defaultRoleId);
                    }
                    // end -- added on 5th jan 2018 - by arun - to add Employee as default role when new user is added

                    $r_data['user_id'] = $userID;
                }
            }
        }

        $return = array();
        $return['error'] = $r_error;
        $r_data['message'] = $r_message;
        $return['data'] = $r_data;

        return $return;
    }

    public static function _randomString($length = 4, $result = '') {
        for ($i = 0; $i < $length; $i++) {
            $case = mt_rand(0, 1);
            switch ($case) {
                case 0:
                    $data = mt_rand(0, 9);
                    break;
                case 1:
                    $alpha = range('a', 'z');
                    $item = mt_rand(0, 26);
                    $data = strtoupper($alpha[$item]);
                    break;
            }
            $result .= $data;
        }
        return $result;
    }

    public static function updateUserPassword($userid, $newPasswordString) {
        $newPassword = md5($newPasswordString);
        $q = "UPDATE users set password='$newPassword' WHERE id=$userid";
        self::DBrunQuery($q);
        //deletes existing tokens of user
        self::deleteUserTokens($userid);
        return true;
    }

    public static function forgotPassword($username, $sendEmail = false) { // api call
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $emailData = array();

        if ($username == 'global_guest') {
            $r_message = "You don't have permission to reset password !!";
        } else {

            $q = "select * from users where username='$username' ";
            $runQuery = self::DBrunQuery($q);
            $row = self::DBfetchRow($runQuery);
            if ($row == false) {
                $r_message = "Username not exists!!";
            } else {
                $userId = $row['id'];
                $status = $row['status'];
                $type = $row['type'];

                if ($type != 'Employee') {
                    $r_message = "You can't reset pasword. Contact Admin.!!";
                } else {
                    if ($status != 'Enabled') {
                        $r_message = "Employee is disabled!!";
                    } else {
                        $newPassword = self::_randomString(5);
                        self::updateUserPassword($userId, $newPassword);
                        $r_error = 0;
                        $r_message = "Password reset Successfully. Check you slack for new password!!";
                        
                        //send slack message
                        $userInfo = self::getUserInfo($userId);
                        $userInfo_name = $userInfo['name'];
                        $slack_userChannelid = $userInfo['slack_profile']['slack_channel_id'];
                        
                        $message_to_user = "Hi $userInfo_name !!  \n Your new password for HR portal is : $newPassword";
                        $slackMessageStatus = self::sendSlackMessageToUser($slack_userChannelid, $message_to_user);
                        if( $sendEmail ){
                            $emailData['email'] = [
                                'email_id' => $userInfo['work_email'],
                                'name' => $userInfo['name'],
                                'subject' => 'Reset Password',
                                'body' => $message_to_user,                                
                            ];
                            self::sendEmail($emailData);                           
                        }
                    }
                }
            }
        }

        $return = array();
        $return['error'] = $r_error;
        $r_data['message'] = $r_message;
        $return['data'] = $r_data;

        return $return;
    }

    public static function updatePassoword($PARAMS) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();

        $f_userid = "";
        $f_newPassword = "";

        $token = $PARAMS['token'];

        $loggedUserInfo = JWT::decode($token, self::JWT_SECRET_KEY);
        $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);

        if (isset($loggedUserInfo['id'])) {
            $f_userid = $loggedUserInfo['id'];
            if (isset($PARAMS['password']) && $PARAMS['password'] != '') {
                $f_newPassword = trim($PARAMS['password']);
            }
            if ($f_newPassword == '') {
                $r_message = "Password is empty!!";
            } else if (strlen($f_newPassword) < 4) {
                $r_message = "Password must be atleast 4 characters!!";
            } else {
                self::updateUserPassword($f_userid, $f_newPassword);
                $r_error = 0;
                $r_message = "Password updated Successfully!!";

                //send slack message to user
                $userInfo = self::getUserInfo($f_userid);
                $userInfo_name = $userInfo['name'];
                $slack_userChannelid = $userInfo['slack_profile']['slack_channel_id'];

                $message_to_user = "Hi $userInfo_name !!  \n Your had just updated your HR Portal password.";
                $slackMessageStatus = self::sendSlackMessageToUser($slack_userChannelid, $message_to_user);
            }
        } else {
            $res['error'] = 1;
            $res['data']['message'] = "User not found";
        }

        $return = array();
        $return['error'] = $r_error;
        $r_data['message'] = $r_message;
        $return['data'] = $r_data;

        return $return;
    }

    public static function changeEmployeeStatus($data) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $status = $data['status'];

        $doFurtherProcess = true;

        // check for if elc is completed or not
        if( strtolower( $status ) == 'disabled' ){
            $checkElcCompleted = self::isUserElcCompleted( $data['user_id'] );
            if( $checkElcCompleted == true ){

            }else{
                $doFurtherProcess = false;
                $r_error = 1;
                $r_data['message'] = 'ELC till termination need to be complete before disabling an user!!';
            }
        }

        if( $doFurtherProcess == true ){
            $q = "UPDATE users SET status = '$status'  WHERE id =" . $data['user_id'];
            $res = self::DBrunQuery($q);
            if ($res == false) {
                $r_error = 1;
                $r_message = "Error occured while updating employee status";
                $r_data['message'] = $r_message;
            } else {

                $r_error = 0;
                $r_message = "Employee Status Updated";
                $r_data['message'] = $r_message;
            }
        }
        $return = array();

        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    public static function getDisabledUsersList($pagination) {
        
        $q = "SELECT users.*,user_profile.*,user_bank_details.bank_account_no as bank_no FROM users LEFT JOIN user_profile ON users.id = user_profile.user_Id LEFT JOIN user_bank_details ON users.id = user_bank_details.user_Id where users.status = 'Disabled'";

        $runQuery = self::DBrunQuery($q);
        $total_rows = self::DBfetchRows($runQuery);
        $rowCount = count($total_rows);
        
        if( isset($pagination['page']) && $pagination['page'] != "" && isset($pagination['limit']) && $pagination['limit'] != "" ) {
            if($pagination['page'] == 1){
                $q = $q . " LIMIT " . $pagination['limit'];  
    
            } else {
                $offset = ($pagination['page'] - 1) * $pagination['limit'];
                $q = $q . " LIMIT " . $pagination['limit'] . " OFFSET " . $offset;
            }
            $pagination = self::pagination($pagination, $rowCount);   
        }
        
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
                
        $newRows = array();
        foreach ($rows as $pp) {
            if ($pp['username'] == 'Admin' || $pp['username'] == 'admin') {

            } else {                
                // get employee last salary
                $user_id = $pp['id'];
                $q = "select * from salary where user_Id = $user_id ORDER by id DESC";
                $runQuery = self::DBrunQuery($q);
                $row = self::DBfetchRows($runQuery);
                if( sizeof($row) > 0 ){
                    $pp['last_salary_details'] = $row[0];
                }

                $newRows[] = $pp;
            }
        }

        $return = array();
        $return['disabled_employees'] = $newRows;
        $return['pagination'] = $pagination;
        
        if( isset($pagination['total_pages']) && $pagination['total_pages'] != "" ) {
            return $return;
        } else {
            return $newRows;
        }
    }

    public static function pagination($pagination, $count) {

        $total_pages = $previous_page = $next_page = "";        
        $prev = false;
        $next = false;
        $limit = 1;
        $page = "";
        
        if( isset($pagination['page']) && $pagination['page'] != "" && isset($pagination['limit']) && $pagination['limit'] != "" ) {
            $page = $pagination['page'];
            $limit = $pagination['limit'];
        }

        $total_pages = ceil($count / $limit);

        if ( $page == 1 ) {
            $next = true;

        } else if ( $page == $total_pages ) {
            $prev = true;
            $next = false;

        } else if ( $page == "" ) {
            $prev = false;
            $next = false;

        } else {
            $prev = true;
            $next = true;
        }

        if ($prev) {
            $previous_page = $page - 1;
        }

        if($next) {
            $next_page = $page + 1;
        }
        
        $res_pagination = array(
            'total_pages' => $total_pages,
            'current_page' => $page,
            'previous_page' => $previous_page,
            'next_page' => $next_page
        );

        return $res_pagination;
    }

    public static function getUserInfofromSlack($userid) {
        $arr = array();
        $q = "SELECT users.*,user_profile.* FROM users LEFT JOIN user_profile ON users.id = user_profile.user_Id where user_profile.slack_id = '$userid' ";

        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRow($runQuery);

        foreach ($row as $val) {
            $arr['id'] = $row['user_Id'];
            $arr['role'] = $row['type'];
        }
        return $arr;
    }

    public static function getAllNotApprovedleaveUser($userid) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();

        $q = "SELECT * FROM leaves Where user_Id = $userid AND status = 'Pending'";

        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        $no_of_rows = self::DBnumRows($runQuery);
        if ($no_of_rows > 0) {
            $r_error = 0;
            $r_data = $rows;
        } else {

            $r_error = 0;
            $r_message = "No Pending leave for this user";
            $r_data['message'] = $r_message;
        }
        $return = array();

        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    public static function ApproveDeclineUserLeave($id, $newstatus) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();

        $q = "UPDATE leaves set status='$newstatus' WHERE id = $id ";
        self::DBrunQuery($q);
        $r_error = 0;
        $r_message = "Leave status  updated Successfully!!";
        $return = array();

        $return['error'] = $r_error;
        $return['message'] = $r_message;
        return $return;
    }

//  get leave summery of a month of an employee
    public static function getUsersPendingLeavesSummary($userid, $year, $month) { // api call
        $r_error = 1;
        $r_message = "";
        $r_data = array();

        $usersAttendance = array();

        $user_month_attendance = self::getUserMonthAttendaceComplete($userid, $year, $month);

        $user_month_attendance = $user_month_attendance['data'];


        //---final leaves and missing punching days
        $raw = $user_month_attendance['attendance'];
        $finalAttendance = array();
        foreach ($raw as $pp) {
            $pp['display_date'] = date('d-M-Y', strtotime($pp['full_date']));
            if ($pp['day_type'] == 'WORKING_DAY') {
                if ($pp['in_time'] == '' || $pp['out_time'] == '') {
                    $finalAttendance[] = $pp;
                }
            } else if ($pp['day_type'] == 'LEAVE_DAY' || $pp['day_type'] == 'HALF_DAY') {
                $finalAttendance[] = $pp;
            }
        }


        //---final leaves and missing punching days

        if (sizeof($finalAttendance) > 0) {

            $u_data = array();
            $u_data['name'] = $user_month_attendance['userName'];
//            $u_data['profileImage'] = '';
//            $u_data['jobtitle'] = $u['jobtitle'];
            $u_data['userid'] = $userid;
            $u_data['year'] = $user_month_attendance['year'];
            $u_data['month'] = $user_month_attendance['month'];
            $u_data['monthName'] = $user_month_attendance['monthName'];
            $u_data['monthSummary'] = $user_month_attendance['monthSummary'];
            $u_data['nextMonth'] = $user_month_attendance['nextMonth'];
            $u_data['previousMonth'] = $user_month_attendance['previousMonth'];
            $u_data['attendance'] = $finalAttendance;
            $usersAttendance = $u_data;
        }

        //----------
        $nextMonth = self::_getNextMonth($year, $month);
        $previousMonth = self::_getPreviousMonth($year, $month);
        $currentMonth = self::_getCurrentMonth($year, $month);
        //----------

        $r_data['year'] = $year;
        $r_data['month'] = $month;
        $r_data['monthName'] = $currentMonth['monthName'];
        $r_data['nextMonth'] = $nextMonth;
        $r_data['previousMonth'] = $previousMonth;
        $r_data['leavesSummary'] = $usersAttendance;

        $r_error = 0;
        $return = array();
        $return['error'] = $r_error;
        $r_data['message'] = $r_message;
        $return['data'] = $r_data;

        return $return;
    }

    // cancel applied leave
    public static function cancelAppliedLeave($data) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $userid = $data['user_id'];
        $leave_start_date = date('Y-m-d', strtotime($data['date']));
        $year = date('Y', strtotime($data['date']));
        $current_date = date("Y-m-d");
        if ((strtotime($current_date) < strtotime($leave_start_date)) || isset($data['role'])) {
            $q = "SELECT * FROM leaves WHERE user_Id= $userid  AND from_date= '$leave_start_date' AND (status = 'Approved' OR status = 'Pending')";

            $runQuery = self::DBrunQuery($q);
            $row2 = self::DBfetchRows($runQuery);
            $no_of_rows = self::DBnumRows($runQuery);
            $users_rh_leaves = self::getUserRHLeaves($userid, $year);
            $users_rh_leaves = array_map(function($iter){
                return $iter['from_date'];
            }, $users_rh_leaves);

            if ($no_of_rows > 0) {
                foreach ($row2 as $val) {
                    $q2 = "UPDATE leaves SET status = 'Cancelled Request' WHERE id=" . $val['id'];
                    $runQuery2 = self::DBrunQuery($q2);
                    if( in_array( $leave_start_date, $users_rh_leaves ) ){
                        $q3 = "DELETE FROM leaves WHERE id = " . $val['id'];
                        $runQuery3 = self::DBrunQuery($q3);
                    }
                }
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

    // get users current status
    public static function getAllUserCurrentStatus() {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $date = date("Y-m-d");
        // $date = "2016-08-08";

        $enabledUsersList = self::getEnabledUsersList();

        foreach ($enabledUsersList as $val) {
            $k = "";
            $n = $val['name'];
            $k = self::getUsersLeaves($val['user_Id']);
            foreach ($k as $v) {
//&& ($v['status'] == 'Approved' || $v['status'] == 'Pending')
                if (strtotime($v['to_date']) >= strtotime($date)) {

                    $r_data[$n][] = $v;
                }
            }
        }
        $return = array();
        if (sizeof($r_data) == 0) {
            $r_message = "No data to show";
            $r_error = 0;
            $return['error'] = $r_error;
            $return['data'] = $r_message;
        } else {
            $r_error = 0;
            $return['error'] = $r_error;
            $return['data'] = $r_data;
        }

        return $return;
    }

    public static function lunchBreak($data) {

        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $date = date("Y-m-d H:i:s");
        $d = date("Y-m-d");
        $userid = $data['user_id'];
        $ins = array(
            'user_Id' => $userid,
            'lunch_start' => $date,
        );

        $userInfo = self::getUserInfo($userid);
        $name = $userInfo['name'];


        if ($data['lunch'] == "lunch_start") {
            $q1 = "SELECT * FROM lunch_break where user_Id = $userid AND lunch_start like '%$d%'";
            $run1 = self::DBrunQuery($q1);
            $row1 = self::DBfetchRow($run1);

            if (empty($row1)) {
                self::DBinsertQuery('lunch_break', $ins);
                $r_error = 0;
                $r_message = "Your lunch start time : " . date("jS M h:i A", strtotime($date));
            } else {
                $r_error = 1;
                $r_message = "Lunch start date already Inserted i.e : " . date("jS M h:i A", strtotime($row1['lunch_start']));
            }
        } elseif ($data['lunch'] == "lunch_end") {
            $q = "SELECT * FROM lunch_break where user_Id = $userid AND lunch_start like '%$d%'";
            $run = self::DBrunQuery($q);
            $row = self::DBfetchRow($run);
            if (empty($row)) {
                $r_error = 0;
                $r_message = "Please start your lunch time first";
            } else {
                if ($row['lunch_end'] == "") {
                    $q2 = "UPDATE lunch_break SET lunch_end = '$date' where id =" . $row['id'];
                    $run2 = self::DBrunQuery($q2);
                    $diff = abs(strtotime($date) - strtotime($row['lunch_start']));
                    $diff = floor($diff / 60);

                    $r_error = 0;
                    $r_message = "Your lunch end time :" . date("jS M h:i A", strtotime($date)) . " Total time = $diff min";
                    $hr_msg = "$name !  lunch start time:" . date("jS M h:i A", strtotime($row['lunch_start'])) . " lunch end time: " . date("jS M h:i A", strtotime($date)) . " \nTotal time = $diff min";

                    if ($userid != 302 && $userid != 288 && $userid != 313 && $userid != 320) {

                        if ($diff > 35) {

                            $extra = $diff - 35;

                            $q3 = "select * from user_working_hours where date = '$d' AND user_Id = $userid";

                            $run3 = self::DBrunQuery($q3);
                            $row3 = self::DBfetchRow($run3);

                            if (empty($row3)) {
                                $increase_time = date("H:i", strtotime('09:00 + ' . $extra . ' minute'));
                                $ins2 = array(
                                    'user_Id' => $userid,
                                    'date' => $d,
                                    'working_hours' => $increase_time,
                                    'reason' => 'lunch time exceed'
                                );
                                self::DBinsertQuery('user_working_hours', $ins2);
                            } else {
                                $increase_time = date("H:i", strtotime($row3['working_hours'] . '+' . $extra . ' minute'));

                                $q4 = "UPDATE user_working_hours SET working_hours = '$increase_time' where id =" . $row3['id'];
                                $run4 = self::DBrunQuery($q4);
                            }
                            $slack_userChannelid = $userInfo['slack_profile']['slack_channel_id'];

                            $msg = "Hi $name! Your working hours has been increased to $increase_time min as you have exceeded lunch time. \nKeep your lunch under 35 minutes\n In case of any issue contact HR";
                            //  $msg = "Hi $name! you have exceeded lunch time duration . \nKeep your lunch under 35 minutes";
                            $slackMessageStatus = self::sendSlackMessageToUser($slack_userChannelid, $msg);
                        }
                    }

                    $slackMessageStatus = self::sendSlackMessageToUser("hr", $hr_msg);
                } else {
                    $r_error = 1;
                    $r_message = "Lunch end date already inserted i.e : " . date("jS M h:i A", strtotime($row['lunch_end']));
                }
            }
        }

        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_message;
        return $return;
    }

    public static function getlunchBreakDetail($userid, $month) {

        $r_error = 1;
        $r_message = "";
        $r_data = array();

        $q = "SELECT * FROM lunch_break where user_Id = $userid AND lunch_start like '%$month%' ";
        try {
            $run = self::DBrunQuery($q);
            $rows = self::DBfetchRows($run);
            $arr = array();
            foreach ($rows as $val) {
                $diff = abs(strtotime($val['lunch_end']) - strtotime($val['lunch_start']));
                $diff = floor($diff / 60);
                $val['lunch_start'] = date("jS M h:i A", strtotime($val['lunch_start']));
                $val['lunch_end'] = date("jS M h:i A", strtotime($val['lunch_end']));
                $val['total_time'] = $diff;
                $arr[] = $val;
            }

            $r_error = 0;
            $r_data = $arr;
        } catch (Exception $e) {
            $r_error = 1;
            $r_message = "Some error occured";
        }

        $return = array();
        $return['error'] = $r_error;
        $return['message'] = $r_message;
        $return['data'] = $r_data;
        return $return;
    }

    public static function getAllUserLunchDetail($date) {

        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $month = date("Y-m", strtotime($date));

        $arr = array();
        $dt = date("jS M Y", strtotime($date));
        $employee_list = self::getEnabledUsersList();
        foreach ($employee_list as $val) {
            $fill = array();
            $name = $val['name'];
            $q = "SELECT * FROM lunch_break where lunch_start like '%$date%' AND user_Id =" . $val['user_Id'];
            $r = self::DBrunQuery($q);
            $run = self::DBfetchRow($r);
            $average = self::lunchBreakAvg($val['user_Id'], $month);

            if (sizeof($run) > 0) {

                $fill['name'] = $name;
                $fill['lunch_start'] = $run['lunch_start'];
                $fill['lunch_end'] = $run['lunch_end'];
                $fill['diff'] = $diff;
                $fill['average'] = $average;
                $arr[$date][] = $fill;
            } else {
                $fill['name'] = $name;
                $fill['lunch_start'] = 0;
                $fill['lunch_end'] = 0;
                $fill['diff'] = 0;
                $fill['average'] = $average;
                $arr[$date][] = $fill;
            }
        }

        if (sizeof($arr) > 0) {
            $r_error = 0;
            $r_data = $arr;
        } elseif (sizeof($arr) <= 0) {
            $r_error = 1;
            $r_message = "Some error occured";
        }

        $return = array();
        $return['error'] = $r_error;
        $return['message'] = $r_message;
        $return['data'] = $r_data;
        return $return;
    }

    public static function lunchBreakAvg($user_id, $month) {

        $q = "SELECT * FROM lunch_break where lunch_start like '%$month%' AND user_Id=$user_id";
        $r = self::DBrunQuery($q);
        $row = self::DBfetchRows($r);
        $average = 0;
        $arr = array();

        foreach ($row as $val) {
            if ($val['lunch_end'] != "" && $val['lunch_start'] != "") {
                $diff = abs(strtotime($val['lunch_end']) - strtotime($val['lunch_start']));
                $diff = floor($diff / 60);
                $arr[] = $diff;
            }
        }
        if (sizeof($arr) > 0) {
            $average = floor(array_sum($arr) / sizeof($arr));
        }

        return $average;
    }

    public static function getPreviousWorkDate($date) {

        $prev_date = date("m-d-Y", strtotime($date . '-1 day'));

        $c = "select * from attendance where timing like '%$prev_date%'";
        $r = self::DBrunQuery($c);
        $row = self::DBfetchRows($r);

        if (sizeof($row) > 0) {
            $status = str_replace("-", "/", $prev_date);
            return $status;
        } else {
            $date = date("Y-m-d", strtotime($date . '-1 day'));
            return self::getPreviousWorkDate($date);
        }
    }

    public static function updateBandwidthStats($data) {

        $r_error = 1;
        $r_message = "";

        $data_array = json_decode($data, true);
        $mac = "";
        $date = "";
        $rx = "";
        $tx = "";
        $total = "";
        if (sizeof($data_array) > 0) {
            $mac = $data_array['mac'];
            if (isset($data_array['dayWise']) && sizeof($data_array['dayWise']) > 0) {

                foreach ($data_array['dayWise'] as $val) {

                    $date = $val['date'];
                    $rx = $val['rx'];
                    $tx = $val['tx'];
                    $total = $val['total'];
                    $q = "select * from bandwidth_stats where mac = '$mac' AND date = '$date'";
                    $run = self::DBrunQuery($q);
                    $row = self::DBfetchRow($run);
                    if (sizeof($row) > 0) {
                        $q2 = "UPDATE bandwidth_stats SET rx = '$rx', tx='$tx', $total='$total' where mac = '$mac'";
                    } else {
                        $q2 = "INSERT INTO bandwidth_stats (mac,date,rx,tx,total) VALUES ('$mac','$date','$rx','$tx','$total')";
                    }
                    $run2 = self::DBrunQuery($q2);
                }
            }
            $r_error = 0;
            $r_message = "Data inserted successfully";
        } else {
            $r_error = 1;
            $r_message = "Some error occured. Please try again";
        }
        $return = array();
        $return['error'] = $r_error;
        $return['message'] = $r_message;
        return $return;
    }

    public static function addOfficeMachine($PARAMS, $logged_user_id = false ) {

        $db = self::getInstance();
        $mysqli = $db->getConnection();

        $r_error = 1;
        $r_message = "";

        $m_type = $m_name = $m_price = $serial_no = $date_purchase = $mac_addr = $os = $status = $userid = $comment = $warranty = $bill_no = $warranty_comment = $repair_comment = "";
        $unassigned_comment = '';
        $warranty_years = 0;
        if (isset($PARAMS['machine_type']) && $PARAMS['machine_type'] != '') {
            $m_type = trim($PARAMS['machine_type']);
        }
        if (isset($PARAMS['machine_name']) && $PARAMS['machine_name'] != '') {
            $m_name = trim($PARAMS['machine_name']);
        }
        if (isset($PARAMS['machine_price']) && $PARAMS['machine_price'] != '') {
            $m_price = trim($PARAMS['machine_price']);
        }
        if (isset($PARAMS['serial_no']) && $PARAMS['serial_no'] != '') {
            $serial_no = trim($PARAMS['serial_no']);
        }
        if (isset($PARAMS['purchase_date']) && $PARAMS['purchase_date'] != '') {
            $date_purchase = trim($PARAMS['purchase_date']);
        }
        if (isset($PARAMS['mac_address']) && $PARAMS['mac_address'] != '') {
            $mac_addr = trim($PARAMS['mac_address']);
        }
        if (isset($PARAMS['operating_system']) && $PARAMS['operating_system'] != '') {
            $os = trim($PARAMS['operating_system']);
        }
        if (isset($PARAMS['status']) && $PARAMS['status'] != '') {
            $status = trim($PARAMS['status']);
        }
        if (isset($PARAMS['comment']) && $PARAMS['comment'] != '') {
            $comment = trim($PARAMS['comment']);
        }
        if (isset($PARAMS['user_id']) && $PARAMS['user_id'] != '') {
            $userid = trim($PARAMS['user_id']);
        }
        if (isset($PARAMS['warranty']) && $PARAMS['warranty'] != '') {
            $warranty = trim($PARAMS['warranty']);
        }
        if (isset($PARAMS['bill_no']) && $PARAMS['bill_no'] != '') {
            $bill_no = trim($PARAMS['bill_no']);
        }
        if (isset($PARAMS['warranty_comment']) && $PARAMS['warranty_comment'] != '') {
            $warranty_comment = trim($PARAMS['warranty_comment']);
        }
        if (isset($PARAMS['repair_comment']) && $PARAMS['repair_comment'] != '') {
            $repair_comment = trim($PARAMS['repair_comment']);
        }
        if (isset($PARAMS['unassigned_comment']) && $PARAMS['unassigned_comment'] != '') {
            $unassigned_comment = trim($PARAMS['unassigned_comment']);
        }
        if (isset($PARAMS['warranty_years']) && $PARAMS['warranty_years'] != '') {
            $warranty_years = trim($PARAMS['warranty_years']);
        }

        

        $row = false;
        //check user name exists
        if ($mac_addr != "") {
            $q = "select * from machines_list where mac_address='$mac_addr'";
            $runQuery = self::DBrunQuery($q);
            $row = self::DBfetchRow($runQuery);
            $r_message = "Mac Address already exist";
        }

        if( $serial_no != '' ){
            $q = "select * from machines_list where serial_number='$serial_no'";
            $runQuery = self::DBrunQuery($q);
            $row = self::DBfetchRow($runQuery);
            $r_message = "Serial no already exist";
        }else{
            $row = true;
            $r_message = "Serial no is empty";
        }

        if ($row != false) {
            $r_error = 1;
        } else {
            $q = "INSERT INTO machines_list ( machine_type, machine_name, machine_price, serial_number, date_of_purchase, mac_address, operating_system, status, comments,warranty_end_date,bill_number,warranty_comment, repair_comment, warranty_years ) VALUES ( '$m_type', '$m_name', '$m_price', '$serial_no','$date_purchase', '$mac_addr', '$os', '$status', '$comment','$warranty','$bill_no','$warranty_comment','$repair_comment', '$warranty_years' ) ";
            self::DBrunQuery($q);
            $machine_id = mysqli_insert_id($mysqli);
            
            // if userid is assigned then only it will be assigned to a user else it will accepts a comment from FE and will be added as comment to the machine
            if( $userid == '' || empty($userid) ){
                self::addInventoryComment( $machine_id, $logged_user_id,  $unassigned_comment );
            }else{
                self::assignUserMachine($machine_id, $userid, $logged_user_id);    
            }
            
            $message = "New machine with following detail added:\n";
            $message.= "Machine Type=" . $m_type . "\n";
            $message.= "Machine Name=" . $m_name . "\n";
            $message.= "Machine Price=" . $m_price . "\n";
            $message.= "Machine Serial no=" . $serial_no . "\n";
            $message.= "Machine Waranty=" . $warranty . "\n";
            $slackMessageStatus = self::sendSlackMessageToUser($slack_userChannelid = "hr", $message);
            $r_error = 0;
            $r_message = "Inventory added successfully and need to be approved by admin!!";
        }

        $return = array();
        $return['error'] = $r_error;
        $return['message'] = $r_message;

        return $return;
    }

    public static function UpdateOfficeMachine( $logged_user_id, $PARAMS) {
        $r_error = 1;
        $r_message = "";

        $userInfo = self::getUserInfo($logged_user_id);
//below done by manish, because for some reason akriti hr role coming as employee. so make it true so it works.
        if( strtolower( $userInfo['type'] ) === 'admin' ||  true ) {
            $data = array(
                "machine_type" => $PARAMS['machine_type'],
                "machine_name" => $PARAMS['machine_name'],
                "machine_price" => $PARAMS['machine_price'],
                "serial_number" => $PARAMS['serial_no'],
                "mac_address" => $PARAMS['mac_address'],
                "date_of_purchase" => $PARAMS['purchase_date'],
                "operating_system" => $PARAMS['operating_system'],
                "status" => $PARAMS['status'],
                "comments" => $PARAMS['comment'],
                "warranty_end_date" => $PARAMS['warranty'],
                "bill_number" => $PARAMS['bill_no'],
                "warranty_comment" => $PARAMS['warranty_comment'],
                "repair_comment" => $PARAMS['repair_comment'],
                "warranty_years" => $PARAMS['warranty_years']
            );

            $inventory_id = $PARAMS['id'];

            $machine_detail = self::getMachineDetail($inventory_id);
            
            // don't know what was the reason on assigning machine when updating, arun commented this on 14th april since no need 
            // self::assignUserMachine($PARAMS['id'], $userid);

            // add this in inventory comment that invenoty is updated
            self::addInventoryComment($inventory_id, $logged_user_id,  "Inventory details are updated" );
            
            $whereField = 'id';
            $whereFieldVal = $inventory_id ;
            foreach ($machine_detail['data'] as $key => $val) {
                if (array_key_exists($key, $data)) {
                    if ($data[$key] != $machine_detail['data'][$key]) {
                        $arr = array();
                        $arr[$key] = $data[$key];
                        $res = self::DBupdateBySingleWhere('machines_list', $whereField, $whereFieldVal, $arr);
                        $msg[$key] = $data[$key];
                    }
                }
            }
            if ($res == false) {
                $r_error = 0;
                $r_message = "No fields updated into table";
                $r_data['message'] = $r_message;
            } else {

                if ($data['send_slack_msg'] == "") {

                    if (sizeof($msg > 0)) {
                        $message = "Machine ".$machine_detail['data']['machine_name']." updated with following detail: \n";
                        foreach ($msg as $key => $valu) {
                            $message = $message . "$key = " . $valu . "\n";
                        }

                        $slackMessageStatus = self::sendSlackMessageToUser($slack_userChannelid = 'hr', $message); // send slack message
                    }
                }
                $r_error = 0;
                $r_message = "Successfully Updated into table";
                $r_data['message'] = $r_message;
            }
        }
        else {
            $r_message = "You are not Authorized!!";
        }
        $return = array();
        $return['error'] = $r_error;
        $return['message'] = $r_message;

        return $return;
    }

    
    public static function getMachineDetail($id) {
        $r_error = 1;
        $row = array();
        //check user name exists
        // $q = "select * from machines_list where id=" . $PARAMS['id'];

        $q = "select 
                machines_list.*,
                machines_user.user_Id,
                machines_user.assign_date ,
                f1.file_name as fileInventoryInvoice,
                f2.file_name as fileInventoryWarranty,
                f3.file_name as fileInventoryPhoto
                from 
                machines_list 
                left join machines_user on machines_list.id = machines_user.machine_id
                left join files as f1 ON machines_list.file_inventory_invoice = f1.id
                left join files as f2 ON machines_list.file_inventory_warranty = f2.id
                left join files as f3 ON machines_list.file_inventory_photo = f3.id
                where 
                machines_list.id = $id";

        $runQuery = self::DBrunQuery($q);

        try {
            $row = self::DBfetchRow($runQuery);
            $r_error = 0;
            // get inventory comments
            $inventoryHistory = self::getInventoryHistory($id);
            $row['history'] = $inventoryHistory;
        } catch (Exception $e) {
            $r_error = 1;
            $row = "Some error occured.";
        }
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $row;

        return $return;
    }

    public static function assignUserMachine($machine_id, $userid, $logged_user_id = null ) {
        $r_error = 1;
        $r_message = "";
        if ($userid == "") {
            $return = self::removeMachineAssignToUser($machine_id, $logged_user_id);
        } else {
            $userInfo = self::getUserInfo($userid);
            $userInfo_name = $userInfo['name'];
            $slack_userChannelid = $userInfo['slack_profile']['slack_channel_id'];
            $machine_info = self::getMachineDetail($machine_id);
            $date = date("Y-m-d");
            //check user name exists
            $q = "select * from machines_user where machine_id ='$machine_id'";
            $runQuery = self::DBrunQuery($q);
            $row = self::DBfetchRow($runQuery);
            if ($row != false) {
                //added to keep record of machine
                $oldUserId = $row['user_Id'];

                // maintained history in inventory_comments table
                self::addInventoryComment( $machine_id, $logged_user_id, 'Inventory Removed', $oldUserId );
                self::addInventoryComment( $machine_id, $logged_user_id, 'Inventory Assigned', $userid );

                $q = "UPDATE machines_user SET  user_Id = '$userid', assign_date = '$date' where id =" . $row['id'];
            } else {
                $q = "INSERT INTO machines_user ( machine_id, user_Id, assign_date ) VALUES ( $machine_id, $userid, '$date') ";
                self::addInventoryComment( $machine_id, $logged_user_id, 'Inventory Assigned', $userid );
            }
            self::DBrunQuery($q);
            $r_error = 0;

            $message = "Hi $userInfo_name !! \n You have been assigned  " . $machine_info['data']['machine_name'] . " " . $machine_info['data']['machine_type'] . " by HR";
            $slackMessageStatus = self::sendSlackMessageToUser($slack_userChannelid, $message);
            $r_message = "Machine assigned Successfully !!";

            $return = array();
            $return['error'] = $r_error;
            $return['message'] = $r_message;
        }


        return $return;
    }

    public static function getUserMachine($userid) {
        $r_error = 1;
        $r_message = "";
        $q = "select machines_list.*,machines_user.user_Id,machines_user.assign_date from machines_list left join machines_user on machines_list.id = machines_user.machine_id where machines_user.user_Id = '$userid'";

        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        if (sizeof($row) == 0) {
            $r_message = "No Machine assigned to user!";
        } else {
            $r_error = 0;
            $r_message = $row;
        }

        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_message;
        print_r($return);die;
        return $return;
    }

    public static function getAllMachineDetail($sort = false, $status_sort = false) {
        if ($sort != false) {
            $q = "select 
                    machines_list.*,
                    machines_user.user_Id,
                    machines_user.assign_date,
                    user_profile.name,
                    user_profile.work_email 
                    f1.file_name as fileInventoryInvoice,
                    f2.file_name as fileInventoryWarranty,
                    f3.file_name as fileInventoryPhoto 
                    from 
                    machines_list 
                    left join machines_user on machines_list.id = machines_user.machine_id 
                    left join user_profile on machines_user.user_Id = user_profile.user_Id 
                    left join files as f1 ON machines_list.file_inventory_invoice = f1.id
                    left join files as f2 ON machines_list.file_inventory_warranty = f2.id
                    left join files as f3 ON machines_list.file_inventory_photo = f3.id
                    where 
                    machines_list.machine_type='$sort' and machines_list.approval_status = 1";
        }if ($status_sort != false) {
            $q = "select 
                    machines_list.*,
                    machines_user.user_Id,
                    machines_user.assign_date,
                    user_profile.name,
                    user_profile.work_email,
                    f1.file_name as fileInventoryInvoice,
                    f2.file_name as fileInventoryWarranty,
                    f3.file_name as fileInventoryPhoto 
                    from 
                    machines_list 
                    left join machines_user on machines_list.id = machines_user.machine_id 
                    left join user_profile on machines_user.user_Id = user_profile.user_Id 
                    left join files as f1 ON machines_list.file_inventory_invoice = f1.id
                    left join files as f2 ON machines_list.file_inventory_warranty = f2.id
                    left join files as f3 ON machines_list.file_inventory_photo = f3.id
                    where 
                    machines_list.status='$status_sort' and machines_list.approval_status = 1";
        } else {
            $q = "select 
                    machines_list.*,
                    machines_user.user_Id,
                    machines_user.assign_date,
                    user_profile.name,
                    user_profile.work_email,
                    f1.file_name as fileInventoryInvoice,
                    f2.file_name as fileInventoryWarranty,
                    f3.file_name as fileInventoryPhoto 
                    from 
                    machines_list 
                    left join machines_user on machines_list.id = machines_user.machine_id 
                    left join user_profile on machines_user.user_Id = user_profile.user_Id 
                    left join files as f1 ON machines_list.file_inventory_invoice = f1.id
                    left join files as f2 ON machines_list.file_inventory_warranty = f2.id
                    left join files as f3 ON machines_list.file_inventory_photo = f3.id
                    where 
                    machines_list.approval_status = 1 
                    ORDER BY 
                    machines_list.id DESC";
        }
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        $return = array();
        $return['error'] = 0;
        $return['data'] = $row;
        return $return;
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

    public static function approveUnapprovedMachine($id) {
            $machineID = $id;
            $q = "UPDATE machines_list set approval_status = 1 where id = '$machineID'";
            $runQuery = self::DBrunQuery($q);
            $row = self::DBfetchRows($runQuery);
            $r_error = 0;
            $r_message = "Machine status updated successfully";
            $return = array();
            $return['message'] = $r_message;
            $return['error'] = $r_error;
            $return['data'] = $row;
            return $return;
    }

    public static function getMachineHistory($machineId) {
        $r_error = 1;
        $r_message = "";
        $q = "select machine_assign_record.*, user_profile.name,user_comment_machine.comment,user_comment_machine.comment_date from machine_assign_record left join user_profile on machine_assign_record.user_Id = user_profile.user_Id left join user_comment_machine on machine_assign_record.machine_id = user_comment_machine.machine_id where machine_assign_record.machine_id = 5 order by machine_assign_record.id desc";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        if (sizeof($row) == 0) {
            $r_message = "This Machine is not assigned to any employee!";
        } else {
            $r_error = 0;
            $r_message = $row;
        }

        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_message;
        return $return;
    } 

    public static function removeMachineAssignToUser($data, $logged_user_id = false, $reason_of_removal = false) {
        $machine_info = self::getMachineDetail($data);
        if (!empty($machine_info['data']['user_Id'])) {
            $userInfo = self::getUserInfo($machine_info['data']['user_Id']);
            $userInfo_name = $userInfo['name'];
            $slack_userChannelid = $userInfo['slack_profile']['slack_channel_id'];
            $message = "Hi $userInfo_name !! \n You have been unassigned  to device " . $machine_info['data']['machine_name'] . " " . $machine_info['data']['machine_type'] . " by HR ";
            $slackMessageStatus = self::sendSlackMessageToUser($slack_userChannelid, $message);

            // save to inventory history
            if( $reason_of_removal == false ){
                $reason_of_removal = 'Inventory Removed';
            }
            self::addInventoryComment( $data, $logged_user_id, $reason_of_removal, $machine_info['data']['user_Id'] );
        }

        $q = "Delete from machines_user where machine_id=$data";
        $runQuery = self::DBrunQuery($q);

        $return = array();
        $return['error'] = 0;
        $return['message'] = "User removed successfully";
        return $return;
    }

    public static function getUnassignedMachineList() {
        $q = "SELECT * FROM machines_list WHERE id not in (select machine_id from machines_user)";
        $runQuery = self::DBrunQuery($query);
        $row = self::DBfetchRows($runQuery);
        $return = array();
        $return['error'] = 0;
        $return['data'] = $row;
        return $return;
    }

    public static function userAssignMachine($userid,$machineid) {

    }

    public static function removeInventoryComments( $inventory_id ){
        $q = "Delete from inventory_comments where inventory_id=$inventory_id";
        $runQuery = self::DBrunQuery($q);
    }

    public static function removeMachineDetails($inventory_id,$logged_user_id) {
        // before deletin a machine 
        // 1. remove machine comments
        // 2. remove mahine assign user
        $logged_user_info = self::getUserInfo($logged_user_id);
        if( strtolower( $logged_user_info['type'] ) === 'admin' ) {
            
            // remove machine comments
            self::removeInventoryComments($inventory_id);

            // remove machine assign user
            self::removeMachineAssignToUser($inventory_id);

            $q = "Delete from machines_list where id=$inventory_id";
            $runQuery = self::DBrunQuery($q);
            
            $r_message = "Machine detail removed successfully";
        } else {
            $r_message = "You are not Authorized to do that!!";
        }
        $return = array();
        $return['error'] = 0;
        $return['message'] = $r_message;
        return $return;
    }

    public static function userCompensateTimedetail($userid, $date = false) {
        date_default_timezone_set('UTC');
        $array = array();
        $de = date("m-Y");
        $year = date("Y");
        $month = date("month");
        if ($date != false) {
            $de = date("m-Y", strtotime($date));
            $year = date("Y", strtotime($date));
            $month = date("m", strtotime($date));
        }

        $query = "SELECT hr_data.*,users.status FROM hr_data LEFT JOIN users ON hr_data.user_id = users.id where users.status='Enabled' AND hr_data.user_id=$userid AND hr_data.date LIKE '%$de%'";
        $runQuery = self::DBrunQuery($query);
        $row1 = self::DBfetchRows($runQuery);
        foreach ($row1 as $tv) {
            $d = strtotime($tv['date']);
            if (array_key_exists($d, $rows)) {
                $rows[$d] = $tv;
            } else {
                $rows[$d] = $tv;
            }
        }
        ksort($rows);

        $getDaysOfMonth = self::getDaysOfMonth($year, $month);

        foreach ($getDaysOfMonth as $getd) {
            $de = 0;
            $de = self::getData(date('m-d-Y', strtotime($getd['full_date'])));
            if ($de != 0) {
                $set[] = $getd['full_date'];
            }
        }
        array_pop($set);
        foreach ($rows as $f) {
            if ($f['entry_time'] != 0 && $f['exit_time'] != 0) {
                $ed = strtotime($f['exit_time']) - strtotime($f['entry_time']);
                $te = date("h:i", $ed);
                $user_id = $f['user_id'];
                $cdate = date('Y-m-d', strtotime($f['date']));
                $working_hour = self::getWorkingHours($cdate);
                $half_time = date("h:i", strtotime($working_hour) / 2);
                if ($working_hour != 0) {
                    $user_working_hour = self::getUserWorkingHours($user_id, $cdate);
                    if ($user_working_hour != 0) {
                        $working_hour = $user_working_hour;
                        //     echo $user_id."-".$cdate."-".$working_hour;
                    }
                    if (strtotime($te) < strtotime($half_time)) {
                        $ed1 = strtotime($half_time) - strtotime($te);
                        $te1 = $ed1 / 60;
                        if ($te1 >= 5) {
                            $vv['ptime'][] = $te1;
                            $vv['ctime'][] = 0;
                            $vv['entry_exit'][] = $f['entry_time'] . "--" . $f['exit_time'] . "--" . $f['date'];
                            $vv['message'][] = "On ".date("jS M",strtotime($f['date']))." Pending: ".$te1." Mins | Compensated : 0 Mins" ;
                        }
                        $vv['half'][] = date("m-d-Y", strtotime($f['date']));
                    }
                    if (strtotime($half_time) <= strtotime($te) && strtotime($te) < strtotime($working_hour)) {
                        $hd = self::getUserHalfDay($user_id, $cdate);
                        if ($hd != 0) {

                        } else {
                            $ed1 = strtotime($working_hour) - strtotime($te);
                            $te1 = $ed1 / 60;
                            if ($te1 >= 5) {
                                $vv['ptime'][] = $te1;
                                $vv['ctime'][] = 0;
                                $vv['entry_exit'][] = $f['entry_time'] . "--" . $f['exit_time'] . "--" . $f['date'];
                                $vv['message'][] = "On ".date("jS M",strtotime($f['date']))." Pending: ".$te1." Mins | Compensated : 0 Mins" ;
                            }
                        }
                    }
                    if (strtotime($te) > strtotime($working_hour)) {
                        $ed1 = strtotime($te) - strtotime($working_hour);
                        $te1 = $ed1 / 60;
                        if ($te1 >= 5) {
                            $vv['ctime'][] = $te1;
                            $vv['ptime'][] = 0;
                            $vv['entry_exit'][] = $f['entry_time'] . "--" . $f['exit_time'] . "--" . $f['date'];
                            $vv['message'][] = "On ".date("jS M",strtotime($f['date']))." Pending: 0 Mins | Compensated : ".$te1." Mins" ;
                        }
                    }
                }
            }
            $vv['wdate'][] = date('m-d-Y', strtotime($f['date']));
            $vv['userid'] = $f['user_id'];
        }

        $pending = $vv['ptime'];
        $compensate = $vv['ctime'];
        $entry = $vv['entry_exit'];
        $message = $vv['message'];
        $wdate = $vv['wdate'];
        $half = array();
        if (array_key_exists('half', $value)) {
            $half = $value['half'];
        }
        $to_compensate = 0;
        $index = 0;
        $rep = array();
        $final = array();
        for ($i = 0; $i < sizeof($pending); $i++) {
            if ($pending[$i] != 0 || !empty($rep)) {
                $at = array();
                $at['pend'] = $pending[$i];
                $at['comp'] = $compensate[$i];
                $at['entry'] = $entry[$i];
                $at['message'] = $message[$i];
                $rep[] = $at;
            }
            $to_compensate = $pending[$i] + $to_compensate;
            if ($to_compensate != 0) {
                $to_compensate = $to_compensate - $compensate[$i];
            }
            if ($to_compensate <= 0) {
                $to_compensate = 0;
                $rep = array();
            }
        }
        $final['t_detail'] = $rep;
        if ($to_compensate >= 10) {
            $hour = floor($to_compensate/60);
            $min = $to_compensate%60;
            $final['t_remain'] = $hour.":".$min;
        }

        $return = array();
        $return['error'] = 0;
        $return['data'] = $rep;
        return $final;
    }

    public static function getData($date) {

        $result = 0;
        $q = "select * from attendance where timing like '%$date%'";

        $runQuery = self::DBrunQuery($q);
        $no_of_rows = self::DBnumRows($runQuery);
        if ($no_of_rows >= 1) {
            $result = 1;
            return $result;
        } else {
            return $result;
        }
    }

    public static function getWorkingHours($data) {

        $result = "09:00"; // default value
        $qry = "select * from working_hours where date='$data'";
        $runQuery = self::DBrunQuery($qry);
        $no_of_rows = self::DBnumRows($runQuery);
        if ($no_of_rows > 0) {
            $row = self::DBfetchRow($runQuery);
            $result = $row['working_hours'];
        }
        return $result;
    }

    public static function getUserWorkingHours($uid, $date) {
        $result = 0;
        $qry = "select * from user_working_hours where user_Id = '$uid' AND date='$date'";
        $runQuery = self::DBrunQuery($qry);
        $no_of_rows = self::DBnumRows($runQuery);
        if ($no_of_rows > 0) {
            $row = self::DBfetchRow($runQuery);
            $result = $row['working_hours'];
        }
        return $result;
    }

    public static function getUserHalfDay($userid, $date) {
        $query = "select * from leaves where user_Id=" . $userid . " AND from_date like '%$date%' AND status != 'Rejected'";
        $runQuery = self::DBrunQuery($qry);
        $no_of_rows = self::DBnumRows($runQuery);
        return $no_of_rows;
    }

    public static function saveBandwidthDetail($data) {

        $error = 1;
        $message = "";

        $plan = "";
        $left_data = "";
        $days_left = "";
        $dsl = "";
        $date = date("Y-m-d");
        if (sizeof($data) > 0) {
            $plan = $data['plan'];
            $left_data = $data['left_data'];
            $days_left = $data['days_left'];
            $dsl = $data['dsl_number'];

            $q = "select * from bandwidth_detail where dsl_number = '$dsl'";
            $runQuery = self::DBrunQuery($q);
            $no_of_rows = self::DBnumRows($runQuery);
            if ($no_of_rows > 0) {
                $qry = "UPDATE bandwidth_detail SET data_plan = '$plan', left_data = '$left_data', days_left = '$days_left', date = '$date' Where dsl_number = '$dsl'";
                $error = 0;
                $message = "Table updated";
            } else {
                $qry = "INSERT INTO bandwidth_detail (data_plan, left_data, dsl_number, days_left , date) VALUES ('$plan','$left_data','$dsl','$days_left','$date' )";
                $error = 0;
                $message = "Data Inserted";
            }
            $runQuery2 = self::DBrunQuery($qry);
        } else {
            $error = 1;
            $message = "Passed data should not be empty";
        }
        $return = array();
        $return['error'] = $error;
        $return['data'] = $message;
        return $return;
    }

    public static function getBandwidthDetail() {
        $query = "select * from bandwidth_detail";
        $runQuery = self::DBrunQuery($query);
        $rows = self::DBfetchRows($runQuery);
        if (sizeof($rows) > 0) {
            $error = 0;
            $message = $rows;
        } else {
            $error = 1;
            $message = 'No data found';
        }
        $return = array();
        $return['error'] = $error;
        $return['data'] = $message;
        return $return;
    }

    public static function validateUniqueKey($data) {
        $unique = $data['unique_key'];
        $mac = $data['mac_address'];
        $query = "select * from user_profile where unique_key = '$unique'";
        $runQuery = self::DBrunQuery($query);
        $row = self::DBfetchRow($runQuery);
        if (sizeof($row) > 0) {
            $id = $row['user_Id'];
            $query2 = "select machines_list.mac_address,machines_list.id, machines_user.machine_id,machines_user.user_Id from machines_list LEFT JOIN machines_user On machines_list.id = machines_user.machine_id  where machines_user.user_Id = $id AND machines_list.mac_address = '$mac'";
            $run = self::DBrunQuery($query2);
            $row2 = self::DBfetchRow($run);
            if (sizeof($row2) > 0) {
                $error = 0;
                $message = 'User is authentic';
            } else {
                $error = 1;
                $message = 'Mac address associated to user is not valid';
            }
        } else {
            $error = 1;
            $message = 'User with given unique key not found';
        }
        $return = array();
        $return['error'] = $error;
        $return['data'] = $message;
        return $return;
    }

    public static function addMachineType($data) {
        $r_error = 1;
        $not_deleted = "";
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
            $p = json_decode($row1['value'], true);
            $value = json_decode($data['value'], true);
            $s = array_diff($p, $value);

            if (sizeof($s) > 0) {
                foreach ($s as $v) {
                    $query = "select * from machines_list where machine_type = '$v'";
                    $run = self::DBrunQuery($query);
                    $n_rows = self::DBnumRows($run);
                    if ($n_rows > 0) {
                        $r_data['not_delete'][] = $v;
                        array_push($value, $v);
                    }
                }
            }
            $res = json_encode($value);
            $q = "UPDATE config set value='$res' WHERE type ='" . $data['type'] . "'";
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

    public static function addMachineStatus($data) {
        $r_error = 1;
        $not_deleted = "";
        $r_message = "";
        $r_data = array();

        $data_status = trim( $data['status'] );
        $data_color = trim( $data['color'] );

        $ins = array(
            'status' => $data_status,
            'color' => $data_color
        );
        $q1 = "select * from machine_status where status ='" . $data_status . "'";

        $runQuery1 = self::DBrunQuery($q1);
        $row1 = self::DBfetchRow($runQuery1);
        $no_of_rows = self::DBnumRows($runQuery1);

        $q2 = "select * from machine_status where color ='" . $data_color . "' AND status !='" . $data_status . "'";
        $runQuery2 = self::DBrunQuery($q2);
        $no_of_rows2 = self::DBnumRows($runQuery2);
        if ($no_of_rows2 == 0) {
            if ($no_of_rows == 0) {
                $res = self::DBinsertQuery('machine_status', $ins);
                $r_error = 0;
                $r_message = "Variable Successfully Inserted";
                $r_data['message'] = $r_message;
            } if ($no_of_rows != 0) {
                $q = "UPDATE machine_status set status='" . $data_status . "', color='" . $data_color . "'WHERE id ='" . $row1['id'] . "'";
                self::DBrunQuery($q);

                $r_error = 0;
                $r_message = "Variable updated successfully";
                $r_data['message'] = $r_message;
            }
        } else {
            $r_error = 1;
            $r_message = "Color already assigned to status";
            $r_data['message'] = $r_message;
        }



        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    public static function getMachineTypeList() {
        $r_error = 1;
        $r_message = "";
        $q1 = "select * from config where type ='machine_type'";
        $runQuery = self::DBrunQuery($q1);
        $row = self::DBfetchRow($runQuery);
        if (sizeof($row) == 0) {
            $r_message = "No machine type list found!";
        } else {
            $r_error = 0;
            $r_message = $row;
        }

        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_message;

        return $return;
    }

    public static function getMachineStatusList() {

        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $q1 = "select * from machine_status";

        $runQuery = self::DBrunQuery($q1);
        $row = self::DBfetchRows($runQuery);
        if (sizeof($row) == 0) {

            $r_message = "No machine status list found!";
        } else {
            $r_error = 0;
            $r_data = $row;
        }

        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        $return['message'] = $r_message;

        return $return;
    }

    public static function deleteMachineStatus($data) {

        $r_error = 1;
        $r_message = "";

        $q = "select * from machines_list where status = '$data'";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        if (sizeof($rows) > 0) {
            $r_message = "Machine status is in use";
        } else {

            $q = "Delete from machine_status where status = '$data'";
            $runQuery = self::DBrunQuery($q);

            $r_error = 0;
            $r_message = "Machine status removed successfully";
        }


        $return = array();
        $return['error'] = $r_error;
        $return['message'] = $r_message;
        return $return;
    }

    public static function deleteUser($data) {

        $r_error = 1;
        $r_message = "";
        $q = "Delete users,user_profile from users INNER JOIN user_profile ON users.id = user_profile.user_Id where users.id = '$data'";
        $runQuery = self::DBrunQuery($q);

        $r_error = 0;
        $r_message = "Employee removed successfully";

        $return = array();
        $return['error'] = $r_error;
        $return['message'] = $r_message;
        return $return;
    }

    public static function getMachineCount() {

        $r_error = 1;
        $r_message = "";

        $query = "SELECT machines_list.*, machines_user.user_Id FROM machines_list LEFT JOIN machines_user ON machines_list.id = machines_user.machine_id";
        $run = self::DBrunQuery($query);
        $row = self::DBfetchRows($run);

        $arr_device = array();
        if (sizeof($row) > 0) {
            foreach ($row as $val) {
                $key = $val['machine_type'];
                $key2 = $val['status'];
                if (array_key_exists($key, $arr_device)) {
                    $arr_device[$key]['total'] = $arr_device[$key]['total'] + 1;
                    if (array_key_exists($key2, $arr_device[$key])) {
                        $arr_device[$key][$key2] = $arr_device[$key][$key2] + 1;
                    } else {
                        $arr_device[$key][$key2] = 1;
                    }
                    if ($val['user_Id'] != "" || $val['user_Id'] != NULL) {
                        $arr_device[$key]['User_Assign'] = $arr_device[$key]['User_Assign'] + 1;
                    } else {
                        $arr_device[$key]['User_Not_Assign'] = $arr_device[$key]['User_Not_Assign'] + 1;
                    }
                } else {
                    $arr_device[$key]['total'] = 1;
                    if (array_key_exists($key2, $arr_device[$key])) {
                        $arr_device[$key][$key2] = $arr_device[$key][$key2] + 1;
                    } else {
                        $arr_device[$key][$key2] = 1;
                    }
                    if ($val['user_Id'] != "" || $val['user_Id'] != NULL) {
                        $arr_device[$key]['User_Assign'] = $arr_device[$key]['User_Assign'] + 1;
                    } else {
                        $arr_device[$key]['User_Not_Assign'] = $arr_device[$key]['User_Not_Assign'] + 1;
                    }
                }
            }
        }


        if (sizeof($arr_device) > 0) {
            $r_error = 0;
            $r_message = "Data found";
        } else {
            $r_error = 1;
            $r_message = "No Data found";
        }


        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $arr_device;
        $return['message'] = $r_message;
        return $return;
    }

    public static function getAllUserPrevMonthTime($year, $month) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();
        $format_array = array();

        $q = "Select users.status,user_profile.name,users_previous_month_time.* from users Inner Join user_profile on users.id = user_profile.user_Id Inner Join users_previous_month_time on users.id = users_previous_month_time.user_Id where users_previous_month_time.year_and_month ='" . $year . "-" . $month . "' AND users.status='Enabled'";

        $run = self::DBrunQuery($q);
        $rows = self::DBfetchRows($run);

        if(sizeof($rows) > 0){
            foreach($rows as $val){
                $hr_min = "";
                $hr_min = explode(":",$val['extra_time']);
                $val['pending_hour'] = $hr_min[0];
                $val['pending_minute'] = $hr_min[1];
                $date = $year."-".$month."-01";
                $time_detail = self::userCompensateTimedetail($val['user_Id'],$date);
                $val['time_detail'] = $time_detail;
                $format_array[] = $val;
            }
        }

        $nextMonth = self::_getNextMonth($year, $month);
        $previousMonth = self::_getPreviousMonth($year, $month);
        $currentMonth = self::_getCurrentMonth($year, $month);

        $r_data['nextMonth'] = $nextMonth;
        $r_data['previousMonth'] = $previousMonth;
        $r_data['month'] = $month;
        $r_data['monthName'] = $currentMonth['monthName'];
        $r_data['year'] = $year;
        $r_data['user_list'] = $format_array;

        if (sizeof($rows) > 0) {
            $r_error = 0;
            $r_message = "Data found";
        } else {
            $r_error = 1;
            $r_message = "No Data found";
        }
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        $return['message'] = $r_message;
        return $return;
    }

    public static function getUserPrevMonthTime($userid, $year, $month) {
        $r_error = 1;
        $r_message = "";
        $r_data = array();

        $q = "Select * from users_previous_month_time where year_and_month ='" . $year . "-" . $month . "' AND user_Id=" . $userid;
        $run = self::DBrunQuery($q);
        $rows = self::DBfetchRows($run);
        $r_data['user_list'] = $rows;

        if (sizeof($rows) > 0) {
            $r_error = 0;
            $r_message = "Data found";
        } else {
            $r_error = 1;
            $r_message = "No Data found";
        }
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $r_data;
        $return['message'] = $r_message;
        return $return;
    }

    public static function getUserRole($userid){
        // this has complete information of role - pages, actions, noitfications
        $return = false;
        $userInfo = self::getUserInfo($userid);
        if( isset( $userInfo['role_id'] ) && !empty( $userInfo['role_id']) ){
            $roleCompleteDetails = self::getRoleCompleteDetails( $userInfo['role_id'] );
            $return = $roleCompleteDetails;
        }
        return $return;
    }

    // check page is valid for current user id
    public static function is_user_valid_page( $page_name, $userid ){
        $return = false;
        $user_role_details = self::getUserRole( $userid );
        $role_pages = $user_role_details['role_pages'];
        if( sizeof( $role_pages ) > 0 ){
            foreach( $role_pages as $page ){
                if( $page_name == $page['page_name'] ){
                    $return = true;
                    break;
                }
            }
        }
        return $return;;
    }

    // check action is valid for current user id
    public static function is_user_valid_action( $action_name, $userid ){
        $return = false;
        $user_role_details = self::getUserRole( $userid );
        $role_actions = $user_role_details['role_actions'];
        if( sizeof( $role_actions ) > 0 ){
            foreach( $role_actions as $action ){
                if( $action_name == $action['action_name'] ){
                    $return = true;
                    break;
                }
            }
        }
        return $return;;
    }

    // check notification is valid for current user id
    public static function is_user_valid_notification( $notification_name, $userid ){
        $return = false;
        $user_role_details = self::getUserRole( $userid );
        $role_notifications = $user_role_details['role_notifications'];
        if( sizeof( $role_notifications ) > 0 ){
            foreach( $role_notifications as $notification ){
                if( $notification_name == $notification['notification_name'] ){
                    $return = true;
                    break;
                }
            }
        }
        return $return;;
    }

    // get employee first working day of the current month
    public static function getEmployeeCurrentMonthFirstWorkingDate( $userid ){
        $return = false;
        $currentDate = date('Y-m-d');
        $currentYear = date('Y');
        $currentMonth = date('m');
        $currentDateDate = date('d');

        $monthDetails = self::getUserMonthAttendace($userid, $currentYear, $currentMonth );
        
        $tempArray = array();
        foreach( $monthDetails as $md ){
            $md_date = $md['date'];
            if( $md['day_type'] == 'WORKING_DAY' ){
                $tempArray[] = $md;
            }
        }
        $return = $tempArray[0];
        return $return;
    }

    // get employee next working date , starts from today onwards
    public static function getEmployeeNextWorkingDate( $userid ){
        $return = false;
        $currentDate = date('Y-m-d');
        $currentYear = date('Y');
        $currentMonth = date('m');
        $currentDateDate = date('d');

        $monthDetails = self::getUserMonthAttendace($userid, $currentYear, $currentMonth );
        // check if there is no working day left in current month
        $tempArray = array();
        foreach( $monthDetails as $md ){
            $md_date = $md['date'];
            if( $md_date * 1 >= $currentDateDate * 1 && $md['day_type'] == 'WORKING_DAY' ){
                $tempArray[] = $md;
            }
        }
        // if temp array is empty, means to get working day from next month date
        if( sizeof( $tempArray) == 0 ){
            $nextMonth = self::_getNextMonth($currentYear, $currentMonth);
            $monthDetails = self::getUserMonthAttendace($userid, $nextMonth['year'], $nextMonth['month'] );
            foreach( $monthDetails as $md ){
                $md_date = $md['date'];
                if( $md['day_type'] == 'WORKING_DAY' ){
                    $tempArray[] = $md;
                }
            }
        }
        $return = $tempArray[0];
        return $return;
    }

    // get employee last present day of the month
    public static function getEmployeeLastPresentDay( $userid, $year, $month ){
        $return = false;
        $monthDetails = self::getUserMonthAttendace($userid, $year, $month );
        $monthDetails = array_reverse($monthDetails);
        foreach( $monthDetails as $md ){
            if( $md['day_type'] == 'WORKING_DAY' ){
                $return = $md;
                break;
            }
        }
        return $return;
    }


    //policy documents
    public static function is_policy_documents_read_by_user( $userid ){
        $return = true;
        $allDocuments = self::NEW_getUserPolicyDocument( $userid );
        if( is_array($allDocuments) ){
            foreach( $allDocuments as $doc ){
                if( $doc['read'] != 1 ){
                    $return = false;
                }
            }
        }
        return $return;
    }

    public static function NEW_getUserPolicyDocument($userid) {

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
        return $arr;
    }

    // EMPLOYEE LIFE CYCLE
    static $ELC_stage_onboard = 5501;
    static $ELC_stage_employment = 5502;
    static $ELC_stage_termination = 5503;

    public static function getGenericElcList(){
        $allStages = array(
            array(
                'stage_id' => self::$ELC_stage_onboard,
                'id' => 5511,
                'text' => 'Create Hr System Account'
            ),
            array(
                'stage_id' => self::$ELC_stage_onboard,
                'id' => 5512,
                'text' => 'Create Gmail Account'
            ),
            array(
                'stage_id' => self::$ELC_stage_onboard,
                'id' => 5513,
                'text' => 'Create Slack Account'
            ),
            array(
                'stage_id' => self::$ELC_stage_onboard,
                'id' => 5514,
                'text' => 'Send Joining Mail'
            ),
            array(
                'stage_id' => self::$ELC_stage_onboard,
                'id' => 5515,
                'text' => 'Joining Document Signature '
            ),
            array(
                'stage_id' => self::$ELC_stage_onboard,
                'id' => 5516,
                'text' => 'Collect Documents'
            ),
            array(
                'stage_id' => self::$ELC_stage_onboard,
                'id' => 5517,
                'text' => 'Add To Biometric'
            ),
            array(
                'stage_id' => self::$ELC_stage_onboard,
                'id' => 5518,
                'text' => 'Assign Inventory'
            ),
            array(
                'stage_id' => self::$ELC_stage_onboard,
                'id' => 5519,
                'text' => 'Assign Stationary'
            ),
            array(
                'stage_id' => self::$ELC_stage_onboard,
                'id' => 5520,
                'text' => 'Assign Temporary ID Card'
            ),
            array(
                'stage_id' => self::$ELC_stage_onboard,
                'id' => 5521,
                'text' => 'Share Joining PPTs and Explain in meeting'
            ),
            array(
                'stage_id' => self::$ELC_stage_onboard,
                'id' => 5522,
                'text' => 'Share HR System PPT and Explain in meeting'
            ),
            array(
                'stage_id' => self::$ELC_stage_onboard,
                'id' => 5523,
                'text' => 'Fill Employee Profile'
            ),
            array(
                'stage_id' => self::$ELC_stage_onboard,
                'id' => 5524,
                'text' => 'Add Salary'
            ),
            array(
                'stage_id' => self::$ELC_stage_onboard,
                'id' => 5525,
                'text' => 'Training Service Agreement'
            ),
            array(
                'stage_id' => self::$ELC_stage_onboard,
                'id' => 5526,
                'text' => 'Update RH Leave'
            ),


            array(
                'stage_id' => self::$ELC_stage_employment,
                'id' => 5611,
                'text' => 'Send Confirmation Email',
                'sort' => 7
            ),
            array(
                'stage_id' => self::$ELC_stage_employment,
                'id' => 5612,
                'text' => 'Service agreement and signature',
                'sort' => 1
            ),
            array(
                'stage_id' => self::$ELC_stage_employment,
                'id' => 5613,
                'text' => 'Offer Letter Signed',
                'sort' => 2
            ),
            array(
                'stage_id' => self::$ELC_stage_employment,
                'id' => 5614,
                'text' => 'HR system update training completion date',
                'sort' => 3
            ),
            array(
                'stage_id' => self::$ELC_stage_employment,
                'id' => 5615,
                'text' => 'Upload documents in digital format',
                'sort' => 4
            ),
            array(
                'stage_id' => self::$ELC_stage_employment,
                'id' => 5616,
                'text' => 'Assign Salary',
                'sort' => 5
            ),
            array(
                'stage_id' => self::$ELC_stage_employment,
                'id' => 5617,
                'text' => 'Issue permanent ID card',
                'sort' => 6
            ),
            array(
                'stage_id' => self::$ELC_stage_employment,
                'id' => 5618,
                'text' => 'Update fingerprint (if required)',
                'sort' => 8
            ),
            array(
                'stage_id' => self::$ELC_stage_employment,
                'id' => 5619,
                'text' => 'Issue Training Certificate',
                'sort' => 9
            ),



            array(
                'stage_id' => self::$ELC_stage_termination,
                'id' => 5711,
                'text' => 'Email Feedback Document and Get it filled'
            ),
            array(
                'stage_id' => self::$ELC_stage_termination,
                'id' => 5712,
                'text' => 'Get NDA Agreement filled depends on employee'
            ),
            array(
                'stage_id' => self::$ELC_stage_termination,
                'id' => 5713,
                'text' => 'Experience Letter'
            ),
            array(
                'stage_id' => self::$ELC_stage_termination,
                'id' => 5714,
                'text' => 'Relieving Letter'
            ),
            array(
                'stage_id' => self::$ELC_stage_termination,
                'id' => 5715,
                'text' => 'Take ID Card'
            ),
            array(
                'stage_id' => self::$ELC_stage_termination,
                'id' => 5716,
                'text' => 'Check and Unassign Inventory (make sure all devices are working)'
            ),
            array(
                'stage_id' => self::$ELC_stage_termination,
                'id' => 5717,
                'text' => 'Put termination date in hr system and termination comments.'
            ),
            array(
                'stage_id' => self::$ELC_stage_termination,
                'id' => 5718,
                'text' => 'Disable the employee'
            ),
            array(
                'stage_id' => self::$ELC_stage_termination,
                'id' => 5719,
                'text' => 'Disable employee from gmail'
            ),
            array(
                'stage_id' => self::$ELC_stage_termination,
                'id' => 5710,
                'text' => 'Disable employee from slack'
            ),
            array(
                'stage_id' => self::$ELC_stage_termination,
                'id' => 5711,
                'text' => 'Remainder to send pending salary to employee on slack'
            ),

        );
        return $allStages;
    }

    public static function getElcStageName( $stageid ){
        $stageName = '';
        $stages = array();
        $stages[] = array( 'id' => self::$ELC_stage_onboard, 'name' => 'New Employee Onboarding' );
        $stages[] = array( 'id' => self::$ELC_stage_employment, 'name' => 'Employment Confirmation' );
        $stages[] = array( 'id' => self::$ELC_stage_termination, 'name' => 'Termination' );
        foreach( $stages as $stage ){
            if( $stage['id'] == $stageid ){
                $stageName = $stage['name'];
                break;
            }
        }
        return $stageName;
    }

    public static function sortElcStageSteps( $a, $b ){
        if ($a['sort'] == $b['sort']) {
            return 0;
        }
        return ($a['sort'] < $b['sort']) ? -1 : 1;
    }

    public static function getELC( $userid = false ){
        $allList = self::getGenericElcList();

        $employeeLifeCycleStepsDone = array();
        if( $userid != false ){
            $employeeLifeCycleStepsDone = self::getEmployeeLifeCycleStepsDone( $userid );
        }

        foreach( $allList as $k => $g ){
            $g_step_id = $g['id'];
            $status = 0;
            foreach( $employeeLifeCycleStepsDone as $d ){
                $d_elc_step_id = $d['elc_step_id'];
                if( $g_step_id == $d_elc_step_id ){
                    $status = 1;
                }
            }
            $allList[$k]['status'] = $status;
        }

        $return = array();


        foreach( $allList as $elc ){
            $sort = 0;
            if( isset( $elc['sort']) ){
                $sort = $elc['sort'];
            }

            if( array_key_exists( $elc['stage_id'], $return )){
                $return[ $elc['stage_id'] ]['steps'][] = array(
                    'id' => $elc['id'],
                    'text' => $elc['text'],
                    'status' => $elc['status'],
                    'sort' => $sort
                );

            }else{
                $return[ $elc['stage_id'] ] = array(
                    'stage_id' => $elc['stage_id'],
                    'text' => self::getElcStageName( $elc['stage_id'] ),
                );
                $return[ $elc['stage_id'] ]['steps'] = array();
                $return[ $elc['stage_id'] ]['steps'][] = array(
                    'id' => $elc['id'],
                    'text' => $elc['text'],
                     'status' => $elc['status'],
                     'sort' => $sort
                );
            }
        }


        //sort according to sort order
        if( sizeof( $return ) > 0 ){
            foreach( $return as $key => $stage ){
                if( isset($stage['steps'] ) && sizeof($stage['steps'])>0 ){
                    $steps = $stage['steps'];
                    usort( $steps, array( 'HR', 'sortElcStageSteps' ) );
                    $return[$key]['steps'] = $steps;
                }
            }
        }

        return $return;
    }

    public static function getEmployeeLifeCycleStepsDone( $userid ){
        $q = "select * from employee_life_cycle where userid=$userid";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        if( sizeof( $rows ) > 0 ){
            return $rows;
        }
        return array();
    }

    public static function getEmployeeLifeCycle( $userid ){

        $return = array();

        $employee_life_cycle =  self::getELC( $userid );

        $employeeLifeCycleStepsDone = self::getEmployeeLifeCycleStepsDone( $userid );
        if( sizeof($employeeLifeCycleStepsDone) > 0 ){
            $data_employee_life_cycle = $employee_life_cycle['employee_life_cycle'];
        }



        $return['error'] = 0;
        $return['message'] = '';
        $return['data'] = array();
        $return['data']['employee_life_cycle'] = $employee_life_cycle;
        //print_r( $return );
        return $return;
    }

    public static function updateELC( $elc_stepid, $userid ){
        $q = "select * from employee_life_cycle where userid=$userid AND elc_step_id=$elc_stepid";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        if (sizeof($rows) > 0) {
            $q2 = "DELETE FROM employee_life_cycle where userid=$userid AND elc_step_id=$elc_stepid";
            self::DBrunQuery($q2);
        } else {
            $q3 = "INSERT into employee_life_cycle ( userid, elc_step_id  ) VALUES ( $userid, $elc_stepid )";
            self::DBrunQuery($q3);
        }
        $return['error'] = 0;
        $return['message'] = 'Successfully Updated!!';
        $return['data'] = array();
        return $return;
    }


    // inventory functions

    // add inventory comment
    public static function addInventoryComment( $inventory_id, $updated_by_user_id,  $comment, $assign_unassign_user_id = null ){
        $db = self::getInstance();
        $mysqli = $db->getConnection();       
        $q = "INSERT into inventory_comments ( inventory_id, updated_by_user_id, comment ) VALUES ( $inventory_id, $updated_by_user_id, '$comment' )";
        if( $assign_unassign_user_id != null ){
            $q = "INSERT into inventory_comments ( inventory_id, updated_by_user_id, assign_unassign_user_id, comment ) VALUES ( $inventory_id, $updated_by_user_id, $assign_unassign_user_id, '$comment' )";
        }
        self::DBrunQuery($q);
        $last_inserted_id = mysqli_insert_id($mysqli);
        $return = array();
        $return['last_inserted_id'] = $last_inserted_id;
        return $return;
    }

    public static function api_addInventoryComment( $inventory_id, $updated_by_user_id,  $comment ){
        self::addInventoryComment($inventory_id, $updated_by_user_id,  $comment );   
        $return['error'] = 0;
        $return['message'] = 'Comment added successfully!!';
        $return['data'] = array();
        return $return;
    }

    // get inventory comments
    public static function getInventoryComments($inventory_id ){
        $q = "SELECT
            inventory_comments.*,
            p1.name as updated_by_user,
            p1.jobtitle as updated_by_user_job_title,
            p2.name as assign_unassign_user_name,
            p2.jobtitle as assign_unassign_job_title
            FROM inventory_comments
            LEFT JOIN user_profile as p1 ON inventory_comments.updated_by_user_id = p1.user_id
            LEFT JOIN user_profile as p2 ON inventory_comments.assign_unassign_user_id = p2.user_id
            where
            inventory_id=$inventory_id ORDER BY updated_at DESC";
        $runQuery = self::DBrunQuery($q);
        $comments = self::DBfetchRows($runQuery);
        return $comments;
    }

    // get inventory assigned history
    public static function getInventoryAssignedUsersHistory($inventory_id ){
        $q = "SELECT
            machines_user.*,
            user_profile.name,user_profile.jobtitle
            FROM machines_user
            LEFT JOIN user_profile ON machines_user.user_Id = user_profile.user_id
            where
            machine_id=$inventory_id ORDER BY updated_at DESC";
        $runQuery = self::DBrunQuery($q);
        $history = self::DBfetchRows($runQuery);
        return $history;
    }

    // get inventory history
    public static function getInventoryHistory( $inventory_id ){
        // this will combination of comments and history will be sorted by timestamp
        $inventoryComments = self::getInventoryComments($inventory_id);
        return $inventoryComments;
    }

    public static function addNewFile( $updated_by_user_id, $file_name, $google_drive_path = null ){
        $db = self::getInstance();
        $mysqli = $db->getConnection();
        $q = "INSERT into files ( updated_by_user_id, file_name, google_drive_path ) VALUES ( $updated_by_user_id, '$file_name', '$google_drive_path')";
        self::DBrunQuery($q);
        $file_id = mysqli_insert_id($mysqli);
        return $file_id;
    }

    public static function updateInventoryFileInvoice( $logged_user_id, $inventory_id, $file_id ){
        $q = "UPDATE machines_list set file_inventory_invoice=$file_id WHERE id = $inventory_id ";
        self::DBrunQuery($q);
        self::addInventoryComment($inventory_id, $logged_user_id,  "Inventory Invoice file is uploaded" );
    }
    public static function updateInventoryFileWarranty( $logged_user_id, $inventory_id, $file_id ){
        $q = "UPDATE machines_list set file_inventory_warranty=$file_id WHERE id = $inventory_id ";
        self::DBrunQuery($q);
        self::addInventoryComment($inventory_id, $logged_user_id,  "Inventory Warranty file is uploaded" );
    }
    public static function updateInventoryFilePhoto( $logged_user_id, $inventory_id, $file_id ){
        $q = "UPDATE machines_list set file_inventory_photo=$file_id WHERE id = $inventory_id ";
        self::DBrunQuery($q);
        self::addInventoryComment($inventory_id, $logged_user_id,  "Inventory Photo is uploaded" );
    }


    // check if a like timings already exits in DB
    public static function checkTimingExitsInAttendance( $userid, $timing ){
        $q = "SELECT * FROM `attendance` WHERE `user_id` = $userid AND `timing` LIKE '%$timing%'";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        if( sizeof($rows) > 0 ){
            return true;
        }
        return false;
    }


    // add manual attendance
    public static function addManualAttendance( $user_id, $time_type, $date, $manual_time, $reason ){
        $db = self::getInstance();
        $mysqli = $db->getConnection();

        // check if timing already exists in db or not
        $explodeTime = explode(" ",$manual_time);
        $checkTime = $date.' '.$explodeTime[0];      

        $checkIfTimingExits = self::checkTimingExitsInAttendance( $user_id, $checkTime );


        if( $checkIfTimingExits == false ){

            $final_date_time = $date .' '.$manual_time;
            // $raw_timestamp = strtotime( $raw_final_time );
            // $raw_date = new DateTime($raw_final_time);
            // $final_date_time =  $raw_date->format('m-d-Y h:i:sA');

            $reason_new = mysqli_real_escape_string($mysqli, $reason);
            
            $q = "INSERT into attendance_manual ( user_id, manual_time, reason ) VALUES ( $user_id, '$final_date_time', '$reason_new')";
            self::DBrunQuery($q);
            $last_inserted_id = mysqli_insert_id($mysqli);
            $userInfo = self::getUserInfo($user_id);
            $userInfo_name = $userInfo['name'];
            $slack_userChannelid = $userInfo['slack_profile']['slack_channel_id'];

            $message_to_user = "Hi $userInfo_name !!  \n You had requested for manual $time_type time : $final_date_time \n Reason - $reason \n You will be notified once it is approved/declined";
            $slackMessageStatus = self::sendSlackMessageToUser($slack_userChannelid, $message_to_user);

            $message_to_hr = "Hi HR !!  \n $userInfo_name had requested for manual $time_type time : $final_date_time \n Reason - $reason \n";
            
            $baseURL =  self::getBasePath();
            $approveLink = $baseURL."/attendance/API_HR/api.php?action=approve_manual_attendance&id=$last_inserted_id";
            $approveLinkMinutesLess = $baseURL."/attendance/API_HR/api.php?action=approve_manual_attendance&id=$last_inserted_id&deductminutes=30";
            $rejectLink = $baseURL."/attendance/API_HR/api.php?action=reject_manual_attendance&id=$last_inserted_id";

            $slackMessageActions = '[
                {
                  "type": "button",
                  "text": "Approve",
                  "url": "'.$approveLink.'",
                  "style": "primary"
                }, 
                {
                  "type": "button",
                  "text": "Reject",
                  "url": "'.$rejectLink.'",
                  "style": "danger"
                }
            ';

            if( $time_type == 'exit' ){
                $slackMessageActions .= ',{
                  "type": "button",
                  "text": "Approve With 30 Minutes Less",
                  "url": "'.$approveLinkMinutesLess.'",
                  "style": "primary"
                }';
            }
            $slackMessageActions .= "]";

            $slackMessageStatus = self::sendSlackMessageToUser("hr", $message_to_hr, false, $slackMessageActions);

            // $return['error'] = 0;
            // $return['message'] = 'Successfully Updated!!';
            // $return['data'] = array();
            // return $return;

            return "Time $final_date_time - Sent For Approval!!";

        } else {
            // $return['error'] = 0;
            // $return['message'] = "$checkTimeTiming already exists. No Need to update!!";
            // $return['data'] = array();
            // return $return;

            // also send message whatever time is already exist
            $final_date_time = $date .' '.$manual_time;

            $userInfo = self::getUserInfo($user_id);
            $userInfo_name = $userInfo['name'];
            $slack_userChannelid = $userInfo['slack_profile']['slack_channel_id'];

            $message_to_hr = "Hi HR !!  \n $userInfo_name had requested manual $time_type time : $final_date_time which is already exist.";

            $slackMessageStatus = self::sendSlackMessageToUser("hr", $message_to_hr, false);

            return "Time $checkTime already exists. No Need to update!!";
        }
    }

    public static function getManualAttendanceById( $id ){
        $q = "SELECT * from attendance_manual WHERE id=$id ";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRow($runQuery);
        if ($row == false) {
            return false;
        } else {
            return $row;
        }
    }

    // approve manual attendance 
    public static function approveManualAttendance( $id, $deductminutes ){
        $message = '';
        $row = self::getManualAttendanceById($id);
        $approvedWithLessTimeText = "";
        if( $row == false ){
            $message = 'No Record found!!';
        } else {
            if( $row['status'] !== null ){
                $message = 'Already processed!!';
            }else{
                $row_userid = $row['user_id'];
                $row_manual_time = $row['manual_time'];
                if( $deductminutes != false ){
                    $explodeActualTime = explode(" ", $row_manual_time);
                    $explodeActualTime_Date = $explodeActualTime[0];
                    $explodeActualTime_Time = $explodeActualTime[1];
                    $explodeActualTime_AMPM = $explodeActualTime[2];                    
                    $explodeDate = explode("-", $explodeActualTime_Date);
                    $n_month = $explodeDate[0];
                    $n_date = $explodeDate[1];
                    $n_year = $explodeDate[2];
                    $actualTimeModified = "$n_date-$n_month-$n_year $explodeActualTime_Time $explodeActualTime_AMPM";
                    $actualTimestamp = strtotime($actualTimeModified);
                    $deductSeconds = $deductminutes * 60;
                    $newTimestamp = $actualTimestamp - $deductSeconds;
                    $row_manual_time = date('m-d-Y h:i A', $newTimestamp);

                    $approvedWithLessTimeText = ": $deductminutes Minutes Less, ";
                }
                $q = "INSERT INTO attendance( id, user_id, timing ) VALUES ( 0, $row_userid, '$row_manual_time' )";
                $q1 = "UPDATE attendance_manual set status=0 WHERE id = $id ";
                self::DBrunQuery($q); 
                self::DBrunQuery($q1);  
                $message = 'Approved Successfully!!';

                $userInfo = self::getUserInfo($row_userid);
                $userInfo_name = $userInfo['name'];
                $slack_userChannelid = $userInfo['slack_profile']['slack_channel_id'];

                $message_to_user = "Hi $userInfo_name !!  \n Your manual attendance $approvedWithLessTimeText $row_manual_time is approved!!";
                $slackMessageStatus = self::sendSlackMessageToUser($slack_userChannelid, $message_to_user);

            }
        }
        $return['error'] = 0;
        $return['message'] = $message;
        $return['data'] = array();
        return $return;
    }

    // reject manual attendance
    public static function rejectManualAttendance( $id ){
        $message = '';
        $row = self::getManualAttendanceById($id);
        if( $row == false ){
            $message = 'No Record found!!';
        } else {
            if( $row['status'] !== null ){
                $message = 'Already processed!!';
            }else{
                $q = "UPDATE attendance_manual set status=0 WHERE id = $id ";
                self::DBrunQuery($q);  
                $message = 'Rejected Successfully!!';

                $row_userid = $row['user_id'];
                $row_manual_time = $row['manual_time'];

                $userInfo = self::getUserInfo($row_userid);
                $userInfo_name = $userInfo['name'];
                $slack_userChannelid = $userInfo['slack_profile']['slack_channel_id'];

                $message_to_user = "Hi $userInfo_name !!  \n Your manual attendance $row_manual_time is Rejected!!";
                $slackMessageStatus = self::sendSlackMessageToUser($slack_userChannelid, $message_to_user);

            }
        }
        $return['error'] = 0;
        $return['message'] = $message;
        $return['data'] = array();
        return $return;
    }

    public static function getBasePath(){
        $url = (isset($_SERVER['HTTPS']) ? "https" : "http") ."://".$_SERVER['HTTP_HOST'];
        if (strpos($url, 'dev.hr.') !== false) {
            $url = "http://dev.hr.excellencetechnologies.in/hr";
        }
        return $url;
    }

    public static function updateInventoryStatus( $logged_user_id, $inventory_id, $new_status ){
        $error = 0;
        $message = '';
        $inventory_detail = self::getMachineDetail($inventory_id);
        if( isset( $inventory_detail['data'] ) && isset( $inventory_detail['data']['id'] ) ){
            $old_status = $inventory_detail['data']['status'];
            $q = "UPDATE machines_list SET status='$new_status' WHERE id = $inventory_id ";
            self::DBrunQuery($q);
            // update inventory history
            $comment = "Status changed from $old_status to $new_status";
            self::addInventoryComment( $inventory_id, $logged_user_id,  $comment );
            $message = 'Updated successfully!!';


            // if new status is Sold, then inventory should be unassigned ( remove from machines_user table ) if it assigned to any user and 
            if( strtolower($new_status) == 'sold' ){
                $comment = "Status changes to sold ";
                self::removeMachineAssignToUser( $inventory_id , $logged_user_id, 'Inventory is sold, hence unassigned!' );
            }

        } else{
            $error = 1;
            $message = 'Inventory not found';
        }
        $return['error'] = $error;
        $return['message'] = $message;
        $return['data'] = array();
        return $return;
    }

    public static function getUnapprovedInventoryList(){
        $q = "select 
                machines_list.*,
                machines_user.user_Id,
                machines_user.assign_date,
                user_profile.name,
                user_profile.work_email,
                f1.file_name as fileInventoryInvoice,
                f2.file_name as fileInventoryWarranty,
                f3.file_name as fileInventoryPhoto 
                from 
                machines_list 
                left join machines_user on machines_list.id = machines_user.machine_id 
                left join user_profile on machines_user.user_Id = user_profile.user_Id 
                left join files as f1 ON machines_list.file_inventory_invoice = f1.id
                left join files as f2 ON machines_list.file_inventory_warranty = f2.id
                left join files as f3 ON machines_list.file_inventory_photo = f3.id
                where 
                machines_list.approval_status = 0
                ORDER BY 
                machines_list.id DESC";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        $return = array();
        $return['error'] = 0;
        $return['data'] = $rows;
        return $return;
    }


    public static function getUserInventories($userid) {
        $return = false;
        $q = "SELECT * FROM machines_user WHERE user_Id=$userid";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        if (sizeof($rows) == 0) {            
        } else {
            $return = $rows;
        }
        return $return;
    }

    // arun use this getMachineDetail function for audit details also
    public static function getInventoryFullDetails($id) {
        $row = array();
        $q = "select 
                machines_list.*,
                machines_user.user_Id,
                machines_user.assign_date,
                user_profile.name,
                user_profile.work_email,
                f1.file_name as fileInventoryInvoice,
                f2.file_name as fileInventoryWarranty,
                f3.file_name as fileInventoryPhoto
                from 
                machines_list 
                left join machines_user on machines_list.id = machines_user.machine_id
                left join user_profile on machines_user.user_Id = user_profile.user_Id
                left join files as f1 ON machines_list.file_inventory_invoice = f1.id
                left join files as f2 ON machines_list.file_inventory_warranty = f2.id
                left join files as f3 ON machines_list.file_inventory_photo = f3.id
                where 
                machines_list.id = $id";
        $runQuery = self::DBrunQuery($q);
        try {
            $row = self::DBfetchRow($runQuery);
            $r_error = 0;
            // get inventory comments
            $inventoryHistory = self::getInventoryHistory($id);
            $row['history'] = $inventoryHistory;

            $assignedUserInfo = array();
            if( $row['user_Id'] && !empty($row['user_Id']) ){
                $raw_assignedUserInfo = self::getUserInfo( $row['user_Id'] );
                $assignedUserInfo['name'] = $raw_assignedUserInfo['name'];
                $assignedUserInfo['jobtitle'] = $raw_assignedUserInfo['jobtitle'];
                $assignedUserInfo['work_email'] = $raw_assignedUserInfo['work_email'];
                $userProfileImage = '';
                try {
                    $userProfileImage = $userInfo['slack_profile']['profile']['image_192'];
                } catch (Exception $e) {

                }               
                $assignedUserInfo['profileImage'] = $userProfileImage;
            }

            $row['assigned_user_info'] = $assignedUserInfo;

            // get audit status of current year or month, if not exists will
            $currentMonthAuditStatus = array();
            $dateTimeData = self::_getDateTimeData();
            $currentMonthAuditStatus['year'] = $dateTimeData['current_year_number'];
            $currentMonthAuditStatus['month'] = $dateTimeData['current_month_number'];
            // if not exists status will be empty / false / null else will be and object
            $currentMonthAuditStatus['status'] = self::getInventoryAuditStatusforYearMonth( $id, $dateTimeData['current_year_number'], $dateTimeData['current_month_number'] );
            $row['audit_current_month_status'] = $currentMonthAuditStatus;

        } catch (Exception $e) {
            $row = false;
        }        
        return $row;
    }

    // return true or false is audit of inventory is pending for passed userid
    public static function isInventoryAuditPending( $userid ){
        $isAuditPending = false;
        $userInventories = self::getUserInventories($userid);
        if( $userInventories == false ){
            
        } else {
            foreach( $userInventories as $ui ){
                $i_details = self::getInventoryFullDetails( $ui['machine_id']);
                if( isset($i_details['audit_current_month_status'] ) &&  $i_details['audit_current_month_status']['status'] == false){
                    $isAuditPending = true;
                }
            }
        }
        return $isAuditPending;
    }


    public static function api_getMyInventories($userid){
        $error = 0;
        $message = '';
        $data = array();
        $userInventories = self::getUserInventories($userid);
        if( $userInventories == false ){
            $message = "No inventories assigned to user!!";
        } else {
            $user_assign_machine = array();
            foreach( $userInventories as $ui ){
                $i_details = self::getInventoryFullDetails( $ui['machine_id']);
                $user_assign_machine[] = $i_details;
            }
            $data['user_assign_machine'] = $user_assign_machine;
            $user_profile_detail = self::getUserInfo($userid);
            
            $upd = array();
            $upd['name'] = $user_profile_detail['name'];
            $upd['name'] = $user_profile_detail['name'];
            $upd['jobtitle'] = $user_profile_detail['jobtitle'];
            $upd['work_email'] = $user_profile_detail['work_email'];
            $upd['slack_profile'] = $user_profile_detail['slack_profile'];
            $upd['role_name'] = $user_profile_detail['role_name'];
            $upd['gender'] = $user_profile_detail['gender'];
            $upd['user_Id'] = $user_profile_detail['user_Id'];

            $data['user_profile_detail'] = $upd;
        }
        $return['error'] = $error;
        $return['message'] = $message;
        $return['data'] = $data;
        return $return;
    }

    public static function getUnassignedInventories(){
        // only retune inventories approved by admin        
        $return = false;
        $q = "select * from machines_list where id not in(select machine_id from machines_user) AND approval_status = 1";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        if (sizeof($rows) == 0) {            
        } else {
            $return = $rows;
        }
        return $return;
    }

    public static function api_getUnassignedInventories($userid){
        $error = 0;
        $message = '';
        $unassignedInventories = array();
        $unassignedInventoriesList = self::getUnassignedInventories($userid);
        if( $unassignedInventoriesList == false ){
            $message = "No unassigned inventories found!!";
        } else {            
            foreach( $unassignedInventoriesList as $ui ){
                $i_details = self::getInventoryFullDetails( $ui['id']);
                $unassignedInventories[] = $i_details;
            }
        }
        $return['error'] = $error;
        $return['message'] = $message;
        $return['data'] = $unassignedInventories;
        return $return;
    }


    public static function getUnapprovedInventories(){      
        $return = false;
        $q = "select * from machines_list where approval_status = 0";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        if (sizeof($rows) == 0) {            
        } else {
            $return = $rows;
        }
        return $return;
    }

    public static function api_getUnapprovedInventories($userid){
        $error = 0;
        $message = '';
        $unapprovedInventories = array();
        $unapprovedInventoriesList = self::getUnapprovedInventories($userid);
        if( $unapprovedInventoriesList == false ){
            $message = "No unapproved inventories found!!";
        } else {            
            foreach( $unapprovedInventoriesList as $ui ){
                $i_details = self::getInventoryFullDetails( $ui['id']);
                $unapprovedInventories[] = $i_details;
            }
        }
        $return['error'] = $error;
        $return['message'] = $message;
        $return['data'] = $unapprovedInventories;
        return $return;
    }

    public static function addInventoryAudit( $inventory_id, $audit_done_by_user_id, $audit_message ){
        $dateTimeData = self::_getDateTimeData();
        $audit_month = $dateTimeData['current_month_number'];
        $audit_year = $dateTimeData['current_year_number']; 
        $res = self::addInventoryComment( $inventory_id, $audit_done_by_user_id,  $audit_message );
        $inventory_comment_id = $res['last_inserted_id'];
        $q = "INSERT 
                INTO 
                inventory_audit_month_wise
                ( inventory_id, month, year, audit_done_by_user_id, inventory_comment_id )
                VALUES
                ( $inventory_id, $audit_month, $audit_year, $audit_done_by_user_id, $inventory_comment_id )
                ";
        self::DBrunQuery($q);
        return true;
    }

    public static function api_addInventoryAudit( $inventory_id, $logged_user_id, $audit_message ){
        self::addInventoryAudit( $inventory_id, $logged_user_id,  $audit_message );
        $error = 0;
        $message = 'Audit added for inventory successfully!!';
        $return['error'] = $error;
        $return['message'] = $message;
        $return['data'] = array();
        return $return;
    }

    // this is a Generic function to get information related to date time
    public static function _getDateTimeData(){
        $data = array();
        $currentTimeStamp = time();
        $data['current_timestamp'] = $currentTimeStamp;
        $data['current_date_number'] = date('d', $currentTimeStamp );
        $data['current_month_number'] = date('m', $currentTimeStamp );
        $data['current_year_number'] = date('Y', $currentTimeStamp );
        return $data;
    }

    /***************************************************/
    /*****************AUDIT FUNCTIONS*******************/
    /***************************************************/

    public static function getInvenoryAuditFullDetails( $audit_id ) {
        $return = array();
        $q = "select 
            inventory_audit_month_wise.id,
            inventory_audit_month_wise.inventory_id,
            inventory_audit_month_wise.month,
            inventory_audit_month_wise.year,
            inventory_audit_month_wise.updated_at,
            user_profile.name as audit_done_by_user_name,
            user_profile.work_email audit_done_by_user_email,
            inventory_comments.comment as audit_comment
            from 
            inventory_audit_month_wise
            left join user_profile on inventory_audit_month_wise.audit_done_by_user_id = user_profile.user_Id
            left join inventory_comments on inventory_comments.id = inventory_audit_month_wise.inventory_comment_id
            where 
            inventory_audit_month_wise.id = $audit_id";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        if (sizeof($rows) == 0) {

        } else {
            $return = $rows[0];
        }
        return $return;
    }

    public static function getInventoryAuditStatusforYearMonth( $inventory_id, $year, $month ){
        // if audit not exist for the current month and year will return false else will send details as an array
        $return = false;
        $q = "SELECT 
                * FROM inventory_audit_month_wise 
                WHERE 
                inventory_id = $inventory_id AND year = $year AND month = $month ";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        if (sizeof($rows) == 0) {

        } else {
            $row = $rows[0];
            $return = self::getInvenoryAuditFullDetails( $row['id'] );
        }
        return $return;
    }


    // eth operations

    public static function updateUserEthToken( $userid, $eth_token ){
        $q2 = "UPDATE user_profile SET eth_token = '$eth_token' WHERE user_Id = $userid ";
        $runQuery2 = self::DBrunQuery($q2);
        $return['error'] = 0;
        $return['message'] = "Eth Token Updated!!";
        $return['data'] = array();
        return $return;
    }

    public static function getInventoriesAuditStatusForYearMonth( $month, $year ){

        $return = array();
        $data = array();
        $error = 0;
        $message = ""; 

        $q = "SELECT 
        machines_list.id, 
        machines_list.machine_type, 
        machines_list.machine_name, 
        machines_user.machine_id,
        machines_user.user_Id as assigned_user_id,
        files.file_name, 
        inventory_audit_month_wise.id as audit_id, 
        inventory_audit_month_wise.inventory_id, 
        inventory_audit_month_wise.month, 
        inventory_audit_month_wise.year, 
        inventory_audit_month_wise.audit_done_by_user_id,
        inventory_comments.comment, 
        up_audit.name as audit_done_by,
        up_assign.name as assigned_to
        FROM 
        machines_list 
        left join files on machines_list.file_inventory_photo = files.id 
        left join inventory_audit_month_wise on machines_list.id = inventory_audit_month_wise.inventory_id 
        AND inventory_audit_month_wise.month = $month 
        AND inventory_audit_month_wise.year = $year 
        left join user_profile as up_audit on inventory_audit_month_wise.audit_done_by_user_id = up_audit.user_Id
        left join inventory_comments on inventory_audit_month_wise.inventory_comment_id = inventory_comments.id 
        left join machines_user on machines_list.id = machines_user.machine_id
        left join user_profile as up_assign on machines_user.user_Id = up_assign.user_Id
        ORDER BY id DESC";
        
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        $inventoriesCount = sizeof($rows);
        $auditDoneCount = 0;
        $auditPendingCount = 0;
        $unassignedInventoriesCount = 0;
        $unassignedInventories = self::getUnassignedInventories();
        if($unassignedInventories){
            $unassignedInventoriesCount = sizeof($unassignedInventories);
        }
                
        if ($inventoriesCount == 0) {
            $message = "No Records Found.";
            
        } else {                       
            foreach($rows as $row){
                if(!isset($row['audit_id']) && $row['audit_id'] == ""){
                    $auditPendingCount++;
                } else {
                    $auditDoneCount++;
                } 
            }
            
            $message = "Inventory Audit List";
            $data = [
                'stats' => [
                    'total_inventories' => $inventoriesCount,
                    'audit_done' => $auditDoneCount,
                    'audit_pending' => $auditPendingCount,
                    'unassigned_inventories' => $unassignedInventoriesCount
                ],
                'audit_list' => $rows
            ];
        }
        
        $return = [
            'error' => $error,
            'message' => $message,
            'data' => $data
        ];
        
        return $return;
    }

    // api call
    public static function api_getAverageWorkingHours( $startDate, $endDate ){
        if( $startDate == null || $endDate == null ){
            $d = self::_getDateTimeData();
            $endDate = $d['current_year_number'].'-'.$d['current_month_number'].'-'.$d['current_date_number'];
            $startDate = date("Y-m-d", strtotime($endDate . '-7 day'));
        }
        $DATA = array();
        $dates = self::_getDatesBetweenTwoDates( $startDate, $endDate );       
        $enabledUsersList = self::getEnabledUsersList();

        $hideUsersArray = array('302','300','415','420');

        foreach ($enabledUsersList as $u) {
            $userid = $u['user_Id'];
            // hide details of specific users
            if( in_array($userid, $hideUsersArray, true)){
                continue;
            }

            $timings = array();
            foreach($dates as $d ){
                $m = date('m', strtotime($d));
                $y = date('Y', strtotime($d));
                $d = date('d', strtotime($d));
                $nd = $m.'-'.$d.'-'.$y;
                $q = "select * from attendance where user_id=$userid AND timing like '%$nd%'";
                $runQuery = self::DBrunQuery($q);
                $rows = self::DBfetchRows($runQuery);
                $allMonthAttendance = array();
                foreach ($rows as $key => $d) {
                    $d_timing = $d['timing'];
                    $d_timing = str_replace("-", "/", $d_timing);
                    // check if date and time are not there in string
                    if( strlen($d_timing) < 10 ){

                    } else {
                        $d_full_date = date("Y-m-d", strtotime($d_timing));
                        $d_timestamp = strtotime($d_timing);
                        $d_month = date("m", $d_timestamp);
                        $d_year = date("Y", $d_timestamp);
                        $d_date = date("d", $d_timestamp);                        
                        $d['timestamp'] = $d_timestamp;
                        $timings[$nd][] = $d;
                    }
                }
            }

            if( sizeof($timings ) > 0 ){
                $totalPresentDays = 0;
                $totalInsideTimeInSeconds = 0;
                foreach( $timings as $pp ){
                    $aa = self::getInsideOfficeTime( $pp );
                    if( $aa['inside_time_seconds'] > 0 ){
                        $totalPresentDays++;
                        $totalInsideTimeInSeconds += $aa['inside_time_seconds'];
                    }
                }

                $average_seconds = $totalInsideTimeInSeconds / $totalPresentDays;

                if( is_nan( $average_seconds) ){

                } else {
                    $DATA[$userid] = array();
                    $DATA[$userid]['name'] = $u['name'];
                    $DATA[$userid]['jobtitle'] = $u['jobtitle'];
                    $DATA[$userid]['totalPresentDays'] = $totalPresentDays;
                    $DATA[$userid]['totalInsideTimeInSeconds'] = $totalInsideTimeInSeconds;
                    $DATA[$userid]['average_inside_seconds'] = $average_seconds;
                    $aaa = self::_secondsToTime( $average_seconds);
                    $average_inside_hours = $aaa['h']. ' Hrs ' . $aaa['m'] . ' Mins';
                    $DATA[$userid]['average_inside_hours'] = $average_inside_hours;
                }
            }
        }

        $sort_average_inside_seconds  = array_column($DATA, 'average_inside_seconds');
        
        array_multisort($sort_average_inside_seconds, SORT_ASC, $DATA);

        $error = 0;
        $return['error'] = $error;
        $return['message'] = "";
        $return['data'] = $DATA;
        return $return;
    }

    public static function getAllUsers(){
        $q = " SELECT users.*, user_profile.* FROM users left join user_profile on users.id = user_profile.user_Id ";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        return $rows;
    }

    public static function getEmployeesHistoryStats(){
        $r_error = 0;
        $r_data = array();
        $return = array();
        $stats = array();
        $jt_stats = array();
        
        $all_employees_list = self::getAllUsers();

        foreach($all_employees_list as $key => $employee){            
            $join_year = date('Y', strtotime($employee['dateofjoining']));        
            $terminate_year = date('Y', strtotime($employee['termination_date']));
            $stats['total_employees']++;
            if($employee['status'] == 'Enabled') {
                $stats['enabled_employees']++;
            }
            if($employee['status'] == 'Disabled') {
                $stats['disabled_employees']++;
            }
            if( $join_year > 0 ){
                if( isset($jt_stats[$join_year]) ){
                    $jt_stats[$join_year]['joining']++;
                } else {
                    $jt_stats[$join_year]['joining'] = 1;
                }
                if( !isset($jt_stats[$join_year]['termination']) ){
                    $jt_stats[$join_year]['termination'] = 0;
                }
            }            

            if( $terminate_year > 0 ){
                if( isset($jt_stats[$terminate_year]) ){
                    $jt_stats[$terminate_year]['termination']++;
                } else {
                    $jt_stats[$terminate_year]['termination'] = 1;
                }
                if( !isset($jt_stats[$terminate_year]['joining']) ){
                    $jt_stats[$terminate_year]['joining'] = 0;
                }
            }
            
        }
        
        $stats['joining_termination_stats'] = $jt_stats;
        $r_data = [
            'stats' => $stats
        ];
        $return = [
            'error' => $r_error,
            'data' => $r_data
        ];

        return $return;
    }

    public static function getLeavesForYearMonth( $year, $month ){
        $year_month = $year . "-" . $month;
        $q = " SELECT * FROM leaves WHERE from_date LIKE '$year_month%' ";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        return $rows;
    }

    public static function API_getEmployeesLeavesStats( $year, $month ){
        $r_error = 0;
        $r_data = array();
        $stats = array();
        $return = array();
        $enableEmployees = self::getEnabledUsersList();
        $totalEmployees = count($enableEmployees);
        $monthly_leaves = self::getLeavesForYearMonth( $year, $month );
        $days = self::getGenericMonthSummary( $year, $month );        
        $removableKeys = ['day_text', 'in_time', 'out_time', 'total_time', 'extra_time', 'text', 'admin_alert', 'admin_alert_message', 'orignal_total_time'];
        foreach( $monthly_leaves as $leave ){ 
            $days_between_leaves = self::getDaysBetweenLeaves( $leave['from_date'], $leave['to_date'] ); 
            foreach($days as $key => $day){   
                $days[$key]['total_employees'] = $totalEmployees;
                $days[$key]['day'] = substr($day['day'], 0, 3);
                foreach( $removableKeys as $removableKey ){
                    unset($days[$key][$removableKey]);
                }
                foreach($days_between_leaves['data']['days'] as $day_between_leave){
                    if( strtolower($day_between_leave['type']) == 'working' ){
                        if($day_between_leave['full_date'] == $day['full_date']){
                            if($leave['status'] == 'Approved'){
                                $days[$key]['approved']++;
                            }
                            if($leave['status'] == 'Pending'){
                                $days[$key]['pending']++;
                            }
                            if($leave['status'] == 'Rejected'){
                                $days[$key]['rejected']++;
                            }
                            if($leave['status'] == 'Cancelled'){
                                $days[$key]['cancelled']++;
                            }
                            if($leave['status'] == 'Cancelled Request'){
                                $days[$key]['cancelled_request']++;
                            }                            
                        } 
                    }
                } 
            }            
        }
        foreach( $days as $key => $day ){
            if( !isset($day['approved']) ) {
                $days[$key]['approved'] = 0;
            }
            if( !isset($day['pending']) ){
                $days[$key]['pending'] = 0;
            }
            if( !isset($day['rejected']) ){
                $days[$key]['rejected'] = 0;
            }
            if( !isset($day['cancelled']) ){
                $days[$key]['cancelled'] = 0;
            }
            if( !isset($day['cancelled_request']) ){
                $days[$key]['cancelled_request'] = 0;
            }
            $stats[]= $days[$key];
        }        
        $r_data = [
            'stats' => $stats
        ];
        $return = [
            'error' => $r_error,
            'data' => $r_data
        ];     
        return $return;           
    }

    public function API_updateUserMetaData( $user_id, $data ){
        $r_error = 0;
        $r_data = [];
        $meta_data = [];
        $userInfo = self::getUserInfo($user_id);        
        if( isset($userInfo['meta_data']) && $userInfo['meta_data'] != "" ){            
            $user_meta_data = json_decode($userInfo['meta_data'], true);                         
            foreach( $user_meta_data as $key => $value ){                
                $meta_data[$key] = $value;
                foreach( $data as $k => $dt ){
                    if( $k != $key ){
                        $meta_data[$k] = $dt;
                    } else {
                        $meta_data[$key] = $dt;
                    }
                }
            }

        } else {
            foreach( $data as $k => $dt ){
                $meta_data[$k] = $dt;
            }
        }        
        $update_meta_data = json_encode($meta_data);        
        $q = " UPDATE user_profile SET meta_data = '$update_meta_data' WHERE user_Id = '$user_id' ";
        $runQuery = self::DBrunQuery($q);
        $r_data = [
            'meta_data' => $meta_data
        ];
        $return = [
            'error' => $r_error,
            'message' => "Meta Data Updated Successfully",
            'data' => $r_data
        ];
        return $return;
    }

    public function API_deleteUserMetaData( $user_id, $keys ){
        $r_error = 0;
        $r_message = "";
        $meta_data = [];
        $userInfo = self::getUserInfo($user_id);
        $user_meta_data = json_decode($userInfo['meta_data'], true);
        foreach( $user_meta_data as $key => $value ){
            $meta_data[$key] = $value; 
            foreach( $keys as $k ){
                if( $key == $k ){
                    unset($meta_data[$key]);
                }
            }
        }
        if( count($user_meta_data) == count($meta_data) ){
            $r_error = 1;
            $r_message = "Key not found";
        } else {
            $r_message = "Key Deleted";
        }
        $update_meta_data = json_encode($meta_data); 
        if( count($meta_data) > 0 ){
            $q = " UPDATE user_profile SET meta_data = '$update_meta_data' WHERE user_Id = '$user_id' ";
        } else {
            $q = " UPDATE user_profile SET meta_data = '' WHERE user_Id = '$user_id' ";
        }
        $runQuery = self::DBrunQuery($q);
        $return  = [
            'error' => $r_error,
            'message' => $r_message
        ];
        return $return;        
    }

    public static function API_getUserRecentPunchTime( $user_id ){
        $r_error = 0;
        $r_data = [];
        $q = " SELECT user_id,timing FROM attendance WHERE user_id = '$user_id' ORDER BY user_id DESC LIMIT 1 ";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);           
        if( count($rows) > 0 ){
            $timing = $rows[0]['timing'];
            $explodeDateTime = explode(" ", $timing);
            $date = $explodeDateTime[0];
            $dateExplode = explode("-", $date);
            $newDate = $dateExplode[2] . "-" . $dateExplode[0] . "-" . $dateExplode[1];        
            $time = $explodeDateTime[1];
            $newDateTime = $newDate . ' ' . $time;
            $recentPunchTime = date('dS M h:ia', strtotime($newDateTime));  
            $r_data = [
                'recent_punch_time' => [
                    'user_id' => $rows[0]['user_id'],
                    'timing' => $recentPunchTime
                ]
            ];
        } else {
            $r_error = 1;
            $r_data = [
                'message' => "Employee didn't punched yet"
            ];
        }
        $return = [
            'error' => $r_error,
            'data' => $r_data
        ];
        return $return;
    }

    public static function API_getUserMetaData( $user_id ){
        $r_error = 0;
        $r_data = [];
        $userInfo = self::getUserInfo( $user_id );        
        if( isset($userInfo['meta_data']) && $userInfo['meta_data'] != "" ){
            $userMetaData = json_decode($userInfo['meta_data']);
            $r_data = [
                'meta_data' => $userMetaData
            ];
        } else {
            $r_error = 1;
            $r_data['message'] = "Meta Data Not Found";
        }
        $return = [
            'error' => $r_error,
            'data' => $r_data
        ];
        return $return;
    }

    public static function API_getUserPunchesByDate( $user_id, $date ){
        $r_error = 0;
        $r_data = [];
        $userPunches = [];
        $explodeDate = explode( "-", $date );
        $day = $explodeDate[0];
        $month = $explodeDate[1];
        $year = $explodeDate[2];
        $newDate = $month . "-" . $day . "-" . $year;
        $userMonthPunching = self::getUserMonthPunching( $user_id, $year, $month );        
        if( count($userMonthPunching[$day]['user_punches']) > 0 ){
            foreach( $userMonthPunching[$day]['user_punches'] as $key => $punches ){            
                $explodePunchTime = explode(" ", $punches['timing']);                
                $punchDate = $explodePunchTime[0];
                if( $newDate == $punchDate ){                    
                    $userPunches[] = $punches;
                }
            }

            $r_data['punches'] = $userPunches;

        } else {
            $r_error = 1;
            $r_data['message'] = "Employee had not punched that day";
        }        
        $return = [
            'error' => $r_error,
            'data' => $r_data
        ];
        return $return;
    }

    public function API_getEmployeesMonthlyAttendance( $year, $month ){
        $r_error = 0;
        $r_data = [];
        $userStats = [];
        $usersAttendanceSummary = self::getMonthAttendaceSummary( $year, $month );          
        $userStats['year'] = $year;
        $userStats['month'] = $month;
        foreach( $usersAttendanceSummary['data']['usersAttendance'] as $key => $userAttendance ){
            $userStats['attendance_info'][$key]['userid'] = $userAttendance['userid'];
            $userStats['attendance_info'][$key]['name'] = $userAttendance['name'];
            $userStats['attendance_info'][$key]['jobtitle'] = $userAttendance['jobtitle'];
            $userStats['attendance_info'][$key]['working_days'] = $userAttendance['monthSummary']['WORKING_DAY'];
            $userStats['attendance_info'][$key]['non_working_days'] = $userAttendance['monthSummary']['NON_WORKING_DAY'];
            $userStats['attendance_info'][$key]['leave_days'] = $userAttendance['monthSummary']['LEAVE_DAY'];
            $userStats['attendance_info'][$key]['half_days'] = $userAttendance['monthSummary']['HALF_DAY'];
            $userStats['attendance_info'][$key]['present_days'] = 0;
            $userStats['attendance_info'][$key]['absent_days'] = 0;
            foreach($userAttendance['attendance'] as $attendance){                
                if( isset($attendance['in_time']) && $attendance['in_time'] != "" ){
                    $userStats['attendance_info'][$key]['present_days']++;
                }
                if( $attendance['day_type'] == 'WORKING_DAY' && ( !isset($attendance['in_time']) || $attendance['in_time'] == "" ) ){
                    $userStats['attendance_info'][$key]['absent_days']++;
                }
            }            
        }
        $r_data['attendance_summary'] = $userStats;
        $return = [
            'error' => $r_error,
            'data' => $r_data
        ];
        return $return;
    }

    public static function API_addAttendanceKeys( $userid_key = false, $timing_key = false ){
        $r_error = 0;
        $r_data = array();
        $userIdKeys = [];
        $timingKeys = [];
        $q = " SELECT * FROM config WHERE type = 'attendance_csv' ";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRow($runQuery);
        if( sizeof($row) > 0 ){
            $attendance_csv_keys = json_decode( $row['value'], true );
            foreach( $attendance_csv_keys as $key => $atCsvKey ){ 
                if( $key == 'user_id' ){
                    $userIdKeys = $atCsvKey;
                }
                if( $key == 'time' ){
                    $timingKeys = $atCsvKey;
                }
            }
            if( isset($userid_key) && $userid_key != "" ){
                if( in_array( $userid_key, $userIdKeys ) ){
                    $r_error = 1;
                    $r_data['message'][] = "UserId key already exist.";
                } else {
                    array_push( $userIdKeys, $userid_key );  
                    $keyValue = " {\"user_id\":" . json_encode($userIdKeys) . ", \"time\":" . json_encode($timingKeys) . "}";                  
                    $q = " UPDATE config SET value = '$keyValue' WHERE type = 'attendance_csv' ";
                    $runQuery = self::DBrunQuery($q);
                    if($runQuery){
                        $r_data['message'][] = "UserId key updated.";
                    } else {
                        $r_error = 1;
                        $r_data['message'][] = "UserId key updated failed.";
                    }
                }
            }

            if( isset($timing_key) && $timing_key != "" ){
                if( in_array( $timing_key, $timingKeys ) ){
                    $r_error = 1;
                    $r_data['message'][] = "Time key already exist.";
                } else {
                    array_push( $timingKeys, $timing_key );  
                    $keyValue = " {\"user_id\":" . json_encode($userIdKeys) . ", \"time\":" . json_encode($timingKeys) . "}";                  
                    $q = " UPDATE config SET value = '$keyValue' WHERE type = 'attendance_csv' ";
                    $runQuery = self::DBrunQuery($q);
                    if($runQuery){
                        $r_data['message'][] = "Time key updated.";
                    } else {
                        $r_error = 1;
                        $r_data['message'][] = "Time key updated failed.";
                    }
                }
            }
            
        } else {
            if( isset($userid_key) && $userid_key != "" ){
                array_push( $userIdKeys, $userid_key );  
            }
            if( isset($timing_key) && $timing_key != "" ){
                array_push( $timingKeys, $timing_key );  
            }
            $keyValue = " {\"user_id\":" . json_encode($userIdKeys) . ", \"time\":" . json_encode($timingKeys) . "}";
            $q = " INSERT INTO config( type, value ) VALUES( 'attendance_csv', '$keyValue' ) ";
            $runQuery = self::DBrunQuery($q);
            if($runQuery){
                $r_data['message'][] = "Row created and Key added";
            } else {
                $r_error = 1;
                $r_data['message'][] = "Row creation failed";
            }
        }        
        $return = [
            'error' => $r_error,
            'data' => $r_data
        ];
        return $return;        
    }

    public static function API_getAttendanceKeys(){
        $r_error = 0;
        $r_data = array();
        $attendanceKeys = array();
        $q = " SELECT * FROM config WHERE type = 'attendance_csv' ";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRow($runQuery);        
        if( sizeof($row) > 0 ){
            $attendance_csv_keys = json_decode( $row['value'], true );            
        } else{
            $r_data['message'] = "No keys found";
        }
        $r_data = $attendance_csv_keys;
        $return = [
            'error' => $r_error,
            'data' => $r_data
        ];
        return $return;
    }

    public static function API_deleteAttendanceKeys( $field_name, $key_text ){
        $r_error = 0;
        $r_data = array();
        $q = " SELECT * FROM config WHERE type = 'attendance_csv' ";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRow($runQuery);
        if( sizeof($row) > 0 ){
            $attendance_csv_keys = json_decode( $row['value'], true );
            foreach( $attendance_csv_keys as $key => $atCsvKey ){ 
                if( $key == 'user_id' ){
                    $userIdKeys = $atCsvKey;
                }
                if( $key == 'time' ){
                    $timingKeys = $atCsvKey;
                }
            }            
            if( $field_name == 'user_id' ){    
                if( in_array( $key_text, $userIdKeys ) ){
                    $start = "[";
                    $end = "]";
                    $userIdKeysString = "";
                    $timeKeysString = "";
                    foreach($userIdKeys as $k => $uid){                
                        if( $uid == $key_text ){
                            unset($userIdKeys[$k]);
                            continue;
                        }
                        $userIdKeysString = $userIdKeysString . "\"" . $uid . "\","; 
                    }
                    foreach($timingKeys as $k => $time){                                    
                        $timeKeysString = $timeKeysString . "\"" . $time . "\","; 
                    }
                    $userIdKeysString = $start . rtrim($userIdKeysString, ",") . $end; 
                    $timeKeysString = $start . rtrim($timeKeysString, ",") . $end; 
                    
                    $keyValue = " {\"user_id\":" . $userIdKeysString . ", \"time\":" . $timeKeysString . "}";                
                    $q = " UPDATE config SET value = '$keyValue' WHERE type = 'attendance_csv' ";
                    $runQuery = self::DBrunQuery($q);
                    if($runQuery){
                        $r_data['message'] = "UserId key deleted";
                    } else {
                        $r_error = 1;
                        $r_data['message'] = "UserId key deletion failed";
                    }
                } else {
                    $r_data['message'] = "UserId key not found";
                }            
                
            } else if( $field_name == 'time' ){
                if( in_array( $key_text, $timingKeys ) ){
                    $start = "[";
                    $end = "]";
                    $userIdKeysString = "";
                    $timeKeysString = "";
                    foreach($timingKeys as $k => $time){                
                        if( $time == $key_text ){
                            unset($timingKeys[$k]);
                            continue;
                        }
                        $timeKeysString = $timeKeysString . "\"" . $time . "\","; 
                    }
                    foreach($userIdKeys as $k => $uid){                                    
                        $userIdKeysString = $userIdKeysString . "\"" . $uid . "\","; 
                    }
                    $userIdKeysString = $start . rtrim($userIdKeysString, ",") . $end; 
                    $timeKeysString = $start . rtrim($timeKeysString, ",") . $end; 
                    
                    $keyValue = " {\"user_id\":" . $userIdKeysString . ", \"time\":" . $timeKeysString . "}";                
                    $q = " UPDATE config SET value = '$keyValue' WHERE type = 'attendance_csv' ";
                    $runQuery = self::DBrunQuery($q);
                    if($runQuery){
                        $r_data['message'] = "Time key deleted";
                    } else {
                        $r_error = 1;
                        $r_data['message'] = "Time key deletion failed";
                    }
                } else {
                    $r_data['message'] = "Time key not found";
                }
                
            } else {
                $r_data['message'] = "Key not found in both field";
            }
        } else {
            $r_data['message'] = "No Data found";
        }
        $return = [
            'error' => $r_error,
            'data' => $r_data
        ];
        return $return;        
    }

    public static function API_resetPasswordConfig( $no_of_days, $status ){
        $r_error = 0;
        $r_data = array();
        $q = " SELECT * FROM config WHERE type = 'reset_password' ";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        $date = date('d-m-Y');
        if( sizeof($rows) > 0 ){
            if( isset($no_of_days) && $no_of_days != "" ){
                $configValue = " {\"days\":\"" . $no_of_days . "\", \"status\":\"" . $status . "\", \"last_updated\":\"" . $date . "\"}";
                $q = " UPDATE config SET value = '$configValue' WHERE type = 'reset_password' ";
                $runQuery = self::DBrunQuery($q);
                if($runQuery){
                    $r_data['message'] = "Config updated successfully";     
                } else {
                    $r_error = 1;
                    $r_data['message'] = "Config updation failed";     
                }
            } else {
                $r_data['message'] = "Please provide interval";     
            }
        } else {
            if( isset($no_of_days) && $no_of_days != "" ){
                $configValue = " {\"days\":\"" . $no_of_days . "\", \"status\":\"" . $status . "\", \"last_updated\":\"" . $date . "\"}";
                $q = " INSERT INTO config( type, value ) VALUES( 'reset_password', '$configValue' ) ";
                $runQuery = self::DBrunQuery($q);
                if($runQuery){
                    $r_data['message'] = "Config inserted successfully";     
                } else {
                    $r_error = 1;
                    $r_data['message'] = "Config insertion failed";     
                }
            } else {
                $r_data['message'] = "Please provide interval";     
            }
        }
        $return = [
            'error' => $r_error,
            'data' => $r_data
        ];
        return $return;
    }

    public static function API_getResetPasswordConfig(){
        $r_error = 0;
        $q = " SELECT * FROM config WHERE type = 'reset_password' ";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRow($runQuery);        
        $row['value'] = json_decode($row['value'], true);
        $return = [
            'error' => $r_error,
            'data' => $row
        ];
        return $return;
    }

}

new HR();
?>
