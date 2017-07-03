<?php

require_once 'c-database.php';
require_once 'c-roles.php';
require_once 'c-jwt.php';

//comman format for dates = "Y-m-d" eg "04/07/2016"

class HR extends DATABASE {

    use Roles;

    const DEFAULT_WORKING_HOURS = "09:00";

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
            $userInfo = self::getUserInfo($userid);

            $userProfileImage = '';
            try {
                $userProfileImage = $userInfo['slack_profile']['profile']['image_192'];
            } catch (Exception $e) {
                
            }

            if ($userInfo == false) {
                $r_message = "Invalid Login";
            } else {
                $r_error = 0;
                $r_message = "Success Login";

                $u = array(
                    "id" => $userInfo['user_Id'],
                    "username" => $userInfo['username'],
                    "role" => $userInfo['type'],
                    "name" => $userInfo['name'],
                    "jobtitle" => $userInfo['jobtitle'],
                    "profileImage" => $userProfileImage,
                    "login_time" => time(),
                    "login_date_time" => date('d-M-Y H:i:s')
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

                $jwtToken = JWT::encode($u, self::JWT_SECRET_KEY);

                self::insertToken($userInfo['user_Id'], $jwtToken);
                $r_data = array(
                    "token" => $jwtToken,
                    "userid" => $userInfo['user_Id']
                );
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

    public static function getEnabledUsersListWithoutPass() {

        $row = self::getEnabledUsersList();
        foreach ($row as $val) {
            unset($val['password']);
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
        );
        return $obj;
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

    public static function getGenericMonthSummary($year, $month) {
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
        $genericMonthDays = self::getGenericMonthSummary($year, $month);
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

    public static function _beautyMonthSummary($monthAttendace) {

        $r_actual_working_hours = $r_completed_working_hours = $r_pending_working_hours = 0;

        $WORKING_DAYS = $NON_WORKING_DAYS = $LEAVE_DAYS = $HALF_DAYS = 0;

        $r_actual_working_seconds = $r_completed_working_seconds = $r_pending_working_seconds = 0;


        foreach ($monthAttendace as $pp) {
            $day_type = $pp['day_type'];
            if ($day_type == 'WORKING_DAY') {
                $WORKING_DAYS++;
                $r_completed_working_seconds += $pp['total_time'];
            } else if ($day_type == 'NON_WORKING_DAY') {
                $NON_WORKING_DAYS++;
            } else if ($day_type == 'LEAVE_DAY') {
                $LEAVE_DAYS++;
            } else if ($day_type == 'HALF_DAY') {
                $HALF_DAYS++;
            }
        }

        //-----------------------------
        $r_actual_working_seconds = $WORKING_DAYS * 9 * 60 * 60;
        $r_pending_working_seconds = $r_actual_working_seconds - $r_completed_working_seconds;
        //-----------------------------
        $a = self::_secondsToTime($r_actual_working_seconds);
        $r_actual_working_hours = $a['h'];

        $b = self::_secondsToTime($r_completed_working_seconds);
        $r_completed_working_hours = $b['h'] . ' Hrs ' . $b['m'] . ' Mins';

        $c = self::_secondsToTime($r_pending_working_seconds);
        $r_pending_working_hours = $c['h'] . ' Hrs ' . $c['m'] . ' Mins';
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

        $r_error = 0;
        $return = array();
        $return['error'] = $r_error;
        $r_data['message'] = $r_message;
        $return['data'] = $r_data;

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

    public static function sendSlackMessageToUser($channelid, $message, $auth_array = false) {
        $return = false;
       
        $message = '[{"text": "' . $message . '", "fallback": "Message Send to Employee", "color": "#36a64f "}]';
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

        if ($year == false) {
            $list = $rows;
        } else {
            foreach ($rows as $pp) {
                $h_date = $pp['date'];
                $h_year = date('Y', strtotime($h_date));
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
        $return = array();
        $return['error'] = $r_error;
        $r_data['message'] = "";
        $r_data['holidays'] = $list;
        $return['data'] = $r_data;

        return $return;
    }

    public static function applyLeave($userid, $from_date, $to_date, $no_of_days, $reason, $day_status, $leave_type, $late_reason, $pending_id = false) {
        //date format = Y-m-d
        $applied_date = date('Y-m-d');
        $reason = self::DBescapeString($reason);
        $q = "INSERT into leaves ( user_Id, from_date, to_date, no_of_days, reason, status, applied_on, day_status,leave_type,late_reason ) VALUES ( $userid, '$from_date', '$to_date', $no_of_days, '$reason', 'Pending', '$applied_date', '$day_status','$leave_type','$late_reason' )";

        $r_error = 0;
        $r_message = "";

        try {
            self::DBrunQuery($q);
            $success = true;
            $r_message = "Leave applied.";
        } catch (Exception $e) {
            $r_error = 1;
            $r_message = "Error in applying leave.";
        }

        if ($r_error == 0) {
            if ($pending_id != false) {
                $q1 = "UPDATE users_previous_month_time SET status = 'Leave applied for previous month pending time'  Where id = $pending_id";
                self::DBrunQuery($q1);
            }
            ////send  slack message to user && HR
            $userInfo = self::getUserInfo($userid);
            $userInfo_name = $userInfo['name'];
            $slack_userChannelid = $userInfo['slack_profile']['slack_channel_id'];

            if ($day_status == "2") {
                $message_to_user = "Hi $userInfo_name !!  \n You just had applied for second half days of leave from $from_date to $to_date. \n Reason mentioned : $reason ";
                $message_to_hr = "Hi HR !!  \n $userInfo_name just had applied for second half days of leave from $from_date to $to_date. \n Reason mentioned : $reason ";
            } elseif ($day_status == "1") {
                $message_to_user = "Hi $userInfo_name !!  \n You just had applied for first half days of leave from $from_date to $to_date. \n Reason mentioned : $reason ";
                $message_to_hr = "Hi HR !!  \n $userInfo_name just had applied for first half days of leave from $from_date to $to_date. \n Reason mentioned : $reason ";
            } else {
                $message_to_user = "Hi $userInfo_name !!  \n You just had applied for $no_of_days days of leave from $from_date to $to_date. \n Reason mentioned : $reason ";
                $message_to_hr = "Hi HR !!  \n $userInfo_name just had applied for $no_of_days days of leave from $from_date to $to_date. \n Reason mentioned : $reason ";
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

            self::changeLeaveStatus($leaveid, $newstatus);

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
            $q = "UPDATE users_previous_month_time SET status = 'Time added to user working hours'  Where id = $pending_id";
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
                    $r_error = 0;
                    $r_message = "Employee added Successfully !!";
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

    public static function forgotPassword($username) { // api call
        $r_error = 1;
        $r_message = "";
        $r_data = array();

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
        $return = array();

        $return['error'] = $r_error;
        $return['data'] = $r_data;
        return $return;
    }

    public static function getDisabledUsersList() {

        $q = "SELECT users.*,user_profile.*,user_bank_details.bank_account_no as bank_no FROM users LEFT JOIN user_profile ON users.id = user_profile.user_Id LEFT JOIN user_bank_details ON users.id = user_bank_details.user_Id where users.status = 'Disabled'";
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
        $current_date = date("Y-m-d");
        if ((strtotime($current_date) < strtotime($leave_start_date)) || isset($data['role'])) {
            $q = "SELECT * FROM leaves WHERE user_Id= $userid  AND from_date= '$leave_start_date' AND (status = 'Approved' OR status = 'Pending')";

            $runQuery = self::DBrunQuery($q);
            $row2 = self::DBfetchRows($runQuery);
            $no_of_rows = self::DBnumRows($runQuery);

            if ($no_of_rows > 0) {
                foreach ($row2 as $val) {
                    $q2 = "UPDATE leaves SET status = 'Cancelled Request' WHERE id=" . $val['id'];
                    $runQuery2 = self::DBrunQuery($q2);
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

    public static function addOfficeMachine($PARAMS) {

        $db = self::getInstance();
        $mysqli = $db->getConnection();

        $r_error = 1;
        $r_message = "";

        $m_type = $m_name = $m_price = $serial_no = $date_purchase = $mac_addr = $os = $status = $userid = $comment = $warranty = $bill_no = $warranty_comment = $repair_comment = "";
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
        $row = false;
        //check user name exists
        if ($mac_addr != "") {
            $q = "select * from machines_list where mac_address='$mac_addr'";
            $runQuery = self::DBrunQuery($q);
            $row = self::DBfetchRow($runQuery);
        }

        if ($row != false) {
            $r_error = 1;
            $r_message = "Mac Address already exist";
        } else {
            $q = "INSERT INTO machines_list ( machine_type, machine_name, machine_price, serial_number, date_of_purchase, mac_address, operating_system, status, comments,warranty_end_date,bill_number,warranty_comment, repair_comment ) VALUES ( '$m_type', '$m_name', '$m_price', '$serial_no','$date_purchase', '$mac_addr', '$os', '$status', '$comment','$warranty','$bill_no','$warranty_comment','$repair_comment' ) ";
            self::DBrunQuery($q);
            $machine_id = mysqli_insert_id($mysqli);
            self::assignUserMachine($machine_id, $userid);
            $message = "New machine with following detail added:\n";
            $message.= "Machine Type=" . $m_type . "\n";
            $message.= "Machine Name=" . $m_name . "\n";
            $message.= "Machine Price=" . $m_price . "\n";
            $message.= "Machine Serial no=" . $serial_no . "\n";
            $message.= "Machine Waranty=" . $warranty . "\n";
            $slackMessageStatus = self::sendSlackMessageToUser($slack_userChannelid = "hr", $message);
            $r_error = 0;
            $r_message = "Machine added Successfully !!";
        }

        $return = array();
        $return['error'] = $r_error;
        $return['message'] = $r_message;

        return $return;
    }

    public static function UpdateOfficeMachine($PARAMS) {
        $r_error = 1;
        $r_message = "";
        $userid = "";

        if (isset($PARAMS['user_id']) && $PARAMS['user_id'] != '') {
            $userid = trim($PARAMS['user_id']);
        }

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
            "repair_comment" => $PARAMS['repair_comment']
        );
        $machine_detail = self::getMachineDetail($PARAMS['id']);
        self::assignUserMachine($PARAMS['id'], $userid);
        $whereField = 'id';
        $whereFieldVal = $PARAMS['id'];
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

        $q = "select machines_list.*,machines_user.user_Id,machines_user.assign_date from machines_list left join machines_user on machines_list.id = machines_user.machine_id where machines_list.id = $id";

        $runQuery = self::DBrunQuery($q);

        try {
            $row = self::DBfetchRow($runQuery);
            $r_error = 0;
        } catch (Exception $e) {
            $r_error = 1;
            $row = "Some error occured.";
        }
        $return = array();
        $return['error'] = $r_error;
        $return['data'] = $row;

        return $return;
    }

    public static function assignUserMachine($machine_id, $userid) {
        $r_error = 1;
        $r_message = "";
        if ($userid == "") {
            $return = self::removeMachineAssignToUser($machine_id);
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
                $q = "UPDATE machines_user SET  user_Id = '$userid', assign_date = '$date' where id =" . $row['id'];
            } else {
                $q = "INSERT INTO machines_user ( machine_id, user_Id, assign_date ) VALUES ( $machine_id, $userid, '$date') ";
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
        $q = "select machines_list.*,machines_user.user_Id,machines_user.assign_date from machines_list left join machines_user on machines_list.id = machines_user.machine_id where machine_user.user_Id = '$userid'";

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

        return $return;
    }

    public static function getAllMachineDetail($sort = false, $status_sort = false) {
        if ($sort != false) {
            $q = "select machines_list.*,machines_user.user_Id,machines_user.assign_date,user_profile.name,user_profile.work_email from machines_list left join machines_user on machines_list.id = machines_user.machine_id left join user_profile on machines_user.user_Id = user_profile.user_Id where machines_list.machine_type='$sort'";
        }if ($status_sort != false) {
            $q = "select machines_list.*,machines_user.user_Id,machines_user.assign_date,user_profile.name,user_profile.work_email from machines_list left join machines_user on machines_list.id = machines_user.machine_id left join user_profile on machines_user.user_Id = user_profile.user_Id where machines_list.status='$status_sort'";
        } else {
            $q = "select machines_list.*,machines_user.user_Id,machines_user.assign_date,user_profile.name,user_profile.work_email from machines_list left join machines_user on machines_list.id = machines_user.machine_id left join user_profile on machines_user.user_Id = user_profile.user_Id ORDER BY machines_list.id DESC";
        }
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);

        $return = array();
        $return['error'] = 0;
        $return['data'] = $row;
        return $return;
    }

    public static function removeMachineAssignToUser($data) {
        $machine_info = self::getMachineDetail($data);
        if (!empty($machine_info['data']['user_Id'])) {
            $userInfo = self::getUserInfo($machine_info['data']['user_Id']);
            $userInfo_name = $userInfo['name'];
            $slack_userChannelid = $userInfo['slack_profile']['slack_channel_id'];
            $message = "Hi $userInfo_name !! \n You have been unassigned  to device " . $machine_info['data']['machine_name'] . " " . $machine_info['data']['machine_type'] . " by HR ";
            $slackMessageStatus = self::sendSlackMessageToUser($slack_userChannelid, $message);
        }

        $q = "Delete from machines_user where machine_id=$data";
        $runQuery = self::DBrunQuery($q);

        $return = array();
        $return['error'] = 0;
        $return['message'] = "User removed successfully";
        return $return;
    }

    public static function removeMachineDetails($data) {
        $q = "Delete from machines_list where id=$data";
        $runQuery = self::DBrunQuery($q);

        self::removeMachineAssignToUser($data);

        $return = array();
        $return['error'] = 0;
        $return['message'] = "Machine detail removed successfully";
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
        $ins = array(
            'status' => $data['status'],
            'color' => $data['color']
        );
        $q1 = "select * from machine_status where status ='" . trim($data['status']) . "'";

        $runQuery1 = self::DBrunQuery($q1);
        $row1 = self::DBfetchRow($runQuery1);
        $no_of_rows = self::DBnumRows($runQuery1);

        $q2 = "select * from machine_status where color ='" . trim($data['color']) . "' AND status !='" . trim($data['status']) . "'";
        $runQuery2 = self::DBrunQuery($q2);
        $no_of_rows2 = self::DBnumRows($runQuery2);
        if ($no_of_rows2 == 0) {
            if ($no_of_rows == 0) {
                $res = self::DBinsertQuery('machine_status', $ins);
                $r_error = 0;
                $r_message = "Variable Successfully Inserted";
                $r_data['message'] = $r_message;
            } if ($no_of_rows != 0) {
                $q = "UPDATE machine_status set status='" . $data['status'] . "', color='" . $data['color'] . "'WHERE id ='" . $row1['id'] . "'";
                self::DBrunQuery($q);

                $r_error = 0;
                $r_message = "Variable updated successfully";
                $r_data['message'] = $r_message;
            }
        } else {
            $r_error = 0;
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
    

}

new HR();
?>