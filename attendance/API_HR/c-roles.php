<?php

//require_once 'c-database.php';

trait Roles {

    //pages
    static $PAGE_home = 101;
    static $PAGE_monthly_attendance = 102;
    static $PAGE_inventory_system = 103;
    static $PAGE_manage_payslips = 104;
    static $PAGE_manage_working_hours = 105;
    static $PAGE_holidays = 107;
    static $PAGE_team_view = 108;
    static $PAGE_apply_leave = 109;
    static $PAGE_manage_leaves = 110;
    static $PAGE_my_leaves = 111;
    static $PAGE_disabled_employes = 112;
    static $PAGE_manage_user_working_hours = 113;
    static $PAGE_leaves_summary = 114;
    static $PAGE_salary = 115;
    static $PAGE_manage_salary = 116;
    static $PAGE_my_profile = 117;
    static $PAGE_my_inventory = 118;
    static $PAGE_manage_users = 119;
    static $PAGE_manage_clients = 120;
    static $PAGE_forgot_password = 121;
    static $PAGE_documents = 122;
    static $PAGE_uploadAttendance = 123;
    static $PAGE_view_salary = 124;
    static $PAGE_policy_documents = 125;
    static $PAGE_upload_policy_documents = 126;
    static $PAGE_add_variables = 127;
    static $PAGE_mail_templates = 128;
    static $PAGE_login = 129;
    static $PAGE_manage_roles = 130;
    static $PAGE_manage_user_pending_hours = 131;
    static $PAGE_logout = 132;
    static $PAGE_add_documents = 133;
    static $PAGE_health_stats = 134;
    static $PAGE_settings = 135;





    //action
    static $ACTION_working_hours_summary = 201;
    static $ACTION_add_new_employee = 202;
    static $ACTION_add_user_working_hours = 203;
    static $ACTION_get_user_worktime_detail = 204;
    static $ACTION_update_user_day_summary = 205;
    static $ACTION_change_leave_status = 206;
    static $ACTION_get_my_leaves = 207;
    static $ACTION_get_enable_user = 208;
    static $ACTION_month_attendance = 209;
    static $ACTION_get_all_leaves = 210;
    static $ACTION_apply_leave = 211;
    static $ACTION_show_disabled_users = 212;
    static $ACTION_change_employee_status = 213;
    static $ACTION_get_holidays_list = 214;
    static $ACTION_admin_user_apply_leave = 215;
    static $ACTION_update_new_password = 216;
    static $ACTION_get_managed_user_working_hours = 217;
    static $ACTION_get_user_previous_month_time = 218;
    static $ACTION_get_all_user_previous_month_time = 219;
    static $ACTION_update_day_working_hours = 220;
    static $ACTION_delete_employee = 221;
    static $ACTION_add_hr_comment = 222;
    static $ACTION_add_extra_leave_day = 222;
    static $ACTION_send_request_for_doc = 223;
    static $ACTION_update_user_entry_exit_time = 224;
    static $ACTION_save_google_payslip_drive_access_token = 225;
    static $ACTION_attendance_summary = 226;
    static $ACTION_user_day_summary = 227;
    static $ACTION_get_all_leaves_summary = 228;
    static $ACTION_get_users_leaves_summary = 229;
    static $ACTION_get_user_role_from_slack_id = 230;
    static $ACTION_get_all_not_approved_leave_of_user = 231;
    static $ACTION_approve_decline_leave_of_user = 232;
    //static $ACTION_cancel_applied_leave = 233;  // since this is also user in sal_info/api.php
    static $ACTION_cancel_applied_leave_admin = 234;
    static $ACTION_get_all_leaves_of_user = 235;
    static $ACTION_get_user_current_status = 236;
    static $ACTION_get_role_from_slackid = 237;
    static $ACTION_updatebandwidthstats = 238;
    static $ACTION_save_bandwidth_detail = 239;
    static $ACTION_get_bandwidth_detail = 240;
    static $ACTION_validate_unique_key = 241;
    static $ACTION_send_slack_msg = 242;
    static $ACTION_get_all_users_detail = 243;
    static $ACTION_get_holiday_types_list = 244;    

    static $ACTION_get_all_clients = 301;
    static $ACTION_get_client_detail = 302;
    static $ACTION_create_new_client = 303;
    static $ACTION_update_client_details = 304;
    static $ACTION_create_client_invoice = 305;
    static $ACTION_delete_invoice = 306;


    static $ACTION_delete_role = 401;
    static $ACTION_assign_user_role = 402;
    static $ACTION_list_all_roles = 403;
    static $ACTION_update_role = 404;
    static $ACTION_add_roles = 405;

    static $ACTION_get_machine_count = 501;
    static $ACTION_get_machine_status_list = 502;
    static $ACTION_add_machine_status = 503;
    static $ACTION_add_machine_type = 504;
    static $ACTION_get_machine_type_list = 505;
    static $ACTION_delete_machine_status = 506;
    static $ACTION_add_office_machine = 507;
    static $ACTION_update_office_machine = 508;
    static $ACTION_get_machine = 509;
    static $ACTION_get_machines_detail = 510;
    static $ACTION_remove_machine_detail = 511;
    static $ACTION_assign_user_machine = 512;
    static $ACTION_get_user_machine = 513;

    static $ACTION_unassigned_my_inventory = 514;
    static $ACTION_get_unassigned_inventories = 515;
    static $ACTION_get_unapproved_inventories = 516;
    static $ACTION_get_my_inventories = 517;
    static $ACTION_add_inventory_comment = 518;
    static $ACTION_add_inventory_audit = 519;
    static $ACTION_get_inventory_audit_status_month_wise = 520;

    //actions not required token
    static $ACTION_login = 601;
    static $ACTION_logout = 602;
    static $ACTION_forgot_password = 603;
    static $ACTION_get_days_between_leaves = 604;

    //template actions
    static $ACTION_get_template_variable = 701;
    static $ACTION_create_template_variable = 702;
    static $ACTION_update_template_variable = 703;
    static $ACTION_delete_template_variable = 704;
    static $ACTION_get_email_template = 705;
    static $ACTION_create_email_template = 706;
    static $ACTION_update_email_template = 707;
    static $ACTION_delete_email_template = 708;
    static $ACTION_get_email_template_byId = 709;

    //team actions
    static $ACTION_add_team_list = 801;
    static $ACTION_get_team_list = 802;
    static $ACTION_get_team_users_detail = 803;

    //policy documents
    static $ACTION_get_user_policy_document = 901;
    static $ACTION_update_user_policy_document = 902;
    static $ACTION_get_policy_document = 903;
    static $ACTION_save_policy_document = 904;

    //lunch actions
    static $ACTION_get_lunch_stats = 7001;
    static $ACTION_get_lunch_break_detail = 7002;
    static $ACTION_lunch_break = 7003;

