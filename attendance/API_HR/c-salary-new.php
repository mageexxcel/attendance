<?php 

error_reporting(0);
ini_set('display_errors', 0);

require_once ("c-hr.php");
require_once 'c-jwt.php';

// constants define
define("admin", "admin");
define("hr", "hr");

trait SalaryNew {

    public static function add_salary_structure($PARAMS){        

        $result = array(
            'data' => array(),
            'error' => array()
        );
        if (!isset($PARAMS['token'])) {
            $result['error'][] = "Please add token ";
        }
        if (isset($PARAMS['token']) && $PARAMS['token'] == "") {
            $result['error'][] = "Please insert a valid token ";
        }
        if (!isset($PARAMS['user_id'])) {
            $result['error'][] = "Please add user id ";
        }
        if (isset($PARAMS['user_id']) && $PARAMS['user_id'] === "") {
            $result['error'][] = "Please insert a valid user id ";
        }
        if (!isset($PARAMS['total_salary'])) {
            $result['error'][] = "Please add total_salary ";
        }
        if (isset($PARAMS['total_salary']) && $PARAMS['total_salary'] === "") {
            $result['error'][] = "Please insert a valid total salary amount ";
        }
        if (!isset($PARAMS['applicable_from'])) {
            $result['error'][] = "Please add applicable_from ";
        }
        if (isset($PARAMS['applicable_from']) && $PARAMS['applicable_from'] == "") {
            $result['error'][] = "Please insert a valid applicable_from date";
        }
        if (!isset($PARAMS['applicable_month'])) {
            $result['error'][] = "Please add applicable_month ";
        }
        if (isset($PARAMS['applicable_month']) && $PARAMS['applicable_month'] == "") {
            $result['error'][] = "Please insert a valid Applicable month ";
        }
        if (!isset($PARAMS['leave'])) {
            $result['error'][] = "Please add leave ";
        }
        if (isset($PARAMS['leave']) && $PARAMS['leave'] == "") {
            $result['error'][] = "Please insert a valid leave ";
        }
        if (!isset($PARAMS['special_allowance'])) {
            $result['error'][] = "Please add special_allowance ";
        }
        if (isset($PARAMS['special_allowance']) && $PARAMS['special_allowance'] === "") {
            $result['error'][] = "Please insert a valid special_allowance ";
        }
        if (!isset($PARAMS['medical_allowance'])) {
            $result['error'][] = "Please add medical_allowance ";
        }
        if (isset($PARAMS['medical_allowance']) && $PARAMS['medical_allowance'] === "") {
            $result['error'][] = "Please insert a valid medical_allowance ";
        }
        if (!isset($PARAMS['conveyance'])) {
            $result['error'][] = "Please add conveyance ";
        }
        if (isset($PARAMS['conveyance']) && $PARAMS['conveyance'] === "") {
            $result['error'][] = "Please insert a valid conveyance ";
        }
        if (!isset($PARAMS['hra'])) {
            $result['error'][] = "Please add hra ";
        }
        if (isset($PARAMS['hra']) && $PARAMS['hra'] === "") {
            $result['error'][] = "Please insert a valid hra ";
        }
        if (!isset($PARAMS['basic'])) {
            $result['error'][] = "Please add basic ";
        }
        if (isset($PARAMS['basic']) && $PARAMS['basic'] === "") {
            $result['error'][] = "Please insert a valid basic ";
        }
        if (!isset($PARAMS['tds'])) {
            $result['error'][] = "Please add tds ";
        }
        if (isset($PARAMS['tds']) && $PARAMS['tds'] === "") {
            $result['error'][] = "Please insert a valid tds ";
        }
        if (!isset($PARAMS['misc_deduction'])) {
            $result['error'][] = "Please add Misc_deduction ";
        }
        if (isset($PARAMS['misc_deduction']) && $PARAMS['misc_deduction'] === "") {
            $result['error'][] = "Please insert a valid Misc_deduction ";
        }
        if (!isset($PARAMS['advance'])) {
            $result['error'][] = "Please add advance ";
        }
        if (isset($PARAMS['advance']) && $PARAMS['advance'] === "") {
            $result['error'][] = "Please insert a valid advance ";
        }
        if (!isset($PARAMS['loan'])) {
            $result['error'][] = "Please add loan ";
        }
        if (isset($PARAMS['loan']) && $PARAMS['loan'] === "") {
            $result['error'][] = "Please insert a valid loan ";
        }
        if (!isset($PARAMS['epf'])) {
            $result['error'][] = "Please add epf ";
        }
        if (isset($PARAMS['epf']) && $PARAMS['epf'] === "") {
            $result['error'][] = "Please insert a valid epf ";
        }
        if (!isset($PARAMS['arrear'])) {
            $result['error'][] = "Please add arrear ";
        }
        if (isset($PARAMS['arrear']) && $PARAMS['arrear'] === "") {
            $result['error'][] = "Please insert a valid arrear ";
        }
        if (!isset($PARAMS['increment_amount'])) {
            $result['error'][] = "Please add increment_amount ";
        }
        if (isset($PARAMS['increment_amount']) && $PARAMS['increment_amount'] === "") {
            $result['error'][] = "Please insert a valid increment_amount ";
        }
        
        if (sizeof($result['error']) <= 0) {
            foreach ($PARAMS as $key => $val) {
                
                if ($key != 'token' && $key != 'applicable_from' && $key != 'applicable_till' && $key != 'submit' && $key != 'action') {
                    if (!is_numeric($val)) {
                        $result['error'][] = "Please insert a valid $key number";
                    }
                }
                if ($key == 'applicable_from' && !preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $val)) {
                    $result['error'][] = "Please insert a valid $key date";
                }
            }
        }
        
