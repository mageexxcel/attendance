<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once ("c-hr.php");
$r_error = 1;
$r_message = "";
$r_data = array();

if ($_FILES) {
    
     $token = $_POST['token'];
      $leaveid = $_POST['leaveid'];
      $return = $_POST['page_url'];
     $validateToken = HR::validateToken($token);
    if ($validateToken == false) {
       echo "Login token expired please login again!!! . $mins ";
        die;
    }

    $leaveDetails = HR::getLeaveDetails($leaveid);
    
    if (is_array($leaveDetails)) {
        $from_date = $leaveDetails['from_date'];
        $to_date = $leaveDetails['to_date'];
        $userInfo = HR::getUserInfo($leaveDetails['user_Id']); 
        $userInfo_name = $userInfo['name'];
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
            if (!move_uploaded_file($file_tmp, "../sal_info/payslip/leave_doc/" . $file_name)) {
                 echo "File Not uploaded";
                die;
            } else {
                $ar = array();
                $ar['name'] = $file_name;
                $ar['path'] = (isset($_SERVER['HTTPS']) ? "https" : "http") ."://".$_SERVER['HTTP_HOST']."/attendance/sal_info/payslip/leave_doc/" . $file_name;
                $arr[] = $ar;
                $q = "UPDATE leaves set doc_link= '".$ar['path']."' WHERE id = $leaveid ";
                HR::DBrunQuery($q);
                $message = "Hi HR !!\n $userInfo_name has uploaded document for leave applied from $from_date to $to_date .\nCheck here ".$ar['path'];
                $slackMessageStatus = HR::sendSlackMessageToUser('hr', $message);
                
            }
        } else {
             echo "Please upload the document in one of the format: PDF,Docx,doc,jpg,jpeg and png.";
                die;
        }
    }
  
   
  header("Location: $return");
  exit;
} 
else {
    $r_error = 1;
    $r_message = "Invalid Access";
    $r_data = $r_message;
}

?>

    
