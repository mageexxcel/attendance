<?php

//require_once 'c-database.php';

trait Roles {

    //pages
    static $PAGE_inventory_system = 101;
    static $PAGE_manage_payslips = 102;
    static $PAGE_home = 103;
    static $PAGE_monthly_attendance = 104;
    static $PAGE_manage_working_hours = 105;
    static $PAGE_logout = 106;
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


    //notification
    static $NOTIFICATION_apply_leave = 1001;
    static $NOTIFICATION_update_leave_status = 1002;
    static $NOTIFICATION_add_user_working_hours = 1003;


    /////IMPORTANT
    /////name cannot be change since they are used in api calling from frontend
    /////IMPORTANT

    public static function getAllPages() {
        $array = array(
            array( 'id' => self::$PAGE_inventory_system, 'name' => 'inventory_system' ),
            array( 'id' => self::$PAGE_manage_payslips, 'name' => 'manage_payslips' ),
            array( 'id' => self::$PAGE_monthly_attendance, 'name' => 'monthly_attendance' ),
            array( 'id' => self::$PAGE_manage_working_hours, 'name' => 'manage_working_hours' ),
            array( 'id' => self::$PAGE_holidays, 'name' => 'holidays' ),
            array( 'id' => self::$PAGE_team_view, 'name' => 'team_view' ),
            array( 'id' => self::$PAGE_apply_leave, 'name' => 'apply_leave' ),
            array( 'id' => self::$PAGE_manage_leaves, 'name' => 'manage_leaves' ),
            array( 'id' => self::$PAGE_my_leaves, 'name' => 'my_leaves' ),
            array( 'id' => self::$PAGE_disabled_employes, 'name' => 'disabled_employes' ),
            array( 'id' => self::$PAGE_manage_user_working_hours, 'name' => 'manage_user_working_hours' ),
            array( 'id' => self::$PAGE_leaves_summary, 'name' => 'leaves_summary' ),
            array( 'id' => self::$PAGE_salary, 'name' => 'salary' ),
            array( 'id' => self::$PAGE_manage_salary, 'name' => 'manage_salary' ),
            array( 'id' => self::$PAGE_my_profile, 'name' => 'my_profile' ),
            array( 'id' => self::$PAGE_my_inventory, 'name' => 'my_inventory' ),
            array( 'id' => self::$PAGE_manage_users, 'name' => 'manage_users' ),
            array( 'id' => self::$PAGE_manage_clients, 'name' => 'manage_clients' ),
            array( 'id' => self::$PAGE_uploadAttendance, 'name' => 'uploadAttendance' ),
            array( 'id' => self::$PAGE_view_salary, 'name' => 'view_salary' ),
            array( 'id' => self::$PAGE_policy_documents, 'name' => 'policy_documents' ),
            array( 'id' => self::$PAGE_upload_policy_documents, 'name' => 'upload_policy_documents' ),
            array( 'id' => self::$PAGE_add_variables, 'name' => 'add_variables' ),
            array( 'id' => self::$PAGE_mail_templates, 'name' => 'mail_templates' )
        );

        return $array;
    }

