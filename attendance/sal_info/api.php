<?php

//error_reporting(0);
//ini_set('display_errors', 0);
require_once ("c-salary.php");

$request_body = file_get_contents('php://input');
//$PARAMS = json_decode($request_body, true);
$PARAMS = $_GET;
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
//if ($validateToken == false) {
//    header("HTTP/1.1 401 Unauthorized");
//    exit;
//}
$user_id = Salary::getIdUsingToken($token);
$userinfo = Salary::getUserDetail($user_id);




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

if ($action == 'create_new_client') {
    if ($userinfo['type'] == "admin") {
        if (!isset($PARAMS['name']) || $PARAMS['name'] == "") {
            $res['data']['message'][] = 'Please Insert name';
        }
        if (!isset($PARAMS['address']) || $PARAMS['address'] == "") {
            $res['data']['message'][] = 'Please Insert address';
        } else {
            $res = Salary::createNewClient($PARAMS);
        }
    } else {
        $res['data']['message'] = 'You are not authorise person for this operation ';
    }
}

if ($action == 'update_client_details') {

    if ($userinfo['type'] == "admin") {
        if (isset($PARAMS['client_id']) && $PARAMS['client_id'] != "") {
            $clientid = $PARAMS['client_id'];
            $res = Salary::UpdateClientDetails($PARAMS);
        } else {
            $res['data']['message'] = 'Please give client_id ';
        }
    } else {
        $res['data']['message'] = 'You are not authorise person for this operation ';
    }
}

if ($action == 'get_all_clients') {

    if ($userinfo['type'] == "admin") {
        $res = Salary::getAllClient();
    } else {
        $res['data']['message'] = 'You are not authorise person for this operation ';
    }
}

if ($action == 'create_client_invoice') {

    if ($userinfo['type'] == "admin") {
        if (!isset($PARAMS['client_id']) || $PARAMS['client_id'] == "") {
            $res['data']['message'][] = 'Please Insert client_id';
        }
        if (!isset($PARAMS['client_name']) || $PARAMS['client_name'] == "") {
            $res['data']['message'][] = 'Please Insert client_name';
        }
        if (!isset($PARAMS['client_address']) || $PARAMS['client_address'] == "") {
            $res['data']['message'][] = 'Please Insert client_address';
        }
        if (!isset($PARAMS['currency']) || $PARAMS['currency'] == "") {
            $res['data']['message'][] = 'Please Insert currency';
        }
        if (!isset($PARAMS['items']) || $PARAMS['items'] == "") {
            $res['data']['message'][] = 'Please Insert items';
        }
        if (!isset($PARAMS['sub_total']) || $PARAMS['sub_total'] == "") {
            $res['data']['message'][] = 'Please Insert sub_total';
        }
        if (!isset($PARAMS['service_tax']) || $PARAMS['service_tax'] == "") {
            $res['data']['message'][] = 'Please Insert service_tax';
        }
        if (!isset($PARAMS['total_amount']) || $PARAMS['total_amount'] == "") {
            $res['data']['message'][] = 'Please Insert total_amount';
        }
        if (!isset($PARAMS['due_date']) || $PARAMS['due_date'] == "") {
            $res['data']['message'][] = 'Please Insert due_date';
        } else {

            $res = Salary::createClientInvoice($PARAMS);
        }
    } else {
        $res['data']['message'] = 'You are not authorise person for this operation ';
    }
}

if ($action == 'delete_invoice') {

    if ($userinfo['type'] == "admin") {
        if (isset($PARAMS['invoice_id']) && $PARAMS['invoice_id'] != "") {
            $invoiceid = $PARAMS['invoice_id'];
            $res = Salary::DeleteInvoice($PARAMS);
        } else {
            $res['data']['message'] = 'Please give invoice_id ';
        }
    } else {
        $res['data']['message'] = 'You are not authorise person for this operation ';
    }
}

if ($action == 'get_client_detail') {
    if ($userinfo['type'] == "admin") {
        if (isset($PARAMS['client_id']) && $PARAMS['client_id'] != "") {
            $client_id = $PARAMS['client_id'];
            $res = Salary::getClientDetails($client_id);
        } else {
            $res['data']['message'] = 'Please give client_id ';
        }
    } else {
        $res['data']['message'] = 'You are not authorise person for this operation ';
    }
}

