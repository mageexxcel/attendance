<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once ("c-salary.php");
$r_error = 1;
$r_message = "";
$r_data = array();

if ($_FILES) {
     $token = $_POST['token'];
      $validateToken = Salary::validateToken($token);
    if ($validateToken == false) {
       echo "Login token expired please login again!!! . $mins ";
        die;
    }
   
    $arr = array();
    foreach ($_FILES as $v) {
        $file_name = $v['name'];
        $file_size = $v['size'];
        $file_tmp = $v['tmp_name'];
        $file_type = $v['type'];
        $ext = pathinfo($file_name, PATHINFO_EXTENSION);
        $exten = array('pdf', 'jpeg', 'docx', 'doc', 'jpg', 'png');
        if (in_array($ext, $exten)) {
            if (!move_uploaded_file($file_tmp, "payslip/" . $file_name)) {
                $ar = array();
                $ar['name'] = $file_name;
                $ar['message'] = "File not uploaded";
                $arr[] = $ar;
            } else {
                $ar = array();
                $ar['name'] = $file_name;
                $ar['path'] = "payslip/" . $file_name;
                $arr[] = $ar;
            }
        } else {
            $ar = array();
            $ar['name'] = $file_name;
            $ar['message'] = "Please upload the document in correct format.";
            $arr[] = $ar;
        }
    }
    $r_error = 0;
    $r_data = $arr;
} else {
    $r_error = 1;
    $r_message = "Invalid Access";
    $r_data = $r_message;
}
$return = array();
$return['error'] = $r_error;
$return['data'] = $r_data;
echo json_encode($return);
