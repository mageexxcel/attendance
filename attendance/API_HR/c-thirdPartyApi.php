<?php 

trait ThirdPartyAPI {

    public static function validateSecretKey($secret_key){
        $return = false;
        $q = " SELECT * from secret_tokens WHERE secret_key = '$secret_key' ";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        if( sizeof($row) > 0 ){
            $return = true;
        }
        return $return;
    }

    public static function generateSecretKey($app_name){        
        $characters = $app_name . time();
        $secretKey = md5($characters);
        return $secretKey;
    }

    public static function checkIfAppNameExist($app_name){
        $return = false;
        $q = " SELECT * FROM secret_tokens WHERE app_name = '$app_name' ";       
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        if( sizeof($rows) > 0 ){
            $return = true;
        }
        return $return;
    }

    public static function API_generateSecretKey( $app_name, $user_id ){
        $r_error = 0;
        $r_data = array();
        $return = array();

        if( isset($app_name) && $app_name != "" ){
            $app_exist = self::checkIfAppNameExist($app_name);
            if( $app_exist ) {
                $r_error = 1;
                $r_data['message'] = "App already exist";           
            } else {
                $secret_key = self::generateSecretKey($app_name);
                $app_info = [
                    'app_name' => $app_name,
                    'secret_key' => $secret_key,
                    'added_by_userid' => $user_id
                ];
                $generate_secret_key = self::DBinsertQuery('secret_tokens', $app_info);
                if( $generate_secret_key ){
                    $r_data['message'] = 'Secret key generated successfully.';
                } else {
                    $r_error = 1;
                    $r_data['message'] = 'Unable to generate secret key.';
                }
            }
            
        } else {
            $r_error = 1;
            $r_data['message'] = 'Please provide App name';
        }                
        $return = [
            'error' => $r_error,
            'data' => $r_data
        ];
        return $return;
    }

    public static function API_regenerateSecretKey( $app_id ){    
        $r_error = 0;
        $r_data = array();
        $return = array();  
        $q = " SELECT * from secret_tokens WHERE id = $app_id ";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        if( sizeof($rows) > 0 ){
            $secret_key = self::generateSecretKey($rows['app_name']);
            $q = " UPDATE secret_tokens SET secret_key = '$secret_key', added_on = CURRENT_TIMESTAMP WHERE id = $app_id ";
            $runQuery = self::DBrunQuery($q);
            if($runQuery){
                $r_data['message'] = 'Secret key regenerated successfully';
            } else {
                $r_error = 1;
                $r_data['message'] = 'Secret key regeneration failed';
            }
        } else {
            $r_error = 1;
            $r_data['message'] = 'No Records Found';
        }
        $return = [
            'error' => $r_error,
            'data' => $r_data
        ];
        return $return;
    }

    public static function API_deleteSecretKey( $app_id ){
        $r_error = 0;
        $r_data = array();
        $return = array();
        $q = " SELECT * FROM secret_tokens WHERE id = $app_id ";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        if( sizeof($rows) > 0 ){
            $q = " DELETE FROM secret_tokens WHERE id = $app_id ";
            $runQuery = self::DBrunQuery($q);
            if($runQuery) {
                $r_data['message'] = 'Secret key deleted successfully';    
            } else {
                $r_error = 1;
                $r_data['message'] = 'Secret key deletion failed';    
            }            
        } else {
            $r_error = 1;
            $r_data['message'] = 'No Records Found';
        }
        $return = [
            'error' => $r_error,
            'data' => $r_data
        ];
        return $return;        
    }

    public static function API_getAllSecretKeys(){
        $r_error = 0;
        $r_data = array();
        $return = array();
        $q = " SELECT * FROM secret_tokens ";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        if( sizeof($rows) > 0 ){
            $r_data['app_info'] = $rows;
        } else {
            $r_error = 1;
            $r_data['message'] = 'No Records Found';
        }
        $return = [
            'error' => $r_error,
            'data' => $r_data
        ];
        return $return;
    }

}

?>