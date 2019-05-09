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
if( !isset($PARAMS['secret_key']) || $PARAMS['secret_key'] == "" ){
    $validateToken = Salary::validateToken($token);
    if ($validateToken == false) {
        header("HTTP/1.1 401 Unauthorized");
        exit;
    }
}


//-----------------------------------------------------------
//-----------------------------------------------------------
// start added by arun JUNE 2017 to implement on role basis

require_once ("../API_HR/c-hr.php");
$DO_TOKEN_VERIFICATION = true;
$actionsNotRequiredToken = HR::getActionsNotRequiredToken();
foreach( $actionsNotRequiredToken as $ac ){
    if( $ac['name'] == $action ){
        $DO_TOKEN_VERIFICATION = false;
    }
}

// check for secret key
$secret_key = $PARAMS['secret_key'];
if(isset($secret_key) && $secret_key != ""){
    $validate_secret = HR::validateSecretKey($secret_key); 
    if($validate_secret) {
        $secret_actions = HR::getActionsForThirdPartyApiCall();
        foreach( $secret_actions as $secret_action ){
            if( $secret_action['name'] == $action ){
                $DO_TOKEN_VERIFICATION = false;
                $q = " UPDATE secret_tokens SET last_request = CURRENT_TIMESTAMP WHERE secret_key = '$secret_key' ";
                $runQuery = HR::DBrunQuery($q);
            }
        }   
    }
}

$IS_SUPER_ADMIN = false;  // can do every thing. this is the person whose type=Admin in users table itself.

if( $DO_TOKEN_VERIFICATION == false  ){
    //echo "action :: $action <br>";
    // these are the actions which not required token, so need to check for role
}else{
    // loggedUserInfo : this variable is compulsory since it is used in most of the actions
    $loggedUserInfo = JWT::decode($token, HR::JWT_SECRET_KEY);
    $loggedUserInfo = json_decode(json_encode($loggedUserInfo), true);
    if ( $slack_id != "" ) { // these are called from slack rtm
        $loggedUserInfo = HR::getUserInfofromSlack($slack_id);
    }
    // dont do any change in above lines
    if( strtolower($loggedUserInfo['role']) == 'admin' ){
        $IS_SUPER_ADMIN = true;
    }

    // echo '<pre>';
    // print_r( $loggedUserInfo );
    // echo '<pre>';


    if( $IS_SUPER_ADMIN === true ){
        // this is the admin on existing role basis, have access to all. type = "Admin" defined in users table
    }else{

    //print_r($loggedUserInfo);

    $loggedUserInfo_emp_id = $loggedUserInfo['id'];
    //if( $loggedUserInfo_emp_id == 343 ){ // uncomment this line after testing for meraj user
        $is_user_valid_action = HR::is_user_valid_action( $action, $loggedUserInfo_emp_id );
        if( $is_user_valid_action == true ){

        }else{
            header("HTTP/1.1 401 Unauthorized");
            exit;
            //send 401 unathoried request, this will show an alert message and redirect to home page

            // $res['error'] = 1;
            // $res['data']['message'] = "$action - You are not authorized to perform this action!!";
            // echo json_encode($res);
            // die;
        }
    //}
    }
}

// end added by arun JUNE 2017 to implement on role basis
//-----------------------------------------------------------
//-----------------------------------------------------------




$user_id = Salary::getIdUsingToken($token);

$userinfo = Salary::getUserDetail($user_id);

if ($userinfo['type'] == admin) {
    $data = "admin";
    Salary::setAdmin($data);
}





//------------------------------------------------------------------------
//------------------------------------------------------------------------
// actions defined as constants DONE
//------------------------------------------------------------------------
//------------------------------------------------------------------------