    // profile, employee, salary .bank
    static $ACTION_get_user_profile_detail = 8001;
    static $ACTION_update_user_profile_detail = 8002;
    static $ACTION_update_user_bank_detail = 8003;
    static $ACTION_create_user_salary = 8004;
    static $ACTION_create_employee_salary_slip = 8005;
    static $ACTION_get_user_manage_payslips_data = 8006;
    static $ACTION_get_user_document = 8007;
    static $ACTION_delete_user_document = 8008;
    static $ACTION_delete_salary = 8009;
    static $ACTION_send_payslips_to_employees = 8010;
    static $ACTION_send_employee_email = 8011;
    static $ACTION_cancel_applied_leave = 8012;
    static $ACTION_create_pdf = 8013;
    static $ACTION_update_read_document = 8014;
    static $ACTION_get_user_salary_info = 8015;
    static $ACTION_get_user_profile_detail_by_id = 8016;
    static $ACTION_update_user_profile_detail_by_id = 8017;
    static $ACTION_update_user_bank_detail_by_id = 8018;
    static $ACTION_get_user_document_by_id = 8019;
    static $ACTION_get_user_salary_info_by_id = 8020;

    static $ACTION_get_employee_life_cycle = 8021;
    static $ACTION_update_employee_life_cycle = 8022;
    
    static $ACTION_update_user_meta_data = 8023;
    static $ACTION_delete_user_meta_data = 8024;
    static $ACTION_get_user_meta_data = 8025;    
    static $ACTION_employee_punch_time = 8026;
    static $ACTION_get_employee_recent_punch_time = 8027;
    static $ACTION_get_employee_punches_by_date = 8028;
    static $ACTION_get_employees_monthly_attendance = 8029;
    static $ACTION_get_my_rh_leaves = 8030;    
    static $ACTION_get_user_rh_stats = 8031;    
    
    




    //notification
    static $NOTIFICATION_apply_leave = 1001;
    static $NOTIFICATION_update_leave_status = 1002;
    static $NOTIFICATION_add_user_working_hours = 1003;

    // action approve reject manual attendance
    static $ACTION_add_manual_attendance = 11001;
    static $ACTION_reject_manual_attendance = 11002;
    static $ACTION_approve_manual_attendance = 11003;
    static $ACTION_get_average_working_hours = 11004;

    // action for ETHER
    static $ACTION_update_user_eth_token = 22001;


    /////IMPORTANT
    /////name cannot be change since they are used in api calling from frontend
    /////IMPORTANT