    public static function getAllActions() {

        $array = array(
            array( 'id' => self::$ACTION_working_hours_summary,'name' => 'working_hours_summary' ),
            array( 'id' => self::$ACTION_add_new_employee, 'name' => 'add_new_employee' ),
            array( 'id' => self::$ACTION_add_user_working_hours, 'name' => 'add_user_working_hours' ),
            array( 'id' => self::$ACTION_get_user_worktime_detail, 'name' => 'get_user_worktime_detail' ),
            array( 'id' => self::$ACTION_update_user_day_summary, 'name' => 'update_user_day_summary' ),
            array( 'id' => self::$ACTION_change_leave_status, 'name' => 'change_leave_status' ),
            array( 'id' => self::$ACTION_get_my_leaves, 'name' => 'get_my_leaves' ),
            array( 'id' => self::$ACTION_get_enable_user, 'name' => 'get_enable_user' ),
            array( 'id' => self::$ACTION_month_attendance, 'name' => 'month_attendance' ),
            array( 'id' => self::$ACTION_get_all_leaves, 'name' => 'get_all_leaves' ),
            array( 'id' => self::$ACTION_apply_leave, 'name' => 'apply_leave' ),
            array( 'id' => self::$ACTION_show_disabled_users, 'name' => 'show_disabled_users' ),
            array( 'id' => self::$ACTION_change_employee_status, 'name' => 'change_employee_status' ),
            array( 'id' => self::$ACTION_get_holidays_list, 'name' => 'get_holidays_list' ),
            array( 'id' => self::$ACTION_admin_user_apply_leave, 'name' => 'admin_user_apply_leave' ),
            array( 'id' => self::$ACTION_update_new_password, 'name' => 'update_new_password' ),
            array( 'id' => self::$ACTION_get_managed_user_working_hours, 'name' => 'get_managed_user_working_hours' ),
            array( 'id' => self::$ACTION_get_user_previous_month_time, 'name' => 'get_user_previous_month_time' ),
            array( 'id' => self::$ACTION_get_all_user_previous_month_time, 'name' => 'get_all_user_previous_month_time' ),
            array( 'id' => self::$ACTION_update_day_working_hours, 'name' => 'update_day_working_hours' ),
            array( 'id' => self::$ACTION_delete_employee, 'name' => 'delete_employee' ),
            array( 'id' => self::$ACTION_add_hr_comment, 'name' => 'add_hr_comment' ),
            array( 'id' => self::$ACTION_add_extra_leave_day, 'name' => 'add_extra_leave_day' ),
            array( 'id' => self::$ACTION_send_request_for_doc, 'name' => 'send_request_for_doc' ),
            array( 'id' => self::$ACTION_update_user_entry_exit_time, 'name' => 'update_user_entry_exit_time' ),
            array( 'id' => self::$ACTION_save_google_payslip_drive_access_token, 'name' => 'save_google_payslip_drive_access_token' ),
            
            array( 'id' => self::$ACTION_delete_role, 'name' => 'delete_role' ),
            array( 'id' => self::$ACTION_assign_user_role, 'name' => 'assign_user_role' ),
            array( 'id' => self::$ACTION_list_all_roles, 'name' => 'list_all_roles' ),
            array( 'id' => self::$ACTION_update_role, 'name' => 'update_role' ),
            array( 'id' => self::$ACTION_add_roles, 'name' => 'add_roles' ),

            array( 'id' => self::$ACTION_get_machine_count, 'name' => 'get_machine_count' ),
            array( 'id' => self::$ACTION_get_machine_status_list, 'name' => 'get_machine_status_list' ),
            array( 'id' => self::$ACTION_add_machine_status, 'name' => 'add_machine_status' ),
            array( 'id' => self::$ACTION_add_machine_type, 'name' => 'add_machine_type' ),
            array( 'id' => self::$ACTION_get_machine_type_list, 'name' => 'get_machine_type_list' ),
            array( 'id' => self::$ACTION_delete_machine_status, 'name' => 'delete_machine_status' ),
            array( 'id' => self::$ACTION_add_office_machine, 'name' => 'add_office_machine' ),
            array( 'id' => self::$ACTION_update_office_machine, 'name' => 'update_office_machine' ),
            array( 'id' => self::$ACTION_get_machine, 'name' => 'get_machine' ),
            array( 'id' => self::$ACTION_get_machines_detail, 'name' => 'get_machines_detail' ),
            array( 'id' => self::$ACTION_remove_machine_detail, 'name' => 'remove_machine_detail' ),
            array( 'id' => self::$ACTION_assign_user_machine, 'name' => 'assign_user_machine' ),
            array( 'id' => self::$ACTION_get_user_machine, 'name' => 'get_user_machine' ),
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

    public static function AddNewRole($name, $description) {
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
        } else {
            $r_error = 1;
            $r_message = "Role name already exist";
        }
        $return = array();
        $return['error'] = $r_error;
        $return['message'] = $r_message;
        return $return;
    }

    public static function updateRole($data) {
        $r_error = 1;
        $r_message = "";
        $table = "";
        $search = "";
        $role = $data['role_id'];
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
            $r_error = 0;
            $r_message = "Role updated!!";
        } else {
            $q = "DELETE FROM " . $table . " WHERE role_id = $role AND $search = $pid";
            self::DBrunQuery($q);
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

    public static function getGenericPagesForAllRoles( $roleid ){
        return array(
            'login',
            'logout',
            'home'
        );
    }

    public static function getRolePagesForApiToken( $roleid ){
        $return = self::getGenericPagesForAllRoles();
        $rolePages = self::getRolePages( $roleid );
        if( $rolePages != false ){
            foreach( $rolePages as $rp ){
                $return[] = $rp['page_name'];
            }
        }
        return $return;
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
    
    

}

//new Roles();
?>