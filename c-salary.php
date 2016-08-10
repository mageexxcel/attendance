<?php
    require_once 'c-database.php';
   

    //comman format for dates = "Y-m-d" eg "04/07/2016"

    class Salary extends DATABASE {
        
        public static function getIdUsingToken( $token ){
            $token = mysql_real_escape_string( $token );
            $q = "select * from login_tokens where token='$token' ";
            $runQuery = self::DBrunQuery($q);
            $rows = self::DBfetchRow($runQuery);
            if( sizeof( $rows ) > 0 ){
                
                return $rows['userid'];
            }else{
                return false;
            }

        }
        
        public function getUserDetail($userid) {
            $q = "select * from user_profile where user_Id = $userid";
            $runQuery = self::DBrunQuery($q);
            $row = self::DBfetchRows($runQuery);
            $arr = array();
            foreach ($row as $val){
                $arr['name']= $val['name'];
                $arr['email']=$val['work_email'];
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
            foreach($row as $val){
                $ret[$val['key']] = $val['value']; 
            }
            return $ret;
        }
    }
?>