   public static function getAllPages() {
        $array = array(
            array(
                'id' => self::$PAGE_home,
                'name' => 'home',
                'actions_list' => array(
                    array( 'id' => self::$ACTION_month_attendance, 'name' => 'month_attendance' ),
                    array( 'id' => self::$ACTION_update_user_day_summary, 'name' => 'update_user_day_summary' ),
                    array( 'id' => self::$ACTION_user_day_summary, 'name' => 'user_day_summary' ),
                )
            ),

            array(
                'id' => self::$PAGE_monthly_attendance,
                'name' => 'monthly_attendance',
                'actions_list' => array(
                    array( 'id' => self::$ACTION_working_hours_summary,'name' => 'working_hours_summary' ),
                    array( 'id' => self::$ACTION_add_new_employee, 'name' => 'add_new_employee' ),
                    array( 'id' => self::$ACTION_add_user_working_hours, 'name' => 'add_user_working_hours' ),
                )
            ),
            array(
                'id' => self::$PAGE_inventory_system,
                'name' => 'inventoryOverviewDetail',
                'actions_list' => array(
                    array( 'id' => self::$ACTION_get_machines_detail, 'name' => 'get_machines_detail' ),
                    array( 'id' => self::$ACTION_get_machine_type_list, 'name' => 'get_machine_type_list' ),
                    array( 'id' => self::$ACTION_get_machine_count, 'name' => 'get_machine_count' ),
                    array( 'id' => self::$ACTION_get_machine_status_list, 'name' => 'get_machine_status_list' ),
                    array( 'id' => self::$ACTION_add_machine_status, 'name' => 'add_machine_status' ),
                    array( 'id' => self::$ACTION_add_machine_type, 'name' => 'add_machine_type' ),
                    array( 'id' => self::$ACTION_delete_machine_status, 'name' => 'delete_machine_status' ),
                    array( 'id' => self::$ACTION_add_office_machine, 'name' => 'add_office_machine' ),
                    array( 'id' => self::$ACTION_update_office_machine, 'name' => 'update_office_machine' ),
                    array( 'id' => self::$ACTION_remove_machine_detail, 'name' => 'remove_machine_detail' ),
                    array( 'id' => self::$ACTION_assign_user_machine, 'name' => 'assign_user_machine' ),
                    array( 'id' => self::$ACTION_get_user_machine, 'name' => 'get_user_machine' ),
                    array( 'id' => self::$ACTION_get_machine, 'name' => 'get_machine' ),
                    array( 'id' => self::$ACTION_unassigned_my_inventory, 'name' => 'unassigned_my_inventory' ),
                    array( 'id' => self::$ACTION_get_unassigned_inventories, 'name' => 'get_unassigned_inventories' ),
                    array( 'id' => self::$ACTION_get_unapproved_inventories, 'name' => 'get_unapproved_inventories' ),
                    array( 'id' => self::$ACTION_get_my_inventories, 'name' => 'get_my_inventories' ),
                    array( 'id' => self::$ACTION_add_inventory_comment, 'name' => 'add_inventory_comment' ),
                    array( 'id' => self::$ACTION_add_inventory_audit, 'name' => 'add_inventory_audit' ),
                    array( 'id' => self::$ACTION_get_inventory_audit_status_month_wise, 'name' => 'get_inventory_audit_status_month_wise' ),
                )
            ),


            array(
                'id' => self::$PAGE_manage_working_hours,
                'name' => 'manage_working_hours',
                'actions_list' => array(
                    array( 'id' => self::$ACTION_working_hours_summary,'name' => 'working_hours_summary' ),
                    array( 'id' => self::$ACTION_update_day_working_hours, 'name' => 'update_day_working_hours' ),
                    array( 'id' => self::$ACTION_add_manual_attendance,'name' => 'add_manual_attendance' ),
                )
            ),

            array(
                'id' => self::$PAGE_holidays,
                'name' => 'holidays',
                'actions_list' => array(
                    array( 'id' => self::$ACTION_get_holidays_list, 'name' => 'get_holidays_list' ),
                )
            ),

            array(
                'id' => self::$PAGE_team_view,
                'name' => 'team_view',
                'actions_list' => array(
                    array( 'id' => self::$ACTION_add_team_list, 'name' => 'add_team_list' ),
                    array( 'id' => self::$ACTION_get_team_list, 'name' => 'get_team_list' ),
                    array( 'id' => self::$ACTION_get_team_users_detail, 'name' => 'get_team_users_detail' ),
                )
            ),

            array(
                'id' => self::$PAGE_apply_leave,
                'name' => 'apply_leave',
                'actions_list' =>  array(
                    array( 'id' => self::$ACTION_apply_leave, 'name' => 'apply_leave' ),
                )
            ),

            array(
                'id' => self::$PAGE_my_leaves,
                'name' => 'my_leaves',
                'actions_list' => array(
                    array( 'id' => self::$ACTION_get_my_leaves, 'name' => 'get_my_leaves' ),
                    array( 'id' => self::$ACTION_cancel_applied_leave, 'name' => 'cancel_applied_leave' ),
                    array( 'id' => self::$ACTION_get_my_rh_leaves, 'name' => 'get_my_rh_leaves' ), 
                    array( 'id' => self::$ACTION_get_user_rh_stats, 'name' => 'get_user_rh_stats' ),                   
                )
            ),

            array(
                'id' => self::$PAGE_disabled_employes,
                'name' => 'disabled_employes',
                'actions_list' => array(
                    array( 'id' => self::$ACTION_show_disabled_users, 'name' => 'show_disabled_users' ),
                    array( 'id' => self::$ACTION_change_employee_status, 'name' => 'change_employee_status' ),

                )
            ),



            array(
                'id' => self::$PAGE_leaves_summary,
                'name' => 'leaves_summary',
                'actions_list' => array(
                    array( 'id' => self::$ACTION_get_all_leaves_summary, 'name' => 'get_all_leaves_summary' ),
                )
            ),

            array(
                'id' => self::$PAGE_salary,
                'name' => 'salary' ,
                'actions_list' => array(

                )
            ),

            array( 'id' => self::$PAGE_manage_salary, 'name' => 'manage_salary' ),
            array(
                'id' => self::$PAGE_my_profile,
                'name' => 'my_profile',
                'actions_list' => array(
                    array( 'id' => self::$ACTION_get_user_profile_detail,'name' => 'get_user_profile_detail' ),
                    array( 'id' => self::$ACTION_update_user_bank_detail,'name' => 'update_user_bank_detail' ),
                    array( 'id' => self::$ACTION_update_user_profile_detail,'name' => 'update_user_profile_detail' ),
                    array( 'id' => self::$ACTION_update_user_profile_detail_by_id,'name' => 'update_user_profile_detail_by_id' ),
                    array( 'id' => self::$ACTION_get_user_salary_info,'name' => 'get_user_salary_info' ),
                    array( 'id' => self::$ACTION_update_new_password, 'name' => 'update_new_password' ),
                    array( 'id' => self::$ACTION_delete_salary,'name' => 'delete_salary' ),
                    array( 'id' => self::$ACTION_update_user_eth_token,'name' => 'update_user_eth_token' ),
                )
            ),

            array(
                'id' => self::$PAGE_my_inventory,
                'name' => 'my_inventory',
                'actions_list' => array(

                )
            ),




            array( 'id' => self::$PAGE_uploadAttendance, 'name' => 'uploadAttendance' ),
            array(
                'id' => self::$PAGE_view_salary,
                'name' => 'view_salary',
                'actions_list' => array(
                    array( 'id' => self::$ACTION_get_all_users_detail, 'name' => 'get_all_users_detail' ),
                )
            ),

            array(
                'id' => self::$PAGE_policy_documents,
                'name' => 'policy_documents',
                'actions_list' => array(
                    array( 'id' => self::$ACTION_get_user_policy_document, 'name' => 'get_user_policy_document' ),
                )
            ),




            array( 'id' => self::$PAGE_login, 'name' => 'login' ),
            array( 'id' => self::$PAGE_logout, 'name' => 'logout' ),
            array(
                'id' => self::$PAGE_manage_roles,
                'name' => 'manage_roles',
                'actions_list' => array(
                    array( 'id' => self::$ACTION_delete_role, 'name' => 'delete_role' ),
                    array( 'id' => self::$ACTION_assign_user_role, 'name' => 'assign_user_role' ),
                    array( 'id' => self::$ACTION_list_all_roles, 'name' => 'list_all_roles' ),
                    array( 'id' => self::$ACTION_update_role, 'name' => 'update_role' ),
                    array( 'id' => self::$ACTION_add_roles, 'name' => 'add_roles' ),
                )
            ),

            array(
                'id' => self::$PAGE_add_documents,
                'name' => 'add_documents',
                'actions_list' => array(
                )
            ),

            array(
                'id' => self::$PAGE_manage_clients,
                'name' => 'manage_clients',
                'actions_list' => array(
                    array( 'id' => self::$ACTION_get_all_clients, 'name' => 'get_all_clients' ),
                    array( 'id' => self::$ACTION_get_client_detail, 'name' => 'get_client_detail' ),
                    array( 'id' => self::$ACTION_create_new_client, 'name' => 'create_new_client' ),
                    array( 'id' => self::$ACTION_update_client_details, 'name' => 'update_client_details' ),
                    array( 'id' => self::$ACTION_create_client_invoice, 'name' => 'create_client_invoice' ),
                    array( 'id' => self::$ACTION_delete_invoice, 'name' => 'delete_invoice' ),
                )
            ),

            array(
                'id' => self::$PAGE_manage_leaves,
                'name' => 'manage_leaves',
                'actions_list' => array(
                    array( 'id' => self::$ACTION_get_all_leaves, 'name' => 'get_all_leaves' ),
                    array( 'id' => self::$ACTION_change_leave_status, 'name' => 'change_leave_status' ),
                    array( 'id' => self::$ACTION_add_extra_leave_day, 'name' => 'add_extra_leave_day' ),
                    array( 'id' => self::$ACTION_send_request_for_doc, 'name' => 'send_request_for_doc' ),
                    array( 'id' => self::$ACTION_add_hr_comment, 'name' => 'add_hr_comment' ),
                )
            ),

            array(
                'id' => self::$PAGE_mail_templates,
                'name' => 'mail_templates',
                'actions_list' => array(
                    array( 'id' => self::$ACTION_update_template_variable, 'name' => 'update_template_variable' ),
                    array( 'id' => self::$ACTION_get_email_template, 'name' => 'get_email_template' ),
                    array( 'id' => self::$ACTION_create_email_template, 'name' => 'create_email_template' ),
                    array( 'id' => self::$ACTION_update_email_template, 'name' => 'update_email_template' ),
                    array( 'id' => self::$ACTION_delete_email_template, 'name' => 'delete_email_template' ),
                    array( 'id' => self::$ACTION_get_email_template_byId, 'name' => 'get_email_template_byId' ),
                    array( 'id' => self::$ACTION_send_employee_email,'name' => 'send_employee_email' ),
                    array( 'id' => self::$ACTION_create_pdf,'name' => 'create_pdf' ),
                )
            ),

            array(
                'id' => self::$PAGE_add_variables,
                'name' => 'add_variables',
                'actions_list' => array(
                    array( 'id' => self::$ACTION_get_template_variable, 'name' => 'get_template_variable' ),
                    array( 'id' => self::$ACTION_create_template_variable, 'name' => 'create_template_variable' ),
                    array( 'id' => self::$ACTION_delete_template_variable, 'name' => 'delete_template_variable' ),
                )
            ),

            array(
                'id' => self::$PAGE_upload_policy_documents,
                'name' => 'upload_policy_documents',
                'actions_list' =>  array(
                    array( 'id' => self::$ACTION_save_policy_document, 'name' => 'save_policy_document' ),
                    array( 'id' => self::$ACTION_get_policy_document, 'name' => 'get_policy_document' ),
                    array( 'id' => self::$ACTION_update_user_policy_document, 'name' => 'update_user_policy_document' ),
                )
            ),

            array(
                'id' => self::$PAGE_manage_users,
                'name' => 'manage_users',
                'actions_list' => array(
                    array( 'id' => self::$ACTION_get_enable_user, 'name' => 'get_enable_user' ),
                    array( 'id' => self::$ACTION_get_user_profile_detail_by_id,'name' => 'get_user_profile_detail_by_id' ),
                    array( 'id' => self::$ACTION_get_user_document_by_id,'name' => 'get_user_document_by_id' ),
                    array( 'id' => self::$ACTION_add_new_employee, 'name' => 'add_new_employee' ),
                    array( 'id' => self::$ACTION_delete_user_document,'name' => 'delete_user_document' ),
                    array( 'id' => self::$ACTION_get_employee_life_cycle,'name' => 'get_employee_life_cycle' ),
                    array( 'id' => self::$ACTION_update_employee_life_cycle,'name' => 'update_employee_life_cycle' ),
                )
            ),

            array(
                'id' => self::$PAGE_manage_user_pending_hours,
                'name' => 'manage_user_pending_hours',
                'actions_list' => array(
                    array( 'id' => self::$ACTION_admin_user_apply_leave, 'name' => 'admin_user_apply_leave' ),
                    array( 'id' => self::$ACTION_get_all_user_previous_month_time, 'name' => 'get_all_user_previous_month_time' ),
                )
            ),

            array(
                'id' => self::$PAGE_manage_payslips,
                'name' => 'manage_payslips',
                'actions_list' => array(
                    array( 'id' => self::$ACTION_create_employee_salary_slip,'name' => 'create_employee_salary_slip' ),
                    array( 'id' => self::$ACTION_send_payslips_to_employees,'name' => 'send_payslips_to_employees' ),
                    array( 'id' => self::$ACTION_get_user_manage_payslips_data,'name' => 'get_user_manage_payslips_data' ),
                    array( 'id' => self::$ACTION_save_google_payslip_drive_access_token, 'name' => 'save_google_payslip_drive_access_token' ),
                    array( 'id' => self::$ACTION_get_user_salary_info_by_id,'name' => 'get_user_salary_info_by_id' ),
                )
            ),
            array(
                'id' => self::$PAGE_documents,
                'name' => 'documents',
                'actions_list' => array(
                    array( 'id' => self::$ACTION_get_user_document,'name' => 'get_user_document' ),
                )
            ),
            array(
                'id' => self::$PAGE_manage_user_working_hours,
                'name' => 'manage_user_working_hours',
                'actions_list' => array(
                    array( 'id' => self::$ACTION_add_user_working_hours, 'name' => 'add_user_working_hours' ),
                    array( 'id' => self::$ACTION_get_managed_user_working_hours, 'name' => 'get_managed_user_working_hours' ),
                )
            ),
            array(
                'id' => self::$PAGE_health_stats,
                'name' => 'health_stats',
                'actions_list' => array(
                    
                )
            ),
            array(
                'id' => self::$PAGE_settings,
                'name' => 'settings',
                'actions_list' => array(
                    
                )
            ),

        );

        return $array;
    }

