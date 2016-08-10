<?php
    require_once '../connection.php';

    class DATABASE
    {
        function __construct(){
            global $host;
            global $user;
            global $pass;
            global $db;

            $this->hostname= $host;
            $this->user= $user;
            $this->password=$pass;
            $this->db_name=$db;
            $this->dbh=mysql_connect($this->hostname, $this->user, $this->password) or die("Unable to connect to MySQL");
            $this->conn = mysql_select_db($this->db_name, $this->dbh) or die("Could not select db");
        }
        function __destruct(){
            if( $this->dbh ){
                mysql_close($this->dbh);
            }
        }
        public static function DBrunQuery($query){
            return mysql_query($query); 
        }
        public static function DBnumRows($result){  return mysql_num_rows($result); }
        public static function DBfetchRow($result){ return mysql_fetch_assoc($result);  }
        public static function DBfetchRows($result){ 
            $row_s = array();   
            while($row=mysql_fetch_assoc($result)){ 
                $row_s[]=$row;  
            }   
            return  $row_s; 
        }
        public static function DBinsertQuery( $tableName, $insertDataArray ){
            $return = false;
            $insertQuery = "INSERT INTO $tableName ";
            if( is_array($insertDataArray) && sizeof($insertDataArray) > 0 ){
                $fieldsString = '';
                $fieldsValString = '';
                foreach( $insertDataArray as $field => $fieldVal ){
                    
                    $fieldVal = mysql_real_escape_string( $fieldVal );
                    
                    if( $fieldsString == ''){
                        $fieldsString = $field;    
                    }else{
                        $fieldsString = $fieldsString.','.$field;
                    }
                    
                    if( $fieldsValString == ''){
                        $fieldsValString = "'$fieldVal'";    
                    }else{
                        $fieldsValString = $fieldsValString.",'$fieldVal'";
                    }
                }
                $insertQuery = $insertQuery."($fieldsString) VALUES ($fieldsValString)";
                if( self::DBrunQuery($insertQuery) ){
                    $return = true;
                }
            }
            return $return;
        }
        public static function DBupdateBySingleWhere( $tableName, $whereField, $whereFieldVal, $updateData ){
            $return = false;
            $updateQuery = "UPDATE $tableName SET ";
            if( is_array($updateData) && sizeof($updateData) > 0 ){
                
                $updateFieldString = '';
                foreach( $updateData as $field => $fieldVal ){
                    $fieldVal = strtolower( mysql_real_escape_string($fieldVal) );
                    if( $updateFieldString == '' ){
                        $updateFieldString = $field."='$fieldVal'";
                    }else{
                        $updateFieldString = $updateFieldString.", $field='$fieldVal' ";
                    }
                }
                
                $updateQuery = $updateQuery."$updateFieldString WHERE $whereField='$whereFieldVal' ";
                if( self::DBrunQuery($updateQuery) ){
                    $return = true;
                }
            }
            return $return;    
        }   
        
    }
    $databaseObj = new DATABASE();
?>