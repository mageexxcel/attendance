<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once ("c-salary.php");


if (isset($_POST['submit'])) {
$userid = $_POST['user_id'];
$doc_type = $_POST['document_type'];
$return = $_POST['page_url'];


$userInfo = Salary::getUserInfo($userid);
$userInfo_name = $userInfo['name'];

$qu = "INSERT INTO user_document_detail (user_Id, document_type, link_1) VALUES ($userid, '$doc_type', '')";
$run = Salary::DBrunQuery($qu);  
$id = mysql_insert_id();

$whereField = 'id';
$arr = array(); 

foreach ($_FILES as $k => $v) {
    $url = '';
    if ($v['name'] != "") {


            $file_name = $_FILES[$k]['name'];
            $file_size = $_FILES[$k]['size'];
            $file_tmp = $_FILES[$k]['tmp_name'];
            $file_type = $_FILES[$k]['type'];

            if (!move_uploaded_file($file_tmp, "demo/" . $file_name)) {
                echo "File Not uploaded";
                die;
            }

            
            $save = Salary::saveDocumentToGoogleDrive($document_type, $userInfo_name, $userid, $file_name, $file_id = false);
            $url = $save['url'];

    }
    $arr[$k] = "<iframe src='$url'></iframe>";
    
    
}

$res = Salary::DBupdateBySingleWhere('user_document_detail', $whereField, $id, $arr);

header("Location: $return");
exit;
}