    public static function getAllActions() {
        $array = array();
        $allPages = self::getAllPages();
        foreach( $allPages as $page ){
            if( isset( $page['actions_list']) ){
                foreach( $page['actions_list'] as $action ){
                    $array[] = $action;
                }
            }
        }

        return $array;

        $array = array(

            // start - uncategorised actions
            array( 'id' => self::$ACTION_get_user_worktime_detail, 'name' => 'get_user_worktime_detail' ),

            array( 'id' => self::$ACTION_get_user_previous_month_time, 'name' => 'get_user_previous_month_time' ),
            array( 'id' => self::$ACTION_delete_employee, 'name' => 'delete_employee' ),

            array( 'id' => self::$ACTION_update_user_entry_exit_time, 'name' => 'update_user_entry_exit_time' ),
            array( 'id' => self::$ACTION_attendance_summary, 'name' => 'attendance_summary' ),
            array( 'id' => self::$ACTION_get_users_leaves_summary, 'name' => 'get_users_leaves_summary' ),
            array( 'id' => self::$ACTION_get_user_role_from_slack_id, 'name' => 'get_user_role_from_slack_id' ),
            array( 'id' => self::$ACTION_get_all_not_approved_leave_of_user, 'name' => 'get_all_not_approved_leave_of_user' ),
            array( 'id' => self::$ACTION_approve_decline_leave_of_user, 'name' => 'approve_decline_leave_of_user' ),
            array( 'id' => self::$ACTION_cancel_applied_leave_admin, 'name' => 'cancel_applied_leave_admin' ),
            array( 'id' => self::$ACTION_get_all_leaves_of_user, 'name' => 'get_all_leaves_of_user' ),
            array( 'id' => self::$ACTION_get_user_current_status, 'name' => 'get_user_current_status' ),
            array( 'id' => self::$ACTION_get_role_from_slackid, 'name' => 'get_role_from_slackid' ),





            array( 'id' => self::$ACTION_update_user_bank_detail_by_id,'name' => 'update_user_bank_detail_by_id' ),
            array( 'id' => self::$ACTION_create_user_salary,'name' => 'create_user_salary' ),
            array( 'id' => self::$ACTION_get_user_manage_payslips_data,'name' => 'get_user_manage_payslips_data' ),

            array( 'id' => self::$ACTION_update_read_document,'name' => 'update_read_document' ),
            
            
            // end - uncategorised actions

            // actions not required token
            // array( 'id' => self::$ACTION_update_user_profile_detail_by_id,'name' => 'update_user_profile_detail_by_id' ),
            // array( 'id' => self::$ACTION_get_lunch_stats, 'name' => 'get_lunch_stats' ),
            // array( 'id' => self::$ACTION_get_lunch_break_detail, 'name' => 'get_lunch_break_detail' ),
            // array( 'id' => self::$ACTION_lunch_break, 'name' => 'lunch_break' ),

            // below aree added in pages
            // array( 'id' => self::$ACTION_create_pdf,'name' => 'create_pdf' ),
            // array( 'id' => self::$ACTION_get_machine, 'name' => 'get_machine' ),
            // array( 'id' => self::$ACTION_get_user_machine, 'name' => 'get_user_machine' ),
            // array( 'id' => self::$ACTION_change_employee_status, 'name' => 'change_employee_status' ),
            // array( 'id' => self::$ACTION_add_hr_comment, 'name' => 'add_hr_comment' ),
            // array( 'id' => self::$ACTION_apply_leave, 'name' => 'apply_leave' ),
            // array( 'id' => self::$ACTION_update_day_working_hours, 'name' => 'update_day_working_hours' ),
            // array( 'id' => self::$ACTION_get_managed_user_working_hours, 'name' => 'get_managed_user_working_hours' ),
            // array( 'id' => self::$ACTION_user_day_summary, 'name' => 'user_day_summary' ),
            // array( 'id' => self::$ACTION_update_user_day_summary, 'name' => 'update_user_day_summary' ),
            // array( 'id' => self::$ACTION_month_attendance, 'name' => 'month_attendance' ),
            // array( 'id' => self::$ACTION_cancel_applied_leave,'name' => 'cancel_applied_leave' ),
            // array( 'id' => self::$ACTION_get_all_user_previous_month_time, 'name' => 'get_all_user_previous_month_time' ),
            // array( 'id' => self::$ACTION_add_user_working_hours, 'name' => 'add_user_working_hours' ),
            // array( 'id' => self::$ACTION_get_all_leaves_summary, 'name' => 'get_all_leaves_summary' ),
            // array( 'id' => self::$ACTION_get_user_document,'name' => 'get_user_document' ),
            // array( 'id' => self::$ACTION_delete_user_document,'name' => 'delete_user_document' ),
            // array( 'id' => self::$ACTION_cancel_applied_leave, 'name' => 'cancel_applied_leave' ),
            // array( 'id' => self::$ACTION_get_all_users_detail, 'name' => 'get_all_users_detail' ),
            // array( 'id' => self::$ACTION_delete_salary,'name' => 'delete_salary' ),
            // array( 'id' => self::$ACTION_get_user_salary_info_by_id,'name' => 'get_user_salary_info_by_id' ),
            // array( 'id' => self::$ACTION_remove_machine_detail, 'name' => 'remove_machine_detail' ),
            // array( 'id' => self::$ACTION_assign_user_machine, 'name' => 'assign_user_machine' ),
            // array( 'id' => self::$ACTION_delete_machine_status, 'name' => 'delete_machine_status' ),
            // array( 'id' => self::$ACTION_add_office_machine, 'name' => 'add_office_machine' ),
            // array( 'id' => self::$ACTION_update_office_machine, 'name' => 'update_office_machine' ),
            // array( 'id' => self::$ACTION_add_machine_status, 'name' => 'add_machine_status' ),
            // array( 'id' => self::$ACTION_add_machine_type, 'name' => 'add_machine_type' ),
            // array( 'id' => self::$ACTION_update_new_password, 'name' => 'update_new_password' ),
            // array( 'id' => self::$ACTION_update_user_profile_detail,'name' => 'update_user_profile_detail' ),
            // array( 'id' => self::$ACTION_update_user_bank_detail,'name' => 'update_user_bank_detail' ),
            // array( 'id' => self::$ACTION_send_employee_email,'name' => 'send_employee_email' ),
            // array( 'id' => self::$ACTION_save_google_payslip_drive_access_token, 'name' => 'save_google_payslip_drive_access_token' ),
            // array( 'id' => self::$ACTION_get_user_manage_payslips_data,'name' => 'get_user_manage_payslips_data' ),
            // array( 'id' => self::$ACTION_send_payslips_to_employees,'name' => 'send_payslips_to_employees' ),
            // array( 'id' => self::$ACTION_create_employee_salary_slip,'name' => 'create_employee_salary_slip' ),
            // array( 'id' => self::$ACTION_get_my_leaves, 'name' => 'get_my_leaves' ),
            // array( 'id' => self::$ACTION_working_hours_summary,'name' => 'working_hours_summary' ),
            // array( 'id' => self::$ACTION_get_holidays_list, 'name' => 'get_holidays_list' ),
            // array( 'id' => self::$ACTION_show_disabled_users, 'name' => 'show_disabled_users' ),
            // array( 'id' => self::$ACTION_get_all_leaves, 'name' => 'get_all_leaves' ),
            // array( 'id' => self::$ACTION_add_new_employee, 'name' => 'add_new_employee' ),
            // array( 'id' => self::$ACTION_get_user_salary_info,'name' => 'get_user_salary_info' ),
            // array( 'id' => self::$ACTION_get_user_document_by_id,'name' => 'get_user_document_by_id' ),
            // array( 'id' => self::$ACTION_get_user_profile_detail,'name' => 'get_user_profile_detail' ),
            // array( 'id' => self::$ACTION_get_user_profile_detail_by_id,'name' => 'get_user_profile_detail_by_id' ),
            // array( 'id' => self::$ACTION_get_machines_detail, 'name' => 'get_machines_detail' ),
            // array( 'id' => self::$ACTION_get_machine_type_list, 'name' => 'get_machine_type_list' ),
            // array( 'id' => self::$ACTION_get_machine_count, 'name' => 'get_machine_count' ),
            // array( 'id' => self::$ACTION_get_machine_status_list, 'name' => 'get_machine_status_list' ),
            // array( 'id' => self::$ACTION_get_all_clients, 'name' => 'get_all_clients' ),
            // array( 'id' => self::$ACTION_get_client_detail, 'name' => 'get_client_detail' ),
            // array( 'id' => self::$ACTION_create_new_client, 'name' => 'create_new_client' ),
            // array( 'id' => self::$ACTION_update_client_details, 'name' => 'update_client_details' ),
            // array( 'id' => self::$ACTION_create_client_invoice, 'name' => 'create_client_invoice' ),
            // array( 'id' => self::$ACTION_delete_invoice, 'name' => 'delete_invoice' ),

            // array( 'id' => self::$ACTION_get_template_variable, 'name' => 'get_template_variable' ),
            // array( 'id' => self::$ACTION_create_template_variable, 'name' => 'create_template_variable' ),
            // array( 'id' => self::$ACTION_update_template_variable, 'name' => 'update_template_variable' ),
            // array( 'id' => self::$ACTION_delete_template_variable, 'name' => 'delete_template_variable' ),
            // array( 'id' => self::$ACTION_get_email_template, 'name' => 'get_email_template' ),
            // array( 'id' => self::$ACTION_create_email_template, 'name' => 'create_email_template' ),
            // array( 'id' => self::$ACTION_update_email_template, 'name' => 'update_email_template' ),
            // array( 'id' => self::$ACTION_delete_email_template, 'name' => 'delete_email_template' ),
            // array( 'id' => self::$ACTION_get_email_template_byId, 'name' => 'get_email_template_byId' ),

            // array( 'id' => self::$ACTION_add_team_list, 'name' => 'add_team_list' ),
            // array( 'id' => self::$ACTION_get_team_list, 'name' => 'get_team_list' ),
            // array( 'id' => self::$ACTION_get_team_users_detail, 'name' => 'get_team_users_detail' ),

            // array( 'id' => self::$ACTION_update_user_policy_document, 'name' => 'update_user_policy_document' ),
            // array( 'id' => self::$ACTION_get_policy_document, 'name' => 'get_policy_document' ),
            // array( 'id' => self::$ACTION_save_policy_document, 'name' => 'save_policy_document' ),

            // array( 'id' => self::$ACTION_delete_role, 'name' => 'delete_role' ),
            // array( 'id' => self::$ACTION_assign_user_role, 'name' => 'assign_user_role' ),
            // array( 'id' => self::$ACTION_list_all_roles, 'name' => 'list_all_roles' ),
            // array( 'id' => self::$ACTION_update_role, 'name' => 'update_role' ),
            // array( 'id' => self::$ACTION_add_roles, 'name' => 'add_roles' ),
            // array( 'id' => self::$ACTION_add_extra_leave_day, 'name' => 'add_extra_leave_day' ),
            // array( 'id' => self::$ACTION_send_request_for_doc, 'name' => 'send_request_for_doc' ),
            // array( 'id' => self::$ACTION_change_leave_status, 'name' => 'change_leave_status' ),
            //array( 'id' => self::$ACTION_get_enable_user, 'name' => 'get_enable_user' ),
        );

        return $array;
    }

