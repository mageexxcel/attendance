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
        $q = "select * from salary_details where salary_id = $salary_id";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        foreach ($row as $val) {
            $ret[$val['key']] = $val['value'];
        }
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
            'applicable_from' => date("Y-m-d", strtotime($data['applicable_from']))
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
            'Misc_Deductions' => $data['Misc_deduction'],
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

    public function insertIncrementInfo($data) {
        $ins = array(
            'user_Id' => $data['user_id'],
            'increment_start_date' => $data['increment_start_date'],
            'increment_end_date' => $data['increment_end_date'],
            'holding_amt' => $data['holding_amt'],
            'holding_amt_start' => $data['holding_amt_start'],
            'holding_amt_stop' => $data['holding_amt_stop'],
            'reminder' => 0
        );

        $res = self::DBinsertQuery('user_increment_info', $ins);
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

        $res = self::DBinsertQuery('user_bank_details', $ins);
        if ($res == false) {
            return false;
        } else {
            return "Successfully Inserted into table";
        }
    }

    public function UpdateUserInfo($data) {
        $ins = array(
            'name' => $data['name'],
            'jobtitle' => $data['jobtitle'],
            'dateofjoining' => $data['dateofjoin'],
            'dob' => $data['dob'],
            'gender' => $data['gender'],
            'marital_status' => $data['marital_status'],
            'address1' => $data['address1'],
            'address2' => $data['address2'],
            'city' => $data['city'],
            'state' => $data['state'],
            'zip_postal' => $data['zipcode'],
            'country' => $data['country'],
            'home_ph' => $data['home_phone'],
            'mobile_ph' => $data['mobile_phone'],
            'work_email' => $data['work_email'],
            'other_email' => $data['other_email'],
            'image' => $data['image'],
        );
        $whereField = 'user_Id';
        $whereFieldVal = $data['user_id'];
        $res = self::DBupdateBySingleWhere('user_profile', $whereField, $whereFieldVal, $ins);
        if ($res == false) {
            return false;
        } else {
            return "Successfully Updated into table";
        }
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
        $q = 'select * from user_document_detail where user_id='.$whereFieldVal;
        $run = mysql_query($q);
        $num_rows = mysql_num_rows($run);
        if($num_rows > 0 ){
         $res = self::DBupdateBySingleWhere('user_document_detail', $whereField, $whereFieldVal, $ins);   
        }
        if($num_rows <= 0 ){
            $res = self::DBinsertQuery('user_document_detail', $ins);
        }
        
        if ($res == false) {
            return false;
        } else {
            return "Successfully Inserted into table";
        }
    }

}

?>