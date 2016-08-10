<?php

error_reporting(0);
ini_set('display_errors', 0);
require_once ("c-salary.php");
$result = array(
    'data' => array(),
    'error' => array(),
);
if (isset($_GET['token']) && $_GET['token'] != "") {
    
    $userid = Salary::getIdUsingToken($_GET['token']);
    $userinfo = Salary::getUserDetail($userid);
    if (sizeof($userinfo) <= 0) {
        $result['error'][] = 'The given user id member not found';
    } else {
        $res = Salary::getSalaryInfo($userid);
        
        foreach($res as $val){
            $fdate = 'Salfrom_'.$val['applicable_from'];
          $res2 = Salary::getSalaryDetail($val['id']);
          $result['data'][$fdate] = $res2;
        }

    }
}
if (!isset($_GET['token'])) {
    $result['error'][] = "Please add token in URL";
}
if (isset($_GET['token']) && $_GET['token'] == "") {
    $result['error'][] = "Please insert a valid token in URL";
}
echo "<pre>";
print_r($result);
die;
//echo json_encode($result);
?>
