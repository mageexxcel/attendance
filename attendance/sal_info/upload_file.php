<?php
/* 
file to upload  employee documents on google drive 
and send slack notification on success to employee. 
  */

error_reporting(0);
ini_set('display_errors', 0);
require_once ("c-salary.php");

if (isset($_POST['submit'])) {

    $userid = $_POST['user_id'];
    $doc_type = $_POST['document_type'];
    $return = $_POST['page_url'];
    $token = $_POST['token'];
    $validateToken = Salary::validateToken($token);

    if ($validateToken != false) {

        //start -- check for token expiry
        $tokenInfo = JWT::decode($token, Salary::JWT_SECRET_KEY);
        $tokenInfo = json_decode(json_encode($tokenInfo), true);

        if (is_array($tokenInfo) && isset($tokenInfo['login_time']) && $tokenInfo['login_time'] != "") {
            $token_start_time = $tokenInfo['login_time'];
            $current_time = time();
            $time_diff = $current_time - $token_start_time;
            $mins = $time_diff / 60;

            if ($mins > 60) { //if 60 mins more
                $validateToken = false;
            }
        } else {
            $validateToken = false;
        }
        //end -- check for token expiry
    }
    if ($validateToken == false) {
       echo "Login token expired please login again!!! . $mins ";
        die;
    }

    $userInfo = Salary::getUserInfo($userid);
    $userInfo_name = $userInfo['name'];
    $user_slack_id = $userInfo['slack_profile']['id'];
    $channel_id = Salary::getSlackChannelIds();
    $slack_userChannelid = "";
    foreach ($channel_id as $v) {
        if ($v['user'] == "$user_slack_id") {
            $slack_userChannelid = $v['id'];
        }
    }
    $whereField = 'id';
    $arr = array();

    foreach ($_FILES as $k => $v) {
        $url = '';
        if ($v['name'] != "") {


            $file_name = $_FILES[$k]['name'];
            $file_size = $_FILES[$k]['size'];
            $file_tmp = $_FILES[$k]['tmp_name'];
            $file_type = $_FILES[$k]['type'];

            $ext = pathinfo($file_name, PATHINFO_EXTENSION);
            $exten = array('pdf', 'jpeg', 'docx', 'doc', 'jpg', 'png');
            if (!in_array($ext, $exten)) {
                echo "Please upload the document in correct format.";
                die;
            }

            if (!move_uploaded_file($file_tmp, "demo/" . $file_name)) {
                echo "File Not uploaded";
                die;
            }


            $save = Salary::saveDocumentToGoogleDrive($document_type, $userInfo_name, $userid, $file_name, $file_id = false);

            if (sizeof($save) <= 0) {
                echo "PLease provide refresh token";
                die;
            }

            $url = $save['url'];
        }
        $arr[$k] = "<iframe src='$url'></iframe>";
    }
    $qu = "INSERT INTO user_document_detail (user_Id, document_type, link_1) VALUES ($userid, '$doc_type', '')";
    $run = Salary::DBrunQuery($qu);
    $id = mysql_insert_id();
    $res = Salary::DBupdateBySingleWhere('user_document_detail', $whereField, $id, $arr);

    $message = $userInfo_name . ". document $doc_type was uploaded on HR System. Please visit your document section or below link to view it \n $url";
    //  echo  $message;
    //$slackMessageStatus = Salary::sendSlackMessageToUser($slack_userChannelid, $message);
    $slackMessageStatus = Salary::sendSlackMessageToUser($slack_usercid = "hr", $message);

    header("Location: $return");
    exit;
}