    public static function getAllNotifications() {
        return array(); // since this is not implemented no need to show for now
        // $array = array(
        //     array(
        //         'id' => self::$NOTIFICATION_apply_leave,
        //         'name' => 'applyLeave',
        //     ),
        //     array(
        //         'id' => self::$NOTIFICATION_update_leave_status,
        //         'name' => 'updateLeaveStatus'
        //     ),
        //     array(
        //         'id' => self::$NOTIFICATION_add_user_working_hours,
        //         'name' => 'addUserWorkingHours'
        //     )
        // );
        // return $array;
    }

    public static function getActionsNotRequiredToken(){
        $array = array(
            array( 'id' => self::$ACTION_login, 'name' => 'login' ),
            array( 'id' => self::$ACTION_logout, 'name' => 'logout' ),
            array( 'id' => self::$ACTION_forgot_password, 'name' => 'forgot_password' ),
            array( 'id' => self::$ACTION_get_days_between_leaves, 'name' => 'get_days_between_leaves' ),
            array( 'id' => self::$ACTION_updatebandwidthstats, 'name' => 'updatebandwidthstats' ),
            array( 'id' => self::$ACTION_send_slack_msg, 'name' => 'send_slack_msg' ),
            array( 'id' => self::$ACTION_save_bandwidth_detail, 'name' => 'save_bandwidth_detail' ),
            array( 'id' => self::$ACTION_get_bandwidth_detail, 'name' => 'get_bandwidth_detail' ),
            array( 'id' => self::$ACTION_validate_unique_key, 'name' => 'validate_unique_key' ),
            array( 'id' => self::$ACTION_get_user_policy_document, 'name' => 'get_user_policy_document' ),
            array( 'id' => self::$ACTION_get_lunch_stats, 'name' => 'get_lunch_stats' ),
            array( 'id' => self::$ACTION_get_lunch_break_detail, 'name' => 'get_lunch_break_detail' ),
            array( 'id' => self::$ACTION_lunch_break, 'name' => 'lunch_break' ),
            array( 'id' => self::$ACTION_approve_manual_attendance, 'name' => 'approve_manual_attendance' ),
            array( 'id' => self::$ACTION_reject_manual_attendance, 'name' => 'reject_manual_attendance' ),
            array( 'id' => self::$ACTION_get_average_working_hours, 'name' => 'get_average_working_hours' ),
            array( 'id' => self::$ACTION_get_holiday_types_list, 'name' => 'get_holiday_types_list' ),
        );
        return $array;
    }

