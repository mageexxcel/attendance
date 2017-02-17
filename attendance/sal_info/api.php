<?php

error_reporting(0);
ini_set('display_errors', 0);
require_once ("c-salary.php");

// constants define
define("admin", "admin");
define("hr", "hr");
define("guest", "guest");
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
$validateToken = Salary::validateToken($token);
if ($validateToken == false) {
    header("HTTP/1.1 401 Unauthorized");
    exit;
}
$user_id = Salary::getIdUsingToken($token);

$userinfo = Salary::getUserDetail($user_id);

if ($userinfo['type'] == admin) {
    $data = "admin";
    Salary::setAdmin($data);
}

// action to get employee profile detail
if ($action == 'get_user_profile_detail') {
    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {
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
// action to update employee profile detail.
if ($action == 'update_user_profile_detail') {
    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {
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
// action to update employee bank details
if ($action == 'update_user_bank_detail') {
    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {
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
// action  to generate user salary.
if ($action == 'create_user_salary') {
    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {
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
// action to create a new client.
if ($action == 'create_new_client') {
    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {
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
// action to update client details 
if ($action == 'update_client_details') {
    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {
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
// action to get all client details 
if ($action == 'get_all_clients') {
    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {
        $res = Salary::getAllClient();
    } else {
        $res['data']['message'] = 'You are not authorise person for this operation ';
    }
}
// action to create a client invoice
if ($action == 'create_client_invoice') {
    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {
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
// action to delete client invoice
if ($action == 'delete_invoice') {
    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {
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
// action to get client detail.
if ($action == 'get_client_detail') {
    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {
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
// action to create an employee salary slip pdf
if ($action == 'create_employee_salary_slip') {
    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {

        if (isset($PARAMS['user_id']) && $PARAMS['user_id'] != "") {
            $res = Salary::createUserPayslip($PARAMS);
        } else {
            $res['data']['message'] = 'Please give user_id ';
        }
    } else {
        $res['data']['message'] = 'You are not authorise person for this operation ';
    }
}
// aciton to get employee last month salary details 
if ($action == 'get_user_manage_payslips_data') {

    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {
        if (isset($PARAMS['user_id']) && $PARAMS['user_id'] != "") {

            $extra_arrear = "";
            $arrear_for_month = "";
            $userid = $PARAMS['user_id'];
            if (isset($PARAMS['year'])) {
                $year = $PARAMS['year'];
            }
            if (isset($PARAMS['month'])) {
                $month = $PARAMS['month'];
            }
            if (isset($PARAMS['extra_arrear']) && isset($PARAMS['arrear_for_month'])) {
                $extra_arrear = $PARAMS['extra_arrear'];
                $arrear_for_month = $PARAMS['arrear_for_month'];
            } 
            
            if (!isset($PARAMS['year']) && !isset($PARAMS['month']))  {

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

            $res = Salary::getUserManagePayslip($userid, $year, $month, $extra_arrear, $arrear_for_month);
        } else {
            $res['data']['message'] = 'Please give user_id ';
        }
    } else {
        $res['data']['message'] = 'You are not authorise person for this operation ';
    }
}
// action to get employee document detail
if ($action == 'get_user_document') {
    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {
        if (isset($PARAMS['user_id']) && $PARAMS['user_id'] != "") {
            $user_id = $PARAMS['user_id'];
            $res = Salary::getUserDocumentDetail($user_id);
        } else {
            $res['data']['message'] = 'Please give user_id ';
        }
    } else {
        $res = Salary::getUserDocumentDetail($user_id);
    }
}
// action to delete employee document detail.
if ($action == 'delete_user_document') {
    if ($userinfo['type'] == guest) {
        $res['data']['message'] = 'You are not authorise for this operation';
    } else {
        if (isset($PARAMS['id']) && $PARAMS['id'] != "") {
            $id = $PARAMS['id'];
            $res = Salary::deleteUserDocument($id);
        } else {
            $res['data']['message'] = 'Please give document id';
        }
    }
}
// action to delete employee salary detail
if ($action == 'delete_salary') {
    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {
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
// action to send payslip slack notification to employee slack channel
if ($action == 'send_payslips_to_employees') {
    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {
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
// action to get all employee details
if ($action == 'get_all_users_detail') {
    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {
        $res = Salary::getAllUserInfo();
    } else {
        $res['data']['message'] = 'You are not authorise person for this operation ';
    }
}
// action to get all template variables
if ($action == 'get_template_variable') {
    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {
        $res = Salary::getAllTemplateVariable();
    } else {
        $res['data']['message'] = 'You are not authorise person for this operation ';
    }
}
// action to create a template varible
if ($action == 'create_template_variable') {
    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {

        $res = Salary::createTemplateVariable($PARAMS);
    } else {
        $res['data']['message'] = 'You are not authorise person for this operation ';
    }
}
// action to update a template variable
if ($action == 'update_template_variable') {
    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {
        $res = Salary::updateTemplateVariable($PARAMS);
    } else {
        $res['data']['message'] = 'You are not authorise person for this operation ';
    }
}
// action to delete a template variable.
if ($action == 'delete_template_variable') {
    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {
        $res = Salary::deleteTemplateVariable($PARAMS);
    } else {
        $res['data']['message'] = 'You are not authorise person for this operation ';
    }
}
// action to get all email templates
if ($action == 'get_email_template') {
    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {
        $res = Salary::getAllEmailTemplate();
    } else {
        $res['data']['message'] = 'You are not authorise person for this operation ';
    }
}
// action to create an email template.
if ($action == 'create_email_template') {
    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {
        $res = Salary::createEmailTemplate($PARAMS);
    } else {
        $res['data']['message'] = 'You are not authorise person for this operation ';
    }
}
// action to update an email template
if ($action == 'update_email_template') {
    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {
        $res = Salary::updateEmailTemplate($PARAMS);
    } else {
        $res['data']['message'] = 'You are not authorise person for this operation ';
    }
}
// action to delete an email template
if ($action == 'delete_email_template') {
    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {
        $res = Salary::deleteEmailTemplate($PARAMS);
    } else {
        $res['data']['message'] = 'You are not authorise person for this operation ';
    }
}
// aciton to get an email template by id 
if ($action == 'get_email_template_byId') {
    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {
        $res = Salary::getEmailTemplateById($PARAMS);
    } else {
        $res['data']['message'] = 'You are not authorise person for this operation ';
    }
}
// action to send employee  email
if ($action == 'send_employee_email') {
    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {

        $res = Salary::sendEmail($PARAMS);
    } else {
        $res['data']['message'] = 'You are not authorise person for this operation ';
    }
}
// action to cancel employee applied leaves
if ($action == 'cancel_applied_leave') {
    if ($userinfo['type'] == guest) {
        $res['data']['message'] = 'You are not authorise for this operation';
    } else {
        if (isset($PARAMS['user_id']) && $PARAMS['user_id'] != "") {
            $res = Salary::cancelAppliedLeave($PARAMS);
        } else {
            $res['data']['message'] = 'Please give user_id ';
        }
    }
}

// action to create a template varible
if ($action == 'create_pdf') {
    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {


        $res = Salary::createEmailTempPdf($PARAMS);
    } else {
        $res['data']['message'] = 'You are not authorise person for this operation ';
    }
}

// action to create a template varible
if ($action == 'update_read_document') {
    $doc_id = $PARAMS['document_id'];
    if ($user_id != "") {
        $res = Salary::UpdateDocumentDetail($user_id, $doc_id);
    } else {
        $res['data']['message'] = 'Please  give the user id';
    }
}

// action to save policy document
if ($action == 'save_policy_document') {
    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {

        $res = Salary::savePolicyDocument($PARAMS);
    } else {
        $res['data']['message'] = 'You are not authorise person for this operation ';
    }
}

// action to get policy document.
if ($action == 'get_policy_document') {

    $res = Salary::getPolicyDocument();
}

// action to get user policy document.
if ($action == 'get_user_policy_document') {


    $res = Salary::getUserPolicyDocument($user_id);
}
// action to update user policy document.
if ($action == 'update_user_policy_document') {

    $PARAMS['user_id'] = $user_id;
    $res = Salary::updateUserPolicyDocument($PARAMS);
}

// action to add or update team list
if ($action == 'add_team_list') {
    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {

        $PARAMS['value'] = json_encode($PARAMS['value']);

        $res = Salary::saveTeamList($PARAMS);
    } else {
        $res['data']['message'] = 'You are not authorise person for this operation ';
    }
}
if ($action == 'get_team_list') {

    $res = Salary::getTeamList();
}

// action to get all employee details on basis of team
if ($action == 'get_team_users_detail') {
    if ($userinfo['type'] == admin || $userinfo['type'] == hr) {
        $team = $PARAMS['team'];
        $res = Salary::getAllUserInfo($team);
    } else {
        $res['data']['message'] = 'You are not authorise person for this operation ';
    }
}



echo json_encode($res);
?>