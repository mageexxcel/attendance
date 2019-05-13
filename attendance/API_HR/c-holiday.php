<?php 

trait Holiday {

    // constants for normal and restricted holidays
    static $NORMAL_HOLIDAY = 0;
    static $RESTRICTED_HOLIDAY = 1;
    
    public static function addHoliday($name, $date, $type){

        $r_error = 0;
        $r_data = array();
        $return = array();

        if(!isset($name) || $name == ""){
            $r_data['message'] = "Please provide holiday name.";

        } else if (!isset($date) || $date == ""){
            $r_data['message'] = "Please provide a holiday date.";

        } else if (!isset($type) || $type == ""){
            $r_data['message'] = "Please provide holiday type.";

        } else {
            
            $q = "SELECT * from holidays where date = '$date'";
            $runQuery = self::DBrunQuery($q);
            $rows = self::DBfetchRows($runQuery);
            
            if( count($rows) > 0 ){
                $r_error = 1;
                $r_data['message'] = "Date Already Exists.";
    
            } else {
                $ins_holiday = array(
                    'name' => $name,
                    'date' => $date,
                    'type' => $type
                );
                $insert_holiday = self::DBinsertQuery('holidays', $ins_holiday);
                $r_data['message'] = "Holiday inserted successfully.";
            }
        }

        $return = [
            'error' => $r_error,
            'data' => $r_data
        ];
        
        return $return;
    }

    public static function isHolidayDateExists($date){        
        $return = false;
        $q = "SELECT * from holidays where date = '$date'";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);        
        if(sizeof($rows) > 0){
            $return = true;
        }
        return $return;
    }

    public static function API_updateHoliday($holiday_id, $name, $date, $type){
        
        $r_error = 0;
        $r_data = array();
        $return = array();

        if(!isset($name) || $name == ""){
            $r_data['message'] = "Please provide holiday name.";
        } else if (!isset($date) || $date == ""){
            $r_data['message'] = "Please provide a holiday date.";
        } else if (!isset($type) || $type == ""){
            $r_data['message'] = "Please provide holiday type.";
        } else {
            $q = "SELECT * from holidays where id = '$holiday_id'";
            $runQuery = self::DBrunQuery($q);
            $rows = self::DBfetchRows($runQuery);
            $date_exist = self::isHolidayDateExists($date);
            if(sizeof($rows) > 0) {
                if($date_exist) {
                    $r_data['message'] = "Duplicate dates not allowed.";
                } else {
                    $q = " UPDATE holidays SET name = '$name', date = '$date', type = '$type' WHERE id = '$holiday_id' ";
                    self::DBrunQuery($q);
                    $r_data['message'] = "Holiday updated successfully.";
                }                                      
            } else {
                if($date_exist) {
                    $r_data['message'] = "Duplicate dates not allowed.";
                } else {
                    $ins_holiday = array(
                        'name' => $name,
                        'date' => $date,
                        'type' => $type
                    );
                    $insert_holiday = self::DBinsertQuery('holidays', $ins_holiday);
                    $r_data['message'] = "Holiday inserted successfully.";
                }                
            }
        }
        $return = [
            'error' => $r_error,
            'data' => $r_data
        ];
                        
        return $return;
    }

    public static function getHolidayDetails($holiday_id){
        $q = " SELECT * from holidays WHERE id = '$holiday_id' ";
        $runQuery = self::DBrunQuery($q);
        $row = self::DBfetchRows($runQuery);
        return $row;
    }

    public static function API_deleteHoliday($holiday_id){
        
        $r_error = 0;
        $r_data = array();
        $return = array();
        $holiday = self::getHolidayDetails($holiday_id);        
        if( sizeof($holiday) > 0 ){
            $q = " DELETE FROM holidays WHERE id = '$holiday_id' ";
            $runQuery = self::DBrunQuery($q);
            $r_data['message'] = "Holiday Deleted Successfully.";
        } else {
            $r_error = 1;
            $r_data['message'] = "Holiday not Found.";
        }
        $return = [
            'error' => $r_error,
            'data' => $r_data
        ];

        return $return;
    }

    public static function getHolidayTypesList(){

        $list = array(
            array('type' => self::$NORMAL_HOLIDAY, 'text' => 'Normal'),
            array('type' => self::$RESTRICTED_HOLIDAY, 'text' => 'Restricted'),
        );

        return $list;
    }

    public static function API_getHolidayTypesList(){
        
        $r_error = 0;

        $r_data = [
            'holiday_type_list' => self::getHolidayTypesList()
        ];

        $return = [
            'error' => $r_error,
            'data' => $r_data
        ];
        
        return $return;
    }

    public static function getUserApprovedRHLeaves( $userid, $year ){        
        $q = " SELECT * FROM leaves WHERE leave_type = 'Restricted' AND user_id = '$userid' AND status = 'Approved' AND from_date LIKE '$year%' ";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        return $rows;
    }
    
    public static function getUserRHCompensationLeaves( $userid, $year ) {
        $q = " SELECT * FROM leaves WHERE leave_type = 'RH Compensation' AND user_id = '$userid' AND status = 'Approved' AND from_date LIKE '$year%' ";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        return $rows;
    }

    public static function getUserRHLeaves( $userid, $year ){        
        $q = " SELECT * FROM leaves WHERE leave_type = 'Restricted' AND user_id = '$userid' AND from_date LIKE '$year%' ";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);
        return $rows;
    }

    public static function getMyRHLeaves( $year ){
        $q = " SELECT * FROM holidays WHERE type = " . self::$RESTRICTED_HOLIDAY . " AND date LIKE '$year%' ORDER BY date ASC ";
        $runQuery = self::DBrunQuery($q);
        $rows = self::DBfetchRows($runQuery);        
        if( sizeof($rows) > 0 ){
            $rhType = self::getHolidayTypesList();
            foreach( $rows as $key => $row ){
                foreach( $rhType as $type ){
                    if( $row['type'] == $type['type'] ){
                        $rows[$key]['type_text'] = $type['text'];
                    }
                }
                $rows[$key]['raw_date'] = $row['date'];
                $rows[$key]['day'] = date('l', strtotime( $row['date'] ));
                $rows[$key]['month'] = date('F', strtotime( $row['date'] ));
                $explodeRawDate = explode("-", $row['date']);
                $rows[$key]['date'] = $explodeRawDate[2] . "-" . $rows[$key]['month'] . "-" . $explodeRawDate[0];
            }
        }    
        return $rows;
    }

    public static function API_getMyRHLeaves( $userid, $year ){
        $r_error = 0;
        $r_data = array();
        if( !isset($year) or $year == "" ){
            $year = date('Y');
        }
        $rhList = self::getMyRHLeaves( $year );
        $rhLeaves = self::getUserRHLeaves( $userid, $year );
        if( sizeof($rhList) > 0 ){       
            foreach( $rhList as $key => $rh ){
                $rhList[$key]['status'] = "";
                foreach( $rhLeaves as $rhLeave ){
                    if( $rhLeave['from_date'] == $rh['raw_date'] ){
                        $rhList[$key]['status'] = $rhLeave['status'];
                    }
                }
            }     
            $r_data['rh_list'] = $rhList;
        } else {
            $r_data['message'] = "Restricted holidays not found for " . $year;
        }
        $return = [
            'error' => $r_error,
            'data' => $r_data
        ];
        return $return;
    }

}

?>