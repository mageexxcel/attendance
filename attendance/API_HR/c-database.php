<?php
   require_once '../../../connection.php';

class Database {
    private $_connection;
    public static $_instance; //The single instance
    private $_host = "";
    private $_username = "";
    private $_password = "";
    private $_database = "";
       public $tdb = '';
    /*
    Get an instance of the Database
    @return Instance
    */
    public static function getInstance() {
        if(!self::$_instance) { // If no instance then make one
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    // Constructor
    public function __construct() {
       
            global $host;
    global $user;
    global $pass;
    global $db;

        $this->_host = $host;
    $this->_username = $user;
    $this->_password = $pass;
    $this->_database = $db; 
      

        $this->_connection = new mysqli($this->_host, $this->_username, 
            $this->_password, $this->_database);
    
        // Error handling
        if(mysqli_connect_error()) {
            trigger_error("Failed to conencto to MySQL: " . mysql_connect_error(),
                 E_USER_ERROR);
        }
    }
    // Magic method clone is empty to prevent duplication of connection
    private function __clone() { }
    // Get mysqli connection
    public function getConnection() {
        return $this->_connection;
    }
   


   public static function DBescapeString( $string ){
           $db = self::getInstance();
           $mysqli = $db->getConnection();               

            return mysqli_real_escape_string($mysqli, $string );
        }

        public static function DBrunQuery($query){
              $db = self::getInstance();
           $mysqli = $db->getConnection();  

            return $mysqli->query($query); 
        }
        public static function DBnumRows($result){  return mysqli_num_rows($result); }
        public static function DBfetchRow($result){ return mysqli_fetch_assoc($result);  }
        public static function DBfetchRows($result){ 
            $row_s = array();   
            while($row=mysqli_fetch_assoc($result)){ 
                $row_s[]=$row;  
            }   
            return  $row_s; 
        }
        public static function DBinsertQuery( $tableName, $insertDataArray ){
             $db = self::getInstance();
           $mysqli = $db->getConnection(); 
          
            $return = false;
            $insertQuery = "INSERT INTO $tableName ";
            if( is_array($insertDataArray) && sizeof($insertDataArray) > 0 ){
                $fieldsString = '';
                $fieldsValString = '';
                foreach( $insertDataArray as $field => $fieldVal ){
                    
                    $fieldVal = mysqli_real_escape_string($mysqli, $fieldVal );
                    
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
             $db = self::getInstance();
           $mysqli = $db->getConnection(); 
            $return = false;
            $updateQuery = "UPDATE $tableName SET ";
            if( is_array($updateData) && sizeof($updateData) > 0 ){
                
                $updateFieldString = '';
                foreach( $updateData as $field => $fieldVal ){
                    $fieldVal =  mysqli_real_escape_string($mysqli, $fieldVal) ;
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

$t = new Database();

?>