if ($action == 'create_employee_salary_slip') {

    if ($userinfo['type'] == "admin") {
        if (isset($PARAMS['user_id']) && $PARAMS['user_id'] != "") {
            $res = Salary::createUserPayslip($PARAMS);
        } else {
            $res['data']['message'] = 'Please give user_id ';
        }
    } else {
        $res['data']['message'] = 'You are not authorise person for this operation ';
    }
}

if ($action == 'get_user_manage_payslips_data') {
    if ($userinfo['type'] == "admin") {
        if (isset($PARAMS['user_id']) && $PARAMS['user_id'] != "") {
            $userid = $PARAMS['user_id'];
            if (isset($PARAMS['year'])) {
                $year = $PARAMS['year'];
            }
            if (isset($PARAMS['month'])) {
                $month = $PARAMS['month'];
            } else {
                $currentYear = date("Y");
                $currentMonth = date("F");

                if ($currentMonth == "January") {
                    $year = date('Y', strtotime($currentYear . ' -1 year'));
                    $month = date('m', strtotime($currentMonth . 'last month'));
                } else {
                    $year = $currentYear;
                    $month = date('m', strtotime($currentMonth . 'last month'));
                }
            }

            $res = Salary::getUserManagePayslip($userid, $year, $month);
        } else {
            $res['data']['message'] = 'Please give user_id ';
        }
    } else {
        $res['data']['message'] = 'You are not authorise person for this operation ';
    }
}

if ($action == 'insert_user_document') {
 
    $PARAMS['user_id'] = 212; 
    $PARAMS['document_type'] = 'PAN Card';
    $PARAMS['link_1'] = 'https://drive.google.com/file/d/0Bw7RILovH7OLQnJtbHk2cFBoakU4WnBHNVJvUEZXYnFMTTE4/view?usp=sharing';
    $PARAMS['link_2'] = 'http://www.google.com';
    $PARAMS['link_3'] = 'http://www.google.com';
    
    
    
    
    if ($userinfo['type'] == "admin") {
        if (isset($PARAMS['user_id']) && $PARAMS['user_id'] != "") {
            $res = Salary::insertUserDocumentInfo($PARAMS);
        } else {
            $res['data']['message'] = 'Please give user_id ';
        }
    } else {
       $PARAMS['user_id'] = $user_id; 
       $res = Salary::insertUserDocumentInfo($PARAMS);
    }
}

if ($action == 'get_user_document') {
    $document_type = 'PAN Card';
     $PARAMS['user_id'] = 212;  
    
    if ($userinfo['type'] == "admin") {
        if (isset($PARAMS['user_id']) && $PARAMS['user_id'] != "") {
            $user_id = $PARAMS['user_id'];
            
            $res = Salary::getUserDocumentDetail($user_id, $document_type);
        } else {
            $res['data']['message'] = 'Please give user_id ';
        }
    } else {
        $res = Salary::getUserDocumentDetail($user_id, $document_type);
    }
}

if ($action == 'delete_salary') {
    if ($userinfo['type'] == "admin") {
        $res['data']['message'] = "";
        if (!isset($PARAMS['user_id']) || (isset($PARAMS['user_id']) && $PARAMS['user_id'] == "")) {
            $res['data']['message'] .= 'Please give user_id ';
        }
        if (!isset($PARAMS['salary_id']) || (isset($PARAMS['salary_id']) && $PARAMS['salary_id'] == "")) {
            $res['data']['message'] .= 'Please give salary_id ';
        } else {
            $userid = $PARAMS['user_id'];
            $salaryid = $PARAMS['salary_id'];
            $res = Salary::deleteUserSalary($userid, $salaryid);
        }
    } else {
        $res['data']['message'] = 'You are not authorise person for this operation ';
    }
}

if ($action == 'send_payslips_to_employees') {

    if ($userinfo['type'] == "admin") {
        $res['data']['message'] = "";
        if (!isset($PARAMS['payslip_ids']) || (isset($PARAMS['payslip_ids']) && $PARAMS['payslip_ids'] == "")) {
            $res['data']['message'] .= 'Please give payslip_ids ';
        } else {
            $payslip_id = array();
            $payslip_id = $PARAMS['payslip_ids'];
            if (sizeof($payslip_id) > 0) {
                foreach ($payslip_id as $val) {
                    $res = Salary::sendPayslipMsgEmployee($val);
                }
            } else {
                $res['data']['message'] .= 'Please give payslip_ids ';
            }
        }
    } else {
        $res['data']['message'] = 'You are not authorise person for this operation ';
    }
}



echo json_encode($res);
?>