if ($action == 'get_all_users_detail') { //action to get all employee details
    $res = Salary::getAllUserInfo();
} else if ($action == 'get_template_variable') {   // action to get all template variables
    $res = Salary::getAllTemplateVariable();
} else if ($action == 'create_template_variable') {    //action to create a template varible
    $res = Salary::createTemplateVariable($PARAMS);
} else if ($action == 'update_template_variable') {    //action to update a template variable
    $res = Salary::updateTemplateVariable($PARAMS);
} else if ($action == 'delete_template_variable') {    //action to delete a template variable.
    $res = Salary::deleteTemplateVariable($PARAMS);
} else if ($action == 'get_email_template') {  //action to get all email templates
    $res = Salary::getAllEmailTemplate();
} else if ($action == 'create_email_template') {   //action to create an email template.
    $res = Salary::createEmailTemplate($PARAMS);
} else if ($action == 'update_email_template') {   //action to update an email template
    $res = Salary::updateEmailTemplate($PARAMS);
} else if ($action == 'delete_email_template') {   //action to delete an email template
    $res = Salary::deleteEmailTemplate($PARAMS);
}  else if ($action == 'get_unapproved_machine_list') {
    $res = HR::getUnapprovedMachineList();
} else if ($action == 'add_user_comment') {
    $serial_number = $PARAMS['serial_number'];
    $user_id = $PARAMS['user_id'];
    $comment = $PARAMS['comment'];
    $res = HR::userCommentOnMachine($user_id,$serial_number,$comment);
} else if ($action == 'get_email_template_byId') { //aciton to get an email template by id
    $res = Salary::getEmailTemplateById($PARAMS);
} else if ($action == 'add_team_list') {  //action to add or update team list
    $PARAMS['value'] = json_encode($PARAMS['value']);
    $res = Salary::saveTeamList($PARAMS);
} else if ($action == 'get_team_list') {
    $res = Salary::getTeamList();
} else if ($action == 'get_team_users_detail') {   //action to get all employee details on basis of team
    $team = $PARAMS['team'];
    $res = Salary::getAllUserInfo($team);
} else if ($action == 'get_user_policy_document') {    // action to get user policy document.
    $res = Salary::getUserPolicyDocument($user_id);
} else if ($action == 'update_user_policy_document') { // action to update user policy document
    $PARAMS['user_id'] = $user_id;
    $res = Salary::updateUserPolicyDocument($PARAMS);
    // update user token when he read doc
    $newToken = HR::refreshToken( $token );
    $res['data']['new_token'] = $newToken;
} else if ($action == 'get_policy_document') { //action to get policy document
    $res = Salary::getPolicyDocument();
} else if ($action == 'save_policy_document') {    //action to save policy document
   $res = Salary::savePolicyDocument($PARAMS);
} else if ($action == 'get_all_clients') { //action to get all client details
    $res = Salary::getAllClient();
} else if ($action == 'get_client_detail') {   //action to get client detail.
    if (isset($PARAMS['client_id']) && $PARAMS['client_id'] != "") {
        $client_id = $PARAMS['client_id'];
        $res = Salary::getClientDetails($client_id);
    } else {
        $res['data']['message'] = 'Please give client_id ';
    }
} else if ($action == 'create_new_client') {   //action to create a new client.
    if (!isset($PARAMS['name']) || $PARAMS['name'] == "") {
        $res['data']['message'][] = 'Please Insert name';
    }
    if (!isset($PARAMS['address']) || $PARAMS['address'] == "") {
        $res['data']['message'][] = 'Please Insert address';
    } else {
        $res = Salary::createNewClient($PARAMS);
    }
} else if ($action == 'update_client_details') {   //action to update client details
    if (isset($PARAMS['client_id']) && $PARAMS['client_id'] != "") {
        $clientid = $PARAMS['client_id'];
        $res = Salary::UpdateClientDetails($PARAMS);
    } else {
        $res['data']['message'] = 'Please give client_id ';
    }
} else if ($action == 'create_client_invoice') {   //action to create a client invoice
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
} else if ($action == 'delete_invoice') {  //action to delete client invoice
    if (isset($PARAMS['invoice_id']) && $PARAMS['invoice_id'] != "") {
        $invoiceid = $PARAMS['invoice_id'];
        $res = Salary::DeleteInvoice($PARAMS);
    } else {
        $res['data']['message'] = 'Please give invoice_id ';
    }
} else if ($action == 'get_user_profile_detail') { //action to get employee profile detail
    $res = Salary::getUserDetailInfo($user_id);
} else if ($action == 'get_user_profile_detail_by_id') { //action to get employee profile detail
    if (isset($PARAMS['user_id']) && $PARAMS['user_id'] != "") {
        $user_id = $PARAMS['user_id'];
        $res = Salary::getUserDetailInfo($user_id);
        if( isset($PARAMS['secret_key']) || $PARAMS['secret_key'] != "" ){
            $validate_secret = HR::validateSecretKey($PARAMS['secret_key']);
            if($validate_secret){
                $secureKeys = [ 'bank_account_num', 'blood_group', 'address1', 'address2', 'emergency_ph1', 'emergency_ph2', 'medical_condition', 'dob', 'marital_status', 'city', 'state', 'zip_postal', 'country', 'home_ph', 'mobile_ph', 'work_email', 'other_email', 'special_instructions', 'pan_card_num', 'permanent_address', 'current_address', 'slack_id', 'policy_document', 'training_completion_date', 'termination_date', 'training_month', 'slack_msg', 'signature', 'role_id', 'role_name', 'eth_token' ];
                foreach( $res['data']['user_profile_detail'] as $key => $r ){
                    foreach( $secureKeys as $secureKey ){
                        if( $key == $secureKey ){
                            unset($res['data']['user_profile_detail'][$key]);
                        }
                    }
                }
            }        
        }
    } else {
        $res['data']['message'] = 'Please give user_id ';
    }
} else if ($action == 'update_user_profile_detail') {  //action to update employee profile detail.
    $PARAMS['user_id'] = $user_id;
    $res = Salary::UpdateUserInfo($PARAMS);
} else if ($action == 'update_user_profile_detail_by_id') {  //action to update employee profile detail.
    if (isset($PARAMS['user_id']) && $PARAMS['user_id'] != "") {
        $user_id = $PARAMS['user_id'];
        $update = true;
        $tr_completion_date = $PARAMS['training_completion_date'];
        if( isset($tr_completion_date) && $tr_completion_date != "" && $tr_completion_date != '0000-00-00' ) {
            $sal_details = Salary::getSalaryInfo($user_id);
            if(count($sal_details) > 1) {
                
            } else {
                $update = false;
                $res['data']['message'] = 'You have to add salary first.';
            }
        }

        if($update) {
            $res = Salary::UpdateUserInfo($PARAMS);
        }
        
    } else {
        $res['data']['message'] = 'Please give user_id ';
    }
} else if ($action == 'update_user_bank_detail') { //action to update employee bank details
    $PARAMS['user_id'] = $user_id;
    $res = Salary::UpdateUserBankInfo($PARAMS);
} else if ($action == 'update_user_bank_detail_by_id') { //action to update employee bank details
    if (isset($PARAMS['user_id']) && $PARAMS['user_id'] != "") {
        $user_id = $PARAMS['user_id'];
        $res = Salary::UpdateUserBankInfo($PARAMS);
    } else {
        $res['data']['message'] = 'Please give user_id ';
    }
} else if ($action == 'create_user_salary') { //action  to generate user salary.
    if (isset($PARAMS['user_id']) && $PARAMS['user_id'] != "") {
        $user_id = $PARAMS['user_id'];
        $res = Salary::generateUserSalary($user_id);
    } else {
        $res['data']['message'] = 'Please give user_id ';
    }
} else if ($action == 'create_employee_salary_slip') { //action to create an employee salary slip pdf
    if (isset($PARAMS['user_id']) && $PARAMS['user_id'] != "") {
        $res = Salary::createUserPayslip($PARAMS);
    } else {
        $res['data']['message'] = 'Please give user_id ';
    }
} else if ($action == 'get_user_manage_payslips_data') {   //aciton to get employee last month salary details
    if (isset($PARAMS['user_id']) && $PARAMS['user_id'] != "") {

        if( $IS_SUPER_ADMIN ==  false ){
            $res = array();
            $res['error'] = 0;
            $r_data = array();
            $r_data['all_users_latest_payslip'] = array();
            $res['data'] = $r_data;
        }else{
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
                    $month = date("m", strtotime ( '-1 month' , strtotime ( $currentMonth ) )) ;
                } else {
                    $year = $currentYear;
                    $month = date("m", strtotime ( '-1 month' , strtotime ( $currentMonth ) )) ;
                }
            }
            $res = Salary::getUserManagePayslip($userid, $year, $month, $extra_arrear, $arrear_for_month);
        }
    } else {
        $res['data']['message'] = 'Please give user_id ';
    }
} else if ($action == 'get_user_document') {   //action to get employee document detail
    $res = Salary::getUserDocumentDetail($user_id);
} else if ($action == 'get_user_document_by_id') {   //action to get employee document detail
    if (isset($PARAMS['user_id']) && $PARAMS['user_id'] != "") {
        $user_id = $PARAMS['user_id'];
        $res = Salary::getUserDocumentDetail($user_id);
    } else {
        $res['data']['message'] = 'Please give user_id ';
    }
} else if ($action == 'delete_user_document') {    //action to delete employee document detail.
    if (isset($PARAMS['id']) && $PARAMS['id'] != "") {
        $id = $PARAMS['id'];
        $res = Salary::deleteUserDocument($id);
    } else {
        $res['data']['message'] = 'Please give document id';
    }
} else if ($action == 'delete_salary') {    //action to delete employee salary detail
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
} else if ($action == 'send_payslips_to_employees') {  //action to send payslip slack notification to employee slack channel
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
} else if ($action == 'send_employee_email') { //action to send employee  email
    $res = Salary::sendEmail($PARAMS);
} else if ($action == 'cancel_applied_leave') {    //action to cancel employee applied leaves
    if (isset($PARAMS['user_id']) && $PARAMS['user_id'] != "") {
        $res = Salary::cancelAppliedLeave($PARAMS);
    } else {
        $res['data']['message'] = 'Please give user_id ';
    }
} else if ($action == 'create_pdf') {  //action to create a template varible
    $include_header_footer = true;
    if( isset($PARAMS['include_header_footer']) && $PARAMS['include_header_footer'] == 0 ){
        $include_header_footer = false;
    }
    $res = Salary::createEmailTempPdf($PARAMS, $include_header_footer);
} else if ($action == 'update_read_document') {    //action to create a template varible
    $doc_id = $PARAMS['document_id'];
    if ($user_id != "") {
        $res = Salary::UpdateDocumentDetail($user_id, $doc_id);
    } else {
        $res['data']['message'] = 'Please  give the user id';
    }
}


