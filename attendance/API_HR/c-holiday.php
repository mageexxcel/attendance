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

    public static function getHolidayTypesList(){
        
        $r_error = 0;

        $holidays_type_list = [
            'normal' => self::$NORMAL_HOLIDAY,
            'restricted' => self::$RESTRICTED_HOLIDAY
        ];

        $r_data = [
            'holiday_type_list' => $holidays_type_list
        ];

        $return = [
            'error' => $r_error,
            'data' => $r_data
        ];
        
        return $return;
    }

}

?>