        if (isset($PARAMS['token']) && $PARAMS['token'] != "") {      
            
            // added on 7th oct 2018 by arun -to get logged user details( role ) from token
            $decodedUserInfo = JWT::decode($PARAMS['token'], HR::JWT_SECRET_KEY);
            $decodedUserInfo = json_decode(json_encode($decodedUserInfo), true);
            $LOGGED_USER_ROLE = $decodedUserInfo['role'];
            
            $tuserid = $decodedUserInfo['id'];
            $userinfo = self::getUserDetail($tuserid); // get user details
            
            $HR_CAN_ADD_SALARY = false;
            //check if employee total months in employment
            if( strtolower($LOGGED_USER_ROLE) == 'hr'){
                $employeeDetails = self::getUserDetail( $PARAMS['user_id'] );
                $joining_date = $employeeDetails['date_of_joining'];
                $current_date = date("Y-m-d");
                $endDate = strtotime($current_date);
                $startDate = strtotime($joining_date);
                
                $numberOfMonths = abs((date('Y', $endDate) - date('Y', $startDate)) * 12 + (date('m', $endDate) - date('m', $startDate)));
                if( $numberOfMonths < 6 && $PARAMS['total_salary'] <= 10000 ){
                    $HR_CAN_ADD_SALARY = true;
                }
            }
            
            if ($userinfo['type'] != admin && $userinfo['type'] != hr) {
                if( $HR_CAN_ADD_SALARY == false ){
                    $result['error'][] = "You are not authorise to update salary information";    
                }
            }
            if (sizeof($result['error']) <= 0) {
                $user_id = $PARAMS['user_id'];
                $re = HR::addNewSalary($user_id, $PARAMS); // insert salary details
                if ($re == "Salary Inserted Successfully.") {
                    $result['data'] = $re;
                } else {
                    $result['error'][] = $re;
                }
            }
        }
        return $result;
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
    