    public static function getActionsForThirdPartyApiCall(){
        $array = array(
            array( 'id' => self::$ACTION_get_machines_detail, 'name' => 'get_machines_detail' ),
            array( 'id' => self::$ACTION_get_machine_type_list, 'name' => 'get_machine_type_list' ),
            array( 'id' => self::$ACTION_get_machine_status_list, 'name' => 'get_machine_status_list' ),
            array( 'id' => self::$ACTION_get_machine_count, 'name' => 'get_machine_count' ),
            array( 'id' => self::$ACTION_list_all_roles, 'name' => 'list_all_roles' ),
            array( 'id' => self::$ACTION_get_user_current_status, 'name' => 'get_user_current_status' ),
            array( 'id' => self::$ACTION_get_inventory_audit_status_month_wise, 'name' => 'get_inventory_audit_status_month_wise' ),
            array( 'id' => self::$ACTION_get_user_profile_detail_by_id, 'name' => 'get_user_profile_detail_by_id' ),
            array( 'id' => self::$ACTION_update_user_profile_detail_by_id, 'name' => 'update_user_profile_detail_by_id' ),            
            array( 'id' => self::$ACTION_update_user_day_summary, 'name' => 'update_user_day_summary' ),
            array( 'id' => self::$ACTION_get_enable_user, 'name' => 'get_enable_user' ),
            array( 'id' => self::$ACTION_update_user_meta_data, 'name' => 'update_user_meta_data' ),
            array( 'id' => self::$ACTION_delete_user_meta_data, 'name' => 'delete_user_meta_data' ),      
            array( 'id' => self::$ACTION_add_new_employee, 'name' => 'add_new_employee' ),
            array( 'id' => self::$ACTION_employee_punch_time, 'name' => 'employee_punch_time' ),  
            array( 'id' => self::$ACTION_get_employee_recent_punch_time, 'name' => 'get_employee_recent_punch_time' ),                        
            array( 'id' => self::$ACTION_get_user_meta_data, 'name' => 'get_user_meta_data' ),                        
            array( 'id' => self::$ACTION_get_employee_punches_by_date, 'name' => 'get_employee_punches_by_date' ),  
            array( 'id' => self::$ACTION_get_employees_monthly_attendance, 'name' => 'get_employees_monthly_attendance' ),
            array( 'id' => self::$ACTION_get_user_rh_stats, 'name' => 'get_user_rh_stats' ),
        );
        return $array;
    }

    // get page by id
    public static function getPageById( $id ){
        $return = false;
        $all = self::getAllPages();
        foreach( $all as $item ){
            if( $item['id'] == $id ){
                $return = $item;
            }
        }
        return $return;
    }

    // get action by id
    public static function getActionById( $id ){
        $return = false;
        $all = self::getAllActions();
        foreach( $all as $item ){
            if( $item['id'] == $id ){
                $return = $item;
            }
        }
        return $return;
    }

    // get notification by id
    public static function getNotificationById( $id ){
        $return = false;
        $all = self::getAllNotifications();
        foreach( $all as $item ){
            if( $item['id'] == $id ){
                $return = $item;
            }
        }
        return $return;
    }

    public static function copyExistingRoleRightsToNewRole( $base_role_id, $new_role_id ){
        $baseRoleData = self::getRoleCompleteDetails( $base_role_id );
        if( $baseRoleData != false && !empty($new_role_id)){
            if( isset( $baseRoleData['role_pages']) && sizeof( $baseRoleData['role_pages'] ) > 0 ){
                $b_pages = $baseRoleData['role_pages'];
                foreach( $b_pages as $b_page ){
                    $b_page_id = $b_page['page_id'];
                    self::addRolePage( $new_role_id, $b_page_id );
                }
            }
            if( isset( $baseRoleData['role_actions']) && sizeof( $baseRoleData['role_actions'] ) > 0 ){
                $b_actions = $baseRoleData['role_actions'];
                foreach( $b_actions as $b_action ){
                    $b_action_id = $b_action['action_id'];
                    self::addRoleAction( $new_role_id, $b_action_id );
                }
            }
            if( isset( $baseRoleData['role_notifications']) && sizeof( $baseRoleData['role_notifications'] ) > 0 ){
                $b_notifications = $baseRoleData['role_notifications'];
                foreach( $b_notifications as $b_notification ){
                    $b_notification_id = $b_notification['notification_id'];
                    self::addRoleNotification( $new_role_id, $b_notification_id );
                }
            }
        }
    }

