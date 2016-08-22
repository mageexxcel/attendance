<?php

error_reporting(0);
ini_set('display_errors', 0);
require_once ("c-salary.php");

$request_body = file_get_contents('php://input');
$PARAMS = json_decode($request_body, true);
//$PARAMS = $_GET;
$action = false;
if (isset($PARAMS['action'])) {
    $action = $PARAMS['action'];
}

$res = array(
    'error' => 1,
    'data' => array()
);
//validate a token

$token = $PARAMS['token'];
$user_id = Salary::getIdUsingToken($token);
$userinfo = Salary::getUserDetail($user_id);

if ($user_id == "") {
    header("HTTP/1.1 401 Unauthorized");
    exit;
}


if ($action == 'get_user_profile_detail') {
    if ($userinfo['type'] == "admin") {
        if (isset($PARAMS['user_id']) && $PARAMS['user_id'] != "") {
            $user_id = $PARAMS['user_id'];
            $res = Salary::getUserDetailInfo($user_id);
        } else {
            $res['data']['message'] = 'Please give user_id ';
        }
    } else {
        $res = Salary::getUserDetailInfo($user_id);
    }
}

if ($action == 'update_user_profile_detail') {
    
     if ($userinfo['type'] == "admin") {
        if (isset($PARAMS['user_id']) && $PARAMS['user_id'] != "") {
            $user_id = $PARAMS['user_id'];
           $res = Salary::UpdateUserInfo($PARAMS);
        } else {
            $res['data']['message'] = 'Please give user_id ';
        }
    } else {
        $PARAMS['user_id'] = $user_id;
        $res = Salary::UpdateUserInfo($PARAMS);
    }
    

    
}

if ($action == 'update_user_bank_detail') {
       if ($userinfo['type'] == "admin") {
        if (isset($PARAMS['user_id']) && $PARAMS['user_id'] != "") {
            $user_id = $PARAMS['user_id'];
            $res = Salary::UpdateUserBankInfo($PARAMS);
        } else {
            $res['data']['message'] = 'Please give user_id ';
        }
    } else {
        $PARAMS['user_id'] = $user_id;
         $res = Salary::UpdateUserBankInfo($PARAMS);
    }

   
}
if ($action == 'create_user_salary') {
   if ($userinfo['type'] == "admin") {
        if (isset($PARAMS['user_id']) && $PARAMS['user_id'] != "") {
            $user_id = $PARAMS['user_id'];
            $res = Salary::generateUserSalary($user_id);
        } else {
            $res['data']['message'] = 'Please give user_id ';
        }
    } else {
        $res['data']['message'] = 'You are not authorise person for this operation ';
    }
}
echo json_encode($res);
?>