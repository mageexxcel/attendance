<?php

header("Access-Control-Allow-Origin: *");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

     exit(0);
}

error_reporting(0);
ini_set('display_errors', 0);
require_once ("c-salary.php");


$result = array(
    'data' => array(),
    'error' => array(),
);
if (isset($_GET['token']) && $_GET['token'] != "") {
    $result['data']['salary_details'] = array();
    $tuserid = Salary::getIdUsingToken($_GET['token']);
    $userinfo = Salary::getUserDetail($tuserid);
    if (sizeof($userinfo) <= 0) {
        $result['error'][] = 'The given user id member not found';
    } else {
        if ($userinfo['type'] == "admin") {
            if (isset($_GET['user_id']) && $_GET['user_id'] != "") {
                $userid = $_GET['user_id'];
                $userinfo = Salary::getUserDetail($userid);
                if (sizeof($userinfo) <= 0) {
                    $result['error'][] = 'The given user id member not found';
                } else {
                    $result['data'] = $userinfo;
                    $res = Salary::getSalaryInfo($userid);
                    $i = 0;
                    foreach ($res as $val) {
                        $res2 = Salary::getSalaryDetail($val['id']);
                        $res2['test'] = $val;
                        $res2['date'] = $val['applicable_from'];
                        $result['data']['salary_details'][] = $res2;
                        $i++;
                    }
                }
            } else {
                $result['error'][] = 'The given user id number';
            }
        } else {
            $result['data'] = $userinfo;
            $res = Salary::getSalaryInfo($tuserid);
            $i = 0;
            foreach ($res as $val) {

                $res2 = Salary::getSalaryDetail($val['id']);
                $res2['test'] = $val;
                $res2['date'] = $val['applicable_from'];
                $result['data']['salary_details'][] = $res2;
                $i++;
            }
        }
    }
}
if (!isset($_GET['token'])) {
    $result['error'][] = "Please add token in URL";
}
if (isset($_GET['token']) && $_GET['token'] == "") {
    $result['error'][] = "Please insert a valid token in URL";
}
//echo "<pre>";
//print_r($result);
//die;
echo json_encode($result);
?>