    //get all payslips info of a employee
    public function getUserPayslipInfo($userid) {
        $q = "select * from payslips where user_Id = $userid ORDER by id DESC";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        return $row;
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

    //get employee salary info 
    public function getSalaryInfo($userid, $sort = false, $date = false) {
        
        $q = "select * from salary where user_Id = $userid";

        if ($sort == 'first_to_last') {
            $q = "select * from salary where user_Id = $userid ORDER by id ASC";
        }
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        
        // calculate applicable month from applicable_from and applicable_till date
        $applicable_month = 0;
        foreach($rows as $key => $row){
            if(isset($row['applicable_from']) && $row['applicable_from'] != "" && $row['applicable_from'] != "0000-00-00" ){
                $applicable_from = $row['applicable_from'];
            }            
            if(isset($row['applicable_till']) && $row['applicable_till'] != "" && $row['applicable_till'] != "0000-00-00"){
                $applicable_till = $row['applicable_till'];
            }
            if( isset($applicable_from) && isset($applicable_till) ){
                $start = date('Ym', strtotime($applicable_from));
                while($start < date("Ym", strtotime($applicable_till))){                    
                    $applicable_month++;
                    if(substr($start, 4, 2) == "12"){
                        $start = (date("Y", strtotime($start)) + 1)."01";
                    } else {
                        $start++;
                    }                    
                }        
            }
            $rows[$key]['applicable_month'] = $applicable_month;
            $applicable_month = 0;
        }
        
        if ($date != false) {
            $arr = array();
            foreach ($rows as $val) {
                if (strtotime($date) >= strtotime($val['applicable_from'])) {
                    $arr[] = $val;
                }
            }
            return $arr;
        } else {
            return $rows;
        }
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

    public static function getUserSalaryInfoById($PARAMS){
        $res = array();
        $token = $PARAMS['token'];

        if (isset($PARAMS['user_id']) && $PARAMS['user_id'] != "") {
            $userid = $PARAMS['user_id'];
            $userinfo = self::getUserDetail($userid);
            if (sizeof($userinfo) <= 0) {
                $res['error'] = 1;
                $res['message'] = 'The given user id member not found';
            } else {
                $res['error'] = 0;
                $res['data'] = $userinfo;
                $res3 = self::getHoldingDetail($userid);
                $resData = self::getSalaryInfo($userid);
                
                // start - check added so that salary greater than not to show to HR
                $hideSalaryFromHR = false;
                if( sizeof($resData ) > 0 ){
                    foreach($resData as $salCheck ){
                        if( $salCheck['total_salary'] && $salCheck['total_salary'] > 20000 ){
                            $hideSalaryFromHR = true;
                        }
                    }
                }
                // end - check added so that salary greater than not to show to HR
                $res4 = self::getUserPayslipInfo($userid);
                $i = 0;
                $res['data']['salary_details'] = array();
                foreach ($resData as $val) {
                    $res2 = self::getSalaryDetail($val['id']);
                    $res2['test'] = $val;
                    $res2['date'] = $val['applicable_from'];
                    $res['data']['salary_details'][] = $res2;
                    $i++;
                }
                $res['data']['holding_details'] = $res3;
                $res['data']['payslip_history'] = $res4;
    
                $joining_date = $res['data']['date_of_joining'];
                $current_date = date("Y-m-d");
                $endDate = strtotime($current_date);
                $startDate = strtotime($joining_date);
                
                $numberOfMonths = abs((date('Y', $endDate) - date('Y', $startDate)) * 12 + (date('m', $endDate) - date('m', $startDate)));
                
                // arun you have to work on this to enable this for hr role
                $loggedUserInfo = JWT::decode($token, self::JWT_SECRET_KEY);
                if( strtolower( $loggedUserInfo->role ) == "hr"  &&  ( $numberOfMonths > 8 || $hideSalaryFromHR == true) ){
                    $res['data'] = array();
                    $res['data']['message'] = "You are not authorise to view this user data";
                }
    
            }
        } else {
            $res['error'] = 1;
            $res['message'] = 'The given user id member not found';
        }

        return $res;
    }
}

?>