    public static function AddNewRole($name, $description, $base_role_id = false ) {
        $r_error = 1;
        $r_message = "";
        $q = "SELECT * FROM roles WHERE name ='" . trim($name) . "'";
        $runQuery = self::DBrunQuery($q);
        $no_of_rows = self::DBnumRows($runQuery);
        if ($no_of_rows == 0) {
            $ins = array(
                'name' => $name,
                'description' => $description
            );
            self::DBinsertQuery('roles', $ins);
            $r_error = 0;
            $r_message = "New role added";

            // pick action and pages of base branch and assign it to latest
            if( $base_role_id != false ){
                $q = "SELECT * FROM roles WHERE name ='" . trim($name) . "'";
                $run = self::DBrunQuery($q);
                $inserted_role = self::DBfetchRow($run);
                if( $inserted_role != false && isset( $inserted_role['id']) ){
                    $inserted_role_id = $inserted_role['id'];
                    self::copyExistingRoleRightsToNewRole( $base_role_id, $inserted_role_id );
                }
            }
        } else {
            $r_error = 1;
            $r_message = "Role name already exist";
        }
        $return = array();
        $return['error'] = $r_error;
        $return['message'] = $r_message;
        return $return;
    }


    public static function addRoleAction( $roleid, $actionid ){ // this will add roleid & actionid combination
        $q = "SELECT * FROM roles_actions WHERE role_id = $roleid AND action_id = $actionid";
        //echo $q;
        $runQuery = self::DBrunQuery($q);
        $no_of_rows = self::DBnumRows($runQuery);
        //echo $no_of_rows.'<br>';
        if ($no_of_rows == 0) {
            $ins = array(
                'role_id' => $roleid,
                'action_id' => $actionid
            );
            // echo '<pre>';
            // print_r( $ins );
            self::DBinsertQuery('roles_actions', $ins);
        }
    }

    public static function addRolePage( $roleid, $pageid ){
        $q = "SELECT * FROM roles_pages WHERE role_id = $roleid AND page_id = $pageid";
        $runQuery = self::DBrunQuery($q);
        $no_of_rows = self::DBnumRows($runQuery);
        if ($no_of_rows == 0) {
            $ins = array(
                'role_id' => $roleid,
                'page_id' => $pageid
            );
            self::DBinsertQuery('roles_pages', $ins);
        }
    }

    public static function addRoleNotification( $roleid, $notificationid ){
        $q = "SELECT * FROM roles_notifications WHERE role_id = $roleid AND page_id = $notificationid";
        $runQuery = self::DBrunQuery($q);
        $no_of_rows = self::DBnumRows($runQuery);
        if ($no_of_rows == 0) {
            $ins = array(
                'role_id' => $roleid,
                'notification_id' => $notificationid
            );
            self::DBinsertQuery('roles_pages', $ins);
        }
    }

    public static function removeRoleAction( $roleid, $actionid ){ // this will add roleid & actionid combination
        $q = "DELETE FROM roles_actions WHERE role_id = $roleid AND action_id = $actionid";
        self::DBrunQuery($q);
    }

    public static function addPageActions( $roleid, $pageid ){
        $allPages = self::getAllPages();
        $selectedPage = false;
        foreach( $allPages as $page ){
            if( $page['id']  == $pageid ){
                $selectedPage = $page;
                break;
            }
        }
        if( $selectedPage != false && isset( $selectedPage['actions_list']) ){
            $actionsToAdd = $selectedPage['actions_list'];
            foreach( $actionsToAdd as $ac ){
                self::addRoleAction( $roleid, $ac['id'] );
            }
        }
    }

    public static function removePageActions( $roleid, $pageid ){

        $allPages = self::getAllPages();
        $selectedPage = false;
        foreach( $allPages as $page ){
            if( $page['id']  == $pageid ){
                $selectedPage = $page;
                break;
            }
        }
        if( $selectedPage != false && isset( $selectedPage['actions_list']) ){
            $actionsToRemove = $selectedPage['actions_list'];
            foreach( $actionsToRemove as $ac ){
                self::removeRoleAction( $roleid, $ac['id'] );
            }
        }

    }

    public static function updateRole($data) {
        $r_error = 1;
        $r_message = "";
        $table = "";
        $search = "";
        $role = $data['role_id'];
        $roleid = $role;
        if (isset($data['page_id']) && $data['page_id'] !="") {
            $table = "roles_pages";
            $search = "page_id";
            $pid = $data['page_id'];
        }
        if (isset($data['action_id']) && $data['action_id'] !="") {
            $table = "roles_actions";
            $search = "action_id";
            $pid = $data['action_id'];
        }
        if (isset($data['notification_id']) && $data['notification_id'] !="") {
            $table = "roles_notifications";
            $search = "notification_id";
            $pid = $data['notification_id'];
        }
        if(!empty($table)){
           $q = "SELECT * FROM " . $table . " WHERE role_id = $role AND $search = $pid";
        $runQuery = self::DBrunQuery($q);
        $no_of_rows = self::DBnumRows($runQuery);
        if ($no_of_rows == 0) {
            $ins = array(
                'role_id' => $role,
                $search => $pid
            );
            self::DBinsertQuery($table, $ins);
            // added by arun - if is page then also add page actions
            if( $table == 'roles_pages' ){
                self::addPageActions( $roleid, $pid );
            }
            $r_error = 0;
            $r_message = "Role updated!!";
        } else {
            $q = "DELETE FROM " . $table . " WHERE role_id = $role AND $search = $pid";
            self::DBrunQuery($q);
            if( $table == 'roles_pages' ){
                self::removePageActions( $roleid, $pid );
            }
            $r_error = 0;
            $r_message = "Role updated!!";
        }
        }
        else{
          $r_message = "Empty data passed";
        }

        $return = array();
        $return['error'] = $r_error;
        $return['message'] = $r_message;
        return $return;
    }

    public static function listAllRole() {

        $result = array();
        $allpages = self::getAllPages();
        $allaction = self::getAllActions();
        $allnotification = self::getAllNotifications();
        $result['default_pages'] = $allpages;
        $result['default_actions'] = $allaction;
        $result['default_notifications'] = $allnotification;

        $array = self::getAllRole();
        $array2 = array();
        if (sizeof($array) > 0) {
            foreach ($array as $val) {
                $role_page = self::getRolePages($val['id']);
                $role_action = self::getRoleActions($val['id']);
                $role_notify = self::getRoleNotifications($val['id']);
                foreach ($allpages as $v1) {
                    $p = 0;
                    foreach ($role_page as $u1) {

                        if ($u1['page_id'] == $v1['id']) {
                            $p = 1;
                        }

                    }
                     $v1['is_assigned'] = $p;
                     // start added by arun
                     $updatedActionsList = array();
                     if( isset($v1['actions_list']) ){
                        $updatedActionsList = $v1['actions_list'];
                        foreach( $updatedActionsList as $key => $ual ){
                            $is_assigned = 0;
                            foreach ($role_action as $u2) {
                                if ($u2['action_id'] == $ual['id']) {
                                    $is_assigned = 1;
                                }
                            }
                            $ual['is_assigned'] = $is_assigned;
                            $updatedActionsList[$key] = $ual;
                        }
                     }
                     // end =---------
                     $v1['actions_list'] = $updatedActionsList;

                  $val['role_pages'][] =  $v1;
                }
                foreach ($allaction as $v2) {
                    $p = 0;
                    foreach ($role_action as $u2) {

                        if ($u2['action_id'] == $v2['id']) {
                            $p = 1;
                        }

                    }
                     $v2['is_assigned'] = $p;
                  $val['role_actions'][] =  $v2;
                }
                foreach ($allnotification as $v3) {
                    $p = 0;
                    foreach ($role_notify as $u2) {

                        if ($u2['notification_id'] == $v3['id']) {
                            $p = 1;
                        }


                    }
                      $v3['is_assigned'] = $p;
                     $val['role_notifications'][] = $v3;
                }
                $result['roles'][] = $val;

            }
        }
       $result['users_list'] = self::getEnabledUsersListWithoutPass();

         $return = array();
        $return['error'] = 0;
        $return['data'] = $result;
        return $return;
    }

