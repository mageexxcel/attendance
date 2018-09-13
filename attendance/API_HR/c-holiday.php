<?php 

trait Holiday {
    
    public static function addHoliday($name, $date, $type){

        $r_error = 0;
        $r_data = array();
        $return = array();

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

        $return = [
            'error' => $r_error,
            'data' => $r_data
        ];
        
        return $return;
    }

}

?>