else if( $action == 'get_user_salary_info' ){
    $res['error'] = 0;
    $res['data'] = $userinfo;
    $userSalaryInfo = Salary::getSalaryInfo($user_id);
    $res3 = Salary::getHoldingDetail($user_id);
    $res4 = Salary::getUserPayslipInfo($user_id, true ); // true is passed to hide payslip data
    $i = 0;
    foreach ($userSalaryInfo as $val) {

        $res2 = Salary::getSalaryDetail($val['id']);

        $res2['test'] = $val;
        $res2['date'] = $val['applicable_from'];
        $res['data']['salary_details'][] = $res2;

        $i++;
    }
    $res['data']['holding_details'] = $res3;
    $res['data']['payslip_history'] = $res4;
}

else if( $action == 'get_user_salary_info_by_id' ){

    if (isset($PARAMS['user_id']) && $PARAMS['user_id'] != "") {
        $userid = $PARAMS['user_id'];
        $userinfo = Salary::getUserDetail($userid);
        if (sizeof($userinfo) <= 0) {
            $res['error'] = 1;
            $res['message'] = 'The given user id member not found';
            //$res['error'][] = 'The given user id member not found';
        } else {
            $res['error'] = 0;
            $res['data'] = $userinfo;
            $res3 = Salary::getHoldingDetail($userid);
            $resData = Salary::getSalaryInfo($userid);

            // start - check added so that salary greater than not to show to HR
            $hideSalaryFromHR = false;
            if( sizeof($resData ) > 0 ){
                foreach($resData as $salCheck ){
                    if( $salCheck['total_salary'] && $salCheck['total_salary'] > 20000 ){
                        $hideSalaryFromHR = true;
                    }
                }
            }
            // end - check added so that salary greater than not to show to HR

            $res4 = Salary::getUserPayslipInfo($userid);
            $i = 0;
            $res['data']['salary_details'] = array();
            foreach ($resData as $val) {
                $res2 = Salary::getSalaryDetail($val['id']);
                $res2['test'] = $val;
                $res2['date'] = $val['applicable_from'];
                $res['data']['salary_details'][] = $res2;
                $i++;
            }
            $res['data']['holding_details'] = $res3;
            $res['data']['payslip_history'] = $res4;

            $joining_date = $res['data']['date_of_joining'];
            $current_date = date("Y-m-d");
            $endDate = strtotime($current_date);
            $startDate = strtotime($joining_date);

            $numberOfMonths = abs((date('Y', $endDate) - date('Y', $startDate)) * 12 + (date('m', $endDate) - date('m', $startDate)));

            // arun you have to work on this to enable this for hr role
            if( strtolower( $loggedUserInfo['role'] ) == "hr"  &&  ( $numberOfMonths > 8 || $hideSalaryFromHR == true) ){
                $res['data'] = array();
                $res['data']['message'] = "You are not authorise to view this user data";
            }

        }
    } else {
        $res['error'] = 1;
        $res['message'] = 'The given user id member not found';
        //$res['error'][] = 'The given user id number';
    }

}


echo json_encode($res);
?>