    public static function getGenericPagesForAllRoles( $roleid = false ){
        $return = array();
        $allPages = self::getAllPages();
        foreach( $allPages as $page ){
            $pid = $page['id'];
            if( $pid == self::$PAGE_login || $pid == self::$PAGE_logout || $pid == self::$PAGE_policy_documents || $pid == self::$PAGE_my_inventory ){
                $new_page = array(
                    'page_id' =>  $page['id'],
                    'page_name' => $page['name']
                );
                $return[] = $new_page;
            }
        }
        return $return;
    }

    public static function sortPages ( $pages ){
        function sortPagesOrder( $a, $b ){
            if ($a['page_id'] == $b['page_id']) {
                return 0;
            }
            return ($a['page_id'] < $b['page_id']) ? -1 : 1;
        }

        usort($pages, "sortPagesOrder");
        return $pages;
    }

    public static function getRolePagesForApiToken( $roleid ){
        $return = self::getGenericPagesForAllRoles();
        $rolePages = self::getRolePages( $roleid );
        if( $rolePages != false ){
            foreach( $rolePages as $rp ){
                //$return[] = $rp['page_name'];
                $return[] = $rp;
            }
        }
        return self::sortPages($return);
    }

    public static function getRolePagesForSuperAdmin ( ){
        $return = self::getGenericPagesForAllRoles();
        $allPages = self::getAllPages( );

        foreach( $allPages as $page ){
            // $pid = $page['id'];
            // if( $pid == self::$PAGE_login || $pid == self::$PAGE_home || $pid == self::$PAGE_logout ){
                $new_page = array(
                    'page_id' =>  $page['id'],
                    'page_name' => $page['name']
                );
                $return[] = $new_page;
            //}
        }
        return self::sortPages($return);
    }

    public static function getRolePages($roleid) {
        $q = "select * from roles_pages where role_id=$roleid";
        $run = self::DBrunQuery($q);
        $rows = self::DBfetchRows($run);
        if( sizeof($rows) > 0 ){
            foreach( $rows as $key => $row ){
                $page = self::getPageById( $row['page_id'] );
                $row['page_name'] = $page['name'];
                $rows[$key] = $row;
            }
        }
        return $rows;
    }

    public static function getRoleActions($roleid) {
        $q = "select * from roles_actions where role_id=$roleid";
        $run = self::DBrunQuery($q);
        $rows = self::DBfetchRows($run);
        if( sizeof($rows) > 0 ){
            foreach( $rows as $key => $row ){
                $action = self::getActionById( $row['action_id'] );
                $row['action_name'] = $action['name'];
                $rows[$key] = $row;
            }
        }
        return $rows;
    }

    public static function getRoleNotifications($roleid) {
        $q = "select * from roles_notifications where role_id=$roleid ";
        $run = self::DBrunQuery($q);
        $rows = self::DBfetchRows($run);
        if( sizeof($rows) > 0 ){
            foreach( $rows as $key => $row ){
                $notification = self::getNotificationById( $row['notification_id'] );
                $row['notification_name'] = $notification['name'];
                $rows[$key] = $row;
            }
        }
        return $rows;
    }

    public static function getAllRole() {

        $q = "Select * from roles";
        $run = self::DBrunQuery($q);
        $rows = self::DBfetchRows($run);

        return $rows;
    }


    public static function assignUserRole($userid,$roleid) {
        $r_error = 1;
        $r_message = "";

        if( $roleid == 0 ){
            // remove user role
            $q = "DELETE FROM user_roles WHERE user_id=$userid";
            $run = self::DBrunQuery($q);
            $r_error = 0;
            $r_message = "User Role removed!!";
        }else{
            // change user role
            $q = "SELECT * FROM user_roles WHERE user_id =".$userid;
            $runQuery = self::DBrunQuery($q);
            $no_of_rows = self::DBnumRows($runQuery);
            if ($no_of_rows == 0) {
                $ins = array(
                    'user_id' => $userid,
                    'role_id' => $roleid
                );
                self::DBinsertQuery('user_roles', $ins);
                $r_error = 0;
                $r_message = "User role assigned!!";
            } else {
                self::DBrunQuery( "UPDATE user_roles SET role_id = '$roleid' WHERE user_id = $userid " );
                $r_error = 0;
                $r_message = "User role changed!!";
            }
        }
        $return = array();
        $return['error'] = $r_error;
        $return['message'] = $r_message;
        return $return;
    }

    // delete role pages
    public static function deleteRolePages($id) {
        $q = "DELETE FROM roles_pages WHERE role_id=$id";
        $run = self::DBrunQuery($q);
        return true;
    }

    // delete role actions
    public static function deleteRoleActions($id) {
        $q = "DELETE FROM roles_actions WHERE role_id=$id";
        $run = self::DBrunQuery($q);
        return true;
    }

    // delete role notifications
    public static function deleteRoleNotifications($id) {
        $q = "DELETE FROM roles_notifications WHERE role_id=$id";
        $run = self::DBrunQuery($q);
        return true;
    }

    // delete role users
    public static function deleteRoleUsers($id) {
        $q = "DELETE FROM user_roles WHERE role_id=$id";
        $run = self::DBrunQuery($q);
        return true;
    }

    // delete role
    public static function deleteRole($id) {
        // remove all linked pages, actions, notification & users;
        self::deleteRolePages( $id );
        self::deleteRoleActions( $id );
        self::deleteRoleNotifications( $id );
        self::deleteRoleUsers( $id );
        $run = self::DBrunQuery( "DELETE FROM roles WHERE id=$id" );
        $return = array();
        $return['error'] = 0;
        $return['message'] = 'Role deleted!!';
        return $return;
    }

    // get role complete details e,g pages, actions, notifications etc
    public static function getRoleCompleteDetails( $roleid ){
        $return = false;
        $q = "SELECT * FROM roles WHERE id=$roleid";
        $run = self::DBrunQuery($q);
        $rows = self::DBfetchRows($run);
        if( sizeof($rows) > 0 ){
            $role = $rows[0];
            $pages = self::getRolePages( $roleid );
            $actions = self::getRoleActions( $roleid );
            $notifications = self::getRoleNotifications( $roleid );
            $role['role_pages'] = $pages;
            $role['role_actions'] = $actions;
            $role['role_notifications'] = $notifications;
            $return =  $role;
        }
        return $return;
    }

    // check if user elc is completed or not
    public static function isUserElcCompleted( $userid ){
        $return = true;
        $employee_life_cycle =  self::getELC( $userid );
        foreach( $employee_life_cycle as $el ) {
            if( isset( $el['steps']) ){
                $el_steps = $el['steps'];
                foreach( $el_steps as $step ){
                    if( $step['status'] == 0 ){
                        $return = false;
                        break;
                    }
                }
            }
        }
        return $return;
    }



}

//new Roles();
?>